<?php

declare(strict_types=1);

namespace Ailhost\Services;

use Ailhost\Adapters\MariaDbAdapter;
use Ailhost\Adapters\PhpAdapter;

final class ServiceHealthService
{
    public function __construct(
        private readonly PhpAdapter $phpAdapter,
        private readonly MariaDbAdapter $mariaDbAdapter
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function status(): array
    {
        $php = $this->phpAdapter->execute(['action' => 'service_health']);
        $db = $this->mariaDbAdapter->execute(['action' => 'service_health']);

        return [
            'php_fpm' => (string) ($php['status'] ?? 'unknown'),
            'mariadb' => (string) ($db['status'] ?? 'unknown'),
        ];
    }
}
