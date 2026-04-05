#!/bin/sh
set -e

# Ensure log directories exist with proper permissions
mkdir -p /var/log/nginx /var/run /var/log/supervisor
chown -R nginx:nginx /var/log/nginx /var/run
chown -R nobody:nobody /var/log/supervisor
chmod -R 755 /var/log/nginx /var/run /var/log/supervisor

# Redirect logs to stdout/stderr for container visibility
ln -sf /dev/stdout /var/log/nginx/access.log 2>/dev/null || true
ln -sf /dev/stderr /var/log/nginx/error.log 2>/dev/null || true

# Start supervisor (manages both php-fpm and nginx)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf