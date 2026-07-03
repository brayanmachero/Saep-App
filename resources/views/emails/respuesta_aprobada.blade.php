<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Solicitud {{ $respuesta->estado }}</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:0;">
@php $aprobada = $respuesta->estado === 'Aprobado'; @endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @include('emails.partials.saep_header', [
        'module' => 'Flujo de aprobaciones',
        'badge' => $aprobada ? 'Aprobada' : 'Rechazada',
        'badgeColor' => $aprobada ? '#059669' : '#dc2626',
        'accentColor' => $aprobada ? '#059669' : '#dc2626',
    ])
    {{-- Body --}}
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 16px;">Estimado/a {{ $respuesta->usuario->name ?? '' }},</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Tu solicitud ha sido <strong style="color:{{ $aprobada ? '#059669' : '#dc2626' }};">{{ $aprobada ? 'aprobada' : 'rechazada' }}</strong>:
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:35%;">Formulario</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $respuesta->formulario->nombre }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Estado</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:{{ $aprobada ? '#059669' : '#dc2626' }};">
                        {{ $respuesta->estado }}
                    </td>
                </tr>
                @if($respuesta->aprobaciones->last()?->comentario)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Comentario</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;font-style:italic;">{{ $respuesta->aprobaciones->last()->comentario }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha resolución</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ now()->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            @include('emails.partials.saep_button', [
                'url' => route('respuestas.show', $respuesta),
                'label' => 'Ver solicitud',
            ])

            <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0;">
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
