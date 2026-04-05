#!/bin/bash
set -e
set -x

# Default PORT to 8080 if Railway doesn't inject one
export PORT="${PORT:-8080}"

# Render nginx config template with the PORT variable
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Run database migrations
php artisan migrate --force

# Start php-fpm in the background
php-fpm &

# Start nginx in the foreground (keeps the container alive)
exec nginx -g 'daemon off;'
