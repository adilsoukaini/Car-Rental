#!/bin/sh
# Cloud Run startup — clear caches on cold start. Cloud Run injects
# env vars into the container; Laravel reads them via env() at runtime.
# Do NOT write a .env file — it would shadow Cloud Run's env vars.

cd /var/www

# Force HTTPS — Cloud Run terminates TLS at the load balancer, so PHP-FPM
# sees HTTP. Without this, Laravel generates http:// URLs and CSP blocks them.
export HTTPS=true

composer dump-autoload --optimize --no-interaction 2>&1
php artisan package:discover --ansi 2>&1
php artisan config:clear 2>&1
php artisan route:clear 2>&1
php artisan view:clear 2>&1
php artisan cache:clear 2>&1

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
