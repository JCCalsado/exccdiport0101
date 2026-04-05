
#!/bin/sh
php artisan migrate --force
php-fpm -D
sleep 2
nginx -g "daemon off;"