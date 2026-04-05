# Stage 1: Build stage
FROM php:8.2-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    libxml2-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    xml \
    curl \
    zip \
    gd \
    intl \
    bcmath \
    tokenizer \
    fileinfo \
    openssl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Build frontend assets
RUN npm ci && npm run build

# Stage 2: Runtime stage
FROM php:8.2-fpm-alpine

# Install runtime dependencies
RUN apk add --no-cache \
    nginx \
    curl \
    libpng \
    libjpeg-turbo \
    libxml2 \
    freetype \
    libzip \
    icu \
    oniguruma \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    xml \
    curl \
    zip \
    gd \
    intl \
    bcmath \
    tokenizer \
    fileinfo \
    openssl

# Create app directory
WORKDIR /app

# Copy application from builder
COPY --from=builder /app /app

# Create necessary directories
RUN mkdir -p /var/log/nginx && \
    touch /var/log/nginx/access.log && \
    touch /var/log/nginx/error.log && \
    chown -R nobody:nobody /var/log/nginx

# Create storage directories and set permissions
RUN mkdir -p storage/logs \
    storage/app \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache && \
    chown -R nobody:nobody /app/storage /app/bootstrap/cache

# Copy nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Copy php-fpm configuration
RUN mkdir -p /usr/local/etc/php-fpm.d && \
    echo '[www]\nuser = nobody\ngroup = nobody\nlisten = 127.0.0.1:9000\nchdir = /app' > /usr/local/etc/php-fpm.d/www.conf

# Copy supervisor configuration
RUN mkdir -p /etc/supervisor/conf.d && \
    echo '[supervisord]\nnodaemon=true\n\n[program:php-fpm]\ncommand=php-fpm\nnumprocs=1\nautostart=true\nautorestart=true\nstderr_logfile=/var/log/php-fpm.log\nstdout_logfile=/var/log/php-fpm.log\n\n[program:nginx]\ncommand=nginx -g "daemon off;"\nnumprocs=1\nautostart=true\nautorestart=true\nstderr_logfile=/var/log/nginx/error.log\nstdout_logfile=/var/log/nginx/access.log' > /etc/supervisor/conf.d/app.conf

# Cache Laravel config, routes, and views
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Start application
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
