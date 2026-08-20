@extends('layouts.app')
@section('title','Programación Contenedores')
@section('content')
@php
    $supervisorActual = $supervisorSistema ?? auth()->user();
    $supervisorActualNombre = $supervisorActual?->nombre_completo ?: $supervisorActual?->name;
    $supervisorActualMeta = collect([
        $supervisorActual?->cargo?->nombre,
        $supervisorActual?->centroCosto?->nombre,
    ])->filter()->implode(' · ');
@endphp
<div class="page-container">
    <div class="page-header">
        <div>
            <h2 class="page-heading">Programación</h2>
            <p class="page-subheading">Carga rápida desde tablas copiadas de correo o Excel.</p>
        </div>
        <a href="{{ route('descarga-contenedores.index') }}" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @include('partials._alerts')
    @include('descarga_contenedores._nav')
    @include('descarga_contenedores._context_help', [
        'title' => 'Uso de programación',
        'items' => [
            'Pega la tabla tal como llega por correo o desde Excel; la vista previa corrige FACT, duplicados y centro antes de guardar.',
            'WALMART se asocia a tarifas WM y SMU a tarifas SMU. Si el código tiene más de un proceso, hay que elegir.',
            'Por defecto no se vuelven a guardar contenedores que ya existen en la misma fecha.',
            'Participantes base aplica el mismo equipo a todas las filas. Sin eso, quedan pendientes de cuadrilla.',
        ],
    ])

    <form method="POST" action="{{ route('descarga-contenedores.store-bulk') }}" id="bulk-form">
        @csrf
        <input type="hidden" name="registros_json" id="registros_json">

        <div class="glass-card" style="margin-bottom:1rem">
            <div class="quick-grid">
                <div>
                    <div class="form-group">
                        <label>Nombre de la carga @include('descarga_contenedores._help_icon', ['text' => 'Nombre interno para rastrear esta tanda en el historial de cargas.'])</label>
                        <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" placeholder="Ej: Programación P07 Peñón turno AM">
                    </div>
                    <div class="form-group">
                        <label>Tabla copiada desde correo o Excel @include('descarga_contenedores._help_icon', ['text' => 'Puedes pegar columnas separadas por tabulador, punto y coma o coma. Si trae encabezado, se omitirá automáticamente.'])</label>
                        <textarea id="paste_source" name="paste_source" class="form-control paste-area" rows="10" placeholder="Pega aquí columnas tipo: Operación, Bodega, Supervisor, Facturación, Fecha, Contenedor, Equipo, H.Cita...">{{ old('paste_source') }}</textarea>
                    </div>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <button type="button" class="btn-premium" id="preview-btn">
                            <i class="bi bi-table"></i> Generar vista previa
                        </button>
                        <button type="button" class="btn-secondary" id="clear-btn">
                            <i class="bi bi-eraser"></i> Limpiar
                        </button>
                    </div>
                </div>

                <div>
                    <h4 class="section-title">Supervisor sistema @include('descarga_contenedores._help_icon', ['text' => 'Se toma desde el login para saber quién creó la carga masiva.'])</h4>
                    <input type="text" class="form-control readonly-control" value="{{ $supervisorActualNombre ?: 'Se asignará por login' }}" readonly>
                    <p class="helper-text" style="margin-top:.5rem">{{ $supervisorActualMeta ?: 'Se completa automáticamente con el usuario autenticado.' }}</p>

                    <h4 class="section-title">Participantes base @include('descarga_contenedores._help_icon', ['text' => 'Equipo que se copiará a cada fila de la vista previa. Luego puedes editar fila por fila.'])</h4>
                    <p class="helper-text">Filtra centro/cargo, agrega los trabajadores disponibles como equipo base y aplícalos a todas las filas. La participación se reparte en partes iguales; puedes editar un registro después si requiere porcentajes especiales.</p>
                    <input type="hidden" id="base_participantes_json" value="[]">
                    <div id="base_worker_picker" class="worker-picker compact"></div>
                    <button type="button" class="btn-secondary" id="apply-base-btn" style="margin-top:.75rem">
                        <i class="bi bi-people"></i> Aplicar a todas las filas
                    </button>

                    <h4 class="section-title" style="margin-top:1.5rem">Mapeo esperado @include('descarga_contenedores._help_icon', ['text' => 'Orden de columnas usado para convertir la tabla pegada en registros.'])</h4>
                    <div class="mapping-list">
                        <span>A Operación</span>
                        <span>B Bodega</span>
                        <span>C Supervisor</span>
                        <span>D Facturación</span>
                        <span>E Fecha</span>
                        <span>F Contenedor</span>
                        <span>G Equipo</span>
                        <span>H Hora cita</span>
                        <span>I Inicio descarga</span>
                        <span>J Término descarga</span>
                        <span>K Item</span>
                        <span>L Cajas</span>
                        <span>M Pallets</span>
                        <span>N Productos</span>
                        <span>O Observación</span>
                        <span>U FACT.</span>
                    </div>
                    <p class="helper-text" style="margin-top:.75rem">Si el correo trae encabezados, se mapean por nombre (FACT, Contenedor, Fecha…). Año, Mes, Semana, Día y Dato se guardan como referencia y ya no ocupan la vista previa.</p>
                </div>
            </div>
        </div>

        <div class="glass-card" id="preview-card" style="display:none">
            <div class="preview-toolbar">
                <div>
        <h3 style="margin:0;font-size:1.1rem">Vista previa editable @include('descarga_contenedores._help_icon', ['text' => 'Revisa FACT, duplicados y equipo antes de guardar. Nada se crea hasta presionar Guardar registros.'])</h3>
                    <p class="helper-text" id="preview-count" style="margin:.2rem 0 0"></p>
                </div>
                <div class="preview-toolbar-actions">
                    <label class="preview-skip-dup">
                        <input type="checkbox" name="omitir_duplicados" value="1" id="omitir_duplicados" checked>
                        <span>Omitir duplicados ya cargados</span>
                    </label>
                    <button type="submit" class="btn-premium">
                        <i class="bi bi-save"></i> Guardar registros
                    </button>
                </div>
            </div>

            <div class="preview-wrap">
                <table class="data-table preview-table">
                    <colgroup>
                        <col class="col-status">
                        <col class="col-short">
                        <col class="col-wide">
                        <col class="col-wide">
                        <col class="col-short">
                        <col class="col-date">
                        <col class="col-wide">
                        <col class="col-short">
                        <col class="col-time">
                        <col class="col-time">
                        <col class="col-time">
                        <col class="col-small">
                        <col class="col-number">
                        <col class="col-number">
                        <col class="col-text">
                        <col class="col-text">
                        <col class="col-fact">
                        <col class="col-workers">
                        <col class="col-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Operación</th>
                            <th>Bodega</th>
                            <th>Sup. Equipo</th>
                            <th>Facturación</th>
                            <th>Fecha</th>
                            <th>Contenedor</th>
                            <th>E.Descarga</th>
                            <th>H.Cita</th>
                            <th>Hi Descarga</th>
                            <th>Ht Descarga</th>
                            <th>Item</th>
                            <th>Cajas</th>
                            <th>Pallets</th>
                            <th>Productos</th>
                            <th>Observación</th>
                            <th>FACT.</th>
                            <th>Trabajadores</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="preview-body"></tbody>
                </table>
            </div>
        </div>
    </form>
