<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class WordPressAdapter implements SystemAdapterInterface
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $allowed = [
            'install',
            'plugin_install',
            'plugin_delete',
            'plugin_list',
            'theme_activate',
            'theme_list',
            'core_update',
            'maintenance_toggle',
        ];
        if (!in_array($action, $allowed, true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen WordPress işlemi.'];
        }

        return ['ok' => true, 'message' => 'WordPress işlemi uygulandı.'];
    }
}
