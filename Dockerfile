# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# app — PHP-FPM with the application and its Composer dependencies.
#
# Dev dependencies are installed on purpose: this image is for local
# development, demo data is generated with Faker, and the test suite runs
# inside it.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

# GD with JPEG and WebP crops and re-encodes the shop covers.
RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip opcache gd \
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
