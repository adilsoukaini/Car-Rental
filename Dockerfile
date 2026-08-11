FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    supervisor \
    curl \
    icu-dev \
    libzip-dev \
    libpng-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip gd

WORKDIR /var/www

COPY . /var/www

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction --no-progress 2>&1 || true
# Docker COPY resolves symlinks, so vendor/carrental/* → plugins/* symlinks
# become real directories. This script fixes the autoload to include plugin
# PSR-4 namespaces that Composer path-repos would normally handle.
COPY docker/inject-autoload.php /tmp/inject-autoload.php
RUN php /tmp/inject-autoload.php 2>&1
RUN php -r "require 'vendor/autoload.php'; echo class_exists('Plugins\\FleetManagement\\FleetManagementServiceProvider') ? 'OK: FleetManagement found' : 'FAIL: FleetManagement not found';" 2>&1

# Frontend is prebuilt locally (npm run build produces public/build/).
# The GitHub Actions CI pipeline builds assets before Docker build, so
# COPY . already includes compiled assets. No npm inside the container.
RUN rm -rf node_modules

RUN mkdir -p /var/www/storage/logs /var/www/storage/framework/cache /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www

RUN mkdir -p /etc/nginx/http.d
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

EXPOSE 8080

CMD ["/usr/local/bin/startup.sh"]
