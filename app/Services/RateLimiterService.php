<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class RateLimiterService
{
    public function __construct(private readonly string $localVarPath)
    {
    }

    /**
     * @param array<string, array<string, int|string>> $buckets
     * @return array{locked: bool, retry_after: int}
     */
    public function check(array $buckets): array
    {
        $state = $this->readState();
        $now = time();

        foreach ($buckets as $key => $_config) {
            $row = is_array($state[$key] ?? null) ? $state[$key] : [];
            $lockUntil = (int) ($row['lock_until'] ?? 0);
            if ($lockUntil > $now) {
                return [
                    'locked' => true,
                    'retry_after' => max(1, $lockUntil - $now),
                ];
            }
        }

        return ['locked' => false, 'retry_after' => 0];
    }

    /**
     * @param array<string, array<string, int|string>> $buckets
     */
    public function hit(array $buckets): void
    {
        $state = $this->readState();
        $now = time();

        foreach ($buckets as $key => $config) {
            $row = is_array($state[$key] ?? null) ? $state[$key] : [
                'count' => 0,
                'window_started_at' => $now,
                'lock_until' => 0,
            ];

            $windowSeconds = (int) ($config['window_seconds'] ?? 900);
            $maxAttempts = (int) ($config['max_attempts'] ?? 5);
            $lockSeconds = (int) ($config['lock_seconds'] ?? 900);
            $windowStart = (int) ($row['window_started_at'] ?? $now);

            if (($now - $windowStart) > $windowSeconds) {
                $row['count'] = 0;
                $row['window_started_at'] = $now;
            }

            $row['count'] = (int) ($row['count'] ?? 0) + 1;
            if ((int) $row['count'] >= $maxAttempts) {
                $row['lock_until'] = $now + $lockSeconds;
                $row['count'] = 0;
                $row['window_started_at'] = $now;
            }

            $row['updated_at'] = date(DATE_ATOM);
            $state[$key] = $row;
        }

        $this->writeState($this->pruneState($state, $now));
    }

    /**
     * @param array<int, string> $keys
     */
    public function clear(array $keys): void
    {
        $state = $this->readState();
        $changed = false;
        foreach ($keys as $key) {
            if (isset($state[$key])) {
                unset($state[$key]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->writeState($state);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $file = $this->stateFile();
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): void
    {
        $file = $this->stateFile();
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $lock = fopen($file . '.lock', 'c');
        if ($lock === false) {
            return;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return;
            }

            $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                return;
            }

            $tmp = $file . '.tmp.' . bin2hex(random_bytes(6));
            if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
                @unlink($tmp);
                return;
            }

            if (!rename($tmp, $file)) {
                @unlink($tmp);
                return;
            }

            @chmod($file, 0664);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function pruneState(array $state, int $now): array
    {
        foreach ($state as $key => $row) {
            if (!is_array($row)) {
                unset($state[$key]);
                continue;
            }

            $lockUntil = (int) ($row['lock_until'] ?? 0);
            $windowStart = (int) ($row['window_started_at'] ?? $now);
            if ($lockUntil > 0 && $lockUntil < ($now - 86400)) {
                unset($state[$key]);
                continue;
            }
            if ($lockUntil <= 0 && ($now - $windowStart) > 86400) {
                unset($state[$key]);
            }
        }

        return $state;
    }

    private function stateFile(): string
    {
        return $this->localVarPath . '/login_attempts.json';
    }
}
