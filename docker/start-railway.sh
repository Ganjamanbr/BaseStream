#!/bin/bash
set -e

echo "=== BaseStream Railway Startup ==="
echo "DEBUG: PORT=$PORT, RAILWAY_PORT=$RAILWAY_PORT"

# Configure nginx to listen on Railway's PORT (default: 80)
LISTEN_PORT=${PORT:-80}
echo "[0/5] Configuring nginx to listen on port $LISTEN_PORT..."
sed -i "s/listen 80;/listen $LISTEN_PORT;/" /etc/nginx/conf.d/basestream.conf

# Ensure storage directories have correct permissions FIRST
echo "[1/5] Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Start supervisord EARLY so nginx responds to healthcheck while DB initializes
echo "[2/5] Starting services (nginx + php-fpm)..."
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord-railway.conf &
SUPERVISOR_PID=$!

# Give nginx and php-fpm a moment to start
sleep 3

# Wait for database to be available (Railway provisions services async)
echo "[3/5] Waiting for database..."
MAX_TRIES=15
COUNT=0
until php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'OK'; exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    COUNT=$((COUNT + 1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "  Warning: Database not available after $MAX_TRIES attempts, continuing..."
        break
    fi
    echo "  Waiting for database connection... ($COUNT/$MAX_TRIES)"
    sleep 2
done

# Run migrations
echo "[4/5] Running migrations..."
php artisan migrate --force --no-interaction 2>&1 || echo "  Warning: migrations failed (database may not be ready yet)"

# Seed database (only if users table is empty)
USER_COUNT=$(php -r "try { \$pdo = new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '5432') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch(Exception \$e) { echo '0'; }" 2>/dev/null)
if [ "$USER_COUNT" = "0" ]; then
    echo "[4.5/5] Seeding database (first run)..."
    php artisan db:seed --class=ProductionSeeder --force --no-interaction 2>&1 || echo "  Warning: seeding failed"
else
    echo "[4.5/5] Database already has $USER_COUNT users, skipping seed."
fi

# Cache configuration (clear first to ensure fresh env vars)
echo "[5/5] Caching configuration..."
php artisan config:clear 2>&1 || true
php artisan route:clear 2>&1 || true
php artisan view:clear 2>&1 || true
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true
php artisan view:cache 2>&1 || true

echo "=== BaseStream Ready ==="

# Wait for supervisord to keep container running
wait $SUPERVISOR_PID
