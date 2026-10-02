{{-- Carta Gantt Scripts --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    updateStats();
    applyActivityFilter();
    switchView(VISTA_INICIAL);

    // CSV file input: toggle placeholder/file info
    const csvInput = document.getElementById('csvFileInput');
    if (csvInput) {
        csvInput.addEventListener('change', function() {
            const placeholder = document.getElementById('csvPlaceholder');
            const info = document.getElementById('csvFileInfo');
            if (this.files && this.files[0]) {
                if (placeholder) placeholder.style.display = 'none';
                if (info) info.style.display = 'flex';
            } else {
                if (placeholder) placeholder.style.display = '';
                if (info) info.style.display = 'none';
            }
        });
    }
});

// ============ CONSTANTS ============
const ANIO = {{ $anioPrograma }};
const ANIO_ACTUAL = new Date().getFullYear();
const MES_ACTUAL = {{ $mesActual }};
const VISTA_INICIAL = @json($vistaInicial);
const MES_INICIAL = {{ $mesVistaInicial }};
const PUEDE_EDITAR = {{ ($puedeEditar ?? false) ? 'true' : 'false' }};
const CURRENT_USER_ID = {{ auth()->id() ?? 0 }};
const USER_HAS_PROGRAM_SCOPE = {{ ($usuarioTieneAlcancePrograma ?? false) ? 'true' : 'false' }};
const MESES = @json($mesesNombres);
const MESES_CORTO = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const actividadesData = @json($actividadesJson);

let currentView = VISTA_INICIAL;
let periodoSem = MES_ACTUAL <= 6 ? 1 : 2;
let periodoMes = MES_INICIAL;
let periodoSemana = 0;
let currentActivityFilter = 'all';

function isPastProgramMonth(mes) {
    return ANIO < ANIO_ACTUAL || (ANIO === ANIO_ACTUAL && mes < MES_ACTUAL);
}

// ============ VIEW SWITCHING (expand/collapse columns) ============
function switchView(view) {
    currentView = view;
    document.querySelectorAll('.sst-view-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-view="'+view+'"]').classList.add('active');

    const periodNav = document.getElementById('periodNav');

    if (view === 'anual') {
        periodNav.style.display = 'none';
    } else {
        periodNav.style.display = 'flex';
    }

    if (view === 'semanal') {
        periodoSemana = Math.min(periodoSemana, Math.max(0, buildSemanasCalendario(periodoMes).length - 1));
    }

    rebuildAllTables();
}

function navigatePeriod(dir) {
    if (currentView === 'semestral') {
        periodoSem = Math.max(1, Math.min(2, periodoSem + dir));
    } else if (currentView === 'mensual') {
        periodoMes = Math.max(1, Math.min(12, periodoMes + dir));
        periodoSemana = 0;
    } else if (currentView === 'semanal') {
        const semanas = buildSemanasCalendario(periodoMes);
        if (dir > 0 && periodoSemana < semanas.length - 1) {
            periodoSemana++;
        } else if (dir < 0 && periodoSemana > 0) {
            periodoSemana--;
        } else if (dir > 0 && periodoMes < 12) {
            periodoMes++;
            periodoSemana = 0;
        } else if (dir < 0 && periodoMes > 1) {
            periodoMes--;
            periodoSemana = buildSemanasCalendario(periodoMes).length - 1;
        }
    }
    rebuildAllTables();
}

function navigateToToday() {
    periodoSem = MES_ACTUAL <= 6 ? 1 : 2;
    periodoMes = MES_ACTUAL;
    periodoSemana = 0;
    rebuildAllTables();
}

