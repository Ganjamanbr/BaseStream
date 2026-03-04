FROM php:8.3-fpm

# System dependencies (including nginx for Railway single-container deploy)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    ffmpeg \
    supervisor \
    nginx \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite pgsql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Working directory
WORKDIR /var/www/html

# Copy project
COPY . .

# Ensure required directories exist (excluded by .dockerignore / .gitignore)
RUN mkdir -p bootstrap/cache \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    /var/log/supervisor

# Install dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config for Railway (single container: nginx + php-fpm on 127.0.0.1:9000)
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx-railway.conf /etc/nginx/conf.d/basestream.conf

# Supervisor configs
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/supervisord-railway.conf /etc/supervisor/conf.d/supervisord-railway.conf

# Startup script (ensure LF line endings for Linux)
COPY docker/start-railway.sh /usr/local/bin/start-railway.sh
RUN sed -i 's/\r$//' /usr/local/bin/start-railway.sh && chmod +x /usr/local/bin/start-railway.sh

# Railway uses port 80
EXPOSE 80

# Default: Railway startup (migrations + cache + supervisord with nginx)
CMD ["/usr/local/bin/start-railway.sh"]
