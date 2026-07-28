<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formulario de Documentación de Ingreso — SAEP</title>
    <link rel="icon" href="{{ asset('brand/wp/saep_favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }

        /* ── Top bar ── */
        .topbar {
            background: linear-gradient(90deg, #0b1437 0%, #1a237e 100%);
            padding: .9rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar__brand { display: flex; align-items: center; gap: .75rem; }
        .topbar__brand img { height: 32px; filter: brightness(0) invert(1); }
        .topbar__brand span { color: rgba(255,255,255,.7); font-size: .85rem; }
        .topbar__user {
            display: flex; align-items: center; gap: .6rem;
            color: rgba(255,255,255,.8); font-size: .85rem;
        }
        .topbar__user img {
            width: 32px; height: 32px; border-radius: 50%;
            border: 2px solid rgba(255,255,255,.3);
        }
        .btn-logout {
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
            color: rgba(255,255,255,.8); padding: .35rem .8rem;
            border-radius: 8px; font-size: .78rem; cursor: pointer;
            text-decoration: none; transition: background .2s; margin-left: .5rem;
        }
        .btn-logout:hover { background: rgba(255,255,255,.2); color: #fff; }

        /* ── Page ── */
        .page { max-width: 860px; margin: 2rem auto; padding: 0 1rem 4rem; }

        /* Steps */
        .steps-bar {
            display: flex; justify-content: center; align-items: center; gap: .5rem;
            margin-bottom: 2rem;
        }
        .step { display: flex; flex-direction: column; align-items: center; gap: .3rem; font-size: .75rem; color: #94a3b8; }
        .step__dot {
            width: 32px; height: 32px; border-radius: 50%;
            background: #e2e8f0; display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #94a3b8; font-size: .8rem;
        }
        .step.active .step__dot { background: #0ea5e9; color: #fff; }
        .step.active { color: #0ea5e9; font-weight: 600; }
        .step.done .step__dot { background: #22c55e; color: #fff; }
        .step-line { flex: 1; height: 2px; background: #e2e8f0; max-width: 60px; margin-bottom: 18px; }

        /* Card */
        .card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0,0,0,.06);
            padding: 2rem 2.5rem; margin-bottom: 1.5rem;
        }
        .card__title {
            font-size: 1.1rem; font-weight: 700; color: #0f1b4c;
            margin-bottom: 1.5rem; padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: .5rem;
        }
        .card__title i { color: #0ea5e9; }

        /* Postulante existente banner */
        .postulante-banner {
            background: #ecfdf5; border: 1px solid #86efac;
            border-radius: 12px; padding: 1rem 1.25rem;
            display: flex; align-items: flex-start; gap: .75rem;
            margin-bottom: 1.5rem; font-size: .875rem; color: #166534;
        }
        .postulante-banner i { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }

        /* Formulario */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block; font-size: .85rem; font-weight: 600;
            color: #374151; margin-bottom: .4rem;
        }
        .form-group label .required { color: #ef4444; margin-left: 2px; }
        .form-control {
            width: 100%; padding: .7rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: .9rem; color: #1e293b;
            transition: border-color .2s, box-shadow .2s;
            background: #fff;
        }
        .form-control:focus {
            outline: none; border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,.15);
        }
        .form-control.is-invalid { border-color: #ef4444; }
        .invalid-feedback { font-size: .78rem; color: #ef4444; margin-top: .3rem; }
        .form-hint { font-size: .78rem; color: #94a3b8; margin-top: .3rem; }

        /* File upload */
        .file-group { margin-bottom: 1.5rem; }
        .file-label {
            display: flex; align-items: center; justify-content: space-between;
            font-size: .85rem; font-weight: 600; color: #374151;
            margin-bottom: .4rem;
        }
        .file-label .badge-opt {
            font-size: .7rem; padding: .2rem .5rem;
            background: #f0f7ff; color: #0369a1;
            border-radius: 6px; font-weight: 500;
        }
        .file-label .badge-req {
            font-size: .7rem; padding: .2rem .5rem;
            background: #fef2f2; color: #dc2626;
            border-radius: 6px; font-weight: 500;
        }
        .file-drop {
            border: 2px dashed #e2e8f0; border-radius: 12px;
            padding: 1.25rem; text-align: center; cursor: pointer;
            transition: all .2s; position: relative; background: #fafbfc;
        }
        .file-drop:hover, .file-drop.dragover { border-color: #0ea5e9; background: #f0f9ff; }
        .file-drop input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .file-drop__icon { font-size: 1.6rem; color: #94a3b8; margin-bottom: .3rem; }
        .file-drop__text { font-size: .82rem; color: #6b7280; }
        .file-drop__text strong { color: #0ea5e9; }
        .file-preview {
            display: flex; align-items: center; justify-content: space-between;
            padding: .6rem .85rem; background: #f0f9ff;
            border: 1px solid #bae6fd; border-radius: 8px; margin-top: .5rem;
            font-size: .82rem; color: #0369a1;
        }
        .file-preview.existing {
            background: #f0fdf4; border-color: #86efac; color: #166534;
        }
        .file-preview.uploading {
            background: #eff6ff; border-color: #93c5fd; color: #1d4ed8;
        }
        .file-preview.uploaded {
            background: #ecfdf5; border-color: #86efac; color: #166534;
        }
        .file-preview.error {
            background: #fef2f2; border-color: #fecaca; color: #991b1b;
        }
        .file-preview .name { display: flex; align-items: center; gap: .4rem; }
        .file-preview .actions { display: flex; align-items: center; gap: .4rem; flex-shrink: 0; }
        .file-preview .retry {
            background: #fff; border: 1px solid currentColor; cursor: pointer;
            color: inherit; font-size: .75rem; border-radius: 6px; padding: .2rem .45rem;
        }
        .file-preview .remove {
            background: none; border: none; cursor: pointer;
            color: #ef4444; font-size: .9rem; padding: 0 .2rem;
        }

        /* Row 2 cols */
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }

        /* Submit */
        .submit-bar {
            display: flex; justify-content: flex-end; gap: 1rem;
            padding-top: 1rem; margin-top: .5rem;
        }
        .btn-primary {
            background: linear-gradient(90deg, #0ea5e9, #0369a1);
            color: #fff; padding: .8rem 2rem;
            border-radius: 10px; border: none; font-size: .95rem;
            font-weight: 700; cursor: pointer; transition: opacity .2s;
            display: flex; align-items: center; gap: .5rem;
        }
        .btn-primary:hover { opacity: .9; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }

        /* Alert */
        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: .85rem 1rem; margin-bottom: 1.5rem;
            font-size: .85rem; color: #991b1b;
        }
        .alert-success {
            background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px;
            padding: .85rem 1rem; margin-bottom: 1.5rem;
            font-size: .85rem; color: #166534;
        }
        .privacy-box {
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            padding: 1rem 1.15rem;
            font-size: .84rem;
            color: #334155;
        }
        .privacy-box label {
            display: flex;
            gap: .65rem;
            align-items: flex-start;
            cursor: pointer;
            line-height: 1.45;
        }
        .privacy-box input { margin-top: .2rem; flex-shrink: 0; }
        .privacy-box a { color: #0369a1; font-weight: 700; }

        @media (max-width: 680px) {
            .page { padding: 0 .5rem 3rem; }
            .card { padding: 1.5rem 1rem; }
            .row-2 { grid-template-columns: 1fr; gap: 0; }
            .topbar { padding: .75rem 1rem; }
            .topbar__brand span { display: none; }
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <div class="topbar__brand">
        <img src="{{ asset('brand/wp/Logo_Saep.svg') }}" alt="SAEP">
        <span>Portal de Contratación</span>
    </div>
    <div class="topbar__user">
        @if($googleUser['avatar'])
        <img src="{{ $googleUser['avatar'] }}" alt="">
        @endif
        <span>{{ $googleUser['name'] }}</span>
        <form method="POST" action="{{ route('contratacion-publico.logout') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Salir
            </button>
        </form>
    </div>
</div>

<div class="page">
    <!-- Steps -->
    <div class="steps-bar">
        <div class="step done">
            <div class="step__dot"><i class="bi bi-check"></i></div>
            <span>Acceso</span>
        </div>
        <div class="step-line"></div>
        <div class="step active">
            <div class="step__dot">2</div>
            <span>Datos</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step__dot">3</div>
            <span>Listo</span>
        </div>
    </div>

    @if($errors->any())
    <div class="alert-error">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> Corrige los errores:</strong>
        <ul style="margin:.5rem 0 0 1rem;padding:0;">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($postulante)
    <div class="postulante-banner">
        <i class="bi bi-patch-check-fill"></i>
        <div>
            <strong>Ya tienes una postulación registrada ({{ $postulante->folio }})</strong><br>
            Puedes actualizar tus datos o reemplazar cualquier documento usando el formulario.
            Solo los campos que completes serán modificados.
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('contratacion-publico.store') }}" enctype="multipart/form-data" id="form-postulacion">
        @csrf

        <!-- Datos personales -->
        <div class="card">
            <div class="card__title">
                <i class="bi bi-person-lines-fill"></i> Datos Personales
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Nombre completo <span class="required">*</span></label>
                    <input type="text" name="nombre" class="form-control {{ $errors->has('nombre') ? 'is-invalid' : '' }}"
                        value="{{ old('nombre', $postulante->nombre ?? '') }}"
                        placeholder="Ej: Juan Pérez González" required>
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>RUT <span class="required">*</span></label>
                    <input type="text" name="rut" id="rut" class="form-control {{ $errors->has('rut') ? 'is-invalid' : '' }}"
                        value="{{ old('rut', $postulante->rut ?? '') }}"
                        placeholder="Ej: 12.345.678-9" maxlength="12" required>
                    @error('rut') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-hint">Se formatará automáticamente (XX.XXX.XXX-X)</div>
                </div>
            </div>

            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="text" class="form-control" value="{{ $googleUser['email'] }}" disabled>
                <div class="form-hint">Correo verificado con Google — no modificable</div>
            </div>
        </div>

        <!-- Documentos -->
        <div class="card">
            <div class="card__title">
                <i class="bi bi-file-earmark-arrow-up-fill"></i> Documentos Requeridos
            </div>
            <p style="font-size:.85rem;color:#6b7280;margin-bottom:1rem;">
                Acepta archivos <strong>JPG, PNG o PDF</strong> de máximo <strong>100 MB</strong> cada uno.
                Los documentos marcados con <span style="color:#ef4444;font-weight:600;">Requerido</span>
                son obligatorios para completar tu postulación.
            </p>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.5rem;font-size:.82rem;color:#92400e;display:flex;gap:.6rem;align-items:flex-start;">
                <span style="font-size:1rem;flex-shrink:0;">📱</span>
                <span><strong>Desde el celular:</strong> si la foto de cámara es muy pesada y el dispositivo no puede procesarla, <strong>usa PDF</strong> o toma la foto en <strong>menor resolución</strong> desde la configuración de tu cámara. Si Chrome indica que el archivo cambió o no se puede leer, vuelve a seleccionarlo desde <strong>Galería</strong> o <strong>Archivos/Descargas</strong> y evita moverlo o editarlo antes de enviar.</span>
            </div>

            <div class="row-2">
                @php
                    $docsCampos = [
                        ['campo' => 'carnet_frontal',     'label' => 'Carnet de Identidad (Frontal)',  'req' => true],
                        ['campo' => 'carnet_reverso',     'label' => 'Carnet de Identidad (Reverso)',  'req' => true],
                        ['campo' => 'certificado_afp',    'label' => 'Certificado de Afiliación AFP',  'req' => true],
                        ['campo' => 'certificado_fonasa', 'label' => 'Certificado FONASA',             'req' => true],
                    ];
                @endphp

                @foreach($docsCampos as $doc)
                @php
                    $tempDoc = $tempUploadsByField[$doc['campo']] ?? null;
                    $tempToken = old("uploaded_documents.{$doc['campo']}", $tempDoc['token'] ?? '');
                    $tempName = $tempDoc['original_name'] ?? 'Documento listo para enviar';
                    $tempSize = isset($tempDoc['size']) ? number_format($tempDoc['size'] / 1048576, 1, ',', '.') . ' MB' : null;
                @endphp
                <div class="file-group">
                    <div class="file-label">
                        {{ $doc['label'] }}
                        @if($doc['req'])
                            <span class="badge-req">Requerido</span>
                        @endif
                    </div>
                    <div class="file-drop" id="drop-{{ $doc['campo'] }}">
                        <input type="file" name="{{ $doc['campo'] }}" id="{{ $doc['campo'] }}"
                            accept=".jpg,.jpeg,.png,.pdf"
                            data-campo="{{ $doc['campo'] }}"
                            data-required="{{ $doc['req'] ? '1' : '0' }}"
                            data-existing="{{ $postulante && $postulante->{$doc['campo']} ? '1' : '0' }}"
                            onchange="mostrarArchivo(this)">
                        <input type="hidden" name="uploaded_documents[{{ $doc['campo'] }}]" id="uploaded-{{ $doc['campo'] }}" value="{{ $tempToken }}">
                        <div class="file-drop__icon"><i class="bi bi-cloud-upload"></i></div>
                        <div class="file-drop__text">
                            <strong>Seleccionar archivo</strong> o arrastrar aquí<br>
                            <span>JPG, PNG, PDF · Máx. 100 MB</span>
                        </div>
                    </div>
                    @if($postulante && $postulante->{$doc['campo']} && !$tempToken)
                    <div class="file-preview existing" id="preview-existing-{{ $doc['campo'] }}">
                        <div class="name"><i class="bi bi-check-circle-fill"></i> Documento ya subido</div>
                        <small>Se reemplazará si subes uno nuevo</small>
                    </div>
                    @endif
                    <div id="preview-{{ $doc['campo'] }}" style="{{ $tempToken ? 'display:flex;' : 'display:none;' }}" class="file-preview {{ $tempToken ? 'uploaded' : '' }}">
                        <div class="name"><i class="bi bi-file-earmark-check"></i> <span>{{ $tempToken ? ('Subido: ' . $tempName . ($tempSize ? ' (' . $tempSize . ')' : '')) : '' }}</span></div>
                        <div class="actions">
                            <button type="button" class="retry" style="display:none;" onclick="reintentarArchivo('{{ $doc['campo'] }}')">
                                Reintentar
                            </button>
                            <button type="button" class="remove" onclick="quitarArchivo('{{ $doc['campo'] }}')">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    @error($doc['campo']) <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
                </div>
                @endforeach
            </div>

            <!-- Licencia de Conducir: Frontal + Reverso (opcional, pero ambos si se sube uno) -->
            <div style="grid-column:1/-1;">
                <div style="font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-car-front-fill" style="color:#f59e0b;"></i>
                    Licencia de Conducir
                    <span class="badge-opt">Opcional</span>
                    <span style="font-size:.72rem;color:#6b7280;font-weight:400;margin-left:.25rem;">— Si subes uno de los dos lados, debes subir el otro también.</span>
                </div>
                <div class="row-2">
                    @foreach([
                        ['campo' => 'licencia_conducir_frontal', 'label' => 'Licencia (Frontal)'],
                        ['campo' => 'licencia_conducir_reverso', 'label' => 'Licencia (Reverso)'],
                    ] as $lic)
                    @php
                        $tempLic = $tempUploadsByField[$lic['campo']] ?? null;
                        $tempLicToken = old("uploaded_documents.{$lic['campo']}", $tempLic['token'] ?? '');
                        $tempLicName = $tempLic['original_name'] ?? 'Documento listo para enviar';
                        $tempLicSize = isset($tempLic['size']) ? number_format($tempLic['size'] / 1048576, 1, ',', '.') . ' MB' : null;
                    @endphp
                    <div class="file-group" style="margin-bottom:0;">
                        <div class="file-label" id="label-{{ $lic['campo'] }}">
                            {{ $lic['label'] }}
                            <span class="badge-opt" id="badge-{{ $lic['campo'] }}">Opcional</span>
                        </div>
                        <div class="file-drop" id="drop-{{ $lic['campo'] }}">
                            <input type="file" name="{{ $lic['campo'] }}" id="{{ $lic['campo'] }}"
                                accept=".jpg,.jpeg,.png,.pdf"
                                data-campo="{{ $lic['campo'] }}"
                                data-required="0"
                                data-existing="{{ $postulante && $postulante->{$lic['campo']} ? '1' : '0' }}"
                                onchange="mostrarArchivo(this); actualizarBadgeLicencia();">
                            <input type="hidden" name="uploaded_documents[{{ $lic['campo'] }}]" id="uploaded-{{ $lic['campo'] }}" value="{{ $tempLicToken }}">
                            <div class="file-drop__icon"><i class="bi bi-cloud-upload"></i></div>
                            <div class="file-drop__text">
                                <strong>Seleccionar archivo</strong> o arrastrar aquí<br>
                                <span>JPG, PNG, PDF · Máx. 100 MB</span>
                            </div>
                        </div>
                        @if($postulante && $postulante->{$lic['campo']} && !$tempLicToken)
                        <div class="file-preview existing" id="preview-existing-{{ $lic['campo'] }}">
                            <div class="name"><i class="bi bi-check-circle-fill"></i> Documento ya subido</div>
                            <small>Se reemplazará si subes uno nuevo</small>
                        </div>
                        @endif
                        <div id="preview-{{ $lic['campo'] }}" style="{{ $tempLicToken ? 'display:flex;' : 'display:none;' }}" class="file-preview {{ $tempLicToken ? 'uploaded' : '' }}">
                            <div class="name"><i class="bi bi-file-earmark-check"></i> <span>{{ $tempLicToken ? ('Subido: ' . $tempLicName . ($tempLicSize ? ' (' . $tempLicSize . ')' : '')) : '' }}</span></div>
                            <div class="actions">
                                <button type="button" class="retry" style="display:none;" onclick="reintentarArchivo('{{ $lic['campo'] }}')">
                                    Reintentar
                                </button>
                                <button type="button" class="remove" onclick="quitarArchivo('{{ $lic['campo'] }}'); actualizarBadgeLicencia();">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </div>
                        </div>
                        @error($lic['campo']) <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__title">
                <i class="bi bi-shield-check"></i> Tratamiento de Datos Personales
            </div>
            <div class="privacy-box">
                <label>
                    <input type="checkbox" name="consentimiento_datos" value="1" {{ old('consentimiento_datos') ? 'checked' : '' }} required>
                    <span>
                        Autorizo a SAEP el tratamiento de mis datos personales y documentos de postulación exclusivamente para fines de reclutamiento, selección, contratación, verificación documental, comunicación con RRHH y archivo del proceso, incluyendo la generación y almacenamiento de una ficha PDF consolidada en SharePoint. Conozco que puedo ejercer mis derechos de acceso, rectificación, cancelación, oposición y demás derechos aplicables según la política de datos personales.
                        <br>
                        <a href="{{ route('proteccion-datos.politica-privacidad') }}" target="_blank" rel="noopener">Ver política de privacidad</a>
                    </span>
                </label>
                @error('consentimiento_datos') <div class="invalid-feedback" style="display:block;margin-top:.6rem;">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- Enviar -->
        <div class="submit-bar">
            <button type="submit" class="btn-primary" id="btn-submit">
                <i class="bi bi-send-fill"></i>
                {{ $postulante ? 'Actualizar Postulación' : 'Enviar Postulación' }}
            </button>
        </div>
    </form>
</div>

<script>
// ─── Formateo de RUT en tiempo real ──────────────────────────────
document.getElementById('rut').addEventListener('input', function () {
    let val = this.value.replace(/[^0-9kK]/g, '').toUpperCase();
    if (val.length > 1) {
        const dv  = val.slice(-1);
        let num   = val.slice(0, -1);
        num = num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        val = num + '-' + dv;
    }
    this.value = val;
});

// ─── Subida individual de archivos ───────────────────────────────
const MAX_FILE_BYTES = 100 * 1024 * 1024; // 100 MB
const MAX_BUFFERED_FILE_BYTES = 15 * 1024 * 1024;
const MAX_UPLOAD_RETRIES = 2;
const UPLOAD_TIMEOUT_MS = 120 * 1000;
const PREUPLOAD_URL = @json(route('contratacion-publico.documento.preupload'));
const DISCARD_UPLOAD_URL = @json(route('contratacion-publico.documento.descartar'));
const UPLOAD_ERROR_URL = @json(route('contratacion-publico.documento.error'));
const CSRF_TOKEN = @json(csrf_token());
const uploadActivos = new Set();
const uploadsEnCurso = new Map();
const colaUploads = [];
let subidaActiva = false;
let envioEnCurso = false;

function inputArchivo(campo) {
    return document.getElementById(campo);
}

function hiddenUpload(campo) {
    return document.getElementById('uploaded-' + campo);
}

function previewArchivo(campo) {
    return document.getElementById('preview-' + campo);
}

function existingPreview(campo) {
    return document.getElementById('preview-existing-' + campo);
}

function mostrarErrorArchivo(campo, mensaje) {
    let errorEl = document.getElementById('file-error-' + campo);
    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.id = 'file-error-' + campo;
        errorEl.className = 'alert-error';
        errorEl.style.marginTop = '.5rem';
        errorEl.style.marginBottom = '0';
        const dropZone = document.getElementById('drop-' + campo);
        if (dropZone && dropZone.parentNode) {
            dropZone.parentNode.insertBefore(errorEl, dropZone.nextSibling);
        }
    }
    errorEl.textContent = mensaje;
    errorEl.style.display = 'block';
}

function ocultarErrorArchivo(campo) {
    const errorEl = document.getElementById('file-error-' + campo);
    if (errorEl) errorEl.style.display = 'none';
}

function registrarErrorUpload(campo, fase, mensaje, input = null, xhr = null) {
    try {
        const file = input && input.files && input.files[0] ? input.files[0] : null;
        const formData = new FormData();
        formData.append('_token', CSRF_TOKEN);
        formData.append('campo', campo || '');
        formData.append('fase', fase || 'desconocida');
        formData.append('mensaje', String(mensaje || '').slice(0, 500));
        formData.append('navigator_online', navigator.onLine ? '1' : '0');
        formData.append('user_agent_cliente', String(navigator.userAgent || '').slice(0, 500));

        if (file) {
            formData.append('archivo_nombre', String(file.name || '').slice(0, 255));
            formData.append('archivo_tamano', String(file.size || 0));
            formData.append('archivo_tipo', String(file.type || '').slice(0, 120));
        }

        if (xhr) {
            formData.append('http_status', String(xhr.status || 0));
            formData.append('xhr_response', String(xhr.responseText || '').slice(0, 1000));
        }

        if (navigator.sendBeacon && navigator.sendBeacon(UPLOAD_ERROR_URL, formData)) {
            return;
        }

        fetch(UPLOAD_ERROR_URL, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: formData,
            keepalive: true
        }).catch(function () {});
    } catch (error) {
        // No bloquear la postulación si falla la telemetría.
    }
}

function iconoEstado(estado) {
    if (estado === 'uploading') return 'bi-cloud-arrow-up-fill';
    if (estado === 'error') return 'bi-exclamation-triangle-fill';
    return 'bi-file-earmark-check-fill';
}

function setEstadoArchivo(campo, estado, texto, opciones = {}) {
    const preview = previewArchivo(campo);
    if (!preview) return;

    preview.className = 'file-preview ' + estado;
    preview.style.display = 'flex';
    const icon = preview.querySelector('.name i');
    const span = preview.querySelector('.name span');
    const retry = preview.querySelector('.retry');
    const remove = preview.querySelector('.remove');

    if (icon) icon.className = 'bi ' + iconoEstado(estado);
    if (span) span.textContent = texto;
    if (retry) retry.style.display = opciones.retry ? 'inline-flex' : 'none';
    if (remove) remove.style.display = opciones.remove === false ? 'none' : 'inline-flex';
}

function ocultarPreviewTemporal(campo) {
    const preview = previewArchivo(campo);
    if (preview) {
        preview.style.display = 'none';
        preview.className = 'file-preview';
        const span = preview.querySelector('.name span');
        if (span) span.textContent = '';
    }
}

function actualizarBotonEnvio() {
    const btn = document.getElementById('btn-submit');
    if (!btn || envioEnCurso) return;
    btn.disabled = uploadActivos.size > 0;
}

function archivoListo(campo) {
    const hidden = hiddenUpload(campo);
    const input = inputArchivo(campo);
    const existing = existingPreview(campo);
    return Boolean(hidden && hidden.value)
        || Boolean(input && input.files && input.files.length > 0)
        || Boolean(existing && existing.style.display !== 'none');
}

async function prepararArchivoParaSubida(file) {
    if (!file || !file.slice || !file.arrayBuffer) {
        return file;
    }

    try {
        if (file.size > MAX_BUFFERED_FILE_BYTES) {
            await file.slice(0, Math.min(file.size, 1024)).arrayBuffer();
            return file;
        }

        // Preserve small mobile documents before the Android file provider can release them.
        return new Blob([await file.arrayBuffer()], {
            type: file.type || 'application/octet-stream'
        });
    } catch (error) {
        return null;
    }
}

function mensajeErrorUpload(xhr) {
    try {
        const data = JSON.parse(xhr.responseText || '{}');
        if (data.errors) {
            const first = Object.values(data.errors)[0];
            if (Array.isArray(first) && first[0]) return first[0];
        }
        if (data.message) return data.message;
    } catch (error) {
        // El servidor puede devolver HTML en errores no controlados.
    }
    return 'No se pudo subir el archivo. Revisa tu conexión e inténtalo nuevamente.';
}

function esperar(ms) {
    return new Promise(function (resolve) {
        window.setTimeout(resolve, ms);
    });
}

function procesarColaUploads() {
    if (subidaActiva || colaUploads.length === 0) return;

    const siguiente = colaUploads.shift();
    subidaActiva = true;

    Promise.resolve()
        .then(siguiente.tarea)
        .then(siguiente.resolve)
        .catch(function () {
            siguiente.resolve(false);
        })
        .then(function () {
            subidaActiva = false;
            procesarColaUploads();
        });
}

function encolarSubida(tarea) {
    return new Promise(function (resolve) {
        colaUploads.push({ tarea: tarea, resolve: resolve });
        procesarColaUploads();
    });
}

function subirDocumento(input) {
    const campo = input.dataset.campo || input.name;
    const file = input.files && input.files[0] ? input.files[0] : null;
    const hidden = hiddenUpload(campo);

    if (uploadsEnCurso.has(campo)) {
        return uploadsEnCurso.get(campo);
    }

    if (!file) {
        return Promise.resolve(Boolean(hidden && hidden.value));
    }

    if (file.size > MAX_FILE_BYTES) {
        const msg = 'El archivo "' + file.name + '" supera el límite de 100 MB. Selecciona un archivo más pequeño.';
        registrarErrorUpload(campo, 'client_size_limit', msg, input);
        input.value = '';
        mostrarErrorArchivo(campo, msg);
        setEstadoArchivo(campo, 'error', 'Archivo demasiado grande', { retry: false });
        return Promise.resolve(false);
    }

    uploadActivos.add(campo);
    actualizarBotonEnvio();
    ocultarErrorArchivo(campo);
    setEstadoArchivo(campo, 'uploading', 'En cola: ' + file.name, { remove: false });

    const carga = encolarSubida(function () {
        return prepararArchivoParaSubida(file).then(function (archivoParaSubida) {
        if (!archivoParaSubida) {
            const msg = 'Chrome no pudo leer este archivo. Vuelve a seleccionarlo desde Galería o Archivos/Descargas y evita moverlo o editarlo antes de enviar.';
            uploadActivos.delete(campo);
            actualizarBotonEnvio();
            registrarErrorUpload(campo, 'client_file_unreadable', msg, input);
            mostrarErrorArchivo(campo, msg);
            setEstadoArchivo(campo, 'error', 'Archivo no disponible en el teléfono', { retry: true });
            return false;
        }

        ocultarErrorArchivo(campo);
        setEstadoArchivo(campo, 'uploading', 'Subiendo ' + file.name + '...', { remove: false });

        function intentarSubida(intentos) {
            return new Promise(function (resolve) {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('campo', campo);
            formData.append('documento', archivoParaSubida, file.name);

            xhr.open('POST', PREUPLOAD_URL, true);
            xhr.timeout = UPLOAD_TIMEOUT_MS;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.onprogress = function (event) {
                if (!event.lengthComputable) return;
                const pct = Math.max(1, Math.min(99, Math.round((event.loaded / event.total) * 100)));
                setEstadoArchivo(campo, 'uploading', 'Subiendo ' + file.name + '... ' + pct + '%', { remove: false });
            };

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    uploadActivos.delete(campo);
                    actualizarBotonEnvio();
                    const data = JSON.parse(xhr.responseText || '{}');
                    if (hidden) hidden.value = data.token || '';
                    input.value = '';
                    const existing = existingPreview(campo);
                    if (existing) existing.style.display = 'none';
                    setEstadoArchivo(campo, 'uploaded', 'Subido: ' + (data.original_name || file.name) + (data.size_label ? ' (' + data.size_label + ')' : ''), { retry: false });
                    ocultarErrorArchivo(campo);
                    actualizarBadgeLicencia();
                    resolve(true);
                    return;
                }

                if ([502, 503, 504].includes(xhr.status)) {
                    reintentarOFinalizar('server_temporarily_unavailable', 'El servidor tardó demasiado en responder. Revisa la señal e intenta nuevamente.');
                    return;
                }

                uploadActivos.delete(campo);
                actualizarBotonEnvio();
                const msg = mensajeErrorUpload(xhr);
                registrarErrorUpload(campo, 'server_upload_rejected', msg, input, xhr);
                mostrarErrorArchivo(campo, msg);
                setEstadoArchivo(campo, 'error', 'Error al subir: ' + file.name, { retry: true });
                resolve(false);
            };

            function reintentarOFinalizar(fase, mensaje) {
                if (intentos < MAX_UPLOAD_RETRIES) {
                    const siguienteIntento = intentos + 1;
                    const espera = siguienteIntento * 1000;
                    setEstadoArchivo(campo, 'uploading', 'Reconectando ' + file.name + '... intento ' + (siguienteIntento + 1) + ' de ' + (MAX_UPLOAD_RETRIES + 1), { remove: false });

                    esperar(espera).then(function () {
                        intentarSubida(siguienteIntento).then(resolve);
                    });
                    return;
                }

                uploadActivos.delete(campo);
                actualizarBotonEnvio();
                registrarErrorUpload(campo, fase + '_after_retries', mensaje, input, xhr);
                mostrarErrorArchivo(campo, mensaje);
                setEstadoArchivo(campo, 'error', 'Error de conexión: ' + file.name, { retry: true });
                resolve(false);
            }

            xhr.onerror = function () {
                reintentarOFinalizar('network_error', 'No se pudo completar la carga después de varios intentos. Revisa la señal y vuelve a elegir el archivo desde Archivos o Descargas.');
            };

            xhr.ontimeout = function () {
                reintentarOFinalizar('network_timeout', 'La carga tardó demasiado. Revisa la señal e intenta nuevamente.');
            };

            xhr.send(formData);
            });
        }

        return intentarSubida(0);
        });
    });

    uploadsEnCurso.set(campo, carga);
    carga.finally(function () {
        uploadsEnCurso.delete(campo);
    });

    return carga;
}

function mostrarArchivo(input) {
    subirDocumento(input);
}

function reintentarArchivo(campo) {
    const input = inputArchivo(campo);
    if (input && input.files && input.files[0]) {
        subirDocumento(input);
        return;
    }

    if (input) {
        input.click();
    }
}

function quitarArchivo(campo) {
    const input = inputArchivo(campo);
    const hidden = hiddenUpload(campo);
    const token = hidden ? hidden.value : '';

    if (input) input.value = '';
    if (hidden) hidden.value = '';
    ocultarErrorArchivo(campo);
    ocultarPreviewTemporal(campo);

    const existing = existingPreview(campo);
    if (existing && input && input.dataset.existing === '1') {
        existing.style.display = 'flex';
    }

    actualizarBadgeLicencia();

    if (!token) return;

    const formData = new FormData();
    formData.append('_token', CSRF_TOKEN);
    formData.append('campo', campo);
    formData.append('token', token);
    fetch(DISCARD_UPLOAD_URL, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData
    }).catch(function () {});
}

// ─── Licencia: badge opcional/requerido dinámico ─────────────────
const licCampos = ['licencia_conducir_frontal', 'licencia_conducir_reverso'];

function tieneValorLicencia(campo) {
    // Hay archivo seleccionado en el input O ya existía en BD (preview existing visible)
    const input     = document.getElementById(campo);
    const existing  = document.getElementById('preview-existing-' + campo);
    const hidden    = hiddenUpload(campo);
    return (input && input.files && input.files.length > 0)
        || (hidden && hidden.value)
        || (existing && existing.style.display !== 'none');
}

function actualizarBadgeLicencia() {
    const tieneFrontal = tieneValorLicencia('licencia_conducir_frontal');
    const tieneReverso = tieneValorLicencia('licencia_conducir_reverso');

    licCampos.forEach(function (campo) {
        const badge = document.getElementById('badge-' + campo);
        if (!badge) return;
        const otroTiene = campo === 'licencia_conducir_frontal' ? tieneReverso : tieneFrontal;
        const esteTiene = tieneValorLicencia(campo);
        if (otroTiene && !esteTiene) {
            badge.textContent = 'Requerido';
            badge.className   = 'badge-req';
        } else {
            badge.textContent = 'Opcional';
            badge.className   = 'badge-opt';
        }
    });
}

// Inicializar al cargar la página (por si hay documentos preexistentes)
document.addEventListener('DOMContentLoaded', actualizarBadgeLicencia);

// ─── Drag & drop visual ──────────────────────────────────────────
document.querySelectorAll('.file-drop').forEach(function (zone) {
    zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function () { zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function () { zone.classList.remove('dragover'); });
});

const submitButton = document.getElementById('btn-submit');
const submitButtonHtml = submitButton ? submitButton.innerHTML : '';

// ─── Submit con loading ──────────────────────────────────────────
document.getElementById('form-postulacion').addEventListener('submit', async function (e) {
    if (envioEnCurso) return;

    e.preventDefault();

    if (!this.checkValidity()) {
        this.reportValidity();
        return;
    }

    // Validar tamaño de todos los inputs de archivo antes de enviar
    const fileInputs = this.querySelectorAll('input[type="file"]');
    let hayError = false;
    fileInputs.forEach(function (input) {
        if (input.files && input.files[0] && input.files[0].size > MAX_FILE_BYTES) {
            const campo = input.dataset.campo || input.name;
            mostrarErrorArchivo(campo, '⚠️ El archivo "' + input.files[0].name + '" supera el límite de 100 MB. Por favor selecciona un archivo más pequeño.');
            input.value = '';
            hayError = true;
        }
    });
    if (hayError) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;animation:spin 1s linear infinite;font-size:1rem;">⏳</span> Verificando archivos...';

    for (const input of fileInputs) {
        if (input.files && input.files[0]) {
            const subido = await subirDocumento(input);
            if (!subido) {
                btn.disabled = false;
                btn.innerHTML = submitButtonHtml;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }
        }
    }

    for (const input of fileInputs) {
        const campo = input.dataset.campo || input.name;
        if (input.dataset.required === '1' && !archivoListo(campo)) {
            mostrarErrorArchivo(campo, 'Este documento es obligatorio. Selecciona el archivo y espera a que aparezca como subido.');
            hayError = true;
        }
    }

    const licenciaFrontalLista = archivoListo('licencia_conducir_frontal');
    const licenciaReversoLista = archivoListo('licencia_conducir_reverso');
    if (licenciaFrontalLista && !licenciaReversoLista) {
        mostrarErrorArchivo('licencia_conducir_reverso', 'Si subes el frontal de la licencia, también debes subir el reverso.');
        hayError = true;
    }
    if (licenciaReversoLista && !licenciaFrontalLista) {
        mostrarErrorArchivo('licencia_conducir_frontal', 'Si subes el reverso de la licencia, también debes subir el frontal.');
        hayError = true;
    }

    if (hayError) {
        const firstError = document.querySelector('[id^="file-error-"][style*="block"]');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        btn.disabled = false;
        btn.innerHTML = submitButtonHtml;
        return;
    }

    if (uploadActivos.size > 0) {
        for (const campo of uploadActivos) {
            mostrarErrorArchivo(campo, 'Espera a que este archivo termine de subir antes de enviar.');
        }
        btn.disabled = false;
        btn.innerHTML = submitButtonHtml;
        return;
    }

    for (const input of fileInputs) {
        if (input.files && input.files[0]) {
            btn.disabled = false;
            btn.innerHTML = submitButtonHtml;
            mostrarErrorArchivo(input.dataset.campo || input.name, 'Este archivo todavía no quedó confirmado. Presiona reintentar o vuelve a seleccionarlo.');
            return;
        }
    }

    envioEnCurso = true;
    btn.innerHTML = '<span style="display:inline-block;animation:spin 1s linear infinite;font-size:1rem;">⏳</span> Enviando...';
    this.submit();
});
</script>
</body>
</html>
