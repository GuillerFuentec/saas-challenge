<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value === false ? $default : $value;
    }
}
