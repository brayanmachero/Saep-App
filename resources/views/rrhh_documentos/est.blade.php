@extends('layouts.app')
@section('title','Guía de documentos EST')
@include('rrhh_documentos.style')
@include('rrhh_documentos.workspace-style')
@section('content')
<div class="gdm doc-workspace">
@include('rrhh_documentos.navigation',['section'=>'guide'])
<section class="panel"><h2>Consulta los requisitos antes de crear una batería</h2><p class="muted">Referencias entregadas por Cristian en la hoja EST de octubre de 2025. La revisión del archivo se realiza dentro de una batería guardada.</p>
<form method="get" class="fields"><label>Cliente o centro de la guía<select name="grupo">@foreach(array_keys($groups) as $option)<option @selected($option===$group)>{{ $option }}</option>@endforeach</select></label><div class="actions"><button class="cta secondary">Consultar requisitos</button></div></form>
</section>
<section class="panel"><div class="doc-toolbar"><div><h2>{{ $group }}</h2><p class="muted">{{ count($documents) }} referencias de documentos</p></div>@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<a class="cta" href="{{ route('rrhh-documentos.battery-create',['grupo'=>$group]) }}">Crear batería para este centro</a>@endif</div>
@if($group==='Unimarc EST')<p class="note">La guía repite documentos con nombres distintos. Revisa las equivalencias al seleccionarlos.</p>@endif
@if(str_starts_with($group,'IDL'))<p class="note">La guía indica revisar en enero el pacto de horas extras. Confirma su versión vigente.</p>@endif
@foreach($documents as $name=>$sources)<div class="doc-reference-row"><div><strong>{{ $name }}</strong><p class="muted">{{ implode(' · ',$sources) }}</p>@if(isset($matches[$name]))<small>Título observado en Talana · {{ $matches[$name] }}</small>@else<small class="muted">Equivalencia por identificar en Talana</small>@endif</div>@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<a class="cta secondary" href="{{ route('rrhh-documentos.battery-create',['grupo'=>$group,'buscar'=>$matches[$name] ?? $name]) }}">Buscar al crear batería</a>@endif</div>@endforeach
</section></div>
@endsection
