# Loyalty Cards — Backend API

Laravel backend for the Loyalty Cards project. **API only** — the customer app,
merchant app and admin dashboard are separate projects that talk to this service
over HTTP.

- Framework: Laravel 13 (PHP 8.3)
- Database: MySQL 8.4
- Auth: customers by phone + WhatsApp OTP (Sanctum tokens); merchants and admins by Clerk
- Responses: JSON only, versioned under `/api/v1`

The full endpoint reference for frontend developers is in [api.md](api.md).

## Requirements

| Tool     | Version used |
| -------- | ------------ |
| PHP      | 8.3          |
| Composer | 2.9          |
| MySQL    | 8.4          |

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip`.

## Setup

Create the database, then:

```sql
CREATE DATABASE loyalty_cards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```sh
composer setup      # install, copy .env, generate key, migrate
php artisan db:seed # lists, packages, settings, admin link — plus demo data locally
```

## Running

```sh
composer dev          # serve + queue worker together
php artisan serve     # HTTP only, http://127.0.0.1:8000
```

| Command                  | Purpose                     |
| ------------------------ | --------------------------- |
| `composer test`          | Run the test suite          |
| `composer lint`          | Format the code with Pint   |
| `php artisan route:list` | List every registered route |

## Running with Docker

For frontend developers who don't want PHP or MySQL installed. Requires Docker
Desktop.

```sh
cp .env.example .env        # optional: only needed for Clerk or LightOTP keys
docker compose up --build -d
```

| What                           | Where                                 |
| ------------------------------ | ------------------------------------- |
| API                            | http://localhost:8000/api/v1/ping     |
| From a phone on the same Wi-Fi | `http://<your-computer-LAN-IP>:8000` |

The first start builds the images (several minutes), runs the migrations and
loads demo data. Later starts reuse the existing data.

- **OTP codes** are not sent over WhatsApp; they appear in the logs:
  `docker compose logs -f app` (look for `OTP code issued`).
- **Clerk:** put `CLERK_JWT_KEY`, `CLERK_AUTHORIZED_PARTIES`, `ADMIN_EMAIL` and
  `ADMIN_CLERK_USER_ID` in `.env`, run `docker compose up -d` to apply them, then
  `docker compose exec app php artisan db:seed --class=AdminUserSeeder` to link the admin.
- **After pulling backend changes:** `docker compose up --build -d`. If the
  migrations changed in an incompatible way, reset with `docker compose down -v`
  followed by `docker compose up --build -d`, which re-seeds from scratch.

| Command                                    | Purpose                                         |
| ------------------------------------------ | ----------------------------------------------- |
| `docker compose ps`                        | Service status                                  |
| `docker compose logs -f app`               | Application logs and OTP codes                  |
| `docker compose exec app php artisan test` | Run the test suite (in-memory database)         |
| `docker compose down`                      | Stop, keep data                                 |
| `docker compose down -v`                   | Stop and delete all data; the next start re-seeds |

MySQL runs inside the stack and is not published on the host, so it does not
clash with a local MySQL on port 3306. Change the API port with `API_PORT=8080`
in `.env`.

## Connecting the frontend

Set the frontend origins in `.env` — these drive CORS. Comma separated, no
trailing slash:

```env
FRONTEND_URL=http://localhost:3000
FRONTEND_URLS=http://localhost:3000,http://localhost:5173
```

## Authentication

| Who                | Signs in with                      | Sends as `Authorization: Bearer`     |
| ------------------ | ---------------------------------- | ------------------------------------ |
| Customer           | Phone number + WhatsApp OTP        | The token returned by `otp/verify`   |
| Merchant and admin | Clerk (Google and other providers) | A Clerk session token on every request |

The WhatsApp OTP is for the customer app only; merchants and admins never use it.
The `phone` a merchant enters at registration is the shop's contact number, not a
sign-in.

### Customer OTP

