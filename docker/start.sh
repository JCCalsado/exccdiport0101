#!/bin/sh
echo "=== STEP 1: Migrate ==="
php artisan migrate --force
echo "=== STEP 2: Starting PHP-FPM ==="
php-fpm -D 2>&1
echo "PHP-FPM exit code: $?"
sleep 2
echo "=== STEP 3: Test nginx ==="
nginx -t 2>&1
echo "Nginx test exit code: $?"
echo "=== STEP 4: Starting Nginx ==="
nginx -g "daemon off;" 2>&1
echo "Nginx exit code: $?"
echo "=== DONE ==="