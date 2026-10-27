# SAEP Platform — Operación y despliegue

> Actualizado: 27-10-2026
> Repositorio: `brayanmachero/Saep-App` · Rama de producción: **`ploi-main`**
> Producción: `https://saep.bmachero.com` (Ploi · servidor `saep-server`)

La plataforma ya no se publica en Azure App Service. El workflow de GitHub Actions,
`startup.sh`, `nginx.conf` y el almacenamiento en Azure Blob fueron retirados de
esta rama; la infraestructura vigente es la descrita aquí.

---

## 1. Arquitectura

```
Usuarios ── HTTPS ──► Ploi · saep-server (Ubuntu, Nginx + PHP-FPM 8.2)
                         │
                         ├─ Laravel 12 (/home/ploi/saep.bmachero.com)
                         │    ├─ Web: rutas Blade + API (routes/api.php)
                         │    ├─ Cola: worker database (jobs de sincronización)
                         │    └─ Scheduler: php artisan schedule:run (cada minuto)
                         ├─ MySQL 8 (base local del servidor)
                         ├─ storage/app/public  (archivos públicos, enlace public/storage)
                         └─ Grafana OSS (opcional, grafana/install.sh)

Integraciones salientes: Kizeo Forms · Microsoft Graph (SharePoint, OneDrive,
calendario, correo) · Microsoft Entra ID (reservas de vehículos) · Google
(OAuth, Drive, Sheets) · Talana · Meta WhatsApp Cloud · Resend.
```

| Elemento        | Valor                                     |
|-----------------|-------------------------------------------|
| Panel           | ploi.io → Servers → `saep-server`         |
| Sitio           | `saep.bmachero.com`                        |
| IP              | `137.184.225.185`                          |
| Usuario sistema | `ploi`                                     |
| Ruta del sitio  | `/home/ploi/saep.bmachero.com`             |
| PHP             | 8.2 (`composer.json` exige `^8.2`)         |
| Base de datos   | MySQL (credenciales solo en el `.env` del servidor) |

---

## 2. Flujo de despliegue

1. El trabajo se desarrolla en ramas `codex/*` o `claude/*` creadas desde `ploi-main`.
2. Se integra en `ploi-main` y se hace push a `origin`.
3. Ploi **Quick deploy** recibe el webhook de GitHub y ejecuta el script de
   despliegue del sitio (Repository → Branch `ploi-main`).

La rama `main` corresponde a la línea antigua de Azure y **no se publica**.
No integrar `main` en `ploi-main`.

### Script de despliegue de referencia

El script real vive en Ploi (sitio → *Deploy script*). Debe contener, como mínimo:

```bash
cd /home/ploi/saep.bmachero.com
git pull origin ploi-main

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan optimize          # config, rutas, eventos y vistas
php artisan storage:link || true
php artisan queue:restart

echo "" | sudo -S service php8.2-fpm reload
```

`queue:restart` es obligatorio: sin él, el worker sigue ejecutando el código anterior.

### Procesos que deben existir en Ploi

| Menú del sitio/servidor | Configuración                                                        |
|-------------------------|----------------------------------------------------------------------|
| **Cronjobs**            | `php /home/ploi/saep.bmachero.com/artisan schedule:run` · cada minuto · usuario `ploi` |
| **Queue**               | conexión `database`, cola `default`, `--tries=3`, 1 proceso como mínimo |
| **SSL**                 | Let's Encrypt para `saep.bmachero.com`                               |

Sin el cronjob no corren las sincronizaciones ni los reportes. Sin el worker no se
procesan `DashboardSyncJob`, `TalanaSyncJob` ni `PrepararActaReservaVehiculoKizeo`.

### Verificación después de publicar

```bash
cd /home/ploi/saep.bmachero.com
git log -1 --oneline            # debe coincidir con origin/ploi-main
php artisan migrate:status | tail
php artisan schedule:list
php artisan queue:failed
tail -n 100 storage/logs/laravel.log
curl -fsS https://saep.bmachero.com/up
```

---

## 3. Variables de entorno

