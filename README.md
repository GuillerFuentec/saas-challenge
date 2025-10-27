# ERP SaaS - Technical Challenge

This repository contains a minimal multi-tenant ERP prototype that mirrors the architecture described in the challenge brief. A Node.js service acts as the credential orchestrator, while the PHP service consumes those credentials to connect to isolated tenant databases hosted on MySQL. Everything ships with Docker so you can run the full stack with a single command.

## Stack

- **Node.js (Express + Prisma)** - central credential API (`/client/:email`).
- **PHP 8.2 + Apache** - simplified ERP API (login + Colors CRUD) that resolves tenants via the Node service.
- **MySQL 8** - hosts the credential store plus one database per tenant (`client1_db`, `client2_db`).
- **Docker Compose** - orchestrates all services on the same network.

## Prerequisites 

- Docker Engine 24+ (Docker Desktop on Windows/macOS; Docker + Compose plugin on Linux).
- 4 GB of free RAM for the three containers.
- Windows users: WSL2 backend enabled in Docker Desktop and PowerShell 7+ for the sample commands.
- Optional (only if you want to run services outside Docker): Node.js 20+ and PHP 8.2.

## Getting started

> All commands assume you are standing at the repository root `Technical Challenge ERP SaaS/`.

1. **Copy the root environment file**

   - Linux/macOS:
     ```bash
     cp .env.example .env
     ```
   - Windows PowerShell:
     ```powershell
     Copy-Item .env.example .env
     ```

   Edit `.env` to adjust ports (`MYSQL_PORT`, `NODE_API_PORT`, `PHP_HTTP_PORT`) or the ERP credentials (`LOGIN_USERNAME`, `LOGIN_PASSWORD`). The PHP container reads those values automatically.

2. **(Optional) Copy the PHP service `.env`**

   Only needed if you plan to run the PHP app outside Docker.

   ```bash
   cp php-app/.env.example php-app/.env          # Linux/macOS
   Copy-Item php-app/.env.example php-app/.env   # Windows PowerShell
   ```

3. **Launch the stack**

   ```bash
   docker compose up --build
   ```

   - Node API -> http://localhost:${NODE_API_PORT:-3000}
   - PHP ERP -> http://localhost:${PHP_HTTP_PORT:-8080}
   - MySQL -> localhost:${MYSQL_PORT:-3306}

   Wait until the logs show `Client credentials synced` (Prisma seed) and `ready for connections` (MySQL). The `--build` flag ensures Apache picks up the rewrite config needed for pretty URLs (e.g., `/login` without `index.php`).

4. **Default access data**

   - ERP login: values from `.env` (`LOGIN_USERNAME`, `LOGIN_PASSWORD`) – defaults are `admin` / `secret123`.
   - Tenants (`clientEmail`): `client1@example.com`, `client2@example.com`.

MySQL is provisioned automatically through the scripts in `mysql/init/`. Prisma syncs the credential schema and seeds the sample tenants on startup.

## Smoke tests

### Linux / macOS (bash)

```bash
# Node API health check
curl http://localhost:3000/health

# PHP login
curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret123","clientEmail":"client1@example.com"}'

# List colors for a tenant
curl "http://localhost:8080/colors?clientEmail=client1@example.com"
```

### Windows PowerShell

```powershell
# Node API health
Invoke-RestMethod http://localhost:3000/health

# PHP login (update username/password if you changed them)
Invoke-RestMethod -Uri http://localhost:8080/login -Method POST `
  -Headers @{ "Content-Type" = "application/json" } `
  -Body '{"username":"admin","password":"secret123","clientEmail":"client1@example.com"}'

# Colors index
Invoke-RestMethod "http://localhost:8080/colors?clientEmail=client1@example.com"
```

If you prefer `curl.exe` on Windows, pass JSON with single quotes or read it from a file to avoid PowerShell escaping issues:

```powershell
"{'username':'admin','password':'secret123','clientEmail':'client1@example.com'}" `
  | Set-Content -NoNewline payload.json

curl.exe -X POST http://localhost:8080/login `
  -H "Content-Type: application/json" `
  --data-binary "@payload.json"
```

## Service responsibilities

