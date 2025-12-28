FROM php:8.4-fpm-alpine AS base

# Install dependencies
RUN apk add --no-cache \
    sqlite-dev \
    curl \
    fcgi \
    && docker-php-ext-install pdo pdo_sqlite opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && apk del .build-deps

# Configure PHP-FPM for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure OPcache for production
RUN echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.memory_consumption=256" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.interned_strings_buffer=16" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.max_accelerated_files=20000" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.save_comments=1" >> "$PHP_INI_DIR/conf.d/opcache.ini"

# Configure PHP-FPM pool
RUN sed -i 's/pm = dynamic/pm = static/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/pm.max_children = 5/pm.max_children = 50/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/;pm.max_requests = 500/pm.max_requests = 1000/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

# Composer stage
FROM base AS composer-build
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY services/inventory/composer.json services/inventory/composer.lock ./
COPY clients/ ../clients/
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY services/inventory/ .
RUN composer dump-autoload --optimize --classmap-authoritative

# Final PHP-FPM image
FROM base AS php-fpm

COPY --from=composer-build /app /app
COPY --from=composer-build /clients /clients

RUN mkdir -p var && chown -R www-data:www-data var

# Add php-fpm-healthcheck script
RUN set -xe && \
    echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck && \
    echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong || exit 1' >> /usr/local/bin/php-fpm-healthcheck && \
    chmod +x /usr/local/bin/php-fpm-healthcheck

# Enable php-fpm ping/status pages
RUN echo 'pm.status_path = /status' >> /usr/local/etc/php-fpm.d/www.conf && \
    echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/www.conf && \
    echo 'ping.response = pong' >> /usr/local/etc/php-fpm.d/www.conf

# Entrypoint script for database initialization
COPY services/inventory/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
