<?php

declare(strict_types=1);

return [
    'name' => 'ailhost',
    'env' => getenv('AILHOST_ENV') ?: 'development',
    'debug' => (getenv('AILHOST_DEBUG') ?: '1') === '1',
    'base_url' => getenv('AILHOST_BASE_URL') ?: 'http://localhost:8080',
];
