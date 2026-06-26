<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $solicitud->numero_solicitud }} — Solicitud ARCO SAEP</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/wp/saep_favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: #172033;
            background:
                linear-gradient(90deg, rgba(15, 27, 76, .94), rgba(49, 18, 104, .82)),
                url("{{ asset('brand/wp/optimized/header_quienes-somos.webp') }}") center/cover fixed;
            padding: 28px 16px;
        }
        .wrap { width: min(820px, 100%); margin: 0 auto; }
        .brand { display: flex; justify-content: center; margin-bottom: 24px; }
        .brand img { width: 142px; height: auto; }
        .panel {
            background: #fff;
            border: 1px solid #e4e8f0;
            border-radius: 8px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, .18);
            padding: clamp(20px, 4vw, 34px);
        }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 8px; padding: 13px 15px; margin-bottom: 20px; font-size: .9rem; }
        .header { display: flex; justify-content: space-between; gap: 18px; flex-wrap: wrap; align-items: flex-start; margin-bottom: 24px; }
        h1 { margin: 0 0 8px; font-size: clamp(1.35rem, 3vw, 2rem); color: #0f1b4c; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 6px 12px; font-weight: 800; font-size: .82rem; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 22px 0; }
        .meta-item { background: #f8fafc; border: 1px solid #e4e8f0; border-radius: 8px; padding: 13px; }
        .label { color: #667085; text-transform: uppercase; letter-spacing: .04em; font-size: .72rem; font-weight: 800; }
        .value { color: #172033; font-size: .94rem; font-weight: 700; margin-top: 5px; overflow-wrap: anywhere; }
        .section { margin-top: 22px; }
        .section h2 { font-size: 1rem; color: #0f1b4c; margin: 0 0 8px; }
        .box { background: #f8fafc; border: 1px solid #e4e8f0; border-radius: 8px; padding: 14px; line-height: 1.55; color: #344054; }
        .notice { margin-top: 24px; color: #667085; font-size: .84rem; line-height: 1.5; }
        .actions { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 26px; }
        a { color: #0f1b4c; font-weight: 800; text-decoration: none; }
        .button { display: inline-flex; align-items: center; gap: 8px; border-radius: 8px; background: #0f1b4c; color: #fff; padding: 11px 16px; }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="brand">
            <img src="{{ asset('brand/wp/Logo-Saep_footer.svg') }}" alt="SAEP">
        </div>

        <section class="panel">
            @if(session('success'))
                <div class="success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
            @endif

            <div class="header">
                <div>
                    <h1>{{ $solicitud->numero_solicitud }}</h1>
                    <span class="badge" style="background: {{ $solicitud->color_estado }}20; color: {{ $solicitud->color_estado }};">
                        <i class="bi bi-shield-check"></i> {{ $solicitud->nombre_estado }}
                    </span>
                </div>
                <div style="text-align:right;">
                    <div class="label">Derecho solicitado</div>
                    <div class="value">{{ $solicitud->nombre_tipo }}</div>
                </div>
            </div>

            <div class="meta">
                <div class="meta-item">
                    <div class="label">Titular</div>
                    <div class="value">{{ $solicitud->titular_nombre_mostrar }}</div>
                </div>
                <div class="meta-item">
                    <div class="label">Canal</div>
                    <div class="value">{{ $solicitud->canal_label }} / {{ str_replace('_', ' ', $solicitud->titular_contexto ?? 'sin contexto') }}</div>
                </div>
                <div class="meta-item">
                    <div class="label">Fecha solicitud</div>
                    <div class="value">{{ $solicitud->fecha_solicitud?->format('d/m/Y H:i') }}</div>
                </div>
                <div class="meta-item">
                    <div class="label">Plazo estimado</div>
                    <div class="value">{{ $solicitud->fecha_vencimiento?->format('d/m/Y') }}</div>
                </div>
            </div>

            @if($solicitud->bloqueo_temporal_activo)
                <div class="box" style="border-color:#bfdbfe;background:#eff6ff;color:#1e40af;">
                    <i class="bi bi-pause-circle"></i>
                    Solicitud con bloqueo temporal activo mientras se revisa el tratamiento aplicable.
                </div>
            @endif

            <div class="section">
                <h2><i class="bi bi-chat-left-text"></i> Descripción</h2>
                <div class="box">{{ $solicitud->descripcion }}</div>
            </div>

            @if($solicitud->respuesta)
                <div class="section">
                    <h2><i class="bi bi-reply"></i> Respuesta SAEP</h2>
                    <div class="box">{{ $solicitud->respuesta }}</div>
                </div>
            @endif

            @if($solicitud->motivo_rechazo)
                <div class="section">
                    <h2><i class="bi bi-x-circle"></i> Motivo del rechazo</h2>
                    <div class="box" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">{{ $solicitud->motivo_rechazo }}</div>
                </div>
            @endif

            @if($solicitud->fecha_ejecucion)
                <div class="section">
                    <h2><i class="bi bi-check2-shield"></i> Ejecución registrada</h2>
                    <div class="box">
                        Ejecutada el {{ $solicitud->fecha_ejecucion->format('d/m/Y H:i') }}.
                        Estado técnico: {{ str_replace('_', ' ', $solicitud->estado_ejecucion ?? 'registrada') }}.
                    </div>
                </div>
            @endif

            <p class="notice">
                Este enlace es privado y permite revisar el estado de la solicitud. Las acciones automáticas se limitan a los datos administrados por esta plataforma. Cuando existan datos en otros sistemas, repositorios documentales o proveedores tecnológicos, SAEP deberá gestionar la propagación o respuesta mediante sus áreas responsables y encargados externos. Si pierde este enlace, contacte a SAEP indicando el folio y el correo informado al crearla.
            </p>

            <div class="actions">
                <a href="{{ route('proteccion-datos.politica-privacidad') }}">Política de privacidad</a>
                <a class="button" href="{{ route('proteccion-datos.publico.crear') }}"><i class="bi bi-plus-circle"></i> Nueva solicitud</a>
            </div>
        </section>
    </main>
</body>
</html>