</div>

@php
    $tarifasData = $tarifas->map(fn($t) => [
        'id' => $t->id,
        'cliente' => $t->cliente,
        'centro_costo_id' => $t->centro_costo_id,
        'centro' => $t->centroCosto?->nombre,
        'codigo' => $t->codigo,
        'proceso' => $t->proceso,
        'requiere_revision' => $t->requiere_revision,
    ])->values();
@endphp
<script type="application/json" id="trabajadores_data">@json($trabajadores)</script>
<script type="application/json" id="centros_data">@json($centros->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values())</script>
<script type="application/json" id="tarifas_data">@json($tarifasData)</script>
<script type="application/json" id="existing_container_dates">@json(array_keys($existingContainerDates ?? []))</script>
<script>
const workers = JSON.parse(document.getElementById('trabajadores_data').textContent || '[]');
const centers = JSON.parse(document.getElementById('centros_data').textContent || '[]');
const tarifas = JSON.parse(document.getElementById('tarifas_data').textContent || '[]');
const existingContainerDates = new Set(JSON.parse(document.getElementById('existing_container_dates').textContent || '[]'));
const byWorkerId = new Map(workers.map(w => [String(w.id), w]));
const byTarifaId = new Map(tarifas.map(t => [String(t.id), t]));

function normalizeText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function normalizeRut(value) {
    return String(value || '').toUpperCase().replace(/[^0-9K]/g, '');
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

function cleanFactCode(value) {
    return String(value || '').trim().toUpperCase();
}

function tarifaLabel(tarifa) {
    return `${tarifa.codigo || ''} · ${tarifa.cliente || ''} · ${tarifa.centro || 'General'} · ${tarifa.proceso || ''}`;
}

function tarifasByCode(code, centerId = '') {
    const clean = cleanFactCode(code);
    return tarifas.filter(t => {
        if (cleanFactCode(t.codigo) !== clean) return false;
        return !centerId || !t.centro_costo_id || String(t.centro_costo_id) === String(centerId);
    });
}

function clienteFromOperacion(operacion) {
    const text = normalizeText(operacion);
    if (!text) return '';
    if (text.includes('smu') || text.includes('unimarc')) return 'SMU';
    if (text.includes('walmart') || text === 'wm' || text.startsWith('wm ')) return 'WM';
    return '';
}

function uniqueTarifaByCode(code, centerId = '', cliente = '') {
    let matches = tarifasByCode(code, centerId);
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

const legacyColumns = [
    'operacion',
    'bodega',
    'supervisor_nombre',
    'facturacion_mes',
    'fecha',
    'contenedor',
    'equipo_descarga',
    'hora_cita',
    'hora_inicio_descarga',
    'hora_termino_descarga',
    'item',
    'cajas',
    'pallets',
    'producto',
    'observacion',
    'fact_codigo',
];

const emailScheduleColumns = [
    'operacion',
    'bodega',
    'supervisor_nombre',
    'facturacion_mes',
    'fecha',
    'contenedor',
    'equipo_descarga',
    'hora_cita',
    'hora_inicio_descarga',
    'hora_termino_descarga',
    'item',
    'cajas',
    'pallets',
    'producto',
    'observacion',
    'anio',
    'mes',
    'semana',
    'dia',
    'dato',
    'fact_codigo',
];

const headerAliases = {
    operacion: 'operacion',
    bodega: 'bodega',
    supequipo: 'supervisor_nombre',
    supervisor: 'supervisor_nombre',
    supervisorencargado: 'supervisor_nombre',
    facturacion: 'facturacion_mes',
    facturacionmes: 'facturacion_mes',
    mesfacturacion: 'facturacion_mes',
    fecha: 'fecha',
    contenedor: 'contenedor',
    edescarga: 'equipo_descarga',
    equipodescarga: 'equipo_descarga',
    hdescarga: 'equipo_descarga',
    hcita: 'hora_cita',
    horacita: 'hora_cita',
    hicitacion: 'hora_cita',
    hidescarga: 'hora_inicio_descarga',
    inicio: 'hora_inicio_descarga',
    iniciodescarga: 'hora_inicio_descarga',
    horainicio: 'hora_inicio_descarga',
    htdescarga: 'hora_termino_descarga',
    termino: 'hora_termino_descarga',
    terminodescarga: 'hora_termino_descarga',
    horatermino: 'hora_termino_descarga',
    item: 'item',
    items: 'item',
    cajas: 'cajas',
    pallets: 'pallets',
    producto: 'producto',
    productos: 'producto',
    observacion: 'observacion',
    obs: 'observacion',
    ano: 'anio',
    anio: 'anio',
    mes: 'mes',
    semana: 'semana',
    dia: 'dia',
    dato: 'dato',
    fact: 'fact_codigo',
    factcodigo: 'fact_codigo',
    codigofact: 'fact_codigo',
};

function splitRow(line) {
    if (line.includes('\t')) return line.split('\t');
    if (line.includes(';')) return line.split(';');
    return line.split(',');
}

function normalizeHeader(value) {
    return normalizeText(value).replace(/[^a-z0-9]/g, '');
}

function buildHeaderIndexes(row) {
    return row.reduce((indexes, cell, index) => {
        const key = headerAliases[normalizeHeader(cell)];
        if (key && indexes[key] === undefined) {
            indexes[key] = index;
        }
        return indexes;
    }, {});
}

function hasRecognizableHeader(row) {
    const indexes = buildHeaderIndexes(row);
    const mappedCount = Object.keys(indexes).length;
    return mappedCount >= 4 && (
        indexes.fecha !== undefined
        || indexes.contenedor !== undefined
        || indexes.operacion !== undefined
        || indexes.fact_codigo !== undefined
    );
}

function makeEmptyImportRow() {
    return { estado: 'borrador', participantes: [] };
}

function parseRowByHeaders(row, indexes) {
    const item = makeEmptyImportRow();

    Object.entries(indexes).forEach(([key, index]) => {
        item[key] = row[index] || '';
    });

    return item;
}

function parseRowByPosition(row) {
    const item = makeEmptyImportRow();
    const columns = row.length >= emailScheduleColumns.length ? emailScheduleColumns : legacyColumns;

    columns.forEach((key, index) => {
        if (!key) return;
        item[key] = row[index] || '';
    });

    return item;
}

function parsePastedTable(text) {
    const rows = text.replace(/\r/g, '').split('\n')
        .map(line => splitRow(line).map(cell => cell.trim()))
        .filter(row => row.some(cell => cell !== ''));

    if (!rows.length) return [];

    const hasHeader = hasRecognizableHeader(rows[0]);
    const headerIndexes = hasHeader ? buildHeaderIndexes(rows[0]) : {};
    const dataRows = hasHeader ? rows.slice(1) : rows;

    return dataRows.map(row => {
        const item = hasHeader
            ? parseRowByHeaders(row, headerIndexes)
            : parseRowByPosition(row);

        item.centro_costo_id = inferCenterId(item.bodega);
        const tarifa = uniqueTarifaByCode(
            item.fact_codigo,
            item.centro_costo_id,
            clienteFromOperacion(item.operacion)
        );
        if (tarifa) {
            item.tarifa_id = String(tarifa.id);
            item.fact_codigo = tarifa.codigo;
        }
        return item;
    });
}

function inferCenterId(bodega) {
    const text = (bodega || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (!text) return '';

    const match = centers.find(center => {
        const name = (center.nombre || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return text.includes(name) || name.includes(text);
    });

    return match ? match.id : '';
}

function initWorkerPicker(container, hiddenInput, initialIds, onChange) {
    const selected = new Map();
    container.innerHTML = `
        <div class="worker-tags"></div>
        <div>
            <button type="button" class="btn-secondary btn-mini" data-add-filtered>
                <i class="bi bi-person-plus"></i> Agregar filtrados
            </button>
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
            <input type="text" class="form-control worker-search" autocomplete="off" placeholder="Buscar trabajador...">
            <div class="worker-dropdown"></div>
        </div>
    `;

    const tags = container.querySelector('.worker-tags');
    const input = container.querySelector('.worker-search');
    const dropdown = container.querySelector('.worker-dropdown');
    const centerFilter = container.querySelector('.worker-center-filter');
    const cargoFilter = container.querySelector('.worker-cargo-filter');
    const countEl = container.querySelector('[data-worker-count]');
    const addFilteredBtn = container.querySelector('[data-add-filtered]');

    const centerOptions = [...new Map(workers.map(worker => [
        workerCenterKey(worker),
        worker.centro || 'Sin centro',
    ])).entries()]
        .sort((a, b) => a[1].localeCompare(b[1], 'es'));
    centerOptions.forEach(([value, label]) => {
        centerFilter.insertAdjacentHTML('beforeend', `<option value="${escapeAttr(value)}">${escapeAttr(label)}</option>`);
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
            cargoFilter.insertAdjacentHTML('beforeend', `<option value="${escapeAttr(value)}">${escapeAttr(label)}</option>`);
        });
        cargoFilter.value = options.some(([value]) => value === currentCargo) ? currentCargo : '';
    }

    refreshCargoOptions();

    (initialIds || []).forEach(id => {
        const worker = byWorkerId.get(String(id));
        if (worker) selected.set(String(id), worker);
    });

    function sync() {
        const ids = [...selected.keys()].map(id => parseInt(id, 10));
        hiddenInput.value = JSON.stringify(ids);
        if (onChange) onChange(ids);
        tags.innerHTML = '';
        selected.forEach((worker, id) => {
            const tag = document.createElement('span');
            tag.className = 'worker-tag';
            tag.innerHTML = `<span>${worker.label}</span><small>${worker.centro || 'Sin centro'}</small><button type="button" title="Quitar">&times;</button>`;
            tag.querySelector('button').addEventListener('click', () => {
                selected.delete(id);
                sync();
            });
            tags.appendChild(tag);
        });
    }

    function availableWorkers(query = '') {
        const q = normalizeText(query);
        const rutQuery = normalizeRut(query);
        const centerValue = centerFilter.value;
        const cargoValue = cargoFilter.value;

        return workers.filter(w => {
            if (selected.has(String(w.id))) return false;
            if (centerValue && workerCenterKey(w) !== centerValue) return false;
            if (cargoValue && workerCargoKey(w) !== cargoValue) return false;

            return !q
                || normalizeText(w.label).includes(q)
                || normalizeText(w.rut).includes(q)
                || normalizeRut(w.rut).includes(rutQuery)
                || normalizeText(w.cargo).includes(q)
                || normalizeText(w.centro).includes(q)
                || normalizeText(w.centro_talana).includes(q);
        });
    }

    function render(query = '') {
        const available = availableWorkers(query);
        const matches = available
            .sort((a, b) => `${a.centro || ''} ${a.cargo || ''} ${a.label || ''}`.localeCompare(`${b.centro || ''} ${b.cargo || ''} ${b.label || ''}`, 'es'))
            .slice(0, 70);
        countEl.textContent = `${available.length} disponible${available.length === 1 ? '' : 's'}`;

        if (matches.length) {
            let currentGroup = '';
            dropdown.innerHTML = matches.map(w => {
                const group = `${w.centro || 'Sin centro'} · ${w.cargo || 'Sin cargo'}`;
                const heading = group !== currentGroup
                    ? `<div class="worker-group">${escapeAttr(group)}</div>`
                    : '';
                currentGroup = group;

                return `
                    ${heading}
                    <div class="worker-option" data-id="${w.id}" role="button" tabindex="0">
                        <strong>${escapeAttr(w.label)}</strong>
                        <small>${escapeAttr(w.rut || '')}${w.cargo ? ' · ' + escapeAttr(w.cargo) : ''}</small>
                        <em>${escapeAttr(w.centro || 'Sin centro')}</em>
                    </div>
                `;
            }).join('');
        } else {
            dropdown.innerHTML = '<div class="worker-empty">Sin resultados para ese centro/cargo</div>';
        }
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.worker-option').forEach(option => {
            const selectOption = event => {
                event.preventDefault();
                if (dropdown.style.display === 'none') return;

                const worker = byWorkerId.get(String(option.dataset.id));
                if (worker) {
                    selected.set(String(worker.id), worker);
                    input.value = '';
                    dropdown.style.display = 'none';
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
    input.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 150));
    centerFilter.addEventListener('change', () => {
        refreshCargoOptions();
        render(input.value.trim());
    });
    cargoFilter.addEventListener('change', () => render(input.value.trim()));
    addFilteredBtn.addEventListener('click', () => {
        const ids = availableWorkers(input.value.trim()).map(worker => worker.id);
        if (!ids.length) {
            showToast('No hay trabajadores disponibles para agregar con ese filtro.', 'warning');
            return;
        }

        ids.forEach(id => {
            const worker = byWorkerId.get(String(id));
            if (worker) selected.set(String(worker.id), worker);
        });
        input.value = '';
        dropdown.style.display = 'none';
        sync();
    });
    sync();

    return {
        set(ids) {
            selected.clear();
            (ids || []).forEach(id => {
                const worker = byWorkerId.get(String(id));
                if (worker) selected.set(String(id), worker);
            });
            sync();
        },
        get() {
            return [...selected.keys()].map(id => parseInt(id, 10));
        }
    };
}

function initTarifaPicker(container, tarifaInput, factInput) {
    const search = container.querySelector('.bulk-fact-search');
    const dropdown = container.querySelector('.bulk-fact-dropdown');
    const selectedText = container.querySelector('[data-fact-selected]');
    const row = container.closest('tr');
    const rowCenterInput = row?.querySelector('[data-key="centro_costo_id"]');
    const rowOperacionInput = row?.querySelector('[data-key="operacion"]');

    function rowCliente() {
        return clienteFromOperacion(rowOperacionInput?.value || '');
    }

    function tarifaMatchesRowCenter(tarifa) {
        const centerId = rowCenterInput?.value || '';
        const cliente = rowCliente();
        if (centerId && tarifa.centro_costo_id && String(tarifa.centro_costo_id) !== String(centerId)) {
            return false;
        }
        if (cliente && String(tarifa.cliente || '').toUpperCase() !== cliente) {
            return false;
        }
        return true;
    }

    function setSelection(tarifa = null, manualCode = '') {
        if (tarifa) {
            tarifaInput.value = String(tarifa.id);
            factInput.value = cleanFactCode(tarifa.codigo);
            search.value = tarifaLabel(tarifa);
            const label = `${tarifa.codigo} · ${tarifa.cliente} · ${tarifa.centro || 'General'} · ${tarifa.proceso}${tarifa.requiere_revision ? ' · Revisar' : ''}`;
            search.title = label;
            selectedText.title = label;
            selectedText.innerHTML = `<strong>${escapeAttr(tarifa.codigo)}</strong> · ${escapeAttr(tarifa.cliente)} · ${escapeAttr(tarifa.centro || 'General')} · ${escapeAttr(tarifa.proceso)}${tarifa.requiere_revision ? ' <span class="badge warning">Revisar</span>' : ''}`;
            refreshPreviewHealth();
            return;
        }

        const code = cleanFactCode(manualCode);
        tarifaInput.value = '';
        factInput.value = code;

        if (!code) {
            search.title = '';
            selectedText.textContent = 'Sin FACT';
            selectedText.title = 'Sin FACT';
            refreshPreviewHealth();
            return;
        }

        const auto = uniqueTarifaByCode(code, rowCenterInput?.value || '', rowCliente());
        if (auto) {
            setSelection(auto);
            return;
        }

        const matches = tarifasByCode(code, rowCenterInput?.value || '').filter(t => !rowCliente() || String(t.cliente || '').toUpperCase() === rowCliente());
        const status = matches.length > 1
            ? `${code}: código repetido, selecciona proceso`
            : `${code}: pendiente de tarifa`;
        search.title = status;
        selectedText.textContent = status;
        selectedText.title = status;
        refreshPreviewHealth();
    }

    function positionDropdown() {
        if (dropdown.parentElement !== document.body) {
            document.body.appendChild(dropdown);
        }

        const rect = search.getBoundingClientRect();
        const viewportPadding = 12;
        const gap = 6;
        const width = Math.min(520, window.innerWidth - viewportPadding * 2);
        const left = Math.min(
            Math.max(viewportPadding, rect.left),
            Math.max(viewportPadding, window.innerWidth - width - viewportPadding)
        );
        const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
        const spaceAbove = rect.top - viewportPadding;
        const openUp = spaceBelow < 260 && spaceAbove > spaceBelow;
        const maxHeight = Math.max(180, Math.min(360, (openUp ? spaceAbove : spaceBelow) - gap));

        dropdown.classList.add('is-floating');
        dropdown.style.width = `${width}px`;
        dropdown.style.left = `${left}px`;
        dropdown.style.right = 'auto';
        dropdown.style.maxHeight = `${maxHeight}px`;
        dropdown.style.top = openUp ? 'auto' : `${rect.bottom + gap}px`;
        dropdown.style.bottom = openUp ? `${window.innerHeight - rect.top + gap}px` : 'auto';
    }

    function showDropdown() {
        closeFactDropdowns(dropdown);
        dropdown.style.display = 'block';
        positionDropdown();
    }

    function hideDropdown() {
        dropdown.style.display = 'none';
    }

    function render(query = '') {
        const q = normalizeText(query);
        const matches = tarifas.filter(t => {
            const haystack = normalizeText([t.codigo, t.cliente, t.centro, t.proceso].join(' '));
            return tarifaMatchesRowCenter(t) && (!q || haystack.includes(q));
        }).slice(0, 60);

        dropdown.innerHTML = matches.length
            ? matches.map(t => `
                <div class="bulk-fact-option" data-id="${escapeAttr(t.id)}" role="button" tabindex="0">
                    <strong>${escapeAttr(t.codigo)}</strong>
                    <small>${escapeAttr(t.cliente)} · ${escapeAttr(t.centro || 'General')} · ${escapeAttr(t.proceso)}</small>
                    ${t.requiere_revision ? '<em>Revisar</em>' : ''}
                </div>
            `).join('')
            : '<div class="worker-empty">Sin resultados. Puedes dejar el código para revisión.</div>';
        showDropdown();

        dropdown.querySelectorAll('.bulk-fact-option').forEach(option => {
            const selectOption = event => {
                event.preventDefault();
                if (dropdown.style.display === 'none') return;

                const tarifa = byTarifaId.get(String(option.dataset.id));
                if (!tarifa) return;
                setSelection(tarifa);
                hideDropdown();
            };

            option.addEventListener('mousedown', selectOption);
            option.addEventListener('click', selectOption);
            option.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') selectOption(event);
            });
        });
    }

    search.addEventListener('focus', () => render(search.value));
    search.addEventListener('input', () => {
        setSelection(null, search.value);
        render(search.value);
    });
    search.addEventListener('blur', () => setTimeout(hideDropdown, 150));

    const initialTarifa = byTarifaId.get(String(tarifaInput.value || ''));
    if (initialTarifa) {
        setSelection(initialTarifa);
        return;
    }

    const uniqueTarifa = uniqueTarifaByCode(factInput.value, rowCenterInput?.value || '', rowCliente());
    if (uniqueTarifa) {
        setSelection(uniqueTarifa);
        return;
    }

    setSelection(null, factInput.value);
}

const rowPickers = new Map();
let basePicker;

function previewInput(key, row, placeholder = '', className = 'mini-input') {
    const value = row[key] || '';
    return `<input class="form-control ${className}" data-key="${escapeAttr(key)}" value="${escapeAttr(value)}" title="${escapeAttr(value)}" placeholder="${escapeAttr(placeholder)}">`;
}

function hiddenPreview(key, row) {
    return `<input type="hidden" data-key="${escapeAttr(key)}" value="${escapeAttr(row[key] || '')}">`;
}

function normalizeDateKey(value) {
    const text = String(value || '').trim();
    if (!text) return '';
    const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
    const dmy = text.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/);
    if (dmy) return `${dmy[3]}-${String(dmy[2]).padStart(2, '0')}-${String(dmy[1]).padStart(2, '0')}`;
    return text;
}

function containerDateKey(contenedor, fecha) {
    const name = String(contenedor || '').trim().toUpperCase();
    const date = normalizeDateKey(fecha);
    if (!name || !date) return '';
    return `${name}|${date}`;
}

function refreshPreviewHealth() {
    const rows = [...document.querySelectorAll('#preview-body tr')];
    const seen = {};
    let withTarifa = 0;
    let pendingFact = 0;
    let duplicates = 0;
    let withoutTeam = 0;

    rows.forEach(tr => {
        const contenedor = tr.querySelector('[data-key="contenedor"]')?.value || '';
        const fecha = tr.querySelector('[data-key="fecha"]')?.value || '';
        const tarifaId = tr.querySelector('[data-key="tarifa_id"]')?.value || '';
        const fact = tr.querySelector('[data-key="fact_codigo"]')?.value || '';
        const workersCount = (() => {
            try { return JSON.parse(tr.querySelector('.row-participants')?.value || '[]').length; }
            catch (e) { return 0; }
        })();
        const key = containerDateKey(contenedor, fecha);
        const isDup = key && (existingContainerDates.has(key) || seen[key]);
        if (key) seen[key] = (seen[key] || 0) + 1;

        if (tarifaId) withTarifa += 1;
        else if (fact) pendingFact += 1;
        if (isDup) duplicates += 1;
        if (!workersCount) withoutTeam += 1;

        const badge = tr.querySelector('[data-row-status]');
        tr.classList.toggle('is-duplicate', !!isDup);
        if (!badge) return;
        badge.className = 'badge';
        if (isDup) {
            badge.classList.add('danger');
            badge.textContent = 'Duplicado';
        } else if (tarifaId) {
            badge.classList.add('success');
            badge.textContent = 'FACT listo';
        } else if (fact) {
            badge.classList.add('warning');
            badge.textContent = 'Revisar FACT';
        } else {
            badge.classList.add('warning');
            badge.textContent = 'Borrador';
        }
    });

    const count = rows.length;
    const parts = [`${count} fila${count === 1 ? '' : 's'}`];
    if (withTarifa) parts.push(`${withTarifa} con FACT`);
    if (pendingFact) parts.push(`${pendingFact} por elegir proceso`);
    if (duplicates) parts.push(`${duplicates} duplicado${duplicates === 1 ? '' : 's'}`);
    if (withoutTeam) parts.push(`${withoutTeam} sin equipo`);
    const el = document.getElementById('preview-count');
    if (el) el.textContent = count ? parts.join(' · ') : 'Sin filas';
}

function renderPreview(rows) {
    const body = document.getElementById('preview-body');
    body.innerHTML = '';
    rowPickers.clear();

    rows.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.dataset.index = index;
        tr.innerHTML = `
            <td>
                <span class="badge warning" data-row-status>Borrador</span>
                <input type="hidden" data-key="estado" value="borrador">
            </td>
            <td>${previewInput('operacion', row)}</td>
            <td>
                ${previewInput('bodega', row, '', 'mini-input wide-input')}
                <input type="hidden" data-key="centro_costo_id" value="${escapeAttr(row.centro_costo_id)}">
            </td>
            <td>${previewInput('supervisor_nombre', row, '', 'mini-input wide-input')}</td>
            <td>${previewInput('facturacion_mes', row)}</td>
            <td>${previewInput('fecha', row, 'dd/mm/aaaa')}</td>
            <td>${previewInput('contenedor', row, '', 'mini-input wide-input')}</td>
            <td>${previewInput('equipo_descarga', row)}</td>
            <td>${previewInput('hora_cita', row, 'hh:mm', 'mini-input time-input')}</td>
            <td>${previewInput('hora_inicio_descarga', row, 'hh:mm', 'mini-input time-input')}</td>
            <td>${previewInput('hora_termino_descarga', row, 'hh:mm', 'mini-input time-input')}</td>
            <td>${previewInput('item', row, '', 'mini-input number-input')}</td>
            <td>${previewInput('cajas', row, '', 'mini-input number-input')}</td>
            <td>${previewInput('pallets', row, '', 'mini-input number-input')}</td>
            <td>${previewInput('producto', row, '', 'mini-input xwide-input')}</td>
            <td>${previewInput('observacion', row, '', 'mini-input xwide-input')}</td>
            <td>
                ${hiddenPreview('anio', row)}
                ${hiddenPreview('mes', row)}
                ${hiddenPreview('semana', row)}
                ${hiddenPreview('dia', row)}
                ${hiddenPreview('dato', row)}
                <input type="hidden" data-key="tarifa_id" value="${escapeAttr(row.tarifa_id || '')}">
                <input type="hidden" data-key="fact_codigo" value="${escapeAttr(row.fact_codigo)}">
                <div class="bulk-fact-picker">
                    <input class="form-control mini-input bulk-fact-search" value="${escapeAttr(row.fact_codigo)}" placeholder="Buscar FACT...">
                    <div class="bulk-fact-selected" data-fact-selected>Sin FACT</div>
                    <div class="bulk-fact-dropdown"></div>
                </div>
            </td>
            <td>
                <input type="hidden" class="row-participants" value="[]">
                <div class="row-worker-compact">
                    <span class="row-worker-summary" data-worker-summary>Sin trabajadores</span>
                    <button type="button" class="btn-secondary btn-mini row-worker-toggle" data-worker-toggle>
                        <i class="bi bi-people"></i> Editar
                    </button>
                    <div class="row-worker-editor" data-worker-editor>
                        <div class="row-worker-picker worker-picker compact"></div>
                    </div>
                </div>
            </td>
            <td>
                <button type="button" class="icon-btn danger remove-row" title="Quitar fila"><i class="bi bi-x-lg"></i></button>
            </td>
        `;
        body.appendChild(tr);

        const hidden = tr.querySelector('.row-participants');
        const summary = tr.querySelector('[data-worker-summary]');
        const editor = tr.querySelector('[data-worker-editor]');
        const toggle = tr.querySelector('[data-worker-toggle]');
        const updateWorkerSummary = ids => {
            const total = (ids || []).length;
            summary.textContent = total ? `${total} trabajador${total === 1 ? '' : 'es'}` : 'Sin trabajadores';
            summary.classList.toggle('has-workers', total > 0);
        };
        const closeOtherWorkerEditors = () => {
            closeWorkerEditors(editor);
        };

        const picker = initWorkerPicker(tr.querySelector('.row-worker-picker'), hidden, row.participantes || [], ids => {
            tr.dataset.participantes = JSON.stringify(ids);
            updateWorkerSummary(ids);
            refreshPreviewHealth();
        });
        updateWorkerSummary(picker.get());
        toggle.addEventListener('click', () => {
            closeOtherWorkerEditors();
            editor.classList.toggle('is-open');
        });
        const factDropdown = tr.querySelector('.bulk-fact-dropdown');
        initTarifaPicker(
            tr.querySelector('.bulk-fact-picker'),
            tr.querySelector('[data-key="tarifa_id"]'),
            tr.querySelector('[data-key="fact_codigo"]')
        );
        tr._workerPicker = picker;
        rowPickers.set(index, picker);

        tr.querySelector('.remove-row').addEventListener('click', () => {
            factDropdown?.remove();
            tr.remove();
            refreshPreviewHealth();
        });
        ['fecha', 'contenedor', 'operacion'].forEach(key => {
            tr.querySelector(`[data-key="${key}"]`)?.addEventListener('change', refreshPreviewHealth);
        });
    });

    document.getElementById('preview-card').style.display = rows.length ? 'block' : 'none';
    refreshPreviewHealth();
}

