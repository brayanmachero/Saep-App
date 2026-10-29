(() => {
    'use strict';
    const pads = new Map();
    document.querySelectorAll('[data-signature]').forEach(canvas => {
        const key = canvas.dataset.signature, ctx = canvas.getContext('2d');
        const input = document.querySelector(`[data-signature-value="${key}"]`);
        const status = document.querySelector(`[data-signature-status="${key}"]`);
        let drawing = false, touched = false;
        const reset = () => { ctx.clearRect(0, 0, canvas.width, canvas.height); input.value = ''; touched = false; if (key === 'croquis') pads.get('firma_encargado')?.reset(); status.textContent = key === 'croquis' ? 'Sin dibujo nuevo' : 'Sin firma'; };
        const point = e => { const r = canvas.getBoundingClientRect(); return [(e.clientX - r.left) * canvas.width / r.width, (e.clientY - r.top) * canvas.height / r.height]; };
        ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#17243b';
        canvas.addEventListener('pointerdown', e => { e.preventDefault(); drawing = true; canvas.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...point(e)); });
        canvas.addEventListener('pointermove', e => { if (!drawing) return; e.preventDefault(); ctx.lineTo(...point(e)); ctx.stroke(); touched = true; });
        const finish = () => { drawing = false; if (touched) { input.value = canvas.toDataURL('image/png'); status.textContent = key === 'croquis' ? 'Dibujo capturado' : 'Firma capturada'; if (key === 'croquis') pads.get('firma_encargado')?.reset(); } };
        canvas.addEventListener('pointerup', finish); canvas.addEventListener('pointercancel', finish);
        document.querySelector(`[data-signature-clear="${key}"]`).addEventListener('click', reset);
        pads.set(key, {reset});
    });
    const form = document.querySelector('[data-acta-form]');
    if (form) form.addEventListener('change', e => { if (e.target.name !== 'firma_encargado') pads.get('firma_encargado')?.reset(); });
    document.querySelectorAll('[data-signature-expand]').forEach(button => {
        button.addEventListener('click', () => {
            const expanded = button.closest('.sig-panel').classList.toggle('expanded');
            button.setAttribute('aria-expanded', String(expanded));
            button.textContent = expanded ? 'Volver al acta' : (button.closest('.sig-panel').querySelector('[data-signature]').dataset.signature === 'croquis' ? 'Ampliar dibujo' : 'Ampliar espacio de firma');
            document.body.style.overflow = expanded ? 'hidden' : '';
        });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelector('.sig-panel.expanded [data-signature-expand]')?.click();
    });
    const photos = document.querySelector('[data-photo-input]'), preview = document.querySelector('[data-photo-preview]');
    const camera = document.querySelector('[data-camera]');
    let files = [], urls = [];
    const render = () => {
        const transfer = new DataTransfer(); files.forEach(file => transfer.items.add(file)); photos.files = transfer.files;
        urls.forEach(URL.revokeObjectURL); urls = []; preview.replaceChildren();
        files.forEach((file, index) => {
            const item = document.createElement('div'); item.className = 'photo-item';
            const img = document.createElement('img'), url = URL.createObjectURL(file); urls.push(url);
            img.src = url; img.alt = 'Fotografía ' + (index + 1);
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Quitar foto ' + (index + 1);
            remove.addEventListener('click', () => { files.splice(index, 1); render(); pads.get('firma_encargado')?.reset(); });
            item.append(img, remove); preview.append(item);
        });
    };
    const add = incoming => {
        if (files.length + incoming.length > 4) { alert('Puedes adjuntar hasta cuatro fotos. Quita una antes de agregar otra.'); render(); return; }
        if (incoming.some(f => !['image/jpeg', 'image/png'].includes(f.type) || f.size > 5 * 1024 * 1024)) {
            alert('Usa fotografías JPG o PNG de hasta 5 MB cada una.'); render(); return;
        }
        files.push(...incoming); render(); pads.get('firma_encargado')?.reset();
    };
    if (photos && typeof DataTransfer !== 'undefined') {
        document.querySelector('.camera-action').hidden = false;
        photos.addEventListener('change', () => add([...photos.files]));
        camera.addEventListener('change', () => { add([...camera.files]); camera.value = ''; });
    }
    document.querySelector('[data-location-clear]')?.addEventListener('click', () => { document.querySelectorAll('[data-location-value]').forEach(input=>{input.value='';input.disabled=true;}); document.querySelector('[data-location-status]').textContent='Sin ubicación. Indica el motivo para continuar.'; pads.get('firma_encargado')?.reset(); });
    const locationButton = document.querySelector('[data-location]');
    locationButton?.addEventListener('click', () => {
        const status = document.querySelector('[data-location-status]');
        if (!navigator.geolocation || !window.isSecureContext) { status.textContent = 'Ubicación no disponible. Indica el motivo para continuar.'; return; }
        locationButton.disabled = true; status.textContent = 'Obteniendo ubicación…';
        navigator.geolocation.getCurrentPosition(position => {
            const values = {latitud:position.coords.latitude, longitud:position.coords.longitude, precision:position.coords.accuracy, capturada_at:new Date(position.timestamp).toISOString()};
            Object.entries(values).forEach(([key,value]) => { const input=document.querySelector(`[data-location-value="${key}"]`); input.disabled=false; input.value=value; });
            status.textContent = `Ubicación: ${values.latitud.toFixed(6)}, ${values.longitud.toFixed(6)} · precisión ${Math.round(values.precision)} m`;
            document.querySelector('#ubicacion_motivo').value=''; locationButton.disabled=false; pads.get('firma_encargado')?.reset();
        }, () => { status.textContent = 'No se pudo obtener una ubicación nueva. Si existe una anterior se conserva; revisa el dato o indica el motivo.'; locationButton.disabled=false; }, {enableHighAccuracy:true,timeout:15000,maximumAge:0});
    });
    document.querySelector('[data-final-sign]')?.addEventListener('submit', e => {
        const input = document.querySelector('[data-signature-value="firma_conductor"]');
        if (!input.value) { e.preventDefault(); alert('Dibuja tu firma antes de confirmar.'); input.closest('.sig-panel').scrollIntoView({block:'center'}); }
    });
})();
