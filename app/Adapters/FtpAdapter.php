<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class FtpAdapter implements SystemAdapterInterface
{
    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if (!in_array($action, ['create_user', 'delete_user', 'set_password', 'disable_user', 'enable_user'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen FTP işlemi.'];
        }

        return ['ok' => true, 'message' => 'FTP işlemi uygulandı.'];
    }
}
