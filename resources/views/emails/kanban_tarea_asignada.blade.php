<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Tarea Asignada</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @include('emails.partials.saep_header', [
        'module' => 'Tablero Kanban',
        'badge' => 'Nueva tarea',
        'badgeColor' => '#6366f1',
        'accentColor' => '#6366f1',
    ])
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">
                Estimado/a {{ $tarea->asignado?->name ?? 'usuario' }},
            </p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                <strong>{{ $asignador->name }}</strong> te ha asignado una nueva tarea en el tablero
                <strong>{{ $tarea->tablero?->nombre ?? 'Kanban' }}</strong>.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:35%;">Tarea</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $tarea->titulo }}</td>
                </tr>
                @if($tarea->descripcion)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Descripción</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ Str::limit($tarea->descripcion, 200) }}</td>
                </tr>
                @endif
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Tablero</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $tarea->tablero?->nombre ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Columna</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $tarea->columna?->nombre ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    @php
                        $pColors = ['ALTA' => '#dc2626', 'MEDIA' => '#f59e0b', 'BAJA' => '#16a34a'];
                        $pLabels = ['ALTA' => '🔴 Alta', 'MEDIA' => '🟡 Media', 'BAJA' => '🟢 Baja'];
                    @endphp
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Prioridad</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:{{ $pColors[$tarea->prioridad] ?? '#6b7280' }};">
                        {{ $pLabels[$tarea->prioridad] ?? $tarea->prioridad }}
                    </td>
                </tr>
                @if($tarea->fecha_vencimiento)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha Vencimiento</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $tarea->fecha_vencimiento->format('d/m/Y') }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Asignada por</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $asignador->name }}</td>
                </tr>
            </table>

            @include('emails.partials.saep_button', [
                'url' => route('kanban.show', $tarea->tablero_id),
                'label' => 'Ver tablero',
            ])
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo generado automaticamente por el tablero Kanban.',
        'context' => 'No respondas a este mensaje.',
    ])
</table>
</td></tr>
</table>
</body>
</html>
