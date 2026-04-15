{{-- Modal Detalle de Tarea (Trello-style) --}}
<style>
@media (max-width: 640px) {
    #modal-detalle .modal-body-flex { flex-direction: column !important; }
    #modal-detalle .modal-sidebar { width: 100% !important; border-right: none !important; border-top: 1px solid var(--border-color); }
    #modal-detalle .modal-main { border-right: none !important; }
}
</style>
<div id="modal-detalle" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;justify-content:center;align-items:flex-start;padding-top:3vh;backdrop-filter:blur(2px);" onclick="if(event.target===this)cerrarDetalle()">
    <div class="glass-card" style="padding:0;width:94%;max-width:820px;max-height:92vh;overflow:hidden;display:flex;flex-direction:column;" onclick="event.stopPropagation()">

        {{-- Cover image (shown if task has image attachment) --}}
        <div id="detalle-cover" style="display:none;width:100%;height:140px;overflow:hidden;position:relative;background:#f1f5f9;">
            <img id="detalle-cover-img" src="" alt="" style="width:100%;height:100%;object-fit:cover;">
        </div>

        {{-- Header --}}
        <div id="detalle-header" style="padding:.85rem 1.25rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-color);flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:0;">
                <i class="bi bi-card-heading" style="color:var(--primary-color);font-size:1rem;"></i>
                <input type="text" id="detalle-titulo" class="form-input" style="font-size:1.05rem;font-weight:700;border:none;padding:.2rem .35rem;background:transparent;flex:1;" placeholder="Título de la tarea">
            </div>
            <div style="display:flex;align-items:center;gap:.4rem;flex-shrink:0;margin-left:.5rem;">
                <span id="detalle-col-dot" style="width:10px;height:10px;border-radius:50%;"></span>
                <span id="detalle-col-nombre" style="font-size:.72rem;font-weight:600;color:var(--text-muted);"></span>
                <div style="width:1px;height:16px;background:var(--border-color);margin:0 .2rem;"></div>
                <button onclick="archivarTareaDetalle()" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:.82rem;padding:.2rem;" title="Archivar">
                    <i class="bi bi-archive"></i>
                </button>
                <button id="detalle-btn-eliminar" onclick="eliminarTareaDetalle()" style="display:none;background:none;border:none;cursor:pointer;color:#dc2626;font-size:.82rem;padding:.2rem;" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
                <button onclick="cerrarDetalle()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);padding:.2rem;">&times;</button>
            </div>
        </div>

        {{-- Body: two-column Trello layout --}}
        <div class="modal-body-flex" style="overflow-y:auto;flex:1;display:flex;gap:0;">
            <input type="hidden" id="detalle-tarea-id">
            <input type="hidden" id="detalle-tablero-id">

            {{-- LEFT: Main content (descripción, checklist, adjuntos, comentarios) --}}
            <div class="modal-main" style="flex:1;padding:1.25rem;min-width:0;border-right:1px solid var(--border-color);">

                {{-- Descripción --}}
                <div style="margin-bottom:1.25rem;">
                    <label style="font-size:.78rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:.3rem;margin-bottom:.4rem;">
                        <i class="bi bi-text-left" style="color:var(--primary-color);"></i> Descripción
                    </label>
                    <textarea id="detalle-descripcion" class="form-input" rows="3" placeholder="Agrega una descripción más detallada..." style="font-size:.84rem;line-height:1.5;resize:vertical;"></textarea>
                </div>

                {{-- Checklist --}}
                <div style="margin-bottom:1.25rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
                        <label style="font-size:.78rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:.3rem;">
                            <i class="bi bi-check2-square" style="color:var(--primary-color);"></i> Checklist
                            <span id="checklist-progreso-badge" style="font-size:.68rem;color:var(--text-muted);font-weight:400;"></span>
                        </label>
                    </div>
                    <div id="checklist-progress-bar" style="display:none;margin-bottom:.5rem;">
                        <div style="height:4px;background:var(--border-color);border-radius:3px;overflow:hidden;">
                            <div id="checklist-progress-fill" style="height:100%;background:#10b981;border-radius:3px;transition:width .3s;width:0%;"></div>
                        </div>
                    </div>
                    <div id="checklist-items"></div>
                    <div style="display:flex;gap:.35rem;margin-top:.4rem;">
                        <input type="text" id="checklist-nuevo-texto" class="form-input" placeholder="Agregar ítem..." style="font-size:.8rem;flex:1;" onkeydown="if(event.key==='Enter'){event.preventDefault();agregarChecklistItem();}">
                        <button onclick="agregarChecklistItem()" style="background:var(--primary-color);color:#fff;border:none;border-radius:6px;padding:.3rem .6rem;cursor:pointer;font-size:.78rem;">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>

                {{-- Adjuntos --}}
                <div style="margin-bottom:1.25rem;">
                    <label style="font-size:.78rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:.3rem;margin-bottom:.5rem;">
                        <i class="bi bi-paperclip" style="color:var(--primary-color);"></i> Adjuntos
                        <span id="adjuntos-count" style="font-size:.68rem;color:var(--text-muted);font-weight:400;"></span>
                    </label>
                    <div id="adjuntos-lista"></div>
                    <div style="margin-top:.4rem;">
                        <label style="display:inline-flex;align-items:center;gap:.3rem;padding:.4rem .75rem;border-radius:6px;border:1px dashed var(--border-color);font-size:.78rem;color:var(--text-muted);cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='var(--primary-color)';this.style.color='var(--primary-color)';" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-muted)';">
                            <i class="bi bi-cloud-upload"></i> Subir archivo (máx 10 MB)
                            <input type="file" id="adjunto-file-input" style="display:none;" onchange="subirAdjunto(this)">
                        </label>
                    </div>
                </div>

                {{-- Comentarios --}}
                <div>
                    <label style="font-size:.78rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:.3rem;margin-bottom:.5rem;">
                        <i class="bi bi-chat-dots" style="color:var(--primary-color);"></i> Comentarios
                        <span id="comentarios-count" style="font-size:.68rem;color:var(--text-muted);font-weight:400;"></span>
                    </label>
                    <div style="display:flex;gap:.4rem;margin-bottom:.75rem;">
                        <div style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;flex-shrink:0;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div style="flex:1;">
                            <textarea id="comentario-nuevo" class="form-input" rows="2" placeholder="Escribe un comentario..." style="font-size:.82rem;"></textarea>
                            <button onclick="enviarComentario()" class="btn-premium" style="margin-top:.3rem;padding:.3rem .6rem;font-size:.75rem;">
                                <i class="bi bi-send"></i> Comentar
                            </button>
                        </div>
                    </div>
                    <div id="comentarios-lista"></div>
                </div>
            </div>

            {{-- RIGHT: Sidebar (metadata, etiquetas, actividad) --}}
            <div class="modal-sidebar" style="width:240px;flex-shrink:0;padding:1.25rem .85rem;background:var(--card-bg);overflow-y:auto;">

                {{-- Miembros / Asignados (multi) --}}
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.35rem;">
                        <i class="bi bi-people"></i> Asignados
                    </label>
                    <div id="detalle-asignados-container" style="max-height:130px;overflow-y:auto;border:1px solid var(--surface-border);border-radius:8px;padding:.3rem;">
                        @foreach($usuarios as $u)
                        <label style="display:flex;align-items:center;gap:.35rem;padding:.15rem .25rem;font-size:.78rem;cursor:pointer;border-radius:5px;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background=''">
                            <input type="checkbox" class="detalle-asignado-cb" value="{{ $u->id }}" style="width:13px;height:13px;">
                            <span>{{ $u->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Prioridad --}}
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.35rem;">
                        <i class="bi bi-flag"></i> Prioridad
                    </label>
                    <select id="detalle-prioridad" class="form-input" style="font-size:.8rem;padding:.35rem .5rem;">
                        <option value="BAJA">🟢 Baja</option>
                        <option value="MEDIA">🟡 Media</option>
                        <option value="ALTA">🔴 Alta</option>
                    </select>
                </div>

                {{-- Fechas --}}
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.35rem;">
                        <i class="bi bi-calendar3"></i> Fechas
                    </label>
                    <div style="display:flex;flex-direction:column;gap:.35rem;">
                        <div>
                            <span style="font-size:.65rem;color:var(--text-muted);">Inicio</span>
                            <input type="date" id="detalle-fecha-inicio" class="form-input" style="font-size:.78rem;padding:.3rem .4rem;">
                        </div>
                        <div>
                            <span style="font-size:.65rem;color:var(--text-muted);">Vencimiento</span>
                            <input type="date" id="detalle-fecha-vencimiento" class="form-input" style="font-size:.78rem;padding:.3rem .4rem;">
                        </div>
                    </div>
                </div>

                {{-- Centro de Costo --}}
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.35rem;">
                        <i class="bi bi-building"></i> Centro de Costo
                    </label>
                    <select id="detalle-centro-costo" class="form-input" style="font-size:.8rem;padding:.35rem .5rem;">
                        <option value="">— Sin asignar —</option>
                        @foreach($centros as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Etiquetas --}}
                @if($kanban->etiquetas->isNotEmpty())
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.35rem;">
                        <i class="bi bi-tags"></i> Etiquetas
                    </label>
                    <div style="display:flex;flex-wrap:wrap;gap:.3rem;" id="detalle-etiquetas-container">
                        @foreach($kanban->etiquetas as $et)
                        <label style="display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .35rem;border-radius:5px;font-size:.7rem;cursor:pointer;border:1px solid {{ $et->color }}30;background:{{ $et->color }}10;transition:background .12s;">
                            <input type="checkbox" class="detalle-etiqueta-cb" value="{{ $et->id }}" style="width:12px;height:12px;">
                            <span style="color:{{ $et->color }};font-weight:600;">{{ $et->nombre }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Guardar --}}
                <button onclick="guardarDetalle()" class="btn-premium" style="width:100%;justify-content:center;padding:.45rem;font-size:.8rem;margin-bottom:1rem;">
                    <i class="bi bi-check-lg"></i> Guardar cambios
                </button>

                <hr style="border:none;border-top:1px solid var(--border-color);margin:.75rem 0;">

                {{-- Actividad --}}
                <div>
                    <label style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:.35rem;cursor:pointer;" onclick="toggleDetalleActividad()">
                        <i class="bi bi-clock-history"></i> Actividad
                        <i id="actividad-toggle-icon" class="bi bi-chevron-down" style="font-size:.6rem;margin-left:.2rem;"></i>
                    </label>
                    <div id="detalle-actividad" style="display:none;max-height:200px;overflow-y:auto;"></div>
                </div>

                {{-- Meta --}}
                <div style="margin-top:.75rem;font-size:.68rem;color:var(--text-muted);">
                    Creada por <strong id="detalle-creador"></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
