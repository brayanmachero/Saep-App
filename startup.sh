#!/bin/bash

echo "=== SAEP Platform Startup Script ==="

# ─── Instalar Ghostscript (necesario para que Imagick convierta PDFs 1.5+) ───
echo "Instalando Ghostscript..."
apt-get install -y --no-install-recommends ghostscript 2>/dev/null \
    && echo "Ghostscript instalado OK" \
    || echo "WARN: ghostscript install failed (non-fatal)"

# ─── Permitir que ImageMagick procese PDFs (política de seguridad por defecto bloquea PDF) ───
for policyFile in /etc/ImageMagick-6/policy.xml /etc/ImageMagick-7/policy.xml; do
    if [ -f "$policyFile" ]; then
        sed -i 's/<policy domain="coder" rights="none" pattern="PDF"/<policy domain="coder" rights="read|write" pattern="PDF"/g' "$policyFile" \
            && echo "ImageMagick policy actualizada: $policyFile" \
            || echo "WARN: no se pudo actualizar policy en $policyFile"
    fi
done

# ─── Aumentar límites de upload en PHP-FPM ─────────────────────────────────
PHP_INI_FILE=$(php --ini 2>/dev/null | grep 'Loaded Configuration File' | awk '{print $NF}')
if [ -z "$PHP_INI_FILE" ] || [ "$PHP_INI_FILE" = "(none)" ]; then
    PHP_INI_FILE="/usr/local/etc/php/php.ini"
fi
PHP_INI_DIR=$(php --ini 2>/dev/null | grep 'Scan for additional' | awk '{print $NF}')
if [ -n "$PHP_INI_DIR" ] && [ -d "$PHP_INI_DIR" ]; then
    cat > "$PHP_INI_DIR/99-upload.ini" <<'EOF'
upload_max_filesize = 50M
post_max_size = 64M
EOF
    echo "PHP upload limits configurados en $PHP_INI_DIR/99-upload.ini"
else
    echo "WARN: no se encontró directorio de ini adicionales"
fi

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

# Restaurar google-credentials.json desde variable de entorno (Base64)
if [ -n "$GOOGLE_CREDENTIALS_BASE64" ]; then
    echo "$GOOGLE_CREDENTIALS_BASE64" | base64 -d > /home/site/wwwroot/google-credentials.json
    echo "google-credentials.json restaurado OK"
else
    echo "WARN: GOOGLE_CREDENTIALS_BASE64 no definida, Google Drive no funcionará"
fi

php artisan storage:link --force 2>/dev/null || true
php artisan optimize:clear || true
php artisan view:clear || true
php artisan config:clear || true
php artisan route:clear || true
php artisan cache:clear || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --class=App\\Modules\\Comercial\\database\\seeders\\ComercialSeeder --force

echo "=== Startup completado ==="
