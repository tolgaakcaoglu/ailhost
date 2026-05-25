<?php

declare(strict_types=1);

namespace Ailhost\Workers;

interface WorkerInterface
{
    public function run(): void;
}
