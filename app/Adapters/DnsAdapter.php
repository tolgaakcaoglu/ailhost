<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class DnsAdapter implements SystemAdapterInterface
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $allowed = ['create_zone', 'delete_zone', 'reload', 'service_health', 'apply_zone_records'];

        if (!in_array($action, $allowed, true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen DNS işlemi.'];
        }

        return match ($action) {
            'service_health' => ['ok' => true, 'status' => 'running', 'service' => 'dns'],
            'create_zone' => $this->createZone($payload),
            'delete_zone' => $this->deleteZone($payload),
            'apply_zone_records' => $this->applyZoneRecords($payload),
            'reload' => ['ok' => true, 'message' => 'DNS reload planlandı.'],
            default => ['ok' => false, 'message' => 'Desteklenmeyen DNS işlemi.'],
        };
    }

    private function createZone(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        $ip = trim((string) ($payload['server_ip'] ?? ''));

        if ($domain === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'message' => 'DNS zone için domain veya IP geçersiz.'];
        }

        if (!is_dir($this->zonesDir()) && !mkdir($concurrentDirectory = $this->zonesDir(), 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'DNS zone dizini oluşturulamadı.'];
        }

        $zoneContent = <<<'ZONE'
$TTL 3600
@   IN SOA ns1.{$domain}. admin.{$domain}. (
        2026051701
        3600
        900
        604800
        86400
)
@   IN NS  ns1.{$domain}.
@   IN A   {$ip}
www IN A   {$ip}

ZONE;
        $zoneContent = str_replace(['{$domain}', '{$ip}'], [$domain, $ip], $zoneContent);

        $zoneFile = $this->zonesDir() . '/' . $domain . '.zone';
        if (file_put_contents($zoneFile, $zoneContent) === false) {
            return ['ok' => false, 'message' => 'DNS zone dosyası yazılamadı.'];
        }

        return ['ok' => true, 'message' => 'DNS zone oluşturuldu.', 'zone_file' => $zoneFile];
    }

    private function deleteZone(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '') {
            return ['ok' => false, 'message' => 'Domain gerekli.'];
        }

        $zoneFile = $this->zonesDir() . '/' . $domain . '.zone';
        if (!is_file($zoneFile)) {
            return ['ok' => true, 'message' => 'DNS zone zaten yok.'];
        }

        return unlink($zoneFile)
            ? ['ok' => true, 'message' => 'DNS zone silindi.']
            : ['ok' => false, 'message' => 'DNS zone silinemedi.'];
    }

    private function zonesDir(): string
    {
        return $this->projectRoot . '/var/generated/dns';
    }

    private function applyZoneRecords(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        $ip = trim((string) ($payload['server_ip'] ?? ''));
        $records = is_array($payload['records'] ?? null) ? $payload['records'] : [];
        if ($domain === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'message' => 'Zone apply için domain/ip geçersiz.'];
        }

        if (!is_dir($this->zonesDir()) && !mkdir($concurrentDirectory = $this->zonesDir(), 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'DNS zone dizini oluşturulamadı.'];
        }

        $lines = [];
        $lines[] = '$TTL 3600';
        $lines[] = '@   IN SOA ns1.' . $domain . '. admin.' . $domain . '. (';
        $lines[] = '        ' . date('Ymd') . '01';
        $lines[] = '        3600';
        $lines[] = '        900';
        $lines[] = '        604800';
        $lines[] = '        86400';
        $lines[] = ')';
        $lines[] = '@   IN NS  ns1.' . $domain . '.';
        $lines[] = '@   IN A   ' . $ip;
        $lines[] = 'www IN A   ' . $ip;
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $type = strtoupper(trim((string) ($record['type'] ?? '')));
            $name = trim((string) ($record['name'] ?? ''));
            $value = trim((string) ($record['value'] ?? ''));
            $ttl = (int) ($record['ttl'] ?? 3600);
            if ($type === '' || $name === '' || $value === '') {
                return ['ok' => false, 'message' => 'Zone apply: eksik DNS kaydı bulundu.'];
            }
            if ($ttl < 60 || $ttl > 86400) {
                return ['ok' => false, 'message' => 'Zone apply: TTL aralığı geçersiz.'];
            }
            $lines[] = $name . ' ' . $ttl . ' IN ' . $type . ' ' . $value;
        }

        $zoneFile = $this->zonesDir() . '/' . $domain . '.zone';
        $zoneContent = implode(PHP_EOL, $lines) . PHP_EOL;
        if (file_put_contents($zoneFile, $zoneContent) === false) {
            return ['ok' => false, 'message' => 'Zone dosyası yazılamadı.'];
        }
        return ['ok' => true, 'message' => 'Zone apply tamamlandı.', 'zone_file' => $zoneFile];
    }
}
