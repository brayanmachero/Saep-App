<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Alerta Actividad SST</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:{{ $tipo === 'vencida' ? '#dc2626' : ($tipo === 'vencimiento' ? '#f59e0b' : '#0f1b4c') }};padding:28px 36px;">
            <h1 style="color:white;font-size:20px;margin:0;">SAEP Platform</h1>
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:6px 0 0;">
                @if($tipo === 'asignacion')
                    📋 Nueva Actividad Asignada
                @elseif($tipo === 'vencimiento')
                    ⏰ Actividad Próxima a Vencer
                @else
                    ⚠️ Actividad Vencida
                @endif
            </p>
        </td>
    </tr>
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
            @endif

            @php
                $programaId = $actividad->categoria?->programa_id;
            @endphp
            @if($programaId)
            <div style="text-align:center;margin-bottom:24px;">
                <a href="{{ route('carta-gantt.show', $programaId) }}"
                   style="background:#0f1b4c;color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;">
                    Ver en Carta Gantt
                </a>
            </div>
            @endif
        </td>
    </tr>
    <tr>
        <td style="background:#f9fafb;padding:20px 36px;border-top:1px solid #e5e7eb;">
            <p style="font-size:11px;color:#9ca3af;margin:0;text-align:center;">
                Este es un correo automático de SAEP Platform. No responder a este mensaje.
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
