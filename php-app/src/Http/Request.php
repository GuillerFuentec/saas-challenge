<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    private array $body;
    private array $query;

    public function __construct(private readonly string $method, private readonly string $path)
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '', true);
        $this->body = is_array($decoded) ? $decoded : [];

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        parse_str($queryString, $parsedQuery);
        $this->query = $parsedQuery;
    }

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return new self(strtoupper($method), $path);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return rtrim($this->path, '/') ?: '/';
    }

    public function body(): array
    {
        return $this->body;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
}
