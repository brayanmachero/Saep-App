<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nueva Solicitud</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @include('emails.partials.saep_header', [
        'module' => 'Flujo de aprobaciones',
        'badge' => 'Nueva solicitud',
        'badgeColor' => '#f97316',
    ])
    {{-- Body --}}
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 16px;">Estimado/a,</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Se ha registrado una nueva solicitud que requiere su revisión y aprobación:
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:35%;">Formulario</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $respuesta->formulario->nombre }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Solicitante</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $respuesta->usuario->name ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Departamento</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $respuesta->usuario->departamento->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $respuesta->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            @include('emails.partials.saep_button', [
                'url' => route('respuestas.show', $respuesta),
                'label' => 'Ver solicitud',
            ])

            <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0;">
                Puedes aprobar o rechazar la solicitud directamente en la plataforma.<br>
                Este correo fue generado automáticamente por SAEP.
            </p>
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo generado automaticamente por el flujo de aprobaciones.',
        'siteUrl' => config('app.url'),
        'siteLabel' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'SAEP',
    ])
</table>
</td></tr>
</table>
</body>
</html>
