<?php

declare(strict_types=1);

namespace Ailhost\Core;

final class Response
{
    public function __construct(
        private readonly string $content,
        private readonly int $statusCode = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }

    public static function redirect(string $location, int $statusCode = 303): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            $statusCode,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
