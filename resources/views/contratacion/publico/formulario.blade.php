<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formulario de Documentación de Ingreso — SAEP</title>
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
        .file-preview .name { display: flex; align-items: center; gap: .4rem; }
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
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
        <span>Portal de Contratación</span>
    </div>
    <div class="topbar__user">
        @if($googleUser['avatar'])
        <img src="{{ $googleUser['avatar'] }}" alt="">
        @endif
        <span>{{ $googleUser['name'] }}</span>
        <a href="{{ route('contratacion-publico.logout') }}" class="btn-logout">
            <i class="bi bi-box-arrow-right"></i> Salir
        </a>
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
            <p style="font-size:.85rem;color:#6b7280;margin-bottom:1.5rem;">
                Acepta archivos <strong>JPG, PNG o PDF</strong> de máximo <strong>5 MB</strong> cada uno.
                Los documentos marcados con <span style="color:#ef4444;font-weight:600;">Requerido</span>
                son obligatorios para completar tu postulación.
            </p>

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
                            onchange="mostrarArchivo(this)">
                        <div class="file-drop__icon"><i class="bi bi-cloud-upload"></i></div>
                        <div class="file-drop__text">
                            <strong>Seleccionar archivo</strong> o arrastrar aquí<br>
                            <span>JPG, PNG, PDF · Máx. 5 MB</span>
                        </div>
                    </div>
                    @if($postulante && $postulante->{$doc['campo']})
                    <div class="file-preview existing" id="preview-existing-{{ $doc['campo'] }}">
                        <div class="name"><i class="bi bi-check-circle-fill"></i> Documento ya subido</div>
                        <small>Se reemplazará si subes uno nuevo</small>
                    </div>
                    @endif
                    <div id="preview-{{ $doc['campo'] }}" style="display:none;" class="file-preview">
                        <div class="name"><i class="bi bi-file-earmark-check"></i> <span></span></div>
                        <button type="button" class="remove" onclick="quitarArchivo('{{ $doc['campo'] }}')">
                            <i class="bi bi-x-circle"></i>
                        </button>
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
                    <div class="file-group" style="margin-bottom:0;">
                        <div class="file-label" id="label-{{ $lic['campo'] }}">
                            {{ $lic['label'] }}
                            <span class="badge-opt" id="badge-{{ $lic['campo'] }}">Opcional</span>
                        </div>
                        <div class="file-drop" id="drop-{{ $lic['campo'] }}">
                            <input type="file" name="{{ $lic['campo'] }}" id="{{ $lic['campo'] }}"
                                accept=".jpg,.jpeg,.png,.pdf"
                                data-campo="{{ $lic['campo'] }}"
                                onchange="mostrarArchivo(this); actualizarBadgeLicencia();">
                            <div class="file-drop__icon"><i class="bi bi-cloud-upload"></i></div>
                            <div class="file-drop__text">
                                <strong>Seleccionar archivo</strong> o arrastrar aquí<br>
                                <span>JPG, PNG, PDF · Máx. 5 MB</span>
                            </div>
                        </div>
                        @if($postulante && $postulante->{$lic['campo']})
                        <div class="file-preview existing" id="preview-existing-{{ $lic['campo'] }}">
                            <div class="name"><i class="bi bi-check-circle-fill"></i> Documento ya subido</div>
                            <small>Se reemplazará si subes uno nuevo</small>
                        </div>
                        @endif
                        <div id="preview-{{ $lic['campo'] }}" style="display:none;" class="file-preview">
                            <div class="name"><i class="bi bi-file-earmark-check"></i> <span></span></div>
                            <button type="button" class="remove" onclick="quitarArchivo('{{ $lic['campo'] }}'); actualizarBadgeLicencia();">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                        @error($lic['campo']) <div class="invalid-feedback" style="display:block;">{{ $message }}</div> @enderror
                    </div>
                    @endforeach
                </div>
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

// ─── Preview de archivos ─────────────────────────────────────────
function mostrarArchivo(input) {
    const campo = input.dataset.campo;
    const preview = document.getElementById('preview-' + campo);
    if (input.files && input.files[0]) {
        const file = input.files[0];
        preview.querySelector('span').textContent = file.name;
        preview.style.display = 'flex';
    } else {
        preview.style.display = 'none';
    }
}

function quitarArchivo(campo) {
    const input   = document.getElementById(campo);
    const preview = document.getElementById('preview-' + campo);
    input.value   = '';
    preview.style.display = 'none';
}

// ─── Licencia: badge opcional/requerido dinámico ─────────────────
const licCampos = ['licencia_conducir_frontal', 'licencia_conducir_reverso'];

function tieneValorLicencia(campo) {
    // Hay archivo seleccionado en el input O ya existía en BD (preview existing visible)
    const input     = document.getElementById(campo);
    const existing  = document.getElementById('preview-existing-' + campo);
    return (input && input.files && input.files.length > 0)
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

// ─── Submit con loading ──────────────────────────────────────────
document.getElementById('form-postulacion').addEventListener('submit', function () {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;animation:spin 1s linear infinite;font-size:1rem;">⏳</span> Enviando...';
});
</script>
</body>
</html>
