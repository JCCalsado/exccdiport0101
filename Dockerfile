FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install and build frontend assets
RUN npm install && npm run build

# Set permissions for Laravel storage
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# ========== ANG MAHALAGANG PARTE ==========
# Gumawa ng log folder para kay Nginx na may tamang permission
RUN mkdir -p /var/log/nginx /var/run && \
    chown -R nginx:nginx /var/log/nginx /var/run && \
    chmod -R 755 /var/log/nginx /var/run && \
    ln -sf /dev/stdout /var/log/nginx/access.log && \
    ln -sf /dev/stderr /var/log/nginx/error.log

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy and prepare startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Expose port
EXPOSE 80

# Start the container
CMD ["/start.sh"]