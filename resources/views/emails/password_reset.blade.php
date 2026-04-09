<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Restablecer Contraseña</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:linear-gradient(135deg,#0f1b4c,#1e3a8a);padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">Solicitud de restablecimiento de contraseña</p>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">Hola {{ $userName }},</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Recibimos una solicitud para restablecer la contraseña de tu cuenta en SAEP Platform.
                Haz clic en el botón de abajo para crear una nueva contraseña:
            </p>

            <div style="text-align:center;margin-bottom:24px;">
                <a href="{{ $resetUrl }}"
                   style="background:#0f1b4c;color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;">
                    Restablecer Contraseña
                </a>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef9f0;border:1px solid #fde68a;border-radius:10px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px;">
                        <p style="font-size:13px;color:#92400e;line-height:1.6;margin:0;">
                            ⏱ Este enlace expirará en <strong>60 minutos</strong>.<br>
                            🔒 Si no solicitaste este cambio, puedes ignorar este correo con total seguridad.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="font-size:12px;color:#9ca3af;line-height:1.6;margin:0 0 8px;">
                Si el botón no funciona, copia y pega este enlace en tu navegador:
            </p>
            <p style="font-size:11px;color:#6b7280;word-break:break-all;background:#f9fafb;padding:12px;border-radius:8px;margin:0;">
                {{ $resetUrl }}
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
