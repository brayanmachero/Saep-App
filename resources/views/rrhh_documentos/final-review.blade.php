@extends('layouts.app')
@section('title','Revisión final de batería')
@include('rrhh_documentos.style')
@include('rrhh_documentos.workspace-style')
@section('content')
@php
$canEdit=auth()->user()->tieneAcceso('gestion_documental','puede_editar');
$labels=['sin-vincular'=>'Sin vincular','por-revisar'=>'Por revisar','observados'=>'Requiere corrección','validados'=>'Validado'];
@endphp
<div class="gdm doc-workspace">
@include('rrhh_documentos.navigation',['section'=>'batteries'])
<a href="{{ route('rrhh-documentos.battery-show',$record->id) }}">← Volver a los documentos</a>
@if($errors->any())<div class="panel err" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
<section class="panel"><h2>{{ $record->nombre }} · versión {{ $record->version }}</h2>
<p class="muted">Revisa los pendientes y los cambios antes de habilitar esta versión para nuevos lotes.</p>
<div class="fields">
@foreach(['empresa_id'=>'Empresa','centro_costo_nombre'=>'Centro de costo','cargo_nombre'=>'Cargo','tipo_contrato_nombre'=>'Tipo de contrato'] as $field=>$label)<div><strong>{{ $label }}</strong><p class="muted">{{ $field==='empresa_id' && !empty($rules[$field]) ? ($companies->firstWhere('empresa_id',$rules[$field])?->empresa_nombre ?? ('Empresa #'.$rules[$field])) : ($rules[$field] ?? 'Todos') }}</p></div>@endforeach
</div>
<div class="doc-counts">@foreach($review['counts'] as $state=>$count)<a href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'filtro'=>$state]) }}"><strong>{{ $count }}</strong>{{ $labels[$state] }}</a>@endforeach</div>
@if(!$documents)<div class="note">La batería está vacía. Agrega documentos antes de activarla.</div>
@elseif($review['ready'])<div class="success">Todos los documentos están vinculados y validados. No quedan observaciones abiertas.</div>
@else<h3>Pendientes que bloquean la activación</h3><div class="final-issues">@foreach($review['issues'] as $issue)<div class="doc-reference-row"><div><strong>{{ $issue['name'] }}</strong><p class="muted">{{ $labels[$issue['state']] }}</p></div><a class="cta secondary" href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'documento'=>$issue['position']]) }}#review">Resolver pendiente</a></div>@endforeach</div>@endif
</section>
<section class="panel"><h2>Comparar con otra versión</h2>
<p class="muted">Se comparan documentos vinculados, registros de Talana y reglas de aplicación. Esta comparación no analiza diferencias en el texto interno de los PDF.</p>
@if($others->isEmpty())<p>Esta es la primera versión; aún no hay otra batería de esta familia para comparar.</p>
@else<form method="get" class="fields"><label>Versión de referencia<select name="comparar" required><option value="">Elige una versión</option>@foreach($others as $other)<option value="{{ $other->id }}" @selected($baseline && $baseline->id===$other->id)>v{{ $other->version }} · {{ ['ACTIVA'=>'Activa','BORRADOR'=>'En preparación','ARCHIVADA'=>'Archivada'][$other->estado] }}</option>@endforeach</select></label><div class="actions"><button class="cta secondary">Comparar versiones</button></div></form>
@if($comparison)
<h3>De v{{ $baseline->version }} a v{{ $record->version }}</h3>
@forelse($comparison['changes'] as $change)<article class="doc-reference-row"><div><strong>{{ ['added'=>'Agregado','removed'=>'Retirado','replaced'=>'Reemplazado o vinculado','moved'=>'Cambio de posición'][$change['type']] }}</strong>@if($change['before'])<p>Antes: {{ $change['before'] }}@if(!empty($change['before_id'])) · Talana #{{ $change['before_id'] }}@endif</p>@endif
@if($change['after'])<p>Ahora: {{ $change['after'] }}@if(!empty($change['after_id'])) · Talana #{{ $change['after_id'] }}@endif</p>@endif</div>@if($change['position']!==null)<a class="cta secondary" href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'documento'=>$change['position']]) }}#review">Ver documento</a>@endif</article>@empty<p>No hay cambios de documentos, registros vinculados o posiciones.</p>@endforelse
@foreach($comparison['rules'] as $rule)<div class="note"><strong>{{ ['empresa_id'=>'Empresa (ID Talana)','centro_costo_nombre'=>'Centro','cargo_nombre'=>'Cargo','tipo_contrato_nombre'=>'Tipo de contrato'][$rule['field']] }}</strong><p>{{ $rule['before'] ?: 'Todos' }} → {{ $rule['after'] ?: 'Todos' }}</p></div>@endforeach
@endif
@endif
</section>
<section class="panel"><h2>Activación en SAEP</h2><p>Esta versión quedará disponible para nuevos lotes. Si existe una versión activa de esta batería, se archivará; los lotes anteriores conservarán sus documentos originales.</p><p class="muted">Esta acción no genera archivos personales ni los envía a Talana.</p>
@if($editable && $canEdit)<form method="post" action="{{ route('rrhh-documentos.battery-activate',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><button class="cta" @disabled(!$review['ready'])>Activar versión {{ $record->version }}</button></form>
@else<p class="muted">{{ $record->estado==='ACTIVA' ? 'Esta versión ya está activa.' : ($record->estado==='ARCHIVADA' ? 'Esta versión está archivada.' : 'Tu rol no permite activar esta batería.') }}</p>@endif
</section></div>
@endsection
