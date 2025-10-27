<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\CredentialService;
use PDO;
use PDOException;
use RuntimeException;

final class TenantConnectionFactory
{
    public function __construct(private readonly CredentialService $credentialService)
    {
    }

    public function open(string $clientEmail): array
    {
        $credentials = $this->credentialService->fetchByEmail($clientEmail);
        $db = $credentials['db'];

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            $pdo = new PDO($dsn, $db['user'], $db['password'], $options);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to tenant database: ' . $exception->getMessage(), 0, $exception);
        }

        return [$pdo, $credentials];
    }
}
