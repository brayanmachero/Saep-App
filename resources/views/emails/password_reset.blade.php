<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña — Plataforma SAEP</title>
</head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;-webkit-text-size-adjust:100%;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">

{{-- Contenedor principal --}}
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">

    @include('emails.partials.saep_header', [
        'module' => 'Seguridad de cuenta',
        'badge' => 'Restablecimiento',
        'badgeColor' => '#f59e0b',
        'accentColor' => '#f59e0b',
    ])

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
                en Plataforma SAEP. Para continuar con el proceso, haga clic en el siguiente botón:
            </p>

            @include('emails.partials.saep_button', [
                'url' => $resetUrl,
                'label' => 'Restablecer contraseña',
            ])

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

    @include('emails.partials.saep_footer', [
        'note' => 'Correo automatico de seguridad de cuenta.',
        'context' => 'Por favor no respondas a este mensaje.',
    ])
</table>

</td></tr>
</table>
</body>
</html>
