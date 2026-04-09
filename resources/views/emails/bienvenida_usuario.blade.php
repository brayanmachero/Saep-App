<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a SAEP Platform</title>
</head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;-webkit-text-size-adjust:100%;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">

{{-- Contenedor principal --}}
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">

    {{-- Header con logo --}}
    <tr>
        <td style="background:#0f1b4c;padding:32px 40px;text-align:center;">
            <img src="https://saep.cl/wp-content/uploads/2023/11/Logo-Saep_footer.svg" alt="SAEP" width="120" style="display:inline-block;">
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
                Bienvenido/a a SAEP Platform
            </h1>
            <p style="font-size:14px;color:#64748b;margin:0 0 28px;line-height:1.5;">
                Plataforma de gestión de prevención, seguridad y salud ocupacional
            </p>

            <p style="font-size:15px;color:#1e293b;margin:0 0 20px;line-height:1.6;">
                Estimado/a <strong>{{ $userName }}</strong>,
            </p>
            <p style="font-size:14px;color:#475569;line-height:1.7;margin:0 0 28px;">
                Le informamos que se ha creado una cuenta a su nombre en SAEP Platform.
                A continuación encontrará sus credenciales de acceso provisorias:
            </p>

            {{-- Credenciales --}}
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:28px;">
                <tr>
                    <td colspan="2" style="background:#f8fafc;padding:12px 20px;border-bottom:1px solid #e2e8f0;">
                        <p style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;margin:0;">Credenciales de acceso</p>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 20px;font-size:13px;color:#64748b;width:40%;vertical-align:middle;">Correo electrónico</td>
                    <td style="padding:14px 20px;font-size:14px;font-weight:600;color:#1e293b;">{{ $userEmail }}</td>
                </tr>
                <tr>
                    <td style="padding:14px 20px;font-size:13px;color:#64748b;vertical-align:middle;">Contraseña provisoria</td>
                    <td style="padding:14px 20px;">
                        <code style="font-size:15px;font-weight:700;color:#0f1b4c;background:#f1f5f9;padding:6px 14px;border-radius:6px;letter-spacing:1.5px;display:inline-block;">{{ $tempPassword }}</code>
                    </td>
                </tr>
            </table>

            {{-- Botón --}}
            <div style="text-align:center;margin-bottom:28px;">
                <a href="{{ $loginUrl }}"
                   style="background:#0f1b4c;color:#ffffff;padding:14px 40px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;letter-spacing:0.02em;">
                    Acceder a la plataforma
                </a>
            </div>

            {{-- Aviso de seguridad --}}
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;margin-bottom:28px;">
                <tr>
                    <td style="padding:16px 20px;">
                        <p style="font-size:13px;font-weight:600;color:#92400e;margin:0 0 4px;">Aviso de seguridad</p>
                        <p style="font-size:13px;color:#a16207;line-height:1.6;margin:0;">
                            Esta es una contraseña provisoria. Por su seguridad, le recomendamos
                            cambiarla inmediatamente después de su primer inicio de sesión
                            a través de la opción <em>"Cambiar contraseña"</em> disponible en la plataforma.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="font-size:13px;color:#94a3b8;line-height:1.6;margin:0;">
                Si usted no solicitó esta cuenta o tiene alguna consulta, comuníquese
                con el administrador del sistema.
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
