@php
    $meta = match($tipo) {
        'confirmacion' => ['titulo' => 'Reserva confirmada', 'texto' => 'Tu solicitud fue registrada y el vehiculo queda bloqueado durante el horario indicado.', 'badge' => 'Confirmada', 'color' => '#07866b'],
        'recordatorio' => ['titulo' => 'Tu reserva comienza pronto', 'texto' => 'Revisa el horario y coordina la entrega del vehiculo con Bodega.', 'badge' => 'Recordatorio', 'color' => '#2563eb'],
        'vencimiento' => ['titulo' => 'Reserva fuera de horario', 'texto' => 'La hora de termino fue superada. Bodega debe confirmar la devolucion o actualizar el estado.', 'badge' => 'Por revisar', 'color' => '#c2410c'],
        'administracion' => ['titulo' => 'Nueva reserva registrada', 'texto' => 'Se creo una reserva desde el portal corporativo. Revisa el detalle operativo.', 'badge' => 'Nueva reserva', 'color' => '#5b21b6'],
        'cancelacion' => ['titulo' => 'Reserva cancelada', 'texto' => 'La reserva fue cancelada y el vehiculo vuelve a estar disponible para ese horario.', 'badge' => 'Cancelada', 'color' => '#be123c'],
        'actualizacion' => ['titulo' => 'Reserva actualizada', 'texto' => 'Bodega actualizo el estado de esta reserva. Revisa el detalle operativo.', 'badge' => 'Actualizada', 'color' => '#2563eb'],
        'reprogramacion' => ['titulo' => 'Reserva reprogramada', 'texto' => 'Bodega actualizo el vehiculo, la fecha u horario de esta reserva. Revisa el nuevo detalle operativo.', 'badge' => 'Reprogramada', 'color' => '#2563eb'],
        'ampliacion' => ['titulo' => 'Horario ampliado', 'texto' => 'La reserva en curso fue ampliada luego de validar la disponibilidad del vehiculo.', 'badge' => 'Ampliada', 'color' => '#2563eb'],
        'eventualidad' => ['titulo' => 'Eventualidad reportada', 'texto' => 'El solicitante registro una eventualidad durante el uso del vehiculo. Bodega recibe este aviso para coordinar el apoyo necesario.', 'badge' => 'Requiere revision', 'color' => '#c2410c'],
        'eliminacion' => ['titulo' => 'Reserva eliminada', 'texto' => 'Bodega elimino permanentemente esta reserva y libero el vehiculo y su evento del calendario compartido.', 'badge' => 'Eliminada', 'color' => '#be123c'],
        default => ['titulo' => 'Actualizacion de reserva', 'texto' => 'Existe una actualizacion en la reserva de vehiculo.', 'badge' => 'Reserva', 'color' => '#2563eb'],
    };
    $contexto = $contexto ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $meta['titulo'] }}</title></head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:28px 12px">
    <tr><td align="center">
        <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:620px;max-width:100%;background:#fff;border:1px solid #d9e0ea;border-radius:8px;overflow:hidden">
            @include('emails.partials.saep_header', ['module' => 'Bodega · Reservas de vehiculos', 'subtitle' => 'Gestion de flota y traslados', 'badge' => $meta['badge'], 'badgeColor' => $meta['color'], 'accentColor' => '#ff6b35'])
            <tr><td style="padding:30px">
                <h1 style="margin:0 0 8px;color:#172033;font-size:22px">{{ $meta['titulo'] }}</h1>
                <p style="margin:0 0 22px;color:#617089;font-size:14px;line-height:1.55">{{ $meta['texto'] }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d9e0ea;border-radius:7px;overflow:hidden">
                    <tr><td style="padding:11px 14px;width:38%;background:#f6f8fb;color:#617089;font-size:12px;font-weight:700">Codigo</td><td style="padding:11px 14px;color:#172033;font-size:13px;font-weight:800">{{ $reserva->codigo }}</td></tr>
                    <tr><td style="padding:11px 14px;background:#f6f8fb;color:#617089;font-size:12px;font-weight:700">Vehiculo</td><td style="padding:11px 14px;color:#172033;font-size:13px"><strong>{{ $reserva->vehiculo->patente }}</strong> · {{ $reserva->vehiculo->nombre_operativo }}</td></tr>
                    <tr><td style="padding:11px 14px;background:#f6f8fb;color:#617089;font-size:12px;font-weight:700">Horario</td><td style="padding:11px 14px;color:#172033;font-size:13px">{{ $reserva->inicio->format('d/m/Y H:i') }} a {{ $reserva->termino->format('d/m/Y H:i') }}</td></tr>
                    <tr><td style="padding:11px 14px;background:#f6f8fb;color:#617089;font-size:12px;font-weight:700">Solicitante</td><td style="padding:11px 14px;color:#172033;font-size:13px">{{ $reserva->solicitante_nombre }}<br><span style="color:#617089;font-size:12px">{{ $reserva->solicitante_email }}</span></td></tr>
                    @if($actor)
                        <tr><td style="padding:11px 14px;background:#f6f8fb;color:#617089;font-size:12px;font-weight:700">Gestionado por</td><td style="padding:11px 14px;color:#172033;font-size:13px">{{ $actor->nombre_completo ?: $actor->name }}<br><span style="color:#617089;font-size:12px">{{ $actor->email }}</span></td></tr>
                    @endif
                    @if($tipo === 'eventualidad')
                        <tr><td style="padding:11px 14px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:700">Eventualidad</td><td style="padding:11px 14px;color:#172033;font-size:13px"><strong>{{ $contexto['tipo_label'] ?? 'Aviso operativo' }}</strong><br><span style="color:#617089;font-size:12px">{{ $contexto['descripcion'] ?? 'Sin detalle informado.' }}</span>@if(!empty($contexto['fecha_estimada_devolucion']))<br><span style="color:#9a3412;font-size:12px;font-weight:700">Devolucion estimada: {{ \Illuminate\Support\Carbon::parse($contexto['fecha_estimada_devolucion'])->format('d/m/Y H:i') }}</span>@endif</td></tr>
                    @endif
                    @if($tipo === 'ampliacion')
                        <tr><td style="padding:11px 14px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700">Motivo de ampliacion</td><td style="padding:11px 14px;color:#172033;font-size:13px">{{ $contexto['motivo'] ?? 'Sin detalle informado.' }}</td></tr>
                    @endif
                    <tr><td style="padding:11px 14px;background:#f6f8fb;color:#617089;font-size:12px;font-weight:700">Destino y motivo</td><td style="padding:11px 14px;color:#172033;font-size:13px">{{ $reserva->destino ?: 'Sin destino informado' }}<br><span style="color:#617089;font-size:12px">{{ $reserva->motivo }}</span></td></tr>
                </table>
                <div style="padding-top:24px;text-align:center"><a href="{{ route('reservas-vehiculos.inicio') }}" style="display:inline-block;padding:11px 18px;border-radius:6px;background:#210a5a;color:#fff;text-decoration:none;font-size:13px;font-weight:800">Ver mis reservas</a></div>
            </td></tr>
            @include('emails.partials.saep_footer', ['note' => 'Correo automatico de Bodega SAEP. Para cambios de horario, utiliza el portal de reservas.', 'context' => 'Las actas de entrega y devolucion se registran por separado.'])
        </table>
    </td></tr>
</table>
</body>
</html>