Codes are generated and verified here; [LightOTP](https://lightotp.com) only
delivers them over WhatsApp.

```env
OTP_DRIVER=log          # local: the code is written to storage/logs/laravel.log
OTP_DRIVER=lightotp     # real delivery, needs the key below
LIGHTOTP_API_KEY=
```

### Clerk (merchants and admins)

The merchant app and the admin dashboard use **one** Clerk application. Clerk
proves who the user is; this backend decides what they may do: an admin is a
Clerk user linked to a row in `admins`, a merchant is one linked to a row in
`merchants`.

1. In the Clerk Dashboard, open **API keys** and copy the **JWT public key**
   (PEM). Put it in `.env` on one line, replacing each line break with `\n`:

   ```env
   CLERK_JWT_KEY=-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqh...\n-----END PUBLIC KEY-----
   ```

   Tokens are verified locally with this key — no call to Clerk per request.

2. List the web origins allowed to send Clerk tokens (the admin dashboard).
   Tokens from the native merchant app carry no origin and are accepted:

   ```env
   CLERK_AUTHORIZED_PARTIES=http://localhost:3000
   ```

3. Add **`email` to the session token claims** (Clerk Dashboard → Sessions →
   Customize session token). The backend needs it for three things: the hashed
   fingerprint that stops the free trial being taken twice, the merchant's
   stored email, and linking a new dashboard account on its first sign-in.
   Clerk's default `fva` claim (minutes since the user last verified) is used as
   is to require a fresh sign-in before a merchant changes the PIN.

4. Sign in to the admin dashboard once, copy your user id (`user_…`) from
   **Clerk Dashboard → Users**, and link it as the first super admin:

   ```env
   ADMIN_EMAIL=owner@example.com
   ADMIN_CLERK_USER_ID=user_2abc...
   ```

   ```sh
   php artisan db:seed --class=AdminUserSeeder
   ```

   Every other dashboard account is added from the dashboard by a super admin
   (name, email, role) and links itself the first time its owner signs in to
   Clerk with that email. What each of the four roles may do is the matrix in
   `AdminRole::permissions()`; each permission is a gate the routes check.

Merchants need no setup: after signing in with Clerk they register in three
steps — business details, package (which starts the free trial) and PIN.
`GET /api/v1/merchant/auth/me` tells the app which step is next. The PIN-protected
tabs require the `X-Pin-Token` header returned by `POST /api/v1/merchant/pin/verify`
(middleware `merchant.pin`).

### Merchant app version

The merchant app is distributed outside the stores, so every request from it
carries `X-App-Version`. Anything older than the `merchant_min_app_version`
setting is refused with `426` and `code: app_update_required`, which drives the
forced-update screen. Requests without the header pass.

## Endpoints

| Method | Path                                      | Auth           | Purpose                                |
| ------ | ----------------------------------------- | -------------- | -------------------------------------- |
| GET    | `/up`                                     | –              | Health check                           |
| GET    | `/api/v1/ping`                            | –              | Connectivity check                     |
| GET    | `/api/v1/lookups/governorates`            | –              | The 14 governorates                    |
| GET    | `/api/v1/lookups/business-types`          | –              | Business types                         |
| GET    | `/api/v1/lookups/icons`                   | –              | Card icon library                      |
| GET    | `/api/v1/lookups/packages`                | –              | Packages with their price matrix       |
| GET    | `/api/v1/policy`                          | –              | Current privacy policy version and URLs |
| POST   | `/api/v1/customer/auth/otp/request`       | –              | Send a login code over WhatsApp        |
| POST   | `/api/v1/customer/auth/otp/verify`        | –              | Verify, create the account, return a token |
| GET    | `/api/v1/customer/auth/me`                | customer token | Current customer                       |
| POST   | `/api/v1/customer/auth/logout`            | customer token | Revoke the current token               |
| POST   | `/api/v1/customer/auth/logout-all`        | customer token | Revoke every token and device          |
| POST   | `/api/v1/customer/devices`                | customer token | Register the device's push token       |
| POST   | `/api/v1/customer/policy/accept`          | customer token | Agree to a new privacy policy version  |
| DELETE | `/api/v1/customer/account`                | customer token | Delete the account (anonymised stamps stay) |
| GET    | `/api/v1/merchant/auth/me`                | Clerk          | Merchant state and the next step       |
| POST   | `/api/v1/merchant/auth/logout`            | Clerk          | Forget this device's push token        |
| POST   | `/api/v1/merchant/registration/business`  | Clerk          | Step 1 — business details              |
| POST   | `/api/v1/merchant/registration/package`   | Clerk          | Step 2 — package, starts the trial     |
| POST   | `/api/v1/merchant/registration/pin`       | Clerk          | Step 3 — set the PIN                   |
| POST   | `/api/v1/merchant/pin/verify`             | merchant       | Check the PIN, return an unlock token  |
| PUT    | `/api/v1/merchant/pin`                    | merchant + fresh Clerk sign-in | Change or reset the PIN |
| POST   | `/api/v1/merchant/devices`                | merchant       | Register the device's push token       |
| GET    | `/api/v1/admin/auth/me`                   | Clerk + admin  | Current dashboard user, role, permissions |
| GET    | `/api/v1/admin/admin-users`               | super admin    | List dashboard accounts                |
| POST   | `/api/v1/admin/admin-users`               | super admin    | Add a dashboard account                |
| PATCH  | `/api/v1/admin/admin-users/{id}`          | super admin    | Rename, change role, switch on or off  |
| DELETE | `/api/v1/admin/admin-users/{id}`          | super admin    | Deactivate a dashboard account         |

Request bodies, responses and every error are documented in [api.md](api.md).

## Database

The schema, relationships and business rules are documented in
[docs/database-schema.md](docs/database-schema.md). `php artisan db:seed` loads
the governorates, business types, icon library, packages with their prices and
the runtime settings, and links the admin from `ADMIN_CLERK_USER_ID`. In the
`local` environment it also runs `DemoSeeder` (a demo merchant, card, customers
and stamps).

Operational numbers — trial length, grace days, stamp range, QR period, privacy
policy version, minimum app version — live in the `settings` table and are read
at runtime, so changing them needs no deploy.

## Rate limits

| Limiter      | Applies to                    | Limit                                  |
| ------------ | ----------------------------- | -------------------------------------- |
| `api`        | every `/api/*` route          | 60 per minute, per user or IP          |
| `otp`        | `customer/auth/otp/request`   | 5 per hour per phone, 20 per hour per IP |
| `otp-verify` | `customer/auth/otp/verify`    | 10 per minute per phone, 30 per IP     |
| `pin`        | `merchant/pin/verify`         | 5 per minute per merchant              |

A new OTP for the same phone also requires a 60-second wait.

## Layout

```
app/Enums/                     Status and type values used across the schema
app/Http/Controllers/Api/V1/   API controllers (Customer, Merchant, Admin)
app/Http/Middleware/           JSON responses, Clerk session, role and app-version checks
app/Http/Requests/Api/V1/      Validation (form requests)
app/Http/Resources/            JSON output shaping
app/Services/Otp/              OTP issuing, verification and delivery drivers
app/Services/Clerk/            Clerk session token verification
app/Support/                   Phone number normalization
routes/api.php                 Versioned API routes
tests/Feature/Api/V1/          Endpoint tests
```

## Notes

- No `package.json`, no Vite — this project builds no frontend assets.
- Sessions use the `array` driver: the API is stateless.
- Models run in strict mode outside production, so lazy loading and silently
  discarded attributes fail loudly during development.
