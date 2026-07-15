<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Resumen Kanban</title></head>
<body style="font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#eef1f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef1f6;padding:40px 16px;">
<tr><td align="center">
<table width="680" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(15,27,76,0.06);">
    @include('emails.partials.saep_header', [
        'module' => 'Tablero Kanban',
        'badge' => 'Resumen diario',
        'badgeColor' => $vencidas > 0 ? '#dc2626' : '#f59e0b',
        'accentColor' => $vencidas > 0 ? '#dc2626' : '#f59e0b',
    ])
    <tr><td style="padding:34px 40px 28px;">
        <h1 style="font-size:20px;font-weight:700;color:#0f1b4c;margin:0 0 6px;">Tareas Kanban por revisar</h1>
        <p style="font-size:13px;color:#64748b;margin:0 0 22px;">
            Estimado/a <strong>{{ $usuario->nombre_completo ?? $usuario->name }}</strong>, tienes {{ $total }} tarea(s) asignada(s) vencidas o próximas a vencer.
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
            <tr>
                <td style="padding:12px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;">
                    <div style="font-size:11px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.06em;">Vencidas</div>
                    <div style="font-size:24px;font-weight:800;color:#dc2626;line-height:1.2;">{{ $vencidas }}</div>
                </td>
                <td width="12"></td>
                <td style="padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;">
                    <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;">Proximas</div>
                    <div style="font-size:24px;font-weight:800;color:#f59e0b;line-height:1.2;">{{ $proximas }}</div>
                </td>
                <td width="12"></td>
                <td style="padding:12px 14px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;">
                    <div style="font-size:11px;font-weight:700;color:#3730a3;text-transform:uppercase;letter-spacing:.06em;">Tableros</div>
                    <div style="font-size:24px;font-weight:800;color:#4f46e5;line-height:1.2;">{{ $tableros->count() }}</div>
                </td>
            </tr>
        </table>

        @foreach($tableros as $grupo)
            @php
                $tablero = $grupo->first()['tarea']->tablero;
                $tableroUrl = route('kanban.show', $tablero);
            @endphp
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:18px;">
                <tr>
                    <td colspan="5" style="background:#f8fafc;padding:12px 16px;border-bottom:1px solid #e2e8f0;">
                        <div style="font-size:12px;font-weight:800;color:#0f1b4c;">{{ $tablero?->nombre ?? 'Tablero' }}</div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px;">{{ $grupo->count() }} tarea(s) por revisar</div>
                    </td>
                </tr>
                <tr style="background:#ffffff;">
                    <th align="left" style="padding:9px 12px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;">Tarea</th>
                    <th align="left" style="padding:9px 12px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;">Columna</th>
                    <th align="left" style="padding:9px 12px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;">Prioridad</th>
                    <th align="left" style="padding:9px 12px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;">Vence</th>
                    <th align="left" style="padding:9px 12px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;">Estado</th>
                </tr>
                @foreach($grupo->sortBy(fn($item) => $item['tarea']->fecha_vencimiento?->timestamp ?? 0) as $item)
                    @php
                        $tarea = $item['tarea'];
                        $dias = $item['dias_restantes'];
                        $estadoColor = $dias <= 0 ? '#dc2626' : '#f59e0b';
                        $estadoTexto = $dias <= 0 ? 'Vencida' : ($dias === 1 ? 'Vence manana' : "Vence en {$dias} dias");
                    @endphp
                    <tr>
                        <td style="padding:10px 12px;font-size:13px;font-weight:700;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ $tarea->titulo }}</td>
                        <td style="padding:10px 12px;font-size:12px;color:#475569;border-bottom:1px solid #f1f5f9;">{{ $tarea->columna?->nombre ?? '-' }}</td>
                        <td style="padding:10px 12px;font-size:12px;font-weight:700;color:{{ $tarea->prioridad === 'ALTA' ? '#dc2626' : ($tarea->prioridad === 'MEDIA' ? '#f59e0b' : '#10b981') }};border-bottom:1px solid #f1f5f9;">{{ $tarea->prioridad }}</td>
                        <td style="padding:10px 12px;font-size:12px;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ $tarea->fecha_vencimiento?->format('d/m/Y') }}</td>
                        <td style="padding:10px 12px;font-size:12px;font-weight:800;color:{{ $estadoColor }};border-bottom:1px solid #f1f5f9;">{{ $estadoTexto }}</td>
                    </tr>
                @endforeach
                <tr><td colspan="5" style="padding:14px 16px;background:#ffffff;">
                    @include('emails.partials.saep_button', [
                        'url' => $tableroUrl,
                        'label' => 'Ver tablero',
                    ])
                </td></tr>
            </table>
        @endforeach
    </td></tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo automatico consolidado del tablero Kanban.',
        'context' => 'Resumen diario de tareas vencidas y proximas a vencer.',
    ])
</table>
</td></tr></table>
</body>
</html>
