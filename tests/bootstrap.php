<?php

declare(strict_types=1);

if (!defined('AILHOST_ROOT')) {
    define('AILHOST_ROOT', dirname(__DIR__));
}

require_once AILHOST_ROOT . '/app/Core/Autoloader.php';

\Ailhost\Core\Autoloader::register(AILHOST_ROOT . '/app');

require_once __DIR__ . '/Support/TestCase.php';
