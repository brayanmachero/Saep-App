# SAEP Platform — Documentación DevOps Completa

> **Versión:** Actualizado al 27 de abril de 2026  
> **Rama principal:** `main`  
> **Repositorio:** `brayanmachero/saep-platform`  
> **URL producción:** `https://app.saep.cl`

> **Documento relacionado:** [Módulo Comercial - Documentación Gerencial y Operativa](MODULO_COMERCIAL_DOCUMENTACION_GERENCIAL.md)

---

## Tabla de Contenidos

1. [Arquitectura General](#1-arquitectura-general)
2. [Infraestructura Azure](#2-infraestructura-azure)
3. [Stack Tecnológico](#3-stack-tecnológico)
4. [Variables de Entorno](#4-variables-de-entorno)
5. [CI/CD Pipeline (GitHub Actions)](#5-cicd-pipeline-github-actions)
6. [Base de Datos — Diagrama y Esquema](#6-base-de-datos--diagrama-y-esquema)
7. [Sistema de Módulos y Permisos](#7-sistema-de-módulos-y-permisos)
8. [Módulos de la Aplicación](#8-módulos-de-la-aplicación)
9. [Integraciones Externas](#9-integraciones-externas)
10. [Almacenamiento de Archivos](#10-almacenamiento-de-archivos)
11. [Flujo del Módulo de Contratación](#11-flujo-del-módulo-de-contratación)
12. [Generación de PDFs (FPDI + Imagick)](#12-generación-de-pdfs-fpdi--imagick)
13. [Autenticación y Seguridad](#13-autenticación-y-seguridad)
14. [Correo Electrónico](#14-correo-electrónico)
15. [Comandos y Tareas Programadas](#15-comandos-y-tareas-programadas)
16. [Historial de Cambios Técnicos Relevantes](#16-historial-de-cambios-técnicos-relevantes)

---

## 1. Arquitectura General

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          INTERNET / USUARIOS                            │
└───────────────────────────┬─────────────────────────────────────────────┘
                            │ HTTPS (app.saep.cl)
                            ▼
┌─────────────────────────────────────────────────────────────────────────┐
│            Azure App Service  (saep-app)  — Linux PHP 8.3              │
│                  Región: Chile Central                                  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                  Laravel 11.50  (wwwroot)                        │  │
│  │                                                                  │  │
│  │  ┌─────────────┐  ┌──────────────┐  ┌──────────────────────┐   │  │
│  │  │   Routes    │  │ Controllers  │  │     Blade Views      │   │  │
│  │  │  web.php    │→ │ /Http/...    │→ │  resources/views/    │   │  │
│  │  └─────────────┘  └──────┬───────┘  └──────────────────────┘   │  │
│  │                          │                                       │  │
│  │                    ┌─────┴──────┐                               │  │
│  │                    │  Services  │                               │  │
│  │                    │ OneDrive   │                               │  │
│  │                    │ Kizeo      │                               │  │
│  │                    │ GoogleDrive│                               │  │
│  │                    └────────────┘                               │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────┬──────────────────────────────────────────┘
              │                │                │               │
              ▼                ▼                ▼               ▼
   ┌─────────────────┐  ┌──────────────┐  ┌──────────┐  ┌──────────────┐
   │  Azure MySQL    │  │ Azure Blob   │  │Microsoft │  │  Google APIs │
   │  Flexible Server│  │  Storage     │  │  Graph   │  │  (OAuth2 +   │
   │  saep-mysql     │  │  saep-files  │  │(SharePnt)│  │  Drive)      │
   │  MySQL 8.0      │  │  (public)    │  │          │  │              │
   └─────────────────┘  └──────────────┘  └──────────┘  └──────────────┘
```

### Flujo de request típico

```
Browser → HTTPS → Azure App Service (nginx/FPM) → Laravel Router
       → Middleware Stack (auth, consentimiento, modulo:xxx)
       → Controller → Model (Eloquent ORM) → MySQL
       → View (Blade) → Response
```

---

## 2. Infraestructura Azure

| Recurso              | Nombre                 | Tipo                        | Región         | Detalles                                          |
|----------------------|------------------------|-----------------------------|----------------|---------------------------------------------------|
| App Service          | `saep-app`             | Web App (Linux, PHP 8.3)    | Chile Central  | Plan B1+, dominio: `app.saep.cl`                 |
| MySQL Flexible Server| `saep-mysql`           | MySQL 8.0 Flexible          | Chile Central  | Host: `saep-mysql.mysql.database.azure.com`       |
| Storage Account      | `saepplatformstorage`  | Azure Blob Storage          | Chile Central  | Contenedor: `saep-files` (acceso público por blob)|
| Resource Group       | `saep-rg`              | Resource Group              | Chile Central  | Agrupa todos los recursos                         |

### Credenciales de Base de Datos (solo referencia)

| Parámetro    | Valor                                   |
|--------------|-----------------------------------------|
| Host         | `saep-mysql.mysql.database.azure.com`   |
| Base de datos| `saep_db`                               |
| Usuario      | `saep_admin`                            |
| Puerto       | `3306` (SSL requerido)                  |

> ⚠️ La contraseña NO se documenta aquí. Está en la variable `DB_PASSWORD` en App Service → Configuration → Application Settings.

### Azure Blob Storage

| Parámetro                | Valor                                                              |
|--------------------------|--------------------------------------------------------------------|
| Storage Account Name     | `saepplatformstorage`                                              |
| Contenedor               | `saep-files`                                                       |
| Acceso                   | Público por blob (archivos accesibles con URL directa)             |
| URL base                 | `https://saepplatformstorage.blob.core.windows.net/saep-files`     |
| Disco Laravel            | `public` (driver `azure` via `league/flysystem-azure-blob-storage`)|

---

## 3. Stack Tecnológico

### Backend

| Tecnología         | Versión   | Uso                                              |
|--------------------|-----------|--------------------------------------------------|
| PHP                | 8.3.29    | Lenguaje principal                               |
| Laravel            | 11.50     | Framework MVC                                    |
| MySQL              | 8.0       | Base de datos relacional                         |
| Composer           | 2.x       | Gestor de dependencias PHP                       |

### Frontend

| Tecnología  | Versión | Uso                                  |
|-------------|---------|--------------------------------------|
| Blade       | —       | Templating engine (Laravel)          |
| Vite        | 5.x     | Bundler de assets                    |
| Tailwind CSS| 3.x     | Framework CSS utility-first          |
| Bootstrap Icons | —   | Íconos (bi-*)                        |
| Alpine.js   | 3.x     | Interactividad JS ligera             |

### Paquetes PHP clave

| Paquete                              | Versión  | Propósito                                           |
|--------------------------------------|----------|-----------------------------------------------------|
| `barryvdh/laravel-dompdf`           | ^3.1     | Generación de PDFs desde HTML (PDF 1.3/1.4)         |
| `setasign/fpdi`                      | 2.6.6    | Merge/combinar PDFs existentes                      |
| `league/flysystem-azure-blob-storage`| —        | Driver Azure Blob para Laravel Storage              |
| `google/apiclient`                   | ^2.x     | Google OAuth2 y APIs de Google                      |
| `phpoffice/phpspreadsheet`           | —        | Exportación Excel                                   |
| `dompdf/dompdf`                      | —        | Dependencia de laravel-dompdf                       |

### Extensiones PHP requeridas en producción

```
mbstring, xml, ctype, iconv, intl, pdo_mysql, zip, gd, bcmath, curl, imagick
```

> `imagick` es crítico para el fallback de PDFs 1.5+ en el módulo de contratación.

---

## 4. Variables de Entorno

Todas las variables se configuran en **Azure App Service → Configuration → Application Settings**.

### Aplicación

| Variable          | Ejemplo / Valor                               | Descripción                             |
|-------------------|-----------------------------------------------|-----------------------------------------|
| `APP_NAME`        | `SAEP Platform`                               | Nombre de la aplicación                 |
| `APP_ENV`         | `production`                                  | Entorno                                 |
| `APP_KEY`         | `base64:...`                                  | Clave de cifrado Laravel (32 bytes)     |
| `APP_DEBUG`       | `false`                                       | Nunca `true` en producción              |
| `APP_URL`         | `https://app.saep.cl`                         | URL pública                             |
| `APP_LOCALE`      | `es`                                          | Idioma                                  |

### Base de Datos

| Variable      | Valor                                       |
|---------------|---------------------------------------------|
| `DB_CONNECTION`| `mysql`                                    |
| `DB_HOST`     | `saep-mysql.mysql.database.azure.com`       |
| `DB_PORT`     | `3306`                                      |
| `DB_DATABASE` | `saep_db`                                   |
| `DB_USERNAME` | `saep_admin`                                |
| `DB_PASSWORD` | *(secreto — no documentar aquí)*            |

### Azure Storage

| Variable               | Valor                                                        |
|------------------------|--------------------------------------------------------------|
| `FILESYSTEM_DISK`      | `public`                                                     |
| `AZURE_STORAGE_NAME`   | `saepplatformstorage`                                        |
| `AZURE_STORAGE_KEY`    | *(secreto)*                                                  |
| `AZURE_STORAGE_CONTAINER` | `saep-files`                                            |
| `AZURE_STORAGE_URL`    | `https://saepplatformstorage.blob.core.windows.net/saep-files` |

### Microsoft Graph (SharePoint)

| Variable                    | Valor / Descripción                                  |
|-----------------------------|------------------------------------------------------|
| `MSGRAPH_TENANT_ID`         | Tenant ID de Azure AD                                |
| `MSGRAPH_CLIENT_ID`         | Client ID de la App Registration                     |
| `MSGRAPH_CLIENT_SECRET`     | Secret de la App Registration                        |
| `MSGRAPH_SHAREPOINT_HOST`   | `saepcl.sharepoint.com`                              |
| `MSGRAPH_SHAREPOINT_SITE`   | `PDR` (sitio para actas vehículos y otros)           |
| `MSGRAPH_ROOT_FOLDER`       | `Actas Vehiculos`                                    |
| `CONTRATACION_SHAREPOINT_SITE`   | `RRH`                                           |
| `CONTRATACION_SHAREPOINT_FOLDER` | `Postulantes Documents`                         |

### Google OAuth2 (para portales públicos)

| Variable               | Descripción                                          |
|------------------------|------------------------------------------------------|
| `GOOGLE_CLIENT_ID`     | Client ID de Google Cloud Console                    |
| `GOOGLE_CLIENT_SECRET` | Secret de Google Cloud Console                       |
| `GOOGLE_REDIRECT_URI`  | `https://app.saep.cl/denuncia-ley-karin/auth/callback` (o `/postulacion/auth/callback`) |

### Kizeo Forms

| Variable                        | Descripción                                  |
|---------------------------------|----------------------------------------------|
| `KIZEO_API_TOKEN`               | Token API de Kizeo Forms                     |
| `KIZEO_API_URL`                 | `https://www.kizeoforms.com/rest/v3`         |
| `KIZEO_WEBHOOK_SECRET`          | Secret para validar webhooks                 |
| `KIZEO_VEHICLE_FORM_ID`         | ID del formulario de vehículos               |
| `KIZEO_CHARLA_FORM_ID`          | ID del formulario de charlas                 |
| `KIZEO_OBSERVACION_FORM_ID`     | ID formulario observaciones                  |
| `KIZEO_INSPECCION_FORM_ID`      | ID formulario inspecciones                   |
| `KIZEO_VISITA_FORM_ID`          | ID formulario visitas terreno                |
| `KIZEO_ACCIDENTE_FORM_ID`       | ID formulario accidentes                     |
| `KIZEO_DECLARACION_FORM_ID`     | ID formulario declaraciones SST              |
| `KIZEO_PERSONAL_VIGENTE_LIST_ID`| ID lista personal vigente en Kizeo           |

### Correo

| Variable          | Descripción                          |
|-------------------|--------------------------------------|
| `MAIL_MAILER`     | `resend` (producción)                |
| `RESEND_API_KEY`  | API Key de Resend                    |
| `MAIL_FROM_ADDRESS` | `noreply@saep.cl`                  |
| `MAIL_FROM_NAME`  | `SAEP Platform`                      |

### Google Drive

| Variable                    | Descripción                                  |
|-----------------------------|----------------------------------------------|
| `GOOGLE_DRIVE_CREDENTIALS_PATH` | Ruta al archivo `google-credentials.json` |
| `GOOGLE_DRIVE_FOLDER_ID`    | ID carpeta raíz en Google Drive              |

### Sesiones y Cache

| Variable          | Valor                |
|-------------------|----------------------|
| `SESSION_DRIVER`  | `database`           |
| `CACHE_STORE`     | `database`           |
| `QUEUE_CONNECTION`| `database`           |

---

## 5. CI/CD Pipeline (GitHub Actions)

**Archivo:** `.github/workflows/main_saep-app.yml`

### Diagrama del Pipeline

```
git push origin main
        │
        ▼
┌──────────────────────────────────────────────────────────┐
│              GitHub Actions (ubuntu-latest)               │
│                                                          │
│  1. checkout@v4                                          │
│  2. setup-php@v2 (PHP 8.3 + extensiones)                │
│  3. setup-node@v4 (Node 20)                              │
│  4. Cache Composer (key: composer.lock hash)            │
│  5. Cache node_modules (key: package-lock.json hash)    │
│  6. composer install --no-dev --optimize-autoloader     │
│  7. npm ci (si cache miss)                              │
│  8. npm run build  (Vite → public/build/)               │
│  9. rm -rf node_modules                                 │
│ 10. azure/login@v2 (OIDC — secretos en GitHub)          │
│ 11. azure/webapps-deploy@v3 (deploy ZIP a saep-app)     │
│ 12. php artisan migrate --force  (via Kudu /api/command) │
└──────────────────────────────────────────────────────────┘
        │
        ▼
   Azure App Service
   saep-app (Production slot)
   /home/site/wwwroot
```

### Secretos requeridos en GitHub

| Secreto                                                | Descripción                          |
|--------------------------------------------------------|--------------------------------------|
| `AZUREAPPSERVICE_CLIENTID_F725ECA705F4413E9F96D4C3A9A1FE31` | Client ID OIDC Azure AD       |
| `AZUREAPPSERVICE_TENANTID_5C9F6BFA340F4D4FA2A5BC01741E1AA0`  | Tenant ID Azure AD            |
| `AZUREAPPSERVICE_SUBSCRIPTIONID_2785B06199884B4DA346A4F35FBEB92B` | Subscription ID Azure    |

### Notas importantes

- El paso de migraciones usa `az webapp deployment list-publishing-credentials` + Kudu REST API.
- La URL del Kudu SCM es `https://saep-app.scm.azurewebsites.net/api/command`.
- `composer install --no-dev` excluye `google/apiclient` (miles de archivos); en producción Azure instala todo según `composer.json` del deploy.
- Assets compilados (`public/build/`) van incluidos en el deploy ZIP.
- Los `node_modules` se eliminan antes del deploy para no subir archivos innecesarios.

---

## 6. Base de Datos — Diagrama y Esquema

### 6.1 Diagrama Entidad-Relación (tablas principales)

```
┌─────────────────┐        ┌──────────────────┐
│   departamentos │        │      roles        │
│─────────────────│        │──────────────────│
│ id (PK)         │        │ id (PK)           │
│ codigo          │        │ codigo            │
│ nombre          │        │ nombre            │
│ descripcion     │        │ puede_crear_forms │
│ activo          │        │ puede_aprobar     │
└────────┬────────┘        │ puede_ver_dashboard│
         │                 │ puede_admin_usuarios│
         │ 1:N             └───────┬──────────┘
         │                         │
         ▼                         │ 1:N
┌─────────────────────────────────────────────┐
│                    users                    │
│─────────────────────────────────────────────│
│ id (PK)                                     │
│ azure_oid          (nullable, unique)        │
│ talana_id          (nullable)               │
│ name                                        │
│ apellido_paterno / apellido_materno         │
│ email              (unique)                 │
│ rut                (nullable)               │
│ departamento_id    (FK → departamentos)     │
│ rol_id             (FK → roles)             │
│ cargo_id           (FK → cargos, nullable)  │
│ centro_costo_id    (FK → centros_costo, nullable)│
│ tipo_nomina / razon_social                  │
│ fecha_nacimiento / nacionalidad / sexo      │
│ estado_civil / fecha_ingreso / telefono     │
│ password / must_change_password             │
│ activo / ultimo_acceso                      │
│ acepta_politica_datos / fecha_aceptacion_politica│
│ foto_perfil (ruta Azure Blob)               │
│ deleted_at (SoftDeletes)                    │
│ created_at / updated_at                     │
└──────────────────────┬──────────────────────┘
                       │
         ┌─────────────┼──────────────────────┐
         │             │                      │
         ▼             ▼                      ▼
  ┌──────────┐  ┌───────────────┐  ┌──────────────────┐
  │  cargos  │  │ centros_costo │  │ consentimientos   │
  │──────────│  │───────────────│  │  _datos          │
  │ id (PK)  │  │ id (PK)       │  │──────────────────│
  │ codigo   │  │ codigo        │  │ id (PK)           │
  │ nombre   │  │ nombre        │  │ user_id (FK)      │
  │ activo   │  │ activo        │  │ tipo              │
  └──────────┘  └───────────────┘  │ acepta / fecha    │
                                   └──────────────────┘
```

### 6.2 Módulos y Permisos

```
┌─────────────┐    ┌──────────────────────────────┐    ┌──────────────┐
│    roles    │    │          rol_modulo           │    │   modulos    │
│─────────────│    │──────────────────────────────│    │──────────────│
│ id (PK)     │◄───│ rol_id (FK → roles)           │───►│ id (PK)      │
│ codigo      │    │ modulo_id (FK → modulos)      │    │ slug (unique)│
│ nombre      │    │ puede_ver    (bool)           │    │ nombre       │
└─────────────┘    │ puede_crear  (bool)           │    │ descripcion  │
                   │ puede_editar (bool)           │    │ icono        │
                   │ puede_eliminar (bool)         │    │ grupo        │
                   └──────────────────────────────┘    │ orden        │
                                                       │ activo       │
                                                       └──────────────┘
```

### 6.3 Módulo Formularios

```
┌──────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ formularios  │     │    respuestas    │     │   aprobaciones  │
│──────────────│     │─────────────────│     │─────────────────│
│ id (PK)      │1:N  │ id (PK)          │1:N  │ id (PK)          │
│ codigo       │────►│ formulario_id FK │────►│ respuesta_id FK  │
│ nombre       │     │ version_form     │     │ aprobador_id FK  │
│ schema_json  │     │ usuario_id FK    │     │ accion (enum)    │
│ departamento_id FK │ talana_trabajador_id│  │ comentario       │
│ version      │     │ departamento_id FK│    │ fecha            │
│ activo       │     │ estado (enum)    │     └─────────────────┘
│ requiere_aprobacion│ datos_json       │
│ aprobador_rol_id FK│ comentario_solicitante│
│ genera_pdf   │     │ pdf_url          │
│ template_pdf_id FK │ kizeo_form_id    │
│ email_notificacion │ kizeo_record_id  │
│ creado_por FK │     │ fecha_envio      │
└──────────────┘     │ fecha_resolucion │
                     └─────────────────┘

┌───────────────────────┐      ┌─────────────────────────┐
│   formulario_versiones│      │ formulario_campo_opciones│
│───────────────────────│      │─────────────────────────│
│ id (PK)               │      │ id (PK)                  │
│ formulario_id FK      │      │ formulario_id FK         │
│ version               │      │ campo_id (nombre campo)  │
│ schema_json           │      │ valor                    │
│ creado_por FK         │      │ etiqueta                 │
│ notas                 │      │ orden                    │
└───────────────────────┘      │ activo                   │
                               └─────────────────────────┘
```

### 6.4 Módulo Charlas SST

```
┌───────────────┐          ┌──────────────────┐          ┌──────────────────┐
│    charlas    │          │charla_asistentes │          │ charla_relatores │
│───────────────│    1:N   │──────────────────│          │──────────────────│
│ id (PK)       │─────────►│ id (PK)           │          │ id (PK)           │
│ titulo        │          │ charla_id FK      │          │ charla_id FK      │
│ tipo (enum)   │          │ usuario_id FK     │          │ usuario_id FK     │
│ lugar         │          │ estado (enum)     │          │ firma_imagen      │
│ fecha_programada│         │ firma_imagen      │          │ fecha_firma       │
│ duracion_minutos│         │ fecha_firma       │          └──────────────────┘
│ creado_por FK │          │ documento_hash    │
│ supervisor_id FK│         └──────────────────┘
│ estado (enum) │
└───────────────┘

┌───────────────────────────┐
│  kizeo_charla_tracking    │
│───────────────────────────│
│ id (PK)                   │
│ kizeo_form_id             │
│ kizeo_record_id (unique)  │
│ charla_id FK (nullable)   │
│ titulo / lugar / fecha    │
│ estado_match (enum)       │
│ sharepoint_path           │
│ tracking_data (JSON)      │
└───────────────────────────┘
```

### 6.5 Módulo Carta Gantt (SST)

```
┌────────────────┐   ┌──────────────────┐   ┌────────────────────┐
│ programas_sst  │   │  sst_categorias  │   │   sst_actividades  │
│────────────────│   │──────────────────│   │────────────────────│
│ id (PK)        │1:N│ id (PK)          │1:N│ id (PK)            │
│ nombre         │──►│ programa_id FK   │──►│ categoria_id FK    │
│ descripcion    │   │ nombre           │   │ nombre             │
│ centro_costo_id│   │ orden            │   │ descripcion        │
│ anio           │   └──────────────────┘   │ tipo (enum)        │
│ creado_por FK  │                          │ cantidad / unidad  │
│ deleted_at     │                          │ fecha_inicio/fin   │
└────────────────┘                          │ responsable_id FK  │
                                            │ estado (enum)      │
                                            │ avance (%)         │
                                            │ deleted_at         │
                                            └────────────────────┘
                                                    │
                    ┌───────────────────────────────┤
                    ▼                               ▼
         ┌──────────────────┐          ┌────────────────────┐
         │ sst_seguimientos │          │  sst_planes_accion │
         │──────────────────│          │────────────────────│
         │ id (PK)           │          │ id (PK)            │
         │ actividad_id FK   │          │ actividad_id FK    │
         │ fecha             │          │ descripcion        │
         │ avance (%)        │          │ responsable        │
         │ observacion       │          │ fecha_compromiso   │
         │ registrado_por FK │          │ estado (enum)      │
         └──────────────────┘          └────────────────────┘

┌────────────────────────┐
│  sst_reprogramaciones  │
│────────────────────────│
│ id (PK)                │
│ actividad_id FK        │
│ fecha_original         │
│ fecha_nueva            │
│ motivo                 │
│ registrado_por FK      │
└────────────────────────┘
```

### 6.6 Módulo Kanban

```
┌───────────────────┐         ┌─────────────────┐
│  kanban_tableros  │         │  kanban_columnas │
│───────────────────│   1:N   │─────────────────│
│ id (PK)           │────────►│ id (PK)          │
│ nombre            │         │ tablero_id FK    │
│ descripcion       │         │ nombre           │
│ creado_por FK     │         │ color            │
│ centro_costo_id FK│         │ orden            │
│ activo            │         │ es_completada    │
│ deleted_at        │         └────────┬─────────┘
└───────────────────┘                  │
         │                             │ 1:N
         │ 1:N                         ▼
         ▼                   ┌───────────────────┐
┌───────────────────┐        │   kanban_tareas   │
│ kanban_etiquetas  │        │───────────────────│
│───────────────────│        │ id (PK)           │
│ id (PK)           │        │ tablero_id FK     │
│ tablero_id FK     │        │ columna_id FK     │
│ nombre            │        │ titulo            │
│ color             │        │ descripcion       │
└──────────┬────────┘        │ prioridad (enum)  │
           │                 │ asignado_a FK     │
           │  N:M            │ creado_por FK     │
           ▼                 │ centro_costo_id FK│
  ┌─────────────────────┐   │ fecha_inicio/venc │
  │kanban_tarea_etiqueta│   │ orden             │
  │─────────────────────│   │ archivada (bool)  │
  │ tarea_id FK         │   │ deleted_at        │
  │ etiqueta_id FK      │   └────────┬──────────┘
  └─────────────────────┘            │
                                     ├──────────────────────────────┐
                                     │                              │
                                     ▼                              ▼
                          ┌──────────────────┐         ┌──────────────────────┐
                          │kanban_comentarios│         │kanban_checklist_items│
                          │──────────────────│         │──────────────────────│
                          │ id (PK)          │         │ id (PK)              │
                          │ tarea_id FK      │         │ tarea_id FK          │
                          │ usuario_id FK    │         │ descripcion          │
                          │ contenido        │         │ completado (bool)    │
                          └──────────────────┘         └──────────────────────┘

┌─────────────────────┐   ┌───────────────────┐   ┌──────────────────────┐
│  kanban_adjuntos    │   │kanban_actividad_log│   │kanban_tarea_asignados│
│─────────────────────│   │───────────────────│   │──────────────────────│
│ id (PK)             │   │ id (PK)           │   │ tarea_id FK          │
│ tarea_id FK         │   │ tablero_id FK     │   │ user_id FK           │
│ nombre_original     │   │ tarea_id FK (null)│   └──────────────────────┘
│ ruta (Storage)      │   │ usuario_id FK     │
│ mime_type           │   │ accion (enum)     │
│ tamano_bytes        │   │ descripcion       │
└─────────────────────┘   └───────────────────┘
```

### 6.7 Módulo Ley Karin

```
┌─────────────────────────────────────────────┐
│                  ley_karin                  │
│─────────────────────────────────────────────│
│ id (PK)                                     │
│ folio (unique, auto-generado)               │
│ tipo_denuncia (enum: ACOSO_SEXUAL, ACOSO_LABORAL, etc.) │
│ denunciante_nombre / rut / email / telefono │
│ denunciante_google_id / google_name         │
│ empresa / departamento / cargo              │
│ imputado_nombre / imputado_empresa          │
│ descripcion_hechos                          │
│ fecha_hechos                                │
│ documentos_adjuntos (JSON)                  │
│ estado (enum: RECIBIDA, EN_INVESTIGACION, etc.) │
│ resolucion / fecha_resolucion               │
│ investigador_id FK (→ users, nullable)      │
│ investigador_externo / empresa_externa      │
│ acuse_recibo_enviado (bool)                 │
│ plazo_legal_dias / plazo_vence_en           │
│ created_at / updated_at                     │
└──────────────────────────┬──────────────────┘
                           │ 1:N
                           ▼
              ┌──────────────────────────┐
              │      ley_karin_logs      │
              │──────────────────────────│
              │ id (PK)                  │
              │ ley_karin_id FK          │
              │ usuario_id FK            │
              │ accion                   │
              │ descripcion              │
              │ metadata (JSON)          │
              └──────────────────────────┘
```

### 6.8 Módulo Contratación (RRHH)

```
┌───────────────────────────────────────────────────────────────────┐
│                   postulantes_contratacion                        │
│───────────────────────────────────────────────────────────────────│
│ id (PK)                                                           │
│ folio (unique)              — ej: POST-2026-0001                  │
│ nombre                      — del postulante                      │
│ rut                         — RUT chileno (sin formato)           │
│ email                       — email del postulante                │
│ google_id (nullable)        — Google sub ID                       │
│ google_name (nullable)      — nombre completo según Google        │
│ google_avatar (TEXT)        — URL avatar Google (puede ser larga) │
│ carnet_frontal (nullable)   — ruta Azure Blob                     │
│ carnet_reverso (nullable)   — ruta Azure Blob                     │
│ certificado_afp (nullable)  — ruta Azure Blob                     │
│ certificado_fonasa (nullable)— ruta Azure Blob                    │
│ licencia_conducir (nullable)— ruta Azure Blob (OPCIONAL)          │
│ estado (enum)               — pendiente|en_revision|aprobado|rechazado│
│ observaciones (TEXT, nullable)— notas internas RRHH               │
│ created_at / updated_at                                           │
└───────────────────────────────────────────────────────────────────┘

Estructura de rutas Azure Blob:
  contratacion/{rut_sin_formato}/{campo}.{extension}
  Ejemplo: contratacion/12345678K/carnet_frontal.pdf

Estructura SharePoint (RRH site):
  Postulantes Documents/
    {rut} - {nombre}/
      carnet_frontal.pdf
      carnet_reverso.pdf
      certificado_afp.pdf
      certificado_fonasa.pdf
      licencia_conducir.pdf  (si aplica)
      {RUT_sin_puntos} - FICHA {NNN}.pdf   (ficha consolidada)
      Ej: 26.173.456-K - FICHA 001.pdf
```

### 6.9 Módulo Accidentes SST

```
┌───────────────────────────┐     ┌────────────────────────────────┐
│      accidentes_sst       │     │     opciones_accidente_sst     │
│───────────────────────────│     │────────────────────────────────│
│ id (PK)                   │     │ id (PK)                        │
│ folio_interno (unique)    │     │ tipo (enum: LESION, CAUSA, etc.)│
│ tipo (enum)               │     │ nombre                         │
│ empresa / faena / lugar   │     │ activo                         │
│ fecha_accidente           │     └────────────────────────────────┘
│ trabajador_nombre/rut     │             ▲
│ cargo / departamento      │             │ (referenciado vía JSON)
│ descripcion               │
│ lesiones (JSON)           │
│ causas_inmediatas (JSON)  │
│ causas_basicas (JSON)     │
│ medidas_control (JSON)    │
│ dias_perdidos             │
│ accidente_grave (bool)    │
│ notificado_seremi (bool)  │
│ estado (enum)             │
│ creado_por FK             │
│ deleted_at                │
└───────────────────────────┘
```

### 6.10 Otras tablas del sistema

```
┌─────────────────────────────────────────────────────────┐
│               TABLAS AUXILIARES / SISTEMA               │
├──────────────────────────┬──────────────────────────────┤
│ sessions                 │ Sesiones de usuario (BD)     │
│ cache                    │ Cache Laravel en BD           │
│ jobs / job_batches /     │ Colas de trabajo asíncronas  │
│ failed_jobs              │                               │
│ password_reset_tokens    │ Tokens recuperación contraseña│
│ notifications            │ Notificaciones in-app (JSON)  │
│ mail_logs                │ Log de todos los correos enviados/fallidos │
│ sst_notificacion_log     │ Log de emails enviados SST    │
│ webhook_logs             │ Log de webhooks Kizeo         │
│ configuraciones          │ Config clave-valor del sistema│
│ notas_personales         │ Notas dictadas por voz        │
│ firmas_electronicas      │ Registro firmas digitales     │
│ archivos_adjuntos        │ Metadatos de archivos privados│
│ consentimientos_datos    │ Ley 21.719 — consentimientos  │
│ solicitudes_arco         │ Solicitudes ARCO Ley 21.719   │
│ registro_tratamiento_datos│ Registro tratamiento datos   │
│ templates_pdf            │ Plantillas HTML para PDF      │
│ documentos_kizeo         │ Documentos sincronizados Kizeo│
│ log_integraciones        │ Log de llamadas a APIs ext.   │
│ stop_observaciones       │ Observaciones tarjeta STOP    │
│ visitas_sst              │ Registro visitas/inspecciones │
│ auditorias_sst           │ Registro auditorías SST       │
└──────────────────────────┴──────────────────────────────┘
```

---

## 7. Sistema de Módulos y Permisos

### Flujo de autorización

```
Request HTTP
    │
    ▼ Middleware: auth (verifica sesión activa)
    │
    ▼ Middleware: consentimiento (verifica acepta_politica_datos = true)
    │
    ▼ Middleware: force.password (verifica must_change_password = false)
    │
    ▼ Middleware: modulo:{slug} (CheckModulo)
         │
         ├── user → rol → rol_modulo → modulo (slug == solicitado)
         │          └── verifica pivot: puede_ver = true
         │
         ├── Si NO tiene acceso → abort(403)
         └── Si tiene acceso → Controller continúa
```

### Roles del sistema

| Código          | Nombre              | Descripción                                         |
|-----------------|---------------------|-----------------------------------------------------|
| `SUPER_ADMIN`   | Súper Administrador | Acceso total a todos los módulos                    |
| `PREVENCIONISTA`| Prevencionista      | SST completo + usuarios + protección datos          |
| `JEFE`          | Jefe / Gerente      | SST lectura/creación, sin admin                     |
| `COORDINADOR`   | Coordinador         | Igual a JEFE                                        |
| `SUPERVISOR`    | Supervisor          | Igual a JEFE                                        |
| `OPERARIO`      | Operario            | Solo formularios asignados, denuncia Ley Karin      |

### Módulos registrados (slugs)

| Slug                   | Grupo              | Nombre                       |
|------------------------|--------------------|------------------------------|
| `dashboard`            | General            | Panel Principal              |
| `formularios`          | Solicitudes        | Formularios                  |
| `categorias_formularios`| Solicitudes       | Categorías Formularios       |
| `respuestas`           | Solicitudes        | Solicitudes / Respuestas     |
| `kizeo_analytics`      | Prevención SST     | Kizeo Analytics              |
| `charlas`              | Prevención SST     | Charlas SST                  |
| `carta_gantt`          | Prevención SST     | Carta Gantt                  |
| `visitas_sst`          | Prevención SST     | Visitas / Inspecciones       |
| `auditorias_sst`       | Prevención SST     | Auditorías SST               |
| `accidentes_sst`       | Prevención SST     | Accidentes SST               |
| `ley_karin`            | Prevención SST     | Ley Karin (Gestión)          |
| `ley_karin_denuncia`   | Prevención SST     | Canal de Denuncia            |
| `stop_dashboard`       | Prevención SST     | Tarjeta STOP (CCU)           |
| `kanban`               | Mis Herramientas   | Tablero Kanban               |
| `notas_personales`     | Mis Herramientas   | Notas Personales             |
| `usuarios`             | Administración     | Gestión de Usuarios          |
| `departamentos`        | Administración     | Departamentos                |
| `cargos`               | Administración     | Cargos                       |
| `centros_costo`        | Administración     | Centros de Costo             |
| `configuracion`        | Sistema            | Configuración                |
| `permisos`             | Sistema            | Permisos por Rol             |
| `importacion`          | Sistema            | Importar Datos               |
| `exportaciones`        | Sistema            | Exportaciones                |
| `proteccion_datos`     | Protección Datos   | Protección de Datos (21.719) |
| `documentacion`        | Ayuda              | Documentación                |
| `contratacion`         | RRHH               | Contratación                 |
| `monitor_correos`      | Configuración      | Monitor de Correos           |

---

## 8. Módulos de la Aplicación

### 8.1 Dashboard

**Ruta:** `/`  
**Controller:** `DashboardController`  
Panel principal con resumen de métricas del sistema. Accesible a todos los roles con permiso `dashboard`.

---

### 8.2 Formularios y Respuestas

**Rutas:** `/formularios`, `/respuestas`  
**Controllers:** `FormularioController`, `RespuestaController`

- **Formularios:** Creación con schema JSON dinámico. Versionado (tabla `formulario_versiones`). Asignación a usuarios. Aprobación configurable por rol.
- **Respuestas:** Completar formularios asignados. Estados: Borrador → Pendiente → Aprobado/Rechazado/Revisión. Generación PDF opcional.
- **Importación/Exportación:** CSV de respuestas.

---

### 8.3 Charlas SST

**Rutas:** `/charlas`  
**Controller:** `CharlaSstController`

- Tipos: CHARLA_5MIN, CAPACITACIÓN, INDUCCIÓN, CHARLA_ESPECIAL
- Asistentes firmantes (firma digital → imagen base64, hash documento)
- Relatores con firma digital
- Integración Kizeo: tracking sincronizado vía `KizeoCharlaTracking`
- Emails automáticos de reporte

---

### 8.4 Carta Gantt (Programa SST)

**Rutas:** `/carta-gantt`  
**Controller:** `CartaGanttController`

- Estructura: Programa → Categorías → Actividades
- Seguimiento de avance (%) con historial
- Reprogramación con registro de motivos
- Planes de acción por actividad
- Exportación PDF
- Importación masiva CSV
- Emails de alerta automáticos por vencimiento

---

### 8.5 Kizeo Analytics

**Rutas:** `/kizeo`  
**Controller:** `KizeoDashboardController`, `KizeoWebhookController`  
**Service:** `KizeoService`

- Dashboard de datos de formularios Kizeo Forms
- Webhook receptor: `POST /api/kizeo/webhook` (sin CSRF, sin auth)
- Sincronización de documentos a SharePoint
- Tracking de charlas Kizeo → SAEP

---

### 8.6 Visitas / Inspecciones SST

**Rutas:** `/visitas-sst`  
**Controller:** `VisitaSstController`

Registro de visitas e inspecciones de terreno SST.

---

### 8.7 Auditorías SST

**Rutas:** `/auditorias-sst`  
**Controller:** `AuditoriaSstController`

Registro de auditorías del sistema SST.

---

### 8.8 Accidentes SST

**Rutas:** `/accidentes-sst`  
**Controller:** `AccidenteSstController`

- Registro completo de accidentes/incidentes/cuasi-accidentes
- Catálogo configurable: lesiones, causas inmediatas, causas básicas, medidas de control
- Seguimiento de notificación SEREMI
- Soft delete

---

### 8.9 Ley Karin

**Rutas admin:** `/ley-karin`  
**Ruta pública:** `/denuncia-ley-karin`  
**Controllers:** `LeyKarinController`, `LeyKarinPublicoController`

- Portal público autenticado con Google OAuth2
- Folio auto-generado (LK-YYYY-NNNN)
- Flujo completo: recepción → investigación → resolución
- Emails: acuse recibo, comunicación denuncia, resolución
- Plazo legal configurable
- Log de acciones (tabla `ley_karin_logs`)

---

### 8.10 Tarjeta STOP (CCU)

**Rutas:** `/stop-dashboard`  
**Controller:** `StopDashboardController`  
**Service:** `StopAnalyticsService`, `StopExcelExport`

- Sincronización desde Google Drive
- Dashboard analytics de observaciones STOP
- Reporte periódico por email

---

### 8.11 Tablero Kanban

**Rutas:** `/kanban`  
**Controller:** `KanbanController`

- Tableros por centro de costo
- Columnas personalizables con marcador de completada
- Tareas: prioridad, múltiples asignados, etiquetas, checklist, adjuntos, comentarios
- Vista Calendario con datos vía AJAX
- Exportación PDF
- Log de actividad por tablero
- Emails de asignación y vencimiento
- Soft delete en tableros y tareas

---

### 8.12 Protección de Datos (Ley 21.719)

**Rutas:** `/proteccion-datos`  
**Controller:** `ProteccionDatosController`

- Registro de consentimiento al primer login
- Portal del titular (ARCO: Acceso, Rectificación, Cancelación, Oposición)
- Administración de solicitudes ARCO
- Registro de tratamiento de datos
- Revocación de consentimiento
- Política de privacidad pública

---

### 8.13 Contratación RRHH

**Rutas admin:** `/contratacion` (requiere `modulo:contratacion`)  
**Ruta pública:** `/postulacion`  
**Controllers:** `ContratacionController`, `ContratacionPublicoController`

Ver sección detallada [11. Flujo del Módulo de Contratación](#11-flujo-del-módulo-de-contratación).

---

### 8.14 Monitor de Correos

**Ruta:** `/configuracion/mail-logs` (requiere `modulo:configuracion`)  
**Controller:** `MailLogController`  
**Navbar:** Configuración → Monitor de Correos

- Registro automático de **todos los correos** enviados o fallidos via listener `LogMailSent` → evento `MessageSent`
- Tabla `mail_logs` con campos: `mailable`, `subject`, `to_email`, `to_name`, `status` (sent/failed), `error_message`, `body_html`, `sent_at`
- Vista con estadísticas (total, enviados, fallidos, tasa éxito), filtros por estado/email/asunto, tabla paginada
- Preview del HTML del correo en modal
- Acción "Limpiar registros" con confirmación (borra logs según rango de fechas)
- `MailLog::recordFailed()` — método estático para registrar errores desde catch blocks de controladores

---

### 8.15 Gestión de Usuarios y Maestros

**Controllers:** `UserController`, `DepartamentoController`, `CargoController`, `CentroCostoController`

- CRUD completo de usuarios (soft delete)
- Reset de contraseña (individual y masivo)
- Importación CSV de centros de costo
- Gestión de departamentos y cargos

---

### 8.16 Sistema de Permisos

**Rutas:** `/permisos`  
**Controller:** `PermisoController`

- Gestión de roles y módulos
- Asignación granular de permisos (ver/crear/editar/eliminar) por rol y módulo
- CRUD de roles y módulos

---

## 9. Integraciones Externas

### 9.1 Microsoft Graph API (SharePoint)

```
┌──────────────┐    OAuth2 Client Credentials    ┌──────────────────────┐
│ SAEP Platform│ ─────────────────────────────► │ Azure AD             │
│              │ ◄───────── access_token ─────── │ (tenant SAEP)        │
│              │                                 └──────────────────────┘
│              │    PUT /sites/{siteId}/drive/    ┌──────────────────────┐
│              │    root:/{path}:/content         │ SharePoint Online    │
│              │ ─────────────────────────────► │ saepcl.sharepoint.com│
│              │ ◄───── 200/201 OK ──────────── │                      │
└──────────────┘                                 └──────────────────────┘
```

**Uso:**
- Subir documentos de postulantes → Sitio: `RRH`, Carpeta: `Postulantes Documents`
- Subir actas de vehículos Kizeo → Sitio: `PDR`, Carpeta: `Actas Vehiculos`
- Subir charlas, observaciones, inspecciones, visitas, accidentes, declaraciones

**Implementación:** `app/Services/OneDriveService.php`
- Token OAuth2 cacheado (50 min) en BD (`msgraph_access_token`)
- Site ID cacheado permanentemente (`msgraph_sharepoint_site_id`)
- Upload session automático para archivos > 4 MB

---

### 9.2 Google OAuth2 (Portales Públicos)

```
Postulante/Denunciante
       │
       ▼  GET /postulacion/auth/google (o /denuncia-ley-karin/auth/google)
       │
       ▼  Redirect → accounts.google.com/o/oauth2/auth
       │
       ▼  Usuario acepta permisos (profile, email)
       │
       ▼  GET /postulacion/auth/callback?code=xxx
       │
       ▼  SAEP intercambia code → access_token → userinfo
       │
       ▼  Sesión PHP (google_user) almacenada
       │
       ▼  GET /postulacion/formulario (protegido por sesión Google)
```

**Uso:**
- Portal Contratación: `/postulacion/auth/callback`
- Portal Ley Karin: `/denuncia-ley-karin/auth/callback`
- Ambas rutas registradas en Google Cloud Console

---

### 9.3 Kizeo Forms

```
Kizeo Forms         SAEP Platform            SharePoint
     │                   │                       │
     │  POST /api/kizeo/webhook                  │
     │ ─────────────────►│                       │
     │                   │ Valida webhook secret  │
     │                   │ Descarga PDF de Kizeo  │
     │                   │ ─────────────────────►│
     │                   │                       │
     │                   │ Guarda en BD           │
     │                   │ (webhook_logs)         │
```

**Servicio:** `KizeoService` — API REST de Kizeo (`https://www.kizeoforms.com/rest/v3`)
**Formularios configurados:** vehículos, charlas, observaciones, inspecciones, visitas, accidentes, declaraciones.

---

### 9.4 Google Drive

**Servicio:** `GoogleDriveService`  
**Uso:** Módulo STOP (tarjeta STOP de CCU) — sincronización de observaciones desde Google Drive.

---

### 9.5 Resend (Email)

**Driver Laravel:** `resend`  
**Paquete:** `resend/resend-php`  
Todos los correos transaccionales del sistema se envían vía Resend.

---

## 10. Almacenamiento de Archivos

### Disco `public` → Azure Blob Storage

```
Contenedor: saep-files
│
├── contratacion/
│   └── {rut_sin_formato}/
│       ├── carnet_frontal.pdf
│       ├── carnet_reverso.pdf
│       ├── certificado_afp.pdf
│       ├── certificado_fonasa.pdf
│       └── licencia_conducir.pdf  (si aplica)
│
├── fotos/
│   └── {user_id}/
│       └── foto_perfil.{ext}
│
└── [otros archivos públicos del sistema]
```

### Disco `local` → Azure App Service Ephemeral Storage

```
/home/site/wwwroot/storage/app/private/
└── [archivos adjuntos privados de formularios/ley karin]
```

> ⚠️ El almacenamiento local en App Service Linux es efímero. Los archivos privados se pierden al redeploy. Para producción, migrar a Azure Blob con visibilidad privada.

### Acceso a archivos públicos

```php
// Generar URL pública de Azure Blob:
Storage::disk('public')->url($ruta);
// Resultado: https://saepplatformstorage.blob.core.windows.net/saep-files/{ruta}
```

---

## 11. Flujo del Módulo de Contratación

### 11.1 Portal del Postulante (Público)

```
                    /postulacion
                         │
                         ▼
              ┌──────────────────────┐
              │  Página de inicio    │
              │  (no requiere auth)  │
              └──────────┬───────────┘
                         │  Click "Postular con Google"
                         ▼
              ┌──────────────────────┐
              │  Google OAuth2       │
              │  (redirect + callback)│
              └──────────┬───────────┘
                         │  Sesión google_user en PHP
                         ▼
              ┌──────────────────────┐
              │  /postulacion/       │
              │  formulario          │
              │                      │
              │  - Nombre, RUT       │
              │  - Carnet Frontal    │
              │  - Carnet Reverso    │
              │  - Certif. AFP       │
              │  - Certif. FONASA    │
              │  - Licencia Conducir │
              │    (opcional)        │
              └──────────┬───────────┘
                         │  POST /postulacion/enviar
                         ▼
              ┌──────────────────────────────────────────┐
              │  ContratacionPublicoController::store()  │
              │                                          │
              │  1. Validar RUT chileno                  │
              │  2. Subir documentos → Azure Blob        │
              │     contratacion/{rut}/{campo}.{ext}     │
              │  3. Crear PostulanteContratacion         │
              │     estado: pendiente                    │
              │     folio: POST-YYYY-NNNN                │
              │  4. subirASharePoint()                   │
              │     a) Generar ficha PDF (DomPDF)        │
              │     b) Merge documentos PDF (FPDI)       │
              │        [Fallback: Imagick si PDF 1.5+]  │
              │     c) Subir ficha a SharePoint          │
              │     d) Subir cada documento a SharePoint │
              │  5. Enviar email de bienvenida           │
              └──────────┬───────────────────────────────┘
                         │
                         ▼
              ┌──────────────────────┐
              │  /postulacion/       │
              │  confirmacion/{folio}│
              │  (página de éxito)   │
              └──────────────────────┘
```

### 11.2 Panel Administrador (RRHH)

```
/contratacion  (requiere modulo:contratacion)
│
├── GET /                    → index()   — Lista paginada de postulantes
├── GET /{postulante}        → show()    — Detalle del postulante
├── PATCH /{postulante}      → update()  — Cambiar estado + observaciones
├── GET /{postulante}/zip    → descargarZip() — ZIP con todos los documentos
├── GET /{postulante}/doc/{campo} → descargarDocumento() — Documento individual
├── GET /{postulante}/ficha-pdf  → fichaPdf()  — Descargar ficha consolidada
├── POST /{postulante}/resincronizar → resincronizarSharePoint() — Forzar resync
├── GET /exportar/excel      → exportarExcel() — Excel de todos los postulantes
├── GET /configuracion/emails → configuracion() — Config emails
└── PATCH /configuracion/emails → guardarConfiguracion()
```

### 11.3 Diagrama Estados del Postulante

```
                    [PENDIENTE]
                        │
              ┌─────────┴─────────┐
              │  Admin cambia     │
              │  estado           │
              ▼                   ▼
         [EN_REVISION]       [RECHAZADO]
              │
    ┌─────────┴─────────┐
    │                   │
    ▼                   ▼
[APROBADO]         [RECHAZADO]
```

---

## 12. Generación de PDFs (FPDI + Imagick)

### Flujo de generación de ficha consolidada

```
generarFichaBytes() / subirASharePoint()
            │
            ▼
┌─────────────────────────────────┐
│  1. Generar ficha con DomPDF    │
│     (HTML → PDF 1.3/1.4)        │
│     Contenido: datos personales │
│     + lista de documentos       │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│  2. ¿Hay documentos PDF?        │
│     ($pdfDocRutas not empty)    │
└────────────────┬────────────────┘
            NO ──┘  SÍ
                     │
                     ▼
┌─────────────────────────────────┐
│  3. Inicializar FPDI            │
│     (try/catch — si falla,      │
│      $fpdi = null, solo ficha)  │
│  4. Importar páginas DomPDF     │
│     en FPDI                     │
└────────────────┬────────────────┘
                 │
                 ▼ Por cada documento
┌─────────────────────────────────────────────────────────────┐
│  5. Intento 1: FPDI::setSourceFile()                        │
│     → Funciona con PDF ≤ 1.4 y algunos 1.5                  │
│     → Falla con PDF 1.5+ /ObjStm (Adobe, Foxit, etc.)       │
│     Si éxito → importar páginas con $fpdi->useTemplate()    │
│     Si falla → Log::warning + intentar Imagick              │
├─────────────────────────────────────────────────────────────┤
│  5b. Intento 2: Imagick (fallback)                          │
│     → Solo si $merged = false && class_exists('Imagick')    │
│     → setResolution(150, 150)                               │
│     → readImage($tempDoc) — usa Ghostscript internamente    │
│     → Por cada página: convertir a PNG → tempnam()          │
│     → $fpdi->AddPage() + $fpdi->Image($tempPng, ...)        │
│     → Log: convertido con Imagick                           │
│     → Si también falla → Log::warning — documento omitido  │
└────────────────────────────────────────────────────────────-┘
                 │
                 ▼
┌─────────────────────────────────┐
│  6. $fpdi->Output('S')          │
│     → Bytes del PDF final       │
│  7. Limpiar archivos temporales │
└─────────────────────────────────┘
```

### Compatibilidad de PDFs

| Formato PDF         | FPDI (gratuito) | Imagick (Ghostscript) |
|---------------------|-----------------|-----------------------|
| PDF 1.3 / 1.4       | ✅ Compatible   | ✅ Compatible         |
| PDF 1.5 con /ObjStm | ❌ No soportado | ✅ Compatible         |
| PDF 1.6 / 1.7+      | ❌ No soportado | ✅ Compatible         |
| PDF con contraseña  | ❌ No soportado | ❌ No soportado       |

> **Nota:** El PDF generado por DomPDF es siempre 1.3/1.4 — FPDI puede leerlo sin problemas. Los documentos de usuario pueden ser cualquier versión.

### Logs de diagnóstico

```
# Ver en Azure → App Service → Log stream, o descargar desde Kudu:
Log::info('SharePoint contratacion: documento PDF importado con FPDI', [...])
Log::info('SharePoint contratacion: documento PDF convertido con Imagick', [...])
Log::warning('SharePoint contratacion: FPDI falló, intentando Imagick', ['error' => ...])
Log::warning('SharePoint contratacion: Imagick también falló', ['error' => ...])
```

---

## 13. Autenticación y Seguridad

### Autenticación interna (usuarios SAEP)

```
POST /login
    │
    ▼ Middleware throttle:5,1 (5 intentos por minuto)
    │
    ▼ AuthController::login()
       │
       ├── Verifica activo = true
       ├── Auth::attempt(['email', 'password'])
       ├── Actualiza ultimo_acceso
       └── Redirect → /
```

### Autenticación pública (Google OAuth2)

- Portal Contratación: sesión `google_user` en PHP (no usa Auth de Laravel)
- Portal Ley Karin: misma mecánica de sesión

### Middleware Stack

| Middleware          | Descripción                                                      |
|---------------------|------------------------------------------------------------------|
| `auth`              | Requiere sesión activa de Laravel                               |
| `consentimiento`    | Requiere `acepta_politica_datos = true` (Ley 21.719)            |
| `force.password`    | Redirige al cambio de contraseña si `must_change_password = true`|
| `modulo:{slug}`     | Verifica acceso al módulo (`CheckModulo` middleware)             |
| `permission:{accion}`| Verifica acción específica (`puede_aprobar`, etc.)              |
| `throttle:5,1`      | Rate limiting en login (5 req/min por IP)                       |
| `throttle:3,1`      | Rate limiting en solicitud de reset de contraseña               |

### Seguridad adicional

- Contraseñas hasheadas con `bcrypt` (12 rounds)
- CSRF en todos los formularios POST (excepto webhook Kizeo explícitamente excluido)
- Soft delete en usuarios, tableros Kanban, tareas, carta gantt y accidentes
- RUT normalizado al guardar (solo dígitos + K), formateado al leer

---

## 14. Correo Electrónico

**Proveedor:** Resend (`resend/resend-php`)  
**From:** `noreply@saep.cl` / `SAEP Platform`

### Mailables registrados

| Mailable                     | Evento disparador                              |
|------------------------------|------------------------------------------------|
| `BienvenidaUsuarioMail`      | Nuevo usuario creado                           |
| `PasswordResetMail`          | Solicitud de reset de contraseña               |
| `RespuestaFormularioMail`    | Nueva respuesta de formulario                  |
| `RespuestaCreadaMail`        | Respuesta creada por usuario                   |
| `RespuestaAprobadaMail`      | Respuesta aprobada/rechazada                   |
| `LeyKarinDenunciaMail`       | Nueva denuncia Ley Karin recibida              |
| `LeyKarinAcuseReciboMail`    | Acuse de recibo al denunciante                 |
| `LeyKarinResolucionMail`     | Resolución enviada al denunciante              |
| `CharlaTrackingReporteMail`  | Reporte periódico de charlas Kizeo             |
| `KanbanTareaAsignadaMail`    | Tarea Kanban asignada a usuario                |
| `KanbanVencimientoMail`      | Tarea Kanban próxima a vencer                  |
| `SstActividadAlertaMail`     | Actividad Carta Gantt próxima a vencer         |
| `StopReporteMail`            | Reporte periódico STOP                         |
| `VehiculoEntregaMail`        | Entrega de vehículo (Kizeo)                    |
| `VehiculoDevolucionMail`     | Devolución de vehículo (Kizeo)                 |
| `ContratacionAcuseReciboMail`| Acuse al postulante tras envío de documentos   |
| `ContratacionNuevoPostulanteMail` | Notificación a RRHH por nuevo postulante  |

### Monitor de Correos

Todos los envíos quedan registrados en la tabla `mail_logs` mediante el listener global `LogMailSent` que escucha el evento `MessageSent` de Laravel. Los fallos capturados manualmente en catch blocks se registran con `MailLog::recordFailed()`. El módulo `/configuracion/mail-logs` permite visualizar y gestionar estos registros desde la plataforma.

---

## 15. Comandos y Tareas Programadas

**Archivos:** `app/Console/Commands/`

Los comandos de artisan se registran en `routes/console.php`. Las tareas programadas se configuran en `app/Console/Kernel.php` (o equivalente Laravel 11).

> Para verificar las tareas programadas activas en producción:
> ```bash
> php artisan schedule:list
> ```

---

## 16. Historial de Cambios Técnicos Relevantes

### 2026-04-27 — Fallback Imagick para PDFs 1.5+ (commit `951bdc5`)

**Problema:** FPDI (versión gratuita) no puede leer PDFs con `/ObjStm` (compressed object streams), formato usado por Adobe Acrobat, Foxit, y la mayoría de PDFs modernos. Los documentos de postulantes se omitían silenciosamente en la ficha consolidada.

**Solución:** Se implementó una cadena de fallback en `ContratacionController::generarFichaBytes()` y `ContratacionPublicoController::subirASharePoint()`:
1. FPDI directo (funciona con PDF ≤1.4)
2. Imagick con Ghostscript: renderiza cada página como PNG a 150 DPI y la incrusta

**Archivos modificados:**
- `app/Http/Controllers/ContratacionController.php`
- `app/Http/Controllers/ContratacionPublicoController.php`

---

### 2026-04-25 — Botón "Sincronizar SharePoint" (commit `8f9c33e`)

**Problema:** Si el postulante re-enviaba documentos, la ficha en SharePoint no se actualizaba.

**Solución:** Nueva ruta `POST /contratacion/{postulante}/resincronizar` → `resincronizarSharePoint()` que re-sube todos los documentos y regenera la ficha PDF. Botón agregado en `show.blade.php`.

---

### 2026-04-25 — Fix 500 error en `/contratacion` (commit `f42c03d`)

**Problema:** `Call to undefined method` al usar `$this->middleware()` en el constructor de `ContratacionController`. En Laravel 11 este método fue eliminado.

**Solución:** Eliminado el constructor; la autorización se maneja vía middleware de ruta `modulo:contratacion`.

**Lección:** En Laravel 11, NO usar `$this->middleware()` en constructores. Toda la autorización por módulo va en la definición de rutas.

---

### 2026-04-25 — Módulo de Contratación inicial (commit `3df63b5`)

- Migración `postulantes_contratacion`
- Portal público `/postulacion` con Google OAuth2
- Panel admin `/contratacion`
- Integración SharePoint (sitio `RRH`)
- Generación ficha PDF con DomPDF + FPDI
- Email de bienvenida

---

### 2026-04-27 — Fix `google_avatar` VARCHAR → TEXT

**Problema:** Las URLs de avatares de Google son URLs largas que superan VARCHAR(255).

**Migración:** `2026_04_27_093120_alter_google_avatar_column_in_postulantes_contratacion.php` — cambia `google_avatar` de `string` (VARCHAR 255) a `text`.

---

### 2026-04-xx — Fix `Storage::url()` para archivos en Azure Blob

**Problema:** Las rutas de archivos se concatenaban manualmente con la URL de Blob, causando rutas incorrectas.

**Solución:** Usar siempre `Storage::url($ruta)` o `Storage::disk('public')->url($ruta)` para generar URLs correctas.

**Archivos afectados:** layouts, vistas de perfil, respuestas.

---

---

### 2026-04-28 — Renombrar PDF consolidado SharePoint (commit `2199ae1`)

**Cambio:** El archivo de ficha consolidada subido a SharePoint cambió de nombre:
- **Antes:** `{folio}_ficha.pdf` (ej: `POST-2026-0001_ficha.pdf`)
- **Ahora:** `{RUT_sin_puntos} - FICHA {NNN}.pdf` (ej: `26.173.456-K - FICHA 001.pdf`)

**Lógica del nuevo nombre:** Helper privado `fichaFilename()` en `ContratacionController` extrae el número secuencial del folio, lo formatea con 3 dígitos y lo combina con el RUT formateado.

**Archivos modificados:**
- `app/Http/Controllers/ContratacionController.php` — métodos `subirFichaSharePoint()`, `fichaPdf()`, `resincronizarSharePoint()` + nuevo helper `fichaFilename()`

**Nota:** Para que los registros existentes en SharePoint tengan el nuevo nombre, usar el botón "Re-sincronizar SharePoint" en la vista de detalle de cada postulante.

---

### 2026-04-28 — Módulo Monitor de Correos (commit `1292b40` + `c940b5b`)

**Funcionalidad:** Nuevo módulo de monitoreo de correos transaccionales.

**Implementación:**
- `MailLog` model + migración `2026_04_27_200000_create_mail_logs_table`
- `LogMailSent` listener registrado en `AppServiceProvider::boot()` — escucha `MessageSent`
- `MailLogController` con métodos `index()`, `show()`, `destroy()`, `limpiar()`
- Vista `mail_logs/index.blade.php` con stats, filtros, tabla, modal preview, modal limpiar
- Link en navbar bajo Configuración: `bi-envelope-check` → `/configuracion/mail-logs`
- Fix modal: usa `style="display:none"` (el CSS de la plataforma NO tiene clase `.hidden`)

**Lección aprendida:** La plataforma NO usa Tailwind — nunca usar `class="hidden"` para ocultar elementos. Usar siempre `style="display:none"` inline.

---

### Decisiones arquitectónicas documentadas

| Decisión                                    | Justificación                                                   |
|---------------------------------------------|-----------------------------------------------------------------|
| `SESSION_DRIVER=database`                   | App Service Linux no tiene Redis incluido; BD es más estable   |
| `CACHE_STORE=database`                      | Misma razón; funciona con MySQL                                 |
| `QUEUE_CONNECTION=database`                 | Sin Redis disponible                                            |
| Azure Blob para archivos públicos           | Almacenamiento local App Service es efímero                    |
| FPDI + Imagick (no FPDI-PDF-Parser)        | FPDI-PDF-Parser es comercial (€ por sitio)                     |
| Google OAuth2 para portales públicos        | Sin necesidad de gestionar cuentas externas                    |
| `modulo:slug` middleware (no Gates/Policies)| Flexibilidad total en permisos sin código adicional por módulo |
| Folio auto-generado (POST-YYYY-NNNN)       | Trazabilidad única, legible por humanos                        |

---

*Documentación generada el 27 de abril de 2026. Actualizar ante cualquier cambio de infraestructura, nuevo módulo o modificación de la arquitectura.*
