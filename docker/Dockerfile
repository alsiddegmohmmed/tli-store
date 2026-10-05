FROM node:22-bookworm-slim AS node
FROM composer:2 AS composer
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-install bcmath pdo_pgsql mbstring intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
