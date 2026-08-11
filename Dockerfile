FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    supervisor \
    curl \
    && docker-php-ext-install pdo pdo_pgsql

WORKDIR /var/www

COPY . /var/www

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress || true
RUN composer dump-autoload --optimize --no-interaction || true

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
