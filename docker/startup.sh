#!/bin/sh
# Cloud Run startup — rebuild autoload + clear caches on cold start.
# Plugin routes are registered dynamically at boot via PluginManager.

cd /var/www

composer dump-autoload --optimize --no-interaction
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
