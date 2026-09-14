<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servicio temporalmente no disponible · SAEP</title>
    <style>
        :root { color-scheme: light; font-family: Inter, Arial, sans-serif; }
        body { align-items: center; background: #f3f4f6; color: #111827; display: flex; justify-content: center; margin: 0; min-height: 100vh; padding: 1.5rem; }
        main { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 16px 45px rgba(15, 27, 76, .12); max-width: 520px; padding: 2.25rem; text-align: center; }
        .icon { align-items: center; background: #fff7ed; border-radius: 50%; color: #ea580c; display: inline-flex; font-size: 1.75rem; height: 3.5rem; justify-content: center; width: 3.5rem; }
        h1 { font-size: 1.35rem; margin: 1rem 0 .5rem; }
        p { color: #64748b; line-height: 1.55; margin: 0; }
        a { background: #0f1b4c; border-radius: 9px; color: #fff; display: inline-block; font-weight: 700; margin-top: 1.35rem; padding: .7rem 1rem; text-decoration: none; }
    </style>
</head>
<body>
    <main role="alert">
        <div class="icon" aria-hidden="true">!</div>
        <h1>No fue posible completar la solicitud</h1>
        <p>La información no se modificó. Intenta nuevamente en unos minutos; si el problema continúa, informa la hora aproximada de este aviso.</p>
        <a href="{{ url('/') }}">Ir al inicio</a>
    </main>
</body>
</html>
