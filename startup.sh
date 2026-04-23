#!/bin/bash

echo "=== SAEP Platform Startup Script ==="

# Copiar nginx.conf del repo al directorio de nginx y recargar
NGINX_CONF_SRC="/home/site/wwwroot/nginx.conf"
NGINX_CONF_DST="/etc/nginx/sites-available/default"

if [ -f "$NGINX_CONF_SRC" ]; then
    echo "Copiando nginx.conf -> $NGINX_CONF_DST"
    cp "$NGINX_CONF_SRC" "$NGINX_CONF_DST"
    nginx -t 2>&1 && nginx -s reload 2>&1 && echo "nginx recargado OK" || echo "WARN: nginx reload fallo"
else
    echo "WARN: nginx.conf no encontrado en $NGINX_CONF_SRC"
fi

cd /home/site/wwwroot

php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

echo "=== Startup completado ==="
