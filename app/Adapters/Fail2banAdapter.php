<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class Fail2banAdapter implements SystemAdapterInterface
{
    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if ($action !== 'status') {
            return ['ok' => false, 'message' => 'Desteklenmeyen fail2ban işlemi.'];
        }

        return [
            'ok' => true,
            'service' => 'fail2ban',
            'status' => 'running',
            'jails' => [
                ['name' => 'sshd', 'banned' => 0],
                ['name' => 'nginx-http-auth', 'banned' => 0],
            ],
        ];
    }
}
