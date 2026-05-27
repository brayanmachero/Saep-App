#!/usr/bin/env bash
# =============================================================================
#  grafana/install.sh  — Instalación de Grafana OSS en servidor Ploi (Ubuntu)
#  SAEP · Talana Analytics · Solo SUPER_ADMIN
#
#  Uso: bash /home/ploi/saep.bmachero.com/grafana/install.sh
#  Requiere: root o sudo, Ubuntu 20.04+
# =============================================================================
set -euo pipefail

APP_ROOT="/home/ploi/saep.bmachero.com"
ENV_FILE="${APP_ROOT}/.env"
GRAFANA_PROV="/etc/grafana/provisioning"
GRAFANA_INI="/etc/grafana/grafana.ini"

echo "=============================="
echo "  SAEP · Grafana Install      "
echo "=============================="

# ── 1. Leer credenciales MySQL desde .env ─────────────────────────────────────
if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: No se encontró ${ENV_FILE}"
    exit 1
fi

DB_USER=$(grep -E '^DB_USERNAME=' "$ENV_FILE" | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")
DB_PASS=$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")
DB_NAME=$(grep -E '^DB_DATABASE=' "$ENV_FILE" | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")

if [[ -z "$DB_USER" || -z "$DB_NAME" ]]; then
    echo "ERROR: No se pudo extraer DB_USERNAME o DB_DATABASE de .env"
    exit 1
fi

echo "[1/7] Credenciales MySQL: user=${DB_USER}, db=${DB_NAME}"

# ── 2. Instalar Grafana OSS ───────────────────────────────────────────────────
echo "[2/7] Instalando Grafana OSS..."

if ! command -v grafana-server &>/dev/null; then
    sudo apt-get update -q
    sudo apt-get install -y -q apt-transport-https software-properties-common wget

    wget -q -O /usr/share/keyrings/grafana.key \
        https://apt.grafana.com/gpg.key

    echo "deb [signed-by=/usr/share/keyrings/grafana.key] \
        https://apt.grafana.com stable main" \
        | sudo tee /etc/apt/sources.list.d/grafana.list > /dev/null

    sudo apt-get update -q
    sudo apt-get install -y -q grafana
    echo "   ✓ Grafana instalado"
else
    echo "   ✓ Grafana ya está instalado ($(grafana-server -v 2>&1 | head -1))"
fi

# ── 3. Configurar grafana.ini ─────────────────────────────────────────────────
echo "[3/7] Configurando grafana.ini..."

# Función auxiliar para setear una clave en grafana.ini
set_ini() {
    local section="$1" key="$2" val="$3"
    # Si existe la línea (comentada o no), reemplazarla; si no, agregarla bajo la sección
    if sudo grep -q "^\s*[;#]*\s*${key}\s*=" "$GRAFANA_INI"; then
        sudo sed -i "s|^\s*[;#]*\s*${key}\s*=.*|${key} = ${val}|" "$GRAFANA_INI"
    else
        # Agregar bajo la sección correspondiente
        sudo sed -i "/^\[${section}\]/a ${key} = ${val}" "$GRAFANA_INI"
    fi
}

# [server]
set_ini "server" "http_port" "3000"
set_ini "server" "root_url" "http://137.184.225.185:3000"
set_ini "server" "serve_from_sub_path" "false"

# [security]
set_ini "security" "allow_embedding" "true"
set_ini "security" "cookie_samesite" "none"
set_ini "security" "cookie_secure" "false"

# [auth.anonymous]  — permite que el iframe cargue sin segundo login
sudo sed -i '/^\[auth\.anonymous\]/,/^\[/ {
    s|^[;#]*\s*enabled\s*=.*|enabled = true|
    s|^[;#]*\s*org_role\s*=.*|org_role = Viewer|
}' "$GRAFANA_INI"

# Si no existía la sección [auth.anonymous], la agregamos al final
if ! sudo grep -q "^\[auth\.anonymous\]" "$GRAFANA_INI"; then
    printf "\n[auth.anonymous]\nenabled = true\norg_role = Viewer\n" \
        | sudo tee -a "$GRAFANA_INI" > /dev/null
fi

echo "   ✓ grafana.ini configurado"

# ── 4. Variables de entorno para provisioning (credenciales DB) ───────────────
echo "[4/7] Configurando variables de entorno de Grafana..."

GRAFANA_ENV_FILE="/etc/systemd/system/grafana-server.service.d/saep.conf"
sudo mkdir -p "$(dirname "$GRAFANA_ENV_FILE")"
sudo tee "$GRAFANA_ENV_FILE" > /dev/null <<EOF
[Service]
Environment="GRAFANA_DB_USER=${DB_USER}"
Environment="GRAFANA_DB_PASSWORD=${DB_PASS}"
EOF

echo "   ✓ /etc/systemd/system/grafana-server.service.d/saep.conf creado"

# ── 5. Copiar archivos de provisioning ───────────────────────────────────────
echo "[5/7] Copiando provisioning files..."

# Datasources
sudo cp "${APP_ROOT}/grafana/provisioning/datasources/mysql.yaml" \
        "${GRAFANA_PROV}/datasources/saep-mysql.yaml"

# Dashboards provider YAML
sudo cp "${APP_ROOT}/grafana/provisioning/dashboards/dashboard.yaml" \
        "${GRAFANA_PROV}/dashboards/saep-provider.yaml"

# Dashboard JSON
sudo cp "${APP_ROOT}/grafana/provisioning/dashboards/talana.json" \
        "${GRAFANA_PROV}/dashboards/talana.json"

# Permisos correctos
sudo chown -R grafana:grafana "${GRAFANA_PROV}"
sudo chmod 640 "${GRAFANA_PROV}/datasources/saep-mysql.yaml"

echo "   ✓ Archivos de provisioning copiados"

# ── 6. Habilitar e iniciar servicio ──────────────────────────────────────────
echo "[6/7] Habilitando servicio systemd..."

sudo systemctl daemon-reload
sudo systemctl enable grafana-server
sudo systemctl restart grafana-server

# Esperar a que arranque (máx 15s)
for i in {1..15}; do
    if sudo systemctl is-active --quiet grafana-server; then
        echo "   ✓ grafana-server activo"
        break
    fi
    sleep 1
done

if ! sudo systemctl is-active --quiet grafana-server; then
    echo "ERROR: grafana-server no inició. Verifica: sudo journalctl -u grafana-server -n 50"
    exit 1
fi

# ── 7. Actualizar .env de Laravel ────────────────────────────────────────────
echo "[7/7] Actualizando .env de Laravel..."

update_env() {
    local key="$1" val="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
    else
        echo "${key}=${val}" >> "$ENV_FILE"
    fi
}

update_env "GRAFANA_URL"           "http://137.184.225.185:3000"
update_env "GRAFANA_DASHBOARD_UID" "talana-saep"

echo "   ✓ .env actualizado"

# ── Limpiar caché de Laravel ─────────────────────────────────────────────────
if command -v php &>/dev/null; then
    cd "$APP_ROOT"
    php artisan config:clear
    echo "   ✓ config:clear ejecutado"
fi

echo ""
echo "============================================================"
echo "  ✓ Instalación completa"
echo "  Grafana: http://137.184.225.185:3000"
echo "  Dashboard UID: talana-saep"
echo ""
echo "  Siguiente paso — primer sync de datos:"
echo "    cd ${APP_ROOT} && php artisan talana:sync-db --meses=3"
echo "============================================================"
