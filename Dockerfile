FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx nodejs npm git curl \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean

RUN mkdir -p /var/log/nginx /var/run

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN npm install && npm run build

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

RUN echo 'events {}\n\
http {\n\
    include /etc/nginx/mime.types;\n\
    access_log /dev/stdout;\n\
    error_log /dev/stderr;\n\
    server {\n\
        listen 8080;\n\
        root /app/public;\n\
        index index.php;\n\
        location / {\n\
            try_files $uri $uri/ /index.php?$query_string;\n\
        }\n\
        location ~ \\.php$ {\n\
            fastcgi_pass 127.0.0.1:9000;\n\
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;\n\
            include fastcgi_params;\n\
        }\n\
    }\n\
}' > /etc/nginx/nginx.conf

EXPOSE 8080

CMD sh -c "php artisan migrate --force && php-fpm -D && sleep 2 && nginx -g 'daemon off;'"