<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista previa - Reporte Observaciones CCU</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#111827; font-family:Arial,Helvetica,sans-serif; }
        .toolbar { position:sticky;top:0;z-index:2;background:#21064f;color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px; }
        .toolbar strong { font-size:14px; }
        .toolbar span { font-size:12px;color:#ddd6fe; }
        .toolbar a { color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.38);border-radius:5px;padding:8px 11px;font-size:12px;font-weight:700; }
        .frame { max-width:850px;margin:24px auto 42px;padding:0 14px;box-shadow:0 10px 45px rgba(0,0,0,.4); }
        @media (max-width:650px) { .toolbar { align-items:flex-start;flex-direction:column; } .frame { margin-top:14px;padding:0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>Vista previa del correo</strong><br><span>Se enviará al usuario autenticado con los filtros activos y el Excel adjunto.</span></div>
        <a href="{{ route('pdr-ccu-dashboard.index', $filters) }}">Volver al dashboard</a>
    </div>
    <div class="frame">{!! $emailHtml !!}</div>
</body>
</html>
