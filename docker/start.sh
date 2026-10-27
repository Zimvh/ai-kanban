#!/bin/sh
# Runs each time the container starts: cache config for the real env vars, update the database, start Apache.
set -e

php artisan package:discover --ansi
php artisan optimize
php artisan migrate --force
chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