let detalleTareaId = null;

function abrirDetalle(tareaId) {
    detalleTareaId = tareaId;
    document.getElementById('modal-detalle').style.display = 'flex';
    document.getElementById('detalle-tarea-id').value = tareaId;

    // Fetch task data
    fetch(`/kanban/tareas/${tareaId}`, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
        .then(r => r.json())
        .then(data => {
            document.getElementById('detalle-tablero-id').value = data.tablero_id;
            document.getElementById('detalle-titulo').value = data.titulo;
            document.getElementById('detalle-descripcion').value = data.descripcion || '';
            document.getElementById('detalle-prioridad').value = data.prioridad;

            // Multi-assignees: check the right checkboxes
            const asigIds = (data.asignados || []).map(a => a.id);
            document.querySelectorAll('.detalle-asignado-cb').forEach(cb => {
                cb.checked = asigIds.includes(parseInt(cb.value));
            });

            document.getElementById('detalle-fecha-inicio').value = data.fecha_inicio || '';
            document.getElementById('detalle-fecha-vencimiento').value = data.fecha_vencimiento || '';
            document.getElementById('detalle-centro-costo').value = data.centro_costo_id || '';
            document.getElementById('detalle-creador').textContent = data.creador_nombre || 'Sistema';

            // Column header
            document.getElementById('detalle-col-dot').style.background = data.columna_color || '#6b7280';
            document.getElementById('detalle-col-nombre').textContent = data.columna_nombre || '';

            // Show/hide delete button based on permissions
            document.getElementById('detalle-btn-eliminar').style.display = data.puede_eliminar ? '' : 'none';

            // Cover image — show first image attachment
            const coverEl = document.getElementById('detalle-cover');
            const coverImg = document.getElementById('detalle-cover-img');
            const firstImg = data.adjuntos.find(a => a.es_imagen && a.url_imagen);
            if (firstImg) {
                coverImg.src = firstImg.url_imagen;
                coverEl.style.display = 'block';
            } else {
                coverEl.style.display = 'none';
                coverImg.src = '';
            }

            // Etiquetas checkboxes
            const etIds = data.etiquetas.map(e => e.id);
            document.querySelectorAll('.detalle-etiqueta-cb').forEach(cb => {
                cb.checked = etIds.includes(parseInt(cb.value));
            });

            // Render checklist
            renderChecklist(data.checklist, data.checklist_progreso);

            // Render adjuntos
            renderAdjuntos(data.adjuntos);

            // Render comentarios
            renderComentarios(data.comentarios);
        })
        .catch(err => { console.error('Error cargando tarea:', err); cerrarDetalle(); });
}

function cerrarDetalle() {
    document.getElementById('modal-detalle').style.display = 'none';
    detalleTareaId = null;
}

function guardarDetalle() {
    const id = document.getElementById('detalle-tarea-id').value;
    const etiquetas = [...document.querySelectorAll('.detalle-etiqueta-cb:checked')].map(cb => cb.value);
    const asignados = [...document.querySelectorAll('.detalle-asignado-cb:checked')].map(cb => cb.value);

    const body = {
        titulo: document.getElementById('detalle-titulo').value,
        descripcion: document.getElementById('detalle-descripcion').value || null,
        prioridad: document.getElementById('detalle-prioridad').value,
        asignados: asignados,
        fecha_inicio: document.getElementById('detalle-fecha-inicio').value || null,
        fecha_vencimiento: document.getElementById('detalle-fecha-vencimiento').value || null,
        centro_costo_id: document.getElementById('detalle-centro-costo').value || null,
        etiquetas: etiquetas,
    };

    fetch(`/kanban/tareas/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error al guardar');
        }
    })
    .catch(() => alert('Error de conexión'));
}

function eliminarTareaDetalle() {
    if (!confirm('¿Eliminar esta tarea permanentemente?')) return;
    const id = document.getElementById('detalle-tarea-id').value;
    fetch(`/kanban/tareas/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(() => location.reload())
    .catch(() => alert('Error al eliminar'));
}

function archivarTareaDetalle() {
    if (!confirm('¿Archivar esta tarea? Podrás recuperarla desde el archivo.')) return;
    const id = document.getElementById('detalle-tarea-id').value;
    fetch(`/kanban/tareas/${id}/archivar`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert('Error al archivar'); })
    .catch(() => alert('Error de conexión'));
}

// =============================================
// CHECKLIST
// =============================================
function renderChecklist(items, progreso) {
    const container = document.getElementById('checklist-items');
    const bar = document.getElementById('checklist-progress-bar');
    const fill = document.getElementById('checklist-progress-fill');
    const badge = document.getElementById('checklist-progreso-badge');

    container.innerHTML = '';

    if (items.length > 0) {
        bar.style.display = 'block';
        const pct = progreso.total > 0 ? Math.round(progreso.completados / progreso.total * 100) : 0;
        fill.style.width = pct + '%';
        badge.textContent = `(${progreso.completados}/${progreso.total})`;
    } else {
        bar.style.display = 'none';
        badge.textContent = '';
    }

    items.forEach(item => {
        container.appendChild(crearChecklistItemEl(item));
    });
}

function crearChecklistItemEl(item) {
    const div = document.createElement('div');
    div.id = 'cl-item-' + item.id;
    div.style.cssText = 'display:flex;align-items:center;gap:.4rem;padding:.3rem .1rem;border-radius:4px;';
    div.innerHTML = `
        <input type="checkbox" ${item.completado ? 'checked' : ''} onchange="toggleChecklistItem(${item.id})" style="width:16px;height:16px;cursor:pointer;accent-color:#10b981;">
        <span style="flex:1;font-size:.82rem;${item.completado ? 'text-decoration:line-through;color:var(--text-muted);' : 'color:var(--text-primary);'}">${escapeHtml(item.texto)}</span>
        <button onclick="eliminarChecklistItem(${item.id})" style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:.75rem;opacity:.5;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.5">
            <i class="bi bi-x-lg"></i>
        </button>
    `;
    return div;
}

function agregarChecklistItem() {
    const input = document.getElementById('checklist-nuevo-texto');
    const texto = input.value.trim();
    if (!texto) return;

    const tareaId = document.getElementById('detalle-tarea-id').value;
    fetch(`/kanban/tareas/${tareaId}/checklist`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ texto }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('checklist-items').appendChild(crearChecklistItemEl(data.item));
            input.value = '';
            actualizarChecklistBadge();
        }
    });
}

