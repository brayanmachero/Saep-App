<ul>
@foreach($r->items as $item)
    <li>{{ $item['cantidad'] }} × {{ $item['nombre'] }} {{ $item['talla'] }}</li>
@endforeach
</ul>
@if($r->insumos)<p><strong>Insumos generales</strong> · {{ $r->insumos }}</p>@endif
@include('epp.timeline')

@foreach($r->pending as $proposal)
<form method="post" action="{{ route('epp.respond', [$r->id, $proposal['id']]) }}">
    @csrf
    <p>¿Aceptas reemplazar {{ $proposal['anterior']['cantidad'] }} × {{ $proposal['anterior']['nombre'] }} {{ $proposal['anterior']['talla'] }} por {{ $proposal['nuevo']['nombre'] }} {{ $proposal['nuevo']['talla'] }}?</p>
    <button type="submit" name="respuesta" value="aceptada">Aceptar reemplazo</button>
    <button class="secondary" type="submit" name="respuesta" value="rechazada">Rechazar reemplazo</button>
</form>
@endforeach
