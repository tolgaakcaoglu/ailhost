<?php

declare(strict_types=1);

namespace Ailhost\Repositories;

interface AuditLogRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * @param array<int, array<string, mixed>> $logs
     */
    public function saveAll(array $logs): bool;
}