function toggleChecklistItem(itemId) {
    fetch(`/kanban/checklist/${itemId}/toggle`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.querySelector(`#cl-item-${itemId} span`);
            if (data.completado) {
                el.style.textDecoration = 'line-through';
                el.style.color = 'var(--text-muted)';
            } else {
                el.style.textDecoration = 'none';
                el.style.color = 'var(--text-primary)';
            }
            const fill = document.getElementById('checklist-progress-fill');
            const badge = document.getElementById('checklist-progreso-badge');
            const pct = data.progreso.total > 0 ? Math.round(data.progreso.completados / data.progreso.total * 100) : 0;
            fill.style.width = pct + '%';
            badge.textContent = `(${data.progreso.completados}/${data.progreso.total})`;
        }
    });
}

function eliminarChecklistItem(itemId) {
    fetch(`/kanban/checklist/${itemId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('cl-item-' + itemId)?.remove();
            actualizarChecklistBadge();
        }
    });
}

function actualizarChecklistBadge() {
    const items = document.querySelectorAll('#checklist-items > div');
    const total = items.length;
    const completados = document.querySelectorAll('#checklist-items input[type="checkbox"]:checked').length;
    const badge = document.getElementById('checklist-progreso-badge');
    const fill = document.getElementById('checklist-progress-fill');
    const bar = document.getElementById('checklist-progress-bar');
    if (total > 0) {
        bar.style.display = 'block';
        badge.textContent = `(${completados}/${total})`;
        fill.style.width = (Math.round(completados / total * 100)) + '%';
    } else {
        bar.style.display = 'none';
        badge.textContent = '';
    }
}

// =============================================
// ADJUNTOS
// =============================================
function renderAdjuntos(adjuntos) {
    const container = document.getElementById('adjuntos-lista');
    const countEl = document.getElementById('adjuntos-count');
    container.innerHTML = '';
    countEl.textContent = adjuntos.length > 0 ? `(${adjuntos.length})` : '';

    adjuntos.forEach(a => {
        container.appendChild(crearAdjuntoEl(a));
    });
}

function crearAdjuntoEl(a) {
    const div = document.createElement('div');
    div.id = 'adjunto-' + a.id;
    div.style.cssText = 'display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;border-radius:6px;border:1px solid var(--border-color);margin-bottom:.35rem;font-size:.8rem;';

    const thumbHtml = a.es_imagen && a.url_imagen
        ? `<img src="${a.url_imagen}" alt="" style="width:40px;height:40px;border-radius:4px;object-fit:cover;flex-shrink:0;">`
        : `<i class="bi bi-file-earmark" style="font-size:1.1rem;color:var(--primary-color);flex-shrink:0;"></i>`;

    div.innerHTML = `
        ${thumbHtml}
        <div style="flex:1;min-width:0;">
            <a href="${a.url_descargar}" style="font-weight:600;color:var(--text-primary);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(a.nombre_original)}">
                ${escapeHtml(a.nombre_original)}
            </a>
            <div style="font-size:.68rem;color:var(--text-muted);">${a.tamanio} · ${a.subido_por} · ${a.fecha}</div>
        </div>
        <button onclick="eliminarAdjunto(${a.id})" style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:.8rem;opacity:.5;flex-shrink:0;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.5">
            <i class="bi bi-trash"></i>
        </button>
    `;
    return div;
}

function subirAdjunto(fileInput) {
    const file = fileInput.files[0];
    if (!file) return;

    const tareaId = document.getElementById('detalle-tarea-id').value;
    const formData = new FormData();
    formData.append('archivo', file);

    fetch(`/kanban/tareas/${tareaId}/adjuntos`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('adjuntos-lista').appendChild(crearAdjuntoEl(data.adjunto));
            const countEl = document.getElementById('adjuntos-count');
            const current = document.querySelectorAll('#adjuntos-lista > div').length;
            countEl.textContent = `(${current})`;
            // Update cover if first image
            const coverEl = document.getElementById('detalle-cover');
            if (data.adjunto.es_imagen && data.adjunto.url_imagen && coverEl.style.display === 'none') {
                document.getElementById('detalle-cover-img').src = data.adjunto.url_imagen;
                coverEl.style.display = 'block';
            }
        } else {
            alert(data.message || 'Error al subir archivo');
        }
        fileInput.value = '';
    })
    .catch(() => { alert('Error de conexión'); fileInput.value = ''; });
}

function eliminarAdjunto(adjuntoId) {
    if (!confirm('¿Eliminar este archivo?')) return;
    fetch(`/kanban/adjuntos/${adjuntoId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('adjunto-' + adjuntoId)?.remove();
            const current = document.querySelectorAll('#adjuntos-lista > div').length;
            document.getElementById('adjuntos-count').textContent = current > 0 ? `(${current})` : '';
        }
    });
}

