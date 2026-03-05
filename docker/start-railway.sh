#!/bin/bash
# Railway startup — supervisord becomes PID 1 immediately via exec,
# so nginx is up within seconds. DB init runs in a background subshell.

echo "=== BaseStream Railway Startup ==="
echo "PORT=$PORT"

# Trim whitespace from PORT env var, default to 80
LISTEN_PORT=$(printf '%s' "${PORT:-80}" | tr -d '[:space:]')
echo "[init] Configuring nginx on port $LISTEN_PORT..."
sed -i "s/listen 80;/listen ${LISTEN_PORT};/g" /etc/nginx/conf.d/basestream.conf

# Create nginx cache directory required by proxy_cache_path
mkdir -p /tmp/nginx_cache
mkdir -p /var/log/nginx
touch /var/log/nginx/access.log /var/log/nginx/error.log

# Remove any conflicting default nginx configs that come with the Debian package
rm -f /etc/nginx/sites-enabled/default
rm -f /etc/nginx/conf.d/default.conf

# Disable all dynamic nginx modules (avoid potential load failures)
rm -f /etc/nginx/modules-enabled/*.conf 2>/dev/null || true

# Test nginx config — prints the exact error if something is wrong
echo "[init] Nginx conf files:"
ls -la /etc/nginx/conf.d/ 2>&1
ls -la /etc/nginx/modules-enabled/ 2>&1
echo "[init] Testing nginx config..."
nginx -t 2>&1

# Storage / bootstrap permissions
mkdir -p /var/www/html/storage/app/transcode \
         /var/www/html/storage/logs \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── DB init in background — does NOT block nginx startup ─────────────────────
(
    echo "[bg] Waiting for database..."
    MAX=20; N=0
    until php -r "try{new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'5432').';dbname='.getenv('DB_DATABASE'),getenv('DB_USERNAME'),getenv('DB_PASSWORD'));exit(0);}catch(Exception \$e){exit(1);}" 2>/dev/null; do
        N=$((N+1))
        [ "$N" -ge "$MAX" ] && { echo "[bg] DB timeout, continuing..."; break; }
        echo "[bg] DB not ready ($N/$MAX)..."
        sleep 3
    done

    echo "[bg] Running migrations..."
    php /var/www/html/artisan migrate --force --no-interaction 2>&1 || echo "[bg] WARNING: migrations failed"

    USER_COUNT=$(php -r "try{\$p=new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'5432').';dbname='.getenv('DB_DATABASE'),getenv('DB_USERNAME'),getenv('DB_PASSWORD'));echo \$p->query('SELECT COUNT(*) FROM users')->fetchColumn();}catch(Exception \$e){echo '0';}" 2>/dev/null)
    if [ "$USER_COUNT" = "0" ]; then
        echo "[bg] Seeding database..."
        php /var/www/html/artisan db:seed --class=ProductionSeeder --force --no-interaction 2>&1 || echo "[bg] WARNING: seeding failed"
    else
        echo "[bg] DB has $USER_COUNT users, skipping seed."
    fi

    echo "[bg] Caching config/routes/views..."
    php /var/www/html/artisan config:clear 2>&1 || true
    php /var/www/html/artisan route:clear  2>&1 || true
    php /var/www/html/artisan view:clear   2>&1 || true
    php /var/www/html/artisan config:cache 2>&1 || true
    php /var/www/html/artisan route:cache  2>&1 || true
    php /var/www/html/artisan view:cache   2>&1 || true
    echo "[bg] Init complete."
) &

# ── Exec supervisord as PID 1 — starts nginx + php-fpm + horizon immediately ─
echo "[init] Handing off to supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord-railway.conf
