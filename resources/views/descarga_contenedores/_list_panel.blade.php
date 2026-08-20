@php
    $puedeGestionarCostos = auth()->user()->puedeGestionarCostosDescargaContenedores();
    $tarifas = $tarifas ?? collect();
    $trabajadores = $trabajadores ?? collect();
    $tarifasPickerData = $tarifas->map(function ($t) use ($puedeGestionarCostos) {
        $data = [
            'id' => $t->id,
            'cliente' => $t->cliente,
            'centro_costo_id' => $t->centro_costo_id,
            'centro' => $t->centroCosto?->nombre,
            'codigo' => $t->codigo,
            'proceso' => $t->proceso,
            'requiere_revision' => $t->requiere_revision,
        ];
        if ($puedeGestionarCostos) {
            $data['costo_unitario'] = $t->costo_unitario;
            $data['pago_colaborador'] = $t->pago_colaborador;
        }
        return $data;
    })->values();
@endphp

<div class="contenedores-bulk-bar" data-bulk-bar hidden>
    <strong data-bulk-count>0 seleccionados</strong>
    <span>Los mismos trabajadores se asignan a todos los contenedores marcados.</span>
    <button type="button" class="btn-premium" data-bulk-open>
        <i class="bi bi-people-fill"></i> Asignar trabajadores
    </button>
    <button type="button" class="btn-secondary" data-bulk-clear>Limpiar selección</button>
</div>

<div class="contenedores-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="contenedores-drawer" data-contenedores-drawer aria-hidden="true">
    <header class="contenedores-drawer-bar">
        <div>
            <h3 data-drawer-title>Editar contenedor</h3>
            <p data-drawer-meta></p>
        </div>
        <button type="button" class="icon-btn" data-drawer-close title="Cerrar"><i class="bi bi-x-lg"></i></button>
    </header>
    <div class="contenedores-drawer-body">
        <section data-drawer-fact>
            <h4>Tarifa FACT</h4>
            <input type="hidden" data-drawer-tarifa-id>
            <input type="hidden" data-drawer-fact-codigo>
            <div class="tarifa-picker">
                <div class="tarifa-search-wrap">
                    <input type="text" class="form-control tarifa-search" autocomplete="off" placeholder="Escribe el código FACT, cliente o proceso...">
                    <div class="tarifa-dropdown"></div>
                </div>
                <div class="tarifa-selected is-empty" data-tarifa-selected>
                    <span data-tarifa-selected-text>Sin tarifa asociada</span>
                    <button type="button" class="icon-btn" data-clear-tarifa title="Limpiar tarifa"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <small class="muted-hint">Al escribir un código único del cliente, se asocia solo. No hace falta volver a ingresarlo.</small>
        </section>
        <section data-drawer-workers>
            <h4>Trabajadores</h4>
            <div class="worker-picker compact" data-drawer-crew></div>
        </section>
    </div>
    <footer class="contenedores-drawer-footer">
        <a href="#" class="btn-secondary" data-drawer-full-edit>Edición completa</a>
        <button type="button" class="btn-premium" data-drawer-save>Guardar</button>
    </footer>
</aside>

