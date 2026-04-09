<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Formulario de Denuncia — Ley Karin</title>
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

        /* Top bar */
        .topbar {
            background: #0b1437; color: #fff;
            padding: .75rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar__left { display: flex; align-items: center; gap: 1rem; }
        .topbar__left img { height: 32px; filter: brightness(0) invert(1); }
        .topbar__left span { font-size: .85rem; font-weight: 600; opacity: .85; }
        .topbar__user {
            display: flex; align-items: center; gap: .75rem;
            background: rgba(255,255,255,.08); border-radius: 50px; padding: .35rem 1rem .35rem .35rem;
        }
        .topbar__avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: #4285f4; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .8rem; color: #fff; overflow: hidden;
        }
        .topbar__avatar img { width: 100%; height: 100%; object-fit: cover; }
        .topbar__email { font-size: .8rem; color: rgba(255,255,255,.8); }
        .topbar__logout {
            background: none; border: none; color: rgba(255,255,255,.5);
            cursor: pointer; font-size: 1.1rem; margin-left: .5rem; transition: color .2s;
        }
        .topbar__logout:hover { color: #f87171; }

        /* Stepper */
        .stepper-wrapper { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 1.25rem 2rem; }
        .stepper {
            display: flex; align-items: center; justify-content: center;
            gap: 0; max-width: 700px; margin: 0 auto;
        }
        .step {
            display: flex; align-items: center; gap: .5rem;
            font-size: .82rem; font-weight: 500; color: #94a3b8;
            white-space: nowrap; transition: all .3s;
        }
        .step.active { color: #0f1b4c; font-weight: 600; }
        .step.done { color: #16a34a; }
        .step__num {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .78rem; font-weight: 700;
            background: #e2e8f0; color: #94a3b8; transition: all .3s;
        }
        .step.active .step__num { background: #0f1b4c; color: #fff; }
        .step.done .step__num { background: #16a34a; color: #fff; }
        .step__line { width: 50px; height: 2px; background: #e2e8f0; margin: 0 .5rem; transition: background .3s; }
        .step__line.done { background: #16a34a; }

        /* Main content */
        .main-content { max-width: 820px; margin: 2rem auto; padding: 0 1.5rem; }

        .form-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.05);
            padding: 2.5rem;
        }
        .form-card__title {
            font-size: 1.15rem; font-weight: 700; color: #0f1b4c;
            margin-bottom: .25rem;
        }
        .form-card__desc { font-size: .85rem; color: #64748b; margin-bottom: 1.75rem; }

        /* Form elements */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
        .form-row.full { grid-template-columns: 1fr; }

        .form-group { display: flex; flex-direction: column; gap: .35rem; }
        .form-group label {
            font-size: .82rem; font-weight: 600; color: #374151;
        }
        .form-group label .req { color: #dc2626; }
        .form-group .hint { font-size: .75rem; color: #94a3b8; margin-top: .15rem; }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: .7rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: .9rem;
            color: #1e293b;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }
        .form-input.readonly { background: #f8fafc; color: #64748b; cursor: not-allowed; }
        .form-textarea { resize: vertical; min-height: 140px; }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8.825L.35 3.175l.7-.7L6 7.425l4.95-4.95.7.7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        /* Type cards */
        .type-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .type-card {
            border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 1.25rem; text-align: center;
            cursor: pointer; transition: all .2s; position: relative;
        }
        .type-card:hover { border-color: #93c5fd; background: #f0f7ff; }
        .type-card.selected { border-color: #0f1b4c; background: #eef2ff; }
        .type-card input[type="radio"] { position: absolute; opacity: 0; }
        .type-card__icon { font-size: 1.8rem; margin-bottom: .5rem; }
        .type-card__label { font-size: .82rem; font-weight: 600; color: #1e293b; }
        .type-card__desc { font-size: .72rem; color: #94a3b8; margin-top: .25rem; line-height: 1.4; }

        /* Navigation buttons */
        .form-nav {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9;
        }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .7rem 1.5rem; border-radius: 10px;
            font-family: inherit; font-size: .88rem; font-weight: 600;
            cursor: pointer; transition: all .2s; border: none;
            text-decoration: none;
        }
        .btn-outline {
            background: #fff; border: 1.5px solid #e2e8f0; color: #64748b;
        }
        .btn-outline:hover { border-color: #94a3b8; color: #374151; }
        .btn-primary {
            background: #0f1b4c; color: #fff;
        }
        .btn-primary:hover { background: #1a2766; }
        .btn-danger {
            background: #dc2626; color: #fff;
        }
        .btn-danger:hover { background: #b91c1c; }

        /* Consent checkbox */
        .consent-box {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 1.25rem; margin-bottom: 1.25rem;
        }
        .consent-box label {
            display: flex; align-items: flex-start; gap: .75rem;
            cursor: pointer; font-size: .85rem; color: #374151; line-height: 1.6;
        }
        .consent-box input[type="checkbox"] {
            width: 20px; height: 20px; margin-top: 2px;
            flex-shrink: 0; accent-color: #0f1b4c;
        }

        /* Geo popup */
        .geo-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.4); backdrop-filter: blur(4px);
            z-index: 999; align-items: center; justify-content: center;
        }
        .geo-modal-overlay.show { display: flex; }
        .geo-modal {
            background: #fff; border-radius: 20px;
            padding: 2.5rem; max-width: 460px; width: 90%;
            text-align: center; box-shadow: 0 25px 60px rgba(0,0,0,.15);
        }
        .geo-modal__icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #eef2ff; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem; font-size: 1.8rem; color: #0f1b4c;
        }
        .geo-modal h3 { font-size: 1.15rem; color: #0f1b4c; margin-bottom: .5rem; }
        .geo-modal p { font-size: .85rem; color: #64748b; line-height: 1.6; margin-bottom: 1.5rem; }
        .geo-modal__btns { display: flex; gap: .75rem; justify-content: center; }
        .geo-modal__btns .btn { flex: 1; justify-content: center; }

        /* Error box */
        .error-summary {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
            padding: 1rem 1.25rem; margin-bottom: 1.5rem;
        }
        .error-summary h4 { font-size: .85rem; color: #991b1b; margin-bottom: .5rem; display: flex; align-items: center; gap: .4rem; }
        .error-summary ul { list-style: none; padding: 0; }
        .error-summary li { font-size: .8rem; color: #b91c1c; padding: .15rem 0; }
        .error-summary li::before { content: '•'; margin-right: .5rem; }

        .field-error { font-size: .75rem; color: #dc2626; margin-top: .25rem; }

        /* Hidden */
        .step-panel { display: none; }
        .step-panel.active { display: block; }

        /* Location badge */
        .geo-status {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .35rem .75rem; border-radius: 8px; font-size: .78rem; font-weight: 500;
        }
        .geo-status.captured { background: #dcfce7; color: #166534; }
        .geo-status.skipped { background: #f3f4f6; color: #6b7280; }

        /* Google badge */
        .google-badge {
            background: #eef2ff; border-radius: 12px; padding: 1rem 1.25rem;
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;
            overflow: hidden;
        }
        .google-badge__icon {
            width: 40px; height: 40px; border-radius: 50%; background: #0f1b4c;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .google-badge__text { min-width: 0; }

        /* Review summary */
        .review-summary {
            background: #f8fafc; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;
        }
        .review-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; font-size: .85rem;
        }

        @media (max-width: 700px) {
            .form-row { grid-template-columns: 1fr; }
            .type-cards { grid-template-columns: 1fr; }
            .topbar { padding: .6rem .75rem; flex-wrap: wrap; gap: .5rem; }
            .topbar__left span { display: none; }
            .topbar__left img { height: 26px; }
            .topbar__user { padding: .25rem .75rem .25rem .25rem; gap: .5rem; }
            .topbar__email { font-size: .72rem; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .topbar__avatar { width: 28px; height: 28px; }
            .stepper-wrapper { padding: .85rem 1rem; }
            .step__num { width: 26px; height: 26px; font-size: .72rem; }
            .step span { display: none; }
            .step__line { width: 28px; }
            .main-content { padding: 1rem .75rem; margin: 0 auto; }
            .form-card { padding: 1.25rem 1rem; border-radius: 12px; }
            .form-card__title { font-size: 1rem; }
            .form-card__desc { font-size: .8rem; }
            .form-input, .form-select, .form-textarea { font-size: .85rem; padding: .6rem .85rem; }
            .type-card { padding: 1rem; border-radius: 10px; }
            .type-card__icon { font-size: 1.4rem; margin-bottom: .35rem; }
            .type-card__label { font-size: .78rem; }
            .type-card__desc { font-size: .68rem; }
            .consent-box { padding: 1rem; }
            .consent-box label { font-size: .8rem; gap: .5rem; }
            .btn { padding: .6rem 1.1rem; font-size: .82rem; }
            .form-nav { gap: .5rem; }
            .geo-modal { padding: 1.5rem; border-radius: 16px; }
            .geo-modal__btns { flex-direction: column; }
            .google-badge { padding: .75rem; gap: .75rem; }
            .google-badge__icon { width: 34px; height: 34px; }
            .review-grid { grid-template-columns: 1fr; gap: .5rem; font-size: .82rem; }
        }

        @media (max-width: 380px) {
            .topbar__email { max-width: 120px; }
            .main-content { padding: .75rem .5rem; }
            .form-card { padding: 1rem .75rem; }
            .form-input, .form-select, .form-textarea { padding: .55rem .75rem; font-size: .82rem; }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <div class="topbar__left">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
        <span>Canal de Denuncia — Ley Karin</span>
    </div>
    <div class="topbar__user">
        <div class="topbar__avatar">
            @if($googleUser['avatar'])
                <img src="{{ $googleUser['avatar'] }}" alt="{{ $googleUser['name'] }}" referrerpolicy="no-referrer">
            @else
                {{ strtoupper(substr($googleUser['name'], 0, 1)) }}
            @endif
        </div>
        <span class="topbar__email">{{ $googleUser['email'] }}</span>
        <form action="{{ route('ley-karin-publico.logout') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="topbar__logout" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></button>
        </form>
    </div>
</div>

<!-- Stepper -->
<div class="stepper-wrapper">
    <div class="stepper">
        <div class="step active" id="step-ind-1">
            <div class="step__num">1</div>
            <span>Tipo de denuncia</span>
        </div>
        <div class="step__line" id="line-1"></div>
        <div class="step" id="step-ind-2">
            <div class="step__num">2</div>
            <span>Datos del caso</span>
        </div>
        <div class="step__line" id="line-2"></div>
        <div class="step" id="step-ind-3">
            <div class="step__num">3</div>
            <span>Revisión y envío</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">

    @if($errors->any())
    <div class="error-summary">
        <h4><i class="bi bi-exclamation-triangle-fill"></i> Corrige los siguientes errores:</h4>
        <ul>
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form id="denunciaForm" action="{{ route('ley-karin-publico.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Hidden fields for geolocation -->
        <input type="hidden" name="latitud" id="input-lat" value="{{ old('latitud') }}">
        <input type="hidden" name="longitud" id="input-lng" value="{{ old('longitud') }}">
        <input type="hidden" name="consentimiento_geolocalizacion" id="input-geo-consent" value="{{ old('consentimiento_geolocalizacion', '0') }}">

        <!-- ===== PASO 1: Tipo de denuncia ===== -->
        <div class="step-panel active" id="panel-1">
            <div class="form-card">
                <h3 class="form-card__title"><i class="bi bi-shield-exclamation"></i> Tipo de Denuncia</h3>
                <p class="form-card__desc">Selecciona el tipo de conducta que deseas denunciar. Puedes consultar las definiciones a continuación.</p>

                <div class="type-cards">
                    <label class="type-card {{ old('tipo') === 'ACOSO_LABORAL' ? 'selected' : '' }}">
                        <input type="radio" name="tipo" value="ACOSO_LABORAL" {{ old('tipo') === 'ACOSO_LABORAL' ? 'checked' : '' }}>
                        <div class="type-card__icon">🏢</div>
                        <div class="type-card__label">Acoso Laboral</div>
                        <div class="type-card__desc">Conducta de agresión u hostigamiento reiterada que menoscabe, maltrate o humille al trabajador.</div>
                    </label>
                    <label class="type-card {{ old('tipo') === 'ACOSO_SEXUAL' ? 'selected' : '' }}">
                        <input type="radio" name="tipo" value="ACOSO_SEXUAL" {{ old('tipo') === 'ACOSO_SEXUAL' ? 'checked' : '' }}>
                        <div class="type-card__icon">⚠️</div>
                        <div class="type-card__label">Acoso Sexual</div>
                        <div class="type-card__desc">Requerimientos de carácter sexual no consentidos que amenacen o perjudiquen la situación laboral.</div>
                    </label>
                    <label class="type-card {{ old('tipo') === 'VIOLENCIA_EN_TRABAJO' ? 'selected' : '' }}">
                        <input type="radio" name="tipo" value="VIOLENCIA_EN_TRABAJO" {{ old('tipo') === 'VIOLENCIA_EN_TRABAJO' ? 'checked' : '' }}>
                        <div class="type-card__icon">🛑</div>
                        <div class="type-card__label">Violencia en el Trabajo</div>
                        <div class="type-card__desc">Conductas de violencia ejercidas por terceros ajenos a la relación laboral (clientes, proveedores, etc.).</div>
                    </label>
                </div>
                @error('tipo')
                <div class="field-error">{{ $message }}</div>
                @enderror

                <div class="form-nav">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="goStep(2)">
                        Continuar <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== PASO 2: Datos del caso ===== -->
        <div class="step-panel" id="panel-2">
            <div class="form-card">
                <h3 class="form-card__title"><i class="bi bi-person-lines-fill"></i> Datos del Caso</h3>
                <p class="form-card__desc">Completa la información sobre la denuncia. Los campos con <span style="color:#dc2626;">*</span> son obligatorios.</p>

                <!-- Denunciante info -->
                <div class="google-badge">
                    <div class="google-badge__icon">
                        <i class="bi bi-google" style="color:#fff;font-size:1rem;"></i>
                    </div>
                    <div class="google-badge__text">
                        <div style="font-size:.82rem;font-weight:600;color:#0f1b4c;">Verificado con Google</div>
                        <div style="font-size:.78rem;color:#64748b;word-break:break-all;">{{ $googleUser['email'] }}</div>
                    </div>
                </div>

                <!-- Opción de denuncia anónima -->
                <div class="consent-box" style="border-color:#6366f1;background:#eef2ff;margin-bottom:1.5rem;">
                    <label>
                        <input type="checkbox" name="anonima" value="1" id="anonima-check" {{ old('anonima') ? 'checked' : '' }} onchange="toggleAnonima(this)">
                        <div>
                            <strong><i class="bi bi-incognito"></i> Deseo que mi denuncia sea anónima</strong><br>
                            <span style="font-size:.8rem;color:#64748b;">
                                Tu nombre y RUT no serán visibles para los investigadores.
                                Tu correo electrónico se mantiene registrado internamente como medida de seguridad
                                para evitar denuncias falsas, pero <strong>no será compartido</strong> con el equipo investigador.
                            </span>
                        </div>
                    </label>
                </div>

                <div id="datos-denunciante" style="{{ old('anonima') ? 'display:none;' : '' }}">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre completo <span class="req" id="nombre-req">*</span></label>
                            <input type="text" name="denunciante_nombre" id="denunciante_nombre" class="form-input" value="{{ old('denunciante_nombre', $googleUser['name']) }}" placeholder="Nombre y apellidos">
                            @error('denunciante_nombre') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label>RUT (opcional)</label>
                            <input type="text" name="denunciante_rut" data-rut class="form-input" value="{{ old('denunciante_rut') }}">
                            <span class="hint">Formato: 12.345.678-9</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <input type="email" class="form-input readonly" value="{{ $googleUser['email'] }}" readonly>
                        <span class="hint">Detectado automáticamente desde tu cuenta de Google</span>
                    </div>
                    <div class="form-group">
                        <label>Centro de costo / Sucursal <span class="req">*</span></label>
                        <select name="centro_costo_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach($centros as $cc)
                            <option value="{{ $cc->id }}" {{ old('centro_costo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nombre }}</option>
                            @endforeach
                        </select>
                        @error('centro_costo_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #f1f5f9;margin:1.5rem 0;">
                <p style="font-size:.82rem;font-weight:600;color:#64748b;margin-bottom:1rem;"><i class="bi bi-person-x"></i> Datos del Denunciado (si se conocen)</p>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre del denunciado</label>
                        <input type="text" name="denunciado_nombre" class="form-input" value="{{ old('denunciado_nombre') }}" placeholder="Nombre completo">
                    </div>
                    <div class="form-group">
                        <label>Cargo del denunciado</label>
                        <input type="text" name="denunciado_cargo" class="form-input" value="{{ old('denunciado_cargo') }}" placeholder="Cargo o función">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Descripción de los hechos <span class="req">*</span></label>
                        <textarea name="descripcion_hechos" class="form-textarea" required placeholder="Describe de forma detallada los hechos ocurridos, incluyendo fechas, lugares y personas involucradas...">{{ old('descripcion_hechos') }}</textarea>
                        <span class="hint">Incluye fechas, lugares y circunstancias. Mínimo 20 caracteres.</span>
                        @error('descripcion_hechos') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <!-- Método de contacto -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Método de contacto preferido</label>
                        <select name="metodo_contacto" class="form-select">
                            <option value="EMAIL" {{ old('metodo_contacto', 'EMAIL') === 'EMAIL' ? 'selected' : '' }}>Correo electrónico</option>
                            <option value="TELEFONO" {{ old('metodo_contacto') === 'TELEFONO' ? 'selected' : '' }}>Teléfono</option>
                            <option value="NO_CONTACTAR" {{ old('metodo_contacto') === 'NO_CONTACTAR' ? 'selected' : '' }}>No deseo ser contactado/a</option>
                        </select>
                        <span class="hint">Indica cómo prefieres recibir actualizaciones sobre tu caso.</span>
                    </div>
                    <div></div>
                </div>

                <!-- Evidencias / archivos adjuntos -->
                <hr style="border:none;border-top:1px solid #f1f5f9;margin:1.5rem 0;">
                <p style="font-size:.82rem;font-weight:600;color:#64748b;margin-bottom:.5rem;"><i class="bi bi-paperclip"></i> Evidencias (opcional)</p>
                <p style="font-size:.78rem;color:#94a3b8;margin-bottom:1rem;">Adjunta archivos que respalden tu denuncia: documentos, imágenes, audios o videos. Máximo 5 archivos, 10 MB cada uno.</p>

                <div class="form-row full">
                    <div class="form-group">
                        <input type="file" name="evidencias[]" id="evidencias-input" class="form-input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.mp3,.mp4,.wav,.doc,.docx" style="padding:.5rem;">
                        <span class="hint">Formatos permitidos: PDF, JPG, PNG, GIF, MP3, MP4, WAV, DOC, DOCX</span>
                        <div id="evidencias-list" style="margin-top:.5rem;"></div>
                        @error('evidencias') <div class="field-error">{{ $message }}</div> @enderror
                        @error('evidencias.*') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn btn-outline" onclick="goStep(1)">
                        <i class="bi bi-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goStep(3)">
                        Continuar <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== PASO 3: Revisión y envío ===== -->
        <div class="step-panel" id="panel-3">
            <div class="form-card">
                <h3 class="form-card__title"><i class="bi bi-check2-square"></i> Revisión y Envío</h3>
                <p class="form-card__desc">Revisa los datos antes de enviar. Al enviar, recibirás un número de folio para seguimiento.</p>

                <!-- Summary -->
                <div id="review-summary" class="review-summary">
                    <div class="review-grid">
                        <div><strong>Tipo:</strong> <span id="rev-tipo">—</span></div>
                        <div><strong>Denunciante:</strong> <span id="rev-nombre">—</span></div>
                        <div><strong>Correo:</strong> <span style="word-break:break-all;">{{ $googleUser['email'] }}</span></div>
                        <div><strong>Centro:</strong> <span id="rev-centro">—</span></div>
                        <div><strong>Denunciado:</strong> <span id="rev-denunciado">—</span></div>
                        <div><strong>Modo:</strong> <span id="rev-anonima">Identificada</span></div>
                        <div><strong>Contacto:</strong> <span id="rev-contacto">—</span></div>
                        <div><strong>Evidencias:</strong> <span id="rev-evidencias">Sin archivos</span></div>
                        <div><strong>Ubicación:</strong> <span id="rev-geo" class="geo-status skipped"><i class="bi bi-geo-alt"></i> No compartida</span></div>
                    </div>
                    <div style="margin-top:.75rem;font-size:.85rem;">
                        <strong>Descripción:</strong>
                        <p id="rev-descripcion" style="color:#64748b;margin-top:.25rem;white-space:pre-wrap;">—</p>
                    </div>
                </div>

                <!-- Geolocation consent -->
                <div class="consent-box" style="border-color:#3b82f6; background:#eff6ff;">
                    <label>
                        <input type="checkbox" id="geo-checkbox" onchange="handleGeoConsent(this)">
                        <div>
                            <strong>Compartir mi ubicación (opcional)</strong><br>
                            <span style="font-size:.8rem;color:#64748b;">
                                Autorizo compartir mi ubicación geográfica para fines de registro
                                conforme al Art. 16 sexies de la Ley 21.719. Esta información es
                                estrictamente confidencial y no será compartida con terceros.
                            </span>
                        </div>
                    </label>
                </div>

                <!-- Data processing consent (required) -->
                <div class="consent-box">
                    <label>
                        <input type="checkbox" name="consentimiento_datos" value="1" required {{ old('consentimiento_datos') ? 'checked' : '' }}>
                        <div>
                            <strong>Acepto el tratamiento de datos personales <span style="color:#dc2626;">*</span></strong><br>
                            <span style="font-size:.8rem;color:#64748b;">
                                Autorizo el tratamiento de mis datos personales para la gestión de esta
                                denuncia conforme a la Ley 21.719 sobre Protección de Datos Personales.
                                Los datos serán tratados con estricta confidencialidad y serán utilizados
                                únicamente para los fines de esta investigación.
                            </span>
                        </div>
                    </label>
                    @error('consentimiento_datos') <div class="field-error" style="margin-left:2.5rem;">{{ $message }}</div> @enderror
                </div>

                <div class="form-nav">
                    <button type="button" class="btn btn-outline" onclick="goStep(2)">
                        <i class="bi bi-arrow-left"></i> Anterior
                    </button>
                    <button type="submit" class="btn btn-danger" id="btn-submit">
                        <i class="bi bi-send-fill"></i> Enviar Denuncia
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Geolocation Modal -->
<div class="geo-modal-overlay" id="geoModal">
    <div class="geo-modal">
        <div class="geo-modal__icon"><i class="bi bi-geo-alt-fill"></i></div>
        <h3>Compartir ubicación</h3>
        <p>
            Tu navegador solicitará permiso para acceder a tu ubicación.
            Esta información es <strong>opcional</strong> y será tratada de forma confidencial
            conforme a la Ley 21.719.
        </p>
        <p style="font-size:.78rem;color:#94a3b8;margin-top:-.5rem;margin-bottom:1.25rem;">
            Solo se registrarán las coordenadas (latitud y longitud) al momento del envío.
        </p>
        <div class="geo-modal__btns">
            <button type="button" class="btn btn-outline" onclick="declineGeo()">No, gracias</button>
            <button type="button" class="btn btn-primary" onclick="acceptGeo()">
                <i class="bi bi-geo-alt"></i> Permitir
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Type card selection
    document.querySelectorAll('.type-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
        });
    });

    // Initialize anonymous toggle
    const anonimaCheck = document.getElementById('anonima-check');
    if (anonimaCheck && anonimaCheck.checked) {
        toggleAnonima(anonimaCheck);
    }

    // File input preview
    document.getElementById('evidencias-input').addEventListener('change', function() {
        const list = document.getElementById('evidencias-list');
        list.innerHTML = '';
        if (this.files.length > 5) {
            list.innerHTML = '<span style="color:#dc2626;font-size:.78rem;">Máximo 5 archivos permitidos.</span>';
            return;
        }
        for (let i = 0; i < this.files.length; i++) {
            const f = this.files[i];
            const sizeMB = (f.size / 1024 / 1024).toFixed(1);
            const isOversize = f.size > 10 * 1024 * 1024;
            list.innerHTML += '<div style="font-size:.78rem;color:' + (isOversize ? '#dc2626' : '#64748b') + ';padding:.15rem 0;">'
                + '<i class="bi bi-file-earmark"></i> ' + f.name + ' (' + sizeMB + ' MB)'
                + (isOversize ? ' — <strong>Excede 10 MB</strong>' : '')
                + '</div>';
        }
    });
});

let currentStep = 1;
const tipoLabels = @json(\App\Models\LeyKarin::tiposMap());

function toggleAnonima(checkbox) {
    const datosDiv = document.getElementById('datos-denunciante');
    const nombreInput = document.getElementById('denunciante_nombre');
    if (checkbox.checked) {
        datosDiv.style.display = 'none';
        if (nombreInput) nombreInput.removeAttribute('required');
    } else {
        datosDiv.style.display = '';
        if (nombreInput) nombreInput.setAttribute('required', '');
    }
}

function goStep(step) {
    // Validate before moving forward
    if (step > currentStep) {
        if (currentStep === 1) {
            const tipo = document.querySelector('input[name="tipo"]:checked');
            if (!tipo) {
                alert('Debes seleccionar un tipo de denuncia.');
                return;
            }
        }
        if (currentStep === 2) {
            const esAnonima = document.getElementById('anonima-check').checked;
            const nombre = document.querySelector('input[name="denunciante_nombre"]').value.trim();
            const centro = document.querySelector('select[name="centro_costo_id"]').value;
            const desc = document.querySelector('textarea[name="descripcion_hechos"]').value.trim();
            if (!esAnonima && !nombre) { alert('Ingresa tu nombre completo o marca la denuncia como anónima.'); return; }
            if (!centro) { alert('Selecciona un centro de costo.'); return; }
            if (!desc || desc.length < 20) { alert('La descripción debe tener al menos 20 caracteres.'); return; }
            // Validate file count and size
            const files = document.getElementById('evidencias-input').files;
            if (files.length > 5) { alert('Máximo 5 archivos permitidos.'); return; }
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 10 * 1024 * 1024) {
                    alert('El archivo "' + files[i].name + '" excede los 10 MB permitidos.');
                    return;
                }
            }
        }
    }

    // Update review summary when reaching step 3
    if (step === 3) {
        updateReview();
    }

    // Switch panels
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + step).classList.add('active');

    // Update stepper indicators
    for (let i = 1; i <= 3; i++) {
        const ind = document.getElementById('step-ind-' + i);
        ind.classList.remove('active', 'done');
        if (i < step) ind.classList.add('done');
        else if (i === step) ind.classList.add('active');
    }
    for (let i = 1; i <= 2; i++) {
        const line = document.getElementById('line-' + i);
        line.classList.toggle('done', i < step);
    }

    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateReview() {
    const tipoEl = document.querySelector('input[name="tipo"]:checked');
    document.getElementById('rev-tipo').textContent = tipoEl ? (tipoLabels[tipoEl.value] || tipoEl.value) : '—';

    const esAnonima = document.getElementById('anonima-check').checked;
    if (esAnonima) {
        document.getElementById('rev-nombre').innerHTML = '<span style="color:#6366f1;font-weight:600;"><i class="bi bi-incognito"></i> Anónima</span>';
    } else {
        document.getElementById('rev-nombre').textContent = document.querySelector('input[name="denunciante_nombre"]').value || '—';
    }
    document.getElementById('rev-anonima').textContent = esAnonima ? 'Denuncia Anónima' : 'Identificada';

    const centroSelect = document.querySelector('select[name="centro_costo_id"]');
    document.getElementById('rev-centro').textContent = centroSelect.selectedIndex > 0 ? centroSelect.options[centroSelect.selectedIndex].text : '—';

    const denunciado = document.querySelector('input[name="denunciado_nombre"]').value;
    const cargo = document.querySelector('input[name="denunciado_cargo"]').value;
    document.getElementById('rev-denunciado').textContent = denunciado ? (denunciado + (cargo ? ' (' + cargo + ')' : '')) : 'No indicado';

    // Contacto
    const contactoSelect = document.querySelector('select[name="metodo_contacto"]');
    document.getElementById('rev-contacto').textContent = contactoSelect.options[contactoSelect.selectedIndex].text;

    // Evidencias
    const files = document.getElementById('evidencias-input').files;
    document.getElementById('rev-evidencias').textContent = files.length > 0 ? files.length + ' archivo(s)' : 'Sin archivos';

    document.getElementById('rev-descripcion').textContent = document.querySelector('textarea[name="descripcion_hechos"]').value || '—';
}

function handleGeoConsent(checkbox) {
    if (checkbox.checked) {
        document.getElementById('geoModal').classList.add('show');
    } else {
        document.getElementById('input-lat').value = '';
        document.getElementById('input-lng').value = '';
        document.getElementById('input-geo-consent').value = '0';
        updateGeoStatus(false);
    }
}

function acceptGeo() {
    document.getElementById('geoModal').classList.remove('show');

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('input-lat').value = pos.coords.latitude.toFixed(7);
                document.getElementById('input-lng').value = pos.coords.longitude.toFixed(7);
                document.getElementById('input-geo-consent').value = '1';
                updateGeoStatus(true);
            },
            function(err) {
                alert('No se pudo obtener la ubicación. Puedes continuar sin ella.');
                document.getElementById('geo-checkbox').checked = false;
                document.getElementById('input-geo-consent').value = '0';
                updateGeoStatus(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        alert('Tu navegador no soporta geolocalización.');
        document.getElementById('geo-checkbox').checked = false;
    }
}

function declineGeo() {
    document.getElementById('geoModal').classList.remove('show');
    document.getElementById('geo-checkbox').checked = false;
    document.getElementById('input-geo-consent').value = '0';
    updateGeoStatus(false);
}

function updateGeoStatus(captured) {
    const el = document.getElementById('rev-geo');
    if (captured) {
        el.className = 'geo-status captured';
        el.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Ubicación capturada';
    } else {
        el.className = 'geo-status skipped';
        el.innerHTML = '<i class="bi bi-geo-alt"></i> No compartida';
    }
}

// Prevent double submit
document.getElementById('denunciaForm').addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';
});
</script>
<script>
(function(){
    function formatRut(value) {
        let clean = value.replace(/[^0-9kK]/g, '').toUpperCase();
        if (!clean) return '';
        let dv = clean.slice(-1);
        let body = clean.slice(0, -1);
        if (!body) return clean;
        let formatted = '';
        let count = 0;
        for (let i = body.length - 1; i >= 0; i--) {
            formatted = body[i] + formatted;
            count++;
            if (count % 3 === 0 && i > 0) formatted = '.' + formatted;
        }
        return formatted + '-' + dv;
    }
    document.querySelectorAll('[data-rut]').forEach(function(input) {
        input.setAttribute('maxlength', '12');
        if (input.value) input.value = formatRut(input.value);
        input.addEventListener('input', function(e) {
            const pos = input.selectionStart;
            const oldLen = input.value.length;
            input.value = formatRut(input.value);
            const newLen = input.value.length;
            const newPos = Math.max(0, pos + (newLen - oldLen));
            input.setSelectionRange(newPos, newPos);
        });
    });
})();
</script>
</body>
</html>
