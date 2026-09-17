#!/bin/sh
set -e

cd /var/www/html

# `storage` is a named volume: recreate Laravel's folders if the volume is new
# and let PHP-FPM (www-data) write to them.
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Generate the application key once and keep it in the storage volume, so it
# survives container rebuilds. It is written to .env (not exported) so every
# PHP process sees it, including `docker compose exec app php artisan …`.
KEY_FILE=storage/app/.docker-app-key
if [ ! -s "$KEY_FILE" ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"
fi
printf 'APP_KEY=%s\n' "$(cat "$KEY_FILE")" > .env

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

    # Seed packages, settings and demo data on the very first start only.
    # `docker compose down -v` removes the volumes and seeds again next time.
    SEED_MARKER=storage/app/.docker-seeded
    if [ ! -f "$SEED_MARKER" ]; then
        php artisan db:seed --force --no-interaction
        touch "$SEED_MARKER"
    fi
fi

exec "$@"
