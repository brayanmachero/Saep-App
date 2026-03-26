<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Resolución de Denuncia — Ley Karin</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:#16a34a;padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.85);font-size:13px;margin:6px 0 0;">
                Resolución de Denuncia Ley Karin — {{ $caso->folio }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">Estimado/a {{ $caso->denunciante_nombre ?? 'Denunciante' }},</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Le informamos que su denuncia registrada bajo la <strong>Ley 21.643 (Ley Karin)</strong>
                ha sido <strong>resuelta</strong>. A continuación, los detalles:
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
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha Denuncia</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ \Carbon\Carbon::parse($caso->fecha_denuncia)->format('d/m/Y') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha Resolución</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $caso->fecha_resolucion ? \Carbon\Carbon::parse($caso->fecha_resolucion)->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Estado</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">
                        <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:600;">Resuelta</span>
                    </td>
                </tr>
            </table>

            @if($caso->resultado_investigacion)
            <div style="background:#f0fdf4;border-left:3px solid #16a34a;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#166534;margin:0;font-weight:600;">Resultado de la Investigación</p>
                <p style="font-size:13px;color:#14532d;margin:8px 0 0;line-height:1.6;">{{ $caso->resultado_investigacion }}</p>
            </div>
            @endif

            @if($caso->medidas_adoptadas)
            <div style="background:#eff6ff;border-left:3px solid #2563eb;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#1d4ed8;margin:0;font-weight:600;">Medidas Adoptadas</p>
                <p style="font-size:13px;color:#1e3a5f;margin:8px 0 0;line-height:1.6;">{{ $caso->medidas_adoptadas }}</p>
            </div>
            @endif

            <div style="background:#fefce8;border-left:3px solid #ca8a04;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#854d0e;margin:0;font-weight:600;">Sus derechos</p>
                <p style="font-size:12px;color:#713f12;margin:6px 0 0;line-height:1.5;">
                    De acuerdo a la Ley 21.643, si no está conforme con la resolución, tiene derecho a
                    recurrir ante la <strong>Dirección del Trabajo</strong> o los <strong>Tribunales de Justicia</strong>
                    competentes.
                </p>
            </div>

            <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0;">
                Si tiene consultas adicionales, comuníquese con el Departamento de Prevención de Riesgos.
            </p>
        </td>
    </tr>
    <tr>
        <td style="background:#f9fafb;padding:20px 36px;border-top:1px solid #e5e7eb;">
            <p style="font-size:11px;color:#9ca3af;margin:0;text-align:center;">
                Este es un mensaje confidencial generado por SAEP Platform.
                No responda a este correo.
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
