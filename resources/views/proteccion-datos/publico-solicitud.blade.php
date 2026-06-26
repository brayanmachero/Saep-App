<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud ARCO pública — SAEP</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/wp/saep_favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: #172033;
            background: #f4f6fb;
        }
        .hero {
            min-height: 260px;
            padding: 34px 18px 72px;
            color: #fff;
            background:
                linear-gradient(90deg, rgba(15, 27, 76, .94), rgba(49, 18, 104, .84)),
                url("{{ asset('brand/wp/optimized/header_servicios_digitalizacion.webp') }}") center/cover;
        }
        .hero-inner, .wrap { width: min(980px, calc(100% - 32px)); margin: 0 auto; }
        .brand { display: flex; justify-content: center; margin-bottom: 28px; }
        .brand img { width: 150px; height: auto; }
        h1 { margin: 0; text-align: center; font-size: clamp(1.55rem, 3vw, 2.35rem); line-height: 1.15; }
        .subtitle { max-width: 760px; margin: 14px auto 0; text-align: center; color: rgba(255,255,255,.82); line-height: 1.55; }
        .panel {
            width: min(920px, calc(100% - 32px));
            margin: -48px auto 46px;
            background: #fff;
            border: 1px solid #e4e8f0;
            border-radius: 8px;
            box-shadow: 0 18px 48px rgba(15, 27, 76, .14);
            padding: clamp(20px, 4vw, 34px);
        }
        .alert { border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: .9rem; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .field { margin-bottom: 18px; }
        label { display: block; margin-bottom: 7px; color: #1f2937; font-size: .88rem; font-weight: 700; }
        input, select, textarea {
            width: 100%;
            border: 1px solid #cfd7e6;
            border-radius: 8px;
            padding: 11px 13px;
            font: inherit;
            color: #172033;
            background: #fff;
        }
        textarea { min-height: 104px; resize: vertical; }
        .rights { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-top: 8px; }
        .right-option input { position: absolute; opacity: 0; pointer-events: none; }
        .right-card {
            min-height: 116px;
            border: 1px solid #d8deea;
            border-radius: 8px;
            padding: 14px;
            cursor: pointer;
            transition: border .15s, box-shadow .15s, transform .15s;
        }
        .right-card i { color: #f97316; font-size: 1.25rem; }
        .right-card strong { display: block; margin-top: 8px; font-size: .9rem; }
        .right-card span { display: block; margin-top: 5px; color: #667085; font-size: .76rem; line-height: 1.35; }
        .right-option input:checked + .right-card {
            border-color: #0f1b4c;
            box-shadow: 0 0 0 2px rgba(15, 27, 76, .14);
            transform: translateY(-1px);
        }
        .consent {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 15px;
            color: #344054;
            font-size: .88rem;
            line-height: 1.5;
        }
        .consent input { width: auto; margin-top: 3px; }
        .actions { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-top: 24px; flex-wrap: wrap; }
        .link { color: #0f1b4c; font-weight: 700; text-decoration: none; }
        button {
            border: 0;
            border-radius: 8px;
            background: #0f1b4c;
            color: #fff;
            font-weight: 800;
            padding: 12px 22px;
            cursor: pointer;
        }
        .notice { margin-top: 20px; color: #475467; font-size: .84rem; line-height: 1.5; }
        @media (max-width: 720px) {
            .grid { grid-template-columns: 1fr; }
            .actions { align-items: stretch; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="hero">
        <div class="hero-inner">
            <div class="brand">
                <img src="{{ asset('brand/wp/Logo-Saep_footer.svg') }}" alt="SAEP">
            </div>
            <h1>Solicitud de Derechos de Datos Personales</h1>
            <p class="subtitle">
                Canal público para ejercer derechos de acceso, rectificación, supresión, oposición, portabilidad o bloqueo respecto de datos tratados por SAEP, especialmente aquellos gestionados en esta plataforma.
            </p>
        </div>
    </header>

    <main class="panel">
        @if($errors->any())
            <div class="alert alert-error">
                <strong>Revise la información ingresada.</strong>
                <ul style="margin:8px 0 0;padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('proteccion-datos.publico.guardar') }}">
            @csrf

            <div class="grid">
                <div class="field">
                    <label for="titular_nombre">Nombre completo *</label>
                    <input id="titular_nombre" name="titular_nombre" value="{{ old('titular_nombre') }}" required maxlength="255" autocomplete="name">
                </div>
                <div class="field">
                    <label for="titular_email">Correo electrónico *</label>
                    <input id="titular_email" name="titular_email" type="email" value="{{ old('titular_email') }}" required maxlength="255" autocomplete="email">
                </div>
                <div class="field">
                    <label for="titular_rut">RUT o identificador</label>
                    <input id="titular_rut" name="titular_rut" value="{{ old('titular_rut') }}" maxlength="30" autocomplete="off">
                </div>
                <div class="field">
                    <label for="titular_telefono">Teléfono</label>
                    <input id="titular_telefono" name="titular_telefono" value="{{ old('titular_telefono') }}" maxlength="50" autocomplete="tel">
                </div>
            </div>

            <div class="field">
                <label for="titular_contexto">Relación con SAEP *</label>
                <select id="titular_contexto" name="titular_contexto" required>
                    <option value="">Seleccione una opción</option>
                    @foreach([
                        'postulacion' => 'Postulación pública',
                        'ley_karin' => 'Canal Ley Karin',
                        'trabajador' => 'Trabajador o ex trabajador',
                        'proveedor' => 'Proveedor o contacto comercial',
                        'visitante' => 'Visitante o contacto web',
                        'otro' => 'Otro vínculo',
                    ] as $value => $label)
                        <option value="{{ $value }}" {{ old('titular_contexto') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Derecho que desea ejercer *</label>
                <div class="rights">
                    @foreach([
                        ['acceso', 'bi-eye', 'Acceso', 'Conocer qué datos se tratan.'],
                        ['rectificacion', 'bi-pencil-square', 'Rectificación', 'Corregir datos inexactos.'],
                        ['supresion', 'bi-trash3', 'Supresión', 'Eliminar datos cuando proceda.'],
                        ['oposicion', 'bi-hand-thumbs-down', 'Oposición', 'Oponerse al tratamiento.'],
                        ['portabilidad', 'bi-box-arrow-right', 'Portabilidad', 'Recibir datos en formato estructurado.'],
                        ['bloqueo', 'bi-pause-circle', 'Bloqueo', 'Suspender temporalmente el tratamiento.'],
                    ] as [$value, $icon, $title, $desc])
                        <label class="right-option">
                            <input type="radio" name="tipo" value="{{ $value }}" {{ old('tipo') === $value ? 'checked' : '' }} required>
                            <span class="right-card">
                                <i class="bi {{ $icon }}"></i>
                                <strong>{{ $title }}</strong>
                                <span>{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label for="descripcion">Descripción de la solicitud *</label>
                <textarea id="descripcion" name="descripcion" required maxlength="2000" placeholder="Explique con claridad qué necesita que revisemos o ejecutemos.">{{ old('descripcion') }}</textarea>
            </div>

            <div class="grid">
                <div class="field">
                    <label for="datos_afectados">Datos afectados</label>
                    <textarea id="datos_afectados" name="datos_afectados" maxlength="1000" placeholder="Ej: postulación, RUT, correo, documentos, denuncia, firma.">{{ old('datos_afectados') }}</textarea>
                </div>
                <div class="field">
                    <label for="causal_invocada">Causal invocada</label>
                    <textarea id="causal_invocada" name="causal_invocada" maxlength="200" placeholder="Requerida para supresión, oposición o bloqueo.">{{ old('causal_invocada') }}</textarea>
                </div>
            </div>

            <div class="field">
                <label for="antecedentes">Antecedentes de respaldo</label>
                <textarea id="antecedentes" name="antecedentes" maxlength="2000" placeholder="Incluya antecedentes útiles para validar la solicitud. No ingrese contraseñas ni datos bancarios.">{{ old('antecedentes') }}</textarea>
            </div>

            <label class="consent">
                <input type="checkbox" name="solicita_bloqueo_temporal" value="1" {{ old('solicita_bloqueo_temporal') ? 'checked' : '' }}>
                <span>Solicito bloqueo temporal del tratamiento mientras SAEP revisa esta solicitud, cuando sea aplicable.</span>
            </label>

            <label class="consent" style="margin-top:12px;">
                <input type="checkbox" name="acepta_tratamiento" value="1" required {{ old('acepta_tratamiento') ? 'checked' : '' }}>
                <span>
                    Acepto que SAEP trate estos datos para verificar mi identidad, tramitar mi solicitud y comunicar el resultado.
                    He leído la <a class="link" href="{{ route('proteccion-datos.politica-privacidad') }}" target="_blank">política de privacidad</a>.
                </span>
            </label>

            <div class="notice">
                Al enviar la solicitud se generará un folio y un enlace privado de seguimiento. Esta plataforma ejecuta acciones automáticas sobre los datos que administra directamente. Si su solicitud alcanza información en otros sistemas internos, repositorios documentales o proveedores tecnológicos, SAEP deberá evaluarla y coordinar la gestión correspondiente con las áreas o encargados aplicables. La supresión, bloqueo u oposición no opera de forma automática sobre datos que deban conservarse por obligación legal, contractual, laboral, investigativa o de defensa de derechos.
            </div>

            <div class="actions">
                <a class="link" href="{{ route('proteccion-datos.politica-privacidad') }}">Ver política de privacidad</a>
                <button type="submit"><i class="bi bi-send"></i> Enviar solicitud</button>
            </div>
        </form>
    </main>
</body>
</html>
