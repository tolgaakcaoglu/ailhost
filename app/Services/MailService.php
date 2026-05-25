<?php

declare(strict_types=1);

namespace Ailhost\Services;

use Ailhost\Adapters\MailAdapter;

final class MailService
{
    public function __construct(
        private readonly SiteService $siteService,
        private readonly MailAdapter $mailAdapter
    ) {
    }

    public function mailboxesBySite(string $siteId): array
    {
        $all = $this->readJson($this->mailboxesFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
    }

    public function createMailbox(string $siteId, string $localPart, string $password): array
    {
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $localPart = strtolower(trim($localPart));
        if (!preg_match('/^[a-z0-9._-]{2,64}$/', $localPart)) {
            return ['ok' => false, 'message' => 'Mailbox adı geçersiz.'];
        }
        if (strlen($password) < 10) {
            return ['ok' => false, 'message' => 'Mailbox şifresi en az 10 karakter olmalı.'];
        }

        $domain = (string) ($site['domain'] ?? '');
        $email = $localPart . '@' . $domain;
        $all = $this->readJson($this->mailboxesFile());
        if (!is_array($all)) {
            $all = [];
        }
        foreach ($all as $row) {
            if (($row['email'] ?? '') === $email) {
                return ['ok' => false, 'message' => 'Mailbox zaten var.'];
            }
        }

        $apply = $this->mailAdapter->execute(['action' => 'create_mailbox', 'site_id' => $siteId, 'email' => $email]);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Mail servisine mailbox uygulanamadı.'];
        }

        $all[] = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->mailboxesFile(), $all)) {
            return ['ok' => false, 'message' => 'Mailbox kaydedilemedi.'];
        }

        return ['ok' => true, 'message' => 'Mailbox oluşturuldu.'];
    }

    public function deleteMailbox(string $siteId, string $mailboxId): array
    {
        $all = $this->readJson($this->mailboxesFile());
        if (!is_array($all)) {
            return ['ok' => false, 'message' => 'Mailbox bulunamadı.'];
        }
        $target = null;
        $remaining = [];
        foreach ($all as $row) {
            if (($row['id'] ?? '') === $mailboxId && ($row['site_id'] ?? '') === $siteId) {
                $target = $row;
                continue;
            }
            $remaining[] = $row;
        }
        if ($target === null) {
            return ['ok' => false, 'message' => 'Mailbox bulunamadı.'];
        }
        $apply = $this->mailAdapter->execute(['action' => 'delete_mailbox', 'site_id' => $siteId, 'email' => (string) ($target['email'] ?? '')]);
        if (($apply['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Mail servisine mailbox silme uygulanamadı.'];
        }
        if (!$this->writeJson($this->mailboxesFile(), $remaining)) {
            return ['ok' => false, 'message' => 'Mailbox silinemedi.'];
        }
        return ['ok' => true, 'message' => 'Mailbox silindi.'];
    }

    public function health(): array
    {
        $health = $this->mailAdapter->execute(['action' => 'service_health']);
        if (($health['ok'] ?? false) !== true) {
            return $health;
        }
        $queue = $this->mailQueue();
        $health['queue_size'] = count($queue);
        return $health;
    }

    public function mailDomainRecords(string $siteId): array
    {
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }

        $domain = (string) ($site['domain'] ?? '');
        $selector = 'default';
        $dkimValue = 'v=DKIM1; k=rsa; p=' . $this->fakeDkimKey($siteId);

        return [
            'ok' => true,
            'records' => [
                [
                    'type' => 'TXT',
                    'name' => '@',
                    'value' => 'v=spf1 a mx ip4:' . (string) ($site['server_ip'] ?? '127.0.0.1') . ' ~all',
                    'ttl' => 3600,
                    'label' => 'SPF',
                ],
                [
                    'type' => 'TXT',
                    'name' => $selector . '._domainkey',
                    'value' => $dkimValue,
                    'ttl' => 3600,
                    'label' => 'DKIM',
                ],
                [
                    'type' => 'TXT',
                    'name' => '_dmarc',
                    'value' => 'v=DMARC1; p=quarantine; rua=mailto:postmaster@' . $domain . '; adkim=s; aspf=s;',
                    'ttl' => 3600,
                    'label' => 'DMARC',
                ],
                [
                    'type' => 'MX',
                    'name' => '@',
                    'value' => '10 mail.' . $domain,
                    'ttl' => 3600,
                    'label' => 'MX',
                ],
                [
                    'type' => 'A',
                    'name' => 'mail',
                    'value' => (string) ($site['server_ip'] ?? '127.0.0.1'),
                    'ttl' => 3600,
                    'label' => 'Mail Host A',
                ],
            ],
        ];
    }

    public function applyMailDomainRecords(string $siteId, string $dmarcPolicy): array
    {
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        if (!in_array($dmarcPolicy, ['none', 'quarantine', 'reject'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz DMARC politikası.'];
        }

        $domain = (string) ($site['domain'] ?? '');
        $records = $this->mailDomainRecords($siteId);
        if (($records['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Mail DNS kayıtları hazırlanamadı.'];
        }

        $rows = is_array($records['records'] ?? null) ? $records['records'] : [];
        $dmarc = 'v=DMARC1; p=' . $dmarcPolicy . '; rua=mailto:postmaster@' . $domain . '; adkim=s; aspf=s;';
        $added = 0;
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            $name = (string) ($row['name'] ?? '');
            $value = (string) ($row['value'] ?? '');
            if ($name === '_dmarc') {
                $value = $dmarc;
            }
            $result = $this->siteService->addDnsRecord($siteId, $type, $name, $value, (int) ($row['ttl'] ?? 3600));
            if (($result['ok'] ?? false) === true) {
                $added++;
                continue;
            }
            $message = (string) ($result['message'] ?? '');
            if ($message !== 'Aynı DNS kaydı zaten var.') {
                return ['ok' => false, 'message' => 'Kayıt uygulanamadı: ' . $type . ' ' . $name];
            }
        }

        return ['ok' => true, 'message' => 'Mail DNS kayıtları güncellendi. Yeni eklenen: ' . $added];
    }

    public function webmailSettingsBySite(string $siteId): array
    {
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return [];
        }

        $all = $this->readJson($this->webmailSettingsFile());
        if (!is_array($all)) {
            $all = [];
        }
        $saved = is_array($all[$siteId] ?? null) ? $all[$siteId] : [];
        $domain = (string) ($site['domain'] ?? '');
        $defaultUrl = 'https://mail.' . $domain . '/webmail';

        return [
            'provider' => (string) ($saved['provider'] ?? 'roundcube'),
            'installed' => ((bool) ($saved['installed'] ?? false)) === true,
            'base_url' => (string) ($saved['base_url'] ?? $defaultUrl),
            'updated_at' => (string) ($saved['updated_at'] ?? ''),
        ];
    }

    public function saveWebmailSettings(string $siteId, string $provider, string $baseUrl, bool $installed): array
    {
        if ($this->siteService->findSite($siteId) === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı.'];
        }
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['roundcube'], true)) {
            return ['ok' => false, 'message' => 'Geçersiz webmail sağlayıcısı.'];
        }
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'message' => 'Geçerli bir webmail URL girin.'];
        }

        $all = $this->readJson($this->webmailSettingsFile());
        if (!is_array($all)) {
            $all = [];
        }
        $all[$siteId] = [
            'provider' => $provider,
            'installed' => $installed,
            'base_url' => $baseUrl,
            'updated_at' => date(DATE_ATOM),
        ];
        if (!$this->writeJson($this->webmailSettingsFile(), $all)) {
            return ['ok' => false, 'message' => 'Webmail ayarları kaydedilemedi.'];
        }

        return ['ok' => true, 'message' => 'Webmail ayarları kaydedildi.'];
    }

    public function mailQueueBySite(string $siteId): array
    {
        $all = $this->mailQueue();
        return array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
    }

    public function deliveryLogsBySite(string $siteId, int $limit = 30): array
    {
        $all = $this->readJson($this->mailDeliveryLogsFile());
        if (!is_array($all)) {
            return [];
        }
        $rows = array_values(array_filter($all, static fn(array $row): bool => ($row['site_id'] ?? '') === $siteId));
        return array_slice(array_reverse($rows), 0, max(1, $limit));
    }

    public function retryQueueItem(string $siteId, string $queueId): array
    {
        $all = $this->mailQueue();
        for ($i = 0, $total = count($all); $i < $total; $i++) {
            if (($all[$i]['id'] ?? '') !== $queueId || ($all[$i]['site_id'] ?? '') !== $siteId) {
                continue;
            }

            $all[$i]['status'] = 'pending';
            $all[$i]['retry_count'] = (int) ($all[$i]['retry_count'] ?? 0) + 1;
            $all[$i]['last_error'] = '';
            $all[$i]['updated_at'] = date(DATE_ATOM);
            if (!$this->writeJson($this->mailQueueFile(), $all)) {
                return ['ok' => false, 'message' => 'Kuyruk öğesi güncellenemedi.'];
            }
            $this->appendDeliveryLog($siteId, 'retry', (string) ($all[$i]['recipient'] ?? ''), 'Kuyruk öğesi tekrar denemeye alındı.');
            return ['ok' => true, 'message' => 'Kuyruk öğesi tekrar denemeye alındı.'];
        }

        return ['ok' => false, 'message' => 'Kuyruk öğesi bulunamadı.'];
    }

    public function removeQueueItem(string $siteId, string $queueId): array
    {
        $all = $this->mailQueue();
        $removed = null;
        $remaining = [];
        foreach ($all as $row) {
            if (($row['id'] ?? '') === $queueId && ($row['site_id'] ?? '') === $siteId) {
                $removed = $row;
                continue;
            }
            $remaining[] = $row;
        }
        if ($removed === null) {
            return ['ok' => false, 'message' => 'Kuyruk öğesi bulunamadı.'];
        }
        if (!$this->writeJson($this->mailQueueFile(), $remaining)) {
            return ['ok' => false, 'message' => 'Kuyruk öğesi silinemedi.'];
        }
        $this->appendDeliveryLog($siteId, 'remove', (string) ($removed['recipient'] ?? ''), 'Kuyruk öğesi kaldırıldı.');
        return ['ok' => true, 'message' => 'Kuyruk öğesi kaldırıldı.'];
    }

    private function mailboxesFile(): string
    {
        return AILHOST_ROOT . '/var/mailboxes.json';
    }

    private function mailQueueFile(): string
    {
        return AILHOST_ROOT . '/var/mail_queue.json';
    }

    private function mailDeliveryLogsFile(): string
    {
        return AILHOST_ROOT . '/var/mail_delivery_logs.json';
    }

    private function webmailSettingsFile(): string
    {
        return AILHOST_ROOT . '/var/webmail_settings.json';
    }

    private function readJson(string $file): mixed
    {
        if (!is_file($file)) {
            return [];
        }
        return json_decode((string) file_get_contents($file), true);
    }

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mailQueue(): array
    {
        $all = $this->readJson($this->mailQueueFile());
        if (!is_array($all)) {
            return [];
        }
        return array_values(array_filter($all, static fn(mixed $row): bool => is_array($row)));
    }

    private function appendDeliveryLog(string $siteId, string $action, string $recipient, string $message): void
    {
        $all = $this->readJson($this->mailDeliveryLogsFile());
        if (!is_array($all)) {
            $all = [];
        }
        $all[] = [
            'id' => bin2hex(random_bytes(8)),
            'site_id' => $siteId,
            'action' => $action,
            'recipient' => $recipient,
            'message' => $message,
            'created_at' => date(DATE_ATOM),
        ];
        $this->writeJson($this->mailDeliveryLogsFile(), $all);
    }

    private function fakeDkimKey(string $siteId): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $siteId . ':dkim', true)), '+/', '-_'), '=');
    }
}