// ============ REBUILD TABLE COLUMNS ============
function rebuildAllTables() {
    const label = document.getElementById('periodLabel');
    let columns = [];

    if (currentView === 'anual') {
        columns = buildAnualColumns();
    } else if (currentView === 'semestral') {
        columns = buildSemestralColumns();
        label.textContent = 'Semestre ' + periodoSem + ' (' + MESES[columns[0].mes] + ' – ' + MESES[columns[columns.length-1].mes] + ')';
    } else if (currentView === 'mensual') {
        columns = buildMensualColumns(periodoMes);
        label.textContent = MESES[periodoMes] + ' ' + ANIO;
    } else if (currentView === 'semanal') {
        columns = buildSemanalColumns(periodoMes, periodoSemana);
        const semana = buildSemanasCalendario(periodoMes)[periodoSemana];
        label.textContent = semana ? 'Semana ' + semana.dayStart + '–' + semana.dayEnd + ' · ' + MESES[periodoMes] + ' ' + ANIO : MESES[periodoMes] + ' ' + ANIO;
    }

    // Update each gantt table
    document.querySelectorAll('.sst-gantt').forEach(table => {
        rebuildTableHeaders(table, columns);
        rebuildTableRows(table, columns);
    });

    applyActivityFilter();
}

function buildAnualColumns() {
    const cols = [];
    for (let m = 1; m <= 12; m++) {
        cols.push({ type: 'month', mes: m, label: MESES_CORTO[m], highlight: m === MES_ACTUAL });
    }
    return cols;
}

function buildSemestralColumns() {
    const start = periodoSem === 1 ? 1 : 7;
    const end = periodoSem === 1 ? 6 : 12;
    const cols = [];
    for (let m = start; m <= end; m++) {
        cols.push({ type: 'month', mes: m, label: MESES_CORTO[m], highlight: m === MES_ACTUAL });
    }
    return cols;
}

function buildMensualColumns(mes) {
    return buildSemanasCalendario(mes).map((semana, index) => ({
        type: 'week', mes, weekNum: index + 1,
        ...semana,
        label: 'S' + (index + 1) + ' (' + semana.dayStart + '-' + semana.dayEnd + ')',
        highlight: semana.containsToday,
    }));
}

function buildSemanasCalendario(mes) {
    const lastDay = new Date(ANIO, mes, 0).getDate();
    const today = new Date();
    const weeks = [];
    let start = 1;
    while (start <= lastDay) {
        const dayOfWeek = new Date(ANIO, mes - 1, start).getDay();
        const daysUntilSunday = (7 - dayOfWeek) % 7;
        const end = Math.min(lastDay, start + daysUntilSunday);
        weeks.push({
            dayStart: start,
            dayEnd: end,
            startKey: dateKey(ANIO, mes, start),
            endKey: dateKey(ANIO, mes, end),
            containsToday: today.getFullYear() === ANIO && today.getMonth() + 1 === mes && today.getDate() >= start && today.getDate() <= end,
        });
        start = end + 1;
    }
    return weeks;
}

function dateKey(year, month, day) {
    return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
}

function buildSemanalColumns(mes, semanaIndex) {
    const semana = buildSemanasCalendario(mes)[semanaIndex] || buildSemanasCalendario(mes)[0];
    const dayNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const cols = [];
    if (!semana) return cols;
    for (let d = semana.dayStart; d <= semana.dayEnd; d++) {
        const dow = new Date(ANIO, mes - 1, d).getDay();
        cols.push({
            type: 'day', mes, day: d, dow,
            dateKey: dateKey(ANIO, mes, d),
            label: dayNames[dow] + ' ' + d,
            highlight: semana.containsToday && new Date().getDate() === d
        });
    }
    return cols;
}

function rebuildTableHeaders(table, columns) {
    const thead = table.querySelector('thead tr');
    if (!thead) return;

    // Remove existing time-columns (keep first 4: Actividad, Responsable, Prior., Estado)
    const fixedCols = 4;
    while (thead.children.length > fixedCols + 1) { // +1 for actions column
        thead.removeChild(thead.children[fixedCols]);
    }

    // Insert new columns before the actions column (last th)
    const actionsTh = thead.lastElementChild;
    columns.forEach(col => {
        const th = document.createElement('th');
        th.className = 'sst-th-mes' + (col.highlight ? ' sst-mes-actual' : '');
        th.textContent = col.label;
        th.style.fontSize = currentView === 'semanal' ? '.6rem' : '.7rem';
        th.style.minWidth = currentView === 'semanal' ? '32px' : '38px';
        thead.insertBefore(th, actionsTh);
    });

    // Update colspan for planes de accion rows
    const totalCols = fixedCols + columns.length + 1;
    table.closest('.sst-cat-card')?.querySelectorAll('.sst-planes-row td[colspan]').forEach(td => {
        td.setAttribute('colspan', totalCols);
    });
}

