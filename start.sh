#!/bin/bash
set -e

# Create nginx log directories
mkdir -p /var/log/nginx
touch /var/log/nginx/access.log
touch /var/log/nginx/error.log

# Copy nginx config
cp /app/nginx.conf /etc/nginx/conf.d/default.conf

# Run migrations
php artisan migrate --force

# Start php-fpm in background
php-fpm &

# Start nginx in foreground
nginx -g 'daemon off;'