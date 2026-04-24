<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nuevo Postulante — SAEP</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(90deg,#0b1437,#1a237e);padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">
                🔔 Nuevo postulante registrado
            </p>
        </td>
    </tr>
    <!-- Body -->
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">
                Se ha registrado un nuevo postulante en el Portal de Contratación.
            </p>

            <!-- Folio -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f9ff;border-radius:10px;margin-bottom:24px;">
                <tr>
                    <td style="padding:20px;text-align:center;">
                        <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:0 0 8px;">Folio</p>
                        <p style="font-size:28px;font-weight:800;color:#0369a1;margin:0;letter-spacing:0.1em;">{{ $postulante->folio }}</p>
                    </td>
                </tr>
            </table>

            <!-- Datos personales -->
            <p style="font-size:13px;font-weight:700;color:#374151;margin:0 0 8px;">Datos personales</p>
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">Nombre</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $postulante->nombre }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">RUT</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;font-family:monospace;">{{ $postulante->rut }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Correo</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulante->email }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha postulación</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $postulante->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            <!-- Documentos -->
            <p style="font-size:13px;font-weight:700;color:#374151;margin:0 0 12px;">
                Documentos subidos
                @if($postulante->documentosCompletos())
                <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;margin-left:8px;">Completos</span>
                @else
                <span style="font-size:11px;background:#fefce8;color:#854d0e;padding:2px 8px;border-radius:4px;font-weight:700;margin-left:8px;">Incompletos</span>
                @endif
            </p>

            @php
            $docsLabels = [
                'carnet_frontal'     => ['label' => 'Carnet (Frontal)',  'required' => true],
                'carnet_reverso'     => ['label' => 'Carnet (Reverso)',  'required' => true],
                'certificado_afp'    => ['label' => 'AFP',               'required' => true],
                'certificado_fonasa' => ['label' => 'FONASA',            'required' => true],
                'licencia_conducir'  => ['label' => 'Lic. Conducir',     'required' => false],
            ];
            @endphp
            @foreach($docsLabels as $campo => $info)
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px;">
                <tr>
                    <td style="font-size:12px;color:#4b5563;">{{ $info['label'] }}</td>
                    <td style="text-align:right;">
                        @if($postulante->$campo)
                        <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;">✓ Subido</span>
                        @elseif($info['required'])
                        <span style="font-size:11px;background:#fefce8;color:#854d0e;padding:2px 8px;border-radius:4px;">Pendiente</span>
                        @else
                        <span style="font-size:11px;background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:4px;">—</span>
                        @endif
                    </td>
                </tr>
            </table>
            @endforeach

            <!-- CTA -->
            @php $panelUrl = config('app.url') . '/contratacion/' . $postulante->id; @endphp
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                <tr>
                    <td align="center">
                        <a href="{{ $panelUrl }}"
                           style="display:inline-block;background:#0ea5e9;color:white;font-size:14px;font-weight:700;
                                  padding:12px 28px;border-radius:10px;text-decoration:none;letter-spacing:0.03em;">
                            Ver en panel RRHH →
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <!-- Footer -->
    <tr>
        <td style="background:#f9fafb;padding:20px 36px;border-top:1px solid #e5e7eb;text-align:center;">
            <p style="font-size:11px;color:#9ca3af;margin:0;">
                &copy; {{ date('Y') }} SAEP Platform · Notificación automática RRHH
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
