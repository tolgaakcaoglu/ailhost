<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class FlashService
{
    private const KEY = 'flash_toast';

    public function setToast(string $message, string $type = 'info', bool $persistent = false): void
    {
        $_SESSION[self::KEY] = [
            'message' => $message,
            'type' => $type,
            'persistent' => $persistent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function consumeToast(): array
    {
        $toast = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return is_array($toast) ? $toast : [];
    }
}
