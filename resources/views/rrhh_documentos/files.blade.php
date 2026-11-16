<section class="panel" id="batch-files">
<h3>Archivos personales por trabajador y contrato</h3>
<p class="muted">Adjunta el PDF definitivo del trabajador indicado. Puedes previsualizarlo antes de guardar; esa vista previa no registra ni envía el archivo.</p>
@forelse($items as $item)
@php($person=json_decode($item->snapshot,true))
<details class="batch-person"><summary>{{ $person['persona_nombre'] }} · contrato #{{ $person['talana_id'] }} @if($item->excluido) · Retirado @endif</summary>
@foreach($battery['documentos'] as $position=>$name)
@php($file=$files->get($item->id)?->firstWhere('position',$position))
<div class="doc-reference-row"><div><strong>{{ $name }}</strong><p class="muted">{{ $file ? (['preparado'=>'PDF guardado en SAEP','cargando'=>'Carga en proceso','cargado'=>'Cargado en Talana','solicitando_firma'=>'Solicitud de firma en proceso','firma_solicitada'=>'Firma solicitada','firmado'=>'Firma confirmada','revisar_carga'=>'Carga por conciliar','revisar_firma'=>'Firma por conciliar'][$file->state] ?? 'Estado por revisar') : 'Sin PDF registrado' }}</p>@if($file)<p class="muted">Categoría: {{ $file->category }}</p>@endif</div>
@if($file)<div class="actions"><button type="button" class="cta secondary" data-batch-preview="{{ route('rrhh-documentos.pdf',[$batch->id,$item->id,$position,'vista'=>1]) }}" data-preview-title="{{ $person['persona_nombre'] }} · {{ $name }}">Ver PDF</button><a href="{{ route('rrhh-documentos.pdf',[$batch->id,$item->id,$position]) }}">Descargar</a></div>@endif</div>
@endforeach
@if($batch->estado==='BORRADOR' && !$item->excluido && !json_decode($item->observaciones,true) && auth()->user()->tieneAcceso('gestion_documental','puede_editar'))
<form class="batch-upload" method="post" enctype="multipart/form-data" action="{{ route('rrhh-documentos.prepare',[$batch->id,$item->id]) }}" data-person="{{ $person['persona_nombre'] }}">@csrf
<div class="fields"><label>Documento de la batería<select name="position">@foreach($battery['documentos'] as $position=>$name)<option value="{{ $position }}">{{ $name }}</option>@endforeach</select></label><label>Categoría en Talana<input name="category" required maxlength="120"></label></div>
<label>PDF definitivo de este trabajador (hasta 10 MB)<input type="file" name="pdf" accept="application/pdf" required></label><p class="muted">Si este documento ya tiene un PDF guardado, se reemplazará al guardar.</p><div class="actions"><button type="button" class="cta secondary" data-local-preview>Previsualizar archivo elegido</button><button class="cta">Guardar PDF de este trabajador</button></div><p class="local-preview-status muted" role="status"></p></form>
@endif
</details>
@empty<p>No hay contratos en este filtro. Cambia la selección para revisar sus archivos.</p>@endforelse
<p class="muted">Esta sección muestra los contratos de la página actual de la tabla de revisión.</p>
</section>
<dialog id="batch-preview-dialog" aria-labelledby="batch-preview-title"><div class="doc-toolbar"><h2 id="batch-preview-title">Vista previa del PDF</h2><button type="button" class="cta secondary" id="close-batch-preview">Cerrar</button></div><p id="batch-preview-status" role="status"></p><iframe id="batch-preview-frame" class="pdf-frame" title="PDF del trabajador" hidden></iframe></dialog>
@push('styles')
<style>.batch-person{padding:16px 0;border-bottom:1px solid var(--border-color,#ddd)}.batch-person summary{overflow-wrap:anywhere}.batch-upload{margin-top:16px}.doc-workspace #batch-preview-title{overflow-wrap:anywhere}.doc-workspace #batch-preview-dialog>.doc-toolbar{position:sticky;top:-18px;background:var(--card-bg,#fff);z-index:2}</style>
@endpush
@push('scripts')
<script>
(()=>{
 const dialog=document.getElementById('batch-preview-dialog'),frame=document.getElementById('batch-preview-frame'),status=document.getElementById('batch-preview-status'),title=document.getElementById('batch-preview-title');let objectUrl=null,requestId=0;
 function clear(){if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;frame.removeAttribute('src');frame.hidden=true;}
 function show(name){clear();title.textContent=name;status.textContent='Cargando PDF…';if(!dialog.open)dialog.showModal();}
 function display(blob,message){objectUrl=URL.createObjectURL(blob);frame.src=objectUrl;frame.hidden=false;status.textContent=message;}
 document.querySelectorAll('[data-batch-preview]').forEach(button=>button.addEventListener('click',async()=>{
  const ticket=++requestId;show(button.dataset.previewTitle);
  try{const response=await fetch(button.dataset.batchPreview,{headers:{Accept:'application/pdf'},credentials:'same-origin'});if(!response.ok||!response.headers.get('Content-Type')?.includes('application/pdf'))throw Error('No se pudo cargar el archivo. Cierra y reintenta, o utiliza Descargar.');const blob=await response.blob();if(ticket!==requestId)return;display(blob,'Archivo guardado en SAEP. Revisa trabajador, contenido y versión.');}catch(error){if(ticket===requestId)status.textContent=error.message;}
 }));
 document.querySelectorAll('[data-local-preview]').forEach(button=>button.addEventListener('click',()=>{
  const form=button.closest('form'),file=form.querySelector('[name=pdf]').files[0],message=form.querySelector('.local-preview-status');
  if(!file){message.textContent='Primero elige un archivo PDF.';return;}if(file.size>10*1024*1024||(!file.name.toLowerCase().endsWith('.pdf'))){message.textContent='Selecciona un PDF de hasta 10 MB.';return;}
  message.textContent='Vista previa local; el archivo aún no está guardado.';++requestId;show(form.dataset.person+' · '+form.querySelector('[name=position]').selectedOptions[0].textContent);display(file,'Archivo seleccionado en tu equipo. Cierra esta vista y usa Guardar PDF para registrarlo.');
 }));
 document.getElementById('close-batch-preview').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{++requestId;clear();});window.addEventListener('pagehide',clear);
})();
</script>
@endpush
