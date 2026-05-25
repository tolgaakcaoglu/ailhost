<?php

declare(strict_types=1);

namespace Ailhost\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $methodRoutes = $this->routes[$request->method()] ?? [];
        $handler = $methodRoutes[$request->path()] ?? null;

        if ($handler === null) {
            return new Response(View::render('errors/404', [
                'title' => 'Not Found',
                'path' => $request->path(),
            ]), 404);
        }

        return $handler($request);
    }
}
