<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

interface SystemAdapterInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload): array;
}
