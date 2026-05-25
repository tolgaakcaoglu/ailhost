<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class SystemMetricsService
{
    public function __construct(private readonly string $localVarPath)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $settings = $this->readSettings();
        $cpuCores = $this->cpuCoreCount();
        $cpuPercent = $this->cpuUsagePercent();
        $memory = $this->memoryUsage();
        $disk = $this->diskUsage($this->diskPath($settings));

        return [
            'cpu' => [
                'percent' => $cpuPercent,
                'cores' => $cpuCores,
                'caption' => $cpuCores > 0 ? $cpuCores . ' Çekirdek - ' . $this->loadCaption($cpuPercent) : 'Çekirdek bilgisi alınamadı',
            ],
            'memory' => $memory,
            'disk' => $disk,
            'identity' => [
                'name' => $this->serverName($settings),
                'ip' => $this->serverIp($settings),
                'os' => $this->osLabel(),
                'uptime' => $this->uptimeLabel(),
                'location' => $this->timezoneLabel($settings),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(): array
    {
        $file = rtrim($this->localVarPath, '/') . '/settings.json';
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function cpuCoreCount(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            $content = (string) file_get_contents('/proc/cpuinfo');
            $count = preg_match_all('/^processor\s*:/m', $content);
            if ($count > 0) {
                return $count;
            }
        }

        $nproc = trim((string) @shell_exec('nproc 2>/dev/null'));
        return ctype_digit($nproc) ? max(1, (int) $nproc) : 0;
    }

    private function cpuUsagePercent(): int
    {
        $first = $this->readCpuStat();
        if ($first === null) {
            $load = sys_getloadavg();
            $cores = max(1, $this->cpuCoreCount());
            return isset($load[0]) ? max(0, min(100, (int) round(($load[0] / $cores) * 100))) : 0;
        }

        usleep(100000);
        $second = $this->readCpuStat();
        if ($second === null) {
            return 0;
        }

        $idleDelta = $second['idle'] - $first['idle'];
        $totalDelta = $second['total'] - $first['total'];
        if ($totalDelta <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round((1 - ($idleDelta / $totalDelta)) * 100)));
    }

    /**
     * @return array{idle:int,total:int}|null
     */
    private function readCpuStat(): ?array
    {
        if (!is_readable('/proc/stat')) {
            return null;
        }

        $line = strtok((string) file_get_contents('/proc/stat'), "\n");
        if (!is_string($line) || !str_starts_with($line, 'cpu ')) {
            return null;
        }

        $parts = array_values(array_filter(explode(' ', trim($line)), static fn(string $part): bool => $part !== 'cpu' && $part !== ''));
        $values = array_map('intval', $parts);
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        return [
            'idle' => $idle,
            'total' => array_sum($values),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function memoryUsage(): array
    {
        $totalKb = 0;
        $availableKb = 0;
        if (is_readable('/proc/meminfo')) {
            $content = (string) file_get_contents('/proc/meminfo');
            if (preg_match('/^MemTotal:\s+(\d+)/m', $content, $m)) {
                $totalKb = (int) $m[1];
            }
            if (preg_match('/^MemAvailable:\s+(\d+)/m', $content, $m)) {
                $availableKb = (int) $m[1];
            }
        }

        $usedKb = max(0, $totalKb - $availableKb);
        $percent = $totalKb > 0 ? (int) round(($usedKb / $totalKb) * 100) : 0;

        return [
            'percent' => max(0, min(100, $percent)),
            'used' => $this->formatGb($usedKb * 1024),
            'total' => $this->formatGb($totalKb * 1024),
            'caption' => '%' . max(0, min(100, $percent)) . ' Kullanım Oranı',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function diskPath(array $settings): string
    {
        foreach (['default_web_root', 'backup_path'] as $key) {
            $path = (string) ($settings[$key] ?? '');
            if ($path !== '' && is_dir($path)) {
                return $path;
            }
        }

        return '/';
    }

    /**
     * @return array<string, int|string>
     */
    private function diskUsage(string $path): array
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (!is_float($total) || !is_float($free) || $total <= 0) {
            return [
                'percent' => 0,
                'used' => '0',
                'total' => '0',
                'caption' => 'Disk bilgisi alınamadı',
            ];
        }

        $used = max(0.0, $total - $free);
        $percent = (int) round(($used / $total) * 100);

        return [
            'percent' => max(0, min(100, $percent)),
            'used' => $this->formatGb($used),
            'total' => $this->formatGb($total),
            'caption' => $path . ' - Kullanım',
        ];
    }

    private function formatGb(float|int $bytes): string
    {
        $gb = ((float) $bytes) / 1024 / 1024 / 1024;
        if ($gb >= 10) {
            return (string) round($gb);
        }

        return rtrim(rtrim(number_format($gb, 1, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function serverName(array $settings): string
    {
        $configured = trim((string) ($settings['server_name'] ?? $settings['hostname'] ?? ''));
        if ($configured !== '') {
            return strtoupper(str_replace(' ', '-', $configured));
        }

        $hostname = gethostname();
        return is_string($hostname) && $hostname !== '' ? strtoupper($hostname) : 'AIL-SERVER';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function serverIp(array $settings): string
    {
        $configured = trim((string) ($settings['server_ip'] ?? ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_IP) !== false) {
            return $configured;
        }

        $ip = trim((string) @shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '127.0.0.1';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function timezoneLabel(array $settings): string
    {
        $timezone = trim((string) ($settings['timezone'] ?? date_default_timezone_get()));
        return $timezone !== '' ? $timezone : 'UTC';
    }

    private function osLabel(): string
    {
        if (is_readable('/etc/os-release')) {
            $content = (string) file_get_contents('/etc/os-release');
            if (preg_match('/^PRETTY_NAME="?(.*?)"?$/m', $content, $matches)) {
                return trim((string) $matches[1], '"');
            }
        }

        return php_uname('s') . ' ' . php_uname('r');
    }

    private function uptimeLabel(): string
    {
        if (!is_readable('/proc/uptime')) {
            return '-';
        }

        $raw = trim((string) file_get_contents('/proc/uptime'));
        $seconds = (int) floor((float) strtok($raw, ' '));
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days . ' gün ' . $hours . ' saat';
        }

        if ($hours > 0) {
            return $hours . ' saat ' . $minutes . ' dk';
        }

        return $minutes . ' dk';
    }

    private function loadCaption(int $percent): string
    {
        if ($percent < 60) {
            return 'Stabil';
        }

        if ($percent < 85) {
            return 'Yoğun';
        }

        return 'Kritik';
    }
}
