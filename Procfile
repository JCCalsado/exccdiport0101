web: php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=$PORT
release: php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan storage:link
