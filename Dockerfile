FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress
RUN npm ci && npm run build

EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=8000
