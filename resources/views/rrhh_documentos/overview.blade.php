@extends('layouts.app')
@section('title','Baterías documentales')
@include('rrhh_documentos.style')
@include('rrhh_documentos.workspace-style')
@section('content')
<div class="gdm doc-workspace">
@include('rrhh_documentos.navigation',['section'=>'batteries'])
@if(session('success'))<p class="success" role="status">{{ session('success') }}</p>@endif
<div class="doc-toolbar"><div><h2>Administra las baterías de cada centro</h2><p class="muted">Abre una batería para revisar sus archivos, confirmar los documentos y activar su versión.</p></div>
@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<a class="cta" href="{{ route('rrhh-documentos.battery-create') }}">Crear batería</a>@endif</div>
<section class="panel"><h3>Base → batería específica → lote</h3><p>1. Prepara y activa la base obligatoria para todos en su alcance. 2. Crea la batería de centro o cargo y selecciona la base que hereda. 3. Revisa la composición completa y prepara el lote con esa batería.</p><p class="muted">El lote usa la batería que selecciones y sus bases vinculadas. No busca ni agrega automáticamente todas las baterías que coincidan con el trabajador.</p></section>
<div class="doc-counts">@foreach(['BORRADOR'=>'En preparación','ACTIVA'=>'Activas','ARCHIVADA'=>'Archivadas'] as $state=>$label)<a href="{{ route('rrhh-documentos.index',['estado'=>$state]) }}"><strong>{{ $counts[$state] ?? 0 }}</strong>{{ $label }}</a>@endforeach</div>
<form method="get" class="panel doc-filter"><label>Buscar batería o centro<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre de la batería o centro de costo"></label><label>Estado<select name="estado"><option value="">Todos</option>@foreach(['BORRADOR'=>'En preparación','ACTIVA'=>'Activas','ARCHIVADA'=>'Archivadas'] as $state=>$label)<option value="{{ $state }}" @selected(($filters['estado'] ?? '')===$state)>{{ $label }}</option>@endforeach</select></label><div class="actions"><button class="cta">Filtrar</button><a href="{{ route('rrhh-documentos.index') }}">Limpiar</a></div></form>
<p class="muted">{{ $batteries->total() }} versiones encontradas</p>
<div class="doc-card-grid">
@foreach($batteries as $battery)
@php
$docs=json_decode($battery->documentos,true);$details=json_decode($battery->document_details ?? '{}',true);$rules=json_decode($battery->reglas,true);
$composition=app(\App\Services\RrhhDocumentCompositionService::class)->resolve($battery);
$validated=collect($docs)->filter(fn($name)=>!empty($details[$name]['validated_at']))->count();
@endphp
<article class="doc-card"><div class="doc-toolbar"><span class="doc-state {{ $battery->estado }}">{{ ['BORRADOR'=>'En preparación','ACTIVA'=>'Activa','ARCHIVADA'=>'Archivada'][$battery->estado] }}</span><span class="muted">Versión {{ $battery->version }}</span></div>
<h2>{{ $battery->nombre }}</h2><p>{{ ['base'=>'Base común obligatoria','specific'=>'Específica con herencia','independent'=>'Pendiente de clasificar'][$battery->kind] }} · {{ count($composition['documents']) }} documentos totales</p>@if($composition['errors'] || $composition['pending'])<p class="note">Composición pendiente: abre la batería para resolverla.</p>@endif<p class="muted">{{ $rules['centro_costo_nombre'] ?? 'Todos los centros' }} · {{ $rules['cargo_nombre'] ?? 'Todos los cargos' }}</p>
<div><p>{{ $validated }} de {{ count($docs) }} documentos validados</p><progress max="{{ max(1,count($docs)) }}" value="{{ $validated }}" aria-label="Avance de validación"></progress></div>
<ul>@foreach(array_slice($docs,0,3) as $name)<li>{{ $name }}</li>@endforeach
@if(count($docs)>3)<li>Y {{ count($docs)-3 }} documentos más</li>@endif</ul>
<footer><p class="muted">Actualizada {{ \Carbon\Carbon::parse($battery->updated_at)->timezone('America/Santiago')->format('d/m/Y H:i') }}</p><a class="cta {{ $battery->estado==='ARCHIVADA' ? 'secondary' : '' }}" href="{{ route('rrhh-documentos.battery-show',$battery->id) }}">{{ $battery->estado==='BORRADOR' ? 'Abrir y revisar documentos' : 'Ver batería y documentos' }}</a></footer></article>
@endforeach</div>
@if($batteries->isEmpty())<section class="doc-empty"><h2>{{ count($counts) ? 'No hay baterías con estos filtros' : 'Comienza creando tu primera batería' }}</h2><p class="muted">{{ count($counts) ? 'Prueba con otro centro o estado.' : 'Elige el centro, agrega documentos de Talana y revisa sus PDF antes de activar la batería.' }}</p><div class="actions">@if(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<a class="cta" href="{{ route('rrhh-documentos.battery-create') }}">Crear batería</a>@endif<a href="{{ route('rrhh-documentos.est') }}">Consultar guía EST</a></div></section>@endif
{{ $batteries->links() }}
</div>
@endsection