| Service | Responsibility |
|---------|----------------|
| `node-api` | Stores tenant metadata (DB + storage) in MySQL via Prisma and serves it through `POST /client/:email` plus a `/health` probe. |
| `php-app` | Resolves the tenant via Node, opens a PDO connection with the supplied credentials, and exposes `/login` (fixed auth) plus CRUD for `colors` scoped to the tenant DB. |
| `mysql` | Hosts `credential_store` (central table managed by Prisma) and one schema per tenant. |

## PHP ERP endpoints

All PHP endpoints live under `http://localhost:${PHP_HTTP_PORT:-8080}` and expect `Content-Type: application/json`.

### `POST /login`
```json
{
  "username": "admin",
  "password": "secret123",
  "clientEmail": "client1@example.com"
}
```
Response (200): returns tenant + storage info after resolving credentials through Node. Wrong credentials yield `401`, unknown tenants bubble up `404` from the Node API.

### `GET /colors?clientEmail=client1@example.com`
Returns every color stored inside the tenant database along with tenant metadata.

### `POST /colors`
```json
{
  "clientEmail": "client1@example.com",
  "name": "Azure",
  "hexadecimal": "#007FFF"
}
```
Creates a new color (201). The same `clientEmail` convention applies to `PUT /colors/:id` and `DELETE /colors/:id`; for non-GET verbs the email can be provided either in the JSON body or as a query parameter.

#### Sample CRUD flow (PowerShell)

```powershell
$headers = @{ "Content-Type" = "application/json" }
$baseUrl = "http://localhost:8080"
$clientEmail = "client1@example.com"

# Create
$payload = @{
  clientEmail = $clientEmail
  name = "Azure"
  hexadecimal = "#007FFF"
} | ConvertTo-Json
Invoke-RestMethod -Uri "$baseUrl/colors" -Method POST -Headers $headers -Body $payload

# Update (replace 1 with the returned id)
Invoke-RestMethod -Uri "$baseUrl/colors/1?clientEmail=$clientEmail" -Method PUT `
  -Headers $headers -Body '{"hexadecimal":"#0066FF"}'

# Delete
Invoke-RestMethod -Uri "$baseUrl/colors/1?clientEmail=$clientEmail" -Method DELETE
```

#### Sample CRUD flow (bash)

```bash
BASE_URL=http://localhost:8080

# Create
curl -X POST "$BASE_URL/colors" \
  -H "Content-Type: application/json" \
  -d '{"clientEmail":"client1@example.com","name":"Azure","hexadecimal":"#007FFF"}'

# Update (replace 1 with the created id)
curl -X PUT "$BASE_URL/colors/1?clientEmail=client1@example.com" \
  -H "Content-Type: application/json" \
  -d '{"hexadecimal":"#0066FF"}'

# Delete
curl -X DELETE "$BASE_URL/colors/1?clientEmail=client1@example.com"
```

## Node credential API

- `POST /client/:email` - returns `{ tenant, db, storage }` for the requested email.
- `GET /health` - quick readiness probe used for troubleshooting.

Credentials are persisted in the `ClientCredential` Prisma model. The Prisma seed (`node-api/prisma/seed.js`) keeps the data idempotent so re-running Docker Compose will not duplicate tenants.

## Useful commands

```bash
# Rebuild containers after code changes
docker compose up --build

# Remove containers/volumes
docker compose down -v
```

## Notes & assumptions

- Sensitive values (root passwords, login credentials, exposed ports) live in `.env`; individual services also support their own `.env.example` for standalone runs.
- The PHP service uses plain PDO + a lightweight autoloader to stay dependency-free in this environment, but the structure allows swapping to Composer packages if desired.
- MinIO is mocked in the credential payload; wiring a real object store only requires updating the seed data + Docker Compose.
- JWT between PHP <-> Node is left as an easy extension point (bonus requirement).

### Troubleshooting

- **`MYSQL_ROOT_PASSWORD` variable is not set**: ensure `.env` exists at the repository root *before* running `docker compose up`. Recreate containers with `docker compose down -v && docker compose up --build`.
- **`clientEmail is required` when testing with PowerShell**: PowerShell may split multiline JSON. Use single-line payloads, here-strings stored in a variable, or `--data-binary "@file.json"` with `curl.exe`.
- **Pretty URLs ( `/login`, `/colors` ) return HTML 404**: rebuild the PHP image so Apache picks up the bundled `.htaccess` rewrite rules (`docker compose up --build php-app`).

Feel free to plug in your own tenant databases by editing `mysql/init/01-init.sql` and the Prisma seed.
