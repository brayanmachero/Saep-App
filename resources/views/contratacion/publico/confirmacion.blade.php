<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Postulación Recibida — SAEP</title>
    <link rel="icon" href="{{ asset('brand/wp/saep_favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0b1437 0%, #1a237e 60%, #0ea5e9 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 2rem;
        }
        .card {
            background: #fff; border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            padding: 3rem 2.5rem; max-width: 560px; width: 100%;
            text-align: center;
        }
        .check-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pop .4s cubic-bezier(.175,.885,.32,1.275);
        }
        .check-circle i { font-size: 2.2rem; color: #fff; }
        @keyframes pop {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        h1 { font-size: 1.6rem; font-weight: 800; color: #0f1b4c; margin-bottom: .5rem; }
        .subtitle { font-size: .95rem; color: #6b7280; line-height: 1.6; margin-bottom: 2rem; }

        /* Folio */
        .folio-box {
            background: #f0f9ff; border: 1px solid #bae6fd;
            border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 2rem;
        }
        .folio-box__label { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
        .folio-box__value { font-size: 1.8rem; font-weight: 800; color: #0369a1; letter-spacing: .1em; }

        /* Checklist de documentos */
        .doc-list { text-align: left; margin-bottom: 2rem; }
        .doc-list h3 { font-size: .9rem; font-weight: 700; color: #374151; margin-bottom: .75rem; }
        .doc-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .5rem .75rem; border-radius: 8px; margin-bottom: .4rem;
            font-size: .85rem;
        }
        .doc-item.ok { background: #f0fdf4; color: #166534; }
        .doc-item.missing { background: #fefce8; color: #854d0e; }
        .doc-item i { flex-shrink: 0; }

        /* Info */
        .info-box {
            background: #f8fafc; border-left: 4px solid #0ea5e9;
            border-radius: 8px; padding: 1rem 1.25rem;
            text-align: left; margin-bottom: 2rem;
            font-size: .83rem; color: #4b5563; line-height: 1.6;
        }
        .info-box strong { color: #0369a1; display: block; margin-bottom: .3rem; }

        /* Actions */
        .actions { display: flex; flex-direction: column; gap: .75rem; }
        .btn-primary {
            background: linear-gradient(90deg, #0ea5e9, #0369a1);
            color: #fff; padding: .85rem 2rem;
            border-radius: 10px; border: none; font-size: .95rem;
            font-weight: 700; cursor: pointer;
            text-decoration: none; display: block;
        }
        .btn-secondary {
            background: #f1f5f9; color: #475569;
            padding: .75rem 2rem; border-radius: 10px;
            border: none; font-size: .9rem; font-weight: 600;
            text-decoration: none; display: block; width: 100%; cursor: pointer;
        }
        .btn-secondary:hover { background: #e2e8f0; }

        .footer-note { font-size: .73rem; color: #9ca3af; margin-top: 1.5rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="check-circle">
        <i class="bi bi-check-lg"></i>
    </div>

    <h1>¡Postulación Recibida!</h1>
    <p class="subtitle">
        Tu información ha sido enviada correctamente. El equipo de RRHH revisará tus documentos
        y te contactará al correo <strong>{{ $postulante->email }}</strong>.
    </p>

    <div class="folio-box">
        <div class="folio-box__label">N° de Folio</div>
        <div class="folio-box__value">{{ $postulante->folio }}</div>
    </div>

    <!-- Estado documentos -->
    <div class="doc-list">
        <h3><i class="bi bi-folder2-open"></i> Estado de tus Documentos</h3>
        @php
            $docsLabels = [
                'carnet_frontal'            => 'Carnet (Frontal)',
                'carnet_reverso'            => 'Carnet (Reverso)',
                'certificado_afp'           => 'Certificado AFP',
                'certificado_fonasa'        => 'Certificado FONASA',
                'licencia_conducir_frontal' => 'Lic. Conducir (Frontal)',
                'licencia_conducir_reverso' => 'Lic. Conducir (Reverso)',
            ];
        @endphp
        @foreach($docsLabels as $campo => $label)
        @if($postulante->$campo)
        <div class="doc-item ok">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ $label }}</span>
        </div>
        @else
        @if($campo !== 'licencia_conducir_frontal' && $campo !== 'licencia_conducir_reverso')
        <div class="doc-item missing">
            <i class="bi bi-clock-history"></i>
            <span>{{ $label }} — Pendiente de subir</span>
        </div>
        @endif
        @endif
        @endforeach
    </div>

    @if(!$postulante->documentosCompletos())
    <div class="info-box">
        <strong><i class="bi bi-info-circle-fill"></i> Documentos faltantes</strong>
        Aún tienes documentos obligatorios por subir. Puedes volver al formulario en cualquier
        momento usando tu cuenta de Google para completar tu postulación.
    </div>
    @else
    <div class="info-box" style="border-color:#22c55e;">
        <strong style="color:#166534;"><i class="bi bi-check2-all"></i> Documentación completa</strong>
        Hemos recibido todos los documentos requeridos. El equipo de RRHH comenzará la revisión
        a la brevedad y te notificará por correo electrónico.
    </div>
    @endif

    <div class="actions">
        @if(!$postulante->documentosCompletos())
        <a href="{{ route('contratacion-publico.formulario') }}" class="btn-primary">
            <i class="bi bi-arrow-up-circle"></i> Completar Documentos
        </a>
        @endif
        <form method="POST" action="{{ route('contratacion-publico.logout') }}">
            @csrf
            <button type="submit" class="btn-secondary">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </button>
        </form>
    </div>

    <p class="footer-note">
        <i class="bi bi-lock-fill"></i> Folio {{ $postulante->folio }} · {{ $postulante->created_at->format('d/m/Y H:i') }}<br>
        &copy; {{ date('Y') }} SAEP Platform. Todos los derechos reservados.
    </p>
</div>
</body>
</html>
