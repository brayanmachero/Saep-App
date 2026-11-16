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
<a href="{{ route('rrhh-documentos.index') }}">← Todas las baterías</a>
@if(session('success'))<p class="success" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="panel err" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
<section class="panel">
<div class="doc-toolbar"><div><h2>{{ $record->nombre }} · v{{ $record->version }}</h2><p class="muted">{{ $rules['centro_costo_nombre'] ?? 'Todos los centros' }} · {{ $rules['cargo_nombre'] ?? 'Todos los cargos' }}</p><span class="doc-state {{ $record->estado }}">{{ ['BORRADOR'=>'En preparación','ACTIVA'=>'Activa','ARCHIVADA'=>'Archivada'][$record->estado] }}</span></div>
<div class="actions">
@if($editable && $canEdit)<button type="button" class="cta secondary" data-open-add>Agregar documentos</button><form method="post" action="{{ route('rrhh-documentos.battery-activate',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><button class="cta" @disabled(!$documents || $pending)>Activar batería</button></form>
@elseif(auth()->user()->tieneAcceso('gestion_documental','puede_crear'))<form method="post" action="{{ route('rrhh-documentos.battery-clone',$record->id) }}">@csrf<button class="cta">Crear nueva versión para editar</button></form>@endif
@if($record->estado==='ACTIVA')<a class="cta secondary" href="{{ route('rrhh-documentos.lots') }}">Preparar lote de trabajadores</a>@endif
</div></div>
<p class="doc-field-hint">Activar deja esta versión disponible en SAEP. La preparación de archivos para cada trabajador y su envío se gestionan después, desde los lotes.</p>
<div style="margin-top:16px"><p>{{ $validated }} de {{ count($documents) }} documentos validados</p><progress max="{{ max(1,count($documents)) }}" value="{{ $validated }}" aria-label="Avance de revisión"></progress></div>
@if($editable)<p class="muted">{{ !$documents ? 'Agrega documentos para comenzar.' : ($pending ? 'Selecciona un documento, revisa su PDF y confirma que corresponde a esta batería.' : 'Revisión completa. Puedes activar esta versión para utilizarla en nuevos lotes.') }}</p>@endif
@if(count($versions)>1)<nav class="doc-version-links" aria-label="Versiones de la batería">@foreach($versions as $version)<a href="{{ route('rrhh-documentos.battery-show',$version->id) }}" @if($version->id===$record->id) aria-current="page" @endif>v{{ $version->version }} · {{ ['BORRADOR'=>'En preparación','ACTIVA'=>'Activa','ARCHIVADA'=>'Archivada'][$version->estado] }}</a>@endforeach</nav>@endif
</section>
@if($documents)
<div class="doc-review-grid">
<section class="panel"><h3>Documentos de la batería</h3><p class="muted">Selecciona un archivo para revisarlo.</p>
<ul class="doc-list" aria-label="Documentos">
@foreach($documents as $position=>$name)<li><a href="{{ route('rrhh-documentos.battery-show',['battery'=>$record->id,'documento'=>$position]) }}#review" aria-current="{{ $selectedPosition===$position ? 'true' : 'false' }}"><strong>{{ $name }}</strong><small>{{ !empty($details[$name]['validated_at']) ? 'Validado' : 'Pendiente de revisión' }}</small></a></li>@endforeach
</ul></section>
<section class="panel" id="review">
<h3 class="doc-review-title">{{ $selectedName }}</h3>
@if(!empty($selectedDetails['validated_at']))<p class="muted">Validado por {{ $selectedDetails['validated_name'] ?? ('usuario #'.$selectedDetails['validated_by']) }} · {{ \Carbon\Carbon::parse($selectedDetails['validated_at'])->timezone('America/Santiago')->format('d/m/Y H:i') }}</p>@endif
@if(!empty($selectedDetails['talana_id']))
<p class="doc-field-hint">Archivo de referencia Talana #{{ $selectedDetails['talana_id'] }}. Puede contener datos del trabajador al que pertenece.</p>
<div class="actions"><button type="button" class="cta secondary" id="expand-preview">Ampliar PDF</button><a class="cta secondary" href="{{ route('rrhh-documentos.battery-pdf',[$record->id,$selectedPosition]) }}" target="_blank" rel="noopener">Abrir en otra pestaña</a></div>
<div class="doc-review-status" id="pdf-status" role="status" aria-live="polite">Cargando PDF…</div>
<button type="button" class="cta secondary" id="retry-preview" hidden>Reintentar carga</button>
<div id="preview-home"><div id="preview-frame"><iframe class="pdf-frame" id="document-preview" title="Vista previa de {{ $selectedName }}" data-url="{{ route('rrhh-documentos.battery-pdf',[$record->id,$selectedPosition]) }}"></iframe></div></div>
@if($editable && $canEdit && empty($selectedDetails['validated_at']))
<form class="doc-confirm" method="post" action="{{ route('rrhh-documentos.battery-validate',[$record->id,$selectedPosition]) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}">
<label class="check"><input type="checkbox" name="confirmed" value="1" id="confirm-document" required disabled> Revisé el archivo y confirmo que el contenido y la versión corresponden a esta batería.</label><button class="cta" id="validate-document" disabled>Validar documento</button></form>
@endif
<noscript><p><a href="{{ route('rrhh-documentos.battery-preview',[$record->id,$selectedPosition]) }}">Abrir la vista de revisión del documento</a></p></noscript>
@else<div class="doc-empty"><h3>Archivo pendiente de vincular</h3><p class="muted">Busca el título en Talana y agrégalo a esta batería para abrir su PDF.</p>@if($editable && $canEdit)<button type="button" class="cta" data-open-add>Buscar y vincular documento</button>@endif</div>@endif
@if($editable && $canEdit)<form class="doc-confirm" method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="remove"><input type="hidden" name="position" value="{{ $selectedPosition }}"><button class="cta doc-danger" aria-label="Quitar {{ $selectedName }} de esta batería">Quitar de esta batería</button></form>@endif
</section></div>
@else<section class="doc-empty"><h2>La batería está vacía</h2><p class="muted">Agrega documentos desde Talana para comenzar.</p>@if($editable && $canEdit)<div class="actions"><button type="button" class="cta" data-open-add>Agregar el primer documento</button></div>@endif</section>@endif
@include('rrhh_documentos.battery-settings')
@if($editable && $canEdit)
<dialog id="add-documents" aria-labelledby="add-documents-title"><div class="doc-toolbar"><h2 id="add-documents-title">Agregar documentos a esta batería</h2><button type="button" class="cta secondary" data-close-add>Cerrar</button></div><form method="post" action="{{ route('rrhh-documentos.battery-change',$record->id) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><input type="hidden" name="action" value="add">@include('rrhh_documentos.document-picker')<button class="cta">Guardar documentos y continuar revisión</button></form></dialog>
@endif
<dialog id="full-preview" aria-labelledby="full-preview-title"><div class="doc-toolbar"><h2 id="full-preview-title">Vista ampliada del documento</h2><button type="button" class="cta secondary" id="close-preview">Cerrar vista ampliada</button></div><div id="full-preview-content"></div></dialog>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const add=document.getElementById('add-documents');
    document.querySelectorAll('[data-open-add]').forEach(button=>button.addEventListener('click',()=>add.showModal()));
    document.querySelector('[data-close-add]')?.addEventListener('click',()=>add.close());
    @if($errors->any() && old('action')==='add')
    add?.showModal();
    @endif
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
