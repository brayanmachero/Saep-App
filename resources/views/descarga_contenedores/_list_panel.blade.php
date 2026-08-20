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
            <div class="contenedores-fact-row">
                <div data-fact-select></div>
                <button type="button" class="icon-btn" data-clear-tarifa title="Limpiar tarifa"><i class="bi bi-x-lg"></i></button>
            </div>
            <small class="muted-hint">Busca como en inventario. Si el código es único del cliente, se asocia solo.</small>
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
    if (!drawer || !backdrop) return;

    document.body.append(backdrop, drawer);

    const titleEl = drawer.querySelector('[data-drawer-title]');
    const metaEl = drawer.querySelector('[data-drawer-meta]');
    const factSection = drawer.querySelector('[data-drawer-fact]');
    const factMount = drawer.querySelector('[data-fact-select]');
    const crewBox = drawer.querySelector('[data-drawer-crew]');
    const tarifaIdInput = drawer.querySelector('[data-drawer-tarifa-id]');
    const factInput = drawer.querySelector('[data-drawer-fact-codigo]');
    const saveBtn = drawer.querySelector('[data-drawer-save]');
    const fullEdit = drawer.querySelector('[data-drawer-full-edit]');
    const drawerBody = drawer.querySelector('.contenedores-drawer-body');

    let mode = 'single';
    let currentId = null;
    let currentCenterId = '';
    let currentOperacion = '';
    let selectedIds = new Set();
    const portalMenus = [];

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

    function workerCenterKey(worker) {
        return worker.centro_costo_id
            ? `id:${worker.centro_costo_id}`
            : `name:${normalizeText(worker.centro || 'Sin centro')}`;
    }

    function workerCargoKey(worker) {
        return worker.cargo_id
            ? `id:${worker.cargo_id}`
            : `name:${normalizeText(worker.cargo || 'Sin cargo')}`;
    }

    function closeAllPortals(except) {
        portalMenus.forEach(item => {
            if (item !== except) item.close();
        });
    }

    function createPortalSelect(config) {
        const wrapper = document.createElement('div');
        wrapper.className = 'contenedores-search-select';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'contenedores-search-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const triggerLabel = document.createElement('span');
        const icon = document.createElement('i');
        icon.className = 'bi bi-search';
        trigger.append(triggerLabel, icon);

        const menu = document.createElement('div');
        menu.className = 'contenedores-search-select-menu is-portal';
        menu.hidden = true;
        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.className = 'contenedores-search-select-search';
        searchInput.autocomplete = 'off';
        searchInput.placeholder = config.searchPlaceholder || 'Buscar...';
        const results = document.createElement('div');
        results.className = 'contenedores-search-select-results';
        const help = document.createElement('small');
        help.className = 'contenedores-search-select-help';
        help.textContent = config.help || 'Escribe para filtrar las opciones.';
        menu.append(searchInput, results, help);
        document.body.appendChild(menu);
        config.mount.replaceChildren(wrapper);
        wrapper.appendChild(trigger);

        function sync() {
            triggerLabel.textContent = (config.getTriggerLabel && config.getTriggerLabel()) || config.placeholder || 'Selecciona una opción';
        }

        function position() {
            if (menu.hidden) return;
            const bounds = trigger.getBoundingClientRect();
            const pad = 16;
            const available = Math.max(1, window.innerWidth - pad * 2);
            const width = Math.min(Math.max(bounds.width, 280), Math.min(540, available));
            const left = Math.min(Math.max(pad, bounds.left), window.innerWidth - width - pad);
            menu.style.width = width + 'px';
            menu.style.left = left + 'px';
            menu.style.right = 'auto';
            menu.style.top = (bounds.bottom + 5) + 'px';
            const menuBounds = menu.getBoundingClientRect();
            if (menuBounds.bottom > window.innerHeight - pad && bounds.top - menuBounds.height - 5 >= pad) {
                menu.style.top = Math.max(pad, bounds.top - menuBounds.height - 5) + 'px';
            }
        }

        function render() {
            const query = normalizeText(searchInput.value);
            const matches = (config.getOptions() || []).filter(option => {
                if (!query) return true;
                return normalizeText([option.label, option.meta, option.search].filter(Boolean).join(' ')).includes(query);
            }).slice(0, 60);
            results.replaceChildren();

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'contenedores-search-select-empty';
                empty.textContent = config.emptyText || 'No hay opciones que coincidan.';
                results.appendChild(empty);
                if (config.renderEmpty) config.renderEmpty(results, searchInput.value);
                return;
            }

            matches.forEach(option => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'contenedores-search-select-option' + (option.selected ? ' is-selected' : '');
                button.innerHTML = `<span>${escapeHtml(option.label)}</span>${option.meta ? `<small>${escapeHtml(option.meta)}</small>` : ''}`;
                button.addEventListener('click', () => {
                    config.onSelect(option, searchInput.value);
                    sync();
                    if (config.stayOpen) {
                        searchInput.value = '';
                        render();
                        position();
                        searchInput.focus();
                    } else {
                        close();
                    }
                });
                results.appendChild(button);
            });
        }

        function open() {
            closeAllPortals(api);
            wrapper.classList.add('is-open');
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            searchInput.value = '';
            render();
            position();
            requestAnimationFrame(() => searchInput.focus());
        }

        function close() {
            menu.hidden = true;
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function destroy() {
            close();
            menu.remove();
            wrapper.remove();
            const index = portalMenus.indexOf(api);
            if (index >= 0) portalMenus.splice(index, 1);
        }

        trigger.addEventListener('click', () => menu.hidden ? open() : close());
        searchInput.addEventListener('input', () => {
            render();
            if (config.onSearch) config.onSearch(searchInput.value);
        });
        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
                trigger.focus();
            }
            if (event.key === 'Enter') {
                const first = results.querySelector('.contenedores-search-select-option');
                if (first) {
                    event.preventDefault();
                    first.click();
                }
            }
        });

        const api = { open, close, sync, render, position, destroy, menu, wrapper };
        portalMenus.push(api);
        sync();
        return api;
    }

    function setSelectedTarifa(tarifa, manualCode = '') {
        tarifaIdInput.value = tarifa ? String(tarifa.id) : '';
        factInput.value = tarifa ? tarifa.codigo : String(manualCode || '').trim().toUpperCase();
        if (factSelect) factSelect.sync();
        crewBox.dispatchEvent(new Event('tarifa-change'));
    }

    const factSelect = createPortalSelect({
        mount: factMount,
        placeholder: 'Selecciona tarifa FACT',
        searchPlaceholder: 'Buscar código FACT, cliente, centro o proceso',
        help: 'Escribe el código. Si es único del cliente, se asocia solo.',
        getTriggerLabel() {
            const tarifa = byTarifaId.get(String(tarifaIdInput.value || ''));
            if (tarifa) return tarifaLabel(tarifa);
            return factInput.value ? `Código manual: ${factInput.value}` : 'Selecciona tarifa FACT';
        },
        getOptions() {
            const cliente = clienteFromOperacion(currentOperacion);
            return tarifas
                .filter(tarifa => !currentCenterId || !tarifa.centro_costo_id || String(tarifa.centro_costo_id) === String(currentCenterId))
                .sort((a, b) => {
                    if (!cliente) return 0;
                    const aMatch = String(a.cliente || '').toUpperCase() === cliente ? 0 : 1;
                    const bMatch = String(b.cliente || '').toUpperCase() === cliente ? 0 : 1;
                    return aMatch - bMatch;
                })
                .map(tarifa => ({
                    id: tarifa.id,
                    label: tarifa.codigo,
                    meta: [tarifa.cliente, tarifa.centro || 'General', tarifa.proceso].filter(Boolean).join(' · '),
                    search: tarifaLabel(tarifa),
                    selected: String(tarifa.id) === String(tarifaIdInput.value || ''),
                    tarifa,
                }));
        },
        onSelect(option) {
            setSelectedTarifa(option.tarifa);
        },
        renderEmpty(results, raw) {
            const code = String(raw || '').trim().toUpperCase();
            if (!code) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'contenedores-search-select-option';
            button.innerHTML = `<span>Usar código manual: ${escapeHtml(code)}</span><small>Queda pendiente de revisión si no hay tarifa única.</small>`;
            button.addEventListener('click', () => {
                const auto = uniqueTarifaByCode(code, currentCenterId, clienteFromOperacion(currentOperacion));
                setSelectedTarifa(auto, code);
                factSelect.close();
            });
            results.appendChild(button);
        },
        emptyText: 'Sin coincidencias en el catálogo.',
    });

    drawer.querySelector('[data-clear-tarifa]')?.addEventListener('click', () => {
        setSelectedTarifa(null, '');
        closeAllPortals();
    });

    function initCrewPicker(initial = []) {
        if (crewBox._portals) {
            crewBox._portals.forEach(item => item.destroy());
        }

        const selected = new Map();
        crewBox._selected = selected;
        let centerFilter = '';
        let cargoFilter = '';

        crewBox.innerHTML = `
            <div class="contenedores-filter-row">
                <label>Centro<div data-center-select></div></label>
                <label>Cargo<div data-cargo-select></div></label>
            </div>
            <div class="contenedores-filter-meta">
                <span data-worker-count></span>
                <button type="button" class="btn-secondary btn-mini" data-add-filtered>Agregar lista filtrada</button>
            </div>
            <div data-worker-select></div>
            <div class="worker-tags"></div>
            <div class="distribution-summary">
                <span>Total: <strong data-total>0%</strong></span>
                <span class="distribution-status" data-total-hint>Debe sumar 100% para validar.</span>
                ${canViewCosts ? '<span>Pago estimado: <strong data-pago>$0</strong></span>' : ''}
                <button type="button" class="btn-secondary btn-mini" data-equalize>Repartir igual</button>
            </div>
        `;

        const tags = crewBox.querySelector('.worker-tags');
        const totalEl = crewBox.querySelector('[data-total]');
        const hintEl = crewBox.querySelector('[data-total-hint]');
        const pagoEl = crewBox.querySelector('[data-pago]');
        const countEl = crewBox.querySelector('[data-worker-count]');
        const centerOptions = [...new Map(workers.map(worker => [
            workerCenterKey(worker),
            worker.centro || 'Sin centro',
        ])).entries()].sort((a, b) => a[1].localeCompare(b[1], 'es'));

        const defaultCenter = currentCenterId ? `id:${currentCenterId}` : '';
        if (defaultCenter && centerOptions.some(([value]) => value === defaultCenter)) {
            centerFilter = defaultCenter;
        }

        function cargoOptions() {
            const source = centerFilter
                ? workers.filter(worker => workerCenterKey(worker) === centerFilter)
                : workers;
            return [...new Map(source.map(worker => [
                workerCargoKey(worker),
                worker.cargo || 'Sin cargo',
            ])).entries()].sort((a, b) => a[1].localeCompare(b[1], 'es'));
        }

        function filteredWorkers() {
            return workers.filter(worker => {
                if (selected.has(String(worker.id))) return false;
                if (centerFilter && workerCenterKey(worker) !== centerFilter) return false;
                if (cargoFilter && workerCargoKey(worker) !== cargoFilter) return false;
                return true;
            });
        }

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
            const available = filteredWorkers().length;
            countEl.textContent = `${available} disponible${available === 1 ? '' : 's'} con el filtro actual`;
        }

        function render() {
            tags.innerHTML = [...selected.values()].map(({ worker, porcentaje }) => `
                <div class="worker-tag">
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
                    crewBox._portals.forEach(item => item.sync());
                });
            });
            updateTotals();
            if (crewBox._portals) crewBox._portals.forEach(item => item.sync());
        }

        function addWorker(worker) {
            if (selected.has(String(worker.id))) return;
            selected.set(String(worker.id), { worker, porcentaje: null });
            equalize();
            render();
        }

        const centerSelect = createPortalSelect({
            mount: crewBox.querySelector('[data-center-select]'),
            placeholder: 'Todos los centros',
            searchPlaceholder: 'Buscar centro',
            help: 'Filtra la nómina por centro. El cargo se actualiza después.',
            getTriggerLabel: () => centerOptions.find(([value]) => value === centerFilter)?.[1] || 'Todos los centros',
            getOptions: () => [
                { id: '', label: 'Todos los centros', selected: !centerFilter },
                ...centerOptions.map(([id, label]) => ({ id, label, selected: centerFilter === id })),
            ],
            onSelect(option) {
                centerFilter = option.id || '';
                if (cargoFilter && !cargoOptions().some(([value]) => value === cargoFilter)) {
                    cargoFilter = '';
                }
                cargoSelect.sync();
                workerSelect.sync();
                updateTotals();
            },
        });

        const cargoSelect = createPortalSelect({
            mount: crewBox.querySelector('[data-cargo-select]'),
            placeholder: 'Todos los cargos',
            searchPlaceholder: 'Buscar cargo',
            help: 'Depende del centro elegido.',
            getTriggerLabel: () => cargoOptions().find(([value]) => value === cargoFilter)?.[1] || 'Todos los cargos',
            getOptions: () => [
                { id: '', label: 'Todos los cargos', selected: !cargoFilter },
                ...cargoOptions().map(([id, label]) => ({ id, label, selected: cargoFilter === id })),
            ],
            onSelect(option) {
                cargoFilter = option.id || '';
                workerSelect.sync();
                updateTotals();
            },
        });

        const workerSelect = createPortalSelect({
            mount: crewBox.querySelector('[data-worker-select]'),
            placeholder: 'Buscar trabajador',
            searchPlaceholder: 'Buscar por nombre, RUT, cargo o centro',
            help: 'Los filtros de centro y cargo reducen esta lista. Puedes agregar varios seguidos.',
            stayOpen: true,
            getTriggerLabel: () => 'Buscar trabajador',
            getOptions: () => filteredWorkers().map(worker => ({
                id: worker.id,
                label: worker.label,
                meta: [worker.rut, worker.cargo, worker.centro].filter(Boolean).join(' · '),
                search: [worker.label, worker.rut, worker.cargo, worker.centro].join(' '),
                worker,
            })),
            onSelect(option) {
                addWorker(option.worker);
            },
            emptyText: 'No hay trabajadores con ese filtro.',
        });

        crewBox._portals = [centerSelect, cargoSelect, workerSelect];
        crewBox.querySelector('[data-equalize]').addEventListener('click', () => {
            equalize();
            render();
        });
        crewBox.querySelector('[data-add-filtered]').addEventListener('click', () => {
            filteredWorkers().forEach(addWorker);
            closeAllPortals();
        });
        crewBox.addEventListener('tarifa-change', updateTotals);

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
        closeAllPortals();
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

    function repositionPortals() {
        portalMenus.forEach(item => item.position());
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
        if (!event.target.closest('.contenedores-search-select, .contenedores-search-select-menu')) {
            closeAllPortals();
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
    window.addEventListener('resize', repositionPortals);
    drawerBody.addEventListener('scroll', repositionPortals);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const openPortal = portalMenus.find(item => !item.menu.hidden);
        if (openPortal) {
            event.preventDefault();
            openPortal.close();
            return;
        }
        if (drawer.classList.contains('is-open')) closeDrawer();
    });
})();
</script>
<style>
.contenedores-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 4000;
    background: rgba(15, 23, 42, .35);
}
.contenedores-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 4001;
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
.contenedores-fact-row,
.contenedores-filter-row,
.contenedores-filter-meta,
.worker-picker {
    display: grid;
    gap: .5rem;
}
.contenedores-fact-row {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
}
.contenedores-filter-row {
    grid-template-columns: 1fr 1fr;
}
.contenedores-filter-row label {
    display: grid;
    gap: .28rem;
    min-width: 0;
    color: #53627d;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .035em;
    text-transform: uppercase;
}
.contenedores-filter-meta {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    color: var(--text-muted);
    font-size: .78rem;
}
.contenedores-search-select { position: relative; min-width: 0; }
.contenedores-search-select-trigger {
    width: 100%;
    min-height: 2.42rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    padding: .48rem .65rem;
    color: #25334f;
    background: #fff;
    border: 1px solid #cfd8e6;
    border-radius: .45rem;
    text-align: left;
    font-size: .82rem;
    line-height: 1.35;
}
.contenedores-search-select-trigger:hover { border-color: #9eadd0; }
.contenedores-search-select-trigger:focus-visible {
    outline: 0;
    border-color: #7250ca;
    box-shadow: 0 0 0 .16rem rgba(114, 80, 202, .12);
}
.contenedores-search-select-trigger > span { min-width: 0; overflow-wrap: anywhere; }
.contenedores-search-select-trigger > i { color: #5b6780; font-size: .9rem; flex: 0 0 auto; margin-top: .15rem; }
.contenedores-search-select-menu.is-portal {
    position: fixed;
    z-index: 4100;
    max-height: calc(100vh - 2rem);
    padding: .55rem;
    background: #fff;
    border: 1px solid #cfd8e6;
    border-radius: .55rem;
    box-shadow: 0 .75rem 1.7rem rgba(28, 39, 63, .2);
}
.contenedores-search-select-menu[hidden] { display: none; }
.contenedores-search-select-search {
    width: 100%;
    min-height: 2.35rem;
    padding: .46rem .62rem;
    color: #25334f;
    background: #fbfcfe;
    border: 1px solid #d5deeb;
    border-radius: .4rem;
    font-size: .84rem;
}
.contenedores-search-select-search:focus {
    outline: 0;
    border-color: #7250ca;
    box-shadow: 0 0 0 .16rem rgba(114, 80, 202, .12);
}
.contenedores-search-select-results {
    max-height: 16rem;
    margin-top: .45rem;
    overflow: auto;
    overscroll-behavior: contain;
}
.contenedores-search-select-option,
.contenedores-search-select-empty {
    width: 100%;
    padding: .5rem .6rem;
    border: 0;
    border-radius: .35rem;
    text-align: left;
    font-size: .8rem;
    line-height: 1.35;
    background: transparent;
}
.contenedores-search-select-option { color: #25334f; }
.contenedores-search-select-option:hover,
.contenedores-search-select-option:focus-visible {
    color: #23085d;
    background: #f3f0ff;
    outline: 0;
}
.contenedores-search-select-option.is-selected {
    color: #321170;
    background: #eee8ff;
    font-weight: 800;
}
.contenedores-search-select-option span { display: block; overflow-wrap: anywhere; }
.contenedores-search-select-option small { display: block; margin-top: .12rem; color: #6d7a91; font-size: .72rem; }
.contenedores-search-select-empty { color: #718096; font-style: italic; }
.contenedores-search-select-help {
    display: block;
    margin: .38rem .1rem 0;
    color: #6d7a91;
    font-size: .7rem;
    line-height: 1.35;
}
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
.btn-mini { padding: .45rem .7rem; font-size: .78rem; min-height: 34px; }
@media (max-width: 640px) {
    .contenedores-drawer { width: 100%; }
    .contenedores-filter-row { grid-template-columns: 1fr; }
}
</style>
