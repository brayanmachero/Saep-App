@php
$selectedDocuments = [];
foreach ((array)old('documentos',[]) as $token) {
    try {
        $entry = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token),true);
        if (($entry['user']??null) === (int)auth()->id()) $selectedDocuments[] = ['name'=>$entry['name'],'value'=>$token];
    } catch (\Throwable $e) {}
}
@endphp
<div class="document-picker" data-url="{{ route('rrhh-documentos.document-options') }}">
<label>Buscar documento en Talana <input type="search" class="document-query" maxlength="255" placeholder="Escribe al menos dos caracteres"></label>
<label>Orden en cada página<select class="document-order"><option value="recent">Más recientes</option><option value="oldest">Más antiguos</option><option value="name">Nombre A–Z</option></select></label>
<button type="button" class="cta document-search">Buscar documentos</button>
<p class="document-status muted" role="status" aria-live="polite"></p>
<div class="document-results"></div>
<button type="button" class="cta secondary document-next" hidden>Más resultados</button>
<h4>Documentos seleccionados</h4>
<ul class="document-selected"></ul>
<p class="muted">Fechas de creación de registros en Talana, hora de Chile. El orden se aplica a cada página. Revisa la versión antes de agregarla. Los PDF se adjuntan al preparar el lote.</p>
<script type="application/json" class="document-initial">@json($selectedDocuments)</script>
</div>
@once
@push('styles')
<style>
.document-picker{margin:16px 0;min-width:0}.document-picker .document-results{max-height:280px;overflow:auto}.document-picker .document-result,.document-picker li{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--border-color,#ddd);padding:10px 0}.document-picker span{min-width:0;overflow-wrap:anywhere;font-size:13px}.document-picker button{flex-shrink:0;min-height:42px}.document-picker ul{padding:0;list-style:none}.document-picker h4{margin:16px 0 8px}.document-picker [hidden]{display:none!important}.document-picker .document-search{margin-top:8px}.document-picker :focus-visible{outline:3px solid #458bef;outline-offset:2px}

.document-picker .document-results:has(.document-result),.document-picker .document-selected:has(li){border:1px solid var(--border-color,#dce3ed);border-radius:10px;margin:10px 0;padding:0 12px}
.document-picker .document-status{margin:10px 0;line-height:1.5;font-size:12px}
.document-picker .document-result:last-child,.document-picker li:last-child{border-bottom:0}
.document-picker .document-results span,.document-picker .document-selected span{line-height:1.5}
.document-picker .muted{margin:10px 0;color:var(--text-muted,#64748b);font-size:12px;line-height:1.5}
.document-picker .document-meta{display:block;font-size:12px;color:var(--text-muted,#64748b);margin-top:4px;line-height:1.6}
</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('.document-picker').forEach(root => {
    const query=root.querySelector('.document-query'), status=root.querySelector('.document-status'), results=root.querySelector('.document-results'), selected=root.querySelector('.document-selected'), next=root.querySelector('.document-next'), search=root.querySelector('.document-search');
    const selections=new Map();
    let page=1, term='', busy=false, order='recent';
    function add(option) {
        if(selections.has(option.name)) { status.textContent='Este documento ya está seleccionado.'; return; }
        if(selections.size>=50) { status.textContent='Puedes seleccionar hasta 50 documentos.'; return; }
        selections.set(option.name,option.value);
        const row=document.createElement('li'), title=document.createElement('span'), input=document.createElement('input'), remove=document.createElement('button');
        title.textContent=option.name;input.type='hidden';input.name='documentos[]';input.value=option.value;
        remove.type='button';remove.className='cta secondary';remove.textContent='Quitar';remove.setAttribute('aria-label','Quitar '+option.name);
        remove.onclick=()=>{selections.delete(option.name);row.remove();status.textContent=selections.size+' documentos seleccionados.';};
        row.append(title,input,remove);selected.append(row);
        status.textContent=selections.size+' documentos seleccionados.';
    }
    JSON.parse(root.querySelector('.document-initial').textContent).forEach(add);
    async function load(append) {
        if(busy)return;
        if(!append){order=root.querySelector('.document-order').value;term=query.value.trim();page=1;if(term.length<2){status.textContent='Escribe al menos dos caracteres.';return;}results.replaceChildren();next.hidden=true;}
        busy=true;search.disabled=true;next.disabled=true;status.textContent='Consultando Talana…';
        try {
            const url=new URL(root.dataset.url,location.origin);url.searchParams.set('q',term);url.searchParams.set('page',page);url.searchParams.set('order',order);
            const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
            if(!response.ok)throw Error(response.status===401?'Tu sesión terminó. Vuelve a ingresar.':'No se pudo consultar Talana. Intenta nuevamente.');
            const data=await response.json();
            for(const option of data.options){
                const row=document.createElement('div'),title=document.createElement('span'),button=document.createElement('button');
                row.className='document-result';title.textContent=option.name;
                const meta=document.createElement('small');meta.className='document-meta';meta.textContent='Último registro observado · '+(option.date || 'Fecha no informada')+' · Versión en el título · '+(option.version || 'No identificada');title.append(meta);button.type='button';button.className='cta secondary';button.textContent='Agregar';button.setAttribute('aria-label','Agregar '+option.name);
                button.onclick=()=>add(option);row.append(title,button);results.append(row);
            }
            next.hidden=!data.next;page++;status.textContent=data.options.length?'Selecciona los documentos que correspondan.':'Sin resultados. Prueba con menos palabras.';
        }catch(error){status.textContent=error.message;}finally{busy=false;search.disabled=false;next.disabled=false;}
    }
    search.onclick=()=>load(false);next.onclick=()=>load(true);
    query.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();load(false);}});
    root.closest('form').addEventListener('submit',event=>{if(!selections.size){event.preventDefault();status.textContent='Selecciona al menos un documento de Talana.';query.focus();}});
});
</script>
@endpush
@endonce
