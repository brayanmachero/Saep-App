<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Recibimos tu Postulación — SAEP</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(90deg,#0b1437,#1a237e);padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">
                ✅ Postulación recibida correctamente
            </p>
        </td>
    </tr>
    <!-- Body -->
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">
                Estimado/a <strong>{{ $postulante->nombre }}</strong>,
            </p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Hemos recibido tu postulación. Nuestro equipo de RRHH revisará tu información
                y te contactará a la brevedad.
            </p>

            <!-- Folio -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f9ff;border-radius:10px;margin-bottom:24px;">
                <tr>
                    <td style="padding:20px;text-align:center;">
                        <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:0 0 8px;">Número de Folio</p>
                        <p style="font-size:28px;font-weight:800;color:#0369a1;margin:0;letter-spacing:0.1em;">{{ $postulante->folio }}</p>
                    </td>
                </tr>
            </table>

            <!-- Datos -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">RUT</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $postulante->rut }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Correo</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulante->email }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulante->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            <!-- Docs status -->
            <p style="font-size:13px;font-weight:700;color:#374151;margin:0 0 12px;">Estado de documentos:</p>
            @php
            $docsLabels = [
                'carnet_frontal'     => 'Carnet (Frontal)',
                'carnet_reverso'     => 'Carnet (Reverso)',
                'certificado_afp'    => 'Certificado AFP',
                'certificado_fonasa' => 'Certificado FONASA',
                'licencia_conducir'  => 'Licencia de Conducir',
            ];
            @endphp
            @foreach($docsLabels as $campo => $label)
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px;">
                <tr>
                    <td style="font-size:12px;color:#4b5563;">{{ $label }}</td>
                    <td style="text-align:right;">
                        @if($postulante->$campo)
                        <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;">✓ Subido</span>
                        @elseif($campo !== 'licencia_conducir')
                        <span style="font-size:11px;background:#fefce8;color:#854d0e;padding:2px 8px;border-radius:4px;font-weight:700;">Pendiente</span>
                        @else
                        <span style="font-size:11px;background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;">No aplica</span>
                        @endif
                    </td>
                </tr>
            </table>
            @endforeach

            @if(!$postulante->documentosCompletos())
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fefce8;border-radius:10px;margin-top:20px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px;">
                        <p style="font-size:13px;color:#854d0e;margin:0;">
                            <strong>⚠️ Documentos pendientes</strong><br>
                            Puedes ingresar nuevamente al portal con tu cuenta de Google para completar tu postulación.
                        </p>
                    </td>
                </tr>
            </table>
            @endif

            <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0;">
                Si tienes dudas, responde este correo o contacta directamente con RRHH.
            </p>
        </td>
    </tr>
    <!-- Footer -->
    <tr>
        <td style="background:#f9fafb;padding:20px 36px;border-top:1px solid #e5e7eb;text-align:center;">
            <p style="font-size:11px;color:#9ca3af;margin:0;">
                &copy; {{ date('Y') }} SAEP Platform · Portal de Contratación
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
