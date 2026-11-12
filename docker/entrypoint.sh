#!/usr/bin/env bash
set -e

PORT="${PORT:-8000}"

# Bind Apache to Koyeb's injected $PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Clear stale config cache so runtime env vars are always used
php /var/www/html/artisan config:clear || true
php /var/www/html/artisan route:cache || true

# Ensure storage permissions at runtime AFTER artisan commands
mkdir -p /var/www/html/storage/framework/cache/data /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run Laravel scheduler as www-data so cache/log files remain writable by Apache
su -s /bin/bash www-data -c "php /var/www/html/artisan schedule:work >> /var/www/html/storage/logs/scheduler.log 2>&1" &

exec apache2-foreground

