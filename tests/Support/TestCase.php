<?php

declare(strict_types=1);

namespace Ailhost\Tests\Support;

use RuntimeException;

final class TestCase
{
    public static function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . ' Beklenen: ' . self::export($expected) . ', gelen: ' . self::export($actual));
        }
    }

    public static function assertContains(string $needle, string $haystack, string $message): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message . ' Aranan: ' . $needle);
        }
    }

    public static function tempDir(string $prefix): string
    {
        $base = sys_get_temp_dir() . '/ailhost-tests';
        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new RuntimeException('Test geçici dizini oluşturulamadı: ' . $base);
        }

        $path = $base . '/' . $prefix . '-' . bin2hex(random_bytes(6));
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Test geçici dizini oluşturulamadı: ' . $path);
        }

        return $path;
    }

    public static function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                self::removeDir($child);
                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }

    private static function export(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        return gettype($value);
    }
}
