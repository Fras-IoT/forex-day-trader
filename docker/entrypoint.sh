#!/usr/bin/env bash
set -e

PORT="${PORT:-8000}"

# Bind Apache to Koyeb's injected $PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Ensure storage permissions at runtime
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Clear stale config cache so runtime env vars from Koyeb are always used
php /var/www/html/artisan config:clear || true
php /var/www/html/artisan route:cache || true

# Run Laravel scheduler in background for 5-minute auto-scan & SL/TP Telegram alerts
php /var/www/html/artisan schedule:work >> /var/www/html/storage/logs/scheduler.log 2>&1 &

exec apache2-foreground

