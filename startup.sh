#!/bin/bash

echo "=== SAEP Platform Startup Script ==="

# Escribir config nginx completa para Laravel (document root = public/)
NGINX_CONF=""
for f in /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf; do
    if [ -f "$f" ]; then
        NGINX_CONF="$f"
        break
    fi
done

if [ -n "$NGINX_CONF" ]; then
    echo "Configurando nginx en: $NGINX_CONF"
    # Obtener el puerto que usa el config original
    PORT=$(grep -oP '(?<=listen )\d+' "$NGINX_CONF" | head -1)
    PORT=${PORT:-80}
    cat > "$NGINX_CONF" << NGINXEOF
server {
    listen ${PORT};
    listen [::]:${PORT};
    root /home/site/wwwroot/public;
    index index.php index.html;
    server_name _;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINXEOF
    nginx -s reload 2>/dev/null || service nginx reload 2>/dev/null || true
    echo "nginx reconfigurado (puerto $PORT) -> public/"
else
    echo "WARN: no se encontro config nginx"
fi

cd /home/site/wwwroot

php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

echo "=== Startup completado ==="
