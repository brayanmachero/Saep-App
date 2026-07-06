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
            'Pega la tabla tal como llega por correo o desde Excel; la vista previa permite corregir antes de guardar.',
            'Cada fila se guarda como borrador para evitar validar información incompleta.',
            'Participantes base aplica el mismo equipo a todas las filas, útil cuando una cuadrilla descarga varios contenedores.',
            'Si el FACT viene repetido o no existe, el registro queda marcado para revisión de tarifa.',
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
                    <p class="helper-text">Selecciona un equipo base desde la nómina Talana de los centros gestionados en los Excel y aplícalo a todas las filas. La participación se reparte en partes iguales; puedes editar un registro después si requiere porcentajes especiales.</p>
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
                    </div>
                    <p class="helper-text" style="margin-top:.75rem">Si vienen más columnas, se cargan inicio, término, ítems, cajas, pallets, producto, observación y FACT.</p>
                </div>
            </div>
        </div>

        <div class="glass-card" id="preview-card" style="display:none">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
                <div>
        <h3 style="margin:0;font-size:1.1rem">Vista previa editable @include('descarga_contenedores._help_icon', ['text' => 'Revisa y corrige datos antes de guardar. Nada se crea hasta presionar Guardar registros.'])</h3>
                    <p class="helper-text" id="preview-count" style="margin:.2rem 0 0"></p>
                </div>
                <button type="submit" class="btn-premium">
                    <i class="bi bi-save"></i> Guardar registros
                </button>
            </div>

            <div class="preview-wrap">
                <table class="data-table preview-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Contenedor</th>
                            <th>Bodega</th>
                            <th>Equipo</th>
                            <th>Cajas</th>
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
        'codigo' => $t->codigo,
        'proceso' => $t->proceso,
        'requiere_revision' => $t->requiere_revision,
    ])->values();
@endphp
<script type="application/json" id="trabajadores_data">@json($trabajadores)</script>
<script type="application/json" id="centros_data">@json($centros->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values())</script>
<script type="application/json" id="tarifas_data">@json($tarifasData)</script>
<script>
const workers = JSON.parse(document.getElementById('trabajadores_data').textContent || '[]');
const centers = JSON.parse(document.getElementById('centros_data').textContent || '[]');
const tarifas = JSON.parse(document.getElementById('tarifas_data').textContent || '[]');
const byWorkerId = new Map(workers.map(w => [String(w.id), w]));
const byTarifaId = new Map(tarifas.map(t => [String(t.id), t]));

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

function cleanFactCode(value) {
    return String(value || '').trim().toUpperCase();
}

function tarifaLabel(tarifa) {
    return `${tarifa.codigo || ''} · ${tarifa.cliente || ''} · ${tarifa.proceso || ''}`;
}

function tarifasByCode(code) {
    const clean = cleanFactCode(code);
    return tarifas.filter(t => cleanFactCode(t.codigo) === clean);
}

function uniqueTarifaByCode(code) {
    const matches = tarifasByCode(code);
    return matches.length === 1 ? matches[0] : null;
}

