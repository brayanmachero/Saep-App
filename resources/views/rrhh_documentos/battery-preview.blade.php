@extends('layouts.app')
@section('title','Revisar documento de Talana')
@include('rrhh_documentos.style')
@include('rrhh_documentos.battery-style')
@section('content')
<div class="gdm battery-view">
<a href="{{ route('rrhh-documentos.battery-show',$record->id) }}">← Volver a la batería</a>
<section class="panel"><h2>{{ $name }}</h2><p class="muted">{{ $record->nombre }} · versión {{ $record->version }}</p>
@if($errors->any())<div class="err" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
@if($error)<p class="note" role="alert">{{ $error }}</p>@else
<p class="note">Este archivo es un registro de referencia asociado a un trabajador. Puede contener datos personales y firmas. Comprueba el contenido y la versión que corresponde a esta batería.</p>
<a class="cta secondary" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Abrir archivo en otra pestaña</a>
<iframe class="pdf-frame" src="{{ $url }}" title="Vista previa de {{ $name }}" referrerpolicy="no-referrer"></iframe>
@if($record->estado === 'BORRADOR' && auth()->user()->tieneAcceso('gestion_documental','puede_editar'))
<form method="post" action="{{ route('rrhh-documentos.battery-validate',[$record->id,$position]) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><label class="check"><input type="checkbox" name="confirmed" value="1" required> Revisé el archivo y confirmo que su contenido y versión corresponden a esta batería.</label><p><button class="cta">Validar documento</button></p></form>
@endif
@endif
</section></div>
@endsection
