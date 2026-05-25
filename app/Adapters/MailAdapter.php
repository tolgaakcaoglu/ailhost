<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class MailAdapter implements SystemAdapterInterface
{
    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if (!in_array($action, ['create_mailbox', 'delete_mailbox', 'service_health'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen mail işlemi.'];
        }

        if ($action === 'service_health') {
            return [
                'ok' => true,
                'postfix' => 'running',
                'dovecot' => 'running',
                'queue_size' => 0,
            ];
        }

        return ['ok' => true, 'message' => 'Mail işlemi uygulandı.'];
    }
}