El `.env` de producción se edita en Ploi (sitio → *Environment*). La referencia
completa de claves está en [`.env.example`](../.env.example). Bloques principales:

| Bloque              | Claves                                                           |
|---------------------|------------------------------------------------------------------|
| Aplicación          | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://saep.bmachero.com`, `APP_LOCALE=es` |
| Base de datos       | `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Sesión/caché/cola   | `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` = `database` |
| Archivos            | `FILESYSTEM_DISK` (los archivos públicos usan el disco `public` → `storage/app/public`, servido vía `public/storage`) |
| Correo              | `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_*`              |
| Kizeo               | `KIZEO_*` (token, webhook, IDs de formularios, exportaciones y listas) |
| Microsoft Graph     | `MSGRAPH_*`, `CONTRATACION_SHAREPOINT_*`                          |
| Entra ID (reservas) | `VEHICLE_RESERVATION_MICROSOFT_*`, `VEHICLE_RESERVATION_CALENDAR_*` |
| Google              | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, credenciales de Drive |
| Talana              | `TALANA_*`                                                         |
| APIs propias        | `WALLMAR_PENON_*`, `RECRUITMENT_CATALOG_API_TOKEN`                 |
| WhatsApp            | `WHATSAPP_*` (`WHATSAPP_ENABLED=false` hasta validar con Meta)     |

Los secretos no se guardan en el repositorio. `google-credentials.json`, `.env`
y `env` están en `.gitignore`.

> Nota: la columna `users.azure_oid` guarda el identificador de Microsoft Entra ID
> (SSO corporativo) y no se relaciona con el antiguo hosting en Azure.

---

## 4. Tareas programadas

Definidas en [`routes/console.php`](../routes/console.php). Para ver el estado real:
`php artisan schedule:list`.

| Frecuencia                     | Comando                                                        |
|--------------------------------|----------------------------------------------------------------|
| Cada 15 min                    | `vehiculos:procesar-reservas`                                   |
| Cada 30 min                    | `kizeo:sync-observaciones-ccu`, `kizeo:sync-inspecciones-preventivas`, `kizeo:sync-entregas-bodega`, `inventario:conciliar-cambios-kizeo`, `kizeo:sync-catalogo-inventario` |
| Cada hora                      | `stop:sync-sheets --force`                                      |
| Cada 2 h (min 10)              | `inventario:sync-centros-kizeo`                                 |
| Cada 4 h / 6 h                 | `kizeo:cache-warm` / `kizeo:sync-charla-tracking`               |
| Diario                         | Talana (05:20, 06:00, 06:30, 12:35, 19:35), Kizeo personal (06:20, 13:00, 20:00), `kanban:tareas-recurrentes` 07:00, `sst:enviar-recordatorios` 08:00, `kanban:alertas-vencimiento` 08:15, `contratacion:cierre-diario` 17:00 |
| Lun–sáb                        | `talana:reporte-asistencia` 08:15 y 08:20 (Peñón)               |
| Semanal (lunes)                | `kizeo:charla-weekly-report` 08:00, `stop:weekly-report --frecuencia=semanal` 08:30 |
| Mensual (día 1)                | `stop:weekly-report --frecuencia=mensual` 09:00                 |

Zona horaria de la aplicación: `America/Santiago`.

---

## 5. Desarrollo local

```bash
git switch ploi-main
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev        # servidor, cola, logs (pail) y Vite
```

Pruebas: `php artisan test` (usa SQLite en memoria según `phpunit.xml`).

---

## 6. Otros componentes

- **Grafana** (`grafana/`): instalación opcional en el mismo servidor para la
  analítica de Talana; lee las credenciales MySQL del `.env` del sitio.
- **Herramientas comerciales** (`ops/comercial/`): scripts Python para preparar
  las matrices de tarifas. Ver [`COMERCIAL_SABANA_TARIFAS.md`](COMERCIAL_SABANA_TARIFAS.md).
- **Protección de datos**: [`proteccion-datos-auditoria.md`](proteccion-datos-auditoria.md)
  y [`auditoria-interna-plataforma.md`](auditoria-interna-plataforma.md).
