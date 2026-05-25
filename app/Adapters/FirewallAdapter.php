<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class FirewallAdapter implements SystemAdapterInterface
{
    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if ($action !== 'status') {
            return ['ok' => false, 'message' => 'Desteklenmeyen firewall işlemi.'];
        }

        return [
            'ok' => true,
            'service' => 'firewalld',
            'status' => 'running',
            'default_policy' => 'deny',
            'open_ports' => ['22/tcp', '80/tcp', '443/tcp'],
        ];
    }
}