const columns = [
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

function splitRow(line) {
    if (line.includes('\t')) return line.split('\t');
    if (line.includes(';')) return line.split(';');
    return line.split(',');
}

function parsePastedTable(text) {
    const rows = text.replace(/\r/g, '').split('\n')
        .map(line => splitRow(line).map(cell => cell.trim()))
        .filter(row => row.some(cell => cell !== ''));

    if (!rows.length) return [];

    const first = rows[0].map(cell => cell.toLowerCase()).join(' ');
    const hasHeader = first.includes('fecha') || first.includes('contenedor') || first.includes('operaci');
    const dataRows = hasHeader ? rows.slice(1) : rows;

    return dataRows.map(row => {
        const item = { estado: 'borrador', participantes: [] };
        columns.forEach((key, index) => item[key] = row[index] || '');
        item.centro_costo_id = inferCenterId(item.bodega);
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

    function setSelection(tarifa = null, manualCode = '') {
        if (tarifa) {
            tarifaInput.value = String(tarifa.id);
            factInput.value = cleanFactCode(tarifa.codigo);
            search.value = tarifaLabel(tarifa);
            selectedText.innerHTML = `<strong>${escapeAttr(tarifa.codigo)}</strong> · ${escapeAttr(tarifa.cliente)} · ${escapeAttr(tarifa.proceso)}${tarifa.requiere_revision ? ' <span class="badge warning">Revisar</span>' : ''}`;
            return;
        }

        const code = cleanFactCode(manualCode);
        tarifaInput.value = '';
        factInput.value = code;

        if (!code) {
            selectedText.textContent = 'Sin FACT';
            return;
        }

        const matches = tarifasByCode(code);
        selectedText.textContent = matches.length > 1
            ? `${code}: código repetido, selecciona proceso`
            : `${code}: pendiente de tarifa`;
    }

    function render(query = '') {
        const q = normalizeText(query);
        const matches = tarifas.filter(t => {
            const haystack = normalizeText([t.codigo, t.cliente, t.proceso].join(' '));
            return !q || haystack.includes(q);
        }).slice(0, 60);

        dropdown.innerHTML = matches.length
            ? matches.map(t => `
                <div class="bulk-fact-option" data-id="${escapeAttr(t.id)}" role="button" tabindex="0">
                    <strong>${escapeAttr(t.codigo)}</strong>
                    <small>${escapeAttr(t.cliente)} · ${escapeAttr(t.proceso)}</small>
                    ${t.requiere_revision ? '<em>Revisar</em>' : ''}
                </div>
            `).join('')
            : '<div class="worker-empty">Sin resultados. Puedes dejar el código para revisión.</div>';
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.bulk-fact-option').forEach(option => {
            const selectOption = event => {
                event.preventDefault();
                if (dropdown.style.display === 'none') return;

                const tarifa = byTarifaId.get(String(option.dataset.id));
                if (!tarifa) return;
                setSelection(tarifa);
                dropdown.style.display = 'none';
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
    search.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 150));

    const initialTarifa = byTarifaId.get(String(tarifaInput.value || ''));
    if (initialTarifa) {
        setSelection(initialTarifa);
        return;
    }

    const uniqueTarifa = uniqueTarifaByCode(factInput.value);
    if (uniqueTarifa) {
        setSelection(uniqueTarifa);
        return;
    }

    setSelection(null, factInput.value);
}

const rowPickers = new Map();
let basePicker;

function renderPreview(rows) {
    const body = document.getElementById('preview-body');
    body.innerHTML = '';
    rowPickers.clear();

    rows.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.dataset.index = index;
        tr.innerHTML = `
            <td>
                <span class="badge warning">Borrador</span>
                <input type="hidden" data-key="estado" value="borrador">
            </td>
            <td><input class="form-control mini-input" data-key="fecha" value="${escapeAttr(row.fecha)}" placeholder="dd/mm/aaaa"></td>
            <td><input class="form-control mini-input" data-key="contenedor" value="${escapeAttr(row.contenedor)}"></td>
            <td>
                <input class="form-control mini-input" data-key="bodega" value="${escapeAttr(row.bodega)}">
                <input type="hidden" data-key="operacion" value="${escapeAttr(row.operacion)}">
                <input type="hidden" data-key="supervisor_nombre" value="${escapeAttr(row.supervisor_nombre)}">
                <input type="hidden" data-key="facturacion_mes" value="${escapeAttr(row.facturacion_mes)}">
                <input type="hidden" data-key="centro_costo_id" value="${escapeAttr(row.centro_costo_id)}">
                <input type="hidden" data-key="hora_cita" value="${escapeAttr(row.hora_cita)}">
                <input type="hidden" data-key="hora_inicio_descarga" value="${escapeAttr(row.hora_inicio_descarga)}">
                <input type="hidden" data-key="hora_termino_descarga" value="${escapeAttr(row.hora_termino_descarga)}">
                <input type="hidden" data-key="item" value="${escapeAttr(row.item)}">
                <input type="hidden" data-key="pallets" value="${escapeAttr(row.pallets)}">
                <input type="hidden" data-key="producto" value="${escapeAttr(row.producto)}">
                <input type="hidden" data-key="observacion" value="${escapeAttr(row.observacion)}">
            </td>
            <td><input class="form-control mini-input" data-key="equipo_descarga" value="${escapeAttr(row.equipo_descarga)}"></td>
            <td><input class="form-control mini-input" data-key="cajas" value="${escapeAttr(row.cajas)}"></td>
            <td>
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
                <div class="row-worker-picker worker-picker compact"></div>
            </td>
            <td>
                <button type="button" class="icon-btn danger remove-row" title="Quitar fila"><i class="bi bi-x-lg"></i></button>
            </td>
        `;
        body.appendChild(tr);

        const hidden = tr.querySelector('.row-participants');
        const picker = initWorkerPicker(tr.querySelector('.row-worker-picker'), hidden, row.participantes || [], ids => {
            tr.dataset.participantes = JSON.stringify(ids);
        });
        initTarifaPicker(
            tr.querySelector('.bulk-fact-picker'),
            tr.querySelector('[data-key="tarifa_id"]'),
            tr.querySelector('[data-key="fact_codigo"]')
        );
        tr._workerPicker = picker;
        rowPickers.set(index, picker);

        tr.querySelector('.remove-row').addEventListener('click', () => {
            tr.remove();
            refreshCount();
        });
    });

    document.getElementById('preview-card').style.display = rows.length ? 'block' : 'none';
    refreshCount();
}

function refreshCount() {
    const count = document.querySelectorAll('#preview-body tr').length;
    document.getElementById('preview-count').textContent = count ? `${count} filas listas para guardar` : 'Sin filas';
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

document.addEventListener('DOMContentLoaded', () => {
    basePicker = initWorkerPicker(
        document.getElementById('base_worker_picker'),
        document.getElementById('base_participantes_json'),
        [],
        null
    );

    document.getElementById('preview-btn').addEventListener('click', () => {
        const rows = parsePastedTable(document.getElementById('paste_source').value);
        if (!rows.length) {
            showToast('No encontré filas para previsualizar.', 'warning');
            return;
        }
        renderPreview(rows);
    });

    document.getElementById('clear-btn').addEventListener('click', () => {
        document.getElementById('paste_source').value = '';
        document.getElementById('preview-body').innerHTML = '';
        document.getElementById('preview-card').style.display = 'none';
        rowPickers.clear();
    });

    document.getElementById('apply-base-btn').addEventListener('click', () => {
        const ids = basePicker.get();
        if (!ids.length) {
            showToast('Selecciona trabajadores base antes de aplicar.', 'warning');
            return;
        }
        document.querySelectorAll('#preview-body tr').forEach(tr => {
            if (tr._workerPicker) tr._workerPicker.set(ids);
        });
        showToast('Participantes base aplicados a la vista previa.', 'success');
    });

    document.getElementById('bulk-form').addEventListener('submit', event => {
        const rows = collectRows();
        if (!rows.length) {
            event.preventDefault();
            showToast('No hay filas para guardar.', 'warning');
            return;
        }
        document.getElementById('registros_json').value = JSON.stringify(rows);
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
.preview-wrap { overflow-x: auto; }
.preview-table { min-width: 1180px; }
.mini-input { min-width: 110px; padding: .5rem .65rem; font-size: .82rem; }
.bulk-fact-picker { position: relative; min-width: 220px; display: grid; gap: .25rem; }
.bulk-fact-search { width: 100%; }
.bulk-fact-selected {
    color: var(--text-muted);
    font-size: .72rem;
    line-height: 1.25;
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
.bulk-fact-option {
    display: grid;
    grid-template-columns: 72px 1fr auto;
    align-items: center;
    gap: .45rem;
    padding: .55rem .7rem;
    cursor: pointer;
}
.bulk-fact-option:hover { background: rgba(15, 27, 76, .06); }
.bulk-fact-option small { color: var(--text-muted); font-size: .74rem; }
.bulk-fact-option em {
    color: #d97706;
    font-size: .68rem;
    font-style: normal;
    font-weight: 700;
}
.worker-picker { display: grid; gap: .45rem; }
.worker-picker.compact .worker-search-wrap { max-width: 360px; }
.worker-picker.compact .worker-tags { max-width: 420px; }
.worker-picker.compact .worker-filter-row { max-width: 420px; }
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
