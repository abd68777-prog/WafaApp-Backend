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
php artisan schedule:work   # subscription statuses (hourly), birthday reminders, idle-token cleanup
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
| `docker compose logs -f worker`            | Push notifications leaving through the queue    |
| `docker compose exec app php artisan test` | Run the test suite (in-memory database)         |
| `docker compose down`                      | Stop, keep data                                 |
| `docker compose down -v`                   | Stop and delete all data; the next start re-seeds |

Besides `app`, `web` and `db`, the stack runs `worker` (the queue, which sends
push notifications) and `scheduler` (subscription statuses every hour, and the daily jobs).

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
| Customer           | Phone number + WhatsApp OTP        | The token returned by `auth/verify`  |
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

### Store review account

Apple and Google reject an app whose reviewers cannot sign in, so the customer
app has one fixed number with a fixed code. Nothing is sent for it and no other
number accepts the code; both empty switches it off.

```env
REVIEW_PHONE=0900000999
REVIEW_OTP_CODE=246810        # 6 digits, the normal code length
```

```sh
php artisan db:seed --class=ReviewAccountSeeder   # two demo shops, a card in progress, a ready reward
```

Write the number and the code under **App access** in Play Console and in the
**App Review** notes in App Store Connect. The demo shops use Clerk ids no user
has, so nobody can sign in to them as a merchant.

### Clerk (merchants and admins)

The merchant app and the admin dashboard use **one** Clerk application. Clerk
proves who the user is; this backend decides what they may do: an admin is a
Clerk user linked to a row in `admin_users`, a merchant is one linked to a row in
`merchants`.

0. Create the application at [dashboard.clerk.com](https://dashboard.clerk.com)
   with **Email** and **Google** as the only sign-in options, email by
   verification code (no password, no magic link, no phone, no username) — the
   merchant sign-in screen of the requirements.

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
   Customize session token):

   ```json
   { "email": "{{user.primary_email_address}}" }
   ```

   The backend needs it for three things: the hashed
   fingerprint that stops the free trial being taken twice, the merchant's
   stored email, and linking a new dashboard account on its first sign-in.
   Clerk's default `fva` claim (minutes since the user last verified) is used as
   is to require a sign-in within the last five minutes before a merchant resets
   a forgotten PIN.

