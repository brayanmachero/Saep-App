@extends('layouts.app')
@section('title', 'Comparar precios · Comercial')
@section('content')
@include('comercial::tarifas.styles')
@php($importe = fn($v) => \App\Modules\Comercial\Services\TarifaPresentacionService::importe($v, 2))
<div class="tarifas"><header class="toolbar"><div><a href="{{ route('comercial.tarifas.index') }}">← Volver a la consulta</a><h1>Comparar precios</h1><p class="muted">La primera tarifa es la base de comparación. Compara también las condiciones del servicio.</p></div></header>
<div class="note">Una diferencia de precio no significa que los servicios sean equivalentes. Las tarifas por confirmar requieren revisión de Comercial antes de utilizarse.</div>
<section class="panel"><div class="table-wrap"><table class="comparison"><thead><tr><th>Qué comparamos</th>@foreach($tarifas as $tarifa)<th>{{ $tarifa->cargo }} @if($tarifa->id === $base->id) · Base @endif</th>@endforeach</tr></thead><tbody>
@foreach(['cliente'=>'Cliente','centro'=>'Centro','modalidad'=>'Modalidad','variante'=>'Condiciones / jornada','categoria'=>'Categoría','unidad'=>'Unidad de cobro'] as $campo=>$label)<tr><th>{{ $label }}</th>@foreach($tarifas as $tarifa)<td @class(['comparison-different'=>($tarifa->$campo ?? '') !== ($base->$campo ?? '')])>{{ $tarifa->$campo ?: 'Por confirmar' }}</td>@endforeach</tr>@endforeach
<tr><th>Precio</th>@foreach($tarifas as $tarifa)<td><strong>{{ $importe($tarifa->precio) }} {{ $tarifa->moneda }}</strong></td>@endforeach</tr>
<tr><th>Diferencia respecto de la base</th>@foreach($tarifas as $tarifa)<td>@if($tarifa->id === $base->id)Base de comparación @elseif($diferencias[$tarifa->id] === null)No calculable: revisa moneda, unidad o precio.@else {{ $importe($diferencias[$tarifa->id]['importe']) }} {{ $tarifa->moneda }}<div class="muted">@if($diferencias[$tarifa->id]['porcentaje'] !== null){{ $importe($diferencias[$tarifa->id]['porcentaje']) }}% @else Porcentaje no calculable: precio base cero. @endif</div>@endif</td>@endforeach</tr>
<tr><th>Fecha de referencia</th>@foreach($tarifas as $tarifa)<td>{{ $tarifa->fecha_referencia?->format('d/m/Y') ?: 'Por confirmar' }}</td>@endforeach</tr>
<tr><th>Estado actual</th>@foreach($tarifas as $tarifa)<td>{{ $tarifa->etiquetaConsulta(in_array($tarifa->id,$vigentesHoy)) }}</td>@endforeach</tr>
<tr><th>Vigencia aprobada</th>@foreach($tarifas as $tarifa)<td>@if($tarifa->estado === 'confirmada'){{ $tarifa->vigente_desde?->format('d/m/Y') ?: 'Inicio por confirmar' }}<br>{{ $tarifa->vigente_hasta ? 'Hasta '.$tarifa->vigente_hasta->format('d/m/Y') : 'Sin término informado' }}@else Sin vigencia activa @endif</td>@endforeach</tr>
<tr><th>Conceptos informados</th>@foreach($tarifas as $tarifa)<td><details><summary>Ver conceptos ({{ count($tarifa->conceptos ?? []) }})</summary>@forelse($tarifa->conceptos ?? [] as $concepto)<p>{{ $concepto['concepto'] ?? 'Concepto sin nombre' }}: <strong>{{ $importe($concepto['valor'] ?? null) }}</strong></p>@empty<p>No hay desglose disponible.</p>@endforelse</details></td>@endforeach</tr>
<tr><th>Detalle y respaldo</th>@foreach($tarifas as $tarifa)<td><a href="{{ route('comercial.tarifas.show',$tarifa) }}">Consultar tarifa #{{ $tarifa->id }}</a>@if($tarifa->fuente && str_ends_with(strtolower($tarifa->fuente->archivo),'.xlsx'))<p><a href="{{ route('comercial.tarifas.archivo',$tarifa) }}">Descargar respaldo</a></p>@endif</td>@endforeach</tr>
</tbody></table></div><p class="muted">Las condiciones distintas se destacan. Los importes se muestran con hasta dos decimales; la diferencia se calcula con el precio guardado.</p></section></div>
@endsection
