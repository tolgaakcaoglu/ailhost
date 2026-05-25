<?php

declare(strict_types=1);

namespace Ailhost\Services;

use Ailhost\Adapters\Fail2banAdapter;
use Ailhost\Adapters\FirewallAdapter;
use Ailhost\Adapters\FtpAdapter;

final class SecurityService
{
    public function __construct(
        private readonly SiteService $siteService,
        private readonly FirewallAdapter $firewallAdapter,
        private readonly Fail2banAdapter $fail2banAdapter,
        private readonly FtpAdapter $ftpAdapter,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    ) {
    }

    public function securityStatus(): array
    {
        $firewall = $this->firewallAdapter->execute(['action' => 'status']);
        $fail2ban = $this->fail2banAdapter->execute(['action' => 'status']);

        return [
            'firewall' => $firewall,
            'fail2ban' => $fail2ban,
        ];
    }

    public function securityChecklist(string $siteId): array
    {
        $status = $this->securityStatus();
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return [];
        }

        $ftpAccounts = $this->ftpAccountsBySite($siteId);
        $domain = (string) ($site['domain'] ?? '');
        $wpConfig = AILHOST_ROOT . '/var/site_files/' . str_replace('.', '_', $domain) . '/wp-config.php';

        return [
            [
                'key' => 'firewall_running',
                'label' => 'Firewall servisi çalışıyor',
                'status' => (($status['firewall']['status'] ?? '') === 'running') ? 'tamam' : 'eksik',
            ],
            [
                'key' => 'fail2ban_running',
                'label' => 'Fail2ban servisi çalışıyor',
                'status' => (($status['fail2ban']['status'] ?? '') === 'running') ? 'tamam' : 'eksik',
            ],
            [
                'key' => 'ftp_account_exists',
                'label' => 'Siteye atanmış FTP hesabı var',
                'status' => count($ftpAccounts) > 0 ? 'tamam' : 'eksik',
            ],
            [
                'key' => 'wp_config_exposed',
                'label' => 'wp-config.php dosyası kök dizinde kontrol edildi',
                'status' => is_file($wpConfig) ? 'dikkat' : 'tamam',
            ],
        ];
    }

    public function ftpAccountsBySite(string $siteId): array
    {
        $all = $this->readJson($this->ftpAccountsFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
    }

    public function createFtpAccount(string $siteId, string $username, string $password): array
    {
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $username = strtolower(trim($username));
        if (!preg_match('/^[a-z0-9_]{3,24}$/', $username)) {
            return ['ok' => false, 'message' => 'FTP kullanıcı adı geçersiz.'];
        }
        if (strlen($password) < 10) {
            return ['ok' => false, 'message' => 'FTP şifresi en az 10 karakter olmalı.'];
        }

        $all = $this->readJson($this->ftpAccountsFile());
        if (!is_array($all)) {
            $all = [];
        }
        foreach ($all as $row) {
            if (($row['username'] ?? '') === $username) {
                return ['ok' => false, 'message' => 'FTP kullanıcı adı zaten var.'];
            }
        }

        $homePath = $this->siteHomePath($site);
        $apply = $this->ftpAdapter->execute([
            'action' => 'create_user',
            'site_id' => $siteId,
            'username' => $username,
            'home_path' => $homePath,
        ]);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'FTP işlemi uygulanamadı.'];
        }

        $all[] = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'home_path' => $homePath,
            'is_disabled' => false,
            'created_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->ftpAccountsFile(), $all)) {
            return ['ok' => false, 'message' => 'FTP hesabı kaydedilemedi.'];
        }

        return ['ok' => true, 'message' => 'FTP hesabı oluşturuldu.'];
    }

    public function resetFtpPassword(string $siteId, string $accountId, string $password): array
    {
        if (strlen($password) < 10) {
            return ['ok' => false, 'message' => 'FTP şifresi en az 10 karakter olmalı.'];
        }
        $all = $this->readJson($this->ftpAccountsFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'FTP hesap verisi okunamadı.'];
        }

        for ($i = 0, $total = count($all); $i < $total; $i++) {
            $row = $all[$i];
            if (($row['id'] ?? '') !== $accountId || ($row['site_id'] ?? '') !== $siteId) {
                continue;
            }
            $apply = $this->ftpAdapter->execute([
                'action' => 'set_password',
                'username' => (string) ($row['username'] ?? ''),
            ]);
            if (($apply['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => 'FTP şifresi güncellenemedi.'];
            }
            $all[$i]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $all[$i]['updated_at'] = date(DATE_ATOM);
            if (!$this->writeJson($this->ftpAccountsFile(), $all)) {
                return ['ok' => false, 'message' => 'FTP hesabı kaydedilemedi.'];
            }
            return ['ok' => true, 'message' => 'FTP şifresi güncellendi.'];
        }

        return ['ok' => false, 'message' => 'FTP hesabı bulunamadı.'];
    }

    public function setFtpAccountDisabled(string $siteId, string $accountId, bool $disabled): array
    {
        $all = $this->readJson($this->ftpAccountsFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'FTP hesap verisi okunamadı.'];
        }
        $action = $disabled ? 'disable_user' : 'enable_user';

        for ($i = 0, $total = count($all); $i < $total; $i++) {
            $row = $all[$i];
            if (($row['id'] ?? '') !== $accountId || ($row['site_id'] ?? '') !== $siteId) {
                continue;
            }
            $apply = $this->ftpAdapter->execute([
                'action' => $action,
                'username' => (string) ($row['username'] ?? ''),
            ]);
            if (($apply['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => 'FTP hesabı durumu güncellenemedi.'];
            }
            $all[$i]['is_disabled'] = $disabled;
            $all[$i]['updated_at'] = date(DATE_ATOM);
            if (!$this->writeJson($this->ftpAccountsFile(), $all)) {
                return ['ok' => false, 'message' => 'FTP hesabı kaydedilemedi.'];
            }
            return ['ok' => true, 'message' => $disabled ? 'FTP hesabı pasifleştirildi.' : 'FTP hesabı aktifleştirildi.'];
        }

        return ['ok' => false, 'message' => 'FTP hesabı bulunamadı.'];
    }

    private function siteHomePath(array $site): string
    {
        $documentRoot = trim((string) ($site['document_root'] ?? ''));
        if ($documentRoot !== '') {
            return $documentRoot;
        }
        $domain = (string) ($site['domain'] ?? '');
        return '/var/www/' . str_replace('.', '_', $domain) . '/public_html';
    }

    private function ftpAccountsFile(): string
    {
        return AILHOST_ROOT . '/var/ftp_accounts.json';
    }

    private function readJson(string $file): mixed
    {
        return $this->jsonStateStore->readArray($file);
    }

    private function writeJson(string $file, array $data): bool
    {
        return $this->jsonStateStore->writeArray($file, $data);
    }
}
