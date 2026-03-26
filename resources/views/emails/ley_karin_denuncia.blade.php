<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nueva Denuncia Ley Karin</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:#dc2626;padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">
                🔒 Nueva Denuncia Ley Karin — Confidencial
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">Estimado/a,</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Se ha registrado una nueva denuncia bajo la <strong>Ley 21.643 (Ley Karin)</strong> que requiere su atención:
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">Folio</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $caso->folio }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Tipo</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $caso->tipo_label }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Centro de Costo</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $caso->centroCosto->nombre ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha Denuncia</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $caso->fecha_denuncia->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Estado</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">
                        <span style="background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:600;">Recibida</span>
                    </td>
                </tr>
            </table>

            <div style="background:#fef2f2;border-left:3px solid #dc2626;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#991b1b;margin:0;font-weight:600;">⚠️ Recordatorio de plazos legales</p>
                <p style="font-size:12px;color:#7f1d1d;margin:6px 0 0;line-height:1.5;">
                    • 3 días hábiles para adoptar medidas de resguardo<br>
                    • 5 días hábiles para derivar a DT si no se investiga internamente<br>
                    • 30 días hábiles máximo para concluir la investigación
                </p>
            </div>

            <div style="text-align:center;margin-bottom:24px;">
                <a href="{{ route('ley-karin.show', $caso) }}"
                   style="background:#dc2626;color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;">
                    Ver Expediente
                </a>
            </div>

            <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0;">
                Este expediente es <strong>confidencial</strong>. Solo personal autorizado debe acceder a su contenido.<br>
                Correo generado automáticamente por SAEP Platform.
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
