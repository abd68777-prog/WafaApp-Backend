# Loyalty Cards — Backend API

Laravel backend for the Loyalty Cards project. **API only** — the frontend is a
separate project that talks to this service over HTTP.

- Framework: Laravel 13 (PHP 8.3)
- Auth: Laravel Sanctum, bearer tokens
- Database: MySQL 8.4
- Responses: JSON only, versioned under `/api/v1`

## Requirements

| Tool     | Version used |
| -------- | ------------ |
| PHP      | 8.3          |
| Composer | 2.9          |
| MySQL    | 8.4          |

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip`.

## Setup

```sh
composer setup
```

That runs `composer install`, copies `.env.example` to `.env`, generates the app
key, and runs migrations. Create the database first:

```sql
CREATE DATABASE loyalty_cards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Running

```sh
composer dev          # serve + queue worker together
php artisan serve     # HTTP only, http://127.0.0.1:8000
```

| Command         | Purpose                          |
| --------------- | -------------------------------- |
| `composer test` | Run the test suite               |
| `composer lint` | Format the code with Pint        |
| `php artisan route:list` | List every registered route |

## Connecting the frontend

Set the frontend origins in `.env` — these drive CORS. Comma separated, no
trailing slash:

```env
FRONTEND_URL=http://localhost:3000
FRONTEND_URLS=http://localhost:3000,http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:5173
```

Requests from any other origin are rejected by the browser. `supports_credentials`
is on, so a wildcard origin is deliberately not used.

## Authentication

Token based (Sanctum). Each account type has its own table: `admins`,
`merchants` and `customers`. There is no public registration for admins — the
platform owner account is created by the seeder from `.env`:

```sh
# .env: ADMIN_EMAIL=... ADMIN_PASSWORD=...
php artisan db:seed
```

Log in, then send the token on every request:

```
Authorization: Bearer <token>
```

```sh
curl -X POST http://127.0.0.1:8000/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"owner@example.com","password":"your-password","device_name":"dashboard"}'

curl http://127.0.0.1:8000/api/v1/admin/auth/me -H "Authorization: Bearer <token>"
```

Customer (WhatsApp OTP) and merchant authentication come in the next API phase.

## Endpoints

| Method | Path                            | Auth   | Purpose                        |
| ------ | ------------------------------- | ------ | ------------------------------ |
| GET    | `/`                             | –      | Landing page (JSON for API clients) |
| GET    | `/up`                           | –      | Health check                   |
| GET    | `/api/v1/ping`                  | –      | Connectivity check             |
| POST   | `/api/v1/admin/auth/login`      | –      | Exchange admin credentials for a token |
| GET    | `/api/v1/admin/auth/me`         | token  | Current admin                  |
| POST   | `/api/v1/admin/auth/logout`     | token  | Revoke the current token       |
| POST   | `/api/v1/admin/auth/logout-all` | token  | Revoke every token of the admin |

## Database

The schema, relationships and business rules are documented in
[docs/database-schema.md](docs/database-schema.md). `php artisan db:seed` loads
the three packages and default platform settings; in the `local` environment it
also loads demo data (merchant login `merchant@example.com` / `password`).

## Response shapes

Success — single resource:

```json
{ "data": { "id": 1, "name": "Sara", "email": "sara@example.com" } }
```

Validation failure — `422`:

```json
{ "message": "The email field is required.", "errors": { "email": ["The email field is required."] } }
```

Other errors return `{"message": "..."}` with the matching status: `401`
unauthenticated, `404` not found, `429` rate limited.

## Rate limits

| Limiter | Applies to            | Limit          | Keyed by         |
| ------- | --------------------- | -------------- | ---------------- |
| `api`   | every `/api/*` route  | 60 per minute  | user id, else IP |
| `auth`  | register, login       | 5 per minute   | email + IP       |

`X-RateLimit-Limit` and `X-RateLimit-Remaining` are exposed to the frontend.

## Layout

```
app/Http/Controllers/Api/V1/   API controllers
app/Http/Requests/Api/V1/      Validation (form requests)
app/Http/Resources/            JSON output shaping
app/Http/Middleware/           ForceJsonResponse
app/Services/                  Business logic
routes/api.php                 Versioned API routes
routes/web.php                 Landing page only
tests/Feature/Api/V1/          Endpoint tests
```

Adding a resource: create the migration and model, add a controller under
`Api/V1`, register it inside the `auth:sanctum` group in `routes/api.php`, and
cover it with a feature test.

## Notes

- No `package.json`, no Vite — this project builds no frontend assets. The
  landing page uses Laravel's built-in fallback styling.
- Sessions are set to the `array` driver: the API is stateless.
- Models run in strict mode outside production, so lazy loading and silently
  discarded attributes fail loudly during development.
