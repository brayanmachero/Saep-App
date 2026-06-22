<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->numero }}</title>
</head>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5;margin:0;padding:0;background:#f8fafc;">
    <div style="max-width:680px;margin:0 auto;background:#ffffff;padding:28px;border:1px solid #e5e7eb;">
        <div style="border-bottom:3px solid #0f1b4c;padding-bottom:16px;margin-bottom:20px;">
            <h1 style="margin:0;color:#0f1b4c;font-size:22px;">SAEP</h1>
            <p style="margin:4px 0 0;color:#64748b;font-size:13px;">Sistema de Cotizaciones Comerciales</p>
        </div>

        <p>Estimados,</p>

        @if(!empty($mensajeUsuario))
            <p>{{ $mensajeUsuario }}</p>
        @else
            <p>Adjuntamos cotización comercial solicitada para su revisión.</p>
        @endif

        <table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px;">
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;color:#64748b;">Número</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;font-weight:bold;">{{ $cotizacion->numero }}</td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;color:#64748b;">Cliente</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;">{{ $cotizacion->cliente?->nombre_comercial ?? $cotizacion->cliente?->nombre }}</td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;color:#64748b;">Cargo</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;">{{ $cotizacion->cargo }}</td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;color:#64748b;">Modalidad</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;">{{ $cotizacion->modalidad?->codigo }}</td>
            </tr>
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;color:#64748b;">Precio Venta</td>
                <td style="padding:8px;border-bottom:1px solid #e5e7eb;font-weight:bold;">${{ number_format($cotizacion->precio_venta, 0, ',', '.') }}</td>
            </tr>
        </table>

        <p style="font-size:13px;color:#64748b;">Este correo fue generado automáticamente desde SAEP. La cotización oficial se encuentra adjunta en formato PDF.</p>

        <p style="margin-top:24px;">Saludos,<br><strong>Equipo SAEP</strong></p>
    </div>
</body>
</html>
