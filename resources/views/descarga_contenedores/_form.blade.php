@php
    $descarga = $descarga ?? null;
    $selectedParticipantes = old('participantes_json');
    if ($selectedParticipantes) {
        $selectedParticipantes = json_decode($selectedParticipantes, true) ?: [];
    } else {
        $selectedParticipantes = $descarga?->participantes?->map(fn ($p) => [
            'id' => $p->talana_trabajador_id,
            'porcentaje' => $p->porcentaje_participacion,
        ])->filter(fn ($p) => !empty($p['id']))->values()->all() ?? [];
    }
    $supervisorActual = $descarga?->supervisor ?: ($supervisorSistema ?? auth()->user());
    $supervisorActualNombre = $supervisorActual?->nombre_completo ?: $supervisorActual?->name;
    $supervisorActualMeta = collect([
        $supervisorActual?->cargo?->nombre,
        $supervisorActual?->centroCosto?->nombre,
    ])->filter()->implode(' · ');
    $selectedTarifaId = old('tarifa_id', $descarga->tarifa_id ?? '');
    $selectedTarifa = $tarifas->first(fn ($t) => (string) $t->id === (string) $selectedTarifaId);
    $selectedFactCodigo = old('fact_codigo', $descarga->fact_codigo ?? ($selectedTarifa?->codigo ?? ''));
    $selectedTarifaSearch = $selectedTarifa
        ? trim($selectedTarifa->codigo . ' · ' . $selectedTarifa->cliente . ' · ' . $selectedTarifa->proceso)
        : $selectedFactCodigo;
    $puedeGestionarCostos = auth()->user()->puedeGestionarCostosDescargaContenedores();
    $tarifasPickerData = $tarifas->map(function ($t) use ($puedeGestionarCostos) {
        $data = [
            'id' => $t->id,
            'cliente' => $t->cliente,
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

@csrf
@if($descarga)
    @method('PUT')
@endif

<div class="form-panel">
    @include('descarga_contenedores._context_help', [
        'title' => 'Criterio de llenado',
        'items' => [
            'Guarda primero en borrador; coordinación valida cuando el registro queda completo.',
            'Supervisor sistema se toma desde el usuario conectado para mantener trazabilidad.',
            'El código FACT puede seleccionarse desde tarifa o escribirse manualmente para revisión.',
            'Los participantes se guardan con copia histórica de nombre, RUT, cargo y centro Talana.',
        ],
    ])

    <h4 class="section-title">Datos de la descarga</h4>
    <div class="form-grid">
        <div class="form-group">
            <label>Estado @include('descarga_contenedores._help_icon', ['text' => 'Todo registro nuevo entra como borrador. Validar corresponde a coordinación.'])</label>
            <input type="text" class="form-control readonly-control" value="{{ $descarga?->estadoBadge['label'] ?? 'Borrador' }}" readonly>
            <small class="muted-hint">Todo registro nuevo queda en borrador. La validación la realiza coordinación desde acciones rápidas.</small>
        </div>
        <div class="form-group">
            <label>Fecha @include('descarga_contenedores._help_icon', ['text' => 'Fecha en que se ejecuta o programa la descarga del contenedor.'])</label>
            <input type="date" name="fecha" value="{{ old('fecha', optional($descarga?->fecha)->format('Y-m-d')) }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Operación @include('descarga_contenedores._help_icon', ['text' => 'Cliente o línea operativa asociada al contenedor. Sirve para reportes.'])</label>
            <input type="text" name="operacion" value="{{ old('operacion', $descarga->operacion ?? 'Walmart') }}" class="form-control" placeholder="Walmart, Maersk, DHL...">
        </div>
        <div class="form-group">
            <label>Centro de costo @include('descarga_contenedores._help_icon', ['text' => 'Centro gestionado por la operación. También filtra trabajadores disponibles en Talana.'])</label>
            <select name="centro_costo_id" class="form-control">
                <option value="">Sin asociar</option>
                @foreach($centros as $centro)
                    <option value="{{ $centro->id }}" {{ old('centro_costo_id', $descarga->centro_costo_id ?? '') == $centro->id ? 'selected' : '' }}>
                        {{ $centro->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Bodega / CD @include('descarga_contenedores._help_icon', ['text' => 'Nombre operativo de la bodega o centro de distribución informado en la programación.'])</label>
            <input type="text" name="bodega" value="{{ old('bodega', $descarga->bodega ?? '') }}" class="form-control" placeholder="LTS Peñón, Quilicura, Campos de Chile...">
        </div>
        <div class="form-group">
            <label>Facturación / mes @include('descarga_contenedores._help_icon', ['text' => 'Periodo interno de facturación o ciclo recibido en la programación.'])</label>
            <input type="text" name="facturacion_mes" value="{{ old('facturacion_mes', $descarga->facturacion_mes ?? '') }}" class="form-control" placeholder="Julio, P072026...">
        </div>
        <div class="form-group">
            <label>Contenedor @include('descarga_contenedores._help_icon', ['text' => 'Identificador o número del contenedor. Se usa para buscar duplicados y trazabilidad.'])</label>
            <input type="text" name="contenedor" value="{{ old('contenedor', $descarga->contenedor ?? '') }}" class="form-control" placeholder="N° contenedor">
        </div>
        <div class="form-group">
            <label>Equipo descarga @include('descarga_contenedores._help_icon', ['text' => 'Nombre del equipo, cuadrilla o grupo responsable de ejecutar la descarga.'])</label>
            <input type="text" name="equipo_descarga" value="{{ old('equipo_descarga', $descarga->equipo_descarga ?? '') }}" class="form-control" placeholder="SAEP 1, Equipo 3, Brazo AM...">
        </div>
        <div class="form-group">
            <label>Supervisor sistema @include('descarga_contenedores._help_icon', ['text' => 'Usuario autenticado que crea o edita el registro. No se escribe manualmente.'])</label>
            <input type="text" class="form-control readonly-control" value="{{ $supervisorActualNombre ?: 'Se asignará por login' }}" readonly>
            <small class="muted-hint">
                {{ $supervisorActualMeta ?: 'Se completa automáticamente con el usuario autenticado.' }}
            </small>
        </div>
        <div class="form-group">
            <label>Supervisor / encargado texto @include('descarga_contenedores._help_icon', ['text' => 'Nombre informado en correo, Excel o reporte cuando no coincide con el usuario conectado.'])</label>
            <input type="text" name="supervisor_nombre" value="{{ old('supervisor_nombre', $descarga->supervisor_nombre ?? '') }}" class="form-control" placeholder="Nombre recibido en correo o reporte">
        </div>
    </div>

    <h4 class="section-title">Detalle operativo</h4>
    <div class="form-grid">
        <div class="form-group">
            <label>Hora cita @include('descarga_contenedores._help_icon', ['text' => 'Hora programada para recibir o iniciar la operación.'])</label>
            <input type="time" name="hora_cita" value="{{ old('hora_cita', $descarga?->hora_cita ? substr($descarga->hora_cita, 0, 5) : '') }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Inicio descarga @include('descarga_contenedores._help_icon', ['text' => 'Hora real de inicio. Permite medir desviaciones contra la cita.'])</label>
            <input type="time" name="hora_inicio_descarga" value="{{ old('hora_inicio_descarga', $descarga?->hora_inicio_descarga ? substr($descarga->hora_inicio_descarga, 0, 5) : '') }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Término descarga @include('descarga_contenedores._help_icon', ['text' => 'Hora real de término de la descarga.'])</label>
            <input type="time" name="hora_termino_descarga" value="{{ old('hora_termino_descarga', $descarga?->hora_termino_descarga ? substr($descarga->hora_termino_descarga, 0, 5) : '') }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Ítems @include('descarga_contenedores._help_icon', ['text' => 'Cantidad de líneas, SKU o ítems informados para el contenedor.'])</label>
            <input type="number" name="item" value="{{ old('item', $descarga->item ?? '') }}" class="form-control" min="0">
        </div>
        <div class="form-group">
            <label>Cajas @include('descarga_contenedores._help_icon', ['text' => 'Volumen de cajas descargadas. Se usa en reportes operativos.'])</label>
            <input type="number" name="cajas" value="{{ old('cajas', $descarga->cajas ?? '') }}" class="form-control" min="0">
        </div>
        <div class="form-group">
            <label>Pallets @include('descarga_contenedores._help_icon', ['text' => 'Cantidad de pallets asociados al contenedor cuando aplica.'])</label>
            <input type="number" name="pallets" value="{{ old('pallets', $descarga->pallets ?? '') }}" class="form-control" min="0" step="0.01">
        </div>
        <div class="form-group">
            <label>Producto @include('descarga_contenedores._help_icon', ['text' => 'Descripción corta del contenido descargado.'])</label>
            <input type="text" name="producto" value="{{ old('producto', $descarga->producto ?? '') }}" class="form-control" placeholder="Productos varios, pastas, aceites...">
        </div>
        <div class="form-group">
            <label>Tarifa FACT @include('descarga_contenedores._help_icon', ['text' => $puedeGestionarCostos ? 'Código FACT asociado a costo empresa y pago colaborador. Queda congelado en el registro.' : 'Código FACT operativo. Los valores económicos quedan reservados para coordinación.'])</label>
            <input type="hidden" name="tarifa_id" id="tarifa_id" value="{{ $selectedTarifaId }}">
            <input type="hidden" name="fact_codigo" id="fact_codigo" value="{{ $selectedFactCodigo }}">
            <div class="tarifa-picker" id="tarifa_picker">
                <div class="tarifa-search-wrap">
                    <input type="text" class="form-control tarifa-search" autocomplete="off" value="{{ $selectedTarifaSearch }}" placeholder="Buscar código FACT, cliente o proceso...">
                    <div class="tarifa-dropdown"></div>
                </div>
                <div class="tarifa-selected {{ $selectedTarifa ? '' : 'is-empty' }}" data-tarifa-selected>
                    <span data-tarifa-selected-text>
                        @if($selectedTarifa)
                            <strong>{{ $selectedTarifa->codigo }}</strong> · {{ $selectedTarifa->cliente }} · {{ $selectedTarifa->proceso }}
                        @else
                            Sin tarifa asociada
                        @endif
                    </span>
                    <button type="button" class="icon-btn" data-clear-tarifa title="Limpiar tarifa"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <small class="muted-hint">
                {{ $puedeGestionarCostos ? 'Busca por código, cliente o proceso. La tarifa elegida queda congelada en el registro para mantener historial.' : 'Busca por código, cliente o proceso. Los valores económicos quedan reservados para coordinación.' }}
            </small>
        </div>
        <div class="form-group">
            <label>Código FACT seleccionado @include('descarga_contenedores._help_icon', ['text' => 'Vista de solo lectura del código que se guardará. Si no hay tarifa asociada, queda como pendiente de revisión.'])</label>
            <input type="text" id="fact_codigo_preview" class="form-control readonly-control" value="{{ $selectedFactCodigo }}" placeholder="Se completa desde la tarifa o al escribir un código" readonly>
            <small class="muted-hint">Si escribes un código manual sin elegir tarifa, se guardará como código FACT para revisión.</small>
        </div>
        <div class="form-group" style="grid-column:1/-1">
            <label>Observación @include('descarga_contenedores._help_icon', ['text' => 'Notas de apoyo: diferencias, apoyo de otro centro, incidencias o instrucciones de coordinación.'])</label>
            <textarea name="observacion" class="form-control" rows="3" placeholder="Notas operativas, apoyo de otro centro, diferencias, etc.">{{ old('observacion', $descarga->observacion ?? '') }}</textarea>
        </div>
    </div>

    <h4 class="section-title">Trabajadores que participaron @include('descarga_contenedores._help_icon', ['text' => 'Selecciona la dotación real que participó. Los porcentajes deben sumar 100% para validar.'])</h4>
    <input type="hidden" name="participantes_json" id="participantes_json" value='@json($selectedParticipantes)'>
    <div id="participantes_picker" class="worker-picker"></div>
    <small class="muted-hint">Fuente: nómina Talana acotada a los centros gestionados en los Excel de descarga. El sistema guardará una copia histórica de nombre, RUT, cargo y centro al momento del registro.</small>
</div>

<script type="application/json" id="trabajadores_data">@json($trabajadores)</script>
<script type="application/json" id="tarifas_data">@json($tarifasPickerData)</script>
<script>
const canViewCosts = @json($puedeGestionarCostos);

function initWorkerPicker(container, hiddenInput, initialIds) {
    const workers = JSON.parse(document.getElementById('trabajadores_data').textContent || '[]');
    const tarifas = JSON.parse(document.getElementById('tarifas_data').textContent || '[]');
    const selected = new Map();
    const byId = new Map(workers.map(w => [String(w.id), w]));
    const byTarifaId = new Map(tarifas.map(t => [String(t.id), t]));
    const tarifaSelect = document.getElementById('tarifa_id');
    const factInput = document.querySelector('[name="fact_codigo"]');
    const factPreview = document.getElementById('fact_codigo_preview');

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

    function tarifaLabel(tarifa) {
        return `${tarifa.codigo || ''} · ${tarifa.cliente || ''} · ${tarifa.proceso || ''}`;
    }

    function setFactCodigo(value) {
        const clean = String(value || '').trim().toUpperCase();
        if (factInput) factInput.value = clean;
        if (factPreview) factPreview.value = clean;
    }

    function initTarifaPicker() {
        const picker = document.getElementById('tarifa_picker');
        if (!picker || !tarifaSelect || !factInput) return;

        const search = picker.querySelector('.tarifa-search');
        const dropdown = picker.querySelector('.tarifa-dropdown');
        const selectedBox = picker.querySelector('[data-tarifa-selected]');
        const selectedText = picker.querySelector('[data-tarifa-selected-text]');
        const clearBtn = picker.querySelector('[data-clear-tarifa]');

        function updateSelected(tarifa = null, manualCode = '') {
            const hasValue = !!tarifa || !!manualCode;
            selectedBox?.classList.toggle('is-empty', !hasValue);

            if (!selectedText) return;

            if (tarifa) {
                const badge = tarifa.requiere_revision ? '<span class="badge warning">Revisar</span>' : '';
                selectedText.innerHTML = `<strong>${escapeHtml(tarifa.codigo)}</strong> · ${escapeHtml(tarifa.cliente)} · ${escapeHtml(tarifa.proceso)} ${badge}`;
                return;
            }

            selectedText.textContent = manualCode ? `Código manual: ${manualCode}` : 'Sin tarifa asociada';
        }

        function renderTarifas(query = '') {
            const q = query.trim().toLowerCase();
            const matches = tarifas.filter(t => {
                const searchable = [
                    t.codigo,
                    t.cliente,
                    t.proceso,
                ].join(' ').toLowerCase();

                return !q || searchable.includes(q);
            }).slice(0, 80);

            dropdown.innerHTML = matches.length
                ? matches.map(t => `
                    <div class="tarifa-option" data-id="${escapeHtml(t.id)}" role="button" tabindex="0">
                        <strong>${escapeHtml(t.codigo)}</strong>
                        <span>${escapeHtml(t.cliente)} · ${escapeHtml(t.proceso)}</span>
                        ${t.requiere_revision ? '<em>Revisar</em>' : ''}
                    </div>
                `).join('')
                : '<div class="tarifa-empty">Sin resultados. Puedes dejar el código escrito para revisión.</div>';
            dropdown.style.display = 'block';

            dropdown.querySelectorAll('.tarifa-option').forEach(option => {
                const selectOption = event => {
                    event.preventDefault();
                    if (dropdown.style.display === 'none') return;

                    const tarifa = byTarifaId.get(String(option.dataset.id));
                    if (!tarifa) return;

                    tarifaSelect.value = String(tarifa.id);
                    search.value = tarifaLabel(tarifa);
                    setFactCodigo(tarifa.codigo);
                    updateSelected(tarifa);
                    dropdown.style.display = 'none';
                    tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
                };

                option.addEventListener('mousedown', selectOption);
                option.addEventListener('click', selectOption);
                option.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') selectOption(event);
                });
            });
        }

        search?.addEventListener('focus', () => renderTarifas(search.value));
        search?.addEventListener('input', () => {
            const manualCode = search.value.trim().toUpperCase();
            tarifaSelect.value = '';
            setFactCodigo(manualCode);
            updateSelected(null, manualCode);
            renderTarifas(search.value);
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
        search?.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 150));
        clearBtn?.addEventListener('click', () => {
            tarifaSelect.value = '';
            if (search) search.value = '';
            setFactCodigo('');
            updateSelected();
            dropdown.style.display = 'none';
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });

        const initialTarifa = byTarifaId.get(String(tarifaSelect.value || ''));
        if (initialTarifa) {
            updateSelected(initialTarifa);
            setFactCodigo(initialTarifa.codigo);
        } else {
            updateSelected(null, factInput.value);
        }
    }

    initTarifaPicker();

    container.innerHTML = `
        <div class="worker-tags"></div>
        <div class="distribution-summary">
            <span>Total: <strong data-total>0%</strong></span>
            @if($puedeGestionarCostos)
            <span>Pago estimado: <strong data-pago>$0</strong></span>
            @endif
            <button type="button" class="btn-secondary btn-mini" data-equalize>Repartir igual</button>
        </div>
        <div class="worker-filter-row">
            <select class="form-control worker-center-filter" aria-label="Filtrar trabajadores por centro">
                <option value="">Todos los centros</option>
            </select>
            <select class="form-control worker-cargo-filter" aria-label="Filtrar trabajadores por cargo">
                <option value="">Todos los cargos</option>
            </select>
            <span class="worker-filter-count" data-worker-count></span>
        </div>
        <div class="worker-search-wrap">
            <input type="text" class="form-control worker-search" autocomplete="off" placeholder="Buscar trabajador por nombre, RUT, cargo o centro...">
            <div class="worker-dropdown"></div>
        </div>
    `;

    const tags = container.querySelector('.worker-tags');
    const input = container.querySelector('.worker-search');
    const dropdown = container.querySelector('.worker-dropdown');
    const centerFilter = container.querySelector('.worker-center-filter');
    const cargoFilter = container.querySelector('.worker-cargo-filter');
    const countEl = container.querySelector('[data-worker-count]');
    const descargaCenterSelect = document.querySelector('[name="centro_costo_id"]');
    const totalEl = container.querySelector('[data-total]');
    const pagoEl = container.querySelector('[data-pago]');
    const equalizeBtn = container.querySelector('[data-equalize]');

    const centerOptions = [...new Map(workers.map(worker => [
        workerCenterKey(worker),
        worker.centro || 'Sin centro',
    ])).entries()]
        .sort((a, b) => a[1].localeCompare(b[1], 'es'));
    centerOptions.forEach(([value, label]) => {
        centerFilter.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
    });

    function cargoOptionsForCenter(centerValue = '') {
        const source = centerValue
            ? workers.filter(worker => workerCenterKey(worker) === centerValue)
            : workers;

        return [...new Map(source.map(worker => [
            workerCargoKey(worker),
            worker.cargo || 'Sin cargo',
        ])).entries()]
            .sort((a, b) => a[1].localeCompare(b[1], 'es'));
    }

    function refreshCargoOptions() {
        const currentCargo = cargoFilter.value;
        const options = cargoOptionsForCenter(centerFilter.value);

        cargoFilter.innerHTML = '<option value="">Todos los cargos</option>';
        options.forEach(([value, label]) => {
            cargoFilter.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
        });

        cargoFilter.value = options.some(([value]) => value === currentCargo) ? currentCargo : '';
    }

    function syncCenterFilterFromRecord() {
        const selectedCenterId = descargaCenterSelect?.value ? `id:${descargaCenterSelect.value}` : '';
        if (selectedCenterId && centerOptions.some(([value]) => value === selectedCenterId)) {
            centerFilter.value = selectedCenterId;
        } else if (!selectedCenterId) {
            centerFilter.value = '';
        }
        refreshCargoOptions();
    }

    refreshCargoOptions();
    syncCenterFilterFromRecord();

    (initialIds || []).forEach(item => {
        const id = typeof item === 'object' ? item.id : item;
        const porcentaje = typeof item === 'object' && item.porcentaje !== null && item.porcentaje !== undefined
            ? parseFloat(item.porcentaje)
            : null;
        const worker = byId.get(String(id));
        if (worker) selected.set(String(id), { worker, porcentaje });
    });

    if ([...selected.values()].every(item => item.porcentaje === null || Number.isNaN(item.porcentaje))) {
        equalize();
    }

    function currentTarifa() {
        return byTarifaId.get(String(tarifaSelect?.value || '')) || null;
    }

    function formatCurrency(value) {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '—';
        return '$' + Math.round(Number(value)).toLocaleString('es-CL');
    }

    function pagoTotal() {
        if (!canViewCosts) {
            return null;
        }

        const tarifa = currentTarifa();
        if (!tarifa || tarifa.requiere_revision || tarifa.pago_colaborador === null || tarifa.pago_colaborador === undefined) {
            return null;
        }
        return Number(tarifa.pago_colaborador);
    }

    function equalize() {
        const ids = [...selected.keys()];
        if (!ids.length) return;
        const base = Math.round((100 / ids.length) * 100) / 100;
        let assigned = 0;
        ids.forEach((id, index) => {
            const entry = selected.get(id);
            entry.porcentaje = index === ids.length - 1
                ? Math.round((100 - assigned) * 100) / 100
                : base;
            assigned += entry.porcentaje;
            selected.set(id, entry);
        });
    }

    function sync(renderTags = true) {
        const pago = pagoTotal();
        let total = 0;
        hiddenInput.value = JSON.stringify([...selected.entries()].map(([id, entry]) => {
            const porcentaje = Number(entry.porcentaje || 0);
            total += porcentaje;
            return { id: parseInt(id, 10), porcentaje };
        }));

        totalEl.textContent = `${Math.round(total * 100) / 100}%`;
        totalEl.style.color = Math.abs(total - 100) <= 0.05 ? 'var(--success-color)' : '#d97706';
        if (pagoEl) {
            pagoEl.textContent = pago !== null ? formatCurrency(pago) : 'Revisar tarifa';
        }

        if (!renderTags) return;

        tags.innerHTML = '';
        selected.forEach((entry, id) => {
            const worker = entry.worker;
            const porcentaje = Number(entry.porcentaje || 0);
            const monto = pago !== null ? pago * porcentaje / 100 : null;
            const tag = document.createElement('span');
            tag.className = `worker-tag${canViewCosts ? '' : ' no-costs'}`;
            tag.innerHTML = `
                <span class="worker-main">
                    <strong>${worker.label}</strong>
                    <small>${worker.centro || 'Sin centro'}${worker.cargo ? ' · ' + worker.cargo : ''}</small>
                </span>
                <label class="percent-control">
                    <input type="number" min="0" max="100" step="0.01" value="${porcentaje}">
                    <span>%</span>
                </label>
                @if($puedeGestionarCostos)
                <small class="worker-amount">${monto !== null ? formatCurrency(monto) : 'Monto por revisar'}</small>
                @endif
                <button type="button" title="Quitar">&times;</button>
            `;
            tag.querySelector('input').addEventListener('input', event => {
                entry.porcentaje = parseFloat(event.target.value || '0');
                selected.set(id, entry);
                @if($puedeGestionarCostos)
                if (canViewCosts) {
                    const updatedPago = pagoTotal();
                    const updatedMonto = updatedPago !== null ? updatedPago * Number(entry.porcentaje || 0) / 100 : null;
                    tag.querySelector('.worker-amount').textContent = updatedMonto !== null ? formatCurrency(updatedMonto) : 'Monto por revisar';
                }
                @endif
                sync(false);
            });
            tag.querySelector('button').addEventListener('click', () => {
                selected.delete(id);
                equalize();
                sync();
            });
            tags.appendChild(tag);
        });
    }

    function positionWorkerDropdown() {
        const rect = input.getBoundingClientRect();
        const gap = 4;
        const viewportPadding = 16;
        const minDropdownHeight = 120;
        const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
        const spaceAbove = rect.top - viewportPadding;
        const openUp = spaceBelow < minDropdownHeight && spaceAbove > spaceBelow;
        const availableSpace = Math.max(minDropdownHeight, openUp ? spaceAbove - gap : spaceBelow - gap);
        const maxHeight = Math.min(320, availableSpace);

        dropdown.classList.add('is-floating');
        dropdown.style.left = `${rect.left}px`;
        dropdown.style.width = `${rect.width}px`;
        dropdown.style.right = 'auto';
        dropdown.style.top = 'auto';
        dropdown.style.bottom = 'auto';
        dropdown.style.maxHeight = `${maxHeight}px`;

        if (openUp) {
            dropdown.style.bottom = `${Math.max(viewportPadding, window.innerHeight - rect.top + gap)}px`;
        } else {
            dropdown.style.top = `${rect.bottom + gap}px`;
        }
    }

    function showWorkerDropdown() {
        if (dropdown.parentElement !== document.body) {
            document.body.appendChild(dropdown);
        }

        dropdown.style.display = 'block';
        positionWorkerDropdown();
    }

    function hideWorkerDropdown() {
        dropdown.style.display = 'none';
        dropdown.style.top = '';
        dropdown.style.bottom = '';
    }

    function render(query = '') {
        const q = query.toLowerCase();
        const centerValue = centerFilter.value;
        const cargoValue = cargoFilter.value;
        const available = workers.filter(w => {
            if (selected.has(String(w.id))) return false;
            if (centerValue && workerCenterKey(w) !== centerValue) return false;
            if (cargoValue && workerCargoKey(w) !== cargoValue) return false;

            return !q
                || (w.label || '').toLowerCase().includes(q)
                || (w.rut || '').toLowerCase().includes(q)
                || (w.cargo || '').toLowerCase().includes(q)
                || (w.centro || '').toLowerCase().includes(q);
        });
        const matches = available
            .sort((a, b) => `${a.centro || ''} ${a.cargo || ''} ${a.label || ''}`.localeCompare(`${b.centro || ''} ${b.cargo || ''} ${b.label || ''}`, 'es'))
            .slice(0, 70);
        countEl.textContent = `${available.length} disponible${available.length === 1 ? '' : 's'}`;

        if (matches.length) {
            let currentGroup = '';
            dropdown.innerHTML = matches.map(w => {
                const group = `${w.centro || 'Sin centro'} · ${w.cargo || 'Sin cargo'}`;
                const heading = group !== currentGroup
                    ? `<div class="worker-group">${escapeHtml(group)}</div>`
                    : '';
                currentGroup = group;

                return `
                    ${heading}
                    <div class="worker-option" data-id="${w.id}" role="button" tabindex="0">
                        <strong>${escapeHtml(w.label)}</strong>
                        <small>${escapeHtml(w.rut || '')}${w.cargo ? ' · ' + escapeHtml(w.cargo) : ''}</small>
                        <em>${escapeHtml(w.centro || 'Sin centro')}</em>
                    </div>
                `;
            }).join('');
        } else {
            dropdown.innerHTML = '<div class="worker-empty">Sin resultados para ese centro/cargo</div>';
        }
        showWorkerDropdown();

        dropdown.querySelectorAll('.worker-option').forEach(option => {
            const selectOption = event => {
                event.preventDefault();
                if (dropdown.style.display === 'none') return;

                const worker = byId.get(String(option.dataset.id));
                if (worker) {
                    selected.set(String(worker.id), { worker, porcentaje: null });
                    equalize();
                    input.value = '';
                    hideWorkerDropdown();
                    sync();
                }
            };

            option.addEventListener('mousedown', selectOption);
            option.addEventListener('click', selectOption);
            option.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') selectOption(event);
            });
        });
    }

    input.addEventListener('focus', () => render(input.value.trim()));
    input.addEventListener('input', () => render(input.value.trim()));
    input.addEventListener('blur', () => setTimeout(hideWorkerDropdown, 150));
    centerFilter.addEventListener('change', () => {
        refreshCargoOptions();
        render(input.value.trim());
    });
    cargoFilter.addEventListener('change', () => render(input.value.trim()));
    descargaCenterSelect?.addEventListener('change', () => {
        syncCenterFilterFromRecord();
        render(input.value.trim());
    });
    equalizeBtn.addEventListener('click', () => {
        equalize();
        sync();
    });
    tarifaSelect?.addEventListener('change', () => sync());
    window.addEventListener('resize', () => {
        if (dropdown.style.display !== 'none') positionWorkerDropdown();
    });
    window.addEventListener('scroll', () => {
        if (dropdown.style.display !== 'none') positionWorkerDropdown();
    }, true);
    sync();
}

document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('participantes_picker');
    const hidden = document.getElementById('participantes_json');
    if (picker && hidden) {
        let initial = [];
        try { initial = JSON.parse(hidden.value || '[]'); } catch (e) {}
        initWorkerPicker(picker, hidden, initial);
    }
});
</script>

