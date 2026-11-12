<!doctype html><html lang="es"><body style="margin:0;background:#eef1f5;font:16px Arial,sans-serif;color:#172441">
<div style="max-width:620px;margin:24px auto;background:white">
<div style="padding:22px;background:#152446;color:white;border-top:5px solid #ff7840"><strong>Bodega SAEP</strong><h1 style="font-size:22px">Pedido EPP #{{ $order->id }}</h1>{{ $evento }}</div>
<div style="padding:22px"><p>{{ $order->nombre }} · {{ $order->email }}<br>{{ $order->centro }} · Mes solicitado {{ $order->mes }}</p>
@if($propuesta)
<p><strong>Reemplazo de {{ $propuesta['anterior']['cantidad'] }} unidades</strong><br>Solicitado: {{ $propuesta['anterior']['nombre'] }} {{ $propuesta['anterior']['talla'] }}<br>Alternativa: {{ $propuesta['nuevo']['nombre'] }} {{ $propuesta['nuevo']['talla'] }}</p>
@if($propuesta['respuesta']==='pendiente')<p>Ingresa con tu cuenta SAEP para aceptar o rechazar esta alternativa.</p>@endif
@endif
<strong>Detalle del pedido</strong><ul>@foreach($items as $item)<li>{{ $item['cantidad'] }} × {{ $item['nombre'] }} {{ $item['talla'] }}</li>@endforeach</ul>
@if($order->insumos)<p>Insumos generales: {{ $order->insumos }}</p>@endif
<a style="display:inline-block;padding:12px 18px;background:#152446;color:white;text-decoration:none" href="{{ $link }}">{{ $received ? 'Abrir pedido en Bodega' : ($propuesta && $propuesta['respuesta']==='pendiente' ? 'Revisar y responder propuesta' : 'Ver mi solicitud') }}</a>
@if(in_array($evento,['Reemplazo aceptado','Reemplazo rechazado'],true))
<p><a href="{{ route('inventario-bodega.epp.index',['buscar'=>$order->id,'estado'=>'todas','mes'=>substr($order->created_at,0,7),'pedido'=>$order->id]) }}">Ver en la bandeja de Bodega</a></p>
@endif
</div></div></body></html>
