FROM dunglas/frankenphp:1-php8.2-alpine

# Install system dependencies & PHP extensions needed for Laravel
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    nodejs \
    npm

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

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install JS dependencies and build assets (Vite/Mix)
RUN npm install && npm run build

# Set permissions para hindi mag-error ang Laravel
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Set Environment Variables for Production
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=8080

# Expose the port
EXPOSE 8080