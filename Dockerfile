FROM php:8.3-fpm-alpine AS base
RUN apk add --no-cache icu-libs libzip oniguruma sqlite-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev sqlite-dev linux-headers \
    && docker-php-ext-install pdo_mysql pdo_sqlite intl mbstring zip opcache pcntl \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && apk del .build-deps
WORKDIR /var/www/html
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

FROM base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

FROM base AS testing
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --prefer-dist --no-interaction --no-progress
CMD ["php", "vendor/bin/phpunit"]

FROM base AS production
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