// =============================================
// COMENTARIOS
// =============================================
function renderComentarios(comentarios) {
    const container = document.getElementById('comentarios-lista');
    const countEl = document.getElementById('comentarios-count');
    container.innerHTML = '';
    countEl.textContent = comentarios.length > 0 ? `(${comentarios.length})` : '';

    comentarios.forEach(c => {
        container.appendChild(crearComentarioEl(c));
    });
}

function crearComentarioEl(c) {
    const div = document.createElement('div');
    div.style.cssText = 'display:flex;gap:.4rem;margin-bottom:.6rem;';
    div.innerHTML = `
        <div style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;flex-shrink:0;">
            ${escapeHtml(c.iniciales)}
        </div>
        <div style="flex:1;background:var(--card-bg);border:1px solid var(--border-color);border-radius:8px;padding:.5rem .65rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem;">
                <span style="font-size:.78rem;font-weight:600;color:var(--text-primary);">${escapeHtml(c.usuario)}</span>
                <span style="font-size:.65rem;color:var(--text-muted);">${c.fecha}</span>
            </div>
            <p style="margin:0;font-size:.82rem;color:var(--text-primary);line-height:1.4;white-space:pre-wrap;">${escapeHtml(c.contenido)}</p>
        </div>
    `;
    return div;
}

