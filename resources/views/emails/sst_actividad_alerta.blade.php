<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Alerta Actividad SST</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    @php
        $headerColors = [
            'vencida' => '#dc2626',
            'vencimiento' => '#f59e0b',
            'recordatorio' => '#6366f1',
            'seguimiento_pendiente' => '#ea580c',
            'asignacion' => '#2563eb',
        ];
        $headerBg = $headerColors[$tipo] ?? '#0f1b4c';
        $headerLabel = match ($tipo) {
            'asignacion' => 'Nueva actividad',
            'vencimiento' => 'Proxima a vencer',
            'recordatorio' => 'Recordatorio',
            'seguimiento_pendiente' => 'Seguimiento pendiente',
            default => 'Actividad vencida',
        };
    @endphp
    @include('emails.partials.saep_header', [
        'module' => 'Programa SST',
        'badge' => $headerLabel,
        'badgeColor' => $headerBg,
        'accentColor' => $headerBg,
    ])
    <tr>
        <td style="padding:32px 36px;">
            <p style="font-size:15px;color:#1e1e2e;margin:0 0 20px;">
                Estimado/a {{ $actividad->responsableUser?->name ?? 'responsable' }},
            </p>

            @if($tipo === 'asignacion')
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Se le ha asignado una nueva actividad en el <strong>Programa SST</strong>. A continuación los detalles:
            </p>
            @elseif($tipo === 'vencimiento')
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Le recordamos que la siguiente actividad está <strong>próxima a vencer</strong>:
            </p>
            @elseif($tipo === 'recordatorio')
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                Este es un recordatorio {{ strtolower(\App\Models\SstActividad::periodicidadesMap()[$actividad->periodicidad] ?? '') }} de la siguiente actividad programada que aún <strong>no ha sido completada</strong> este mes:
            </p>
            @elseif($tipo === 'seguimiento_pendiente')
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                La siguiente actividad tenía <strong>seguimiento programado el mes anterior</strong> pero no fue marcada como realizada:
            </p>
            @else
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                La siguiente actividad ha <strong>superado su fecha de vencimiento</strong> sin ser completada:
            </p>
            @endif

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;overflow:hidden;margin-bottom:24px;">
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;width:40%;">Actividad</td>
                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#1e1e2e;">{{ $actividad->nombre }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Programa</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $actividad->categoria?->programa?->nombre ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Categoría</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">{{ $actividad->categoria?->nombre ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Prioridad</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">
                        @php
                            $prioColors = ['ALTA'=>'#dc2626','MEDIA'=>'#f59e0b','BAJA'=>'#22c55e'];
                        @endphp
                        <span style="background:{{ $prioColors[$actividad->prioridad] ?? '#6b7280' }};color:white;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:600;">
                            {{ $actividad->prioridad }}
                        </span>
                    </td>
                </tr>
                @if($actividad->fecha_fin)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Fecha Límite</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;{{ $tipo === 'vencida' ? 'color:#dc2626;font-weight:600;' : '' }}">
                        {{ $actividad->fecha_fin->format('d/m/Y') }}
                    </td>
                </tr>
                @endif
                @if($actividad->periodicidad)
                <tr>
                    <td style="padding:12px 16px;font-size:12px;color:#6b7280;">Periodicidad</td>
                    <td style="padding:12px 16px;font-size:13px;color:#1e1e2e;">
                        {{ \App\Models\SstActividad::periodicidadesMap()[$actividad->periodicidad] ?? $actividad->periodicidad }}
                    </td>
                </tr>
                @endif
            </table>

            @if($tipo === 'vencida')
            <div style="background:#fef2f2;border-left:3px solid #dc2626;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#991b1b;margin:0;font-weight:600;">⚠️ Acción requerida</p>
                <p style="font-size:12px;color:#7f1d1d;margin:6px 0 0;line-height:1.5;">
                    Esta actividad ha vencido. Por favor complete la tarea o registre un plan de acción lo antes posible.
                </p>
            </div>
            @elseif($tipo === 'vencimiento')
            <div style="background:#fffbeb;border-left:3px solid #f59e0b;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#92400e;margin:0;font-weight:600;">⏰ Próxima a vencer</p>
                <p style="font-size:12px;color:#78350f;margin:6px 0 0;line-height:1.5;">
                    Quedan pocos días para completar esta actividad. Revise el progreso y asegúrese de cumplir los plazos.
                </p>
            </div>
            @elseif($tipo === 'recordatorio')
            <div style="background:#eef2ff;border-left:3px solid #6366f1;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#3730a3;margin:0;font-weight:600;">🔔 Recordatorio {{ strtolower(\App\Models\SstActividad::periodicidadesMap()[$actividad->periodicidad] ?? '') }}</p>
                <p style="font-size:12px;color:#4338ca;margin:6px 0 0;line-height:1.5;">
                    Esta actividad tiene periodicidad <strong>{{ strtolower(\App\Models\SstActividad::periodicidadesMap()[$actividad->periodicidad] ?? $actividad->periodicidad) }}</strong>.
                    Recuerde marcar el seguimiento como realizado una vez completada.
                </p>
            </div>
            @elseif($tipo === 'seguimiento_pendiente')
            <div style="background:#fff7ed;border-left:3px solid #ea580c;border-radius:8px;padding:14px 16px;margin-bottom:24px;">
                <p style="font-size:13px;color:#9a3412;margin:0;font-weight:600;">📊 Seguimiento sin completar</p>
                <p style="font-size:12px;color:#7c2d12;margin:6px 0 0;line-height:1.5;">
                    El mes anterior esta actividad estaba programada pero no fue marcada como realizada. Por favor actualice el estado o registre un plan de acción.
                </p>
            </div>
            @endif

            @php
                $programaId = $actividad->categoria?->programa_id;
                $jefeNombre = $actividad->categoria?->programa?->responsable?->nombre_completo;
            @endphp
            @if($programaId)
            @include('emails.partials.saep_button', [
                'url' => route('carta-gantt.show', $programaId),
                'label' => 'Ver en Carta Gantt',
            ])
            @endif

            @if($tipo !== 'asignacion' && $jefeNombre)
            <div style="background:#f0fdf4;border-radius:8px;padding:10px 14px;margin-bottom:16px;">
                <p style="font-size:11px;color:#15803d;margin:0;">
                    📤 Este correo también fue enviado al jefe del programa (<strong>{{ $jefeNombre }}</strong>) y al equipo de administración para seguimiento.
                </p>
            </div>
            @endif
        </td>
    </tr>
    @include('emails.partials.saep_footer', [
        'note' => 'Correo automatico del programa SST.',
        'context' => 'No respondas a este mensaje.',
    ])
</table>
</td></tr>
</table>
</body>
</html>
