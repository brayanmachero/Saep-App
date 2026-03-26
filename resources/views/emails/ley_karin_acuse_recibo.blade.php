<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(135deg,#0b1437,#1a237e);padding:30px 40px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:20px;font-weight:700;">Acuse de Recibo</h1>
            <p style="color:rgba(255,255,255,0.7);margin:8px 0 0;font-size:14px;">Canal de Denuncia — Ley Karin</p>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:35px 40px;">
            <p style="font-size:15px;color:#1e293b;margin:0 0 20px;line-height:1.6;">
                Estimado/a denunciante,
            </p>
            <p style="font-size:15px;color:#1e293b;margin:0 0 20px;line-height:1.6;">
                Hemos recibido tu denuncia correctamente. A continuación encontrarás los datos de registro:
            </p>

            <!-- Folio Box -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
            <tr>
                <td style="background:#eef2ff;border:2px solid #c7d2fe;border-radius:10px;padding:20px;text-align:center;">
                    <p style="font-size:12px;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin:0 0 6px;font-weight:600;">Número de Folio</p>
                    <p style="font-size:24px;font-weight:800;color:#0f1b4c;margin:0;font-family:'Courier New',monospace;letter-spacing:2px;">{{ $caso->folio }}</p>
                    <p style="font-size:12px;color:#818cf8;margin:8px 0 0;">Guarda este número para futuras consultas</p>
                </td>
            </tr>
            </table>

            <!-- Details -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 16px;font-size:13px;color:#64748b;font-weight:600;width:40%;">Tipo de denuncia</td>
                    <td style="padding:10px 16px;font-size:13px;color:#1e293b;">{{ $caso->tipo_label }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 16px;font-size:13px;color:#64748b;font-weight:600;border-top:1px solid #e2e8f0;">Fecha de registro</td>
                    <td style="padding:10px 16px;font-size:13px;color:#1e293b;border-top:1px solid #e2e8f0;">{{ $caso->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 16px;font-size:13px;color:#64748b;font-weight:600;border-top:1px solid #e2e8f0;">Canal</td>
                    <td style="padding:10px 16px;font-size:13px;color:#1e293b;border-top:1px solid #e2e8f0;">{{ $caso->canal_label }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 16px;font-size:13px;color:#64748b;font-weight:600;border-top:1px solid #e2e8f0;">Estado</td>
                    <td style="padding:10px 16px;font-size:13px;color:#1e293b;border-top:1px solid #e2e8f0;">{{ $caso->estado_label }}</td>
                </tr>
            </table>

            <!-- Next Steps -->
            <h3 style="font-size:15px;color:#0f1b4c;margin:25px 0 10px;">¿Qué sigue?</h3>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:6px 0;font-size:14px;color:#374151;line-height:1.6;">
                        <strong>1.</strong> Tu denuncia será revisada por el equipo de Prevención de Riesgos.
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0;font-size:14px;color:#374151;line-height:1.6;">
                        <strong>2.</strong> Se asignará un investigador en un plazo máximo de 3 días hábiles.
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0;font-size:14px;color:#374151;line-height:1.6;">
                        <strong>3.</strong> La investigación se completará en un máximo de <strong>30 días hábiles</strong> (Ley 21.643).
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0;font-size:14px;color:#374151;line-height:1.6;">
                        <strong>4.</strong> Recibirás una notificación con la resolución final.
                    </td>
                </tr>
            </table>

            <!-- Warning -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:25px 0 0;">
            <tr>
                <td style="background:#fef2f2;border-left:3px solid #dc2626;border-radius:0 8px 8px 0;padding:14px 16px;">
                    <p style="font-size:13px;color:#991b1b;margin:0;line-height:1.6;">
                        <strong>Protección contra represalias:</strong> La Ley 21.643 prohíbe cualquier tipo de represalia contra el denunciante.
                        Si sufres alguna acción adversa, puedes denunciarlo ante la <a href="https://www.dt.gob.cl" style="color:#2563eb;">Inspección del Trabajo</a>.
                    </p>
                </td>
            </tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;">
            <p style="font-size:12px;color:#9ca3af;margin:0;line-height:1.6;">
                Este es un correo automático. Por favor no respondas a este mensaje.<br>
                Información confidencial protegida por la Ley 21.643 y Ley 21.719.<br>
                &copy; {{ date('Y') }} SAEP Platform
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
