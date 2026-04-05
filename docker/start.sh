#!/bin/bash

# Run Laravel setup
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Start PHP-FPM in background
php-fpm &

# Start Nginx
nginx -g "daemon off;"