# T-01 / T-20: imagen del API (PHP 8.4 + extensión de PostgreSQL + Composer).
FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev unzip git \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Portadas de hasta 2 MB: el límite de PHP se deja por encima para que la regla la aplique el caso de uso (422).
RUN printf "upload_max_filesize=5M\npost_max_size=8M\n" > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app
COPY . .

ENV PHP_CLI_SERVER_WORKERS=4 \
    COMPOSER_PROCESS_TIMEOUT=2000

EXPOSE 8000

# Arranque: dependencias si faltan → migraciones → admin inicial desde el entorno → servidor.
CMD ["sh", "-c", "[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist; php artisan migrate --force && php artisan library:bootstrap-admin && php artisan serve --host=0.0.0.0 --port=8000 --no-reload"]
