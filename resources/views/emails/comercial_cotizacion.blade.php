<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->numero }}</title>
</head>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5;margin:0;padding:0;background:#eef1f6;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:36px 16px;">
<tr><td align="center">
<table width="680" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 2px 16px rgba(15,27,76,0.06);">
        @include('emails.partials.saep_header', [
            'module' => 'Sistema de cotizaciones comerciales',
            'badge' => 'Cotizacion enviada',
            'badgeColor' => '#0ea5e9',
            'accentColor' => '#0ea5e9',
        ])
        <tr>
        <td style="padding:28px 36px;">

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
        </td>
        </tr>
        @include('emails.partials.saep_footer', [
            'note' => 'Correo generado automaticamente desde SAEP.',
            'context' => 'La cotizacion oficial se encuentra adjunta en formato PDF.',
        ])
</table>
</td></tr>
</table>
</body>
</html>
