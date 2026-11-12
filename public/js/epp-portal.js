(() => {
 const $ = id => document.getElementById(id), catalog=window.eppCatalog;
 const norm=v=>String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
 const centers=[...$('centro').options];
 $('buscar-centro').oninput=()=>{const id=$('centro').value; $('centro').replaceChildren(...centers.filter(o=>!o.value||o.value===id||norm(o.text).includes(norm($('buscar-centro').value)))); $('centro').value=id;};
 const label = item => [item.nombre, item.talla?.trim()].filter(Boolean).join(' · ');
 function filter(){const words=norm($('buscar').value).split(/\s+/).filter(Boolean);
 const found=catalog.filter(i=>(!$('categoria').value||$('categoria').value===i.categoria)&&(!$('talla').value||$('talla').value===i.talla)&&words.every(w=>norm([label(i), i.codigo, i.categoria].join(' ')).includes(w)));
 $('variante').replaceChildren(...found.map(i=>new Option(label(i),i.id))); $('agregar').disabled=!found.length;
 if(!found.length)$('variante').add(new Option('Sin coincidencias','')); }
 for(const id of ['buscar','categoria','talla']) $(id).addEventListener('input',filter);
 function add(i,quantity){if(!i)return;const key='q-'+i.id, prev=$(key);
 if(prev){prev.focus();$('aviso').textContent='Este artículo ya está en el pedido. Ajusta su cantidad.';return;}
 const row=document.createElement('div');row.className='line';const title=document.createElement('span');title.textContent=label(i);
 const q=document.createElement('input');Object.assign(q,{type:'number',min:'1',max:'99999',step:'1',required:true,name:'cantidades['+i.id+']',id:key,value:quantity});q.setAttribute('aria-label','Cantidad de '+label(i));
 const remove=document.createElement('button');remove.type='button';remove.className='secondary';remove.textContent='Quitar';remove.onclick=()=>{row.remove();$('vacio').hidden=$('lineas').children.length>0;};
 row.append(title,q,remove);$('lineas').append(row);$('vacio').hidden=true;$('aviso').textContent='';}
 $('agregar').onclick=()=>add(catalog.find(i=>String(i.id)===$('variante').value),1);
 Object.entries(window.eppOld||{}).forEach(([id,q])=>add(catalog.find(i=>String(i.id)===id),q));filter();
})();
