<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\TenantConnectionFactory;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\ColorRepository;
use RuntimeException;

final class ColorController
{
    public function __construct(private readonly TenantConnectionFactory $connections)
    {
    }

    public function index(Request $request): void
    {
        $clientEmail = $this->requireClientEmail($request);
        if ($clientEmail === null) {
            return;
        }

        try {
            [$pdo, $credentials] = $this->connections->open($clientEmail);
            $repository = new ColorRepository($pdo);
            $colors = $repository->all();

            Response::json([
                'tenant' => $credentials['tenant'],
                'count' => count($colors),
                'data' => $colors
            ]);
        } catch (RuntimeException $exception) {
            $this->renderException($exception);
        }
    }

    public function store(Request $request): void
    {
        $clientEmail = $this->requireClientEmail($request);
        if ($clientEmail === null) {
            return;
        }

        $body = $request->body();
        $name = trim((string) ($body['name'] ?? ''));
        $hex = strtoupper(trim((string) ($body['hexadecimal'] ?? '')));

        $errors = [];
        if ($name === '') {
            $errors[] = 'name is required';
        }

        if ($hex === '' || !preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            $errors[] = 'hexadecimal must follow the #RRGGBB pattern';
        }

        if ($errors) {
            Response::json(['message' => 'Validation failed', 'errors' => $errors], 422);
            return;
        }

        try {
            [$pdo, $credentials] = $this->connections->open($clientEmail);
            $repository = new ColorRepository($pdo);
            $color = $repository->create($name, $hex);

            Response::json([
                'tenant' => $credentials['tenant'],
                'data' => $color
            ], 201);
        } catch (RuntimeException $exception) {
            $this->renderException($exception);
        }
    }

    public function update(Request $request, int $id): void
    {
        $clientEmail = $this->requireClientEmail($request);
        if ($clientEmail === null) {
            return;
        }

        $body = $request->body();
        $payload = [];
        $errors = [];

        if (array_key_exists('name', $body)) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                $errors[] = 'name cannot be empty';
            } else {
                $payload['name'] = $name;
            }
        }

        if (array_key_exists('hexadecimal', $body)) {
            $hex = strtoupper(trim((string) $body['hexadecimal']));
            if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                $errors[] = 'hexadecimal must follow the #RRGGBB pattern';
            } else {
                $payload['hexadecimal'] = $hex;
            }
        }

        if ($payload === [] && $errors === []) {
            $errors[] = 'Provide at least one field to update';
        }

        if ($errors) {
            Response::json(['message' => 'Validation failed', 'errors' => $errors], 422);
            return;
        }

        try {
            [$pdo, $credentials] = $this->connections->open($clientEmail);
            $repository = new ColorRepository($pdo);
            $color = $repository->update($id, $payload);

            if ($color === null) {
                Response::json(['message' => 'Color not found'], 404);
                return;
            }

            Response::json([
                'tenant' => $credentials['tenant'],
                'data' => $color
            ]);
        } catch (RuntimeException $exception) {
            $this->renderException($exception);
        }
    }

    public function destroy(Request $request, int $id): void
    {
        $clientEmail = $this->requireClientEmail($request);
        if ($clientEmail === null) {
            return;
        }

        try {
            [$pdo, $credentials] = $this->connections->open($clientEmail);
            $repository = new ColorRepository($pdo);

            if (!$repository->delete($id)) {
                Response::json(['message' => 'Color not found'], 404);
                return;
            }

            Response::json([
                'tenant' => $credentials['tenant'],
                'message' => 'Color removed successfully'
            ]);
        } catch (RuntimeException $exception) {
            $this->renderException($exception);
        }
    }

    private function requireClientEmail(Request $request): ?string
    {
        $email = (string) $request->input('clientEmail', '');
        if ($email === '') {
            Response::json(['message' => 'clientEmail is required'], 422);
            return null;
        }

        return strtolower($email);
    }

    private function renderException(RuntimeException $exception): void
    {
        $status = $exception->getCode();
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        Response::json([
            'message' => $exception->getMessage()
        ], $status);
    }
}
