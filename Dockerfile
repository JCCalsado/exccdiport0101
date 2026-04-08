FROM dunglas/frankenphp:1-php8.2-alpine

# Install system dependencies & PHP extensions needed for Laravel
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    nodejs \
    npm \
    curl

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    bcmath \
    gd \
    zip \
    exif \
    pcntl \
    intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files (node_modules and vendor excluded via .dockerignore)
COPY . .

# Install PHP dependencies (production optimized)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Install JS dependencies and build assets (Vite)
RUN npm ci && npm run build

# Remove node_modules after build — not needed at runtime
RUN rm -rf node_modules

# Create storage directories and set permissions
RUN mkdir -p storage/app/public storage/logs storage/framework/cache \
    storage/framework/sessions storage/framework/views bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copy and make entrypoint executable
COPY docker-entrypoint.sh /app/docker-entrypoint.sh
RUN chmod +x /app/docker-entrypoint.sh

# Environment defaults
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=8080
# CRITICAL: Tell Octane to use FrankenPHP (matches the base image)
ENV OCTANE_SERVER=frankenphp

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:${PORT}/up || exit 1

EXPOSE 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]