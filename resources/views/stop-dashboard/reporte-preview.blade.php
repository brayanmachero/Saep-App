<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vista Previa — Reporte STO CCU {{ $frecuencia }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; }

    /* ── Toolbar ── */
    .preview-toolbar {
        position: sticky; top: 0; z-index: 100;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-bottom: 1px solid rgba(255,255,255,.08);
        padding: 12px 24px;
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        backdrop-filter: blur(20px);
        box-shadow: 0 4px 20px rgba(0,0,0,.3);
    }
    .toolbar-title {
        font-size: 14px; font-weight: 600; color: #e2e8f0;
        display: flex; align-items: center; gap: 8px;
        white-space: nowrap;
    }
    .toolbar-title i { color: #22c55e; font-size: 18px; }
    .toolbar-badge {
        background: rgba(34,197,94,.15); color: #22c55e;
        padding: 2px 10px; border-radius: 20px;
        font-size: 11px; font-weight: 600;
    }
    .toolbar-info {
        font-size: 12px; color: #94a3b8;
        display: flex; align-items: center; gap: 6px;
    }
    .toolbar-separator { width: 1px; height: 24px; background: rgba(255,255,255,.1); }

    /* ── Send form ── */
    .send-form {
        display: flex; align-items: center; gap: 8px; margin-left: auto;
    }
    .send-form select,
    .send-form input[type="email"] {
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
        color: #e2e8f0; padding: 7px 12px; border-radius: 8px;
        font-size: 13px; outline: none; transition: border-color .2s;
    }
    .send-form input[type="email"] { width: 260px; }
    .send-form select { width: 120px; cursor: pointer; }
    .send-form select option { background: #1e293b; }
    .send-form input:focus, .send-form select:focus {
        border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,.15);
    }
    .send-form input::placeholder { color: #64748b; }
    .btn-send {
        background: linear-gradient(135deg, #1B5E20, #2E7D32);
        color: #fff; border: none; padding: 8px 20px;
        border-radius: 8px; font-size: 13px; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; gap: 6px;
        transition: all .2s; white-space: nowrap;
    }
    .btn-send:hover { background: linear-gradient(135deg, #2E7D32, #388E3C); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34,197,94,.3); }
    .btn-send:active { transform: translateY(0); }
    .btn-send:disabled { opacity: .5; cursor: not-allowed; transform: none; }
    .btn-back {
        background: rgba(255,255,255,.06); color: #94a3b8; border: 1px solid rgba(255,255,255,.1);
        padding: 7px 14px; border-radius: 8px; font-size: 13px; text-decoration: none;
        display: flex; align-items: center; gap: 4px; transition: all .2s; white-space: nowrap;
    }
    .btn-back:hover { color: #e2e8f0; border-color: rgba(255,255,255,.2); }

    /* ── Alert ── */
    .toolbar-alert {
        width: 100%; padding: 8px 16px; border-radius: 8px;
        font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 8px;
        animation: slideDown .3s ease;
    }
    .toolbar-alert.success { background: rgba(34,197,94,.12); color: #22c55e; border: 1px solid rgba(34,197,94,.2); }
    .toolbar-alert.error { background: rgba(239,68,68,.12); color: #ef4444; border: 1px solid rgba(239,68,68,.2); }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* ── Email frame ── */
    .email-frame {
        max-width: 760px; margin: 24px auto; padding: 0 16px 40px;
    }
    .email-frame-inner {
        border-radius: 12px; overflow: hidden;
        box-shadow: 0 8px 40px rgba(0,0,0,.4);
        border: 1px solid rgba(255,255,255,.06);
    }

    /* ── Loading spinner ── */
    .spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div class="preview-toolbar">
    <div class="toolbar-title">
        <i class="bi bi-envelope-paper-fill"></i>
        Vista Previa — Reporte {{ $frecuencia }}
    </div>
    <span class="toolbar-badge">{{ $totalRows }} tarjetas</span>
    <div class="toolbar-info">
        <i class="bi bi-calendar3"></i> {{ $periodo }}
    </div>
    <div class="toolbar-separator"></div>

    <form action="{{ route('stop-dashboard.reporte.test-send') }}" method="POST" class="send-form" id="sendForm">
        @csrf
        @if($mes)<input type="hidden" name="mes" value="{{ $mes }}">@endif
        @if($anio)<input type="hidden" name="anio" value="{{ $anio }}">@endif

        <select name="frecuencia">
            <option value="Semanal" {{ $frecuencia === 'Semanal' ? 'selected' : '' }}>Semanal</option>
            <option value="Mensual" {{ $frecuencia === 'Mensual' ? 'selected' : '' }}>Mensual</option>
        </select>

        <input type="email" name="email" placeholder="correo@destino.com" required autocomplete="email">

        <button type="submit" class="btn-send" id="btnSend">
            <i class="bi bi-send-fill"></i>
            <span>Enviar Prueba</span>
            <div class="spinner" id="spinner"></div>
        </button>
    </form>

    <a href="{{ route('stop-dashboard') }}" class="btn-back">
        <i class="bi bi-arrow-left"></i> Dashboard
    </a>

    @if($success)
    <div class="toolbar-alert success">
        <i class="bi bi-check-circle-fill"></i> {{ $success }}
    </div>
    @endif
    @if($error)
    <div class="toolbar-alert error">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ $error }}
    </div>
    @endif
</div>

<div class="email-frame">
    <div class="email-frame-inner">
        {!! $emailHtml !!}
    </div>
</div>

<script>
document.getElementById('sendForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnSend');
    const spinner = document.getElementById('spinner');
    btn.disabled = true;
    btn.querySelector('span').textContent = 'Enviando...';
    spinner.style.display = 'inline-block';
});
</script>
</body>
</html>