<script type="application/json" id="contenedores_trabajadores_data">@json($trabajadores)</script>
<script type="application/json" id="contenedores_tarifas_data">@json($tarifasPickerData)</script>
<script>
(function () {
    const canViewCosts = @json($puedeGestionarCostos);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const workers = JSON.parse(document.getElementById('contenedores_trabajadores_data')?.textContent || '[]');
    const tarifas = JSON.parse(document.getElementById('contenedores_tarifas_data')?.textContent || '[]');
    const byWorkerId = new Map(workers.map(w => [String(w.id), w]));
    const byTarifaId = new Map(tarifas.map(t => [String(t.id), t]));

    const drawer = document.querySelector('[data-contenedores-drawer]');
    const backdrop = document.querySelector('[data-drawer-backdrop]');
    const bulkBar = document.querySelector('[data-bulk-bar]');
    if (!drawer) return;

    const titleEl = drawer.querySelector('[data-drawer-title]');
    const metaEl = drawer.querySelector('[data-drawer-meta]');
    const factSection = drawer.querySelector('[data-drawer-fact]');
    const crewBox = drawer.querySelector('[data-drawer-crew]');
    const tarifaIdInput = drawer.querySelector('[data-drawer-tarifa-id]');
    const factInput = drawer.querySelector('[data-drawer-fact-codigo]');
    const search = drawer.querySelector('.tarifa-search');
    const dropdown = drawer.querySelector('.tarifa-dropdown');
    const selectedBox = drawer.querySelector('[data-tarifa-selected]');
    const selectedText = drawer.querySelector('[data-tarifa-selected-text]');
    const saveBtn = drawer.querySelector('[data-drawer-save]');
    const fullEdit = drawer.querySelector('[data-drawer-full-edit]');

    let mode = 'single';
    let currentId = null;
    let currentCenterId = '';
    let currentOperacion = '';
    let selectedIds = new Set();

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function capitalize(value) {
        const text = String(value || '');
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
    }

    function formatCurrency(value) {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '—';
        return '$' + Math.round(Number(value)).toLocaleString('es-CL');
    }

    function toast(message, type) {
        if (typeof showToast === 'function') {
            showToast(message, type || 'success');
            return;
        }
        window.alert(message);
    }

    function clienteFromOperacion(operacion) {
        const text = normalizeText(operacion);
        if (!text) return '';
        if (text.includes('smu') || text.includes('unimarc')) return 'SMU';
        if (text.includes('walmart') || text === 'wm' || text.startsWith('wm ')) return 'WM';
        return '';
    }

    function uniqueTarifaByCode(code, centerId = '', cliente = '') {
        const clean = String(code || '').trim().toUpperCase();
        if (!clean) return null;
        let matches = tarifas.filter(t => {
            if (String(t.codigo || '').trim().toUpperCase() !== clean) return false;
            return !centerId || !t.centro_costo_id || String(t.centro_costo_id) === String(centerId);
        });
        if (cliente) {
            const byClient = matches.filter(t => String(t.cliente || '').toUpperCase() === cliente);
            if (byClient.length) matches = byClient;
        }
        const scoped = centerId
            ? matches.filter(t => String(t.centro_costo_id || '') === String(centerId))
            : [];
        if (scoped.length === 1) return scoped[0];
        const general = matches.filter(t => !t.centro_costo_id);
        if (general.length === 1) return general[0];
        return matches.length === 1 ? matches[0] : null;
    }

    function tarifaLabel(tarifa) {
        return `${tarifa.codigo || ''} · ${tarifa.cliente || ''} · ${tarifa.centro || 'General'} · ${tarifa.proceso || ''}`;
    }

    function setSelectedTarifa(tarifa, manualCode = '') {
        tarifaIdInput.value = tarifa ? String(tarifa.id) : '';
        factInput.value = tarifa ? tarifa.codigo : String(manualCode || '').trim().toUpperCase();
        selectedBox.classList.toggle('is-empty', !tarifa && !manualCode);
        if (tarifa) {
            selectedText.innerHTML = `<strong>${escapeHtml(tarifa.codigo)}</strong> · ${escapeHtml(tarifa.cliente)} · ${escapeHtml(tarifa.centro || 'General')} · ${escapeHtml(tarifa.proceso)}`;
            if (document.activeElement !== search) search.value = tarifaLabel(tarifa);
        } else {
            selectedText.textContent = manualCode ? `Código manual: ${manualCode}` : 'Sin tarifa asociada';
        }
        crewBox.dispatchEvent(new Event('tarifa-change'));
    }

    function resolveTypedCode(raw) {
        const auto = uniqueTarifaByCode(raw, currentCenterId, clienteFromOperacion(currentOperacion));
        if (auto) {
            setSelectedTarifa(auto);
            return auto;
        }
        setSelectedTarifa(null, String(raw || '').trim().toUpperCase());
        return null;
    }

    function renderTarifas(query = '') {
        const q = query.trim().toLowerCase();
        const matches = tarifas.filter(t => {
            const centerOk = !currentCenterId || !t.centro_costo_id || String(t.centro_costo_id) === String(currentCenterId);
            const searchable = [t.codigo, t.cliente, t.centro, t.proceso].join(' ').toLowerCase();
            return centerOk && (!q || searchable.includes(q));
        }).slice(0, 80);

        dropdown.innerHTML = matches.length
            ? matches.map(t => `
                <div class="tarifa-option" data-id="${escapeHtml(t.id)}" role="button" tabindex="0">
                    <strong>${escapeHtml(t.codigo)}</strong>
                    <span>${escapeHtml(t.cliente)} · ${escapeHtml(t.centro || 'General')} · ${escapeHtml(t.proceso)}</span>
                </div>
            `).join('')
            : '<div class="tarifa-empty">Sin resultados. Puedes dejar el código escrito para revisión.</div>';
        dropdown.style.display = 'block';
        dropdown.querySelectorAll('.tarifa-option').forEach(option => {
            option.addEventListener('mousedown', event => {
                event.preventDefault();
                const tarifa = byTarifaId.get(String(option.dataset.id));
                if (!tarifa) return;
                setSelectedTarifa(tarifa);
                search.value = tarifaLabel(tarifa);
                dropdown.style.display = 'none';
            });
        });
    }

    search?.addEventListener('focus', () => renderTarifas(search.value));
    search?.addEventListener('input', () => {
        resolveTypedCode(search.value);
        renderTarifas(search.value);
    });
    search?.addEventListener('blur', () => {
        const auto = resolveTypedCode(search.value);
        if (auto) search.value = tarifaLabel(auto);
        setTimeout(() => dropdown.style.display = 'none', 150);
    });
    drawer.querySelector('[data-clear-tarifa]')?.addEventListener('click', () => {
        search.value = '';
        setSelectedTarifa(null, '');
        dropdown.style.display = 'none';
    });

    function initCrewPicker(initial = []) {
        const selected = new Map();
        crewBox._selected = selected;
        crewBox.innerHTML = `
            <div class="worker-tags"></div>
            <div class="distribution-summary">
                <span>Total: <strong data-total>0%</strong></span>
                <span class="distribution-status" data-total-hint>Debe sumar 100% para validar.</span>
                ${canViewCosts ? '<span>Pago estimado: <strong data-pago>$0</strong></span>' : ''}
                <button type="button" class="btn-secondary btn-mini" data-equalize>Repartir igual</button>
            </div>
            <div class="worker-search-wrap">
                <input type="text" class="form-control worker-search" autocomplete="off" placeholder="Buscar trabajador por nombre, RUT, cargo o centro...">
                <div class="worker-dropdown"></div>
            </div>
        `;
        const tags = crewBox.querySelector('.worker-tags');
        const input = crewBox.querySelector('.worker-search');
        const list = crewBox.querySelector('.worker-dropdown');
        const totalEl = crewBox.querySelector('[data-total]');
        const hintEl = crewBox.querySelector('[data-total-hint]');
        const pagoEl = crewBox.querySelector('[data-pago]');

        function currentTarifa() {
            return byTarifaId.get(String(tarifaIdInput.value || '')) || null;
        }

        function equalize() {
            const ids = [...selected.keys()];
            const count = ids.length;
            if (!count) return;
            const base = Math.round((100 / count) * 100) / 100;
            let assigned = 0;
            ids.forEach((id, index) => {
                const item = selected.get(id);
                const value = index === count - 1 ? Math.round((100 - assigned) * 100) / 100 : base;
                item.porcentaje = value;
                assigned += value;
            });
        }

        function render() {
            tags.innerHTML = [...selected.values()].map(({ worker, porcentaje }) => `
                <div class="worker-tag ${canViewCosts ? '' : 'no-costs'}">
                    <div class="worker-main">
                        <strong>${escapeHtml(worker.label)}</strong>
                        <small>${escapeHtml([worker.rut, worker.cargo, worker.centro].filter(Boolean).join(' · '))}</small>
                    </div>
                    <label class="percent-control">
                        <input type="number" min="0" max="100" step="0.01" value="${porcentaje ?? ''}" data-id="${escapeHtml(worker.id)}">
                        <span>%</span>
                    </label>
                    <button type="button" data-remove="${escapeHtml(worker.id)}" title="Quitar">&times;</button>
                </div>
            `).join('') || '<div class="review-empty">Sin trabajadores asignados.</div>';

            tags.querySelectorAll('input[data-id]').forEach(field => {
                field.addEventListener('input', () => {
                    const item = selected.get(String(field.dataset.id));
                    if (item) item.porcentaje = field.value === '' ? null : parseFloat(field.value);
                    updateTotals();
                });
            });
            tags.querySelectorAll('[data-remove]').forEach(btn => {
                btn.addEventListener('click', () => {
                    selected.delete(String(btn.dataset.remove));
                    render();
                });
            });
            updateTotals();
        }

        function updateTotals() {
            const total = [...selected.values()].reduce((sum, item) => sum + (Number(item.porcentaje) || 0), 0);
            totalEl.textContent = `${Math.round(total * 100) / 100}%`;
            const ok = Math.abs(total - 100) <= 0.01 && selected.size > 0;
            hintEl.textContent = selected.size === 0
                ? 'Agrega trabajadores para completar el equipo.'
                : (ok ? 'Distribución lista para validar.' : 'Debe sumar 100% para validar.');
            hintEl.classList.toggle('is-ok', ok);
            if (pagoEl) {
                const tarifa = currentTarifa();
                const pago = tarifa && tarifa.pago_colaborador != null ? Number(tarifa.pago_colaborador) : null;
                pagoEl.textContent = pago == null ? '$0' : formatCurrency(pago);
            }
        }

        function addWorker(worker) {
            if (selected.has(String(worker.id))) return;
            selected.set(String(worker.id), { worker, porcentaje: null });
            equalize();
            render();
        }

        input.addEventListener('focus', () => renderOptions(input.value));
        input.addEventListener('input', () => renderOptions(input.value));
        input.addEventListener('blur', () => setTimeout(() => list.style.display = 'none', 150));
        crewBox.querySelector('[data-equalize]').addEventListener('click', () => {
            equalize();
            render();
        });
        crewBox.addEventListener('tarifa-change', updateTotals);

        function renderOptions(query) {
            const q = normalizeText(query);
            const matches = workers.filter(w => {
                if (selected.has(String(w.id))) return false;
                if (!q) return true;
                return normalizeText([w.label, w.rut, w.cargo, w.centro].join(' ')).includes(q);
            }).slice(0, 40);
            list.innerHTML = matches.length
                ? matches.map(w => `
                    <div class="worker-option" data-id="${escapeHtml(w.id)}" role="button" tabindex="0">
                        <strong>${escapeHtml(w.label)}</strong>
                        <span>${escapeHtml([w.rut, w.cargo, w.centro].filter(Boolean).join(' · '))}</span>
                    </div>
                `).join('')
                : '<div class="tarifa-empty">Sin resultados.</div>';
            list.style.display = 'block';
            list.querySelectorAll('.worker-option').forEach(option => {
                option.addEventListener('mousedown', event => {
                    event.preventDefault();
                    const worker = byWorkerId.get(String(option.dataset.id));
                    if (!worker) return;
                    addWorker(worker);
                    input.value = '';
                    list.style.display = 'none';
                });
            });
        }

        initial.forEach(item => {
            const id = typeof item === 'object' ? item.id : item;
            const worker = byWorkerId.get(String(id));
            if (!worker) return;
            selected.set(String(id), {
                worker,
                porcentaje: typeof item === 'object' && item.porcentaje != null ? parseFloat(item.porcentaje) : null,
            });
        });
        if ([...selected.values()].every(item => item.porcentaje === null || Number.isNaN(item.porcentaje))) {
            equalize();
        }
        render();
    }

    function crewPayload() {
        return [...(crewBox._selected || new Map()).entries()].map(([id, item]) => ({
            id: Number(id),
            porcentaje: item.porcentaje,
        }));
    }

    function pendingHtml(row) {
        if (row.estado !== 'borrador') return '<span class="badge success">OK</span>';
        if (!row.blockers.length) return '<span class="badge success">Listo</span>';
        const n = row.blockers.length;
        const details = row.blockers.map(b => `<span class="badge warning">${escapeHtml(capitalize(b))}</span>`).join('');
        return `<button type="button" class="pending-compact" data-pending-toggle aria-expanded="false"><span class="badge warning">${n} pendiente${n === 1 ? '' : 's'}</span></button><div class="pending-details" hidden>${details}</div>`;
    }

    function applyRow(row) {
        const tr = document.querySelector(`tr[data-descarga-id="${row.id}"]`);
        if (!tr) return;
        const factCell = tr.querySelector('[data-cell="fact"]');
        if (factCell) {
            let html = `<code>${escapeHtml(row.fact_codigo || '—')}</code>`;
            if (canViewCosts && (row.tarifa_cliente || row.tarifa_proceso)) {
                html += `<div style="font-size:.72rem;color:var(--text-muted)">${escapeHtml([row.tarifa_cliente, row.tarifa_proceso].filter(Boolean).join(' · '))}</div>`;
            }
            factCell.innerHTML = html;
        }
        const pagoCell = tr.querySelector('[data-cell="pago"]');
        if (pagoCell) {
            if (row.requiere_revision_tarifa) pagoCell.innerHTML = '<span class="badge warning">Revisar</span>';
            else if (row.pago !== null && row.pago !== undefined) pagoCell.textContent = formatCurrency(row.pago);
            else pagoCell.textContent = '—';
        }
        const trabCell = tr.querySelector('[data-cell="trab"]');
        if (trabCell) {
            trabCell.innerHTML = row.participantes_count === 0
                ? '<span class="badge warning">Sin equipo</span>'
                : String(row.participantes_count);
        }
        const pendingCell = tr.querySelector('[data-cell="pendientes"]');
        if (pendingCell) pendingCell.innerHTML = pendingHtml(row);

        const validateSlot = tr.querySelector('[data-validate-slot]');
        if (validateSlot && row.estado === 'borrador') {
            if (row.can_validate) {
                const action = validateSlot.dataset.action || validateSlot.getAttribute('data-action');
                validateSlot.innerHTML = `
                    <form method="POST" action="${escapeHtml(action)}" style="display:inline" onsubmit="return confirm('¿Validar este registro de contenedor?')">
                        <input type="hidden" name="_token" value="${escapeHtml(csrf)}">
                        <input type="hidden" name="_method" value="PATCH">
                        <button class="icon-btn validation-ready" title="Validar: bloquea el borrador como registro revisado"><i class="bi bi-check2-circle"></i></button>
                    </form>
                `;
            } else {
                validateSlot.innerHTML = `<button class="icon-btn validation-disabled" title="No se puede validar. Pendiente: ${escapeHtml((row.blockers || []).join(', '))}" disabled><i class="bi bi-check2-circle"></i></button>`;
            }
        }
    }

    async function api(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            ...options,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'No se pudo guardar.');
        }
        return data;
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.hidden = true;
        mode = 'single';
        currentId = null;
    }

    function openDrawerShell(title, meta) {
        titleEl.textContent = title;
        metaEl.textContent = meta || '';
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        backdrop.hidden = false;
    }

    async function openSingle(id, focus) {
        mode = 'single';
        currentId = id;
        factSection.hidden = false;
        fullEdit.hidden = false;
        saveBtn.textContent = 'Guardar';
        const data = await api(`{{ url('descarga-contenedores') }}/${id}/panel`);
        if (!data.can_edit) {
            toast('Este registro no se puede editar desde el listado.', 'warning');
            return;
        }
        const d = data.descarga;
        currentCenterId = d.centro_costo_id || '';
        currentOperacion = d.operacion || '';
        openDrawerShell(d.contenedor || 'Sin contenedor', [d.fecha, d.bodega, d.operacion].filter(Boolean).join(' · '));
        fullEdit.href = `{{ url('descarga-contenedores') }}/${id}/edit`;
        const tarifa = byTarifaId.get(String(d.tarifa_id || '')) || uniqueTarifaByCode(d.fact_codigo, currentCenterId, clienteFromOperacion(currentOperacion));
        search.value = tarifa ? tarifaLabel(tarifa) : (d.fact_codigo || '');
        setSelectedTarifa(tarifa || null, d.fact_codigo || '');
        initCrewPicker(d.participantes || []);
        if (focus === 'workers') {
            drawer.querySelector('[data-drawer-workers]')?.scrollIntoView({ block: 'start' });
        }
    }

    function openBulk() {
        const ids = [...selectedIds];
        if (!ids.length) {
            toast('Selecciona al menos un contenedor.', 'warning');
            return;
        }
        mode = 'bulk';
        currentId = null;
        factSection.hidden = true;
        fullEdit.hidden = true;
        saveBtn.textContent = 'Asignar a seleccionados';
        openDrawerShell('Asignación masiva', `${ids.length} contenedor(es) seleccionados`);
        initCrewPicker([]);
    }

    async function saveDrawer() {
        const payload = crewPayload();
        saveBtn.disabled = true;
        try {
            if (mode === 'bulk') {
                const data = await api(@json(route('descarga-contenedores.equipo-masivo')), {
                    method: 'POST',
                    body: JSON.stringify({
                        descargas: [...selectedIds],
                        participantes_json: JSON.stringify(payload),
                    }),
                });
                (data.rows || []).forEach(applyRow);
                toast(data.message || 'Equipo asignado.');
                clearSelection();
                closeDrawer();
                return;
            }

            const data = await api(`{{ url('descarga-contenedores') }}/${currentId}/rapido`, {
                method: 'PATCH',
                body: JSON.stringify({
                    tarifa_id: tarifaIdInput.value || null,
                    fact_codigo: factInput.value || '',
                    participantes_json: JSON.stringify(payload),
                }),
            });
            if (data.row) applyRow(data.row);
            toast(data.message || 'Registro actualizado.');
            closeDrawer();
        } catch (error) {
            toast(error.message || 'No se pudo guardar.', 'error');
        } finally {
            saveBtn.disabled = false;
        }
    }

    function selectedCheckboxes() {
        return [...document.querySelectorAll('.contenedores-select:checked')];
    }

    function syncSelection() {
        selectedIds = new Set(selectedCheckboxes().map(input => Number(input.value)));
        const count = selectedIds.size;
        if (!bulkBar) return;
        bulkBar.hidden = count === 0;
        const label = bulkBar.querySelector('[data-bulk-count]');
        if (label) label.textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
        const master = document.querySelector('[data-select-all]');
        if (master) {
            const boxes = [...document.querySelectorAll('.contenedores-select')];
            master.checked = boxes.length > 0 && boxes.every(box => box.checked);
            master.indeterminate = count > 0 && !master.checked;
        }
    }

    function clearSelection() {
        document.querySelectorAll('.contenedores-select').forEach(box => { box.checked = false; });
        const master = document.querySelector('[data-select-all]');
        if (master) {
            master.checked = false;
            master.indeterminate = false;
        }
        syncSelection();
    }

    document.addEventListener('click', event => {
        const opener = event.target.closest('[data-open-drawer]');
        if (opener) {
            event.preventDefault();
            openSingle(opener.dataset.openDrawer, opener.dataset.focus || '').catch(error => {
                toast(error.message || 'No se pudo abrir el panel.', 'error');
            });
            return;
        }
        const toggle = event.target.closest('[data-pending-toggle]');
        if (toggle) {
            const details = toggle.parentElement?.querySelector('.pending-details');
            if (!details) return;
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            details.hidden = expanded;
        }
    });

    document.addEventListener('change', event => {
        if (event.target.matches('.contenedores-select, [data-select-all]')) {
            if (event.target.matches('[data-select-all]')) {
                document.querySelectorAll('.contenedores-select').forEach(box => {
                    box.checked = event.target.checked;
                });
            }
            syncSelection();
        }
    });

    backdrop.addEventListener('click', closeDrawer);
    drawer.querySelector('[data-drawer-close]').addEventListener('click', closeDrawer);
    saveBtn.addEventListener('click', saveDrawer);
    document.querySelector('[data-bulk-open]')?.addEventListener('click', openBulk);
    document.querySelector('[data-bulk-clear]')?.addEventListener('click', clearSelection);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
    });
})();
</script>
<style>
.contenedores-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    background: rgba(15, 23, 42, .35);
}
.contenedores-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 81;
    display: flex;
    flex-direction: column;
    width: min(540px, 100%);
    background: var(--surface-color);
    box-shadow: -16px 0 40px rgba(15, 23, 42, .18);
    transform: translateX(100%);
    transition: transform .2s ease;
}
.contenedores-drawer.is-open { transform: translateX(0); }
.contenedores-drawer-bar,
.contenedores-drawer-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .9rem 1rem;
    border-bottom: 1px solid var(--surface-border);
}
.contenedores-drawer-footer {
    border-bottom: 0;
    border-top: 1px solid var(--surface-border);
    margin-top: auto;
}
.contenedores-drawer-bar h3 { margin: 0; font-size: 1rem; }
.contenedores-drawer-bar p { margin: .2rem 0 0; color: var(--text-muted); font-size: .78rem; }
.contenedores-drawer-body {
    overflow: auto;
    padding: 1rem;
    display: grid;
    gap: 1.1rem;
}
.contenedores-drawer-body h4 {
    margin: 0 0 .45rem;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.contenedores-bulk-bar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .65rem;
    margin-top: .75rem;
    padding: .75rem 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-color);
    box-shadow: 0 -8px 20px rgba(15, 23, 42, .06);
}
.contenedores-bulk-bar span { color: var(--text-muted); font-size: .8rem; }
.pending-compact {
    border: 0;
    background: none;
    padding: 0;
    cursor: pointer;
}
.pending-details:not([hidden]) {
    display: flex;
    flex-wrap: wrap;
    gap: .25rem;
    margin-top: .35rem;
}
.pending-details .review-next-step {
    flex: 1 1 100%;
    max-width: none;
}
.review-queue[open] .review-queue-grid { margin-top: .75rem; }
.review-queue > summary {
    list-style: none;
    cursor: pointer;
}
.review-queue > summary::-webkit-details-marker { display: none; }
.tarifa-picker, .worker-picker { display: grid; gap: .45rem; }
.tarifa-search-wrap, .worker-search-wrap { position: relative; }
.tarifa-dropdown, .worker-dropdown {
    position: absolute;
    left: 0; right: 0; top: calc(100% + 4px);
    z-index: 5;
    display: none;
    max-height: 240px;
    overflow: auto;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-color);
    box-shadow: 0 12px 24px rgba(15, 23, 42, .12);
}
.tarifa-option, .worker-option {
    display: grid;
    gap: .1rem;
    padding: .55rem .7rem;
    cursor: pointer;
}
.tarifa-option:hover, .worker-option:hover { background: rgba(15, 27, 76, .06); }
.tarifa-option span, .worker-option span, .tarifa-empty { color: var(--text-muted); font-size: .75rem; }
.tarifa-empty { padding: .65rem .7rem; }
.tarifa-selected {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .55rem .65rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    font-size: .82rem;
}
.tarifa-selected.is-empty { color: var(--text-muted); }
.distribution-summary {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
    align-items: center;
    color: var(--text-muted);
    font-size: .8rem;
}
.distribution-status { color: #d97706; font-weight: 700; }
.distribution-status.is-ok { color: var(--success-color); }
.worker-tags { display: grid; gap: .4rem; }
.worker-tag {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: .45rem;
    align-items: center;
    padding: .5rem .6rem;
    border-radius: 8px;
    background: rgba(15, 27, 76, .08);
}
.worker-tag small { display: block; color: var(--text-muted); font-size: .72rem; }
.percent-control { display: inline-flex; align-items: center; gap: .25rem; }
.percent-control input { width: 72px; }
.muted-hint { display: block; margin-top: .35rem; color: var(--text-muted); font-size: .75rem; }
@media (max-width: 640px) {
    .contenedores-drawer { width: 100%; }
}
</style>
