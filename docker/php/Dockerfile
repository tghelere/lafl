FROM php:8.5-cli-alpine

RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libpng-dev \
        libwebp-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        mbstring \
        bcmath \
        exif \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

# `php artisan serve` foi descartado de propósito: o comando spawna um subprocesso PHP para
# o servidor embutido que NÃO herda o ambiente do container (só repassa APP_ENV e PATH),
# então DB_HOST/REDIS_HOST/MAIL_HOST injetados via `environment:` do compose somem e a app
# cai de volta em 127.0.0.1 sem avisar. `php -S` direto roda como PID 1 e herda o ambiente
# real do container.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
