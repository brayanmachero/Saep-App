{{-- Carta Gantt Scripts --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    updateProgress();
});

// ============ VIEW STATE ============
const ANIO = {{ $anioPrograma }};
const MES_ACTUAL = {{ $mesActual }};
const MESES = @json($mesesNombres);
let currentView = 'anual';
let periodoSem = MES_ACTUAL <= 6 ? 1 : 2;
let periodoMes = MES_ACTUAL;
let periodoSemana = getISOWeek(new Date());

// ============ DATA: all actividades for detail views ============
const actividadesData = @json($actividadesJson);

// ============ VIEW SWITCHING ============
function switchView(view) {
    currentView = view;
    document.querySelectorAll('.sst-view-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-view="'+view+'"]').classList.add('active');

    const periodNav = document.getElementById('periodNav');
    const viewAnual = document.getElementById('view-anual');
    const viewDetail = document.getElementById('view-detail');

    if (view === 'anual') {
        viewAnual.style.display = '';
        viewDetail.style.display = 'none';
        periodNav.style.display = 'none';
    } else {
        viewAnual.style.display = 'none';
        viewDetail.style.display = '';
        periodNav.style.display = 'flex';
        renderDetailView();
    }
}

function navigatePeriod(dir) {
    if (currentView === 'semestral') {
        periodoSem = periodoSem + dir;
        if (periodoSem < 1) periodoSem = 1;
        if (periodoSem > 2) periodoSem = 2;
    } else if (currentView === 'mensual') {
        periodoMes = periodoMes + dir;
        if (periodoMes < 1) periodoMes = 1;
        if (periodoMes > 12) periodoMes = 12;
    } else if (currentView === 'semanal') {
        periodoSemana = periodoSemana + dir;
        if (periodoSemana < 1) periodoSemana = 1;
        if (periodoSemana > 53) periodoSemana = 53;
    }
    renderDetailView();
}

function navigateToToday() {
    if (currentView === 'semestral') periodoSem = MES_ACTUAL <= 6 ? 1 : 2;
    else if (currentView === 'mensual') periodoMes = MES_ACTUAL;
    else if (currentView === 'semanal') periodoSemana = getISOWeek(new Date());
    renderDetailView();
}

// ============ DETAIL VIEW RENDERERS ============
function renderDetailView() {
    const container = document.getElementById('detail-content');
    const label = document.getElementById('periodLabel');

    if (currentView === 'semestral') {
        const start = periodoSem === 1 ? 1 : 7;
        const end = periodoSem === 1 ? 6 : 12;
        label.textContent = 'Semestre ' + periodoSem + ' (' + MESES[start] + ' – ' + MESES[end] + ')';
        container.innerHTML = renderSemestralView(start, end);
    } else if (currentView === 'mensual') {
        label.textContent = MESES[periodoMes] + ' ' + ANIO;
        container.innerHTML = renderMensualView(periodoMes);
    } else if (currentView === 'semanal') {
        const weekDates = getWeekDates(ANIO, periodoSemana);
        label.textContent = 'Semana ' + periodoSemana + ' · ' + formatShortDate(weekDates[0]) + ' – ' + formatShortDate(weekDates[6]);
        container.innerHTML = renderSemanalView(periodoSemana, weekDates);
    }
}

function renderSemestralView(start, end) {
    const mesHeaders = [];
    for (let m = start; m <= end; m++) {
        const isActual = m === MES_ACTUAL;
        mesHeaders.push('<th class="' + (isActual ? 'sst-highlight-col' : '') + '">' + MESES[m].substring(0,3) + '</th>');
    }

    let html = '';
    const grouped = groupByCat(actividadesData);
    for (const cat in grouped) {
        html += '<div class="sst-cat-card"><div class="sst-cat-header"><div style="display:flex;align-items:center;gap:.5rem">';
        html += '<div class="sst-cat-icon"><i class="bi bi-folder2-open"></i></div>';
        html += '<h3 class="sst-cat-title">' + escHtml(cat) + '</h3></div></div>';
        html += '<div class="sst-table-wrap"><table class="sst-detail-table"><thead><tr>';
        html += '<th style="text-align:left;min-width:200px">Actividad</th><th style="width:100px">Resp.</th><th style="width:60px">Pri.</th><th style="width:70px">Estado</th>';
        html += mesHeaders.join('') + '</tr></thead><tbody>';
        grouped[cat].forEach(act => {
            html += '<tr>';
            html += '<td class="td-name">' + escHtml(act.nombre) + '</td>';
            html += '<td style="font-size:.78rem;color:var(--text-muted)">' + escHtml(act.responsable || '—') + '</td>';
            html += '<td>' + prioridadBadge(act.prioridad) + '</td>';
            html += '<td>' + estadoBadge(act.estado) + '</td>';
            for (let m = start; m <= end; m++) {
                const s = act.seguimiento[m];
                const isActual = m === MES_ACTUAL;
                const cls = isActual ? ' sst-highlight-col' : '';
                if (s && s.programado) {
                    const cellClass = s.realizado ? 'gantt-done' : (m < MES_ACTUAL ? 'gantt-overdue' : 'gantt-plan');
                    const symbol = s.realizado ? '✓' : (m < MES_ACTUAL ? '!' : '○');
                    html += '<td class="' + cls + '"><button class="gantt-cell ' + cellClass + '" onclick="toggleSeguimiento(' + act.id + ',' + m + ',this)">' + symbol + '</button></td>';
                } else {
                    html += '<td class="' + cls + '"></td>';
                }
            }
            html += '</tr>';
        });
        html += '</tbody></table></div></div>';
    }
    return html || '<p style="padding:2rem;color:var(--text-muted);text-align:center">Sin actividades.</p>';
}

function renderMensualView(mes) {
    const daysInMonth = new Date(ANIO, mes, 0).getDate();
    const firstDow = (new Date(ANIO, mes - 1, 1).getDay() + 6) % 7; // 0=Mon
    const weeks = [];
    let week = new Array(7).fill(null);
    let d = 1;
    for (let i = firstDow; i < 7 && d <= daysInMonth; i++) { week[i] = d++; }
    weeks.push(week);
    while (d <= daysInMonth) {
        week = new Array(7).fill(null);
        for (let i = 0; i < 7 && d <= daysInMonth; i++) { week[i] = d++; }
        weeks.push(week);
    }

    const today = new Date();
    const isCurrentMonth = today.getFullYear() === ANIO && (today.getMonth() + 1) === mes;
    const todayDay = isCurrentMonth ? today.getDate() : -1;
    const diasSemana = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

    let html = '';
    const grouped = groupByCat(actividadesData);
    for (const cat in grouped) {
        const actsInMonth = grouped[cat].filter(a => {
            const s = a.seguimiento[mes];
            return s && s.programado;
        });
        if (actsInMonth.length === 0) continue;

        html += '<div class="sst-cat-card"><div class="sst-cat-header"><div style="display:flex;align-items:center;gap:.5rem">';
        html += '<div class="sst-cat-icon"><i class="bi bi-folder2-open"></i></div>';
        html += '<h3 class="sst-cat-title">' + escHtml(cat) + ' <small style="font-weight:400;color:var(--text-muted)">(' + actsInMonth.length + ' actividades programadas)</small></h3></div></div>';

        html += '<div style="padding:.75rem 1rem">';
        actsInMonth.forEach(act => {
            const s = act.seguimiento[mes];
            const done = s && s.realizado;
            const late = !done && mes < MES_ACTUAL;
            const statusColor = done ? '#10b981' : (late ? '#ef4444' : '#6366f1');
            const statusText = done ? 'Realizado' : (late ? 'Vencido' : 'Programado');

            html += '<div style="background:var(--bg-color);border:1px solid var(--surface-border);border-radius:10px;padding:.7rem .85rem;margin-bottom:.6rem;border-left:3px solid ' + statusColor + '">';
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.3rem">';
            html += '<span style="font-weight:700;font-size:.88rem">' + escHtml(act.nombre) + '</span>';
            html += '<span style="font-size:.72rem;padding:.15rem .4rem;border-radius:6px;background:' + statusColor + '20;color:' + statusColor + ';font-weight:600">' + statusText + '</span>';
            html += '</div>';
            html += '<div style="font-size:.78rem;color:var(--text-muted);display:flex;gap:.8rem;flex-wrap:wrap">';
            html += '<span><i class="bi bi-person"></i> ' + escHtml(act.responsable || '—') + '</span>';
            html += '<span>' + prioridadBadge(act.prioridad) + '</span>';
            if (act.fecha_inicio) html += '<span><i class="bi bi-calendar3"></i> ' + act.fecha_inicio + '</span>';
            if (act.periodicidad) html += '<span><i class="bi bi-arrow-repeat"></i> ' + escHtml(act.periodicidad) + '</span>';
            html += '</div></div>';
        });
        html += '</div></div>';
    }

    // Calendar grid
    html += '<div class="sst-cat-card"><div class="sst-cat-header"><div style="display:flex;align-items:center;gap:.5rem">';
    html += '<div class="sst-cat-icon" style="background:linear-gradient(135deg,#0ea5e9,#38bdf8)"><i class="bi bi-calendar-month"></i></div>';
    html += '<h3 class="sst-cat-title">Calendario · ' + MESES[mes] + '</h3></div></div>';
    html += '<div class="sst-table-wrap"><table class="sst-detail-table"><thead><tr>';
    diasSemana.forEach(d => { html += '<th>' + d + '</th>'; });
    html += '</tr></thead><tbody>';
    weeks.forEach(w => {
        html += '<tr>';
        w.forEach(d => {
            if (d === null) {
                html += '<td style="color:var(--surface-border)">·</td>';
            } else {
                const isToday = d === todayDay;
                const style = isToday ? 'background:var(--accent-color);color:#fff;border-radius:50%;width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;font-weight:700' : '';
                html += '<td' + (isToday ? ' class="sst-highlight-col"' : '') + '><span style="' + style + '">' + d + '</span></td>';
            }
        });
        html += '</tr>';
    });
    html += '</tbody></table></div></div>';

    return html || '<p style="padding:2rem;color:var(--text-muted);text-align:center">Sin actividades programadas este mes.</p>';
}

function renderSemanalView(weekNum, weekDates) {
    const diasSemana = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
    const today = new Date();
    today.setHours(0,0,0,0);

    // Find activities that fall within this week based on their programmed months
    const weekMonth = weekDates[0].getMonth() + 1;
    const weekMonth2 = weekDates[6].getMonth() + 1;

    let html = '<div class="sst-cat-card"><div class="sst-cat-header"><div style="display:flex;align-items:center;gap:.5rem">';
    html += '<div class="sst-cat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="bi bi-calendar-week"></i></div>';
    html += '<h3 class="sst-cat-title">Vista Semanal</h3></div></div>';
    html += '<div class="sst-table-wrap"><table class="sst-detail-table"><thead><tr><th style="text-align:left;min-width:200px">Actividad</th>';
    weekDates.forEach((d, i) => {
        const isToday = d.getTime() === today.getTime();
        html += '<th class="' + (isToday ? 'sst-highlight-col' : '') + '">' + diasSemana[i].substring(0,3) + ' ' + d.getDate() + '</th>';
    });
    html += '</tr></thead><tbody>';

    let hasRows = false;
    actividadesData.forEach(act => {
        // Check if activity is programmed in any of the months covered by this week
        const monthsInWeek = [weekMonth];
        if (weekMonth2 !== weekMonth) monthsInWeek.push(weekMonth2);

        let inWeek = false;
        monthsInWeek.forEach(m => {
            const s = act.seguimiento[m];
            if (s && s.programado) inWeek = true;
        });

        if (!inWeek) return;
        hasRows = true;

        html += '<tr><td class="td-name">' + escHtml(act.nombre) + '<br><span style="font-size:.7rem;color:var(--text-muted)">' + escHtml(act.responsable || '') + '</span></td>';
        weekDates.forEach((d, i) => {
            const m = d.getMonth() + 1;
            const s = act.seguimiento[m];
            const isToday = d.getTime() === today.getTime();
            const cls = isToday ? ' sst-highlight-col' : '';

            if (s && s.programado) {
                const done = s.realizado;
                const late = !done && m < MES_ACTUAL;
                const dotColor = done ? '#10b981' : (late ? '#ef4444' : '#6366f1');
                html += '<td class="' + cls + '"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + dotColor + '" title="' + (done ? 'Realizado' : (late ? 'Vencido' : 'Programado')) + '"></span></td>';
            } else {
                html += '<td class="' + cls + '"></td>';
            }
        });
        html += '</tr>';
    });

    if (!hasRows) {
        html += '<tr><td colspan="8" style="padding:2rem;color:var(--text-muted);font-style:italic;text-align:center">Sin actividades programadas esta semana.</td></tr>';
    }

    html += '</tbody></table></div></div>';

    // Day breakdown with activity cards
    html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.6rem;margin-top:.8rem">';
    weekDates.forEach((d, i) => {
        if (i >= 5) return; // Only Mon-Fri cards
        const m = d.getMonth() + 1;
        const isToday = d.getTime() === today.getTime();
        const dayActs = actividadesData.filter(a => { const s = a.seguimiento[m]; return s && s.programado; });

        html += '<div style="background:var(--surface-color);border:1px solid ' + (isToday ? 'var(--accent-color)' : 'var(--surface-border)') + ';border-radius:10px;padding:.65rem;' + (isToday ? 'box-shadow:0 0 0 2px rgba(99,102,241,.2)' : '') + '">';
        html += '<div style="font-size:.78rem;font-weight:700;margin-bottom:.4rem;color:' + (isToday ? 'var(--accent-color)' : 'var(--text-main)') + '">' + diasSemana[i] + ' ' + d.getDate() + (isToday ? ' <span style="font-size:.65rem;font-weight:400;opacity:.7">HOY</span>' : '') + '</div>';
        if (dayActs.length > 0) {
            dayActs.forEach(a => {
                const s = a.seguimiento[m];
                const done = s && s.realizado;
                html += '<div style="font-size:.73rem;padding:.25rem .4rem;margin-bottom:.2rem;border-radius:4px;background:' + (done ? 'rgba(16,185,129,.08)' : 'rgba(99,102,241,.06)') + ';display:flex;align-items:center;gap:.3rem">';
                html += '<span style="width:6px;height:6px;border-radius:50%;background:' + (done ? '#10b981' : '#6366f1') + ';flex-shrink:0"></span>';
                html += escHtml(a.nombre.length > 25 ? a.nombre.substring(0,25) + '…' : a.nombre);
                html += '</div>';
            });
        } else {
            html += '<div style="font-size:.72rem;color:var(--text-muted);font-style:italic">Sin actividades</div>';
        }
        html += '</div>';
    });
    html += '</div>';

    return html;
}

// ============ HELPERS ============
function groupByCat(acts) {
    const g = {};
    acts.forEach(a => { if (!g[a.categoria]) g[a.categoria] = []; g[a.categoria].push(a); });
    return g;
}

function escHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function prioridadBadge(p) {
    const colors = {ALTA:'#ef4444',MEDIA:'#f59e0b',BAJA:'#10b981'};
    const labels = {ALTA:'Alta',MEDIA:'Media',BAJA:'Baja'};
    const c = colors[p] || '#94a3b8';
    return '<span style="display:inline-block;padding:.1rem .35rem;border-radius:5px;font-size:.68rem;font-weight:700;background:'+c+'20;color:'+c+'">'+(labels[p]||'—')+'</span>';
}

function estadoBadge(e) {
    const colors = {PENDIENTE:'#94a3b8',EN_PROGRESO:'#f59e0b',COMPLETADA:'#10b981',CANCELADA:'#ef4444'};
    const labels = {PENDIENTE:'Pend.',EN_PROGRESO:'Progreso',COMPLETADA:'Compl.',CANCELADA:'Cancel.'};
    const c = colors[e] || '#94a3b8';
    return '<span style="display:inline-block;padding:.1rem .35rem;border-radius:5px;font-size:.68rem;font-weight:600;background:'+c+'20;color:'+c+'">'+(labels[e]||'—')+'</span>';
}

function getISOWeek(d) {
    const date = new Date(d.getTime());
    date.setHours(0,0,0,0);
    date.setDate(date.getDate() + 3 - (date.getDay() + 6) % 7);
    const week1 = new Date(date.getFullYear(), 0, 4);
    return 1 + Math.round(((date.getTime() - week1.getTime()) / 86400000 - 3 + (week1.getDay() + 6) % 7) / 7);
}

function getWeekDates(year, weekNum) {
    const jan4 = new Date(year, 0, 4);
    const dayOfWeek = (jan4.getDay() + 6) % 7;
    const monday = new Date(jan4.getTime());
    monday.setDate(jan4.getDate() - dayOfWeek + (weekNum - 1) * 7);
    const dates = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday.getTime());
        d.setDate(monday.getDate() + i);
        dates.push(d);
    }
    return dates;
}

function formatShortDate(d) {
    return d.getDate() + '/' + (d.getMonth()+1);
}

// ============ SEGUIMIENTO AJAX ============
function toggleSeguimiento(actId, mes, btn) {
    btn.disabled = true;
    btn.style.opacity = '.5';
    fetch("{{ url('carta-gantt/actividades') }}/" + actId + "/seguimiento", {
        method: 'PATCH',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body: JSON.stringify({mes: mes})
    })
    .then(r => r.json())
    .then(data => {
        if (data.realizado) {
            btn.className = 'gantt-cell gantt-done';
            btn.textContent = '✓';
            btn.title = 'Realizado — clic para desmarcar';
        } else {
            const vencido = mes < MES_ACTUAL;
            btn.className = 'gantt-cell ' + (vencido ? 'gantt-overdue' : 'gantt-plan');
            btn.textContent = vencido ? '!' : '○';
            btn.title = vencido ? 'Vencido — clic para marcar' : 'Programado — clic para marcar';
        }
        // Update local data
        const actData = actividadesData.find(a => a.id === actId);
        if (actData && actData.seguimiento[mes]) {
            actData.seguimiento[mes].realizado = data.realizado;
        }
        updateProgress();
    })
    .catch(err => { console.error(err); alert('Error al actualizar seguimiento.'); })
    .finally(() => { btn.disabled = false; btn.style.opacity = '1'; });
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
    document.getElementById('edit-fecha-inicio').value = data.fecha_inicio || '';
    document.getElementById('edit-fecha-fin').value = data.fecha_fin || '';
    modal.style.display = 'flex';
}

// ============ DETAIL MODAL ============
function openDetail(row) {
    const data = JSON.parse(row.dataset.act);
    const act = actividadesData.find(a => a.id === data.id);
    if (!act) return;

    const priColors = {ALTA:'#ef4444',MEDIA:'#f59e0b',BAJA:'#10b981'};
    const priLabels = {ALTA:'Alta',MEDIA:'Media',BAJA:'Baja'};
    const estLabels = {PENDIENTE:'Pendiente',EN_PROGRESO:'En Progreso',COMPLETADA:'Completada',CANCELADA:'Cancelada'};
    const perLabels = {UNICA:'Única',DIARIA:'Diaria',SEMANAL:'Semanal',QUINCENAL:'Quincenal',MENSUAL:'Mensual',BIMESTRAL:'Bimestral',TRIMESTRAL:'Trimestral',SEMESTRAL:'Semestral',ANUAL:'Anual'};

    document.getElementById('detail-title').innerHTML = '<i class="bi bi-info-circle"></i> ' + escHtml(act.nombre);

    let body = '<div class="sst-detail-grid">';
    body += detailItem('Categoría', act.categoria);
    body += detailItem('Responsable', act.responsable || '—');
    body += detailItem('Prioridad', priLabels[act.prioridad] || '—');
    body += detailItem('Estado', estLabels[act.estado] || '—');
    body += detailItem('Periodicidad', perLabels[act.periodicidad] || '—');
    body += detailItem('Fecha Inicio', act.fecha_inicio || '—');
    body += detailItem('Fecha Fin', act.fecha_fin || '—');
    body += '</div>';

    if (act.descripcion) {
        body += '<div style="margin-bottom:1rem"><span class="sst-label">Descripción</span><p style="font-size:.85rem;margin:.2rem 0">' + escHtml(act.descripcion) + '</p></div>';
    }

    // Seguimiento grid
    body += '<div style="margin-bottom:.5rem"><span class="sst-label">Seguimiento Mensual</span></div>';
    body += '<div class="sst-seg-grid">';
    for (let m = 1; m <= 12; m++) {
        const s = act.seguimiento[m];
        let cls = 'sst-seg-none';
        let txt = MESES[m].substring(0,3);
        if (s && s.programado) {
            if (s.realizado) { cls = 'sst-seg-done'; txt += ' ✓'; }
            else if (m < MES_ACTUAL) { cls = 'sst-seg-late'; txt += ' !'; }
            else { cls = 'sst-seg-prog'; txt += ' ○'; }
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

// ============ PROGRESS UPDATE ============
function updateProgress() {
    let prog = 0, real = 0;
    actividadesData.forEach(a => {
        for (let m = 1; m <= 12; m++) {
            const s = a.seguimiento[m];
            if (s && s.programado) { prog++; if (s.realizado) real++; }
        }
    });
    const pct = prog > 0 ? Math.round(real / prog * 100) : 0;
    const bar = document.getElementById('progressBar');
    const num = document.getElementById('progressNum');
    if (bar) bar.style.width = pct + '%';
    if (num) num.textContent = pct + '%';
}
</script>
