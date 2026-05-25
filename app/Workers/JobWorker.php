<?php

declare(strict_types=1);

namespace Ailhost\Workers;

use Ailhost\Adapters\DnsAdapter;
use Ailhost\Adapters\NginxAdapter;
use Ailhost\Adapters\SslAdapter;
use Ailhost\Adapters\PhpAdapter;
use Ailhost\Adapters\MariaDbAdapter;
use Ailhost\Adapters\WordPressAdapter;
use Ailhost\Adapters\BackupAdapter;
use Ailhost\Adapters\ProcessSupervisorAdapter;
use Ailhost\Adapters\PackageAdapter;
use Ailhost\Services\JobService;
use Ailhost\Services\ServiceOpsService;
use Ailhost\Services\SiteService;
use Throwable;

final class JobWorker implements WorkerInterface
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly SiteService $siteService,
        private readonly NginxAdapter $nginxAdapter,
        private readonly DnsAdapter $dnsAdapter,
        private readonly SslAdapter $sslAdapter,
        private readonly PhpAdapter $phpAdapter,
        private readonly MariaDbAdapter $mariaDbAdapter,
        private readonly WordPressAdapter $wordpressAdapter,
        private readonly BackupAdapter $backupAdapter,
        private readonly ProcessSupervisorAdapter $processSupervisorAdapter,
        private readonly PackageAdapter $packageAdapter,
        private readonly ServiceOpsService $serviceOpsService
    ) {
    }

    public function run(): void
    {
        $job = $this->jobService->claimNext();
        if ($job === null) {
            return;
        }

        $jobId = (string) ($job['id'] ?? '');
        $type = (string) ($job['type'] ?? 'unknown');
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];

        try {
            $this->process($jobId, $type, $payload);
            $this->jobService->complete($jobId);
        } catch (Throwable $exception) {
            $this->jobService->appendLog($jobId, 'error', 'İş hatası: ' . $exception->getMessage());
            $siteId = (string) ($payload['site_id'] ?? '');
            $domain = (string) ($payload['domain'] ?? '');
            if ($type === 'deploy_run' && $siteId !== '') {
                $this->siteService->saveRuntimeState($siteId, [
                    'status' => 'failed',
                    'last_error' => $exception->getMessage(),
                    'last_job_id' => $jobId,
                    'last_job_type' => 'deploy_run',
                ]);
            }
            if ($type === 'site_create' && $siteId !== '' && $domain !== '') {
                $this->rollbackSiteCreate($jobId, $siteId, $domain);
            }
            $this->jobService->fail($jobId, $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function process(string $jobId, string $type, array $payload): void
    {
        if ($type === 'site_create') {
            $this->processSiteCreate($jobId, $payload);
            return;
        }
        if ($type === 'domain_add') {
            $this->processDomainAdd($jobId, $payload);
            return;
        }
        if ($type === 'domain_delete') {
            $this->processDomainDelete($jobId, $payload);
            return;
        }
        if ($type === 'site_delete') {
            $this->processSiteDelete($jobId, $payload);
            return;
        }
        if ($type === 'site_php_update') {
            $this->assertOk($this->phpAdapter->execute(['action' => 'apply_site_profile'] + $payload), 'PHP profil uygulama başarısız.');
            $this->jobService->appendLog($jobId, 'info', 'PHP profil güncellemesi tamamlandı.');
            return;
        }
        if ($type === 'db_create') {
            $this->assertOk($this->mariaDbAdapter->execute(['action' => 'create_database'] + $payload), 'Veritabanı oluşturma başarısız.');
            $this->jobService->appendLog($jobId, 'info', 'Veritabanı oluşturma tamamlandı.');
            return;
        }
        if ($type === 'db_delete') {
            $this->assertOk($this->mariaDbAdapter->execute(['action' => 'delete_database'] + $payload), 'Veritabanı silme başarısız.');
            $this->jobService->appendLog($jobId, 'info', 'Veritabanı silme tamamlandı.');
            return;
        }
        if ($type === 'db_user_reset') {
            $this->assertOk($this->mariaDbAdapter->execute(['action' => 'reset_user_password'] + $payload), 'DB kullanıcı şifre sıfırlama başarısız.');
            $this->jobService->appendLog($jobId, 'info', 'DB kullanıcı şifre sıfırlama tamamlandı.');
            return;
        }
        if ($type === 'wp_install') {
            $this->assertOk($this->wordpressAdapter->execute(['action' => 'install'] + $payload), 'WordPress kurulum başarısız.');
            $siteId = (string) ($payload['site_id'] ?? '');
            $site = $this->siteService->findSite($siteId);
            $domain = (string) ($payload['domain'] ?? '');
            $this->siteService->saveWordPressData($siteId, [
                'installed' => true,
                'url' => 'https://' . $domain,
                'admin_user' => (string) ($payload['admin_user'] ?? ''),
                'admin_email' => (string) ($payload['admin_email'] ?? ''),
                'core_version' => '6.6',
                'maintenance' => false,
                'plugins' => ['akismet', 'hello-dolly'],
                'active_theme' => 'twentytwentyfour',
                'document_root' => (string) ($site['document_root'] ?? ''),
                'updated_at' => date(DATE_ATOM),
            ]);
            $this->jobService->appendLog($jobId, 'info', 'WordPress kurulumu tamamlandı.');
            return;
        }
        if ($type === 'wp_plugin_install' || $type === 'wp_plugin_delete') {
            $action = $type === 'wp_plugin_install' ? 'plugin_install' : 'plugin_delete';
            $this->assertOk($this->wordpressAdapter->execute(['action' => $action] + $payload), 'Plugin işlemi başarısız.');
            $siteId = (string) ($payload['site_id'] ?? '');
            $plugin = (string) ($payload['plugin'] ?? '');
            $wp = $this->siteService->wordpressData($siteId);
            $plugins = array_values(array_unique(array_map('strval', is_array($wp['plugins'] ?? null) ? $wp['plugins'] : [])));
            if ($type === 'wp_plugin_install' && $plugin !== '' && !in_array($plugin, $plugins, true)) {
                $plugins[] = $plugin;
            }
            if ($type === 'wp_plugin_delete') {
                $plugins = array_values(array_filter($plugins, static fn(string $p): bool => $p !== $plugin));
            }
            $wp['plugins'] = $plugins;
            $wp['updated_at'] = date(DATE_ATOM);
            $this->siteService->saveWordPressData($siteId, $wp);
            $this->jobService->appendLog($jobId, 'info', 'Plugin işlemi tamamlandı: ' . $plugin);
            return;
        }
        if ($type === 'wp_theme_activate') {
            $this->assertOk($this->wordpressAdapter->execute(['action' => 'theme_activate'] + $payload), 'Tema aktivasyonu başarısız.');
            $siteId = (string) ($payload['site_id'] ?? '');
            $wp = $this->siteService->wordpressData($siteId);
            $wp['active_theme'] = (string) ($payload['theme'] ?? '');
            $wp['updated_at'] = date(DATE_ATOM);
            $this->siteService->saveWordPressData($siteId, $wp);
            $this->jobService->appendLog($jobId, 'info', 'Tema aktivasyonu tamamlandı.');
            return;
        }
        if ($type === 'wp_core_update') {
            $this->assertOk($this->wordpressAdapter->execute(['action' => 'core_update'] + $payload), 'WordPress core update başarısız.');
            $siteId = (string) ($payload['site_id'] ?? '');
            $wp = $this->siteService->wordpressData($siteId);
            $wp['core_version'] = '6.7';
            $wp['updated_at'] = date(DATE_ATOM);
            $this->siteService->saveWordPressData($siteId, $wp);
            $this->jobService->appendLog($jobId, 'info', 'WordPress core update tamamlandı.');
            return;
        }
        if ($type === 'wp_maintenance_toggle') {
            $this->assertOk($this->wordpressAdapter->execute(['action' => 'maintenance_toggle'] + $payload), 'Maintenance toggle başarısız.');
            $siteId = (string) ($payload['site_id'] ?? '');
            $wp = $this->siteService->wordpressData($siteId);
            $wp['maintenance'] = (bool) ($payload['enabled'] ?? false);
            $wp['updated_at'] = date(DATE_ATOM);
            $this->siteService->saveWordPressData($siteId, $wp);
            $this->jobService->appendLog($jobId, 'info', 'Maintenance durumu güncellendi.');
            return;
        }
        if ($type === 'backup_create') {
            $create = $this->backupAdapter->execute(['action' => 'create'] + $payload);
            $this->assertOk($create, 'Backup oluşturma başarısız.');
            $this->siteService->saveBackupRecord([
                'id' => (string) ($payload['backup_id'] ?? bin2hex(random_bytes(8))),
                'site_id' => (string) ($payload['site_id'] ?? ''),
                'type' => (string) ($payload['type'] ?? 'full'),
                'created_at' => date(DATE_ATOM),
                'file' => (string) ($create['file'] ?? ''),
                'size_bytes' => (int) ($create['size_bytes'] ?? 0),
                'checksum_sha256' => (string) ($create['checksum_sha256'] ?? ''),
                'integrity_ok' => true,
            ]);
            $this->jobService->appendLog($jobId, 'info', 'Backup oluşturma tamamlandı.');
            return;
        }
        if ($type === 'backup_restore') {
            $siteId = (string) ($payload['site_id'] ?? '');
            $backupId = (string) ($payload['backup_id'] ?? '');
            $backup = $this->siteService->findBackup($siteId, $backupId);
            if ($backup === null) {
                throw new \RuntimeException('Restore backup kaydı bulunamadı.');
            }
            $integrity = $this->siteService->verifyBackupIntegrity($backup);
            if (($integrity['ok'] ?? false) !== true) {
                throw new \RuntimeException('Restore öncesi backup bütünlük kontrolü başarısız: ' . (string) ($integrity['message'] ?? ''));
            }
            $this->assertOk($this->backupAdapter->execute(['action' => 'restore'] + $payload), 'Restore başarısız.');
            $this->jobService->appendLog($jobId, 'info', 'Restore tamamlandı.');
            return;
        }
        if ($type === 'backup_restore_dry_run') {
            $siteId = (string) ($payload['site_id'] ?? '');
            $backupId = (string) ($payload['backup_id'] ?? '');
            $backup = $this->siteService->findBackup($siteId, $backupId);
            if ($backup === null) {
                throw new \RuntimeException('Dry-run backup kaydı bulunamadı.');
            }
            $integrity = $this->siteService->verifyBackupIntegrity($backup);
            if (($integrity['ok'] ?? false) !== true) {
                $this->siteService->markBackupIntegrity($siteId, $backupId, false);
                $this->siteService->markBackupDryRun($siteId, $backupId, false);
                throw new \RuntimeException('Dry-run öncesi bütünlük doğrulaması başarısız: ' . (string) ($integrity['message'] ?? ''));
            }
            $this->assertOk($this->backupAdapter->execute(['action' => 'restore_dry_run'] + $payload), 'Dry-run başarısız.');
            $this->siteService->markBackupDryRun($siteId, $backupId, true);
            $this->jobService->appendLog($jobId, 'info', 'Restore dry-run tamamlandı.');
            return;
        }
        if ($type === 'backup_retention_cleanup') {
            $siteId = (string) ($payload['site_id'] ?? '');
            $keepLast = (int) ($payload['keep_last'] ?? 5);
            $removed = $this->siteService->pruneBackupsByKeepLast($siteId, $keepLast);
            $this->jobService->appendLog($jobId, 'info', 'Backup retention cleanup tamamlandı. Silinen kayıt: ' . (string) $removed);
            return;
        }
        if ($type === 'deploy_run') {
            $this->processDeployRun($jobId, $payload);
            return;
        }
        if ($type === 'runtime_start' || $type === 'runtime_stop' || $type === 'runtime_restart') {
            $this->processRuntimeControl($jobId, $type, $payload);
            return;
        }
        if ($type === 'proxy_apply') {
            $this->processProxyApply($jobId, $payload);
            return;
        }
        if ($type === 'dns_apply') {
            $this->processDnsApply($jobId, $payload);
            return;
        }
        if ($type === 'ssl_issue' || $type === 'ssl_renew') {
            $this->processSslAction($jobId, $type, $payload);
            return;
        }
        if ($type === 'install_package') {
            $this->processInstallPackage($jobId, $payload);
            return;
        }
        if ($type === 'remove_package') {
            $this->processRemovePackage($jobId, $payload);
            return;
        }
        if ($type === 'service_control') {
            $this->processServiceControl($jobId, $payload);
            return;
        }
        if ($type === 'docker_image_pull') {
            $this->processDockerImagePull($jobId, $payload);
            return;
        }

        $this->jobService->appendLog($jobId, 'info', 'İş işlendi: ' . $type);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processInstallPackage(string $jobId, array $payload): void
    {
        $package = trim((string) ($payload['package'] ?? ''));
        if ($package === '') {
            throw new \RuntimeException('install_package payload eksik.');
        }
        $this->jobService->appendLog($jobId, 'info', 'Paket kontrolü başladı: ' . $package);
        $result = $this->packageAdapter->execute([
            'action' => 'ensure_installed',
            'package' => $package,
        ]);
        $this->assertOk($result, 'Paket kurulumu başarısız: ' . $package);
        $this->jobService->appendLog($jobId, 'info', (string) ($result['message'] ?? 'Paket işlendi.') . ' (' . $package . ')');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processRemovePackage(string $jobId, array $payload): void
    {
        $package = trim((string) ($payload['package'] ?? ''));
        if ($package === '') {
            throw new \RuntimeException('remove_package payload eksik.');
        }
        $this->jobService->appendLog($jobId, 'info', 'Paket kaldırma başladı: ' . $package);
        $result = $this->packageAdapter->execute([
            'action' => 'ensure_removed',
            'package' => $package,
        ]);
        $this->assertOk($result, 'Paket kaldırma başarısız: ' . $package);
        $service = trim((string) ($payload['service'] ?? ''));
        if ($service !== '') {
            $this->serviceOpsService->clearState($service);
        }
        $this->jobService->appendLog($jobId, 'info', (string) ($result['message'] ?? 'Paket kaldırıldı.') . ' (' . $package . ')');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processServiceControl(string $jobId, array $payload): void
    {
        $service = (string) ($payload['service'] ?? '');
        $action = (string) ($payload['action'] ?? '');
        if ($service === '' || $action === '') {
            throw new \RuntimeException('service_control payload eksik.');
        }
        $this->jobService->appendLog($jobId, 'info', 'Servis aksiyonu başladı: ' . $service . ' / ' . $action);
        $result = $this->serviceOpsService->applyControl($service, $action);
        $this->assertOk($result, 'Servis aksiyonu başarısız: ' . $service);
        $this->jobService->appendLog($jobId, 'info', 'Servis aksiyonu tamamlandı: ' . $service . ' / ' . $action);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processDockerImagePull(string $jobId, array $payload): void
    {
        $image = trim((string) ($payload['image'] ?? ''));
        if ($image === '') {
            throw new \RuntimeException('docker_image_pull payload eksik.');
        }
        $this->jobService->appendLog($jobId, 'info', 'Docker image pull başladı: ' . $image);
        $result = $this->serviceOpsService->processDockerImagePull($image);
        $this->assertOk($result, 'Docker image pull başarısız: ' . $image);
        $this->jobService->appendLog($jobId, 'info', 'Docker image pull tamamlandı: ' . $image);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processSiteCreate(string $jobId, array $payload): void
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        $domain = (string) ($payload['domain'] ?? '');
        $documentRoot = (string) ($payload['document_root'] ?? '');
        $serverIp = (string) ($payload['server_ip'] ?? '');
        $provisionDns = ((bool) ($payload['provision_dns'] ?? true)) === true;
        $provisionSsl = ((bool) ($payload['provision_ssl'] ?? true)) === true;

        if ($siteId === '' || $domain === '' || $documentRoot === '' || $serverIp === '') {
            throw new \RuntimeException('site_create payload eksik.');
        }

        $this->siteService->updateSiteStatus($siteId, 'running');
        $this->jobService->appendLog($jobId, 'info', 'Website provisioning başladı.');

        $nginxConfig = $this->nginxAdapter->execute([
            'action' => 'create_site_config',
            'domain' => $domain,
            'document_root' => $documentRoot,
        ]);
        $this->assertOk($nginxConfig, 'Nginx config üretimi başarısız.');

        $nginxTest = $this->nginxAdapter->execute([
            'action' => 'test_config',
            'config_file' => (string) ($nginxConfig['config_file'] ?? ''),
        ]);
        $this->assertOk($nginxTest, 'Nginx config doğrulaması başarısız.');

        if ($provisionDns) {
            $dns = $this->dnsAdapter->execute([
                'action' => 'create_zone',
                'domain' => $domain,
                'server_ip' => $serverIp,
            ]);
            $this->assertOk($dns, 'DNS zone oluşturma başarısız.');
        } else {
            $this->jobService->appendLog($jobId, 'info', 'DNS provisioning atlandı.');
        }

        if ($provisionSsl) {
            $ssl = $this->sslAdapter->execute([
                'action' => 'issue_letsencrypt',
                'domain' => $domain,
            ]);
            $this->assertOk($ssl, 'SSL alma işlemi başarısız.');
        } else {
            $this->jobService->appendLog($jobId, 'info', 'SSL provisioning atlandı.');
        }

        $reloadNginx = $this->nginxAdapter->execute(['action' => 'reload']);
        $this->assertOk($reloadNginx, 'Nginx reload başarısız.');
        if ($provisionDns) {
            $reloadDns = $this->dnsAdapter->execute(['action' => 'reload']);
            $this->assertOk($reloadDns, 'DNS reload başarısız.');
        }

        $this->siteService->updateSiteStatus($siteId, 'active');
        $this->jobService->appendLog($jobId, 'info', 'Website provisioning tamamlandı.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processDomainAdd(string $jobId, array $payload): void
    {
        $domain = (string) ($payload['domain'] ?? '');
        $serverIp = (string) ($payload['server_ip'] ?? '');
        if ($domain === '' || $serverIp === '') {
            throw new \RuntimeException('domain_add payload eksik.');
        }

        $this->jobService->appendLog($jobId, 'info', 'Domain ekleme başladı: ' . $domain);
        $dns = $this->dnsAdapter->execute([
            'action' => 'create_zone',
            'domain' => $domain,
            'server_ip' => $serverIp,
        ]);
        $this->assertOk($dns, 'DNS zone oluşturma başarısız.');

        $ssl = $this->sslAdapter->execute([
            'action' => 'issue_letsencrypt',
            'domain' => $domain,
        ]);
        $this->assertOk($ssl, 'SSL alma işlemi başarısız.');

        $this->jobService->appendLog($jobId, 'info', 'Domain ekleme tamamlandı: ' . $domain);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processDomainDelete(string $jobId, array $payload): void
    {
        $domain = (string) ($payload['domain'] ?? '');
        if ($domain === '') {
            throw new \RuntimeException('domain_delete payload eksik.');
        }

        $this->jobService->appendLog($jobId, 'info', 'Domain silme başladı: ' . $domain);
        $dns = $this->dnsAdapter->execute([
            'action' => 'delete_zone',
            'domain' => $domain,
        ]);
        $this->assertOk($dns, 'DNS zone silme başarısız.');

        $ssl = $this->sslAdapter->execute([
            'action' => 'revoke_certificate',
            'domain' => $domain,
        ]);
        $this->assertOk($ssl, 'SSL iptal başarısız.');

        $this->jobService->appendLog($jobId, 'info', 'Domain silme tamamlandı: ' . $domain);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processSiteDelete(string $jobId, array $payload): void
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        if ($siteId === '') {
            throw new \RuntimeException('site_delete payload eksik.');
        }

        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            throw new \RuntimeException('Silinecek site bulunamadı.');
        }

        $siteDomains = $this->siteService->domainsBySite($siteId);
        $this->jobService->appendLog($jobId, 'info', 'Site silme başladı: ' . (string) ($site['domain'] ?? $siteId));

        foreach ($siteDomains as $domainRow) {
            $domain = (string) ($domainRow['domain'] ?? '');
            if ($domain === '') {
                continue;
            }
            $this->assertOk($this->dnsAdapter->execute([
                'action' => 'delete_zone',
                'domain' => $domain,
            ]), 'DNS zone silme başarısız.');
            $this->assertOk($this->sslAdapter->execute([
                'action' => 'revoke_certificate',
                'domain' => $domain,
            ]), 'SSL iptal başarısız.');
        }

        $primaryDomain = (string) ($site['domain'] ?? '');
        if ($primaryDomain !== '') {
            $this->assertOk($this->nginxAdapter->execute([
                'action' => 'delete_site_config',
                'domain' => $primaryDomain,
            ]), 'Nginx config silme başarısız.');
        }

        if (!$this->siteService->finalizeSiteDelete($siteId)) {
            throw new \RuntimeException('Site kayıtları silinemedi.');
        }

        $this->jobService->appendLog($jobId, 'info', 'Site silme tamamlandı: ' . ($primaryDomain !== '' ? $primaryDomain : $siteId));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertOk(array $result, string $fallbackMessage): void
    {
        if (($result['ok'] ?? false) === true) {
            return;
        }

        throw new \RuntimeException((string) ($result['message'] ?? $fallbackMessage));
    }

    private function rollbackSiteCreate(string $jobId, string $siteId, string $domain): void
    {
        $this->nginxAdapter->execute(['action' => 'delete_site_config', 'domain' => $domain]);
        $this->dnsAdapter->execute(['action' => 'delete_zone', 'domain' => $domain]);
        $this->siteService->rollbackSite($siteId);
        $this->jobService->appendLog($jobId, 'error', 'Hata sonrası rollback uygulandı.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processDeployRun(string $jobId, array $payload): void
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        $sourceType = trim((string) ($payload['source_type'] ?? ''));
        $sourceValue = trim((string) ($payload['source_value'] ?? ''));
        $buildCommand = trim((string) ($payload['build_command'] ?? ''));
        $startCommand = trim((string) ($payload['start_command'] ?? ''));
        $port = (int) ($payload['port'] ?? 0);

        if ($siteId === '') {
            throw new \RuntimeException('deploy_run payload: site_id eksik.');
        }
        if (!in_array($sourceType, ['manual', 'directory', 'git', 'zip'], true)) {
            throw new \RuntimeException('deploy_run payload: kaynak tipi desteklenmiyor.');
        }
        if ($port < 1 || $port > 65535) {
            throw new \RuntimeException('deploy_run payload: port geçersiz.');
        }

        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            throw new \RuntimeException('Deploy için site bulunamadı.');
        }

        $documentRoot = trim((string) ($site['document_root'] ?? ''));
        if ($documentRoot === '' || !is_dir($documentRoot)) {
            throw new \RuntimeException('Deploy için document root bulunamadı: ' . $documentRoot);
        }

        $this->jobService->appendLog($jobId, 'info', 'Deploy başlatıldı. Kaynak: ' . $sourceType . ', Port: ' . (string) $port);

        if ($sourceType === 'directory') {
            if ($sourceValue === '' || !is_dir($sourceValue)) {
                throw new \RuntimeException('Directory kaynak dizini bulunamadı.');
            }
            $this->jobService->appendLog($jobId, 'info', 'Directory kaynak doğrulandı: ' . $sourceValue);
        } elseif ($sourceType === 'git') {
            if ($sourceValue === '') {
                throw new \RuntimeException('Git kaynak adresi zorunlu.');
            }
            if (!preg_match('/^(https?:\/\/|git@)[^\s]+$/', $sourceValue)) {
                throw new \RuntimeException('Git kaynak adresi formatı geçersiz.');
            }
            $this->jobService->appendLog($jobId, 'info', 'Git kaynak doğrulandı: ' . $sourceValue);
            $this->jobService->appendLog($jobId, 'info', 'Git clone/pull adımı bir sonraki stepte etkinleştirilecek.');
        } elseif ($sourceType === 'zip') {
            if ($sourceValue === '') {
                throw new \RuntimeException('ZIP kaynak yolu zorunlu.');
            }
            $this->jobService->appendLog($jobId, 'info', 'ZIP kaynak yolu doğrulandı: ' . $sourceValue);
            $this->jobService->appendLog($jobId, 'info', 'ZIP extract adımı bir sonraki stepte etkinleştirilecek.');
        } else {
            $this->jobService->appendLog($jobId, 'info', 'Manual kaynak seçimi kullanılıyor.');
        }

        if ($buildCommand !== '') {
            $this->jobService->appendLog($jobId, 'info', 'Build komutu çalıştırılıyor: ' . $buildCommand);
            $this->runCommandWithLogs($jobId, $documentRoot, $buildCommand);
            $this->jobService->appendLog($jobId, 'info', 'Build komutu tamamlandı.');
        } else {
            $this->jobService->appendLog($jobId, 'info', 'Build komutu boş, atlandı.');
        }

        if ($startCommand !== '') {
            $this->jobService->appendLog($jobId, 'info', 'Start komutu kaydedildi: ' . $startCommand);
            $this->jobService->appendLog($jobId, 'info', 'Start/stop process yönetimi bir sonraki stepte etkinleştirilecek.');
        } else {
            $this->jobService->appendLog($jobId, 'info', 'Start komutu boş.');
        }

        $this->siteService->saveRuntimeState($siteId, [
            'status' => 'deployed',
            'last_error' => '',
            'last_job_id' => $jobId,
            'last_job_type' => 'deploy_run',
            'last_deploy_at' => date(DATE_ATOM),
            'source_type' => $sourceType,
            'source_value' => $sourceValue,
            'build_command' => $buildCommand,
            'start_command' => $startCommand,
            'port' => $port,
            'document_root' => $documentRoot,
        ]);

        $this->jobService->appendLog($jobId, 'info', 'Deploy işlemi tamamlandı.');
    }

    private function runCommandWithLogs(string $jobId, string $workdir, string $command): void
    {
        $script = 'cd ' . escapeshellarg($workdir) . ' && ' . $command;
        $output = [];
        $exitCode = 0;
        exec('bash -lc ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

        $maxLines = 40;
        $lines = array_slice($output, 0, $maxLines);
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }
            $this->jobService->appendLog($jobId, 'info', $trimmed);
        }
        if (count($output) > $maxLines) {
            $this->jobService->appendLog($jobId, 'info', '... çıktı kesildi (' . (string) count($output) . ' satır).');
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException('Komut başarısız oldu: ' . $command . ' (exit=' . (string) $exitCode . ')');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processRuntimeControl(string $jobId, string $type, array $payload): void
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        if ($siteId === '') {
            throw new \RuntimeException('runtime kontrol payload: site_id eksik.');
        }

        $action = match ($type) {
            'runtime_start' => 'start',
            'runtime_stop' => 'stop',
            'runtime_restart' => 'restart',
            default => '',
        };
        if ($action === '') {
            throw new \RuntimeException('Geçersiz runtime kontrol tipi.');
        }

        $this->jobService->appendLog($jobId, 'info', 'Runtime işlem başlatıldı: ' . $action);
        $result = $this->processSupervisorAdapter->execute([
            'action' => $action,
            'site_id' => $siteId,
            'start_command' => (string) ($payload['start_command'] ?? ''),
            'port' => (int) ($payload['port'] ?? 0),
        ]);
        $this->assertOk($result, 'Runtime kontrol işlemi başarısız.');

        $status = (string) ($result['status'] ?? ($action === 'stop' ? 'stopped' : 'running'));
        $this->siteService->saveRuntimeState($siteId, [
            'status' => $status,
            'last_error' => '',
            'last_job_id' => $jobId,
            'last_job_type' => $type,
            'start_command' => (string) ($payload['start_command'] ?? ''),
            'port' => (int) ($payload['port'] ?? 0),
        ]);
        $this->jobService->appendLog($jobId, 'info', 'Runtime işlem tamamlandı: ' . $action . ' -> ' . $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processProxyApply(string $jobId, array $payload): void
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        if ($siteId === '') {
            throw new \RuntimeException('proxy_apply payload: site_id eksik.');
        }
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            throw new \RuntimeException('Proxy apply için site bulunamadı.');
        }

        $domain = (string) ($site['domain'] ?? '');
        $documentRoot = (string) ($site['document_root'] ?? '');
        if ($domain === '' || $documentRoot === '') {
            throw new \RuntimeException('Proxy apply için domain/document_root eksik.');
        }

        $routes = $this->siteService->proxyRoutesBySite($siteId);
        $configFile = AILHOST_ROOT . '/var/generated/nginx/' . $domain . '.conf';
        $previousConfig = is_file($configFile) ? (string) file_get_contents($configFile) : null;

        try {
            $this->jobService->appendLog($jobId, 'info', 'Proxy apply başladı. Route sayısı: ' . (string) count($routes));
            $create = $this->nginxAdapter->execute([
                'action' => 'create_site_config',
                'domain' => $domain,
                'document_root' => $documentRoot,
                'proxy_routes' => $routes,
            ]);
            $this->assertOk($create, 'Nginx config üretimi başarısız.');

            $test = $this->nginxAdapter->execute([
                'action' => 'test_config',
                'config_file' => (string) ($create['config_file'] ?? ''),
            ]);
            $this->assertOk($test, 'Nginx config doğrulaması başarısız.');

            $reload = $this->nginxAdapter->execute(['action' => 'reload']);
            $this->assertOk($reload, 'Nginx reload başarısız.');

            $this->jobService->appendLog($jobId, 'info', 'Proxy apply tamamlandı.');
        } catch (Throwable $exception) {
            if ($previousConfig !== null) {
                @file_put_contents($configFile, $previousConfig);
                $this->jobService->appendLog($jobId, 'error', 'Nginx config geri yüklendi.');
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processSslAction(string $jobId, string $type, array $payload): void
    {
        $domain = (string) ($payload['domain'] ?? '');
        if ($domain === '') {
            throw new \RuntimeException('ssl payload: domain eksik.');
        }
        $action = $type === 'ssl_renew' ? 'renew_letsencrypt' : 'issue_letsencrypt';
        $this->jobService->appendLog($jobId, 'info', 'SSL işlemi başladı: ' . $domain . ' / ' . $action);
        $result = $this->sslAdapter->execute([
            'action' => $action,
            'domain' => $domain,
        ]);
        $this->assertOk($result, 'SSL işlemi başarısız.');
        $this->jobService->appendLog($jobId, 'info', 'SSL işlemi tamamlandı: ' . $domain);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processDnsApply(string $jobId, array $payload): void
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        $domain = (string) ($payload['domain'] ?? '');
        $serverIp = (string) ($payload['server_ip'] ?? '');
        if ($siteId === '' || $domain === '' || $serverIp === '') {
            throw new \RuntimeException('dns_apply payload eksik.');
        }
        $this->jobService->appendLog($jobId, 'info', 'DNS zone apply başladı: ' . $domain);
        $records = $this->siteService->dnsRecordsBySite($siteId);
        $apply = $this->dnsAdapter->execute([
            'action' => 'apply_zone_records',
            'domain' => $domain,
            'server_ip' => $serverIp,
            'records' => $records,
        ]);
        $this->assertOk($apply, 'DNS zone apply başarısız.');
        $reload = $this->dnsAdapter->execute(['action' => 'reload']);
        $this->assertOk($reload, 'DNS reload başarısız.');
        $this->jobService->appendLog($jobId, 'info', 'DNS zone apply tamamlandı: ' . $domain);
    }
}
