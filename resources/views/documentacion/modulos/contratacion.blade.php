@extends('layouts.app')

@section('title', 'Documentación — Contratación RRHH')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-person-badge-fill" style="color:var(--primary-color);"></i>
                Contratación RRHH
            </h2>
            <p class="page-subheading">Portal de postulación pública, panel administrativo RRHH y sincronización con SharePoint</p>
        </div>
        <a href="{{ route('documentacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Documentación
        </a>
    </div>

    {{-- Navegación interna --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <strong style="font-size:.85rem;color:var(--text-muted);display:block;margin-bottom:.5rem;">Contenido</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            <a href="#flujo" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Flujo de postulación</a>
            <a href="#portal" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Portal público</a>
            <a href="#admin" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Panel RRHH</a>
            <a href="#storage" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Almacenamiento</a>
            <a href="#sharepoint" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">SharePoint</a>
            <a href="#pdf" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Ficha PDF</a>
            <a href="#estados" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Estados</a>
            <a href="#emails" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Correos</a>
        </div>
    </div>

    {{-- Versión / badge --}}
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:center;">
        <span class="badge success">v1.1 — Activo</span>
        <span style="font-size:.8rem;color:var(--text-muted);">Acceso admin: <code>modulo:contratacion</code></span>
        <span style="font-size:.8rem;color:var(--text-muted);">Portal público: <code>/postulacion</code> (sin autenticación interna)</span>
    </div>

    {{-- 1. Flujo general --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="flujo">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">1</span>
                Flujo General de Postulación
            </h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:.5rem;max-width:600px;">
            @foreach([
                ['bi-google','#ea4335','1. Postulante accede a /postulacion y se autentica con Google OAuth2'],
                ['bi-person-fill-check','#3b82f6','2. Completa el formulario: nombre, RUT y documentos requeridos'],
                ['bi-cloud-upload-fill','#10b981','3. Documentos se suben a Azure Blob Storage (disco público)'],
                ['bi-file-earmark-person-fill','#8b5cf6','4. Se genera la ficha PDF consolidada (DomPDF + FPDI/Imagick)'],
                ['bi-folder-symlink-fill','#f59e0b','5. Ficha y documentos se suben a SharePoint (sitio RRH)'],
                ['bi-envelope-fill','#6366f1','6. Correo de acuse recibo al postulante + notificación a RRHH'],
                ['bi-check-circle-fill','#10b981','7. RRHH revisa y cambia el estado del postulante desde el panel admin'],
            ] as [$icono,$color,$texto])
            <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.625rem;background:var(--surface-bg);border-radius:.5rem;">
                <i class="bi {{ $icono }}" style="color:{{ $color }};font-size:1.1rem;flex-shrink:0;margin-top:.1rem;"></i>
                <span style="font-size:.875rem;line-height:1.5;">{{ $texto }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 2. Portal público --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="portal">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Portal Público del Postulante
            </h3>
        </div>

        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            El portal es accesible públicamente en <code>/postulacion</code> sin cuenta SAEP. Usa
            <strong>Google OAuth2</strong> para identificar al postulante (no crea usuario en el sistema SAEP).
        </p>

        <div style="overflow-x:auto;margin-bottom:1rem;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Ruta</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['GET /postulacion','Página de inicio del portal (sin auth)'],
                        ['GET /postulacion/auth/google','Redirige a Google OAuth2'],
                        ['GET /postulacion/auth/callback','Callback Google — crea sesión php'],
                        ['GET /postulacion/formulario','Formulario de postulación (requiere sesión Google)'],
                        ['POST /postulacion/enviar','Procesa el formulario y crea el registro'],
                        ['GET /postulacion/confirmacion/{folio}','Página de éxito con folio'],
                    ] as [$ruta,$accion])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;">{{ $ruta }}</code></td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">{{ $accion }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <strong style="font-size:.875rem;display:block;margin-bottom:.5rem;">Documentos aceptados:</strong>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.625rem;">
            @foreach([
                ['Carnet de Identidad (Frontal)','Requerido','bi-person-vcard'],
                ['Carnet de Identidad (Reverso)','Requerido','bi-person-vcard-fill'],
                ['Certificado AFP','Requerido','bi-file-earmark-text'],
                ['Certificado FONASA','Requerido','bi-file-earmark-medical'],
                ['Licencia de Conducir','Opcional','bi-card-heading'],
            ] as [$nombre,$req,$icono])
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.75rem;display:flex;align-items:center;gap:.6rem;">
                <i class="bi {{ $icono }}" style="font-size:1.2rem;color:var(--primary-color);flex-shrink:0;"></i>
                <div>
                    <strong style="font-size:.8rem;display:block;">{{ $nombre }}</strong>
                    <span style="font-size:.75rem;color:{{ $req === 'Requerido' ? '#10b981' : '#f59e0b' }};">{{ $req }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 3. Panel Admin --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="admin">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Panel Administrativo RRHH
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            Acceso restringido por middleware <code>modulo:contratacion</code>. Ruta base: <code>/contratacion</code>.
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Ruta</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Función</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['GET /contratacion','Lista paginada de postulantes con filtros y estadísticas'],
                        ['GET /contratacion/{id}','Detalle del postulante con documentos y folio'],
                        ['PATCH /contratacion/{id}','Cambiar estado + observaciones internas'],
                        ['GET /contratacion/{id}/zip','Descargar ZIP con todos los documentos'],
                        ['GET /contratacion/{id}/doc/{campo}','Descargar documento individual'],
                        ['GET /contratacion/{id}/ficha-pdf','Descargar ficha PDF consolidada'],
                        ['POST /contratacion/{id}/resincronizar','Re-subir ficha y documentos a SharePoint'],
                        ['GET /contratacion/exportar/excel','Exportar todos los postulantes a Excel'],
                        ['GET /contratacion/create','Formulario ingreso manual'],
                        ['POST /contratacion/store-manual','Guardar postulante ingresado manualmente'],
                        ['GET /contratacion/configuracion/emails','Config emails notificación RRHH'],
                    ] as [$ruta,$funcion])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;">{{ $ruta }}</code></td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">{{ $funcion }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 4. Almacenamiento --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="storage">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Almacenamiento de Archivos (Azure Blob)
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            Los documentos del postulante se almacenan en el disco <code>public</code> (Azure Blob Storage, contenedor <code>saep-files</code>).
        </p>
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;font-family:monospace;font-size:.8rem;line-height:2;overflow-x:auto;margin-bottom:1rem;">
            <span style="color:var(--text-muted);"># Azure Blob → saep-files/</span><br>
            contratacion/<br>
            &nbsp;&nbsp;└── {rut_sin_formato}/<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── carnet_frontal.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── carnet_reverso.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── certificado_afp.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── certificado_fonasa.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└── licencia_conducir.pdf  <span style="color:var(--text-muted);">(si aplica)</span>
        </div>
        <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.25);border-radius:.5rem;padding:.875rem;font-size:.85rem;">
            <strong><i class="bi bi-info-circle" style="color:#10b981;"></i> Acceso a archivos:</strong>
            <code style="display:block;margin-top:.4rem;color:var(--primary-color);">Storage::disk('public')->url($ruta)</code>
            <span style="display:block;margin-top:.3rem;color:var(--text-muted);">Genera: <code>https://saepplatformstorage.blob.core.windows.net/saep-files/{ruta}</code></span>
        </div>
    </div>

    {{-- 5. SharePoint --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="sharepoint">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Sincronización con SharePoint
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            Los documentos y la ficha consolidada se sincronizan al sitio SharePoint <code>RRH</code> mediante <code>OneDriveService</code>
            usando Microsoft Graph API con Client Credentials OAuth2.
        </p>

        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;font-family:monospace;font-size:.8rem;line-height:2;overflow-x:auto;margin-bottom:1rem;">
            <span style="color:var(--text-muted);"># SharePoint → sitio: RRH</span><br>
            Postulantes Documents/<br>
            &nbsp;&nbsp;└── {rut} - {nombre}/<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── carnet_frontal.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── carnet_reverso.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── certificado_afp.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── certificado_fonasa.pdf<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── licencia_conducir.pdf  <span style="color:var(--text-muted);">(si aplica)</span><br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└── <strong style="color:var(--primary-color);">{RUT} - FICHA {NNN}.pdf</strong>  <span style="color:var(--text-muted);">← ficha consolidada</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;">
                <strong style="font-size:.85rem;display:block;margin-bottom:.4rem;">Nombre de la ficha</strong>
                <code style="font-size:.8rem;color:var(--primary-color);">26.173.456-K - FICHA 001.pdf</code>
                <span style="display:block;font-size:.75rem;color:var(--text-muted);margin-top:.3rem;">Formato: <code>{RUT_formateado} - FICHA {NNN}</code></span>
            </div>
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;">
                <strong style="font-size:.85rem;display:block;margin-bottom:.4rem;">Resincronización</strong>
                <span style="font-size:.8rem;line-height:1.5;color:var(--text-muted);">Botón "Re-sincronizar SharePoint" en la vista de detalle del postulante. Regenera la ficha y la sube con el nombre actualizado.</span>
            </div>
        </div>

        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:.5rem;padding:.875rem;font-size:.85rem;">
            <strong><i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;"></i> Variables requeridas:</strong>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.4rem;">
                @foreach(['MSGRAPH_TENANT_ID','MSGRAPH_CLIENT_ID','MSGRAPH_CLIENT_SECRET','MSGRAPH_SHAREPOINT_HOST','CONTRATACION_SHAREPOINT_SITE','CONTRATACION_SHAREPOINT_FOLDER'] as $var)
                <code style="font-size:.75rem;background:var(--surface-bg);padding:.15rem .4rem;border-radius:.25rem;">{{ $var }}</code>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 6. Ficha PDF --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="pdf">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Generación de la Ficha PDF Consolidada
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            La ficha consolida los datos del postulante + todos sus documentos en un único PDF. Se genera con <strong>DomPDF</strong>
            para la portada y <strong>FPDI + Imagick/Ghostscript</strong> para mergear los documentos PDFs del postulante.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;">
            @foreach([
                ['bi-1-circle-fill','#3b82f6','DomPDF genera la portada con datos personales e índice de documentos (PDF 1.3/1.4)'],
                ['bi-2-circle-fill','#8b5cf6','FPDI intenta importar cada documento PDF del postulante (funciona ≤ PDF 1.4)'],
                ['bi-3-circle-fill','#f59e0b','Si FPDI falla (PDF 1.5+), Imagick/Ghostscript renderiza cada página como PNG y la incrusta'],
                ['bi-4-circle-fill','#10b981','Si ambos fallan, el documento se omite con Log::warning (sin error crítico)'],
            ] as [$icono,$color,$texto])
            <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.625rem;background:var(--surface-bg);border-radius:.5rem;">
                <i class="bi {{ $icono }}" style="color:{{ $color }};font-size:1.2rem;flex-shrink:0;margin-top:.05rem;"></i>
                <span style="font-size:.875rem;line-height:1.5;">{{ $texto }}</span>
            </div>
            @endforeach
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Formato PDF</th>
                        <th style="text-align:center;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">FPDI</th>
                        <th style="text-align:center;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Imagick/GS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['PDF 1.3 / 1.4','✅','✅'],
                        ['PDF 1.5 con /ObjStm','❌','✅'],
                        ['PDF 1.6 / 1.7+','❌','✅'],
                        ['PDF con contraseña','❌','❌'],
                    ] as [$fmt,$fpdi,$imagick])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;">{{ $fmt }}</td>
                        <td style="padding:.5rem .75rem;text-align:center;">{{ $fpdi }}</td>
                        <td style="padding:.5rem .75rem;text-align:center;">{{ $imagick }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 7. Estados --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="estados">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">7</span>
                Estados del Postulante
            </h3>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">
            @foreach([
                ['pendiente','#f59e0b','Recién creado, aún no revisado por RRHH'],
                ['en_revision','#3b82f6','RRHH está revisando los documentos'],
                ['aprobado','#10b981','Postulante aceptado'],
                ['rechazado','#ef4444','Postulante no aceptado'],
            ] as [$estado,$color,$desc])
            <div style="display:flex;align-items:center;gap:.625rem;">
                <span style="background:{{ $color }};width:12px;height:12px;border-radius:50%;flex-shrink:0;"></span>
                <div>
                    <strong style="font-size:.875rem;display:block;">{{ ucfirst(str_replace('_',' ',$estado)) }}</strong>
                    <span style="font-size:.8rem;color:var(--text-muted);">{{ $desc }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <p style="margin:1rem 0 0;font-size:.85rem;color:var(--text-muted);">
            El cambio de estado se realiza desde la vista de detalle del postulante. Se puede agregar observaciones internas. El postulante no recibe correo al cambiar el estado (solo se notifica internamente en RRHH).
        </p>
    </div>

    {{-- 8. Correos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="emails">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">8</span>
                Correos del Módulo
            </h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:.625rem;">
            @foreach([
                ['ContratacionAcuseReciboMail','Al postulante','Se envía automáticamente al crear el registro (portal o ingreso manual)','bi-person-fill'],
                ['ContratacionNuevoPostulanteMail','A emails RRHH configurados','Notifica a RRHH que hay un nuevo postulante. Configurar en /contratacion/configuracion/emails','bi-bell-fill'],
            ] as [$mailable,$dest,$desc,$icono])
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:.875rem;display:flex;align-items:flex-start;gap:.75rem;">
                <i class="bi {{ $icono }}" style="color:var(--primary-color);font-size:1.1rem;flex-shrink:0;margin-top:.1rem;"></i>
                <div>
                    <code style="font-size:.8rem;font-weight:600;">{{ $mailable }}</code>
                    <span style="display:block;font-size:.8rem;color:#10b981;margin-top:.15rem;">→ {{ $dest }}</span>
                    <span style="display:block;font-size:.8rem;color:var(--text-muted);margin-top:.15rem;">{{ $desc }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.25);border-radius:.5rem;padding:.875rem;margin-top:1rem;font-size:.85rem;">
            <strong><i class="bi bi-envelope-check" style="color:#3b82f6;"></i> Monitor de correos:</strong>
            <span style="color:var(--text-muted);"> Todos los envíos quedan registrados en el <a href="{{ route('documentacion.show', 'monitor-correos') }}">Monitor de Correos</a>.</span>
        </div>
    </div>

</div>
@endsection