4. Create your own user (Clerk Dashboard → **Users** → Create user, or sign in
   once through Clerk's hosted sign-in page), copy its id (`user_…`), and link
   it as the first super admin:

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
`GET /api/v1/merchant/me` tells the app which step is next. The PIN-protected
tabs require the `X-Pin-Token` header returned by `POST /api/v1/merchant/pin/unlock`
(middleware `merchant.pin`).

### Testing against the real Clerk instance

`php artisan clerk:smoke-test` signs real session tokens and runs the merchant
and dashboard flows against the API: it creates throwaway Clerk users, registers
a shop through every step, links a dashboard account by email, checks your own
account is a super admin, then deletes everything it created. It needs the
**Secret key** of a development instance (Clerk Dashboard → API keys), which no
request of the API ever uses:

```env
CLERK_SECRET_KEY=sk_test_...
```

It writes to the database the API uses, so run it where the API runs:

```sh
php artisan serve                     # in one terminal
php artisan clerk:smoke-test          # in another

docker compose up -d                  # Docker: apply the .env values first
docker compose exec app php artisan clerk:smoke-test --base-url=http://web
```

`--keep` leaves the test users, shop and dashboard account in place. The command
refuses to run in production or with a live (`sk_live_`) key.

### Clerk webhooks

`POST /api/v1/webhooks/clerk` keeps our copy of Clerk users in step: a changed
primary email reaches the merchant and the dashboard account at once, and a
deleted Clerk user unlinks its dashboard account (so a user recreated with the
same email links again) while a shop is left untouched and only recorded in the
audit log. Deliveries are verified with the Svix signature; anything unsigned or
older than five minutes is refused with `400`.

In the Clerk Dashboard → **Webhooks** → Add endpoint, point it at
`https://<api-domain>/api/v1/webhooks/clerk`, subscribe to `user.updated` and
`user.deleted`, and put the endpoint's **Signing secret** in `.env`:

```env
CLERK_WEBHOOK_SECRET=whsec_...
```

Clerk cannot reach `localhost`; to receive real deliveries locally, expose the
API through a tunnel (cloudflared, ngrok).

The "PIN reset after a fresh sign-in" step always shows **SKIPPED**: Clerk
gives sessions opened from the backend `fva: [99999, -1]` (no recent
verification), so the API correctly answers `PIN_RESET_REQUIRES_RECENT_LOGIN`. A
real sign-in in the app produces a fresh `fva`, and that is where the reset is
tested end to end.

### Merchant app version

The merchant app is distributed outside the stores, so every request from it
carries `X-App-Version`. Anything older than the `merchant_min_app_version`
setting is refused with `426` and `APP_VERSION_UNSUPPORTED`, which drives the
forced-update screen. Requests without the header pass.

### Customer session length

A customer's token has no fixed lifetime: it stops working after
`CUSTOMER_TOKEN_IDLE_DAYS` (default 90) days without any request, and every
request starts the count again. A refused token gets `401 UNAUTHENTICATED`, and
the app returns to the phone screen. `customer-tokens:prune-idle` deletes those
tokens every night. Merchant and admin sessions belong to Clerk and are not
affected.

## Push notifications

Every notification goes to the in-app inbox at once and is pushed through
Firebase Cloud Messaging to all the account's devices from the queue. FCM
reaches iOS through APNs on its own, so both apps on both platforms share one
path. Tokens FCM reports as unregistered are deleted.

1. Firebase → Project settings → **Service accounts** → generate a private key.
2. Save it as `secrets/firebase-credentials.json` (the folder is never committed
   or copied into the Docker image) and set
   `FIREBASE_CREDENTIALS=secrets/firebase-credentials.json` in `.env`.
3. Keep a queue worker running: `composer dev`, `php artisan queue:work`, or the
   Docker `worker` service.
4. Check a phone end to end: `php artisan push:test <fcm-token>`.

Without credentials nothing is pushed and the inbox still works.

| Type | To | When |
| ---- | -- | ---- |
| `stamp_added`, `card_completed`, `reward_redeemed` | Customer | Stamp and reward events |
| `campaign` | Customer | A shop's campaign, unless the customer muted that shop or all offers |
| `birthday_greeting` | Customer | A shop's greeting, even when offers are muted |
| `birthdays_today` | Merchant | 09:00 Damascus, when customers have their birthday |
| `trial_ending`, `subscription_ending`, `grace_started`, `subscription_expired` | Merchant | `subscriptions:sync`, hourly |
| `payment_approved`, `payment_rejected` | Merchant | The payments reviewer's decision |

## Media storage

Shop logos and payment proofs go to **Cloudinary** in production and are served
from its CDN; `logo_url` and `proof_url` are full CDN links. The app talks to
Cloudinary's REST API through a small Storage driver
(`app/Support/Cloudinary/CloudinaryAdapter.php`), so the code only ever calls
`Storage`.

```
MEDIA_DISK=cloudinary        # shop logos
PROOFS_DISK=cloudinary       # payment proofs
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
```

Locally both default to local disks (`public` and `local`), and the tests fake
Cloudinary's API. `CLOUDINARY_URL` holds the API secret: keep it in `.env` only.
File names are random 40-character hashes, so a link cannot be guessed, but
anyone holding a proof's link can open it.

## Endpoints

The customer and merchant endpoints follow Deep Code's **API contract**
(`1.0.0-draft.1`) path for path, with its response shapes and its single error
shape:

```json
{ "error": { "code": "STAMP_INTERVAL", "message": "…", "details": { "minutes_remaining": 40 } } }
```

[api.md](api.md) documents every live endpoint — inputs, a real response and its
errors — plus QR generation for the customer app and the axios setup of each
app.
[openapi.yaml](openapi.yaml) describes the same endpoints as OpenAPI 3.1, for Swagger,
Postman or generating TypeScript types.

| Area | Endpoints |
| ---- | --------- |
| Customer | sign-in, config, account, profile, policy consent, QR secret, my cards, shop directory and page, shops list with contact numbers, muting a shop, notifications, devices |
| Merchant | me, lookups, registration, PIN unlock/change/reset, cards, scan → stamp → reward, statistics, customers, shop profile and logo, campaigns, birthday greetings, subscription and payments, notifications, devices |
| Admin | dashboard accounts, shops (list, page, name/type fix, suspend and reactivate, card takedown), customers (search by number, page, cycle and stamps, reveal number, birthdate fix), cancelling a stamp, business types and icons, packages and prices, billing and app settings, payment review, trial extension, audit log |
| Server | Clerk webhook |

`php artisan route:list --path=api` prints them all.

## Subscriptions and payments

A shop moves `TRIAL → ACTIVE → GRACE → EXPIRED` by its dates; `subscriptions:sync` applies them every hour
and sends the reminders. The merchant transfers outside the app and uploads the proof; the price and exchange
rate are copied into the payment at that moment. Only the **payments reviewer** approves or rejects; only the
**Super Admin** sets packages, prices and billing settings and extends trials — nobody holds both
(requirements §5.1). Every decision is in `audit_logs`.

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
| `api`        | every `/api/*` route          | 60 per minute, per bearer token (or IP without one) |
| `otp`        | `customer/auth/otp`           | 5 per hour per phone, 20 per hour per IP |
| `otp-verify` | `customer/auth/verify`        | 10 per minute per phone, 30 per IP     |

A new OTP for the same phone also requires a 60-second wait. Five wrong PINs in a
row lock a shop's protected tabs for 15 minutes (`PIN_LOCKED`).

## Layout

```
app/Enums/                     Status and type values used across the schema
app/Http/Controllers/Api/V1/   API controllers (Customer, Merchant, Admin)
app/Http/Middleware/           JSON responses, Clerk session, role and app-version checks
app/Http/Requests/Api/V1/      Validation (form requests)
app/Http/Resources/            JSON output shaping
app/Exceptions/                The contract's error shape (ApiException, ApiErrorRenderer)
app/Notifications/             Inbox notifications and their FCM push channel
app/Console/Commands/          push:test, birthday reminders, idle-token cleanup
app/Services/Otp/              OTP issuing, verification and delivery drivers
app/Services/Clerk/            Clerk session token verification
app/Services/Customer/         QR codes (TOTP), my cards, account deletion
app/Services/Merchant/         Subscription state, PIN unlock and lockout
app/Services/Billing/          Subscription periods, lifecycle, payment review
app/Services/Stamping/         Scan preview, stamps, rewards and their rules
app/Support/                   Phone numbers, Base32, cursor pagination
routes/api.php                 Versioned API routes
tests/Feature/Api/V1/          Endpoint tests
```

## Notes

- No `package.json`, no Vite — this project builds no frontend assets.
- Sessions use the `array` driver: the API is stateless.
- Models run in strict mode outside production, so lazy loading and silently
  discarded attributes fail loudly during development.
