#!/bin/sh
set -e

cd /var/www/html

# `storage` is a named volume: recreate Laravel's folders if the volume is new
# and let PHP-FPM (www-data) write to them.
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# The application key is written to .env (not exported) so every PHP process
# sees it, including `docker compose exec app php artisan …`.
#
# A server keeps it as APP_KEY in its own .env, which compose passes in as
# APP_KEY_FROM_ENV, so losing the storage volume never changes it. Without
# one (a developer machine), a key is generated once into the storage volume:
# only PHP-FPM creates it, and the queue worker and the scheduler wait for it,
# so all containers share one key.
if [ -n "${APP_KEY_FROM_ENV:-}" ]; then
    printf 'APP_KEY=%s\n' "$APP_KEY_FROM_ENV" > .env
else
    KEY_FILE=storage/app/.docker-app-key
    if [ "${APP_ENV:-production}" != "local" ]; then
        echo "WARNING: APP_KEY is not set in the server's .env; using the key in the storage volume ($KEY_FILE). Copy it into .env." >&2
    fi
    if [ "$1" = "php-fpm" ] && [ ! -s "$KEY_FILE" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"
    fi
    waited=0
    until [ -s "$KEY_FILE" ]; do
        if [ "$waited" -ge 60 ]; then
            echo "No application key yet; start the app service first." >&2
            exit 1
        fi
        sleep 2
        waited=$((waited + 2))
    done
    printf 'APP_KEY=%s\n' "$(cat "$KEY_FILE")" > .env
fi
unset APP_KEY_FROM_ENV

# Only the long-running PHP-FPM process prepares the database; one-off
# commands started with `docker compose run app …` skip it.
if [ "$1" = "php-fpm" ]; then
    attempts=0
    until php artisan migrate --force --no-interaction; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 20 ]; then
            echo "The database is not reachable; giving up." >&2
            exit 1
        fi
        echo "Waiting for the database..." >&2
        sleep 3
    done

    # Seed packages, settings and demo data on the very first start only —
    # never in production, where seeding is a deliberate manual step
    # (`php artisan db:seed --force`, which leaves the demo data out).
    # `docker compose down -v` removes the volumes and seeds again next time.
    SEED_MARKER=storage/app/.docker-seeded
    if [ "${APP_ENV:-local}" != "production" ] && [ ! -f "$SEED_MARKER" ]; then
        php artisan db:seed --force --no-interaction
        touch "$SEED_MARKER"
    fi
fi

exec "$@"
