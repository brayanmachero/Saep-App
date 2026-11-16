@extends('layouts.app')
@section('title','Detalle de batería documental')
@include('rrhh_documentos.style')
@include('rrhh_documentos.battery-style')
@section('content')
@php
$canEdit=auth()->user()->tieneAcceso('gestion_documental','puede_editar');
$validated=collect($documents)->filter(fn($name)=>!empty($details[$name]['validated_at']))->count();
@endphp
<div class="gdm battery-view">
<a href="{{ route('rrhh-documentos.index') }}">← Gestión documental</a>
@if(session('success'))<p class="success" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="panel err" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
<section class="panel">
<div class="battery-head"><div><h2>{{ $record->nombre }} · v{{ $record->version }}</h2><p>{{ ['BORRADOR'=>'Borrador','ACTIVA'=>'Activa','ARCHIVADA'=>'Archivada'][$record->estado] }} · {{ $validated }} de {{ count($documents) }} documentos validados</p></div>
@if($editable && $canEdit)<form method="post" action="{{ route('rrhh-documentos.battery-activate',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><button class="cta" @disabled(!$documents || $validated!==count($documents))>Activar batería</button></form>
@elseif(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<form method="post" action="{{ route('rrhh-documentos.battery-clone',$record->id) }}">@csrf<button class="cta">Crear nueva versión para editar</button></form>@endif</div>
@if($record->estado === 'ACTIVA')<p class="muted">Disponible para nuevos lotes desde {{ \Carbon\Carbon::parse($record->activated_at)->timezone('America/Santiago')->format('d/m/Y H:i') }}.</p>
@elseif($editable)<p class="muted">Abre cada documento, confirma su contenido y activa la batería cuando la revisión esté completa.</p>@endif
</section>
<section class="panel"><h3>Documentos de la batería</h3>
<ul class="battery-list">@forelse($documents as $position=>$name)
<li class="battery-row"><div><strong>{{ $name }}</strong>
@if(!empty($details[$name]['validated_at']))<small class="status-valid">Validado por {{ $details[$name]['validated_name'] ?? ('usuario #'.$details[$name]['validated_by']) }} · {{ \Carbon\Carbon::parse($details[$name]['validated_at'])->timezone('America/Santiago')->format('d/m/Y H:i') }}</small>@else<small>Pendiente de revisión</small>@endif
@if(empty($details[$name]['talana_id']))<small>Vincula el archivo usando el buscador de Talana.</small>@else<small>Registro de referencia Talana #{{ $details[$name]['talana_id'] }}</small>@endif
</div><div class="actions">
<a class="cta secondary" href="{{ route('rrhh-documentos.battery-preview',[$record->id,$position]) }}">{{ $editable && $canEdit && empty($details[$name]['validated_at']) ? 'Ver y validar' : 'Ver documento' }}</a>
@if($editable && $canEdit)<form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="remove"><input type="hidden" name="position" value="{{ $position }}"><button class="cta remove" aria-label="Quitar {{ $name }} de esta batería">Quitar de esta batería</button></form>@endif
</div></li>
@empty<li>La batería está vacía. Agrega documentos desde Talana para comenzar.</li>@endforelse</ul>
</section>
@if($editable && $canEdit)
<section class="panel"><h3>Agregar documentos</h3><form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="add">@include('rrhh_documentos.document-picker')<button class="cta">Guardar documentos en esta batería</button></form></section>
@endif
<section class="panel"><h3>Aplicación de la batería</h3>
@if($editable && $canEdit)<form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="rules"><div class="fields"><label>Empresa<select name="empresa_id"><option value="">Todas</option>@foreach($companies as $company)<option value="{{ $company->empresa_id }}" @selected((string)old('empresa_id',$rules['empresa_id'] ?? '') === (string)$company->empresa_id)>{{ $company->empresa_nombre }}</option>@endforeach</select></label>
@foreach(['centro_costo_nombre'=>'Centro de costo','cargo_nombre'=>'Cargo','tipo_contrato_nombre'=>'Tipo de contrato'] as $field=>$label)<label>{{ $label }}<select name="{{ $field }}"><option value="">Todos</option>@foreach($options[$field] as $option)<option @selected(old($field,$rules[$field] ?? '') === $option)>{{ $option }}</option>@endforeach</select></label>@endforeach</div><p class="muted">Cambiar la aplicación requiere revisar nuevamente los documentos.</p><button class="cta">Guardar aplicación</button></form>
@else<p class="muted">@foreach($rules as $field=>$value){{ ['empresa_id'=>'Empresa','centro_costo_nombre'=>'Centro','cargo_nombre'=>'Cargo','tipo_contrato_nombre'=>'Tipo de contrato'][$field] ?? $field }} · {{ $value ?: 'Todos' }}<br>@endforeach</p>@endif
</section>
<details class="panel"><summary>Historial de cambios</summary><ul class="battery-events">@forelse($events as $event)<li><strong>{{ ['create'=>'Borrador creado','remove'=>'Documento retirado','add'=>'Documentos agregados o vinculados','rules'=>'Aplicación modificada','clone'=>'Nueva versión creada','validate'=>'Documento validado','activate'=>'Batería activada','archive'=>'Versión anterior archivada'][$event->action] ?? $event->action }}</strong> · usuario #{{ $event->actor_id }} · {{ \Carbon\Carbon::parse($event->created_at)->timezone('America/Santiago')->format('d/m/Y H:i') }}@if(isset(json_decode($event->detail,true)['documento']))<br>{{ json_decode($event->detail,true)['documento'] }}@endif</li>@empty<li>Aún no hay cambios registrados.</li>@endforelse</ul>{{ $events->links() }}</details>
</div>
@endsection
