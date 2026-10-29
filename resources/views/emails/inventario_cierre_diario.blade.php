<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cierre de Bodega · SAEP</title></head>
<body style="margin:0;padding:0;background:#eef1f6;font-family:Arial,Helvetica,sans-serif;color:#24324a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="680" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #dce3ed;border-radius:12px;overflow:hidden;">
@include('emails.partials.saep_header', ['subtitle'=>'Bodega e inventario', 'module'=>'Cierre diario · Entregas Kizeo', 'accentColor'=>'#f97316'])
<tr><td style="padding:28px 24px 12px;">
<p style="margin:0 0 10px;color:#b45309;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Revisión pendiente</p>
<h1 style="margin:0 0 10px;color:#0f1b4c;font-size:25px;line-height:1.25;">Entregas sin descontar</h1>
<p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#64748b;">{{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }} &nbsp;·&nbsp; Corte de las 17:00, hora de Chile</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;"><tr>
<td width="64" align="center" style="padding:16px 8px 16px 16px;color:#c2410c;font-size:34px;font-weight:700;">{{ $deliveries->count() }}</td>
<td style="padding:16px;color:#7c2d12;font-size:14px;line-height:1.5;"><strong>{{ $deliveries->count() === 1 ? 'entrega del día pendiente' : 'entregas del día pendientes' }}</strong><br>Revisa el motivo antes de aplicar la salida de stock.</td>
</tr></table>
<p style="margin:16px 0 8px;color:#64748b;font-size:13px;line-height:1.6;">Este aviso informa pendientes; no realiza movimientos de inventario.</p>
</td></tr>
@foreach($deliveries as $delivery)
<tr><td style="padding:10px 24px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dce3ed;border-radius:8px;overflow:hidden;">
<tr><td style="padding:16px 18px;background:#f5f7fb;border-bottom:1px solid #dce3ed;">
<p style="margin:0 0 6px;color:#b45309;font-size:12px;font-weight:700;">KZ-{{ $delivery->kizeo_record_number ?: $delivery->kizeo_data_id }}</p>
<h2 style="margin:0 0 5px;color:#0f1b4c;font-size:17px;line-height:1.4;">{{ $delivery->nombre ?: 'Sin nombre' }}</h2>
<p style="margin:0;color:#64748b;font-size:13px;line-height:1.5;">{{ $delivery->centro ?: 'Sin centro informado' }}</p>
</td></tr>
<tr><td style="padding:18px;font-size:14px;line-height:1.65;">
<p style="margin:0 0 6px;font-size:11px;letter-spacing:.6px;font-weight:700;color:#9a3412;">POR QUÉ NO SE DESCONTÓ</p>
<p style="margin:0 0 12px;">{{ $delivery->motivoDescuentoPendiente() }}</p>
@if($delivery->descuento_intentado_en)<p style="margin:0 0 16px;color:#64748b;font-size:12px;">Intento registrado: {{ $delivery->descuento_intentado_en->timezone('America/Santiago')->format('d/m/Y H:i:s') }}</p>@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:12px 14px;background:#f0f4fa;border-left:3px solid #0f1b4c;color:#334155;font-size:13px;line-height:1.6;"><strong>Qué revisar</strong><br>{{ $delivery->accionDescuentoPendiente() }}</td></tr></table>
</td></tr></table>
</td></tr>
@endforeach
<tr><td style="padding:22px 24px 4px;">
@include('emails.partials.saep_button', ['url'=>route('inventario-bodega.index', ['vista'=>'kizeo','kizeo_descuento'=>'no_descontadas','kizeo_periodo'=>'mes_actual']), 'label'=>'Revisar pendientes en SAEP'])
<p style="margin:0 0 20px;font-size:12px;line-height:1.7;color:#64748b;">Se consideran comprobantes con fecha de pedido de hoy, sincronizados y pendientes al cierre. Los posteriores a las 17:00 no están incluidos. No se arrastran pendientes de días anteriores. Los fallos no se reintentan automáticamente al reponer stock.</p>
</td></tr>
@include('emails.partials.saep_footer', ['note'=>'Notificación automática · Bodega e Inventario', 'context'=>'SAEP · Seguimiento de entregas Kizeo'])
</table></td></tr></table>
</body></html>
