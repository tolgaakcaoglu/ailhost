<?php

declare(strict_types=1);

namespace Ailhost\Repositories;

use Ailhost\Services\JsonStateStore;

final class JsonJobRepository implements JobRepositoryInterface
{
    public function __construct(
        private readonly string $localVarPath,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    )
    {
    }

    public function all(): array
    {
        $decoded = $this->readJson($this->jobsFile());
        return is_array($decoded) ? $decoded : [];
    }

    public function saveAll(array $jobs): bool
    {
        return $this->writeJson($this->jobsFile(), $jobs);
    }

    public function logs(): array
    {
        $decoded = $this->readJson($this->logsFile());
        return is_array($decoded) ? $decoded : [];
    }

    public function saveLogs(array $logs): bool
    {
        return $this->writeJson($this->logsFile(), $logs);
    }

    private function jobsFile(): string
    {
        return $this->localVarPath . '/jobs.json';
    }

    private function logsFile(): string
    {
        return $this->localVarPath . '/job_logs.json';
    }

    private function readJson(string $file): mixed
    {
        return $this->jsonStateStore->readArray($file);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function writeJson(string $file, array $data): bool
    {
        return $this->jsonStateStore->writeArray($file, $data);
    }
}