function rebuildTableRows(table, columns) {
    table.querySelectorAll('.sst-act-row').forEach(row => {
        const actId = parseInt(row.dataset.actividadId);
        const actData = actividadesData.find(a => a.id === actId);
        if (!actData) return;
        const fixedCols = 4;
        const tds = Array.from(row.children);
        const actionsTd = tds[tds.length - 1];
        while (row.children.length > fixedCols + 1) {
            row.removeChild(row.children[fixedCols]);
        }

        columns.forEach(col => {
            const td = document.createElement('td');
            td.className = 'sst-td-mes' + (col.highlight ? ' sst-mes-actual' : '');
            td.style.textAlign = 'center';

            const granular = ['DIARIA', 'SEMANAL'].includes(actData.periodicidad);
            const seg = getSeguimientoMes(actData, col.mes);
            const occurrences = (actData.ocurrencias || []);
            let selected = [];
            if (col.type === 'week') {
                selected = occurrences.filter(o => o.fecha_inicio <= col.endKey && o.fecha_fin >= col.startKey);
            } else if (col.type === 'day') {
                selected = occurrences.filter(o => o.fecha_programada === col.dateKey);
            } else {
                selected = occurrences.filter(o => Number((o.fecha_programada || '').slice(5, 7)) === Number(col.mes));
            }

            if (granular && selected.length) {
                if (col.type === 'day' && actData.periodicidad === 'SEMANAL' && selected[0].fecha_programada !== col.dateKey) {
                    row.insertBefore(td, actionsTd);
                    return;
                }
                const done = selected.filter(o => o.realizado).length;
                const total = selected.length;
                const target = done < total ? selected.find(o => !o.realizado) : selected[selected.length - 1];
                td.appendChild(buildOccurrenceControl(actData, selected, done, total, target, col));
            } else if (!granular && seg?.programado) {
                const anchor = actData.fecha_inicio || dateKey(ANIO, col.mes, 1);
                const shouldShow = col.type === 'month' || (col.type === 'week' && col.dayStart === 1) || (col.type === 'day' && col.dateKey === anchor);
                if (shouldShow) {
                    const cantProg = Number(seg.cantidad_programada || actData.cantidad_programada || 1);
                    const cantReal = Number(seg.cantidad_realizada || 0);
                    const done = !!seg.realizado;
                    const control = buildLegacyControl(actData, col, done, cantReal, cantProg);
                    td.appendChild(control);
                }
            }

            row.insertBefore(td, actionsTd);
        });
    });
}

function buildOccurrenceControl(actData, occurrences, done, total, target, col) {
    const complete = done === total;
    const el = document.createElement(PUEDE_EDITAR ? 'button' : 'span');
    el.className = 'gantt-cell ' + (complete ? 'gantt-done' : (done > 0 ? 'gantt-partial' : 'gantt-plan'));
    el.textContent = total > 1 ? done + '/' + total : (complete ? '✓' : '○');
    el.title = actData.nombre + ': ' + done + '/' + total + ' ocurrencia(s) realizada(s).' + (PUEDE_EDITAR ? ' Clic para actualizar.' : ' Solo lectura.');
    el.setAttribute('aria-label', el.title);
    if (PUEDE_EDITAR) el.onclick = () => toggleOcurrencia(actData.id, target.id, el);
    else el.style.cursor = 'default';
    return el;
}