function refreshCount() {
    refreshPreviewHealth();
}

function closeWorkerEditors(except = null) {
    document.querySelectorAll('.row-worker-editor.is-open').forEach(editor => {
        if (editor !== except) editor.classList.remove('is-open');
    });
}

function closeFactDropdowns(except = null) {
    document.querySelectorAll('.bulk-fact-dropdown').forEach(dropdown => {
        if (dropdown !== except) dropdown.style.display = 'none';
    });
}

function collectRows() {
    return [...document.querySelectorAll('#preview-body tr')].map(tr => {
        const row = {};
        tr.querySelectorAll('[data-key]').forEach(input => row[input.dataset.key] = input.value.trim());
        try {
            row.participantes = JSON.parse(tr.querySelector('.row-participants').value || '[]');
        } catch (e) {
            row.participantes = [];
        }
        return row;
    });
}

function escapeAttr(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function notifyUser(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
        return;
    }

    window.alert(message);
}

document.addEventListener('DOMContentLoaded', () => {
    const previewBtn = document.getElementById('preview-btn');
    const pasteSource = document.getElementById('paste_source');
    const clearBtn = document.getElementById('clear-btn');
    const applyBaseBtn = document.getElementById('apply-base-btn');
    const bulkForm = document.getElementById('bulk-form');
    const basePickerEl = document.getElementById('base_worker_picker');
    const baseInput = document.getElementById('base_participantes_json');
    const previewBody = document.getElementById('preview-body');
    const previewCard = document.getElementById('preview-card');
    const previewWrap = document.querySelector('.preview-wrap');
    const registrosInput = document.getElementById('registros_json');

    if (previewBtn && pasteSource) {
        previewBtn.addEventListener('click', () => {
            try {
                const rows = parsePastedTable(pasteSource.value);
                if (!rows.length) {
                    notifyUser('No encontré filas para previsualizar.', 'warning');
                    return;
                }
                renderPreview(rows);
                previewCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                console.error('Error generando vista previa de contenedores:', error);
                notifyUser('No pude generar la vista previa. Revisa que la tabla venga con columnas separadas desde Excel o correo.', 'error');
            }
        });
    }

    if (basePickerEl && baseInput) {
        try {
            basePicker = initWorkerPicker(basePickerEl, baseInput, [], null);
        } catch (error) {
            console.error('Error inicializando participantes base:', error);
            notifyUser('No pude inicializar el selector de participantes base, pero puedes generar la vista previa igual.', 'warning');
        }
    }

    clearBtn?.addEventListener('click', () => {
        if (pasteSource) pasteSource.value = '';
        if (previewBody) previewBody.innerHTML = '';
        if (previewCard) previewCard.style.display = 'none';
        rowPickers.clear();
    });

    applyBaseBtn?.addEventListener('click', () => {
        const ids = basePicker?.get() || [];
        if (!ids.length) {
            notifyUser('Selecciona trabajadores base antes de aplicar.', 'warning');
            return;
        }
        document.querySelectorAll('#preview-body tr').forEach(tr => {
            if (tr._workerPicker) tr._workerPicker.set(ids);
        });
        refreshPreviewHealth();
        notifyUser('Participantes base aplicados a la vista previa.', 'success');
    });

    bulkForm?.addEventListener('submit', event => {
        const rows = collectRows();
        if (!rows.length) {
            event.preventDefault();
            notifyUser('No hay filas para guardar.', 'warning');
            return;
        }
        if (registrosInput) registrosInput.value = JSON.stringify(rows);
    });

    previewWrap?.addEventListener('scroll', () => {
        closeWorkerEditors();
        closeFactDropdowns();
    }, { passive: true });
    window.addEventListener('scroll', () => closeFactDropdowns(), { passive: true });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeWorkerEditors();
            closeFactDropdowns();
        }
    });
});
</script>

