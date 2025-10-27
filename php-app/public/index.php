<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\ColorController;
use App\Database\TenantConnectionFactory;
use App\Http\Request;
use App\Http\Response;
use App\Services\CredentialService;
use App\Support\Config;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = Request::capture();
$config = new Config();
$credentialService = new CredentialService((string) $config->get('NODE_API_BASE_URL', 'http://node-api:3000'));
$connectionFactory = new TenantConnectionFactory($credentialService);
$authController = new AuthController($config, $credentialService);
$colorController = new ColorController($connectionFactory);

$method = $request->method();
$path = $request->path();

if ($method === 'GET' && $path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ERP SaaS API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; line-height: 1.5; }
        code { background: #f4f4f4; padding: 0.2rem 0.4rem; border-radius: 4px; }
        ul { list-style: disc; margin-left: 1.5rem; }
    </style>
</head>
<body>
    <h1>ERP SaaS PHP API</h1>
    <p>The service is running inside Docker. Use the following endpoints:</p>
    <ul>
        <li><code>POST /login</code> &ndash; authenticate (credentials in <code>.env</code>).</li>
        <li><code>GET /colors?clientEmail=&lt;email&gt;</code> &ndash; list tenant colors.</li>
        <li><code>POST /colors</code>, <code>PUT /colors/{id}</code>, <code>DELETE /colors/{id}</code> &ndash; manage colors.</li>
    </ul>
    <p>Ensure <code>clientEmail</code> matches a tenant configured via the Node credential API.</p>
</body>
</html>
HTML;
    return;
}

if ($method === 'POST' && $path === '/login') {
    $authController->login($request);
    return;
}

if ($path === '/colors') {
    match ($method) {
        'GET' => $colorController->index($request),
        'POST' => $colorController->store($request),
        default => Response::json(['message' => 'Method not allowed'], 405)
    };

    return;
}

if (preg_match('#^/colors/(\d+)$#', $path, $matches)) {
    $colorId = (int) $matches[1];

    match ($method) {
        'PUT' => $colorController->update($request, $colorId),
        'DELETE' => $colorController->destroy($request, $colorId),
        default => Response::json(['message' => 'Method not allowed'], 405)
    };

    return;
}

Response::json(['message' => 'Resource not found'], 404);