function buildLegacyControl(actData, col, done, cantReal, cantProg) {
    const el = document.createElement(PUEDE_EDITAR ? 'button' : 'span');
    el.className = 'gantt-cell ' + (done ? 'gantt-done' : (cantReal > 0 ? 'gantt-partial' : 'gantt-plan'));
    el.textContent = cantProg > 1 ? (done ? '✓' : cantReal + '/' + cantProg) : (done ? '✓' : '○');
    el.title = actData.nombre + ': ' + (done ? 'realizado' : 'programado') + '.';
    if (PUEDE_EDITAR) el.onclick = () => toggleSeguimiento(actData.id, col.mes, el);
    else el.style.cursor = 'default';
    return el;
}

// ============ ACTIVITY FILTERS ============
function setActivityFilter(filter) {
    currentActivityFilter = filter;
    document.querySelectorAll('.sst-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    applyActivityFilter();
}

function getSelectedActivityMonth() {
    return parseInt(document.getElementById('mesFilterSelect')?.value || selectedStatMonth || MES_ACTUAL);
}

function getActividadData(actId) {
    return actividadesData.find(a => parseInt(a.id) === parseInt(actId));
}

function getSeguimientoMes(actData, mes) {
    return actData?.seguimiento?.[mes] || actData?.seguimiento?.[String(mes)] || null;
}

function actividadTieneVencidos(actData) {
    if (!actData?.seguimiento) return false;
    for (let m = 1; m <= 12; m++) {
        const s = getSeguimientoMes(actData, m);
        const cantidadRealizada = parseInt(s?.cantidad_realizada || 0);
        if (s?.programado && !s?.realizado && isPastProgramMonth(m) && cantidadRealizada === 0) {
            return true;
        }
    }
    return false;
}

function matchesFilter(actData, filter) {
    if (!actData || filter === 'all') return true;

    const mes = getSelectedActivityMonth();
    const seg = getSeguimientoMes(actData, mes);

    if (filter === 'mine') {
        if (USER_HAS_PROGRAM_SCOPE) return true;
        return parseInt(actData.responsable_id || 0) === parseInt(CURRENT_USER_ID);
    }
    if (filter === 'pending') {
        return !!(seg?.programado && !seg?.realizado && parseInt(seg?.cantidad_realizada || 0) === 0);
    }
    if (filter === 'overdue') {
        return actividadTieneVencidos(actData);
    }
    if (filter === 'done') {
        return !!(seg?.programado && seg?.realizado);
    }

    return true;
}

function matchesActivityFilter(actData) {
    return matchesFilter(actData, currentActivityFilter);
}

function updateActivityFilterBadges() {
    const filters = ['all', 'mine', 'pending', 'overdue', 'done'];
    filters.forEach(filter => {
        const count = actividadesData.filter(actData => matchesFilter(actData, filter)).length;
        const badge = document.querySelector('[data-filter-badge="' + filter + '"]');
        if (badge) badge.textContent = count;
    });
}

function closeActivityDetailRows(actId) {
    ['planes-', 'comentarios-', 'reprog-', 'historial-'].forEach(prefix => {
        const row = document.getElementById(prefix + actId);
        if (row) row.style.display = 'none';
    });
}

function applyActivityFilter() {
    updateActivityFilterBadges();

    const rows = Array.from(document.querySelectorAll('.sst-act-row'));
    let visible = 0;

    rows.forEach(row => {
        const actId = parseInt(row.dataset.actividadId);
        const actData = getActividadData(actId);
        const show = matchesActivityFilter(actData);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
        else closeActivityDetailRows(actId);
    });

    document.querySelectorAll('.sst-cat-card').forEach(card => {
        const cardRows = Array.from(card.querySelectorAll('.sst-act-row'));
        const visibleRows = cardRows.filter(row => row.style.display !== 'none');
        const count = card.querySelector('[data-filter-count]');
        card.style.display = visibleRows.length > 0 ? '' : 'none';
        if (count) {
            const pct = card.querySelector('.sst-cat-progress-fill')?.style.width || '0%';
            const pctText = pct.replace('%', '') || '0';
            count.textContent = currentActivityFilter === 'all'
                ? cardRows.length + ' actividades · ' + pctText + '% avance'
                : visibleRows.length + '/' + cardRows.length + ' visibles · ' + pctText + '% avance';
        }
    });

    const info = document.getElementById('activityFilterInfo');
    if (info) {
        const labels = {
            all: 'Mostrando todas las actividades del programa.',
            mine: USER_HAS_PROGRAM_SCOPE ? 'Mostrando el alcance asignado a tu usuario en este programa.' : 'Mostrando solo actividades donde figuras como responsable.',
            pending: 'Mostrando actividades pendientes del mes seleccionado.',
            overdue: 'Mostrando actividades vencidas sin avance.',
            done: 'Mostrando actividades completadas del mes seleccionado.'
        };
        info.textContent = (labels[currentActivityFilter] || labels.all) + ' ' + visible + ' de ' + rows.length + ' visibles.';
    }

    const empty = document.getElementById('activityFilterEmpty');
    if (empty) {
        empty.style.display = rows.length > 0 && visible === 0 ? 'flex' : 'none';
    }
}

// ============ SEGUIMIENTO AJAX ============
function toggleSeguimiento(actId, mes, el) {
    el.style.opacity = '.5';
    el.style.pointerEvents = 'none';
    fetch("{{ url('carta-gantt/actividades') }}/" + actId + "/seguimiento", {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body: JSON.stringify({mes: mes})
    })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        // Update local data
        const actData = actividadesData.find(a => a.id === actId);
        if (actData) {
            if (actData.seguimiento[mes]) {
                actData.seguimiento[mes].realizado = data.realizado;
                actData.seguimiento[mes].cantidad_realizada = (data.cantidad_realizada > 0) ? data.cantidad_realizada : (data.realizado ? 1 : 0);
            }
            if (data.estado) {
                actData.estado = data.estado;
            }
        }
        // Rebuild the current view to reflect changes
        rebuildAllTables();
        updateStats();
        applyActivityFilter();
    })
    .catch(err => { console.error(err); alert('Error al actualizar seguimiento.'); })
    .finally(() => { el.style.opacity = '1'; el.style.pointerEvents = ''; });
}

