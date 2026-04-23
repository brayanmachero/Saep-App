@extends('layouts.app')

@section('title', 'Documentación — Infraestructura y DevOps')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-cloud-fill" style="color:var(--primary-color);"></i>
                Infraestructura y DevOps
            </h2>
            <p class="page-subheading">Stack técnico, arquitectura Azure, deploy automático y servicios externos</p>
        </div>
        <a href="{{ route('documentacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Documentación
        </a>
    </div>

    {{-- Navegación interna --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <strong style="font-size:.85rem;color:var(--text-muted);display:block;margin-bottom:.5rem;">Contenido</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            <a href="#stack" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Stack Tecnológico</a>
            <a href="#azure" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Recursos Azure</a>
            <a href="#deploy" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Deploy Automático</a>
            <a href="#storage" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Almacenamiento</a>
            <a href="#email" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Correo (ACS)</a>
            <a href="#dominio" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Dominio y DNS</a>
            <a href="#env" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Variables de Entorno</a>
            <a href="#accesos" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Accesos</a>
        </div>
    </div>

    {{-- Alerta para desarrolladores --}}
    <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.3);border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;gap:.75rem;align-items:flex-start;">
        <i class="bi bi-info-circle-fill" style="color:#3b82f6;flex-shrink:0;margin-top:.1rem;"></i>
        <div>
            <strong style="color:#3b82f6;">Para desarrolladores</strong>
            <p style="margin:.25rem 0 0;font-size:.875rem;line-height:1.6;color:var(--text-muted);">
                Esta sección documenta la infraestructura de producción de SAEP Platform en Microsoft Azure.
                Toda la infraestructura fue migrada desde DigitalOcean + Ploi en 2025. Actualmente no existe
                servidor dedicado ni panel de control de terceros — todo corre en servicios gestionados de Azure.
            </p>
        </div>
    </div>

    {{-- 1. Stack Tecnológico --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="stack">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">1</span>
                Stack Tecnológico
            </h3>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
            @foreach([
                ['icono'=>'bi-code-slash','color'=>'#f97316','titulo'=>'PHP 8.3','desc'=>'Lenguaje del backend'],
                ['icono'=>'bi-layers-fill','color'=>'#e11d48','titulo'=>'Laravel 11','desc'=>'Framework principal (última LTS)'],
                ['icono'=>'bi-database-fill','color'=>'#0ea5e9','titulo'=>'MySQL 8.0','desc'=>'Base de datos relacional'],
                ['icono'=>'bi-layout-text-window-reverse','color'=>'#8b5cf6','titulo'=>'Blade + Vanilla JS','desc'=>'Vistas del frontend'],
                ['icono'=>'bi-wind','color'=>'#06b6d4','titulo'=>'Tailwind CSS','desc'=>'Estilos + utilidades CSS'],
                ['icono'=>'bi-box-seam','color'=>'#10b981','titulo'=>'Vite','desc'=>'Bundler de assets (JS/CSS)'],
            ] as $t)
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;display:flex;align-items:center;gap:.75rem;">
                <i class="bi {{ $t['icono'] }}" style="font-size:1.5rem;color:{{ $t['color'] }};flex-shrink:0;"></i>
                <div>
                    <strong style="display:block;font-size:.9rem;">{{ $t['titulo'] }}</strong>
                    <span style="font-size:.8rem;color:var(--text-muted);">{{ $t['desc'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 2. Recursos Azure --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="azure">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Recursos Azure (Resource Group: <code style="font-size:.85rem;background:var(--surface-bg);padding:.1rem .4rem;border-radius:.3rem;">saep-rg</code>)
            </h3>
        </div>
        <p style="font-size:.875rem;color:var(--text-muted);margin:0 0 1rem;line-height:1.6;">
            Región principal: <strong>Chile Central</strong>. Suscripción Azure: <code>dc9fbc10-208d-4e64-89d8-c3f176438bb2</code>
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.625rem .75rem;font-weight:600;border-bottom:1px solid var(--surface-border);">Recurso</th>
                        <th style="text-align:left;padding:.625rem .75rem;font-weight:600;border-bottom:1px solid var(--surface-border);">Nombre</th>
                        <th style="text-align:left;padding:.625rem .75rem;font-weight:600;border-bottom:1px solid var(--surface-border);">Tipo</th>
                        <th style="text-align:left;padding:.625rem .75rem;font-weight:600;border-bottom:1px solid var(--surface-border);">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['App Service','saep-app','Web App (Linux)','PHP 8.3, plan ASP-saeprg-81da, región Chile Central'],
                        ['App Service Plan','ASP-saeprg-81da','Hosting Plan','Plan B1, Linux'],
                        ['Base de datos','saep-mysql','MySQL Flexible Server 8.0','Host: saep-mysql.mysql.database.azure.com, SSL requerido'],
                        ['Almacenamiento','saepplatformstorage','Storage Account (Blob)','Contenedor: saep-files, acceso público por blob'],
                        ['Correo (ACS)','saep-communication','Azure Communication Services','SMTP: smtp.azurecomm.net:587/TLS'],
                        ['Email Service','saep-email','Email Communication Service','Vinculado a saep-communication'],
                        ['Identidad','oidc-msi-a544','Managed Identity','Para autenticación entre servicios Azure'],
                    ] as $r)
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.625rem .75rem;font-weight:500;">{{ $r[0] }}</td>
                        <td style="padding:.625rem .75rem;"><code style="background:var(--surface-bg);padding:.15rem .4rem;border-radius:.3rem;font-size:.8rem;">{{ $r[1] }}</code></td>
                        <td style="padding:.625rem .75rem;color:var(--text-muted);">{{ $r[2] }}</td>
                        <td style="padding:.625rem .75rem;color:var(--text-muted);font-size:.8rem;">{{ $r[3] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Deploy Automático --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="deploy">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Deploy Automático (CI/CD)
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El deploy se realiza automáticamente mediante <strong>GitHub Actions</strong> al hacer <code>git push</code> a la rama <code>main</code>.
            No hay pasos manuales — el pipeline hace todo.
        </p>

        <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.25rem;">
            @foreach([
                ['1','Push a main','El desarrollador hace git push origin main'],
                ['2','GitHub Actions','El workflow .github/workflows/ detecta el push y se activa'],
                ['3','composer install','Instala dependencias PHP (sin dev) con autoloader optimizado'],
                ['4','npm ci + vite build','Compila assets JS y CSS para producción'],
                ['5','Deploy a Azure','Sube el build al App Service saep-app vía publish profile'],
                ['6','startup.sh','El servidor ejecuta: config:cache, route:cache, view:cache, migrate --force'],
            ] as [$n,$titulo,$desc])
            <div style="display:flex;align-items:flex-start;gap:.75rem;">
                <span style="background:var(--primary-color);color:#fff;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;margin-top:.1rem;">{{ $n }}</span>
                <div>
                    <strong style="font-size:.875rem;">{{ $titulo }}</strong>
                    <span style="display:block;font-size:.8rem;color:var(--text-muted);">{{ $desc }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;border-left:3px solid #f59e0b;">
            <strong><i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;"></i> Importante para desarrolladores</strong>
            <ul style="margin:.5rem 0 0;padding-left:1.25rem;font-size:.85rem;line-height:1.8;color:var(--text-muted);">
                <li>Nunca subir el archivo <code>.env</code> al repositorio — las variables están en Azure App Settings</li>
                <li>El repositorio es <strong>privado</strong>: <code>github.com/brayanmachero/saep-platform</code></li>
                <li>Las migraciones se ejecutan automáticamente en cada deploy (<code>migrate --force</code>)</li>
                <li>Los assets compilados (<code>public/build/</code>) se suben como parte del deploy</li>
                <li>El servidor web es <strong>Nginx</strong> corriendo en el contenedor Linux del App Service</li>
            </ul>
        </div>
    </div>

    {{-- 4. Almacenamiento de Archivos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="storage">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Almacenamiento de Archivos
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            Todos los archivos subidos por usuarios se almacenan en <strong>Azure Blob Storage</strong>.
            El disco <code>public</code> de Laravel apunta directamente a Azure Blob — no se usa el sistema de archivos local
            del contenedor (que se borraría en cada deploy).
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem;margin-bottom:1rem;">
            @foreach([
                ['bi-person-circle','#3b82f6','Fotos de perfil','fotos_perfil/'],
                ['bi-kanban-fill','#8b5cf6','Adjuntos Kanban','kanban/adjuntos/{id}/'],
                ['bi-ui-checks','#10b981','Adjuntos formularios','respuestas/adjuntos/{id}/'],
                ['bi-shield-exclamation','#ef4444','Evidencias Ley Karin','ley_karin/{id}/'],
            ] as [$icono,$color,$titulo,$ruta])
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;display:flex;align-items:center;gap:.75rem;">
                <i class="bi {{ $icono }}" style="font-size:1.4rem;color:{{ $color }};flex-shrink:0;"></i>
                <div>
                    <strong style="display:block;font-size:.85rem;">{{ $titulo }}</strong>
                    <code style="font-size:.75rem;color:var(--text-muted);">{{ $ruta }}</code>
                </div>
            </div>
            @endforeach
        </div>
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;font-size:.85rem;">
            <strong>URL base de archivos públicos:</strong><br>
            <code style="color:var(--primary-color);">https://saepplatformstorage.blob.core.windows.net/saep-files/{ruta}</code>
        </div>
    </div>

    {{-- 5. Correo (ACS) --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="email">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Correo Transaccional (Azure Communication Services)
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El envío de correos (bienvenida, alertas, Ley Karin, reset de contraseña, etc.) usa <strong>Azure Communication Services</strong>
            como relay SMTP. Se migró desde Resend en abril 2026.
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <tbody>
                    @foreach([
                        ['MAIL_MAILER','smtp','Transporte de correo'],
                        ['MAIL_HOST','smtp.azurecomm.net','Servidor SMTP de ACS'],
                        ['MAIL_PORT','587','Puerto TLS'],
                        ['MAIL_USERNAME','saep-communication','Nombre del recurso ACS'],
                        ['MAIL_FROM_ADDRESS','notificaciones@saep.cl','Remitente visible'],
                        ['MAIL_FROM_NAME','SAEP notificaciones','Nombre visible'],
                    ] as [$var,$val,$desc])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;"><code style="font-size:.8rem;background:var(--surface-bg);padding:.15rem .4rem;border-radius:.3rem;">{{ $var }}</code></td>
                        <td style="padding:.5rem .75rem;font-size:.8rem;color:#10b981;font-weight:500;">{{ $val }}</td>
                        <td style="padding:.5rem .75rem;font-size:.8rem;color:var(--text-muted);">{{ $desc }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:.5rem;padding:.875rem;margin-top:1rem;font-size:.85rem;">
            <strong><i class="bi bi-clock-history" style="color:#f59e0b;"></i> Pendiente:</strong>
            El dominio <code>saep.cl</code> requiere verificación DNS en ACS para que los correos se envíen desde
            <code>notificaciones@saep.cl</code>. Mientras tanto, el SMTP funciona pero el remitente real es el dominio
            de ACS. Ver sección de Dominio y DNS.
        </div>
    </div>

    {{-- 6. Dominio y DNS --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="dominio">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Dominio y DNS
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;">
            El dominio <strong>saep.cl</strong> está registrado en <strong>NIC Chile</strong> y gestiona los registros DNS
            en el proveedor externo del registro. Microsoft 365 también usa este dominio para correo corporativo.
        </p>

        <strong style="font-size:.875rem;display:block;margin-bottom:.5rem;">Registros DNS pendientes de agregar:</strong>
        <div style="overflow-x:auto;margin-bottom:1rem;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Tipo</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Nombre</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Valor</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Propósito</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['TXT','@ (raíz)','ms-domain-verification=543803ce-3238-481c-b785-750cdd6f0ad7','Verificación dominio ACS'],
                        ['CNAME','selector1-azurecomm-prod-net._domainkey','selector1-azurecomm-prod-net._domainkey.azurecomm.net','DKIM firma email'],
                        ['CNAME','selector2-azurecomm-prod-net._domainkey','selector2-azurecomm-prod-net._domainkey.azurecomm.net','DKIM2 firma email'],
                        ['CNAME','app','saep-app-gah2azercshxb0ey.chilecentral-01.azurewebsites.net','URL app.saep.cl'],
                    ] as [$tipo,$nombre,$valor,$prop])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;"><span style="background:rgba(59,130,246,0.1);color:#3b82f6;padding:.15rem .4rem;border-radius:.25rem;font-weight:600;">{{ $tipo }}</span></td>
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;">{{ $nombre }}</code></td>
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;color:#10b981;">{{ $valor }}</code></td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">{{ $prop }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;font-size:.85rem;">
            <strong>URL actual de la aplicación:</strong><br>
            <code>https://saep-app-gah2azercshxb0ey.chilecentral-01.azurewebsites.net</code><br><br>
            <strong>URL objetivo (pendiente DNS):</strong><br>
            <code style="color:var(--primary-color);">https://app.saep.cl</code>
        </div>
    </div>

    {{-- 7. Variables de Entorno --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="env">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">7</span>
                Variables de Entorno
            </h3>
        </div>
        <p style="line-height:1.6;margin:0 0 1rem;font-size:.875rem;">
            Todas las variables están configuradas en <strong>Azure App Service → Configuración → Variables de entorno</strong>.
            No existe archivo <code>.env</code> en el servidor. Para desarrollo local, copiar <code>.env.example</code> y completar los valores.
        </p>
        <div style="display:flex;flex-direction:column;gap:.5rem;">
            @foreach([
                ['App','APP_KEY, APP_URL, APP_ENV, APP_DEBUG','Configuración base de Laravel'],
                ['Base de datos','DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, MYSQL_ATTR_SSL_CA','Conexión MySQL Flexible Server con SSL'],
                ['Correo','MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS, MAIL_FROM_NAME','SMTP Azure Communication Services'],
                ['Almacenamiento','AZURE_STORAGE_NAME, AZURE_STORAGE_KEY, AZURE_STORAGE_CONTAINER, AZURE_STORAGE_URL, FILESYSTEM_DISK','Azure Blob Storage'],
                ['Autenticación Google','GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI','OAuth Google via Laravel Socialite'],
                ['Google APIs','GOOGLE_APPLICATION_CREDENTIALS','Ruta credenciales Google API (Drive, etc.)'],
                ['Sesión/Caché','SESSION_DRIVER, CACHE_STORE, QUEUE_CONNECTION','Drivers de sesión y caché'],
            ] as [$grupo,$vars,$desc])
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;">
                <strong style="font-size:.85rem;display:block;margin-bottom:.25rem;">{{ $grupo }}</strong>
                <code style="font-size:.75rem;color:var(--primary-color);line-height:1.8;">{{ $vars }}</code>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);margin-top:.25rem;">{{ $desc }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 8. Accesos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="accesos">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">8</span>
                Accesos para Desarrolladores
            </h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            @foreach([
                ['bi-github','#1f2937','Repositorio GitHub','github.com/brayanmachero/saep-platform','Rama principal: main. Requiere acceso al repo.'],
                ['bi-cloud-fill','#0078d4','Portal Azure','portal.azure.com','Resource Group: saep-rg. Requiere cuenta con acceso a la suscripción.'],
                ['bi-envelope-fill','#0078d4','Azure Communication Services','portal.azure.com','Recurso: saep-communication. Gestión de SMTP y dominios.'],
                ['bi-hdd-fill','#0078d4','Azure Blob Storage','portal.azure.com','Cuenta: saepplatformstorage, contenedor: saep-files.'],
                ['bi-terminal-fill','#1f2937','Azure CLI','Instalación local','az login → az webapp → az storage (grupo: saep-rg)'],
            ] as [$icono,$color,$titulo,$url,$desc])
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;display:flex;align-items:flex-start;gap:.75rem;">
                <i class="bi {{ $icono }}" style="font-size:1.3rem;color:{{ $color }};flex-shrink:0;margin-top:.1rem;"></i>
                <div>
                    <strong style="font-size:.875rem;display:block;">{{ $titulo }}</strong>
                    <code style="font-size:.75rem;color:var(--primary-color);">{{ $url }}</code>
                    <span style="display:block;font-size:.8rem;color:var(--text-muted);margin-top:.2rem;">{{ $desc }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
