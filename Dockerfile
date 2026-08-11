FROM php:8.4-fpm-alpine

# Layer 1: OS packages (rarely changes)
RUN apk add --no-cache nginx postgresql-dev supervisor curl icu-dev libzip-dev libpng-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip gd

WORKDIR /var/www

# Layer 2: Composer deps (only rebuilds when composer.json/lock change)
COPY composer.json composer.lock /var/www/
COPY plugins/ /var/www/plugins/
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction --no-progress

# Inject plugin autoload (Docker COPY breaks path-repo symlinks)
COPY docker/inject-autoload.php /tmp/inject-autoload.php
RUN php /tmp/inject-autoload.php

# Layer 3: App source + compiled frontend (changes every deploy)
COPY . /var/www
RUN rm -rf node_modules

# Layer 4: Runtime config
RUN mkdir -p /var/www/storage/logs /var/www/storage/framework/{cache,sessions,views} /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && mkdir -p /etc/nginx/http.d

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

EXPOSE 8080
CMD ["/usr/local/bin/startup.sh"]
