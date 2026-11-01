# Image de production : FrankenPHP (Caddy + PHP) => HTTPS automatique, un seul conteneur web
FROM dunglas/frankenphp:1-php8.4 AS app
RUN install-php-extensions pdo_pgsql intl opcache bcmath zip gd
ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock symfony.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && mkdir -p var/cache var/log && chown -R www-data:www-data var
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-prod.ini
