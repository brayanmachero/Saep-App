<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nueva Solicitud</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:#4f46e5;padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">Nueva Solicitud Pendiente de Aprobación</p>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">Estimado/a,</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Se ha enviado una nueva solicitud que requiere su revisión y aprobación:
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">Formulario</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $respuesta->formulario->nombre }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Solicitante</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $respuesta->usuario->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $respuesta->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            <div style="text-align:center;margin-bottom:24px;">
                <a href="{{ route('respuestas.show', $respuesta) }}"
                   style="background:#4f46e5;color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;">
                    Ver Solicitud
                </a>
            </div>

            <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0;">
                Puedes aprobar o rechazar la solicitud directamente en la plataforma.<br>
                Este correo fue generado automáticamente por SAEP Platform.
            </p>
        </td>
    </tr>
    <tr>
        <td style="background:#f3f4f6;padding:16px 36px;text-align:center;">
            <p style="font-size:11px;color:#9ca3af;margin:0;">
                © {{ date('Y') }} SAEP Platform. Todos los derechos reservados.
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
