#!/bin/bash

echo "=== SAEP Platform Startup Script ==="
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
