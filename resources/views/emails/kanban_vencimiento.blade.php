<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Alerta de Vencimiento</title></head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">
    <tr><td style="background:#0f1b4c;padding:28px 40px;text-align:center;">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo-Saep_footer.svg" alt="SAEP" width="100" style="display:inline-block;">
    </td></tr>
    <tr><td style="height:4px;background:linear-gradient(90deg,#f97316,#fb923c,#f97316);"></td></tr>
    <tr><td style="padding:36px 40px 28px;">
        <h1 style="font-size:20px;font-weight:700;color:#0f1b4c;margin:0 0 6px;">
            @if($diasRestantes <= 0) 🔴 Tarea vencida @elseif($diasRestantes == 1) ⚠️ Tarea vence mañana @else ⚠️ Tarea vence en {{ $diasRestantes }} días @endif
        </h1>
        <p style="font-size:13px;color:#64748b;margin:0 0 24px;">Tablero Kanban — {{ $tableroNombre }}</p>

        <p style="font-size:14px;color:#1e293b;margin:0 0 16px;">Estimado/a <strong>{{ $userName }}</strong>,</p>
        <p style="font-size:13px;color:#475569;line-height:1.6;margin:0 0 24px;">
            Le informamos que la siguiente tarea
            @if($diasRestantes <= 0) <strong style="color:#dc2626;">ya está vencida</strong> @else está próxima a vencer @endif :
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:24px;">
            <tr><td colspan="2" style="background:#f8fafc;padding:10px 20px;border-bottom:1px solid #e2e8f0;">
                <p style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin:0;">Detalle de la tarea</p>
            </td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 20px;font-size:13px;color:#64748b;width:35%;">Título</td>
                <td style="padding:12px 20px;font-size:14px;font-weight:600;color:#1e293b;">{{ $tarea->titulo }}</td>
            </tr>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 20px;font-size:13px;color:#64748b;">Columna</td>
                <td style="padding:12px 20px;font-size:13px;color:#1e293b;">{{ $columnaNombre }}</td>
            </tr>
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 20px;font-size:13px;color:#64748b;">Prioridad</td>
                <td style="padding:12px 20px;font-size:13px;font-weight:600;color:{{ $tarea->prioridad === 'ALTA' ? '#dc2626' : ($tarea->prioridad === 'MEDIA' ? '#f59e0b' : '#10b981') }};">
                    {{ $tarea->prioridad }}
                </td>
            </tr>
            <tr>
                <td style="padding:12px 20px;font-size:13px;color:#64748b;">Fecha vencimiento</td>
                <td style="padding:12px 20px;font-size:14px;font-weight:700;color:#dc2626;">
                    {{ $tarea->fecha_vencimiento->format('d/m/Y') }}
                </td>
            </tr>
        </table>

        <div style="text-align:center;margin-bottom:24px;">
            <a href="{{ $tareaUrl }}" style="background:#0f1b4c;color:#ffffff;padding:12px 36px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;display:inline-block;">
                Ver Tablero
            </a>
        </div>
    </td></tr>
    <tr><td style="padding:0 40px;"><div style="border-top:1px solid #f1f5f9;"></div></td></tr>
    <tr><td style="padding:20px 40px 28px;text-align:center;">
        <p style="font-size:11px;color:#94a3b8;margin:0 0 6px;">Este correo fue enviado automáticamente por SAEP Platform.</p>
        <p style="font-size:11px;color:#cbd5e1;margin:0;">&copy; {{ date('Y') }} S.A.E.P. Ltda. &mdash; <a href="https://saep.cl" style="color:#94a3b8;text-decoration:none;">saep.cl</a></p>
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