function toggleOcurrencia(actId, occurrenceId, el) {
    el.style.opacity = '.5';
    el.style.pointerEvents = 'none';
    fetch("{{ url('carta-gantt/actividades') }}/" + actId + "/ocurrencias/" + occurrenceId + "/toggle", {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body: JSON.stringify({})
    })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        const actData = getActividadData(actId);
        if (!actData) return;
        const occurrence = (actData.ocurrencias || []).find(o => Number(o.id) === Number(occurrenceId));
        if (occurrence) occurrence.realizado = !!data.ocurrencia.realizado;
        const month = Number((data.ocurrencia.fecha_programada || '').slice(5, 7));
        if (actData.seguimiento[month]) {
            actData.seguimiento[month] = data.resumen;
        }
        actData.estado = data.estado || actData.estado;
        rebuildAllTables();
        updateStats();
        applyActivityFilter();
    })
    .catch(err => { console.error(err); alert('Error al actualizar la ocurrencia.'); })
    .finally(() => { el.style.opacity = '1'; el.style.pointerEvents = ''; });
}

// ============ PLANES DE ACCIÓN ============
function togglePlanes(actId) {
    const row = document.getElementById('planes-' + actId);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}

function toggleComentarios(actId) {
    const row = document.getElementById('comentarios-' + actId);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}

function scrollToActividad(actId) {
    const row = document.querySelector('[data-actividad-id="' + actId + '"]');
    if (!row) return;

    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.add('sst-row-focus');
    setTimeout(() => row.classList.remove('sst-row-focus'), 1800);
}

