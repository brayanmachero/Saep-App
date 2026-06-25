<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña — SAEP Platform</title>
</head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;-webkit-text-size-adjust:100%;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">

{{-- Contenedor principal --}}
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">

    {{-- Header con logo --}}
    <tr>
        <td style="background:#0f1b4c;padding:32px 40px;text-align:center;">
            <img src="{{ asset('brand/wp/Logo-Saep_footer.svg') }}" alt="SAEP" width="120" style="display:inline-block;">
        </td>
    </tr>

    {{-- Barra naranja decorativa --}}
    <tr>
        <td style="height:4px;background:linear-gradient(90deg,#f97316,#fb923c,#f97316);"></td>
    </tr>

    {{-- Cuerpo --}}
    <tr>
        <td style="padding:40px 40px 32px;">

            <h1 style="font-size:22px;font-weight:700;color:#0f1b4c;margin:0 0 8px;">
                Solicitud de restablecimiento de contraseña
            </h1>
            <p style="font-size:14px;color:#64748b;margin:0 0 28px;line-height:1.5;">
                Se ha recibido una solicitud asociada a su cuenta
            </p>

            <p style="font-size:15px;color:#1e293b;margin:0 0 20px;line-height:1.6;">
                Estimado/a <strong>{{ $userName }}</strong>,
            </p>
            <p style="font-size:14px;color:#475569;line-height:1.7;margin:0 0 28px;">
                Hemos recibido una solicitud para restablecer la contraseña de su cuenta
                en SAEP Platform. Para continuar con el proceso, haga clic en el siguiente botón:
            </p>

            {{-- Botón --}}
            <div style="text-align:center;margin-bottom:28px;">
                <a href="{{ $resetUrl }}"
                   style="background:#0f1b4c;color:#ffffff;padding:14px 40px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;letter-spacing:0.02em;">
                    Restablecer contraseña
                </a>
            </div>

            {{-- Información de expiración --}}
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;margin-bottom:28px;">
                <tr>
                    <td style="padding:16px 20px;">
                        <p style="font-size:13px;font-weight:600;color:#92400e;margin:0 0 4px;">Información importante</p>
                        <p style="font-size:13px;color:#a16207;line-height:1.6;margin:0;">
                            Este enlace tiene una validez de <strong>60 minutos</strong> desde el momento
                            de su generación. Transcurrido ese plazo, deberá solicitar uno nuevo.<br><br>
                            Si usted no realizó esta solicitud, puede ignorar este correo con total
                            seguridad. Su contraseña actual no será modificada.
                        </p>
                    </td>
                </tr>
            </table>

            {{-- URL alternativa --}}
            <p style="font-size:12px;color:#94a3b8;line-height:1.5;margin:0 0 6px;">
                Si el botón no funciona, copie y pegue la siguiente dirección en su navegador:
            </p>
            <p style="font-size:11px;color:#64748b;word-break:break-all;background:#f8fafc;border:1px solid #e2e8f0;padding:12px 16px;border-radius:8px;margin:0 0 24px;line-height:1.6;">
                {{ $resetUrl }}
            </p>
        </td>
    </tr>

    {{-- Separador --}}
    <tr>
        <td style="padding:0 40px;">
            <div style="border-top:1px solid #f1f5f9;"></div>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:24px 40px 32px;text-align:center;">
            <p style="font-size:11px;color:#94a3b8;margin:0 0 8px;line-height:1.6;">
                Este correo fue enviado automáticamente por SAEP Platform.<br>
                Por favor no responda a este mensaje.
            </p>
            <p style="font-size:11px;color:#cbd5e1;margin:0;">
                &copy; {{ date('Y') }} S.A.E.P. Ltda. &mdash; Todos los derechos reservados<br>
                <a href="https://saep.cl" style="color:#94a3b8;text-decoration:none;">saep.cl</a>
            </p>
        </td>
    </tr>
</table>

</td></tr>
</table>
</body>
</html>
