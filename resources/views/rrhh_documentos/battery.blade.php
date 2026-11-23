@extends('layouts.app')
@section('title','Revisar batería documental')
@include('rrhh_documentos.style')
@include('rrhh_documentos.workspace-style')
@section('content')
@php
$canEdit=auth()->user()->tieneAcceso('gestion_documental','puede_editar');
$validated=collect($documents)->filter(fn($name)=>!empty($details[$name]['validated_at']))->count();
$pending=count($documents)-$validated;
@endphp
<div class="gdm doc-workspace">
@include('rrhh_documentos.navigation',['section'=>'batteries'])
<div class="doc-toolbar"><a href="{{ route('rrhh-documentos.index') }}">← Todas las baterías</a><a href="{{ route('rrhh-documentos.battery-final',$record->id) }}">Revisión final y comparación de versiones</a></div>
@if(session('success'))<p class="success" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="panel err" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
<section class="panel">
<div class="doc-toolbar"><div><h2>{{ $record->nombre }} · v{{ $record->version }}</h2><p class="muted">{{ $composition['rules']['centro_costo_nombre'] ?? 'Todos los centros' }} · {{ $composition['rules']['cargo_nombre'] ?? 'Todos los cargos' }}</p><span class="doc-state {{ $record->estado }}">{{ ['BORRADOR'=>'En preparación','ACTIVA'=>'Activa','ARCHIVADA'=>'Archivada'][$record->estado] }}</span></div>
<div class="actions">
@if($editable && $canEdit)<button type="button" class="cta secondary" data-open-add>Agregar documentos</button><a class="cta" href="{{ route('rrhh-documentos.battery-final',$record->id) }}">Revisar y activar</a>
@elseif(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<form method="post" action="{{ route('rrhh-documentos.battery-clone',$record->id) }}">@csrf<button class="cta">Crear nueva versión para editar</button></form>@endif
@if($record->estado==='ACTIVA')<a class="cta secondary" href="{{ route('rrhh-documentos.lots') }}">Preparar lote de trabajadores</a>@endif
</div></div>
<p class="muted">Empresa documental: {{ $companies->firstWhere('empresa_id',(int)($composition['rules']['empresa_id'] ?? 0))?->empresa_nombre ?? 'Define la empresa en la configuración antes de agregar documentos' }}</p>
@if(!empty($composition['pending']))<details class="note"><summary>Revisión pendiente de empresa y composición ({{ count($composition['pending']) }})</summary><ul>@foreach($composition['pending'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></details>@endif
<p class="doc-field-hint">Activar deja esta versión disponible en SAEP. La preparación de archivos para cada trabajador y su envío se gestionan después, desde los lotes.</p>
@if($documents)
<div class="doc-counts" aria-label="Resumen de la batería">
@foreach(['todos'=>[count($documents),'Requisitos propios'],'sin-vincular'=>[$review['counts']['sin-vincular'],'Sin vincular'],'por-revisar'=>[$review['counts']['por-revisar'],'Por revisar'],'observados'=>[$review['counts']['observados'],'Requieren corrección'],'validados'=>[$review['counts']['validados'],'Validados']] as $key=>$metric)<a href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'filtro'=>$key]) }}"><strong>{{ $metric[0] }}</strong>{{ $metric[1] }}</a>@endforeach
</div><p class="muted">{{ $linked }} vinculados de {{ count($documents) }} requisitos. Vincular un archivo no valida su contenido.</p>
<div style="margin-top:16px"><p>{{ $validated }} de {{ count($documents) }} documentos validados</p><progress max="{{ max(1,count($documents)) }}" value="{{ $validated }}" aria-label="Avance de revisión"></progress></div>
@endif
@if($editable)<p class="muted">{{ !$documents ? ($composition['documents'] ? 'Sin adicionales propios. Revisa los documentos heredados y la composición.' : 'Agrega documentos para comenzar.') : ($pending ? 'Selecciona un documento, revisa su PDF y confirma que corresponde a esta batería.' : 'Revisión de documentos propios completa. Revisa también la composición antes de activar.') }}</p>@endif
@if(count($versions)>1)<nav class="doc-version-links" aria-label="Versiones de la batería">@foreach($versions as $version)<a href="{{ route('rrhh-documentos.battery-show',$version->id) }}" @if($version->id===$record->id) aria-current="page" @endif>v{{ $version->version }} · {{ ['BORRADOR'=>'En preparación','ACTIVA'=>'Activa','ARCHIVADA'=>'Archivada'][$version->estado] }}</a>@endforeach</nav>@endif
</section>
@include('rrhh_documentos.composition')
@if($documents)
<div class="doc-review-grid">
<section class="panel"><h3>Documentos propios de la batería</h3><p class="muted">Selecciona un archivo para revisarlo.</p>
<form method="get"><label>Mostrar documentos<select name="filtro" onchange="this.form.submit()">@foreach(['todos'=>'Todos','sin-vincular'=>'Sin vincular','por-revisar'=>'Por revisar','observados'=>'Requieren corrección','validados'=>'Validados'] as $value=>$label)<option value="{{ $value }}" @selected($filter===$value)>{{ $label }}</option>@endforeach</select></label><noscript><button class="cta">Filtrar</button></noscript></form>
<ul class="doc-list" aria-label="Documentos">
@foreach($visibleDocuments as $position=>$name)<li><a href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'documento'=>$position,'filtro'=>$filter]) }}#review" aria-current="{{ $selectedPosition===$position ? 'true' : 'false' }}"><strong>{{ $name }}</strong>@include('rrhh_documentos.document-status',['metadata'=>$details[$name] ?? []])</a></li>@endforeach
</ul></section>
<section class="panel" id="review">
@if($selectedName !== null)
<nav class="actions" aria-label="Recorrer documentos">@if($previous !== null)<a class="cta secondary" href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'documento'=>$previous,'filtro'=>$filter]) }}#review">Anterior</a>@endif
@if($next !== null)<a class="cta secondary" href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'documento'=>$next,'filtro'=>$filter]) }}#review">Siguiente</a>@endif</nav>
<h3 class="doc-review-title">{{ $selectedName }}</h3>
@if(!empty($selectedDetails['talana_id']) && !empty($composition['rules']['empresa_id']) && app(\App\Services\RrhhDocumentBatteryService::class)->sourceCompany($selectedDetails)!==(int)$composition['rules']['empresa_id'])
<p class="note" role="status">Este archivo fue vinculado desde otra base de Talana. Usa «Reemplazar documento» para elegir la versión de la empresa de esta batería antes de validarlo.</p>
@endif
@include('rrhh_documentos.document-status',['metadata'=>$selectedDetails])
@if($editable && $canEdit && !empty($selectedDetails['talana_id']) && empty($selectedDetails['validated_at']) && ($selectedDetails['observation']['state'] ?? null)!=='open')
<form class="doc-confirm doc-validation-action" method="post" action="{{ route('rrhh-documentos.battery-validate',[$record->id,$selectedPosition]) }}">@csrf<h3>Validar este documento</h3><p class="muted">Revisa el PDF de abajo. Cuando cargue, marca la confirmación y pulsa Validar documento.</p><input type="hidden" name="revision" value="{{ $record->revision }}">
<input type="hidden" name="filtro" value="{{ $filter }}"><label class="check"><input type="checkbox" name="confirmed" value="1" id="confirm-document" required disabled> Revisé el archivo y confirmo que el contenido y la versión corresponden a esta batería.</label><button class="cta" id="validate-document" disabled>Validar documento</button></form>
@endif
@if($editable && $canEdit && ($selectedDetails['observation']['state'] ?? null)==='open')
<div class="doc-validation-action"><h3>Validación bloqueada por una observación</h3><p class="muted">Primero reemplaza el archivo o registra cómo se resolvió la observación. Después podrás revisar el PDF y validarlo.</p><a class="cta secondary" href="#document-correction" onclick="document.getElementById('document-correction').open=true">Resolver observación</a></div>
@endif
<div class="actions doc-document-actions">@if($editable && $canEdit)<button type="button" class="cta secondary" data-open-replace>{{ !empty($selectedDetails['talana_id']) ? 'Reemplazar documento' : 'Vincular a este requisito' }}</button>@endif
@if($editable && $canEdit)<form class="doc-remove-form" onsubmit="return confirm('¿Quitar este documento de la batería? El archivo seguirá disponible en Talana y el cambio quedará en el historial.')" method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="remove"><input type="hidden" name="position" value="{{ $selectedPosition }}"><button class="cta doc-danger" aria-label="Quitar {{ $selectedName }} de esta batería">Quitar de esta batería</button></form>@endif</div>
@if($editable && $canEdit)<p class="doc-field-hint">Quitar retira este requisito de la batería. No borra el archivo de Talana; el cambio queda en el historial.</p>@endif
@if(!empty($selectedDetails['source_reference']))<p class="doc-field-hint">Requisito de origen: {{ $selectedDetails['source_reference'] }}</p>@endif
@if(!empty($selectedDetails['review_note']))<div class="note"><strong>Resultado de la conciliación</strong><p>{{ $selectedDetails['review_note'] }}</p>
@if(!empty($selectedDetails['candidate_names']))<p>Posibles títulos encontrados; requieren confirmar equivalencia:</p><ul>@foreach($selectedDetails['candidate_names'] as $candidate)<li>{{ $candidate }}</li>@endforeach</ul>@endif
@if(!empty($selectedDetails['checked_at']))<p class="muted">Consulta realizada {{ \Carbon\Carbon::parse($selectedDetails['checked_at'])->timezone('America/Santiago')->format('d/m/Y H:i') }}.</p>@endif
</div>@endif
@if(!empty($selectedDetails['observation']))
<div class="note" role="status"><strong>{{ $selectedDetails['observation']['state']==='open' ? 'Requiere corrección' : 'Observación resuelta · pendiente de nueva revisión si corresponde' }}</strong><p>{{ $selectedDetails['observation']['reason'] }}</p><p class="muted">Registrada por {{ $selectedDetails['observation']['name'] }} · {{ \Carbon\Carbon::parse($selectedDetails['observation']['at'])->timezone('America/Santiago')->format('d/m/Y H:i') }}</p>
@if(isset($selectedDetails['observation']['resolution']))<p>Resolución: {{ $selectedDetails['observation']['resolution']['reason'] }}</p>@endif</div>
@endif
@if(!empty($selectedDetails['validated_at']))<p class="muted">Validado por {{ $selectedDetails['validated_name'] ?? ('usuario #'.$selectedDetails['validated_by']) }} · {{ \Carbon\Carbon::parse($selectedDetails['validated_at'])->timezone('America/Santiago')->format('d/m/Y H:i') }}</p>@endif
@if(!empty($selectedDetails['talana_id']))
<p class="doc-field-hint">Archivo de referencia Talana #{{ $selectedDetails['talana_id'] }}. Puede contener datos del trabajador al que pertenece.</p>
<div class="actions"><button type="button" class="cta secondary" id="expand-preview">Ampliar PDF</button><a class="cta secondary" href="{{ route('rrhh-documentos.battery-pdf',[$record->id,$selectedPosition]) }}" target="_blank" rel="noopener">Abrir en otra pestaña</a></div>
<div class="doc-review-status" id="pdf-status" role="status" aria-live="polite">Cargando PDF…</div>
<button type="button" class="cta secondary" id="retry-preview" hidden>Reintentar carga</button>
<div id="preview-home"><div id="preview-frame"><iframe class="pdf-frame" id="document-preview" title="Vista previa de {{ $selectedName }}" data-url="{{ route('rrhh-documentos.battery-pdf',[$record->id,$selectedPosition]) }}"></iframe></div></div>

