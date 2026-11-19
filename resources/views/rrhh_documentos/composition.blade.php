<section class="panel" id="composition"><h2>Así se compone esta batería</h2>
<p class="muted">{{ ['base'=>'Base común obligatoria para su alcance.','specific'=>'Base heredada + documentos adicionales para este alcance.','independent'=>'Batería anterior: clasifica su función antes de utilizarla como base.'][$record->kind] }}</p>
<div class="doc-counts"><div><strong>{{ count($composition['documents']) }}</strong>Documentos únicos en total</div><div><strong>{{ collect($composition['origins'])->filter(fn($origins)=>collect($origins)->contains('inherited',true))->count() }}</strong>Heredados</div><div><strong>{{ count($documents) }}</strong>Requisitos propios</div></div>
<p class="muted">Alcance combinado: @include('rrhh_documentos.scope-label',['scope'=>$composition['rules']]). Se aplican todas las condiciones a la vez.</p>
@foreach(array_merge($composition['errors'],$composition['pending']) as $message)<p class="note" role="alert">{{ $message }}</p>@endforeach
<div class="doc-source-chain">@foreach($composition['sources'] as $source)<a class="cta secondary" href="{{ route('rrhh-documentos.battery-show',$source['id']) }}">{{ $source['nombre'] }} · v{{ $source['version'] }} · {{ $source['estado'] }}</a>@endforeach</div>
@if(count($composition['sources'])>1)
<details open><summary>Documentos heredados · se mantienen en su batería de origen</summary>
<div class="doc-inherited-list">@foreach($composition['documents'] as $name)
@php($origin=collect($composition['origins'][$name])->firstWhere('inherited',true))
@if($origin)<article class="doc-reference-row"><div><strong>{{ $name }}</strong><p class="muted">{{ $origin['nombre'] }} · v{{ $origin['version'] }}</p>@include('rrhh_documentos.document-status',['metadata'=>$composition['details'][$name] ?? []])</div><a class="cta secondary" href="{{ route('rrhh-documentos.battery-show',['battery'=>$origin['battery_id'],'documento'=>$origin['position']]) }}#review">Ver documento en origen</a></article>@endif
@endforeach</div></details>
@endif
<p class="muted">Un mismo registro de Talana se incluye una sola vez. Los heredados no se pueden quitar desde esta batería. Cambiar la base exige revisar una nueva versión; los lotes anteriores mantienen su composición original.</p>
@if($editable && $canEdit)<details><summary>Configurar base y documentos heredados</summary><form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="composition">@include('rrhh_documentos.composition-fields')<button class="cta">Guardar composición</button></form></details>@endif
</section>
