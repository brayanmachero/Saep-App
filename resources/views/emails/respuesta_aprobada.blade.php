<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Solicitud {{ $respuesta->estado }}</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:0;">
@php $aprobada = $respuesta->estado === 'Aprobado'; @endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    {{-- Header --}}
    <tr>
        <td style="background:#0f1b4c;padding:0;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:24px 36px;">
                        <h1 style="color:white;font-size:22px;margin:0;letter-spacing:2px;font-weight:800;">SAEP</h1>
                        <p style="color:rgba(255,255,255,0.55);font-size:10px;margin:4px 0 0;text-transform:uppercase;letter-spacing:1px;">Sistema Automatizado de Ejecución y Prevención</p>
                    </td>
                    <td style="padding:24px 36px;text-align:right;vertical-align:middle;">
                        <span style="background:{{ $aprobada ? '#059669' : '#dc2626' }};color:white;padding:6px 14px;border-radius:50px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                            {{ $aprobada ? 'Aprobada' : 'Rechazada' }}
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    {{-- Accent stripe --}}
    <tr>
        <td style="background:{{ $aprobada ? '#059669' : '#dc2626' }};height:4px;font-size:0;line-height:0;">&nbsp;</td>
    </tr>
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

            <div style="text-align:center;margin-bottom:24px;">
                <a href="{{ route('respuestas.show', $respuesta) }}"
                   style="background:#0f1b4c;color:white;padding:14px 36px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;">
                    Ver Solicitud
                </a>
            </div>

            <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0;">
                Este correo fue generado automáticamente por SAEP.
            </p>
        </td>
    </tr>
    {{-- Footer --}}
    <tr>
        <td style="background:#0f1b4c;padding:16px 36px;text-align:center;">
            <p style="font-size:11px;color:rgba(255,255,255,0.5);margin:0;">
                © {{ date('Y') }} SAEP Platform · saep.bmachero.com
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