<noscript><p><a href="{{ route('rrhh-documentos.battery-preview',[$record->id,$selectedPosition]) }}">Abrir la vista de revisión del documento</a></p></noscript>
@else<div class="doc-empty"><h3>Archivo pendiente de vincular</h3><p class="muted">Busca el título oficial y vincúlalo a este requisito; no se agregará un requisito adicional.</p>@if($editable && $canEdit)<button type="button" class="cta" data-open-replace>Buscar y vincular documento</button>@endif</div>@endif
@if($editable && $canEdit)
<details id="document-correction" class="doc-confirm" @if(in_array(old('action'),['observe','resolve']) && $errors->any()) open @endif><summary>{{ ($selectedDetails['observation']['state'] ?? null)==='open' ? 'Registrar cómo se corrigió' : 'Marcar que requiere corrección' }}</summary>
<form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="position" value="{{ $selectedPosition }}"><input type="hidden" name="action" value="{{ ($selectedDetails['observation']['state'] ?? null)==='open' ? 'resolve' : 'observe' }}">
<label>Motivo o detalle de la corrección<textarea name="reason" rows="3" required minlength="5" maxlength="1500">{{ in_array(old('action'),['observe','resolve']) ? old('reason') : '' }}</textarea></label>
<p class="muted">Las observaciones abiertas bloquean la validación y la activación. Resolver una observación requiere revisar y validar nuevamente el PDF.</p><button class="cta secondary">{{ ($selectedDetails['observation']['state'] ?? null)==='open' ? 'Guardar corrección y volver a revisar' : 'Guardar observación' }}</button></form></details>
@endif

