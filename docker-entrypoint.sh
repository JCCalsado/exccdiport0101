#!/bin/bash
set -e

echo "🚀 Starting CCDI Account Portal..."

# Wait for database to be ready (with timeout)
echo "⏳ Waiting for database connection..."
for i in {1..30}; do
    if php artisan tinker --execute="echo('✓ Database connected');" 2>/dev/null; then
        echo "✓ Database is ready"
        break
    fi
    echo "  Attempt $i/30..."
    sleep 2
done

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force || {
    echo "⚠️  Migrations failed (may already be run)"
}

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link || {
    echo "⚠️  Storage link already exists or failed (harmless)"
}

# Ensure permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "✓ Setup complete! Starting Octane server on port $PORT..."
echo ""

# Start Octane
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=${PORT:-8080}
