<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class JsonStateStore
{
    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    public function readArray(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $data
     */
    public function writeArray(string $file, array $data): bool
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $lockHandle = fopen($file . '.lock', 'c');
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
}
