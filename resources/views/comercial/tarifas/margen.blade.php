@if(auth()->user()->tieneAcceso('comercial','puede_editar') || auth()->user()->esSuperAdmin() || auth()->user()->rol?->puede_aprobar)
<style>.revision-margen{margin:20px 0;padding:20px;border:1px solid var(--surface-border,#cbd5e1);border-radius:12px;background:var(--surface-color,#fff)}.revision-margen p{margin:10px 0;line-height:1.5;font-size:.88rem}.revision-margen .margen-cifras{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin:24px 0}.revision-margen .margen-cifras strong{display:block;font-size:1.25rem;margin-top:8px}.revision-margen .margen-cifras div{font-size:.85rem}.revision-margen [role=alert]{border-left:4px solid #dc2626;padding:12px}</style>
<section class="panel revision-margen" aria-label="Revisión de margen comercial">
<h2>Revisión de margen · Comercial</h2>
<p><strong>{{ $revisionMargen['estado'] }}</strong></p>
<p>{{ $revisionMargen['mensaje'] }}</p>
@if($revisionMargen['verificable'])
@php($importeMargen = fn ($valor) => \App\Modules\Comercial\Services\TarifaPresentacionService::importe($valor, 2))
<p>{{ $revisionMargen['origen'] }} · {{ $revisionMargen['moneda'] }} por {{ $revisionMargen['unidad'] }}</p>
<div class="margen-cifras">
@foreach(['costo'=>'Costo total del servicio','precio'=>'Precio de venta','diferencia'=>'Margen en dinero (precio menos costo)'] as $campo=>$label)
<div><div>{{ $label }}</div><strong>{{ $importeMargen($revisionMargen[$campo]) }} {{ $revisionMargen['moneda'] }}</strong></div>
@endforeach
<div><div>Porcentaje sobre costo</div><strong>{{ $importeMargen($revisionMargen['sobre_costo']) }} %</strong></div>
<div><div>Porcentaje sobre venta</div><strong>{{ $revisionMargen['sobre_venta'] === null ? 'No calculable: precio cero' : $importeMargen($revisionMargen['sobre_venta']).' %' }}</strong></div>
</div>
@if($revisionMargen['diferencia'] < 0)<p role="alert"><strong>Precio inferior al costo: esta tarifa tiene margen negativo.</strong></p>@endif
@if($revisionMargen['margen_guardado'] !== null)<p>Margen guardado: {{ $importeMargen($revisionMargen['margen_guardado']) }} {{ $revisionMargen['moneda'] }}.</p>@endif
@if($revisionMargen['porcentaje_guardado'] !== null)<p>Porcentaje guardado por el cotizador (sobre costo): {{ $importeMargen($revisionMargen['porcentaje_guardado']) }} %.</p>@endif
@endif
<p>Antes de aprobar, comprueba que el costo corresponde al mismo servicio, unidad y moneda. No se ha definido un margen mínimo comercial automático.</p>
</section>
@endif
