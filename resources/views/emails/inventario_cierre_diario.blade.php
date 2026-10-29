<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#172b4d;padding:20px">
<div style="max-width:720px;margin:auto;background:white;padding:24px;border-radius:12px">
<h1 style="font-size:24px">Entregas pendientes de descuento</h1>
<p>Cierre de Bodega · {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }} · 17:00 Chile</p>
<p><strong>{{ $deliveries->count() }} entregas del día siguen sin descontarse del inventario.</strong> Este aviso no realiza movimientos de stock.</p>
@foreach($deliveries as $delivery)
<div style="border:1px solid #dbe3ef;padding:16px;margin:16px 0;border-radius:8px">
<h2 style="font-size:18px;margin-top:0">KZ-{{ $delivery->kizeo_record_number ?: $delivery->kizeo_data_id }} · {{ $delivery->nombre ?: 'Sin nombre' }}</h2>
<p>{{ $delivery->centro ?: 'Sin centro informado' }}</p>
<p><strong>Motivo:</strong> {{ $delivery->motivoDescuentoPendiente() }}</p>
@if($delivery->descuento_intentado_en)<p>Intento: {{ $delivery->descuento_intentado_en->timezone('America/Santiago')->format('d/m/Y H:i:s') }}.</p>@endif
<p><strong>Qué revisar:</strong> {{ $delivery->accionDescuentoPendiente() }}</p>
</div>
@endforeach
<p><a href="{{ route('inventario-bodega.index', ['vista'=>'kizeo','kizeo_descuento'=>'no_descontadas','kizeo_periodo'=>'mes_actual']) }}">Abrir entregas no descontadas en SAEP</a></p>
<p style="font-size:13px;color:#52627a">Se consideran comprobantes con fecha de pedido de hoy, sincronizados y pendientes al momento del cierre. Los posteriores a las 17:00 no están incluidos en este corte. No se arrastran pendientes de días anteriores. Los fallos no se reintentan automáticamente al reponer stock.</p>
</div></body></html>