function openActividadComentarios(actId) {
    const row = document.getElementById('comentarios-' + actId);
    if (row && row.style.display === 'none') {
        toggleComentarios(actId);
    }

    scrollToActividad(actId);
    setTimeout(() => {
        const target = document.getElementById('comentarios-' + actId);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 220);
}

function toggleReprogramaciones(actId) {
    const row = document.getElementById('reprog-' + actId);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}

function toggleHistorial(actId) {
    const row = document.getElementById('historial-' + actId);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}

function openReprogramar(actId, mesesVencidos) {
    const modal = document.getElementById('reprogramarModal');
    const form = document.getElementById('reprogramarForm');
    const selectOrig = document.getElementById('reprog_mes_original');
    const selectNuevo = document.getElementById('reprog_mes_nuevo');
    const meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const mesActual = ANIO === ANIO_ACTUAL ? MES_ACTUAL : 1;

    form.action = '/carta-gantt/actividades/' + actId + '/reprogramar';

    // Populate mes_original with overdue months
    selectOrig.innerHTML = '<option value="">Seleccione...</option>';
    mesesVencidos.forEach(m => {
        selectOrig.innerHTML += `<option value="${m}">${meses[m]}</option>`;
    });

    // Populate mes_nuevo with valid months for the program year
    selectNuevo.innerHTML = '<option value="">Seleccione...</option>';
    if (ANIO < ANIO_ACTUAL) {
        selectNuevo.innerHTML = '<option value="">Programa de año anterior</option>';
    } else {
        for (let m = mesActual; m <= 12; m++) {
            selectNuevo.innerHTML += `<option value="${m}">${meses[m]}</option>`;
        }
    }

    document.getElementById('reprog_motivo').value = '';
    modal.style.display = 'flex';
}

function closeReprogramar() {
    document.getElementById('reprogramarModal').style.display = 'none';
}

// ============ ADD ACTIVIDAD ============
function toggleAddActividad(catId) {
    const el = document.getElementById('addAct-' + catId);
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}

// ============ ADD CATEGORÍA ============
function toggleAddCat() {
    const el = document.getElementById('addCat');
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}

// ============ EDIT MODAL ============
function openEditModal(row) {
    const data = JSON.parse(row.dataset.act);
    const modal = document.getElementById('editModal');
    const form = document.getElementById('editForm');
    form.action = "{{ url('carta-gantt/actividades') }}/" + data.id;
    document.getElementById('edit-nombre').value = data.nombre || '';
    document.getElementById('edit-descripcion').value = data.descripcion || '';
    document.getElementById('edit-responsable').value = data.responsable_id || '';
    document.getElementById('edit-prioridad').value = data.prioridad || 'MEDIA';
    document.getElementById('edit-estado').value = data.estado || 'PENDIENTE';
    document.getElementById('edit-periodicidad').value = data.periodicidad || '';
    document.getElementById('edit-cantidad').value = data.cantidad_programada || 1;
    document.getElementById('edit-fecha-inicio').value = data.fecha_inicio || '';
    document.getElementById('edit-fecha-fin').value = data.fecha_fin || '';

    // Set month checkboxes
    for (let m = 1; m <= 12; m++) {
        const cb = document.getElementById('edit-mes-' + m);
        if (cb) cb.checked = data.meses_prog && data.meses_prog.includes(m);
    }

    modal.style.display = 'flex';
}

// ============ DETAIL MODAL ============
function openDetail(row) {
    const data = JSON.parse(row.dataset.act);
    const act = actividadesData.find(a => a.id === data.id);
    if (!act) return;

    const priLabels = {ALTA:'Alta',MEDIA:'Media',BAJA:'Baja'};
    const estLabels = {PENDIENTE:'Pendiente',EN_PROGRESO:'En Progreso',COMPLETADA:'Completada',CANCELADA:'Cancelada'};
    const perLabels = {UNICA:'Única',DIARIA:'Diaria',SEMANAL:'Semanal',QUINCENAL:'Quincenal',MENSUAL:'Mensual',BIMENSUAL:'Bimensual',TRIMESTRAL:'Trimestral',SEMESTRAL:'Semestral',ANUAL:'Anual'};

    document.getElementById('detail-title').innerHTML = '<i class="bi bi-info-circle"></i> ' + escHtml(act.nombre);

    let body = '<div class="sst-detail-grid">';
    body += detailItem('Categoría', act.categoria);
    body += detailItem('Responsable', act.responsable || '—');
    body += detailItem('Prioridad', priLabels[act.prioridad] || '—');
    body += detailItem('Estado', estLabels[act.estado] || '—');
    body += detailItem('Periodicidad', perLabels[act.periodicidad] || '—');
    const cantProg = act.cantidad_programada || 1;
    body += detailItem('Cantidad/mes', ['DIARIA', 'SEMANAL'].includes(act.periodicidad) ? 'Derivada de las fechas calendario' : (cantProg > 1 ? cantProg + ' repeticiones' : '1 (estándar)'));
    body += detailItem('Fecha Inicio', act.fecha_inicio || '—');
    body += detailItem('Fecha Fin', act.fecha_fin || '—');
    body += '</div>';

    if (act.descripcion) {
        body += '<div style="margin-bottom:1rem"><span class="sst-label">Descripción</span><p style="font-size:.85rem;margin:.2rem 0">' + escHtml(act.descripcion) + '</p></div>';
    }

    body += '<div style="margin-bottom:.5rem"><span class="sst-label">Seguimiento Mensual</span></div>';
    body += '<div class="sst-seg-grid">';
    for (let m = 1; m <= 12; m++) {
        const s = act.seguimiento[m];
        let cls = 'sst-seg-none';
        let txt = MESES_CORTO[m];
        if (s && s.programado) {
            const cantidadMes = s.cantidad_programada || cantProg;
            const cantReal = s.realizado ? cantidadMes : (s.cantidad_realizada > 0 ? s.cantidad_realizada : 0);
            if (s.realizado) { cls = 'sst-seg-done'; txt += cantidadMes > 1 ? ' ' + cantidadMes+'/'+cantidadMes : ' ✓'; }
            else if (isPastProgramMonth(m)) { cls = 'sst-seg-late'; txt += cantidadMes > 1 ? ' ' + cantReal+'/'+cantidadMes : ' !'; }
            else { cls = 'sst-seg-prog'; txt += cantidadMes > 1 ? ' ' + cantReal+'/'+cantidadMes : ' ○'; }
        }
        body += '<div class="sst-seg-cell ' + cls + '">' + txt + '</div>';
    }
    body += '</div>';

    document.getElementById('detail-body').innerHTML = body;
    document.getElementById('detailModal').style.display = 'flex';
}

function detailItem(label, value) {
    return '<div class="sst-detail-item"><span class="sst-label">' + escHtml(label) + '</span><div class="sst-detail-value">' + escHtml(value) + '</div></div>';
}

// ============ HELPERS ============
function escHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ============ STATS UPDATE ============
let selectedStatMonth = MES_ACTUAL;

function filterByMonth(mes) {
    selectedStatMonth = parseInt(mes);
    periodoMes = selectedStatMonth;
    periodoSemana = 0;
    if (currentView === 'mensual' || currentView === 'semanal') rebuildAllTables();
    updateStats();
    applyActivityFilter();
}

function updateStats() {
    let progTotal = 0, realTotal = 0;
    let mesProgTotal = 0, mesRealTotal = 0;
    let completadas = 0, enProgreso = 0, vencidosMes = 0, pendientesMes = 0;
    let reprogMes = 0;
    const mesFiltro = selectedStatMonth;

    // Helper: get realized quantity
    // If realizado=true, always count as fully done (cantProg)
    function getCantReal(s, cantProg) {
        if (s.realizado) return cantProg;
        return s.cantidad_realizada > 0 ? s.cantidad_realizada : 0;
    }

    actividadesData.forEach(a => {
        for (let m = 1; m <= 12; m++) {
            const s = a.seguimiento[m];
            if (s && s.programado) {
                const cantProg = s.cantidad_programada || a.cantidad_programada || 1;
                const cantReal = getCantReal(s, cantProg);
                progTotal += cantProg;
                realTotal += cantReal;
                if (!s.realizado && (ANIO < ANIO_ACTUAL || (ANIO === ANIO_ACTUAL && m < mesFiltro))) { vencidosMes++; }
                // Selected month stats
                if (m === mesFiltro) {
                    mesProgTotal += cantProg;
                    mesRealTotal += cantReal;
                    if (s.realizado) { completadas++; }
                    else if (s.cantidad_realizada > 0) { enProgreso++; }
                    else { pendientesMes++; }
                }
            }
        }
        // Count reprogramaciones related to the selected month
        if (a.reprogramaciones) {
            a.reprogramaciones.forEach(r => {
                if (r.mes_original === mesFiltro || r.mes_nuevo === mesFiltro) reprogMes++;
            });
        }
    });

    const pct = progTotal > 0 ? Math.round(realTotal / progTotal * 100) : 0;
    const mesPct = mesProgTotal > 0 ? Math.round(mesRealTotal / mesProgTotal * 100) : 0;

    // Global progress
    const bar = document.getElementById('progressBar');
    const num = document.getElementById('progressNum');
    if (bar) bar.style.width = pct + '%';
    if (num) num.textContent = pct + '%';

    // Month progress
    const mBar = document.getElementById('monthProgressBar');
    const mNum = document.getElementById('monthProgressNum');
    const lblAvMes = document.getElementById('labelAvanceMes');
    if (mBar) mBar.style.width = mesPct + '%';
    if (mNum) mNum.textContent = mesPct + '%';
    if (lblAvMes) lblAvMes.textContent = 'Avance ' + MESES[mesFiltro];

    // Stat cards
    const mesLabel = MESES_CORTO[mesFiltro];
    const elComp = document.getElementById('statCompletadas');
    const elProg = document.getElementById('statEnProgreso');
    const elVenc = document.getElementById('statVencidas');
    const elPend = document.getElementById('statPendientes');
    const elReprog = document.getElementById('statReprogramaciones');
    const lblComp = document.getElementById('labelCompletadas');
    const lblProg = document.getElementById('labelEnProgreso');
    const lblPend = document.getElementById('labelPendientes');
    const lblReprog = document.getElementById('labelReprogramaciones');
    if (elComp) elComp.textContent = completadas;
    if (elProg) elProg.textContent = enProgreso;
    if (elVenc) elVenc.textContent = vencidosMes;
    if (elPend) elPend.textContent = pendientesMes;
    if (elReprog) elReprog.textContent = reprogMes;
    if (lblComp) lblComp.textContent = 'Completadas ' + mesLabel;
    if (lblProg) lblProg.textContent = 'En Progreso ' + mesLabel;
    if (lblPend) lblPend.textContent = 'Pendientes ' + mesLabel;
    if (lblReprog) lblReprog.textContent = 'Reprog. ' + mesLabel;

    // Update category progress bars
    document.querySelectorAll('.sst-cat-card').forEach(card => {
        let catProg = 0, catReal = 0;
        card.querySelectorAll('.sst-act-row').forEach(row => {
            const actId = parseInt(row.dataset.actividadId);
            const actData = actividadesData.find(a => a.id === actId);
            if (!actData) return;
            const cantProg = actData.cantidad_programada || 1;
            for (let m = 1; m <= 12; m++) {
                const s = actData.seguimiento[m];
                if (s && s.programado) {
                    catProg += cantProg;
                    catReal += getCantReal(s, cantProg);
                }
            }
        });
        const catPct = catProg > 0 ? Math.round(catReal / catProg * 100) : 0;
        const catFill = card.querySelector('.sst-cat-progress-fill');
        if (catFill) catFill.style.width = catPct + '%';
        const catInfo = card.querySelector('[data-filter-count]');
        if (catInfo) {
            const count = card.querySelectorAll('.sst-act-row').length;
            catInfo.textContent = count + ' actividades · ' + catPct + '% avance';
        }
    });
    applyActivityFilter();
}
</script>
