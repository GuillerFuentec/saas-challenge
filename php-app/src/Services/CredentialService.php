<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CredentialService
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    public function fetchByEmail(string $email): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('NODE_API_BASE_URL is not configured');
        }

        $uri = rtrim($this->baseUrl, '/') . '/client/' . rawurlencode(strtolower($email));
        $payload = json_encode(['requestedAt' => time()]);

        $handle = curl_init($uri);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize HTTP client');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload ?: '{}'
        ]);

        $responseBody = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE) ?: 0;

        if ($responseBody === false) {
            $error = curl_error($handle) ?: 'Unknown cURL error';
            curl_close($handle);
            throw new RuntimeException('Credential API error: ' . $error);
        }

        curl_close($handle);

        $decoded = json_decode($responseBody, true);
        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? 'Credential service responded with an error';
            throw new RuntimeException($message, $httpCode);
        }

        if (!is_array($decoded) || !isset($decoded['db'])) {
            throw new RuntimeException('Credential response is malformed');
        }

        return $decoded;
    }
}
