@if(config('vehiculos_actas.enabled'))
    @php $actasLocales = $reserva->actasSaep; $entregaLocal = $actasLocales->firstWhere('tipo', 'entrega'); $devolucionLocal = $actasLocales->firstWhere('tipo', 'devolucion'); @endphp
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
        @if($actasLocales->isEmpty() && in_array($reserva->estado, ['EN_USO', 'VENCIDA']))
            <span class="fleet-badge off">Entrega anterior · devolución por flujo existente</span>
        @endif
        @foreach($actasLocales as $actaLocal)
            <a class="fleet-badge {{ $actaLocal->estado === 'FIRMADA' ? '' : 'off' }}" style="text-decoration:none" href="{{ $actaLocal->estado === 'BORRADOR' && auth()->user()->tieneAcceso('gestion_vehiculos','puede_editar') ? route('vehiculos-actas.editar', [$reserva, $actaLocal->tipo]) : route('vehiculos-actas.show', $actaLocal) }}">{{ ucfirst($actaLocal->tipo) }} · {{ $actaLocal->etiqueta_estado }}</a>
        @endforeach
        @if(auth()->user()->tieneAcceso('gestion_vehiculos','puede_editar'))
            @if(!$entregaLocal && $reserva->estado === 'CONFIRMADA' && !$reserva->entregada_at)
                <a class="fleet-badge" style="text-decoration:none;background:#fff0e7;color:#a34522" href="{{ route('vehiculos-actas.editar', [$reserva, 'entrega']) }}">✎ Inspección y firma de entrega</a>
            @elseif($entregaLocal?->estado === 'FIRMADA' && !$devolucionLocal && in_array($reserva->estado,['EN_USO','VENCIDA']))
                <a class="fleet-badge" style="text-decoration:none;background:#fff0e7;color:#a34522" href="{{ route('vehiculos-actas.editar', [$reserva, 'devolucion']) }}">Registrar devolución</a>
            @endif
        @endif
    </div>
@endif
