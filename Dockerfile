# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# app — PHP-FPM with the application and its Composer dependencies.
#
# Dev dependencies are installed on purpose: this image is for local
# development, demo data is generated with Faker, and the test suite runs
# inside it.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so editing application code does not reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-interaction --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

# Strip Windows line endings in case the file was checked out with CRLF.
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# web — nginx serving public/ and handing PHP requests to the app container.
# ---------------------------------------------------------------------------
FROM nginx:stable-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
