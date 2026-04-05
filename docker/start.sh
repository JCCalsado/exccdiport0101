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

# Wait for database to be available (retry up to 30 times, 1 second apart)
echo "Waiting for database connection..."
for i in $(seq 1 30); do
    if php artisan tinker --execute="exit;" 2>/dev/null; then
        echo "Database connection successful!"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "Database connection failed after 30 attempts"
        exit 1
    fi
    echo "Attempt $i/30 - retrying in 1 second..."
    sleep 1
done

# Run migrations
echo "Running database migrations..."
php artisan migrate --force 2>/dev/null || true

# Clear and cache config for production
echo "Optimizing Laravel for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisor (manages both php-fpm and nginx)
echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf