{{-- Carta Gantt Scripts --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    updateStats();
});

// ============ CONSTANTS ============
const ANIO = {{ $anioPrograma }};
const MES_ACTUAL = {{ $mesActual }};
const MESES = @json($mesesNombres);
const MESES_CORTO = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const actividadesData = @json($actividadesJson);

let currentView = 'anual';
let periodoSem = MES_ACTUAL <= 6 ? 1 : 2;
let periodoMes = MES_ACTUAL;

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

    rebuildAllTables();
}

function navigatePeriod(dir) {
    if (currentView === 'semestral') {
        periodoSem = Math.max(1, Math.min(2, periodoSem + dir));
    } else if (currentView === 'mensual') {
        periodoMes = Math.max(1, Math.min(12, periodoMes + dir));
    } else if (currentView === 'semanal') {
        // Move by month (each semanal view shows one full month)
        periodoMes = Math.max(1, Math.min(12, periodoMes + dir));
    }
    rebuildAllTables();
}

function navigateToToday() {
    periodoSem = MES_ACTUAL <= 6 ? 1 : 2;
    periodoMes = MES_ACTUAL;
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
        columns = buildSemanalColumns(periodoMes);
        label.textContent = MESES[periodoMes] + ' ' + ANIO + ' — Vista diaria';
    }

    // Update each gantt table
    document.querySelectorAll('.sst-gantt').forEach(table => {
        rebuildTableHeaders(table, columns);
        rebuildTableRows(table, columns);
    });
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
    // Expand a month into its weeks (Sem 1, Sem 2, Sem 3, Sem 4, Sem 5)
    const daysInMonth = new Date(ANIO, mes, 0).getDate();
    const cols = [];
    const today = new Date();
    const isCurrentMonth = today.getFullYear() === ANIO && (today.getMonth() + 1) === mes;
    const todayDay = isCurrentMonth ? today.getDate() : -1;

    let weekNum = 1;
    let weekStart = 1;
    while (weekStart <= daysInMonth) {
        const weekEnd = Math.min(weekStart + 6, daysInMonth);
        const containsToday = todayDay >= weekStart && todayDay <= weekEnd;
        cols.push({
            type: 'week',
            mes: mes,
            weekNum: weekNum,
            dayStart: weekStart,
            dayEnd: weekEnd,
            label: 'S' + weekNum + ' (' + weekStart + '-' + weekEnd + ')',
            highlight: containsToday
        });
        weekStart = weekEnd + 1;
        weekNum++;
    }
    return cols;
}

function buildSemanalColumns(mes) {
    // Expand a month into individual days
    const daysInMonth = new Date(ANIO, mes, 0).getDate();
    const dayNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    const cols = [];
    const today = new Date();
    const isCurrentMonth = today.getFullYear() === ANIO && (today.getMonth() + 1) === mes;
    const todayDay = isCurrentMonth ? today.getDate() : -1;

    for (let d = 1; d <= daysInMonth; d++) {
        const dow = new Date(ANIO, mes - 1, d).getDay();
        cols.push({
            type: 'day',
            mes: mes,
            day: d,
            dow: dow,
            label: dayNames[dow] + ' ' + d,
            highlight: d === todayDay
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

        // Remove existing time-cells (keep first 4 td + actions td at end)
        const fixedCols = 4;
        const tds = Array.from(row.children);
        const actionsTd = tds[tds.length - 1];

        // Remove all time columns
        while (row.children.length > fixedCols + 1) {
            row.removeChild(row.children[fixedCols]);
        }

        // Insert new cells before actions
        columns.forEach(col => {
            const td = document.createElement('td');
            td.className = 'sst-td-mes' + (col.highlight ? ' sst-mes-actual' : '');
            td.style.textAlign = 'center';

            const seg = actData.seguimiento[col.mes];
            const prog = seg && seg.programado;
            const real = seg && seg.realizado;
            const cantProg = actData.cantidad_programada || 1;
            const cantReal = (seg && seg.cantidad_realizada) ? seg.cantidad_realizada : 0;
            const parcial = prog && !real && cantReal > 0;

            if (col.type === 'month' && prog) {
                const vencido = !real && col.mes < MES_ACTUAL;
                const btn = document.createElement('button');
                if (cantProg > 1) {
                    btn.className = 'gantt-cell ' + (real ? 'gantt-done' : (vencido ? 'gantt-overdue' : (parcial ? 'gantt-partial' : 'gantt-plan')));
                    btn.textContent = real ? '✓' : (cantReal > 0 ? cantReal+'/'+cantProg : '0/'+cantProg);
                    btn.title = cantReal+'/'+cantProg + ' — clic para ' + (real ? 'resetear' : 'avanzar');
                } else {
                    btn.className = 'gantt-cell ' + (real ? 'gantt-done' : (vencido ? 'gantt-overdue' : 'gantt-plan'));
                    btn.textContent = real ? '✓' : (vencido ? '!' : '○');
                    btn.title = real ? 'Realizado' : (vencido ? 'Vencido — clic para marcar' : 'Programado — clic para marcar');
                }
                btn.onclick = function() { toggleSeguimiento(actId, col.mes, btn); };
                td.appendChild(btn);
            } else if (col.type === 'week' && prog) {
                const vencido = !real && col.mes < MES_ACTUAL;
                const btn = document.createElement('button');
                if (cantProg > 1) {
                    btn.className = 'gantt-cell ' + (real ? 'gantt-done' : (vencido ? 'gantt-overdue' : (parcial ? 'gantt-partial' : 'gantt-plan')));
                    btn.textContent = real ? '✓' : (cantReal > 0 ? cantReal+'/'+cantProg : '0/'+cantProg);
                } else {
                    btn.className = 'gantt-cell ' + (real ? 'gantt-done' : (vencido ? 'gantt-overdue' : 'gantt-plan'));
                    btn.textContent = real ? '✓' : (vencido ? '!' : '○');
                }
                btn.title = real ? 'Realizado' : (vencido ? 'Vencido — clic para marcar' : 'Programado — clic para marcar');
                btn.onclick = function() { toggleSeguimiento(actId, col.mes, btn); };
                td.appendChild(btn);
            } else if (col.type === 'day' && prog) {
                const vencido = !real && col.mes < MES_ACTUAL;
                const dot = document.createElement('span');
                dot.style.cssText = 'display:inline-block;width:10px;height:10px;border-radius:50%;cursor:pointer;';
                dot.style.background = real ? '#10b981' : (vencido ? '#ef4444' : (parcial ? '#f59e0b' : '#6366f1'));
                dot.title = cantProg > 1 ? (cantReal+'/'+cantProg) : (real ? 'Realizado' : (vencido ? 'Vencido' : 'Programado'));
                dot.onclick = function() { toggleSeguimiento(actId, col.mes, dot); };
                td.appendChild(dot);
            }

            row.insertBefore(td, actionsTd);
        });
    });
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
                actData.seguimiento[mes].cantidad_realizada = data.cantidad_realizada ?? (data.realizado ? 1 : 0);
            }
            if (data.estado) {
                actData.estado = data.estado;
            }
        }
        // Rebuild the current view to reflect changes
        rebuildAllTables();
        updateStats();
    })
    .catch(err => { console.error(err); alert('Error al actualizar seguimiento.'); })
    .finally(() => { el.style.opacity = '1'; el.style.pointerEvents = ''; });
}

// ============ PLANES DE ACCIÓN ============
function togglePlanes(actId) {
    const row = document.getElementById('planes-' + actId);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
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
    body += detailItem('Cantidad/mes', cantProg > 1 ? cantProg + ' repeticiones' : '1 (estándar)');
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
            const cantReal = s.cantidad_realizada ?? (s.realizado ? cantProg : 0);
            if (s.realizado) { cls = 'sst-seg-done'; txt += cantProg > 1 ? ' ' + cantReal+'/'+cantProg : ' ✓'; }
            else if (m < MES_ACTUAL) { cls = 'sst-seg-late'; txt += cantProg > 1 ? ' ' + cantReal+'/'+cantProg : ' !'; }
            else { cls = 'sst-seg-prog'; txt += cantProg > 1 ? ' ' + cantReal+'/'+cantProg : ' ○'; }
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
function updateStats() {
    let progTotal = 0, realTotal = 0;
    let mesProgTotal = 0, mesRealTotal = 0;
    let completadas = 0, enProgreso = 0, vencidosMes = 0;

    actividadesData.forEach(a => {
        let actProg = 0, actReal = 0;
        const cantProg = a.cantidad_programada || 1;
        for (let m = 1; m <= 12; m++) {
            const s = a.seguimiento[m];
            if (s && s.programado) {
                progTotal += cantProg; actProg += cantProg;
                const cantReal = s.cantidad_realizada ?? (s.realizado ? cantProg : 0);
                realTotal += cantReal; actReal += cantReal;
                if (!s.realizado && m < MES_ACTUAL) { vencidosMes++; }
                // Current month
                if (m === MES_ACTUAL) { mesProgTotal += cantProg; mesRealTotal += cantReal; }
            }
        }
        // Recalculate effective estado from data
        if (a.estado === 'CANCELADA') return;
        if (actProg > 0 && actReal >= actProg) completadas++;
        else if (actReal > 0) enProgreso++;
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
    if (mBar) mBar.style.width = mesPct + '%';
    if (mNum) mNum.textContent = mesPct + '%';

    // Stat cards
    const elComp = document.getElementById('statCompletadas');
    const elProg = document.getElementById('statEnProgreso');
    const elVenc = document.getElementById('statVencidas');
    if (elComp) elComp.textContent = completadas;
    if (elProg) elProg.textContent = enProgreso;
    if (elVenc) elVenc.textContent = vencidosMes;

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
                    catReal += s.cantidad_realizada ?? (s.realizado ? cantProg : 0);
                }
            }
        });
        const catPct = catProg > 0 ? Math.round(catReal / catProg * 100) : 0;
        const catFill = card.querySelector('.sst-cat-progress-fill');
        if (catFill) catFill.style.width = catPct + '%';
        const catInfo = card.querySelector('.sst-cat-header span[style*="font-size:.72rem"]');
        if (catInfo) {
            const count = card.querySelectorAll('.sst-act-row').length;
            catInfo.textContent = count + ' actividades · ' + catPct + '% avance';
        }
    });
}
</script>
