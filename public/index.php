<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $remember = (string) ($_COOKIE['ailhost_remember'] ?? '') === '1';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => $remember ? (60 * 60 * 24 * 30) : 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$app = require __DIR__ . '/../bootstrap/app.php';

$request = \Ailhost\Core\Request::fromGlobals();
$response = $app['router']->dispatch($request);

$response->send();
