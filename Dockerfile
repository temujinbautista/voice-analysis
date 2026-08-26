FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        ffmpeg \
        git \
        unzip \
        curl \
        gnupg \
        libzip-dev \
        libsqlite3-dev \
        libonig-dev \
        libxml2-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-install pdo pdo_sqlite mbstring xml bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# The base image's default upload_max_filesize (2M) is far below what the app
# validates against (500MB batch archives) — PHP itself rejects the upload
# before Laravel ever sees a valid file otherwise.
RUN { \
        echo 'upload_max_filesize = 512M'; \
        echo 'post_max_size = 520M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 300'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# .env is excluded from the build context, so VITE_APP_NAME (baked into the
# JS bundle at build time, not read at runtime) has to be set explicitly here
# — otherwise app.ts falls back to its hardcoded 'Laravel' default.
ENV VITE_APP_NAME="Technical Trial Project"

# Ziggy's JS package resolves via a relative path into vendor/, so composer
# install has to run before npm run build, not in a separate build stage.
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs database \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
