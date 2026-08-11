#!/bin/sh
# Cloud Run startup — run migrations + clear caches on cold start.
# Plugin routes are registered dynamically at boot; we must not use a
# pre-cached route list. Ziggy reads the current route list on first
# request, so clearing the cache here ensures plugin routes are included.

cd /var/www

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
