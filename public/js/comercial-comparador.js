(() => {
    const form = document.getElementById('tarifas-comparador');
    if (!form) return;
    const key = 'saep-comercial-comparador-v1';
    const boxes = [...document.querySelectorAll('.comparar-tarifa')];
    const selected = document.getElementById('comparador-seleccion');
    const notice = document.getElementById('comparador-aviso');
    let items = [];
    try { const saved = JSON.parse(sessionStorage.getItem(key) || '[]'); if(Array.isArray(saved)) items = saved.filter(x => /^\d+$/.test(x.id) && typeof x.label === 'string').slice(0,4); } catch (_) {}
    function render() {
        selected.replaceChildren();
        form.querySelectorAll('input[name="ids[]"]').forEach(x=>x.remove());
        items.forEach(item => {
            const input = document.createElement('input'); input.type='hidden';input.name='ids[]';input.value=item.id;form.append(input);
            const button=document.createElement('button');button.type='button';button.className='btn';button.textContent=item.label+' ×';button.setAttribute('aria-label','Quitar '+item.label);button.onclick=()=>{items=items.filter(x=>x.id!==item.id);render();};selected.append(button);
        });
        boxes.forEach(box=>{box.checked=items.some(x=>x.id===box.value);box.disabled=items.length===4&&!box.checked;});
        document.getElementById('comparador-abrir').disabled=items.length<2;
        notice.textContent=items.length+' de 4 seleccionadas'+(items.length===4 ? '. Quita una para elegir otra.' : '.');
        try {sessionStorage.setItem(key,JSON.stringify(items));} catch (_) {notice.textContent+=' La selección no se conservará al cambiar de página.';}
    }
    boxes.forEach(box=>box.addEventListener('change',()=>{items=items.filter(x=>x.id!==box.value);if(box.checked && items.length<4) items.push({id:box.value,label:box.dataset.label});render();}));
    document.getElementById('comparador-limpiar').onclick=()=>{items=[];render();};
    form.addEventListener('submit',event=>{if(items.length<2||items.length>4) event.preventDefault();});
    render();
})();
