#!/bin/bash
set -e

# Create nginx log directories
mkdir -p /var/log/nginx
touch /var/log/nginx/access.log
touch /var/log/nginx/error.log

# Run migrations
php artisan migrate --force

# Generate nginx config from template, defaulting PORT to 8080
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Start php-fpm in background
php-fpm &

# Start nginx in foreground
nginx -g 'daemon off;'