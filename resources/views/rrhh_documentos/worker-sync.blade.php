<section class="panel" id="worker-sync" data-status-url="{{ route('rrhh-documentos.workers-sync-status') }}">
<h3>Actualizar trabajadores desde Talana</h3>
<p class="muted">Trae contratos y datos de trabajadores a SAEP. No modifica Talana ni envía documentos. Recargar esta página no inicia una sincronización.</p>
<p id="worker-sync-result" role="status" aria-live="polite">{{ $workerSync['message'] }}</p>
<p class="muted">Última actualización completa confirmada: <strong id="worker-sync-last">{{ $workerSync['last_success'] ?? 'Sin confirmación registrada' }}</strong> · Hora de Chile.</p>
<p class="muted">Último registro actualizado: {{ $workerSync['latest_record'] ?? 'Sin datos' }}. Este dato por sí solo no confirma que toda la sincronización haya terminado.</p>
<div class="actions">
@if(auth()->user()->tieneAcceso('gestion_documental','puede_editar'))
<form method="POST" action="{{ route('rrhh-documentos.workers-sync') }}" id="worker-sync-form">@csrf<button type="submit" class="cta" id="worker-sync-button" @disabled($workerSync['busy'])>{{ $workerSync['busy'] ? 'Actualización pendiente o en curso…' : 'Actualizar trabajadores desde Talana' }}</button></form>
@endif
<a class="cta secondary" href="{{ request()->fullUrl() }}">Actualizar listado</a>
</div>
<p class="muted">Al terminar, pulsa Actualizar listado para aplicar los datos nuevos. Conserva los filtros, pero reinicia la selección de trabajadores que aún no hayas guardado.</p>
</section>
@push('scripts')
<script>
(()=>{
 const panel=document.getElementById('worker-sync'),form=document.getElementById('worker-sync-form'),button=document.getElementById('worker-sync-button'),status=document.getElementById('worker-sync-result'),last=document.getElementById('worker-sync-last');let timer=null;
 function paint(data){status.textContent=data.message;last.textContent=data.last_success||'Sin confirmación registrada';if(button){button.disabled=!!data.busy;button.textContent=data.busy?'Actualización pendiente o en curso…':'Actualizar trabajadores desde Talana';}clearTimeout(timer);if(data.busy)timer=setTimeout(poll,8000);}
 async function poll(){try{const response=await fetch(panel.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)throw Error('No se pudo consultar el estado. Actualiza la página o vuelve a iniciar sesión.');paint(await response.json());}catch(e){status.textContent=e.message;}}
 form?.addEventListener('submit',async event=>{event.preventDefault();button.disabled=true;status.textContent='Solicitando actualización…';try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw Error(data.message||'No se pudo solicitar la actualización.');paint(data);}catch(e){status.textContent=e.message;button.disabled=false;}});
 if(@json($workerSync['busy']))timer=setTimeout(poll,8000);window.addEventListener('pagehide',()=>clearTimeout(timer));
})();
</script>
@endpush