<style>
.form-panel { max-width: 1120px; margin: 0 auto; }
.section-title {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--surface-border);
}
.section-title:not(:first-child) { margin-top: 1.5rem; }
.muted-hint { display: block; margin-top: .45rem; color: var(--text-muted); }
.readonly-control { background: var(--surface-bg); color: var(--text-main); cursor: default; }
.tarifa-picker { display: grid; gap: .45rem; }
.tarifa-search-wrap { position: relative; }
.tarifa-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 280px;
    overflow-y: auto;
    background: var(--surface-card-solid);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    margin-top: 3px;
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
}
.tarifa-option {
    display: grid;
    grid-template-columns: 88px 1fr auto;
    align-items: center;
    gap: .55rem;
    padding: .65rem .8rem;
    cursor: pointer;
}
.tarifa-option:hover { background: rgba(15, 27, 76, .06); }
.tarifa-option span,
.tarifa-empty { color: var(--text-muted); font-size: .78rem; }
.tarifa-option em {
    font-style: normal;
    color: #d97706;
    font-size: .72rem;
    font-weight: 700;
}
.tarifa-empty { padding: .7rem .8rem; }
.tarifa-selected {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    min-height: 38px;
    padding: .45rem .55rem;
    border-radius: 8px;
    background: rgba(15, 27, 76, .06);
    color: var(--text-main);
    font-size: .82rem;
}
.tarifa-selected.is-empty { color: var(--text-muted); background: rgba(107, 114, 128, .08); }
.tarifa-selected .icon-btn { width: 30px; height: 30px; flex: 0 0 auto; }
.worker-picker { display: grid; gap: .5rem; }
.distribution-summary {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    color: var(--text-muted);
    font-size: .84rem;
}
.worker-filter-row {
    display: grid;
    grid-template-columns: minmax(170px, 1fr) minmax(170px, 1fr) auto;
    gap: .5rem;
    align-items: center;
    max-width: 760px;
}
.worker-filter-count {
    color: var(--text-muted);
    font-size: .78rem;
    white-space: nowrap;
}
.btn-mini { padding: .35rem .65rem; font-size: .78rem; }
.worker-tags { display: flex; flex-wrap: wrap; gap: .45rem; min-height: 2rem; }
.worker-tag {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) auto auto auto;
    align-items: center;
    gap: .45rem;
    padding: .45rem .55rem;
    border-radius: 8px;
    background: rgba(15, 27, 76, .08);
    color: var(--text-main);
    font-size: .82rem;
    min-width: 430px;
}
.worker-tag.no-costs {
    grid-template-columns: minmax(180px, 1fr) auto auto;
    min-width: 330px;
}
.worker-main { display: grid; gap: .1rem; min-width: 0; }
.worker-tag small { color: var(--text-muted); font-size: .72rem; }
.percent-control {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    color: var(--text-muted);
    font-size: .75rem;
}
.percent-control input {
    width: 74px;
    padding: .35rem .45rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-card-solid);
    color: var(--text-main);
}
.worker-amount { min-width: 92px; text-align: right; }
.worker-tag button {
    border: 0;
    background: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
}
.worker-search-wrap { position: relative; max-width: 620px; }
.worker-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 280px;
    overflow-y: auto;
    background: var(--surface-card-solid);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    margin-top: 3px;
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
}
.worker-dropdown.is-floating {
    position: fixed;
    right: auto;
    margin-top: 0;
    z-index: 5000;
}
.worker-option { padding: .65rem .8rem; cursor: pointer; display: grid; gap: .15rem; }
.worker-option:hover { background: rgba(15, 27, 76, .06); }
.worker-option em {
    color: var(--text-muted);
    font-size: .72rem;
    font-style: normal;
}
.worker-group {
    padding: .48rem .8rem .25rem;
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: rgba(107, 114, 128, .08);
}
.worker-option small, .worker-empty { color: var(--text-muted); font-size: .78rem; }
.worker-empty { padding: .7rem .8rem; }
@media (max-width: 640px) {
    .tarifa-option { grid-template-columns: 1fr; align-items: start; }
    .worker-filter-row { grid-template-columns: 1fr; max-width: none; }
    .worker-filter-count { white-space: normal; }
    .worker-search-wrap { max-width: none; }
    .worker-tag { width: 100%; min-width: 0; grid-template-columns: 1fr auto; }
    .worker-amount { text-align: left; }
}
</style>
