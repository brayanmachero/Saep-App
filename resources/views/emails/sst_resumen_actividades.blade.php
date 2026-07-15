<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Resumen Carta Gantt</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="680" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,27,76,0.10);">
    @include('emails.partials.saep_header', [
        'module' => 'Carta Gantt SST',
        'badge' => 'Resumen diario',
        'badgeColor' => '#f97316',
        'accentColor' => '#f97316',
    ])
    <tr>
        <td style="padding:30px 36px 18px;">
            <p style="font-size:15px;color:#111827;margin:0 0 12px;">
                Hola {{ $nombre ?: 'equipo' }},
            </p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 22px;">
                Este es el resumen consolidado de actividades de Carta Gantt que requieren revision, seguimiento o cierre. Se envia en un solo correo para evitar multiples notificaciones individuales.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px;">
                <tr>
                    <td style="padding:10px 8px 10px 0;">
                        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">
                            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;">Total</div>
                            <div style="font-size:24px;color:#111827;font-weight:800;margin-top:4px;">{{ $total }}</div>
                        </div>
                    </td>
                    <td style="padding:10px 8px;">
                        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;">
                            <div style="font-size:11px;color:#991b1b;text-transform:uppercase;font-weight:700;">Vencidas</div>
                            <div style="font-size:24px;color:#dc2626;font-weight:800;margin-top:4px;">{{ $conteos['vencida'] }}</div>
                        </div>
                    </td>
                    <td style="padding:10px 8px;">
                        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px;">
                            <div style="font-size:11px;color:#92400e;text-transform:uppercase;font-weight:700;">Por vencer</div>
                            <div style="font-size:24px;color:#d97706;font-weight:800;margin-top:4px;">{{ $conteos['vencimiento'] }}</div>
                        </div>
                    </td>
                    <td style="padding:10px 0 10px 8px;">
                        <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:14px;">
                            <div style="font-size:11px;color:#3730a3;text-transform:uppercase;font-weight:700;">Seguimiento</div>
                            <div style="font-size:24px;color:#4f46e5;font-weight:800;margin-top:4px;">{{ $conteos['recordatorio'] + $conteos['seguimiento_pendiente'] }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            @php
                $tipoLabels = [
                    'vencida' => 'Vencida',
                    'vencimiento' => 'Proxima a vencer',
                    'recordatorio' => 'Recordatorio',
                    'seguimiento_pendiente' => 'Seguimiento pendiente',
                ];
                $tipoColors = [
                    'vencida' => '#dc2626',
                    'vencimiento' => '#d97706',
                    'recordatorio' => '#4f46e5',
                    'seguimiento_pendiente' => '#ea580c',
                ];
            @endphp

            @foreach($itemsPorPrograma as $programaItems)
                @php
                    $programa = $programaItems->first()['programa'] ?? null;
                    $programaUrl = $programaItems->first()['url'] ?? route('carta-gantt.index');
                @endphp
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e5e7eb;border-radius:12px;margin:0 0 18px;overflow:hidden;">
                    <tr>
                        <td style="background:#f9fafb;padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                            <div style="font-size:14px;color:#111827;font-weight:800;">{{ $programa?->nombre ?: 'Carta Gantt sin programa' }}</div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px;">{{ $programaItems->count() }} actividad(es) pendientes en esta carta</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <th align="left" style="font-size:11px;color:#6b7280;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid #e5e7eb;">Estado</th>
                                    <th align="left" style="font-size:11px;color:#6b7280;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid #e5e7eb;">Actividad</th>
                                    <th align="left" style="font-size:11px;color:#6b7280;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid #e5e7eb;">Categoria</th>
                                    <th align="left" style="font-size:11px;color:#6b7280;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid #e5e7eb;">Fecha limite</th>
                                </tr>
                                @foreach($programaItems as $item)
                                    @php
                                        $actividad = $item['actividad'];
                                        $tipo = $item['tipo'];
                                        $dias = $item['dias'];
                                        $diasLabel = $dias === null
                                            ? 'Sin fecha'
                                            : ($dias < 0 ? 'Vencida hace ' . abs($dias) . ' dia(s)' : ($dias === 0 ? 'Vence hoy' : 'Quedan ' . $dias . ' dia(s)'));
                                    @endphp
                                    <tr>
                                        <td style="padding:11px 12px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
                                            <span style="background:{{ $tipoColors[$tipo] ?? '#64748b' }};color:#ffffff;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:800;display:inline-block;white-space:nowrap;">
                                                {{ $tipoLabels[$tipo] ?? 'Alerta' }}
                                            </span>
                                        </td>
                                        <td style="padding:11px 12px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
                                            <div style="font-size:13px;color:#111827;font-weight:700;line-height:1.35;">{{ $actividad->nombre }}</div>
                                            <div style="font-size:11px;color:#6b7280;margin-top:3px;">Responsable: {{ $actividad->nombre_responsable }}</div>
                                        </td>
                                        <td style="padding:11px 12px;border-bottom:1px solid #f3f4f6;vertical-align:top;font-size:12px;color:#374151;">
                                            {{ $item['categoria']?->nombre ?? '-' }}
                                        </td>
                                        <td style="padding:11px 12px;border-bottom:1px solid #f3f4f6;vertical-align:top;">
                                            <div style="font-size:12px;color:#111827;font-weight:700;">{{ $actividad->fecha_fin?->format('d/m/Y') ?? '-' }}</div>
                                            <div style="font-size:11px;color:#6b7280;margin-top:3px;">{{ $diasLabel }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 16px;background:#ffffff;">
                            <a href="{{ $programaUrl }}" style="color:#0f1b4c;text-decoration:none;font-size:13px;font-weight:800;">Abrir Carta Gantt</a>
                        </td>
                    </tr>
                </table>
            @endforeach

            @include('emails.partials.saep_button', [
                'url' => route('carta-gantt.index'),
                'label' => 'Ver todas las Cartas Gantt',
                'color' => '#0f1b4c',
            ])

            <p style="font-size:12px;color:#6b7280;line-height:1.6;margin:0 0 8px;">
                La plataforma mantiene el registro individual de cada actividad notificada para control y auditoria.
            </p>
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo automatico consolidado de Carta Gantt SST.',
        'context' => 'No respondas a este mensaje.',
    ])
</table>
</td></tr>
</table>
</body>
</html>
