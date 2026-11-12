<ul>
@foreach($r->items as $item)
    <li>{{ $item['cantidad'] }} × {{ $item['nombre'] }} {{ $item['talla'] }}</li>
@endforeach
</ul>
@if($r->insumos)<p><strong>Insumos generales</strong> · {{ $r->insumos }}</p>@endif
@include('epp.timeline')
