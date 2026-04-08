#!/bin/bash
# Intentionally no "set -e" — we handle errors explicitly so a transient
# database hiccup never prevents the container from starting.

# Ensure all output goes to stdout/stderr so Railway captures it.
exec 1>&1
exec 2>&2

echo "🚀 Starting CCDI Account Portal..."

# ---------------------------------------------------------------------------
# Database connectivity check
# Uses a raw PHP PDO snippet so we don't depend on artisan tinker, which
# spawns an interactive REPL and is unreliable in non-TTY Alpine containers.
# ---------------------------------------------------------------------------
DB_READY=false
echo "⏳ Waiting for database connection..."

for i in $(seq 1 30); do
    DB_CHECK_OUTPUT=$(php -r "
        \$host = getenv('DB_HOST') ?: '127.0.0.1';
        \$port = getenv('DB_PORT') ?: '3306';
        \$db   = getenv('DB_DATABASE') ?: 'forge';
        \$user = getenv('DB_USERNAME') ?: 'root';
        \$pass = getenv('DB_PASSWORD') ?: '';
        try {
            new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass,
                [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo 'ok';
        } catch (Exception \$e) {
            fwrite(STDERR, \$e->getMessage() . PHP_EOL);
            exit(1);
        }
    " 2>&1)

    if [ "$DB_CHECK_OUTPUT" = "ok" ]; then
        echo "✓ Database is ready (attempt $i)"
        DB_READY=true
        break
    else
        echo "  Attempt $i/30 — database not ready: $DB_CHECK_OUTPUT"
        sleep 2
    fi
done

if [ "$DB_READY" = false ]; then
    echo "⚠️  WARNING: Database did not become ready after 30 attempts." >&2
    echo "⚠️  Continuing startup — the application will handle connection errors at runtime." >&2
fi

# ---------------------------------------------------------------------------
# Migrations — non-fatal: log the error and carry on so the server starts.
# ---------------------------------------------------------------------------
echo "📦 Running migrations..."
if php artisan migrate --force 2>&1; then
    echo "✓ Migrations complete"
else
    echo "⚠️  ERROR: Migrations failed (exit code $?). Check logs above for details." >&2
    echo "⚠️  Continuing startup — the application may be degraded until migrations succeed." >&2
fi

# ---------------------------------------------------------------------------
# Storage link — safe to ignore if it already exists.
# ---------------------------------------------------------------------------
echo "🔗 Creating storage link..."
php artisan storage:link 2>&1 || {
    echo "⚠️  Storage link already exists or failed (harmless)"
}

# Ensure permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "✓ Setup complete! Starting Octane server on port ${PORT:-8080}..."
echo ""

# ---------------------------------------------------------------------------
# Start Octane — use exec so the process receives signals directly.
# ---------------------------------------------------------------------------
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=${PORT:-8080}