function enviarComentario() {
    const textarea = document.getElementById('comentario-nuevo');
    const contenido = textarea.value.trim();
    if (!contenido) return;

    const tareaId = document.getElementById('detalle-tarea-id').value;
    fetch(`/kanban/tareas/${tareaId}/comentarios`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ contenido }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById('comentarios-lista');
            container.insertBefore(crearComentarioEl(data.comentario), container.firstChild);
            textarea.value = '';
            const current = container.children.length;
            document.getElementById('comentarios-count').textContent = `(${current})`;
        }
    });
}

// Utility
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Close with Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && detalleTareaId) cerrarDetalle();
});

// ACTIVIDAD EN DETALLE
function toggleDetalleActividad() {
    const el = document.getElementById('detalle-actividad');
    const icon = document.getElementById('actividad-toggle-icon');
    if (el.style.display === 'none') {
        el.style.display = 'block';
        icon.className = 'bi bi-chevron-up';
        cargarActividadTarea();
    } else {
        el.style.display = 'none';
        icon.className = 'bi bi-chevron-down';
    }
}

function cargarActividadTarea() {
    const tareaId = document.getElementById('detalle-tarea-id').value;
    const tableroId = document.getElementById('detalle-tablero-id').value;
    const cont = document.getElementById('detalle-actividad');
    cont.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:.78rem;padding:.5rem;">Cargando...</div>';

    fetch(`/kanban/${tableroId}/actividad`, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
        // Filtrar solo actividades de esta tarea
        const acts = (data.actividades || []).filter(a => a.tarea_id == tareaId);
        if (acts.length === 0) {
            cont.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:.78rem;padding:.5rem;">Sin actividad.</div>';
            return;
        }
        const iconos = {
            created: 'bi-plus-circle', updated: 'bi-pencil', moved: 'bi-arrows-move',
            commented: 'bi-chat-dots', attachment: 'bi-paperclip', assigned: 'bi-person-plus',
            deleted: 'bi-trash', checklist: 'bi-check2-square',
        };
        cont.innerHTML = acts.map(a => {
            const icono = iconos[a.accion] || 'bi-circle';
            const fecha = new Date(a.created_at).toLocaleString('es-CL', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
            return `<div style="display:flex;gap:.4rem;align-items:flex-start;padding:.3rem 0;border-bottom:1px solid var(--border-color);font-size:.75rem;">
                <i class="bi ${icono}" style="margin-top:.1rem;color:var(--primary-color);"></i>
                <div style="flex:1;">
                    <strong>${escapeHtml(a.usuario?.name || 'Sistema')}</strong>
                    <span style="color:var(--text-muted);"> — ${escapeHtml(a.detalle || a.accion)}</span>
                    <div style="font-size:.65rem;color:var(--text-muted);">${fecha}</div>
                </div>
            </div>`;
        }).join('');
    })
    .catch(() => { cont.innerHTML = '<div style="color:#ef4444;font-size:.78rem;">Error</div>'; });
}
</script>
