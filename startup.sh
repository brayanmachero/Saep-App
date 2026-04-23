#!/bin/bash

echo "=== SAEP Platform Startup Script ==="
# nginx.conf en la raiz del repo es copiado automaticamente por Azure App Service
# a /etc/nginx/sites-available/default antes de arrancar nginx.

cd /home/site/wwwroot

php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

echo "=== Startup completado ==="
