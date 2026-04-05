#!/bin/bash
set -e
set -x

echo "Starting Container" >&2

# Create nginx log directories
echo "Creating nginx log directories..." >&2
mkdir -p /var/log/nginx
touch /var/log/nginx/access.log
touch /var/log/nginx/error.log

# Run migrations
echo "Running database migrations..." >&2
php artisan migrate --force

# Generate nginx config from template, defaulting PORT to 8080
export PORT="${PORT:-8080}"
echo "Rendering nginx config template for PORT=${PORT}..." >&2

TEMPLATE=/etc/nginx/nginx.conf.template
if [ ! -f "$TEMPLATE" ]; then
    echo "ERROR: nginx config template not found at $TEMPLATE" >&2
    exit 1
fi

envsubst '${PORT}' < "$TEMPLATE" > /etc/nginx/nginx.conf
echo "nginx.conf written successfully." >&2

# Start php-fpm in background, forwarding logs to stdout/stderr
echo "Starting php-fpm..." >&2
php-fpm 2>&1 &

# Start nginx in foreground, forwarding logs to stdout/stderr
echo "Starting nginx on port ${PORT}..." >&2
exec nginx -g 'daemon off;'