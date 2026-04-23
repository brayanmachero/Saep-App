#!/bin/bash

echo "=== SAEP Platform Startup Script ==="

# Configurar nginx para servir desde public/ (Laravel)
NGINX_CONF="/etc/nginx/sites-available/default"
if [ -f "$NGINX_CONF" ]; then
    sed -i 's|root /home/site/wwwroot;|root /home/site/wwwroot/public;|g' "$NGINX_CONF"
    nginx -s reload 2>/dev/null || service nginx reload 2>/dev/null || true
    echo "nginx reconfigurado -> public/"
fi

cd /home/site/wwwroot

# Crear enlace de almacenamiento público si no existe
php artisan storage:link --force 2>/dev/null || true

# Optimizar Laravel con las variables de entorno de App Service
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones pendientes
php artisan migrate --force

echo "=== Startup completado ==="
