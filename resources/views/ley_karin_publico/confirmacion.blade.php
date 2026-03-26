<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Denuncia Registrada — Ley Karin</title>
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
            display: flex;
            flex-direction: column;
        }

        /* Top bar */
        .topbar {
            background: #0b1437; color: #fff;
            padding: .75rem 2rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .topbar img { height: 32px; filter: brightness(0) invert(1); }
        .topbar span { font-size: .85rem; font-weight: 600; opacity: .85; }

        .main-content {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
        }

        .confirm-card {
            background: #fff; border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,.06);
            padding: 3rem; max-width: 620px; width: 100%;
            text-align: center;
        }

        .confirm-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #16a34a, #15803d);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            animation: scaleIn .4s ease;
        }
        .confirm-icon i { font-size: 2.2rem; color: #fff; }

        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }

        .confirm-card h1 {
            font-size: 1.5rem; font-weight: 800; color: #0f1b4c;
            margin-bottom: .5rem;
        }
        .confirm-card > p {
            font-size: .9rem; color: #64748b; line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* Folio display */
        .folio-box {
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            border: 2px solid #c7d2fe;
            border-radius: 14px;
            padding: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .folio-box__label { font-size: .78rem; color: #6366f1; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; }
        .folio-box__value {
            font-size: 1.6rem; font-weight: 800; color: #0f1b4c;
            margin-top: .35rem; letter-spacing: .05em;
            font-family: 'Courier New', monospace;
        }
        .folio-box__hint { font-size: .78rem; color: #818cf8; margin-top: .5rem; }

        /* Details grid */
        .details {
            background: #f8fafc; border-radius: 12px;
            padding: 1.25rem; margin-bottom: 1.75rem; text-align: left;
        }
        .details__row {
            display: flex; justify-content: space-between; align-items: center;
            padding: .5rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: .85rem;
        }
        .details__row:last-child { border-bottom: none; }
        .details__label { color: #64748b; font-weight: 500; }
        .details__value { color: #1e293b; font-weight: 600; text-align: right; }

        .badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .3rem .7rem; border-radius: 8px;
            font-size: .78rem; font-weight: 600;
        }
        .badge-yellow { background: #fef9c3; color: #854d0e; }

        /* Info boxes */
        .info-box {
            background: #f0f4ff; border-left: 3px solid #0f1b4c;
            border-radius: 8px; padding: 1rem 1.15rem; margin-bottom: 1rem;
            text-align: left;
        }
        .info-box h4 { font-size: .82rem; color: #0f1b4c; margin-bottom: .35rem; display: flex; align-items: center; gap: .4rem; }
        .info-box p { font-size: .8rem; color: #4b5563; line-height: 1.6; margin: 0; }
        .info-box a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .info-box a:hover { text-decoration: underline; }

        .warning-box {
            background: #fef2f2; border-left: 3px solid #dc2626;
        }
        .warning-box h4 { color: #dc2626; }

        /* Actions */
        .actions {
            display: flex; gap: .75rem; justify-content: center; margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .7rem 1.5rem; border-radius: 10px;
            font-family: inherit; font-size: .88rem; font-weight: 600;
            cursor: pointer; transition: all .2s; border: none;
            text-decoration: none;
        }
        .btn-primary { background: #0f1b4c; color: #fff; }
        .btn-primary:hover { background: #1a2766; }
        .btn-outline { background: #fff; border: 1.5px solid #e2e8f0; color: #64748b; }
        .btn-outline:hover { border-color: #94a3b8; color: #374151; }

        .footer-note {
            text-align: center; padding: 1.5rem;
            font-size: .75rem; color: #9ca3af;
        }

        @media (max-width: 600px) {
            .confirm-card { padding: 2rem 1.5rem; }
            .folio-box__value { font-size: 1.25rem; }
            .details__row { flex-direction: column; align-items: flex-start; gap: .15rem; }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
    <span>Canal de Denuncia — Ley Karin</span>
</div>

<div class="main-content">
    <div class="confirm-card">
        <div class="confirm-icon">
            <i class="bi bi-check-lg"></i>
        </div>

        <h1>Denuncia Registrada Exitosamente</h1>
        <p>
            Tu denuncia ha sido recibida y será atendida con la máxima confidencialidad.
            Guarda el número de folio para consultar el estado de tu caso.
        </p>

        <!-- Folio -->
        <div class="folio-box">
            <div class="folio-box__label">Número de folio</div>
            <div class="folio-box__value">{{ $caso->folio }}</div>
            <div class="folio-box__hint">
                <i class="bi bi-clipboard"></i> Guarda este número para futuras consultas
            </div>
        </div>

        <!-- Details -->
        <div class="details">
            <div class="details__row">
                <span class="details__label">Tipo de denuncia</span>
                <span class="details__value">{{ $caso->tipo_label }}</span>
            </div>
            <div class="details__row">
                <span class="details__label">Fecha de registro</span>
                <span class="details__value">{{ $caso->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="details__row">
                <span class="details__label">Canal</span>
                <span class="details__value">{{ $caso->canal_label }}</span>
            </div>
            <div class="details__row">
                <span class="details__label">Estado</span>
                <span class="details__value"><span class="badge badge-yellow"><i class="bi bi-clock"></i> {{ $caso->estado_label }}</span></span>
            </div>
            <div class="details__row">
                <span class="details__label">Plazo de investigación</span>
                <span class="details__value">30 días hábiles (Ley 21.643)</span>
            </div>
        </div>

        <!-- Info boxes -->
        <div class="info-box">
            <h4><i class="bi bi-envelope-check"></i> Notificación enviada</h4>
            <p>
                El equipo de Prevención de Riesgos ha sido notificado automáticamente
                y se comunicará contigo a través del correo registrado.
            </p>
        </div>

        <div class="info-box warning-box">
            <h4><i class="bi bi-shield-check"></i> Protección contra represalias</h4>
            <p>
                La Ley 21.643 prohíbe cualquier tipo de represalia contra el denunciante.
                Si sufres alguna acción adversa, puedes denunciarlo ante la
                <a href="https://www.dt.gob.cl" target="_blank" rel="noopener">Inspección del Trabajo</a>.
            </p>
        </div>

        <!-- Actions -->
        <div class="actions">
            <button type="button" class="btn btn-outline" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <a href="{{ route('ley-karin-publico.inicio') }}" class="btn btn-primary">
                <i class="bi bi-house"></i> Volver al inicio
            </a>
        </div>
    </div>
</div>

<div class="footer-note">
    <i class="bi bi-lock-fill"></i> Información confidencial · Ley 21.643 · Ley 21.719<br>
    &copy; {{ date('Y') }} SAEP Platform. Todos los derechos reservados.
</div>
</body>
</html>
