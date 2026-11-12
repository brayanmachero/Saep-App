<h3>Historial de la solicitud</h3>
<ol class="epp-history">
    <li><strong>Pendiente · Solicitud recibida</strong>
        <small>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }} · {{ $r->nombre }}</small></li>
    @foreach($r->events as $event)
        <li><strong>En gestión · {{ isset($event['respuesta']) ? 'Propuesta de reemplazo' : 'Reemplazo por falta de stock' }}</strong>
            <div>{{ $event['anterior']['cantidad'] }} × {{ $event['anterior']['nombre'] }} {{ $event['anterior']['talla'] }} → {{ $event['nuevo']['nombre'] }} {{ $event['nuevo']['talla'] }}</div>
            <small>{{ \Illuminate\Support\Carbon::parse($event['fecha'])->format('d/m/Y H:i') }} · {{ $event['gestor'] }}</small>
            @if(isset($event['respuesta']))
                @if($event['respuesta'] === 'pendiente')<p>Pendiente de respuesta del solicitante</p>
                @else<p><strong>Propuesta {{ $event['respuesta'] }}</strong> · {{ $event['respondida_por'] }} · {{ \Illuminate\Support\Carbon::parse($event['respondida_en'])->format('d/m/Y H:i') }}</p>@endif
            @endif
        </li>
    @endforeach
    @if($r->completada_en)
        <li><strong>Completada</strong>
            <small>{{ \Illuminate\Support\Carbon::parse($r->completada_en)->format('d/m/Y H:i') }} · {{ $r->gestor_cierre }}</small></li>
    @endif
</ol>
