#!/bin/sh
set -e

# Siguraduhing may log directory at tama ang permission
mkdir -p /var/log/nginx /var/run
chown -R nginx:nginx /var/log/nginx /var/run
chmod -R 755 /var/log/nginx /var/run

# I-redirect logs para siguradong hindi na maghanap ng physical file
ln -sf /dev/stdout /var/log/nginx/access.log 2>/dev/null || true
ln -sf /dev/stderr /var/log/nginx/error.log 2>/dev/null || true

# Start PHP-FPM (kung ginagamit niyo)
if command -v php-fpm >/dev/null 2>&1; then
    php-fpm -D
fi

# Start Nginx
nginx -g "daemon off;"