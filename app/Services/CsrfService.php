<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class CsrfService
{
    private const SESSION_KEY = 'csrf_token';

    public function token(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY]) || $_SESSION[self::SESSION_KEY] === '') {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function isValid(string $submitted): bool
    {
        $current = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($current) || $current === '' || $submitted === '') {
            return false;
        }

        return hash_equals($current, $submitted);
    }
}
