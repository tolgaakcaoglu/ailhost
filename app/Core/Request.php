<?php

declare(strict_types=1);

namespace Ailhost\Core;

final class Request
{
    /** @param array<string, mixed> $post */
    /** @param array<string, mixed> $query */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $post = [],
        private readonly array $query = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        $post = $_POST;
        if (!is_array($post)) {
            $post = [];
        }

        $query = $_GET;
        if (!is_array($query)) {
            $query = [];
        }

        return new self(strtoupper($method), is_string($path) ? $path : '/', $post, $query);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }
}
