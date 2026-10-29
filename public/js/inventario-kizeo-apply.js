document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-kizeo-async-apply]').forEach(function (form) {
        var busy = false, done = false;
        var result = form.querySelector('[data-kizeo-apply-result]');
        var submit = form.querySelector('[type="submit"]');
        submit.parentElement.before(result);
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (busy || done) return;
            busy = true;
            var body = new FormData(form);
            var originalLabel = submit.innerHTML;
            submit.disabled = true;
            submit.textContent = 'Aplicando…';
            form.setAttribute('aria-busy', 'true');
            result.hidden = false;
            result.textContent = 'Validando y aplicando todas las líneas de esta entrega…';
            try {
                var response = await fetch(form.action, {
                    method: 'POST', body: body, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Respuesta no confirmada');
                var payload = await response.json();
                if (payload.stocks) document.dispatchEvent(new CustomEvent('inventory:kizeo-stocks', { detail: payload.stocks }));
                if (!response.ok) {
                    var errors = Object.values(payload.errors || {}).flat();
                    result.textContent = response.status === 422
                        ? (errors.join(' ') || payload.message || 'Revisa los datos de la entrega.')
                        : (response.status === 419 ? 'Tu sesión venció. Vuelve a iniciar sesión antes de aplicar.' : 'No se pudo confirmar la operación. Verifica el estado antes de reintentar.');
                    return;
                }
                done = true;
                var card = form.closest('.inventory-delivery-card');
                var status = card.querySelector('.inventory-delivery-status .inventory-status');
                status.textContent = 'Salida descontada';
                status.className = 'inventory-status is-ok';
                status.title = 'Descuento confirmado por el servidor.';
                var checkbox = card.querySelector('[data-kizeo-batch-checkbox]');
                if (checkbox) {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                    checkbox.closest('label').hidden = true;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
                form.querySelectorAll('select,input,button').forEach(function (control) { control.disabled = true; });
                card.querySelectorAll('.inventory-delivery-body > .inventory-notice').forEach(function (notice) { notice.hidden = true; });
                result.textContent = payload.message + ' Se conserva esta tarjeta para que no pierdas tu posición. Los totales del período se actualizarán cuando vuelvas a consultar la vista.';
            } catch (error) {
                result.textContent = 'No se pudo confirmar el resultado. Puede haberse completado en el servidor. Verifica el estado antes de volver a aplicar; no se reintentó automáticamente.';
            } finally {
                busy = false;
                form.removeAttribute('aria-busy');
                submit.disabled = done;
                if (done) submit.textContent = 'Descuento aplicado';
                else submit.innerHTML = originalLabel;
            }
        });
    });
});
