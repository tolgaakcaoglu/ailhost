<?php

declare(strict_types=1);

namespace Ailhost\Repositories;

use Ailhost\Services\JsonStateStore;

final class JsonAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private readonly string $localVarPath,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    )
    {
    }

    public function all(): array
    {
        $decoded = $this->jsonStateStore->readArray($this->filePath());
        return is_array($decoded) ? $decoded : [];
    }

    public function saveAll(array $logs): bool
    {
        return $this->jsonStateStore->writeArray($this->filePath(), $logs);
    }

    private function filePath(): string
    {
        return $this->localVarPath . '/audit_logs.json';
    }
}
