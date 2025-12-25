FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    unzip \
    libsqlite3-dev \
    curl \
    && docker-php-ext-install pdo pdo_sqlite \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

RUN mkdir -p var && chmod -R 777 var

EXPOSE 8003

CMD bash -c "php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || php bin/console doctrine:schema:create --no-interaction && php -S 0.0.0.0:8003 -t public"
