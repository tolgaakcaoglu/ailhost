<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class SiteService
{
    public function __construct(
        private readonly array $paths,
        private readonly InstallService $installService,
        private readonly JobService $jobService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allSites(): array
    {
        $decoded = $this->readJson($this->sitesFile());
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allDomains(): array
    {
        $decoded = $this->readJson($this->domainsFile());
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSite(string $siteId): ?array
    {
        foreach ($this->allSites() as $site) {
            if (($site['id'] ?? '') === $siteId) {
                return $site;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createSite(string $domain): array
    {
        return $this->createSiteFromWizard([
            'domain' => $domain,
            'runtime' => 'php',
            'web_server' => 'nginx',
            'document_root' => '',
            'provision_dns' => true,
            'provision_ssl' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createSiteFromWizard(array $input): array
    {
        $domain = mb_strtolower(trim((string) ($input['domain'] ?? '')));
        $runtime = trim((string) ($input['runtime'] ?? 'php'));
        $webServer = trim((string) ($input['web_server'] ?? 'nginx'));
        $documentRootInput = trim((string) ($input['document_root'] ?? ''));
        $provisionDns = ((bool) ($input['provision_dns'] ?? true)) === true;
        $provisionSsl = ((bool) ($input['provision_ssl'] ?? true)) === true;

        $domain = mb_strtolower(trim($domain));
        if (!$this->isValidDomain($domain)) {
            return ['ok' => false, 'message' => 'Geçerli bir alan adı girin.'];
        }
        if (!in_array($runtime, ['php', 'node'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz runtime seçimi.'];
        }
        if (!in_array($webServer, ['nginx', 'openlitespeed'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz web sunucusu seçimi.'];
        }

        $sites = $this->allSites();
        foreach ($sites as $site) {
            if (($site['domain'] ?? '') === $domain) {
                return ['ok' => false, 'message' => 'Bu alan adı zaten kayıtlı.'];
            }
        }

        $settings = $this->installService->settingsData();
        $serverIp = (string) ($settings['server_ip'] ?? '');
        if ($serverIp === '' || filter_var($serverIp, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'message' => 'Sunucu IP ayarı geçersiz. Önce kurulum ayarlarını güncelleyin.'];
        }

        $siteId = bin2hex(random_bytes(8));
        $defaultDocumentRoot = '/var/www/' . str_replace('.', '_', $domain) . '/public_html';
        $documentRoot = $documentRootInput !== '' ? $documentRootInput : $defaultDocumentRoot;
        if (!$this->isValidDocumentRoot($documentRoot)) {
            return ['ok' => false, 'message' => 'Geçersiz document root. /var/www ile başlamalı ve .. içermemeli.'];
        }

        $site = [
            'id' => $siteId,
            'domain' => $domain,
            'document_root' => $documentRoot,
            'web_server' => $webServer,
            'runtime' => $runtime,
            'status' => 'provisioning',
            'server_ip' => $serverIp,
            'provision_dns' => $provisionDns,
            'provision_ssl' => $provisionSsl,
            'created_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
        ];

        $sites[] = $site;
        if (!$this->writeJson($this->sitesFile(), $sites)) {
            return ['ok' => false, 'message' => 'Site kaydı yazılamadı.'];
        }

        $domains = $this->allDomains();
        $domains[] = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'domain' => $domain,
            'type' => 'primary',
            'ssl_status' => 'pending',
            'dns_status' => 'pending',
            'created_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->domainsFile(), $domains)) {
            return ['ok' => false, 'message' => 'Domain kaydı yazılamadı.'];
        }

        $job = $this->jobService->enqueue('site_create', [
            'site_id' => $siteId,
            'domain' => $domain,
            'document_root' => $documentRoot,
            'server_ip' => $serverIp,
            'web_server' => $webServer,
            'runtime' => $runtime,
            'provision_dns' => $provisionDns,
            'provision_ssl' => $provisionSsl,
        ]);

        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Site kaydı oluşturuldu ancak iş kuyruğa alınamadı.'];
        }

        $this->jobService->appendLog((string) (($job['job']['id'] ?? 'system')), 'info', 'Website oluşturma işi kuyruğa alındı.');
        return ['ok' => true, 'message' => 'Website oluşturma isteği alındı.', 'site_id' => $siteId];
    }

    private function isValidDocumentRoot(string $documentRoot): bool
    {
        if (!str_starts_with($documentRoot, '/var/www/')) {
            return false;
        }
        return !str_contains($documentRoot, '..');
    }

    public function updateSiteStatus(string $siteId, string $status): bool
    {
        $sites = $this->allSites();
        for ($i = 0, $total = count($sites); $i < $total; $i++) {
            if (($sites[$i]['id'] ?? '') !== $siteId) {
                continue;
            }

            $sites[$i]['status'] = $status;
            $sites[$i]['updated_at'] = date(DATE_ATOM);
            return $this->writeJson($this->sitesFile(), $sites);
        }

        return false;
    }

    public function saveForceHttps(string $siteId, bool $enabled): array
    {
        $sites = $this->allSites();
        $found = false;
        for ($i = 0, $total = count($sites); $i < $total; $i++) {
            if (($sites[$i]['id'] ?? '') !== $siteId) {
                continue;
            }
            $sites[$i]['force_https'] = $enabled;
            $sites[$i]['updated_at'] = date(DATE_ATOM);
            $found = true;
            break;
        }
        if (!$found) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if (!$this->writeJson($this->sitesFile(), $sites)) {
            return ['ok' => false, 'message' => 'HTTPS ayarı kaydedilemedi.'];
        }
        return ['ok' => true, 'message' => 'HTTPS ayarı güncellendi.'];
    }

    public function requestSslAction(string $siteId, string $domain, string $actionType): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $domain = mb_strtolower(trim($domain));
        $validDomain = false;
        foreach ($this->domainsBySite($siteId) as $row) {
            if ((string) ($row['domain'] ?? '') === $domain) {
                $validDomain = true;
                break;
            }
        }
        if (!$validDomain) {
            return ['ok' => false, 'message' => 'Domain bu siteye ait değil.'];
        }
        if (!in_array($actionType, ['issue', 'renew'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz SSL aksiyonu.'];
        }

        $jobType = $actionType === 'issue' ? 'ssl_issue' : 'ssl_renew';
        $job = $this->jobService->enqueue($jobType, [
            'site_id' => $siteId,
            'domain' => $domain,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'SSL işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'SSL işi kuyruğa alındı.'];
    }

    public function rollbackSite(string $siteId): bool
    {
        $sites = array_values(array_filter(
            $this->allSites(),
            static fn(array $site): bool => ($site['id'] ?? '') !== $siteId
        ));

        $domains = array_values(array_filter(
            $this->allDomains(),
            static fn(array $domain): bool => ($domain['site_id'] ?? '') !== $siteId
        ));

        return $this->writeJson($this->sitesFile(), $sites) && $this->writeJson($this->domainsFile(), $domains);
    }

    /**
     * @return array<string, mixed>
     */
    public function addDomain(string $siteId, string $domain, string $type = 'alias'): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }

        $domain = mb_strtolower(trim($domain));
        if (!$this->isValidDomain($domain)) {
            return ['ok' => false, 'message' => 'Geçerli bir alan adı girin.'];
        }

        $domains = $this->allDomains();
        foreach ($domains as $item) {
            if (($item['domain'] ?? '') === $domain) {
                return ['ok' => false, 'message' => 'Bu domain zaten kayıtlı.'];
            }
        }

        $domainRow = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'domain' => $domain,
            'type' => $type,
            'ssl_status' => 'pending',
            'dns_status' => 'pending',
            'created_at' => date(DATE_ATOM),
        ];
        $domains[] = $domainRow;

        if (!$this->writeJson($this->domainsFile(), $domains)) {
            return ['ok' => false, 'message' => 'Domain kaydı yazılamadı.'];
        }

        $job = $this->jobService->enqueue('domain_add', [
            'site_id' => $siteId,
            'domain' => $domain,
            'server_ip' => (string) ($site['server_ip'] ?? ''),
        ]);

        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Domain kaydedildi ancak iş kuyruğa alınamadı.'];
        }

        return ['ok' => true, 'message' => 'Domain ekleme isteği alındı.', 'domain_id' => (string) $domainRow['id']];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeDomain(string $domainId): array
    {
        $domains = $this->allDomains();
        $target = null;
        $remaining = [];
        foreach ($domains as $row) {
            if (($row['id'] ?? '') === $domainId) {
                $target = $row;
                continue;
            }
            $remaining[] = $row;
        }

        if ($target === null) {
            return ['ok' => false, 'message' => 'Domain bulunamadı.'];
        }
        if (($target['type'] ?? '') === 'primary') {
            return ['ok' => false, 'message' => 'Ana domain silinemez.'];
        }

        if (!$this->writeJson($this->domainsFile(), $remaining)) {
            return ['ok' => false, 'message' => 'Domain kaydı güncellenemedi.'];
        }

        $job = $this->jobService->enqueue('domain_delete', [
            'site_id' => (string) ($target['site_id'] ?? ''),
            'domain' => (string) ($target['domain'] ?? ''),
        ]);

        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Domain silindi ancak iş kuyruğa alınamadı.'];
        }

        return ['ok' => true, 'message' => 'Domain silme isteği alındı.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function domainsBySite(string $siteId): array
    {
        return array_values(array_filter(
            $this->allDomains(),
            static fn(array $domain): bool => ($domain['site_id'] ?? '') === $siteId
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function requestSiteDelete(string $siteId, string $confirmationDomain): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }

        $expected = (string) ($site['domain'] ?? '');
        $confirmation = mb_strtolower(trim($confirmationDomain));
        if ($expected === '' || $confirmation !== $expected) {
            return ['ok' => false, 'message' => 'Onay alanına ana domain tam olarak yazılmalı.'];
        }

        $this->updateSiteStatus($siteId, 'deleting');
        $job = $this->jobService->enqueue('site_delete', [
            'site_id' => $siteId,
            'domain' => $expected,
        ]);
        if (($job['ok'] ?? false) !== true) {
            $this->updateSiteStatus($siteId, 'active');
            return ['ok' => false, 'message' => 'Site silme işi kuyruğa alınamadı.'];
        }

        return ['ok' => true, 'message' => 'Site silme isteği alındı.'];
    }

    public function finalizeSiteDelete(string $siteId): bool
    {
        $sites = array_values(array_filter(
            $this->allSites(),
            static fn(array $site): bool => ($site['id'] ?? '') !== $siteId
        ));
        $domains = array_values(array_filter(
            $this->allDomains(),
            static fn(array $domain): bool => ($domain['site_id'] ?? '') !== $siteId
        ));

        return $this->writeJson($this->sitesFile(), $sites) && $this->writeJson($this->domainsFile(), $domains);
    }

    /**
     * @return array<string, mixed>
     */
    public function phpProfile(string $siteId): array
    {
        $all = $this->readJson($this->phpProfilesFile());
        if (!is_array($all)) {
            return [];
        }
        return is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
    }

    public function savePhpProfile(string $siteId, string $phpVersion, string $memoryLimit, string $uploadMax, int $executionTime): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $allowed = ['8.1', '8.2', '8.3'];
        if (!in_array($phpVersion, $allowed, true)) {
            return ['ok' => false, 'message' => 'Geçersiz PHP sürümü.'];
        }
        if ($executionTime < 30 || $executionTime > 600) {
            return ['ok' => false, 'message' => 'Execution time 30-600 aralığında olmalı.'];
        }

        $profiles = $this->readJson($this->phpProfilesFile());
        if (!is_array($profiles)) {
            $profiles = [];
        }
        $profiles[$siteId] = [
            'php_version' => $phpVersion,
            'memory_limit' => $memoryLimit,
            'upload_max_filesize' => $uploadMax,
            'max_execution_time' => $executionTime,
            'updated_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->phpProfilesFile(), $profiles)) {
            return ['ok' => false, 'message' => 'PHP profili kaydedilemedi.'];
        }

        $job = $this->jobService->enqueue('site_php_update', [
            'site_id' => $siteId,
            'document_root' => (string) ($site['document_root'] ?? '/var/www'),
            'php_version' => $phpVersion,
            'memory_limit' => $memoryLimit,
            'upload_max_filesize' => $uploadMax,
            'max_execution_time' => $executionTime,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Profil kaydedildi ancak iş kuyruğa alınamadı.'];
        }

        return ['ok' => true, 'message' => 'PHP profili güncelleme isteği alındı.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function databasesBySite(string $siteId): array
    {
        $all = $this->readJson($this->databasesFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
    }

    public function createDatabase(string $siteId, string $dbName, string $dbUser, string $dbPassword): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $dbName = strtolower(trim($dbName));
        $dbUser = strtolower(trim($dbUser));
        if (!preg_match('/^[a-z0-9_]{3,32}$/', $dbName) || !preg_match('/^[a-z0-9_]{3,32}$/', $dbUser)) {
            return ['ok' => false, 'message' => 'Veritabanı adı ve kullanıcı adı formatı geçersiz.'];
        }
        if (strlen($dbPassword) < 10) {
            return ['ok' => false, 'message' => 'Veritabanı şifresi en az 10 karakter olmalı.'];
        }

        $all = $this->readJson($this->databasesFile());
        if (!is_array($all)) {
            $all = [];
        }
        foreach ($all as $row) {
            if (($row['db_name'] ?? '') === $dbName) {
                return ['ok' => false, 'message' => 'Bu veritabanı adı zaten kullanılıyor.'];
            }
        }
        $id = bin2hex(random_bytes(8));
        $all[] = [
            'id' => $id,
            'site_id' => $siteId,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password_hash' => password_hash($dbPassword, PASSWORD_DEFAULT),
            'created_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->databasesFile(), $all)) {
            return ['ok' => false, 'message' => 'Veritabanı kaydı yazılamadı.'];
        }

        $job = $this->jobService->enqueue('db_create', ['site_id' => $siteId, 'db_name' => $dbName, 'db_user' => $dbUser]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Veritabanı kaydedildi ancak iş kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Veritabanı oluşturma isteği alındı.'];
    }

    public function deleteDatabase(string $siteId, string $dbId): array
    {
        $all = $this->readJson($this->databasesFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'Veritabanı bulunamadı.'];
        }
        $target = null;
        $remaining = [];
        foreach ($all as $row) {
            if (($row['id'] ?? '') === $dbId && ($row['site_id'] ?? '') === $siteId) {
                $target = $row;
                continue;
            }
            $remaining[] = $row;
        }
        if ($target === null) {
            return ['ok' => false, 'message' => 'Veritabanı bulunamadı.'];
        }
        if (!$this->writeJson($this->databasesFile(), $remaining)) {
            return ['ok' => false, 'message' => 'Veritabanı kaydı güncellenemedi.'];
        }
        $job = $this->jobService->enqueue('db_delete', ['site_id' => $siteId, 'db_name' => (string) ($target['db_name'] ?? '')]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Veritabanı silindi ancak iş kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Veritabanı silme isteği alındı.'];
    }

    public function resetDatabaseUserPassword(string $siteId, string $dbId, string $newPassword): array
    {
        if (strlen($newPassword) < 10) {
            return ['ok' => false, 'message' => 'Yeni şifre en az 10 karakter olmalı.'];
        }
        $all = $this->readJson($this->databasesFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'Veritabanı bulunamadı.'];
        }
        $target = null;
        for ($i = 0, $total = count($all); $i < $total; $i++) {
            if (($all[$i]['id'] ?? '') === $dbId && ($all[$i]['site_id'] ?? '') === $siteId) {
                $all[$i]['db_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $all[$i]['updated_at'] = date(DATE_ATOM);
                $target = $all[$i];
                break;
            }
        }
        if ($target === null) {
            return ['ok' => false, 'message' => 'Veritabanı bulunamadı.'];
        }
        if (!$this->writeJson($this->databasesFile(), $all)) {
            return ['ok' => false, 'message' => 'Şifre güncellenemedi.'];
        }
        $job = $this->jobService->enqueue('db_user_reset', [
            'site_id' => $siteId,
            'db_name' => (string) ($target['db_name'] ?? ''),
            'db_user' => (string) ($target['db_user'] ?? ''),
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Şifre güncellendi ancak iş kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'DB kullanıcı şifre sıfırlama isteği alındı.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function wordpressData(string $siteId): array
    {
        $all = $this->readJson($this->wordpressFile());
        if (!is_array($all)) {
            return [];
        }
        $data = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $data['plugins'] = is_array($data['plugins'] ?? null) ? $data['plugins'] : [];
        $data['themes'] = is_array($data['themes'] ?? null) ? $data['themes'] : [];
        $data['last_operations'] = is_array($data['last_operations'] ?? null) ? $data['last_operations'] : [];
        $data['staging'] = is_array($data['staging'] ?? null) ? $data['staging'] : [];
        return $data;
    }

    public function wordpressCliPolicy(): array
    {
        return [
            'allowed_commands' => [
                'core install',
                'core update',
                'plugin install',
                'plugin delete',
                'theme activate',
                'maintenance-mode activate/deactivate',
                'plugin list',
                'theme list',
            ],
            'blocked_commands' => [
                'db drop',
                'db reset',
                'search-replace --all-tables',
                'eval',
                'shell',
            ],
            'updated_at' => date(DATE_ATOM),
        ];
    }

    public function wordpressSecurityReport(string $siteId): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return [
                'ok' => false,
                'score' => 0,
                'critical_count' => 0,
                'checklist' => [],
                'summary' => 'Website bulunamadı.',
            ];
        }
        $wp = $this->wordpressData($siteId);
        $installed = ((bool) ($wp['installed'] ?? false)) === true;
        if (!$installed) {
            return [
                'ok' => true,
                'score' => 0,
                'critical_count' => 0,
                'checklist' => [],
                'summary' => 'WordPress kurulu değil.',
                'updated_at' => (string) ($wp['security_scan_at'] ?? ''),
            ];
        }

        $checklist = [
            [
                'key' => 'core_update',
                'label' => 'WordPress core güncel',
                'ok' => ((bool) ($wp['core_update_available'] ?? false)) === false,
                'severity' => 'critical',
            ],
            [
                'key' => 'plugins_update',
                'label' => 'Plugin güncelleme beklemiyor',
                'ok' => ((int) ($wp['plugin_updates'] ?? 0)) === 0,
                'severity' => 'warning',
            ],
            [
                'key' => 'themes_update',
                'label' => 'Tema güncelleme beklemiyor',
                'ok' => ((int) ($wp['theme_updates'] ?? 0)) === 0,
                'severity' => 'warning',
            ],
            [
                'key' => 'debug_disabled',
                'label' => 'WP_DEBUG kapalı',
                'ok' => ((bool) ($wp['wp_debug_enabled'] ?? false)) === false,
                'severity' => 'warning',
            ],
            [
                'key' => 'xmlrpc_disabled',
                'label' => 'XML-RPC kapalı',
                'ok' => ((bool) ($wp['xmlrpc_enabled'] ?? false)) === false,
                'severity' => 'warning',
            ],
            [
                'key' => 'file_editor_disabled',
                'label' => 'Tema/eklenti dosya editörü kapalı',
                'ok' => ((bool) ($wp['file_editor_disabled'] ?? true)) === true,
                'severity' => 'critical',
            ],
            [
                'key' => 'object_cache',
                'label' => 'Object cache aktif',
                'ok' => ((bool) ($wp['object_cache_enabled'] ?? false)) === true,
                'severity' => 'info',
            ],
        ];

        $total = count($checklist);
        $okCount = count(array_filter($checklist, static fn(array $item): bool => ((bool) ($item['ok'] ?? false)) === true));
        $criticalCount = count(array_filter($checklist, static fn(array $item): bool => ((bool) ($item['ok'] ?? false)) === false && (($item['severity'] ?? '') === 'critical')));
        $score = $total > 0 ? (int) floor(($okCount / $total) * 100) : 0;

        return [
            'ok' => true,
            'score' => $score,
            'critical_count' => $criticalCount,
            'checklist' => $checklist,
            'summary' => $criticalCount > 0 ? 'Kritik güvenlik riski var.' : 'Kritik risk görünmüyor.',
            'updated_at' => (string) ($wp['security_scan_at'] ?? ''),
        ];
    }

    public function refreshWordPressSecuritySignals(string $siteId): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $all = $this->readJson($this->wordpressFile());
        if (!is_array($all)) {
            $all = [];
        }
        $row = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $row['security_scan_at'] = date(DATE_ATOM);
        $row['core_update_available'] = ((bool) ($row['core_update_available'] ?? false)) === true;
        $row['plugin_updates'] = (int) ($row['plugin_updates'] ?? 0);
        $row['theme_updates'] = (int) ($row['theme_updates'] ?? 0);
        $row['wp_debug_enabled'] = ((bool) ($row['wp_debug_enabled'] ?? false)) === true;
        $row['xmlrpc_enabled'] = ((bool) ($row['xmlrpc_enabled'] ?? false)) === true;
        $row['file_editor_disabled'] = ((bool) ($row['file_editor_disabled'] ?? true)) === true;
        $row['object_cache_enabled'] = ((bool) ($row['object_cache_enabled'] ?? false)) === true;
        $all[$siteId] = $row;
        if (!$this->writeJson($this->wordpressFile(), $all)) {
            return ['ok' => false, 'message' => 'WordPress güvenlik sinyalleri kaydedilemedi.'];
        }

        $report = $this->wordpressSecurityReport($siteId);
        return ['ok' => true, 'message' => 'WordPress güvenlik taraması güncellendi.', 'report' => $report];
    }

    public function requestWordPressInstall(string $siteId, string $adminUser, string $adminPassword, string $adminEmail): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $adminUser = trim($adminUser);
        $adminEmail = trim($adminEmail);
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $adminUser)) {
            return ['ok' => false, 'message' => 'Geçerli bir admin kullanıcı adı girin.'];
        }
        if (strlen($adminPassword) < 10) {
            return ['ok' => false, 'message' => 'Admin şifresi en az 10 karakter olmalı.'];
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Geçerli admin e-posta girin.'];
        }

        $databases = $this->databasesBySite($siteId);
        if ($databases === []) {
            $base = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ($site['domain'] ?? 'site')));
            $base = substr($base, 0, 18);
            $autoDb = $base . '_wp';
            $autoUser = $base . '_u';
            $autoPass = bin2hex(random_bytes(8)) . 'A1!';
            $dbResult = $this->createDatabase($siteId, $autoDb, $autoUser, $autoPass);
            if (($dbResult['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => 'WordPress için otomatik veritabanı oluşturulamadı.'];
            }
        }

        $job = $this->jobService->enqueue('wp_install', [
            'site_id' => $siteId,
            'domain' => (string) ($site['domain'] ?? ''),
            'admin_user' => $adminUser,
            'admin_password' => $adminPassword,
            'admin_email' => $adminEmail,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'WordPress kurulum işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'WordPress kurulum isteği alındı.'];
    }

    public function saveWordPressData(string $siteId, array $data): bool
    {
        $all = $this->readJson($this->wordpressFile());
        if (!is_array($all)) {
            $all = [];
        }
        $all[$siteId] = $data;
        return $this->writeJson($this->wordpressFile(), $all);
    }

    public function requestWordPressPluginAction(string $siteId, string $plugin, string $action): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $plugin = trim($plugin);
        if (!preg_match('/^[a-z0-9-_]{2,80}$/', $plugin)) {
            return ['ok' => false, 'message' => 'Geçersiz plugin adı.'];
        }
        if (!in_array($action, ['install', 'delete'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz plugin işlemi.'];
        }
        $policy = $this->wordpressCliPolicy();
        $required = 'plugin ' . $action;
        if (!in_array($required, $policy['allowed_commands'], true)) {
            return ['ok' => false, 'message' => 'Bu WP-CLI işlemi güvenlik politikası nedeniyle engellendi.'];
        }
        $job = $this->jobService->enqueue('wp_plugin_' . $action, ['site_id' => $siteId, 'plugin' => $plugin]);
        $ok = ($job['ok'] ?? false) === true;
        $this->appendWordPressOperation($siteId, $ok ? 'plugin.' . $action : 'plugin.' . $action . '_failed', $plugin, $ok);
        return $ok ? ['ok' => true, 'message' => 'Plugin işlemi kuyruğa alındı.'] : ['ok' => false, 'message' => 'Plugin işi kuyruğa alınamadı.'];
    }

    public function requestWordPressThemeActivate(string $siteId, string $theme): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $theme = trim($theme);
        if (!preg_match('/^[a-z0-9-_]{2,80}$/', $theme)) {
            return ['ok' => false, 'message' => 'Geçersiz tema adı.'];
        }
        $policy = $this->wordpressCliPolicy();
        if (!in_array('theme activate', $policy['allowed_commands'], true)) {
            return ['ok' => false, 'message' => 'Bu WP-CLI işlemi güvenlik politikası nedeniyle engellendi.'];
        }
        $job = $this->jobService->enqueue('wp_theme_activate', ['site_id' => $siteId, 'theme' => $theme]);
        $ok = ($job['ok'] ?? false) === true;
        $this->appendWordPressOperation($siteId, $ok ? 'theme.activate' : 'theme.activate_failed', $theme, $ok);
        return $ok ? ['ok' => true, 'message' => 'Tema aktivasyon işi kuyruğa alındı.'] : ['ok' => false, 'message' => 'Tema işi kuyruğa alınamadı.'];
    }

    public function requestWordPressCoreUpdate(string $siteId): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $policy = $this->wordpressCliPolicy();
        if (!in_array('core update', $policy['allowed_commands'], true)) {
            return ['ok' => false, 'message' => 'Bu WP-CLI işlemi güvenlik politikası nedeniyle engellendi.'];
        }
        $job = $this->jobService->enqueue('wp_core_update', ['site_id' => $siteId]);
        $ok = ($job['ok'] ?? false) === true;
        $this->appendWordPressOperation($siteId, $ok ? 'core.update' : 'core.update_failed', 'wordpress', $ok);
        return $ok ? ['ok' => true, 'message' => 'WordPress güncelleme işi kuyruğa alındı.'] : ['ok' => false, 'message' => 'WordPress güncelleme işi kuyruğa alınamadı.'];
    }

    public function requestWordPressMaintenanceToggle(string $siteId, bool $enabled): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $policy = $this->wordpressCliPolicy();
        if (!in_array('maintenance-mode activate/deactivate', $policy['allowed_commands'], true)) {
            return ['ok' => false, 'message' => 'Bu WP-CLI işlemi güvenlik politikası nedeniyle engellendi.'];
        }
        $job = $this->jobService->enqueue('wp_maintenance_toggle', ['site_id' => $siteId, 'enabled' => $enabled]);
        $ok = ($job['ok'] ?? false) === true;
        $this->appendWordPressOperation($siteId, $ok ? 'maintenance.toggle' : 'maintenance.toggle_failed', $enabled ? 'on' : 'off', $ok);
        return $ok ? ['ok' => true, 'message' => 'Maintenance işi kuyruğa alındı.'] : ['ok' => false, 'message' => 'Maintenance işi kuyruğa alınamadı.'];
    }

    public function requestWordPressStagingCreate(string $siteId): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['wp_staging_create', 'wp_staging_sync_live'])) {
            return ['ok' => false, 'message' => 'Staging işlemi zaten çalışıyor.'];
        }

        $job = $this->jobService->enqueue('wp_staging_create', [
            'site_id' => $siteId,
            'domain' => (string) ($site['domain'] ?? ''),
        ]);
        if (($job['ok'] ?? false) !== true) {
            $this->appendWordPressOperation($siteId, 'staging.create_failed', 'staging', false);
            return ['ok' => false, 'message' => 'Staging oluşturma işi kuyruğa alınamadı.'];
        }

        $this->upsertWordPressStagingState($siteId, [
            'status' => 'creating',
            'staging_domain' => 'staging.' . (string) ($site['domain'] ?? ''),
            'last_create_requested_at' => date(DATE_ATOM),
            'last_error' => '',
        ]);
        $this->appendWordPressOperation($siteId, 'staging.create', 'staging', true);
        return ['ok' => true, 'message' => 'Staging oluşturma isteği alındı.'];
    }

    public function requestWordPressStagingSyncToLive(string $siteId, string $confirmText): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $domain = (string) ($site['domain'] ?? '');
        if (trim($confirmText) !== $domain) {
            return ['ok' => false, 'message' => 'Onay için ana domain doğru girilmelidir.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['wp_staging_create', 'wp_staging_sync_live'])) {
            return ['ok' => false, 'message' => 'Staging işlemi zaten çalışıyor.'];
        }

        $job = $this->jobService->enqueue('wp_staging_sync_live', [
            'site_id' => $siteId,
            'domain' => $domain,
        ]);
        if (($job['ok'] ?? false) !== true) {
            $this->appendWordPressOperation($siteId, 'staging.sync_live_failed', 'live', false);
            return ['ok' => false, 'message' => 'Staging -> canlı senkron işi kuyruğa alınamadı.'];
        }

        $this->upsertWordPressStagingState($siteId, [
            'last_sync_requested_at' => date(DATE_ATOM),
            'status' => 'syncing',
            'last_error' => '',
        ]);
        $this->appendWordPressOperation($siteId, 'staging.sync_live', 'live', true);
        return ['ok' => true, 'message' => 'Staging -> canlı senkron isteği alındı.'];
    }

    private function appendWordPressOperation(string $siteId, string $action, string $target, bool $ok): void
    {
        $all = $this->readJson($this->wordpressFile());
        if (!is_array($all)) {
            $all = [];
        }
        $row = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $ops = is_array($row['last_operations'] ?? null) ? $row['last_operations'] : [];
        $ops[] = [
            'time' => date(DATE_ATOM),
            'action' => $action,
            'target' => $target,
            'ok' => $ok,
        ];
        $row['last_operations'] = array_slice($ops, -20);
        $all[$siteId] = $row;
        $this->writeJson($this->wordpressFile(), $all);
    }

    private function upsertWordPressStagingState(string $siteId, array $stagingPatch): void
    {
        $all = $this->readJson($this->wordpressFile());
        if (!is_array($all)) {
            $all = [];
        }
        $row = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $staging = is_array($row['staging'] ?? null) ? $row['staging'] : [];
        foreach ($stagingPatch as $key => $value) {
            $staging[(string) $key] = $value;
        }
        $staging['updated_at'] = date(DATE_ATOM);
        $row['staging'] = $staging;
        $all[$siteId] = $row;
        $this->writeJson($this->wordpressFile(), $all);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backupsBySite(string $siteId): array
    {
        $all = $this->readJson($this->backupsFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(array $b): bool => ($b['site_id'] ?? '') === $siteId));
    }

    public function requestBackup(string $siteId, string $type = 'full'): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['backup_restore', 'deploy_run'])) {
            return ['ok' => false, 'message' => 'Bu site için restore/deploy işlemi sürerken yeni backup başlatılamaz.'];
        }
        if (!in_array($type, ['full', 'files', 'database', 'safety'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz backup tipi.'];
        }
        $backupId = bin2hex(random_bytes(8));
        $job = $this->jobService->enqueue('backup_create', [
            'site_id' => $siteId,
            'backup_id' => $backupId,
            'type' => $type,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Backup işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Backup isteği alındı.', 'backup_id' => $backupId];
    }

    public function requestRestore(string $siteId, string $backupId): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $backup = $this->findBackup($siteId, $backupId);
        if ($backup === null) {
            return ['ok' => false, 'message' => 'Restore edilecek backup bulunamadı.'];
        }
        $integrity = $this->verifyBackupIntegrity($backup);
        if (($integrity['ok'] ?? false) !== true) {
            $this->markBackupIntegrity($siteId, $backupId, false);
            return ['ok' => false, 'message' => 'Backup bütünlük doğrulaması başarısız: ' . (string) ($integrity['message'] ?? '')];
        }
        $this->markBackupIntegrity($siteId, $backupId, true);
        $dryRunOk = ((bool) ($backup['dry_run_ok'] ?? false)) === true;
        $dryRunAt = strtotime((string) ($backup['dry_run_at'] ?? '')) ?: 0;
        if (!$dryRunOk || $dryRunAt <= 0 || $dryRunAt < (time() - 3600)) {
            return ['ok' => false, 'message' => 'Restore öncesi dry-run gerekli. Önce Dry-Run çalıştırın.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['backup_create', 'backup_restore', 'deploy_run'])) {
            return ['ok' => false, 'message' => 'Bu site için backup/deploy işlemi sürerken restore başlatılamaz.'];
        }
        $safety = $this->requestBackup($siteId, 'safety');
        if (($safety['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Restore öncesi safety backup alınamadı.'];
        }
        $job = $this->jobService->enqueue('backup_restore', [
            'site_id' => $siteId,
            'backup_id' => $backupId,
            'safety_backup_id' => (string) ($safety['backup_id'] ?? ''),
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Restore işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Restore isteği alındı.'];
    }

    public function requestRestoreDryRun(string $siteId, string $backupId): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $backup = $this->findBackup($siteId, $backupId);
        if ($backup === null) {
            return ['ok' => false, 'message' => 'Dry-run backup kaydı bulunamadı.'];
        }
        $integrity = $this->verifyBackupIntegrity($backup);
        if (($integrity['ok'] ?? false) !== true) {
            $this->markBackupIntegrity($siteId, $backupId, false);
            return ['ok' => false, 'message' => 'Dry-run öncesi bütünlük doğrulaması başarısız: ' . (string) ($integrity['message'] ?? '')];
        }
        $this->markBackupIntegrity($siteId, $backupId, true);
        if ($this->hasActiveSiteJob($siteId, ['backup_create', 'backup_restore', 'deploy_run', 'backup_restore_dry_run'])) {
            return ['ok' => false, 'message' => 'Bu site için başka bir backup/deploy işi sürüyor.'];
        }
        $job = $this->jobService->enqueue('backup_restore_dry_run', [
            'site_id' => $siteId,
            'backup_id' => $backupId,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Dry-run işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Dry-run işi kuyruğa alındı.'];
    }

    public function saveBackupRecord(array $record): bool
    {
        $all = $this->readJson($this->backupsFile());
        if (!is_array($all)) {
            $all = [];
        }
        if (!isset($record['integrity_ok'])) {
            $record['integrity_ok'] = true;
        }
        $all[] = $record;
        return $this->writeJson($this->backupsFile(), $all);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBackup(string $siteId, string $backupId): ?array
    {
        foreach ($this->backupsBySite($siteId) as $backup) {
            if ((string) ($backup['id'] ?? '') === $backupId) {
                return $backup;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $backup
     * @return array<string, mixed>
     */
    public function verifyBackupIntegrity(array $backup): array
    {
        $siteId = (string) ($backup['site_id'] ?? '');
        $backupId = (string) ($backup['id'] ?? '');
        if ($siteId === '' || $backupId === '') {
            return ['ok' => false, 'message' => 'Backup kaydı eksik.'];
        }
        $file = AILHOST_ROOT . '/var/backups/' . $siteId . '/' . $backupId . '.tar.gz';
        if (!is_file($file)) {
            return ['ok' => false, 'message' => 'Backup dosyası bulunamadı.'];
        }
        $size = filesize($file);
        if ($size === false || $size <= 0) {
            return ['ok' => false, 'message' => 'Backup dosya boyutu geçersiz.'];
        }
        $expectedSize = (int) ($backup['size_bytes'] ?? 0);
        if ($expectedSize <= 0) {
            return ['ok' => false, 'message' => 'Backup manifest boyut bilgisi eksik.'];
        }
        if ($expectedSize !== (int) $size) {
            return ['ok' => false, 'message' => 'Dosya boyutu uyuşmuyor.'];
        }
        $expectedChecksum = (string) ($backup['checksum_sha256'] ?? '');
        if ($expectedChecksum === '') {
            return ['ok' => false, 'message' => 'Backup manifest checksum bilgisi eksik.'];
        }
        $actual = hash_file('sha256', $file);
        if ($actual === false || !hash_equals($expectedChecksum, $actual)) {
            return ['ok' => false, 'message' => 'Checksum uyuşmuyor.'];
        }
        return ['ok' => true, 'message' => 'Backup bütünlüğü doğrulandı.'];
    }

    public function markBackupIntegrity(string $siteId, string $backupId, bool $ok): void
    {
        $all = $this->readJson($this->backupsFile());
        if (!is_array($all)) {
            return;
        }
        $changed = false;
        for ($i = 0, $total = count($all); $i < $total; $i++) {
            if ((string) ($all[$i]['site_id'] ?? '') !== $siteId || (string) ($all[$i]['id'] ?? '') !== $backupId) {
                continue;
            }
            $all[$i]['integrity_ok'] = $ok;
            $all[$i]['updated_at'] = date(DATE_ATOM);
            $changed = true;
            break;
        }
        if ($changed) {
            $this->writeJson($this->backupsFile(), $all);
        }
    }

    public function markBackupDryRun(string $siteId, string $backupId, bool $ok): void
    {
        $all = $this->readJson($this->backupsFile());
        if (!is_array($all)) {
            return;
        }
        $changed = false;
        for ($i = 0, $total = count($all); $i < $total; $i++) {
            if ((string) ($all[$i]['site_id'] ?? '') !== $siteId || (string) ($all[$i]['id'] ?? '') !== $backupId) {
                continue;
            }
            $all[$i]['dry_run_ok'] = $ok;
            $all[$i]['dry_run_at'] = date(DATE_ATOM);
            $all[$i]['updated_at'] = date(DATE_ATOM);
            $changed = true;
            break;
        }
        if ($changed) {
            $this->writeJson($this->backupsFile(), $all);
        }
    }

    public function pruneBackupsByKeepLast(string $siteId, int $keepLast): int
    {
        $keepLast = max(1, $keepLast);
        $siteBackups = $this->backupsBySite($siteId);
        if (count($siteBackups) <= $keepLast) {
            return 0;
        }

        $drop = array_slice($siteBackups, 0, count($siteBackups) - $keepLast);
        $dropIds = array_map(static fn(array $backup): string => (string) ($backup['id'] ?? ''), $drop);
        $all = $this->readJson($this->backupsFile());
        if (!is_array($all)) {
            return 0;
        }

        $remaining = array_values(array_filter($all, static fn(array $backup): bool => !in_array((string) ($backup['id'] ?? ''), $dropIds, true)));
        if (!$this->writeJson($this->backupsFile(), $remaining)) {
            return 0;
        }

        return count($dropIds);
    }

    public function requestRetentionCleanup(string $siteId, int $keepLast = 5): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $job = $this->jobService->enqueue('backup_retention_cleanup', ['site_id' => $siteId, 'keep_last' => $keepLast]);
        return ($job['ok'] ?? false) === true
            ? ['ok' => true, 'message' => 'Retention cleanup işi kuyruğa alındı.']
            : ['ok' => false, 'message' => 'Retention cleanup işi kuyruğa alınamadı.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function backupSchedule(string $siteId): array
    {
        $all = $this->readJson($this->backupSchedulesFile());
        if (!is_array($all)) {
            return [];
        }
        return is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
    }

    public function saveBackupSchedule(string $siteId, bool $enabled, string $frequency, int $hour, int $keepLast): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz schedule sıklığı.'];
        }
        if ($hour < 0 || $hour > 23) {
            return ['ok' => false, 'message' => 'Saat 0-23 aralığında olmalı.'];
        }
        $keepLast = max(1, min(30, $keepLast));

        $all = $this->readJson($this->backupSchedulesFile());
        if (!is_array($all)) {
            $all = [];
        }
        $schedule = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $all[$siteId] = [
            'site_id' => $siteId,
            'enabled' => $enabled,
            'frequency' => $frequency,
            'hour' => $hour,
            'keep_last' => $keepLast,
            'last_run_at' => (string) ($schedule['last_run_at'] ?? ''),
            'updated_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->backupSchedulesFile(), $all)) {
            return ['ok' => false, 'message' => 'Schedule kaydedilemedi.'];
        }
        return ['ok' => true, 'message' => 'Backup schedule kaydedildi.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allBackupSchedules(): array
    {
        $all = $this->readJson($this->backupSchedulesFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(mixed $s): bool => is_array($s)));
    }

    public function markScheduleRun(string $siteId): void
    {
        $all = $this->readJson($this->backupSchedulesFile());
        if (!is_array($all) || !is_array($all[$siteId] ?? null)) {
            return;
        }
        $all[$siteId]['last_run_at'] = date(DATE_ATOM);
        $this->writeJson($this->backupSchedulesFile(), $all);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dnsRecordsBySite(string $siteId): array
    {
        $all = $this->readJson($this->dnsRecordsFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
    }

    public function addDnsRecord(string $siteId, string $type, string $name, string $value, int $ttl): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $type = strtoupper(trim($type));
        $name = strtolower(trim($name));
        $value = trim($value);
        $ttl = max(60, min(86400, $ttl));
        if (!in_array($type, ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS'], true)) {
            return ['ok' => false, 'message' => 'DNS kayıt tipi desteklenmiyor.'];
        }
        if (!$this->isValidDnsRecordName($name) || $value === '') {
            return ['ok' => false, 'message' => 'DNS kayıt adı ve değeri zorunlu.'];
        }
        if (!$this->isValidDnsValue($type, $value)) {
            return ['ok' => false, 'message' => 'DNS kayıt değeri seçilen tipe uygun değil.'];
        }

        $all = $this->readJson($this->dnsRecordsFile());
        if (!is_array($all)) {
            $all = [];
        }
        foreach ($all as $row) {
            if (($row['site_id'] ?? '') === $siteId
                && strtoupper((string) ($row['type'] ?? '')) === $type
                && strtolower((string) ($row['name'] ?? '')) === $name
                && (string) ($row['value'] ?? '') === $value) {
                return ['ok' => false, 'message' => 'Aynı DNS kaydı zaten var.'];
            }
        }

        $all[] = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'ttl' => $ttl,
            'created_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->dnsRecordsFile(), $all)) {
            return ['ok' => false, 'message' => 'DNS kaydı yazılamadı.'];
        }
        $apply = $this->requestDnsApply($siteId);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'DNS kaydı eklendi ancak zone apply kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'DNS kaydı eklendi ve zone apply kuyruğa alındı.'];
    }

    public function deleteDnsRecord(string $siteId, string $recordId): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $all = $this->readJson($this->dnsRecordsFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'DNS kaydı bulunamadı.'];
        }
        $found = false;
        $remaining = [];
        foreach ($all as $row) {
            if (($row['id'] ?? '') === $recordId && ($row['site_id'] ?? '') === $siteId) {
                $found = true;
                continue;
            }
            $remaining[] = $row;
        }
        if (!$found) {
            return ['ok' => false, 'message' => 'DNS kaydı bulunamadı.'];
        }
        if (!$this->writeJson($this->dnsRecordsFile(), $remaining)) {
            return ['ok' => false, 'message' => 'DNS kaydı silinemedi.'];
        }
        $apply = $this->requestDnsApply($siteId);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'DNS kaydı silindi ancak zone apply kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'DNS kaydı silindi ve zone apply kuyruğa alındı.'];
    }

    public function requestDnsApply(string $siteId): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $primaryDomain = (string) ($site['domain'] ?? '');
        $serverIp = (string) ($site['server_ip'] ?? '');
        if ($primaryDomain === '' || filter_var($serverIp, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'message' => 'DNS apply için site domain/ip geçersiz.'];
        }
        $job = $this->jobService->enqueue('dns_apply', [
            'site_id' => $siteId,
            'domain' => $primaryDomain,
            'server_ip' => $serverIp,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'DNS apply işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'DNS apply işi kuyruğa alındı.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function proxyRoutesBySite(string $siteId): array
    {
        $all = $this->readJson($this->proxyRoutesFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
    }

    public function addProxyRoute(string $siteId, string $prefix, int $targetPort, string $description = ''): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $prefix = trim($prefix);
        $description = trim($description);
        if ($prefix === '' || !str_starts_with($prefix, '/')) {
            return ['ok' => false, 'message' => 'Prefix "/" ile başlamalı.'];
        }
        if (!preg_match('#^/[a-zA-Z0-9/_-]{0,120}$#', $prefix)) {
            return ['ok' => false, 'message' => 'Prefix formatı geçersiz.'];
        }
        if ($targetPort < 1 || $targetPort > 65535) {
            return ['ok' => false, 'message' => 'Port 1-65535 aralığında olmalı.'];
        }

        $all = $this->readJson($this->proxyRoutesFile());
        if (!is_array($all)) {
            $all = [];
        }
        foreach ($all as $row) {
            if (($row['site_id'] ?? '') === $siteId && ($row['prefix'] ?? '') === $prefix) {
                return ['ok' => false, 'message' => 'Bu prefix zaten tanımlı.'];
            }
        }
        $all[] = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'prefix' => $prefix,
            'target_port' => $targetPort,
            'description' => $description,
            'status' => 'active',
            'created_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->proxyRoutesFile(), $all)) {
            return ['ok' => false, 'message' => 'Proxy route kaydedilemedi.'];
        }
        $apply = $this->requestProxyApply($siteId);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Route eklendi ancak Nginx apply işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Proxy route eklendi ve apply işlemi kuyruğa alındı.'];
    }

    public function deleteProxyRoute(string $siteId, string $routeId): array
    {
        $all = $this->readJson($this->proxyRoutesFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'Proxy route bulunamadı.'];
        }
        $found = false;
        $remaining = [];
        foreach ($all as $row) {
            if (($row['id'] ?? '') === $routeId && ($row['site_id'] ?? '') === $siteId) {
                $found = true;
                continue;
            }
            $remaining[] = $row;
        }
        if (!$found) {
            return ['ok' => false, 'message' => 'Proxy route bulunamadı.'];
        }
        if (!$this->writeJson($this->proxyRoutesFile(), $remaining)) {
            return ['ok' => false, 'message' => 'Proxy route silinemedi.'];
        }
        $apply = $this->requestProxyApply($siteId);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Route silindi ancak Nginx apply işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Proxy route silindi ve apply işlemi kuyruğa alındı.'];
    }

    public function requestProxyApply(string $siteId): array
    {
        $site = $this->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['proxy_apply', 'site_create', 'site_delete'])) {
            return ['ok' => false, 'message' => 'Nginx apply işlemi zaten sürüyor.'];
        }

        $job = $this->jobService->enqueue('proxy_apply', [
            'site_id' => $siteId,
            'domain' => (string) ($site['domain'] ?? ''),
            'document_root' => (string) ($site['document_root'] ?? ''),
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Proxy apply işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Proxy apply işi kuyruğa alındı.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function deployProfile(string $siteId): array
    {
        $all = $this->readJson($this->deployProfilesFile());
        if (!is_array($all)) {
            return [];
        }
        $profile = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        if ($profile === []) {
            return [];
        }
        if (!isset($profile['webhook_secret_encrypted'])) {
            $legacy = trim((string) ($profile['webhook_secret'] ?? ''));
            if ($legacy !== '') {
                $encrypted = $this->encryptWebhookSecret($legacy);
                if ($encrypted !== null) {
                    $profile['webhook_secret_encrypted'] = $encrypted;
                    $profile['webhook_secret'] = '';
                    $all[$siteId] = $profile;
                    $this->writeJson($this->deployProfilesFile(), $all);
                }
            }
        }
        $profile['webhook_secret_configured'] = $this->webhookSecretFromProfile($profile) !== '';
        return $profile;
    }

    public function saveDeployProfile(
        string $siteId,
        string $sourceType,
        string $sourceValue,
        string $buildCommand,
        string $startCommand,
        int $port,
        string $webhookBranch = 'main',
        string $webhookSecret = ''
    ): array {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $sourceType = trim($sourceType);
        if (!in_array($sourceType, ['manual', 'git', 'zip', 'directory'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz deploy kaynak tipi.'];
        }
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'message' => 'Port 1-65535 aralığında olmalı.'];
        }
        $webhookBranch = trim($webhookBranch);
        if ($webhookBranch === '' || !preg_match('/^[a-zA-Z0-9._\\/-]{1,120}$/', $webhookBranch)) {
            return ['ok' => false, 'message' => 'Geçersiz webhook branch değeri.'];
        }
        $webhookSecret = trim($webhookSecret);
        if ($webhookSecret !== '' && strlen($webhookSecret) < 12) {
            return ['ok' => false, 'message' => 'Webhook secret en az 12 karakter olmalı.'];
        }

        $all = $this->readJson($this->deployProfilesFile());
        if (!is_array($all)) {
            $all = [];
        }
        $existing = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $encryptedSecret = '';
        if ($webhookSecret === '') {
            $encryptedSecret = (string) ($existing['webhook_secret_encrypted'] ?? '');
            if ($encryptedSecret === '') {
                $legacy = trim((string) ($existing['webhook_secret'] ?? ''));
                if ($legacy !== '') {
                    $encryptedSecret = (string) $this->encryptWebhookSecret($legacy);
                }
            }
        } else {
            $encryptedSecret = (string) $this->encryptWebhookSecret($webhookSecret);
            if ($encryptedSecret === '') {
                return ['ok' => false, 'message' => 'Webhook secret şifrelenemedi. Sunucu anahtarı eksik veya geçersiz.'];
            }
        }

        $all[$siteId] = [
            'site_id' => $siteId,
            'source_type' => $sourceType,
            'source_value' => trim($sourceValue),
            'build_command' => trim($buildCommand),
            'start_command' => trim($startCommand),
            'port' => $port,
            'webhook_secret' => '',
            'webhook_secret_encrypted' => $encryptedSecret,
            'webhook_branch' => $webhookBranch,
            'updated_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->deployProfilesFile(), $all)) {
            return ['ok' => false, 'message' => 'Deploy profili kaydedilemedi.'];
        }
        return ['ok' => true, 'message' => 'Deploy profili kaydedildi.'];
    }

    public function requestDeployRun(string $siteId): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['backup_create', 'backup_restore', 'deploy_run'])) {
            return ['ok' => false, 'message' => 'Bu site için backup/restore/deploy işlemi sürerken yeni deploy başlatılamaz.'];
        }
        $profile = $this->deployProfile($siteId);
        if ($profile === []) {
            return ['ok' => false, 'message' => 'Deploy profili bulunamadı. Önce profil kaydedin.'];
        }

        $job = $this->jobService->enqueue('deploy_run', [
            'site_id' => $siteId,
            'source_type' => (string) ($profile['source_type'] ?? ''),
            'source_value' => (string) ($profile['source_value'] ?? ''),
            'build_command' => (string) ($profile['build_command'] ?? ''),
            'start_command' => (string) ($profile['start_command'] ?? ''),
            'port' => (int) ($profile['port'] ?? 0),
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Deploy işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Deploy işlemi kuyruğa alındı.'];
    }

    public function requestDeployRunFromWebhook(string $siteId, string $source, string $branch, string $deliveryId = ''): array
    {
        $profile = $this->deployProfile($siteId);
        if ($profile === []) {
            return ['ok' => false, 'message' => 'Deploy profili bulunamadı.'];
        }
        if ($deliveryId !== '' && !$this->rememberWebhookDelivery($siteId, $deliveryId)) {
            return ['ok' => false, 'message' => 'Aynı webhook isteği tekrar gönderildi.'];
        }
        $expectedBranch = (string) ($profile['webhook_branch'] ?? 'main');
        if ($expectedBranch !== '' && $branch !== $expectedBranch) {
            return ['ok' => false, 'message' => 'Branch eşleşmedi.'];
        }

        $result = $this->requestDeployRun($siteId);
        if (($result['ok'] ?? false) !== true) {
            return $result;
        }
        return ['ok' => true, 'message' => 'Webhook deploy tetiklendi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function runtimeState(string $siteId): array
    {
        $all = $this->readJson($this->runtimeStatesFile());
        if (!is_array($all)) {
            return [];
        }
        return is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function saveRuntimeState(string $siteId, array $state): bool
    {
        $all = $this->readJson($this->runtimeStatesFile());
        if (!is_array($all)) {
            $all = [];
        }
        $current = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $all[$siteId] = array_merge($current, $state, [
            'site_id' => $siteId,
            'updated_at' => date(DATE_ATOM),
        ]);
        return $this->writeJson($this->runtimeStatesFile(), $all);
    }

    public function requestRuntimeControl(string $siteId, string $action): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if (!in_array($action, ['start', 'stop', 'restart'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz runtime işlemi.'];
        }
        if ($this->hasActiveSiteJob($siteId, ['runtime_start', 'runtime_stop', 'runtime_restart', 'deploy_run'])) {
            return ['ok' => false, 'message' => 'Runtime/deploy işlemi sürerken yeni runtime işlemi başlatılamaz.'];
        }

        $runtime = $this->runtimeState($siteId);
        $deploy = $this->deployProfile($siteId);
        $startCommand = trim((string) ($runtime['start_command'] ?? $deploy['start_command'] ?? ''));
        $port = (int) ($runtime['port'] ?? $deploy['port'] ?? 0);

        if ($action !== 'stop') {
            if ($startCommand === '') {
                return ['ok' => false, 'message' => 'Start komutu tanımlı değil. Önce deploy profili kaydedin.'];
            }
            if ($port < 1 || $port > 65535) {
                return ['ok' => false, 'message' => 'Runtime port bilgisi geçersiz.'];
            }
        }

        $jobType = 'runtime_' . $action;
        $job = $this->jobService->enqueue($jobType, [
            'site_id' => $siteId,
            'start_command' => $startCommand,
            'port' => $port,
        ]);
        if (($job['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Runtime işi kuyruğa alınamadı.'];
        }
        return ['ok' => true, 'message' => 'Runtime işlemi kuyruğa alındı.'];
    }

    /**
     * @return array<string, bool>
     */
    public function siteJobLocks(string $siteId): array
    {
        return [
            'backup_locked' => $this->hasActiveSiteJob($siteId, ['backup_restore', 'deploy_run']),
            'restore_locked' => $this->hasActiveSiteJob($siteId, ['backup_create', 'backup_restore', 'deploy_run']),
            'deploy_locked' => $this->hasActiveSiteJob($siteId, ['backup_create', 'backup_restore', 'deploy_run']),
            'runtime_locked' => $this->hasActiveSiteJob($siteId, ['runtime_start', 'runtime_stop', 'runtime_restart', 'deploy_run']),
        ];
    }

    private function sitesFile(): string
    {
        return $this->paths['local_var_path'] . '/sites.json';
    }

    private function domainsFile(): string
    {
        return $this->paths['local_var_path'] . '/domains.json';
    }

    private function phpProfilesFile(): string
    {
        return $this->paths['local_var_path'] . '/php_profiles.json';
    }

    private function databasesFile(): string
    {
        return $this->paths['local_var_path'] . '/databases.json';
    }

    private function wordpressFile(): string
    {
        return $this->paths['local_var_path'] . '/wordpress.json';
    }

    private function backupsFile(): string
    {
        return $this->paths['local_var_path'] . '/backups.json';
    }

    private function backupSchedulesFile(): string
    {
        return $this->paths['local_var_path'] . '/backup_schedules.json';
    }

    private function dnsRecordsFile(): string
    {
        return $this->paths['local_var_path'] . '/dns_records.json';
    }

    private function isValidDnsRecordName(string $name): bool
    {
        if ($name === '@') {
            return true;
        }
        return (bool) preg_match('/^[a-z0-9*][a-z0-9*._-]{0,62}$/', $name);
    }

    private function isValidDnsValue(string $type, string $value): bool
    {
        return match ($type) {
            'A' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'AAAA' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'CNAME', 'NS' => (bool) preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\.?$/i', $value),
            'MX' => (bool) preg_match('/^\d{1,3}\s+(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\.?$/i', $value),
            'TXT' => strlen($value) <= 255,
            default => false,
        };
    }

    private function proxyRoutesFile(): string
    {
        return $this->paths['local_var_path'] . '/proxy_routes.json';
    }

    private function deployProfilesFile(): string
    {
        return $this->paths['local_var_path'] . '/deploy_profiles.json';
    }

    private function runtimeStatesFile(): string
    {
        return $this->paths['local_var_path'] . '/runtime_states.json';
    }

    private function readJson(string $file): mixed
    {
        if (!is_file($file)) {
            return [];
        }

        return json_decode((string) file_get_contents($file), true);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function writeJson(string $file, array $data): bool
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $lockPath = $file . '.lock';
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            return false;
        }
        try {
            if (!flock($lockHandle, LOCK_EX)) {
                return false;
            }
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                return false;
            }
            $tmpPath = $file . '.tmp.' . bin2hex(random_bytes(6));
            if (file_put_contents($tmpPath, $encoded, LOCK_EX) === false) {
                @unlink($tmpPath);
                return false;
            }
            if (!rename($tmpPath, $file)) {
                @unlink($tmpPath);
                return false;
            }
            @chmod($file, 0664);
            return true;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function isValidDomain(string $domain): bool
    {
        if (strlen($domain) < 4 || strlen($domain) > 253) {
            return false;
        }

        return (bool) preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
    }

    /**
     * @param array<int, string> $types
     */
    private function hasActiveSiteJob(string $siteId, array $types): bool
    {
        foreach ($this->jobService->all() as $job) {
            if (!is_array($job)) {
                continue;
            }
            $status = (string) ($job['status'] ?? '');
            if (!in_array($status, ['pending', 'running'], true)) {
                continue;
            }
            $type = (string) ($job['type'] ?? '');
            if (!in_array($type, $types, true)) {
                continue;
            }
            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            if ((string) ($payload['site_id'] ?? '') === $siteId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a random webhook secret token
     */
    public function generateWebhookSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Enable webhook for a deploy profile
     */
    public function enableWebhook(string $siteId, string $branch = 'main'): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }

        $branch = trim($branch);
        if ($branch === '') {
            $branch = 'main';
        }

        $all = $this->readJson($this->deployProfilesFile());
        if (!is_array($all)) {
            $all = [];
        }

        if (!is_array($all[$siteId] ?? null)) {
            return ['ok' => false, 'message' => 'Deploy profili bulunamadı.'];
        }

        $profile = $all[$siteId];
        $secret = $this->generateWebhookSecret();

        $encryptedSecret = $this->encryptWebhookSecret($secret);
        if ($encryptedSecret === null) {
            return ['ok' => false, 'message' => 'Webhook secret şifrelenemedi. Sunucu anahtarı eksik veya geçersiz.'];
        }
        $profile['webhook_secret'] = '';
        $profile['webhook_secret_encrypted'] = $encryptedSecret;
        $profile['webhook_branch'] = $branch;
        $profile['webhook_enabled_at'] = date(DATE_ATOM);

        $all[$siteId] = $profile;

        if (!$this->writeJson($this->deployProfilesFile(), $all)) {
            return ['ok' => false, 'message' => 'Webhook yapılandırması kaydedilemedi.'];
        }

        return [
            'ok' => true,
            'message' => 'Webhook etkinleştirildi.',
            'secret' => $secret,
            'branch' => $branch,
        ];
    }

    /**
     * Disable webhook for a deploy profile
     */
    public function disableWebhook(string $siteId): array
    {
        if ($this->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }

        $all = $this->readJson($this->deployProfilesFile());
        if (!is_array($all)) {
            $all = [];
        }

        if (!is_array($all[$siteId] ?? null)) {
            return ['ok' => false, 'message' => 'Deploy profili bulunamadı.'];
        }

        $profile = $all[$siteId];
        $profile['webhook_secret'] = '';
        $profile['webhook_secret_encrypted'] = '';
        $profile['webhook_branch'] = '';

        $all[$siteId] = $profile;

        if (!$this->writeJson($this->deployProfilesFile(), $all)) {
            return ['ok' => false, 'message' => 'Webhook yapılandırması kaydedilemedi.'];
        }

        return ['ok' => true, 'message' => 'Webhook devre dışı bırakıldı.'];
    }

    /**
     * Get webhook URL for a site
     */
    public function getWebhookUrl(string $siteId, string $baseUrl = ''): string
    {
        if ($baseUrl === '') {
            $isHttps = isset($_SERVER['HTTPS']) && (string) $_SERVER['HTTPS'] !== '' && (string) $_SERVER['HTTPS'] !== 'off';
            $baseUrl = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return rtrim($baseUrl, '/') . '/webhooks/deploy?site=' . urlencode($siteId);
    }

    public function webhookSecretForSite(string $siteId): string
    {
        $profile = $this->deployProfile($siteId);
        if ($profile === []) {
            return '';
        }
        return $this->webhookSecretFromProfile($profile);
    }

    private function webhookSecretFromProfile(array $profile): string
    {
        $encrypted = trim((string) ($profile['webhook_secret_encrypted'] ?? ''));
        if ($encrypted !== '') {
            return $this->decryptWebhookSecret($encrypted);
        }
        return trim((string) ($profile['webhook_secret'] ?? ''));
    }

    private function encryptWebhookSecret(string $plain): ?string
    {
        $key = $this->webhookCipherKey();
        if ($key === '') {
            return null;
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return null;
        }
        return base64_encode($iv . $cipher);
    }

    private function decryptWebhookSecret(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= 16) {
            return '';
        }
        $key = $this->webhookCipherKey();
        if ($key === '') {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? $plain : '';
    }

    private function webhookCipherKey(): string
    {
        $raw = trim((string) (getenv('AILHOST_SECRET_KEY') ?: getenv('AILHOST_APP_KEY') ?: ''));
        if ($raw === '') {
            $raw = hash('sha256', AILHOST_ROOT . '|' . php_uname('n'), false);
        }
        return hash('sha256', $raw, true);
    }

    private function rememberWebhookDelivery(string $siteId, string $deliveryId): bool
    {
        $file = $this->webhookDeliveriesFile();
        $all = $this->readJson($file);
        if (!is_array($all)) {
            $all = [];
        }
        $siteRows = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $now = time();

        foreach ($siteRows as $id => $ts) {
            if (!is_int($ts) || $ts < ($now - 86400)) {
                unset($siteRows[$id]);
            }
        }
        if (isset($siteRows[$deliveryId])) {
            return false;
        }
        $siteRows[$deliveryId] = $now;
        $all[$siteId] = $siteRows;
        return $this->writeJson($file, $all);
    }

    private function webhookDeliveriesFile(): string
    {
        return $this->paths['local_var_path'] . '/webhook_deliveries.json';
    }
}
