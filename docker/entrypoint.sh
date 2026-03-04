#!/bin/bash
set -e

echo "🚀 BaseStream - Starting..."

# Copy env if not exists
if [ ! -f .env ]; then
    cp .env.example .env
    echo "📋 .env criado a partir do .env.example"
fi

# Generate key if needed
if grep -q "APP_KEY=$" .env 2>/dev/null || grep -q "APP_KEY=\s*$" .env 2>/dev/null; then
    php artisan key:generate --force
    echo "🔑 APP_KEY gerado"
fi

# Wait for postgres
echo "⏳ Aguardando PostgreSQL..."
until pg_isready -h postgres -p 5432 -U postgres 2>/dev/null; do
    sleep 1
done
echo "✅ PostgreSQL disponível"

# Wait for redis
echo "⏳ Aguardando Redis..."
until redis-cli -h redis ping 2>/dev/null; do
    sleep 1
done
echo "✅ Redis disponível"

# Run migrations
php artisan migrate --force
echo "📦 Migrations executadas"

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "⚡ Cache otimizado"

echo "✅ BaseStream pronto!"

# Start supervisor (PHP-FPM + Horizon)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
