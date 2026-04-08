#!/bin/bash

echo "[$(date)] 🚀 Starting CCDI Account Portal on port ${PORT:-8080}..."

# Simple wait for database
echo "[$(date)] ⏳ Checking database connection..."
for i in {1..30}; do
    if php -r "mysqli_connect(getenv('DB_HOST'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), getenv('DB_DATABASE'));" 2>/dev/null; then
        echo "[$(date)] ✓ Database connected"
        break
    fi
    echo "[$(date)] Attempt $i/30..."
    sleep 2
done

# Run migrations
echo "[$(date)] 📦 Running migrations..."
php artisan migrate --force 2>&1 || echo "[$(date)] ⚠️  Migrations skipped"

# Create storage link
echo "[$(date)] 🔗 Creating storage link..."
php artisan storage:link 2>&1 || echo "[$(date)] ⚠️  Storage link skipped"

# Fix permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "[$(date)] ✓ Setup complete!"
echo "[$(date)] 🌐 Starting Octane..."
echo ""

# Start Octane (replace this process)
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=${PORT:-8080}
