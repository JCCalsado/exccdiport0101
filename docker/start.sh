#!/bin/sh

echo "Running Laravel setup..."

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g "daemon off;"