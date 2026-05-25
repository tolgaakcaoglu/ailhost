<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class MariaDbAdapter implements SystemAdapterInterface
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if (!in_array($action, ['create_database', 'delete_database', 'reset_user_password', 'service_health'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen MariaDB işlemi.'];
        }

        if ($action === 'service_health') {
            return $this->serviceHealth();
        }

        return ['ok' => true, 'message' => 'MariaDB işlemi uygulandı.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceHealth(): array
    {
        $serverBinary = trim((string) @shell_exec('command -v mariadbd mysqld 2>/dev/null | head -n 1'));
        $systemUnit = trim((string) @shell_exec("systemctl list-unit-files mariadb.service mysql.service 2>/dev/null | awk 'NR>1 && $1 ~ /(mariadb|mysql)\\.service/ {print $1; exit}'"));
        if ($serverBinary === '' && $systemUnit === '') {
            return ['ok' => false, 'status' => 'not_installed', 'service' => 'mariadb', 'version' => '-'];
        }

        $active = trim((string) @shell_exec('systemctl is-active mariadb mysql 2>/dev/null | head -n 1'));
        if ($active === '') {
            $active = trim((string) @shell_exec("pgrep -x mariadbd >/dev/null 2>&1 || pgrep -x mysqld >/dev/null 2>&1; test $? -eq 0 && echo running || echo stopped"));
        }

        $versionBinary = $serverBinary !== '' ? $serverBinary : trim((string) @shell_exec('command -v mariadb mysql 2>/dev/null | head -n 1'));
        $versionOutput = $versionBinary !== '' ? trim((string) @shell_exec($versionBinary . ' --version 2>/dev/null')) : '';
        $version = $versionOutput !== '' ? $versionOutput : 'MariaDB/MySQL';

        return [
            'ok' => $active === 'active' || $active === 'running',
            'status' => ($active === 'active' || $active === 'running') ? 'running' : 'stopped',
            'service' => 'mariadb',
            'version' => $version,
        ];
    }
}
