#!/bin/bash
set -e

echo "=== BaseStream Railway Startup ==="

# Wait for database to be available (Railway provisions services async)
echo "[1/5] Waiting for database..."
MAX_TRIES=30
COUNT=0
until php artisan db:monitor --databases=pgsql 2>/dev/null || [ $COUNT -eq $MAX_TRIES ]; do
    echo "  Waiting for database connection... ($COUNT/$MAX_TRIES)"
    sleep 2
    COUNT=$((COUNT + 1))
done

# Run migrations
echo "[2/5] Running migrations..."
php artisan migrate --force --no-interaction 2>&1 || echo "  Warning: migrations failed (database may not be ready yet)"

# Cache configuration
echo "[3/5] Caching configuration..."
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true
php artisan view:cache 2>&1 || true

# Ensure storage directories have correct permissions
echo "[4/5] Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Start supervisord (nginx + php-fpm + horizon)
echo "[5/5] Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord-railway.conf
