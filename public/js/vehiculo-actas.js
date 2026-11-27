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
    let files = [], urls = [], processingPhotos = false;
    const thumbnails = new WeakMap();
    const render = () => {
        const transfer = new DataTransfer(); files.forEach(file => transfer.items.add(file)); photos.files = transfer.files;
        const count = document.querySelector('[data-photo-count]');
        if (count) count.textContent = files.length ? files.length + ' de 5 fotos seleccionadas' : (photos.dataset.savedCount || 0) + ' de 5 fotos guardadas';
        urls.forEach(URL.revokeObjectURL); urls = []; preview.replaceChildren();
        files.forEach((file, index) => {
            const item = document.createElement('div'); item.className = 'photo-item';
            const img = document.createElement('img'), url = URL.createObjectURL(thumbnails.get(file) || file); urls.push(url);
            img.decoding = 'async'; img.src = url; img.alt = 'Fotografía ' + (index + 1);
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Quitar foto ' + (index + 1);
            remove.addEventListener('click', () => { files.splice(index, 1); render(); pads.get('firma_encargado')?.reset(); });
            item.append(img, remove); preview.append(item);
        });
    };
    const add = async incoming => {
        if (processingPhotos) return;
        if (files.length + incoming.length > 5) { alert('Puedes adjuntar hasta cinco fotos. Quita una antes de agregar otra.'); render(); return; }
        if (incoming.some(f => !['image/jpeg', 'image/png'].includes(f.type) || f.size > 5 * 1024 * 1024)) {
            alert('Usa fotografías JPG o PNG de hasta 5 MB cada una.'); render(); return;
        }
        processingPhotos = true;
        photos.disabled = true; camera.disabled = true;
        preview.querySelectorAll('button').forEach(button => { button.disabled = true; });
        const count = document.querySelector('[data-photo-count]');
        let failed = false;
        try {
            for (let i = 0; i < incoming.length; i++) {
                if (count) count.textContent = `Preparando foto ${i + 1} de ${incoming.length}… Puedes seguir completando los datos.`;
                // Yield between photos so mobile input and paint remain responsive.
                await new Promise(resolve => setTimeout(resolve, 0));
                const file = typeof createImageBitmap === 'function' ? await compactPhoto(incoming[i]) : incoming[i];
                files.push(file);
            }
        } catch (_) {
            failed = true;
        } finally {
            processingPhotos = false; photos.disabled = false; camera.disabled = false;
            render(); pads.get('firma_encargado')?.reset();
            if (failed && count) count.textContent = `${files.length} fotos listas. No se pudo preparar una foto; vuelve a seleccionar las que faltan. Las anteriores se conservaron.`;
        }
    };
    if (photos && typeof DataTransfer !== 'undefined') {
        document.querySelector('.camera-action').hidden = false;
        photos.addEventListener('change', () => add([...photos.files]));
        camera.addEventListener('change', () => { add([...camera.files]); camera.value = ''; });
    }
    form?.addEventListener('submit', event => {
        if (processingPhotos) {
            event.preventDefault();
            document.querySelector('[data-photo-count]')?.scrollIntoView({block:'center'});
            return;
        }
        if (event.submitter?.value !== 'preparar' || !photos) return;
        const count = photos.files.length || Number(photos.dataset.savedCount);
        if (count !== 5) {
            event.preventDefault();
            alert('Adjunta las cinco fotografías antes de continuar a la firma.');
            photos.scrollIntoView({block: 'center'});
        }
    });
    document.querySelector('[data-final-sign]')?.addEventListener('submit', e => {
        const input = document.querySelector('[data-signature-value="firma_conductor"]');
        if (!input.value) { e.preventDefault(); alert('Dibuja tu firma antes de confirmar.'); input.closest('.sig-panel').scrollIntoView({block:'center'}); }
    });

    // Keep the page, photos and signatures intact on validation or network errors.
    const optimized = new WeakMap();
    async function compactPhoto(file) {
        if (optimized.has(file)) return optimized.get(file);
        const bitmap = await createImageBitmap(file);
        try {
            const scale = Math.min(1, 2400 / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .85));
            if (!blob) throw new Error('No se pudo optimizar la fotografía.');
            const result = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {type:'image/jpeg'});
            const thumb = document.createElement('canvas');
            const thumbScale = Math.min(1, 640 / Math.max(canvas.width, canvas.height));
            thumb.width = Math.max(1, Math.round(canvas.width * thumbScale));
            thumb.height = Math.max(1, Math.round(canvas.height * thumbScale));
            thumb.getContext('2d').drawImage(canvas, 0, 0, thumb.width, thumb.height);
            const thumbnail = await new Promise(resolve => thumb.toBlob(resolve, 'image/jpeg', .75));
            if (thumbnail) thumbnails.set(result, thumbnail);
            optimized.set(file, result);
            optimized.set(result, result); // Do not recompress a selected photo at submission.
            return result;
        } finally { bitmap.close(); }
    }
    document.querySelectorAll('[data-acta-form], [data-final-sign]').forEach(target => {
        let busy = false, dirty = false;
        const status = document.createElement('div');
        status.className = 'alert'; status.hidden = true; status.setAttribute('role', 'status');
        target.before(status);
        target.addEventListener('input', () => { dirty = true; });
        target.addEventListener('change', () => { dirty = true; });
        target.addEventListener('pointerdown', e => { if (e.target.matches('[data-signature]')) dirty = true; });
        window.addEventListener('beforeunload', e => {
            if (dirty || busy) { e.preventDefault(); e.returnValue = ''; }
        });
        target.addEventListener('submit', async event => {
            if (event.defaultPrevented) return;
            event.preventDefault();
            if (busy) return;
            status.hidden = false; status.className = 'alert';
            if (!navigator.onLine) {
                status.textContent = 'No hay conexión. Tus datos siguen en esta pantalla. Recupera la conexión y vuelve a guardar; no cierres la página.';
                return;
            }
            busy = true; target.inert = true;
            status.textContent = 'Preparando fotografías… No cierres esta pantalla.';
            try {
                const payload = new FormData(target);
                if (event.submitter?.name) payload.set(event.submitter.name, event.submitter.value);
                if (typeof createImageBitmap === 'function') {
                    for (const name of ['fotos[]', 'foto_dano']) {
                        const selected = payload.getAll(name).filter(file => file instanceof File && file.size);
                        if (!selected.length) continue;
                        payload.delete(name);
                        for (let i = 0; i < selected.length; i++) {
                            status.textContent = `Optimizando fotografía ${i + 1} de ${selected.length}…`;
                            payload.append(name, await compactPhoto(selected[i]));
                        }
                    }
                }
                const response = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open(target.method || 'POST', target.action);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.timeout = 120000;
                    xhr.upload.onprogress = e => {
                        status.textContent = e.lengthComputable && e.loaded < e.total
                            ? `Enviando: ${Math.round(e.loaded * 100 / e.total)}%. No cierres esta pantalla.`
                            : 'Archivos enviados. Confirmando el guardado…';
                    };
                    xhr.onload = () => {
                        let data;
                        try { data = JSON.parse(xhr.responseText); } catch (_) { data = {}; }
                        if (xhr.status >= 200 && xhr.status < 300 && data.redirect) return resolve(data);
                        const messages = Object.values(data.errors || {}).flat().join(' ');
                        reject(new Error(messages || (xhr.status === 419 || xhr.status === 401
                            ? 'La sesión venció. Inicia sesión en otra pestaña y vuelve a intentar aquí.'
                            : 'No se pudo confirmar el guardado. Revisa el estado del acta antes de reintentar.')));
                    };
                    xhr.onerror = xhr.ontimeout = () => reject(new Error('Se interrumpió la conexión o la respuesta tardó demasiado. El servidor podría haber guardado el acta; revisa su estado antes de reintentar.'));
                    xhr.send(payload);
                });
                dirty = false; busy = false;
                status.textContent = 'Guardado confirmado. Abriendo el acta…';
                window.location.assign(response.redirect);
            } catch (error) {
                busy = false; target.inert = false;
                status.className = 'alert error';
                status.textContent = error.message + ' Tus datos, fotos y firmas siguen en esta pantalla mientras no la cierres ni recargues.';
                status.scrollIntoView({block:'center'});
            }
        });
    });
})();
