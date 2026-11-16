@extends('layouts.app')
@section('title','Crear batería documental')
@include('rrhh_documentos.style')
@include('rrhh_documentos.workspace-style')
@section('content')
<div class="gdm doc-workspace">
@include('rrhh_documentos.navigation',['section'=>'batteries'])
<a href="{{ route('rrhh-documentos.index') }}">← Volver a mis baterías</a>
@if($errors->any())<div class="panel err" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
<section class="panel"><h2>Define a quién corresponde esta batería</h2><p class="muted">Al guardar abrirás el detalle para ver y validar cada archivo.</p>
@if($group)<p class="note">Referencia elegida · {{ $group }}. Selecciona en Talana los documentos que corresponden al centro.</p>@endif
<form method="post" action="{{ route('rrhh-documentos.battery') }}">@csrf
@if($group)<input type="hidden" name="origen_est" value="1">@endif
<input type="hidden" name="version" value="{{ old('version',1) }}">
<div class="fields"><label>Nombre de batería<input required name="nombre" maxlength="150" value="{{ old('nombre',$group ? 'EST · '.$group : '') }}" placeholder="Ej. Ingreso EST · IDL Macul"></label>
@if(!$group)<label>Empresa<select name="empresa_id"><option value="">Todas</option>@foreach($companies as $company)<option value="{{ $company->empresa_id }}" @selected((string)old('empresa_id')===(string)$company->empresa_id)>{{ $company->empresa_nombre }}</option>@endforeach</select></label>@endif
@foreach(['centro_costo_nombre'=>'Centro de costo','cargo_nombre'=>'Cargo','tipo_contrato_nombre'=>'Tipo de contrato'] as $field=>$label)<label>{{ $label }}<select name="{{ $field }}" @required($group && $field==='centro_costo_nombre')><option value="">{{ $group && $field==='centro_costo_nombre' ? 'Selecciona un centro EST' : 'Todos' }}</option>@foreach($options[$field] as $option)<option @selected(old($field)===$option)>{{ $option }}</option>@endforeach</select></label>@endforeach</div>
<h3>Elige los documentos que debe incluir</h3>
@include('rrhh_documentos.document-picker')
<div class="actions"><button class="cta">Crear borrador y abrir documentos</button><a href="{{ route('rrhh-documentos.index') }}">Cancelar</a></div>
</form></section></div>
@endsection
