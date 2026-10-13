@extends('layouts.app')
@section('title', 'Registrar tarifa · Comercial')
@section('content')
@include('comercial::tarifas.styles')
<div class="tarifas"><header><a href="{{ route('comercial.tarifas.index') }}">← Sábana de tarifas</a><h1 style="margin-top:12px">Registrar {{ $base->exists ? 'nueva versión' : 'tarifa' }}</h1><p class="muted">Se conservará el historial. La nueva referencia quedará pendiente de revisión comercial.</p></header>
@if($errors->any())<div class="note error">{{ $errors->first() }}</div>@endif
<form class="panel" method="POST" action="{{ route('comercial.tarifas.store') }}">@csrf<div class="filters">
@foreach(['cliente'=>'Cliente','centro'=>'Centro de costo','cargo'=>'Cargo','categoria'=>'Categoría','modalidad'=>'Modalidad','unidad'=>'Unidad de cobro','variante'=>'Variante / tramo / jornada'] as $campo=>$label)<div><label for="{{ $campo }}">{{ $label }}</label><input id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo,$base->$campo) }}" maxlength="255" @required(in_array($campo,['cliente','cargo','unidad']))></div>@endforeach
<div><label for="moneda">Moneda</label><select id="moneda" name="moneda">@foreach(['CLP','UF','USD'] as $moneda)<option @selected(old('moneda',$base->moneda ?? 'CLP')===$moneda)>{{ $moneda }}</option>@endforeach</select></div>
<div><label for="precio">Tarifa (sin separador de miles)</label><input id="precio" name="precio" type="number" step="0.000001" min="0" required value="{{ old('precio') }}"></div><div><label for="fecha_referencia">Fecha del documento / acuerdo</label><input id="fecha_referencia" type="date" name="fecha_referencia" required value="{{ old('fecha_referencia') }}"></div></div><div style="margin:16px 0"><label for="observaciones">Documento de respaldo y condiciones</label><textarea id="observaciones" name="observaciones" required rows="4" maxlength="4000">{{ old('observaciones',$base->exists ? 'Nueva versión de referencia #'.$base->id.'. Respaldo: ' : '') }}</textarea></div><button class="btn btn-primary">Registrar referencia</button></form></div>
@endsection
