<?php

declare(strict_types=1);

namespace Ailhost\Repositories;

interface JobRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * @param array<int, array<string, mixed>> $jobs
     */
    public function saveAll(array $jobs): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function logs(): array;

    /**
     * @param array<int, array<string, mixed>> $logs
     */
    public function saveLogs(array $logs): bool;
}