<style>
.quick-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(320px, .8fr); gap: 1.5rem; align-items: start; }
.paste-area { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; min-height: 230px; }
.helper-text { color: var(--text-muted); font-size: .86rem; line-height: 1.45; margin: 0 0 .75rem; }
.readonly-control { background: var(--surface-bg); color: var(--text-main); cursor: default; }
.section-title {
    margin: 0 0 .75rem;
    color: var(--text-muted);
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--surface-border);
}
.mapping-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .4rem; }
.mapping-list span {
    padding: .45rem .55rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    color: var(--text-main);
    font-size: .8rem;
}
.preview-toolbar {
    position: sticky;
    top: .5rem;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .65rem;
    padding: .55rem .6rem;
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    background: var(--surface-card-solid);
    box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
    flex-wrap: wrap;
}
.preview-wrap {
    overflow: auto;
    max-height: min(64vh, 680px);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
}
.preview-toolbar-actions {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}
.preview-skip-dup {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    margin: 0;
    font-size: .8rem;
    font-weight: 700;
    color: var(--text-muted);
    white-space: nowrap;
}
.preview-table {
    min-width: 1560px;
    table-layout: fixed;
}
.preview-table .col-status { width: 92px; }
.preview-table .col-short { width: 82px; }
.preview-table .col-wide { width: 112px; }
.preview-table .col-date { width: 86px; }
.preview-table .col-time { width: 64px; }
.preview-table .col-small { width: 48px; }
.preview-table .col-number { width: 64px; }
.preview-table .col-text { width: 148px; }
.preview-table .col-fact { width: 138px; }
.preview-table .col-workers { width: 150px; }
.preview-table .col-action { width: 38px; }
.preview-table th,
.preview-table td {
    padding: .3rem .34rem;
    vertical-align: top;
}
.preview-table thead th {
    position: sticky;
    top: 0;
    z-index: 8;
    background: var(--surface-card-solid);
    white-space: nowrap;
}
.preview-table tbody tr {
    scroll-margin-top: 42px;
}
.preview-table tbody tr.is-duplicate {
    background: rgba(185, 28, 28, .06);
}
.preview-table .badge {
    padding: .28rem .45rem;
    font-size: .68rem;
    white-space: nowrap;
}
.mini-input {
    width: 100%;
    min-width: 0;
    padding: .3rem .4rem;
    font-size: .74rem;
    line-height: 1.15;
    box-sizing: border-box;
}
.bulk-fact-picker { position: relative; min-width: 0; display: grid; gap: .12rem; }
.bulk-fact-search { width: 100%; }
.bulk-fact-selected {
    color: var(--text-muted);
    font-size: .68rem;
    line-height: 1.15;
    max-height: 1.2em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bulk-fact-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: 38px;
    z-index: 1000;
    max-height: 250px;
    overflow-y: auto;
    background: var(--surface-card-solid);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
}
.bulk-fact-dropdown.is-floating {
    position: fixed;
    right: auto;
    z-index: 2500;
}
.bulk-fact-option {
    display: grid;
    grid-template-columns: 70px minmax(0, 1fr) auto;
    align-items: start;
    gap: .5rem;
    padding: .48rem .6rem;
    cursor: pointer;
}
.bulk-fact-option:hover { background: rgba(15, 27, 76, .06); }
.bulk-fact-option strong {
    font-size: .78rem;
    line-height: 1.2;
}
.bulk-fact-option small {
    color: var(--text-muted);
    font-size: .73rem;
    line-height: 1.25;
    white-space: normal;
    overflow-wrap: anywhere;
}
.bulk-fact-option em {
    color: #d97706;
    font-size: .68rem;
    font-style: normal;
    font-weight: 700;
}
.row-worker-compact {
    position: relative;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: .35rem;
}
.row-worker-summary {
    min-width: 82px;
    color: var(--text-muted);
    font-size: .72rem;
    white-space: nowrap;
}
.row-worker-summary.has-workers { color: var(--text-main); font-weight: 700; }
.row-worker-editor {
    display: none;
    position: absolute;
    top: calc(100% + 5px);
    right: 0;
    width: min(520px, 78vw);
    z-index: 30;
    padding: .75rem;
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    background: var(--surface-card-solid);
    box-shadow: 0 16px 40px rgba(15, 23, 42, .18);
}
.row-worker-editor.is-open { display: block; }
.worker-picker { display: grid; gap: .45rem; }
.worker-picker.compact .worker-search-wrap { max-width: none; }
.worker-picker.compact .worker-tags { max-width: none; }
.worker-picker.compact .worker-filter-row { max-width: none; }
.btn-mini { padding: .35rem .65rem; font-size: .78rem; }
.worker-filter-row {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) minmax(140px, 1fr) auto;
    gap: .4rem;
    align-items: center;
}
.worker-filter-row .form-control { padding: .45rem .55rem; font-size: .78rem; }
.worker-filter-count {
    color: var(--text-muted);
    font-size: .72rem;
    white-space: nowrap;
}
.worker-tags { display: flex; flex-wrap: wrap; gap: .35rem; min-height: 1.8rem; }
.worker-tag {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .25rem .48rem;
    border-radius: 999px;
    background: rgba(15, 27, 76, .08);
    color: var(--text-main);
    font-size: .74rem;
}
.worker-tag small { color: var(--text-muted); font-size: .68rem; }
.worker-tag button {
    border: 0;
    background: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: .9rem;
    line-height: 1;
}
.worker-search-wrap { position: relative; }
.worker-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 250px;
    overflow-y: auto;
    background: var(--surface-card-solid);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    margin-top: 3px;
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
}
.worker-option { padding: .55rem .7rem; cursor: pointer; display: grid; gap: .15rem; }
.worker-option:hover { background: rgba(15, 27, 76, .06); }
.worker-option em {
    color: var(--text-muted);
    font-size: .7rem;
    font-style: normal;
}
.worker-group {
    padding: .42rem .7rem .22rem;
    color: var(--text-muted);
    font-size: .66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: rgba(107, 114, 128, .08);
}
.worker-option small, .worker-empty { color: var(--text-muted); font-size: .76rem; }
.worker-empty { padding: .65rem .75rem; }
@media (max-width: 900px) {
    .quick-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .worker-filter-row { grid-template-columns: 1fr; }
    .worker-filter-count { white-space: normal; }
}
</style>
@endsection
