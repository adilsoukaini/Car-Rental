#!/bin/sh
# Cloud Run startup — rebuild autoload + clear caches on cold start.

cd /var/www

# Write a minimal .env so Laravel picks up the DB + Scout config even
# if the Cloud Run env vars aren't available to the config cache.
cat > /var/www/.env << ENDENV
APP_ENV=production
APP_DEBUG=true
APP_URL=$APP_URL
DB_CONNECTION=pgsql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD
SCOUT_DRIVER=database
LOG_CHANNEL=stderr
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
ENDENV

composer dump-autoload --optimize --no-interaction 2>&1
php artisan config:clear 2>&1
php artisan route:clear 2>&1
php artisan view:clear 2>&1
php artisan cache:clear 2>&1

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
