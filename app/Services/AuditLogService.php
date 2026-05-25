<?php

declare(strict_types=1);

namespace Ailhost\Services;

use Ailhost\Repositories\AuditLogRepositoryInterface;

final class AuditLogService
{
    public function __construct(private readonly AuditLogRepositoryInterface $repository)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        ?string $actorEmail,
        string $action,
        string $resourceType,
        string $resourceId,
        array $metadata = []
    ): void {
        $logs = $this->repository->all();
        $logs[] = [
            'id' => bin2hex(random_bytes(8)),
            'actor_email' => $actorEmail ?? '',
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $this->maskSensitive($metadata),
            'created_at' => date(DATE_ATOM),
        ];
        $this->repository->saveAll($logs);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $logs = $this->repository->all();
        if ($limit <= 0) {
            return $logs;
        }

        return array_slice($logs, -$limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return array<string, int>
     */
    public function pruneOlderThanDays(int $days): array
    {
        $days = max(1, min(3650, $days));
        $all = $this->repository->all();
        $threshold = time() - ($days * 86400);
        $kept = [];
        foreach ($all as $row) {
            if (!is_array($row)) {
                continue;
            }
            $createdAt = strtotime((string) ($row['created_at'] ?? ''));
            if ($createdAt !== false && $createdAt < $threshold) {
                continue;
            }
            $kept[] = $row;
        }
        $this->repository->saveAll($kept);
        return [
            'removed' => count($all) - count($kept),
            'remaining' => count($kept),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function maskSensitive(array $metadata): array
    {
        $masked = $metadata;
        foreach ($masked as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $k = strtolower($key);
            if (str_contains($k, 'password') || str_contains($k, 'token') || str_contains($k, 'secret')) {
                $masked[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskSensitive($value);
            }
        }

        return $masked;
    }
}