@else<div class="doc-empty"><h3>No hay documentos con este estado</h3><a href="{{ route('rrhh-documentos.battery-show',$record->id) }}">Ver todos los documentos</a></div>@endif
</section></div>
@else<section class="doc-empty"><h2>{{ $composition['documents'] ? 'Sin documentos adicionales propios' : 'La batería está vacía' }}</h2><p class="muted">{{ $composition['documents'] ? 'Los documentos heredados ya están incluidos. Agrega sólo los adicionales que correspondan.' : 'Agrega documentos desde Talana para comenzar.' }}</p>@if($editable && $canEdit)<div class="actions"><button type="button" class="cta" data-open-add>Agregar documento propio</button></div>@endif</section>@endif
@include('rrhh_documentos.battery-settings')
@if($editable && $canEdit)
<dialog id="add-documents" aria-labelledby="add-documents-title"><div class="doc-toolbar"><h2 id="add-documents-title">Agregar documentos a esta batería</h2><button type="button" class="cta secondary" data-close-add>Cerrar</button></div><form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="add"><input type="hidden" name="position" value="{{ $selectedPosition }}"><p id="selection-mode-hint" class="muted"></p><div class="selection-workspace">@include('rrhh_documentos.document-picker',['documentCompanyId'=>$composition['rules']['empresa_id'] ?? ''])<div id="candidate-review" hidden><p role="status" id="candidate-status"></p><iframe class="pdf-frame" id="candidate-preview" title="Vista previa del candidato" hidden></iframe></div></div><div class="selection-footer"><button class="cta" id="save-selection">Guardar documentos y continuar revisión</button></div></form></dialog>
@endif
<dialog id="full-preview" aria-labelledby="full-preview-title"><div class="doc-toolbar"><h2 id="full-preview-title">Vista ampliada del documento</h2><button type="button" class="cta secondary" id="close-preview">Cerrar vista ampliada</button></div><div id="full-preview-content"></div></dialog>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const add=document.getElementById('add-documents');
    function openSelection(mode) {
        add.querySelector('[name="action"]').value=mode;
        add.querySelector('.document-picker').dataset.limit=mode==='replace'?'1':'50';
        document.getElementById('add-documents-title').textContent=mode==='replace'?'Vincular o reemplazar este requisito':'Agregar documentos a esta batería';
        document.getElementById('selection-mode-hint').textContent=mode==='replace'?'Selecciona un solo archivo. Conservaremos el requisito de origen y el cambio quedará en el historial. El archivo necesitará nueva validación.':'Selecciona los documentos adicionales.';
        document.getElementById('save-selection').textContent=mode==='replace'?'Confirmar vínculo y revisar documento':'Guardar documentos y continuar revisión';
        add.showModal();
    }
    document.querySelectorAll('[data-open-add]').forEach(button=>button.addEventListener('click',()=>openSelection('add')));
    document.querySelectorAll('[data-open-replace]').forEach(button=>button.addEventListener('click',()=>openSelection('replace')));
    document.querySelector('[data-close-add]')?.addEventListener('click',()=>add.close());
    @if($errors->any() && in_array(old('action'),['add','replace']))
    if(add)openSelection(@json(old('action')));
    @endif
    let candidateUrl=null,candidateRequest=0;
    add?.addEventListener('document-preview',async event=>{
        const requestId=++candidateRequest,area=document.getElementById('candidate-review'),status=document.getElementById('candidate-status'),iframe=document.getElementById('candidate-preview');
        area.hidden=false;iframe.hidden=true;status.textContent='Cargando vista previa del candidato…';
        try {
            const response=await fetch(@json(route('rrhh-documentos.candidate-pdf')),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/pdf','X-CSRF-TOKEN':add.querySelector('[name="_token"]').value},body:JSON.stringify({selection:event.detail.value})});
            if(!response.ok||!response.headers.get('Content-Type')?.includes('application/pdf'))throw Error('No se pudo abrir el PDF. Puedes reintentar desde Ver PDF.');
            const blob=await response.blob();if(requestId!==candidateRequest)return;
            if(candidateUrl)URL.revokeObjectURL(candidateUrl);candidateUrl=URL.createObjectURL(blob);iframe.src=candidateUrl;iframe.hidden=false;status.textContent='Vista previa: '+event.detail.name+'. Confirma el vínculo para continuar su revisión.';
        }catch(error){if(requestId===candidateRequest)status.textContent=error.message;}
    });
    window.addEventListener('pagehide',()=>{if(candidateUrl)URL.revokeObjectURL(candidateUrl);});
    const frame=document.getElementById('document-preview');
    if(!frame)return;
    const status=document.getElementById('pdf-status'),retry=document.getElementById('retry-preview'),check=document.getElementById('confirm-document'),validate=document.getElementById('validate-document'),expand=document.getElementById('expand-preview');
    let blobUrl=null,loading=false;
    async function load() {
        if(loading)return;
        loading=true;retry.hidden=true;expand.disabled=true;status.textContent='Cargando PDF…';
        if(check){check.disabled=true;check.checked=false;validate.disabled=true;}
        try {
            const response=await fetch(frame.dataset.url,{headers:{Accept:'application/pdf'},credentials:'same-origin'});
            if(!response.ok||!response.headers.get('Content-Type')?.includes('application/pdf'))throw Error('No se pudo cargar el PDF. Reintenta o vuelve a seleccionar el documento.');
            const blob=await response.blob();if(blobUrl)URL.revokeObjectURL(blobUrl);
            blobUrl=URL.createObjectURL(blob);frame.src=blobUrl;
            status.textContent='PDF cargado. Revisa el contenido antes de confirmar.';
            if(check)check.disabled=false;expand.disabled=false;
        }catch(error){status.textContent=error.message;retry.hidden=false;}
        finally{loading=false;}
    }
    check?.addEventListener('change',()=>validate.disabled=!check.checked);
    retry.addEventListener('click',load);
    const dialog=document.getElementById('full-preview'),wrapper=document.getElementById('preview-frame');
    expand.addEventListener('click',()=>{document.getElementById('full-preview-content').append(wrapper);dialog.showModal();});
    document.getElementById('close-preview').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>document.getElementById('preview-home').append(wrapper));
    window.addEventListener('pagehide',()=>{if(blobUrl)URL.revokeObjectURL(blobUrl);});
    load();
})();
</script>
@endpush
