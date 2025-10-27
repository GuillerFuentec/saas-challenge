<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CredentialService;
use App\Support\Config;
use RuntimeException;

final class AuthController
{
    public function __construct(
        private readonly Config $config,
        private readonly CredentialService $credentialService
    ) {
    }

    public function login(Request $request): void
    {
        $body = $request->body();
        $username = (string) ($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $clientEmail = strtolower((string) ($body['clientEmail'] ?? ''));

        if ($clientEmail === '') {
            Response::json(['message' => 'clientEmail is required'], 422);
            return;
        }

        $expectedUser = (string) $this->config->get('LOGIN_USERNAME', 'admin');
        $expectedPass = (string) $this->config->get('LOGIN_PASSWORD', 'secret123');

        if ($username !== $expectedUser || $password !== $expectedPass) {
            Response::json(['message' => 'Invalid credentials'], 401);
            return;
        }

        try {
            $credentials = $this->credentialService->fetchByEmail($clientEmail);
        } catch (RuntimeException $exception) {
            $status = $exception->getCode();
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            Response::json(['message' => $exception->getMessage()], $status);
            return;
        }

        Response::json([
            'message' => 'Authentication successful',
            'tenant' => $credentials['tenant'],
            'storage' => $credentials['storage'],
            'clientEmail' => $clientEmail
        ]);
    }
}
