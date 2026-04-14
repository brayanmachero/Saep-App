@extends('layouts.app')
@section('title', $kanban->nombre)
@section('content')
<div class="page-container">

    {{-- HEADER --}}
    <div class="page-header" style="flex-wrap:wrap;gap:.75rem;">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-kanban" style="color:var(--primary-color)"></i> {{ $kanban->nombre }}
            </h2>
            <p class="page-subheading">
                {{ $kanban->descripcion ?? 'Tablero Kanban' }}
                @if($kanban->centroCosto)
                    — <i class="bi bi-building"></i> {{ $kanban->centroCosto->nombre }}
                @endif
            </p>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            {{-- Vista switcher --}}
            <div style="display:flex;border-radius:8px;overflow:hidden;border:1px solid var(--border-color);">
                <a href="{{ route('kanban.show', [$kanban, 'vista' => 'kanban']) }}"
                   style="padding:.4rem .75rem;font-size:.78rem;font-weight:600;text-decoration:none;{{ $vista === 'kanban' ? 'background:var(--primary-color);color:#fff;' : 'background:var(--card-bg);color:var(--text-muted);' }}">
                    <i class="bi bi-kanban"></i> Kanban
                </a>
                <a href="{{ route('kanban.show', [$kanban, 'vista' => 'lista']) }}"
                   style="padding:.4rem .75rem;font-size:.78rem;font-weight:600;text-decoration:none;border-left:1px solid var(--border-color);{{ $vista === 'lista' ? 'background:var(--primary-color);color:#fff;' : 'background:var(--card-bg);color:var(--text-muted);' }}">
                    <i class="bi bi-list-ul"></i> Lista
                </a>
                <a href="{{ route('kanban.show', [$kanban, 'vista' => 'calendario']) }}"
                   style="padding:.4rem .75rem;font-size:.78rem;font-weight:600;text-decoration:none;border-left:1px solid var(--border-color);{{ $vista === 'calendario' ? 'background:var(--primary-color);color:#fff;' : 'background:var(--card-bg);color:var(--text-muted);' }}">
                    <i class="bi bi-calendar3"></i> Calendario
                </a>
            </div>
            <a href="{{ route('kanban.index') }}" class="btn-secondary" style="padding:.4rem .75rem;font-size:.78rem;">
                <i class="bi bi-arrow-left"></i> Tableros
            </a>
        </div>
    </div>

    @include('partials._alerts')

    {{-- BARRA DE FILTROS + PANELES --}}
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;">
        <form method="GET" action="{{ route('kanban.show', $kanban) }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;flex:1;">
            <input type="hidden" name="vista" value="{{ $vista }}">
            <select name="filtro_asignado" class="form-input" style="width:auto;min-width:150px;padding:.3rem .5rem;font-size:.78rem;">
                <option value="">👤 Todos los asignados</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" {{ ($filtrosActivos['filtro_asignado'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="filtro_prioridad" class="form-input" style="width:auto;min-width:120px;padding:.3rem .5rem;font-size:.78rem;">
                <option value="">🎯 Todas las prioridades</option>
                <option value="ALTA" {{ ($filtrosActivos['filtro_prioridad'] ?? '') === 'ALTA' ? 'selected' : '' }}>🔴 Alta</option>
                <option value="MEDIA" {{ ($filtrosActivos['filtro_prioridad'] ?? '') === 'MEDIA' ? 'selected' : '' }}>🟡 Media</option>
                <option value="BAJA" {{ ($filtrosActivos['filtro_prioridad'] ?? '') === 'BAJA' ? 'selected' : '' }}>🟢 Baja</option>
            </select>
            @if($kanban->etiquetas->isNotEmpty())
            <select name="filtro_etiqueta" class="form-input" style="width:auto;min-width:140px;padding:.3rem .5rem;font-size:.78rem;">
                <option value="">🏷️ Todas las etiquetas</option>
                @foreach($kanban->etiquetas as $et)
                    <option value="{{ $et->id }}" {{ ($filtrosActivos['filtro_etiqueta'] ?? '') == $et->id ? 'selected' : '' }}>{{ $et->nombre }}</option>
                @endforeach
            </select>
            @endif
            <button type="submit" class="btn-premium" style="padding:.3rem .65rem;font-size:.78rem;">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            @if(array_filter($filtrosActivos))
            <a href="{{ route('kanban.show', [$kanban, 'vista' => $vista]) }}" style="font-size:.78rem;color:#ef4444;text-decoration:none;font-weight:600;">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
            @endif
        </form>
        <div style="display:flex;gap:.4rem;">
            <button onclick="togglePanel('panel-miembros')" class="btn-secondary" style="padding:.3rem .6rem;font-size:.78rem;" title="Miembros">
                <i class="bi bi-people"></i> {{ $kanban->miembros->count() }}
            </button>
            <button onclick="togglePanel('panel-actividad')" class="btn-secondary" style="padding:.3rem .6rem;font-size:.78rem;" title="Actividad">
                <i class="bi bi-clock-history"></i>
            </button>
        </div>
    </div>

    {{-- PANEL MIEMBROS --}}
    <div id="panel-miembros" style="display:none;margin-bottom:1rem;">
        <div class="glass-card" style="padding:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                <h4 style="margin:0;font-size:.92rem;font-weight:700;"><i class="bi bi-people" style="color:var(--primary-color);"></i> Miembros del Tablero</h4>
                <button onclick="togglePanel('panel-miembros')" style="background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-muted);">&times;</button>
            </div>
            {{-- Lista de miembros --}}
            <div id="lista-miembros" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;">
                @foreach($kanban->miembros as $miembro)
                <div class="miembro-chip" data-user-id="{{ $miembro->id }}" style="display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .6rem;border-radius:20px;background:var(--primary-color)10;border:1px solid var(--primary-color)30;font-size:.78rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:var(--primary-color);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;">
                        {{ strtoupper(substr($miembro->name, 0, 2)) }}
                    </span>
                    <span style="font-weight:600;">{{ $miembro->name }}</span>
                    <span style="font-size:.65rem;color:var(--text-muted);padding:.1rem .3rem;background:var(--border-color);border-radius:8px;">{{ $miembro->pivot->rol }}</span>
                    @if($miembro->id !== $kanban->creado_por)
                    <button onclick="removerMiembro({{ $kanban->id }}, {{ $miembro->id }})" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:.75rem;line-height:1;" title="Remover">&times;</button>
                    @endif
                </div>
                @endforeach
            </div>
            {{-- Agregar miembro --}}
            <div style="display:flex;gap:.4rem;align-items:center;">
                <select id="nuevo-miembro-id" class="form-input" style="flex:1;padding:.3rem .5rem;font-size:.78rem;">
                    <option value="">— Seleccionar usuario —</option>
                    @foreach($usuarios as $u)
                        @if(!$kanban->miembros->contains('id', $u->id))
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endif
                    @endforeach
                </select>
                <select id="nuevo-miembro-rol" class="form-input" style="width:auto;padding:.3rem .5rem;font-size:.78rem;">
                    <option value="editor">Editor</option>
                    <option value="viewer">Viewer</option>
                    <option value="admin">Admin</option>
                </select>
                <button onclick="agregarMiembro({{ $kanban->id }})" class="btn-premium" style="padding:.3rem .6rem;font-size:.78rem;">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- PANEL ACTIVIDAD --}}
    <div id="panel-actividad" style="display:none;margin-bottom:1rem;">
        <div class="glass-card" style="padding:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                <h4 style="margin:0;font-size:.92rem;font-weight:700;"><i class="bi bi-clock-history" style="color:var(--primary-color);"></i> Actividad Reciente</h4>
                <button onclick="togglePanel('panel-actividad')" style="background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-muted);">&times;</button>
            </div>
            <div id="actividad-contenido" style="max-height:300px;overflow-y:auto;">
                <div style="text-align:center;color:var(--text-muted);font-size:.82rem;padding:1rem;">
                    <i class="bi bi-arrow-repeat"></i> Cargando...
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Detalle de Tarea (editar, comentarios, checklist, adjuntos) --}}
    @include('kanban._modal_detalle')

    {{-- MODAL: Nueva Tarea --}}
    <div id="modal-nueva-tarea" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;justify-content:center;align-items:center;backdrop-filter:blur(2px);" onclick="if(event.target===this)cerrarModal()">
        <div class="glass-card" style="padding:1.5rem;width:90%;max-width:520px;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 style="margin:0;font-size:1rem;font-weight:700;"><i class="bi bi-plus-circle" style="color:var(--primary-color);"></i> Nueva Tarea</h3>
                <button onclick="cerrarModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);">&times;</button>
            </div>
            <form method="POST" action="{{ route('kanban.tareas.store', $kanban) }}" id="form-nueva-tarea">
                @csrf
                <input type="hidden" name="columna_id" id="input-columna-id">

                <div style="margin-bottom:.75rem;">
                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Título *</label>
                    <input type="text" name="titulo" class="form-input" required maxlength="300" placeholder="¿Qué necesitas hacer?">
                </div>
                <div style="margin-bottom:.75rem;">
                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Descripción</label>
                    <textarea name="descripcion" class="form-input" rows="2" placeholder="Detalles opcionales"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Prioridad</label>
                        <select name="prioridad" class="form-input">
                            <option value="BAJA">🟢 Baja</option>
                            <option value="MEDIA" selected>🟡 Media</option>
                            <option value="ALTA">🔴 Alta</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Asignado a</label>
                        <select name="asignado_a" class="form-input">
                            <option value="">— Sin asignar —</option>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" class="form-input">
                    </div>
                    <div>
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Fecha vencimiento</label>
                        <input type="date" name="fecha_vencimiento" class="form-input">
                    </div>
                </div>
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Centro de Costo</label>
                    <select name="centro_costo_id" class="form-input">
                        <option value="">— Sin asignar —</option>
                        @foreach($centros as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @if($kanban->etiquetas->isNotEmpty())
                <div style="margin-bottom:1rem;">
                    <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Etiquetas</label>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                        @foreach($kanban->etiquetas as $et)
                        <label style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;border-radius:6px;font-size:.75rem;cursor:pointer;border:1px solid {{ $et->color }}20;background:{{ $et->color }}15;">
                            <input type="checkbox" name="etiquetas[]" value="{{ $et->id }}" style="width:14px;height:14px;">
                            <span style="color:{{ $et->color }};font-weight:600;">{{ $et->nombre }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
                <button type="submit" class="btn-premium" style="width:100%;justify-content:center;">
                    <i class="bi bi-check-lg"></i> Crear Tarea
                </button>
            </form>
        </div>
    </div>

    {{-- ============================================= --}}
    {{-- VISTA KANBAN --}}
    {{-- ============================================= --}}
    @if($vista === 'kanban')
    <div id="kanban-board" style="display:flex;gap:1rem;overflow-x:auto;padding-bottom:1rem;min-height:60vh;">
        @foreach($kanban->columnas as $columna)
        <div class="kanban-column" data-columna-id="{{ $columna->id }}" style="min-width:280px;max-width:320px;flex-shrink:0;">
            {{-- Column header --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .75rem;border-radius:10px 10px 0 0;background:{{ $columna->color }}15;border-bottom:3px solid {{ $columna->color }};">
                <div style="display:flex;align-items:center;gap:.4rem;">
                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $columna->color }};"></span>
                    <span style="font-size:.82rem;font-weight:700;color:var(--text-primary);">{{ $columna->nombre }}</span>
                    <span style="font-size:.7rem;background:var(--text-muted);color:#fff;padding:0 .4rem;border-radius:8px;font-weight:600;">{{ $columna->tareas->count() }}</span>
                </div>
                <button onclick="abrirModal({{ $columna->id }})" style="background:none;border:none;cursor:pointer;font-size:.9rem;color:var(--text-muted);" title="Añadir tarea">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            {{-- Tasks container (sortable) --}}
            <div class="kanban-tasks" data-columna-id="{{ $columna->id }}" style="padding:.5rem;min-height:100px;background:var(--card-bg);border-radius:0 0 10px 10px;border:1px solid var(--border-color);border-top:none;">
                @foreach($columna->tareas as $tarea)
                @include('kanban._card', ['tarea' => $tarea])
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- ============================================= --}}
    {{-- VISTA LISTA --}}
    {{-- ============================================= --}}
    @elseif($vista === 'lista')
    <div>
        @foreach($kanban->columnas as $columna)
        <div class="glass-card" style="margin-bottom:1rem;overflow:hidden;">
            <div style="padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;background:{{ $columna->color }}10;border-bottom:2px solid {{ $columna->color }};">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <span style="width:12px;height:12px;border-radius:50%;background:{{ $columna->color }};"></span>
                    <h3 style="margin:0;font-size:.92rem;font-weight:700;">{{ $columna->nombre }}</h3>
                    <span style="font-size:.72rem;background:{{ $columna->color }};color:#fff;padding:.1rem .45rem;border-radius:8px;">{{ $columna->tareas->count() }}</span>
                </div>
                <button onclick="abrirModal({{ $columna->id }})" class="btn-premium" style="padding:.3rem .6rem;font-size:.75rem;">
                    <i class="bi bi-plus-lg"></i> Tarea
                </button>
            </div>
            @if($columna->tareas->isEmpty())
                <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:.82rem;">Sin tareas</div>
            @else
            <table class="premium-table" style="margin:0;">
                <thead>
                    <tr>
                        <th style="width:35%;">Tarea</th>
                        <th>Prioridad</th>
                        <th>Asignado</th>
                        <th>Vencimiento</th>
                        <th>Etiquetas</th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($columna->tareas as $tarea)
                    <tr style="cursor:pointer;" onclick="abrirDetalle({{ $tarea->id }})">
                        <td>
                            <div style="font-weight:600;font-size:.85rem;">{{ $tarea->titulo }}</div>
                            @if($tarea->descripcion)
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.15rem;">{{ Str::limit($tarea->descripcion, 60) }}</div>
                            @endif
                        </td>
                        <td>
                            @php $pColors = ['ALTA'=>'#dc2626','MEDIA'=>'#f59e0b','BAJA'=>'#16a34a']; @endphp
                            <span style="font-size:.72rem;padding:.15rem .4rem;border-radius:6px;background:{{ $pColors[$tarea->prioridad] }}15;color:{{ $pColors[$tarea->prioridad] }};font-weight:600;">{{ $tarea->prioridad }}</span>
                        </td>
                        <td style="font-size:.8rem;">{{ $tarea->asignado?->name ?? '—' }}</td>
                        <td style="font-size:.8rem;">
                            @if($tarea->fecha_vencimiento)
                                <span style="{{ $tarea->estaVencida ? 'color:#dc2626;font-weight:600;' : '' }}">
                                    {{ $tarea->fecha_vencimiento->format('d/m/Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @foreach($tarea->etiquetas as $et)
                            <span style="font-size:.65rem;padding:.1rem .35rem;border-radius:4px;background:{{ $et->color }}20;color:{{ $et->color }};font-weight:600;">{{ $et->nombre }}</span>
                            @endforeach
                        </td>
                        <td>
                            <form method="POST" action="{{ route('kanban.tareas.destroy', $tarea) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar esta tarea?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:.85rem;" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ============================================= --}}
    {{-- VISTA CALENDARIO --}}
    {{-- ============================================= --}}
    @elseif($vista === 'calendario')
    <div class="glass-card" style="padding:1rem;">
        <div id="kanban-calendar" style="min-height:500px;"></div>
    </div>
    @endif

</div>

{{-- ============================================= --}}
{{-- SCRIPTS --}}
{{-- ============================================= --}}
<script>
const csrfTokenGlobal = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

// Modal
function abrirModal(columnaId) {
    document.getElementById('input-columna-id').value = columnaId;
    const modal = document.getElementById('modal-nueva-tarea');
    modal.style.display = 'flex';
}
function cerrarModal() {
    document.getElementById('modal-nueva-tarea').style.display = 'none';
    document.getElementById('form-nueva-tarea').reset();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });

// Toggle Panels
function togglePanel(id) {
    const el = document.getElementById(id);
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? 'block' : 'none';
    if (isHidden && id === 'panel-actividad') cargarActividad();
}

// Miembros
function agregarMiembro(tableroId) {
    const userId = document.getElementById('nuevo-miembro-id').value;
    const rol = document.getElementById('nuevo-miembro-rol').value;
    if (!userId) return;

    fetch(`/kanban/${tableroId}/miembros`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenGlobal, 'Accept': 'application/json' },
        body: JSON.stringify({ user_id: userId, rol: rol }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || 'Error al agregar miembro');
    })
    .catch(() => alert('Error al agregar miembro'));
}

function removerMiembro(tableroId, userId) {
    if (!confirm('¿Remover este miembro del tablero?')) return;

    fetch(`/kanban/${tableroId}/miembros/${userId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfTokenGlobal, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`.miembro-chip[data-user-id="${userId}"]`)?.remove();
        } else alert(data.message || 'Error');
    })
    .catch(() => alert('Error al remover miembro'));
}

// Actividad
function cargarActividad() {
    fetch('{{ route("kanban.actividad", $kanban) }}', {
        headers: { 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => {
        const cont = document.getElementById('actividad-contenido');
        if (!data.actividades || data.actividades.length === 0) {
            cont.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:.82rem;padding:1rem;">No hay actividad registrada.</div>';
            return;
        }
        const iconos = {
            created: 'bi-plus-circle text-success', updated: 'bi-pencil', moved: 'bi-arrows-move',
            commented: 'bi-chat-dots', attachment: 'bi-paperclip', assigned: 'bi-person-plus',
            deleted: 'bi-trash text-danger', member_added: 'bi-person-plus-fill', member_removed: 'bi-person-dash',
            checklist: 'bi-check2-square',
        };
        cont.innerHTML = data.actividades.map(a => {
            const icono = iconos[a.accion] || 'bi-circle';
            const fecha = new Date(a.created_at).toLocaleString('es-CL', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
            return `<div style="display:flex;gap:.5rem;align-items:flex-start;padding:.4rem 0;border-bottom:1px solid var(--border-color);font-size:.78rem;">
                <i class="bi ${icono}" style="margin-top:.15rem;color:var(--primary-color);"></i>
                <div style="flex:1;">
                    <span style="font-weight:600;">${a.usuario?.name || 'Sistema'}</span>
                    <span style="color:var(--text-muted);"> — ${a.detalle || a.accion}</span>
                    <div style="font-size:.7rem;color:var(--text-muted);">${fecha}</div>
                </div>
            </div>`;
        }).join('');
    })
    .catch(() => {
        document.getElementById('actividad-contenido').innerHTML = '<div style="color:#ef4444;font-size:.82rem;">Error cargando actividad</div>';
    });
}
</script>

@if($vista === 'kanban')
{{-- SortableJS via CDN (production safe) --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                    || '{{ csrf_token() }}';

    document.querySelectorAll('.kanban-tasks').forEach(container => {
        Sortable.create(container, {
            group: 'kanban',
            animation: 150,
            ghostClass: 'kanban-ghost',
            dragClass: 'kanban-dragging',
            handle: '.kanban-card',
            onEnd: function(evt) {
                const tareaId = evt.item.dataset.tareaId;
                const newColumnaId = evt.to.dataset.columnaId;
                const newOrden = evt.newIndex;

                fetch(`/kanban/tareas/${tareaId}/mover`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ columna_id: newColumnaId, orden: newOrden }),
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Error moviendo tarea');
                        location.reload();
                    }
                    // Actualizar contadores
                    document.querySelectorAll('.kanban-column').forEach(col => {
                        const count = col.querySelector('.kanban-tasks').children.length;
                        const badge = col.querySelector('[style*="border-radius:8px"][style*="font-weight:600"]');
                        if (badge && badge.textContent !== undefined) {
                            badge.textContent = count;
                        }
                    });
                })
                .catch(() => location.reload());
            }
        });
    });
});
</script>
<style>
.kanban-ghost { opacity: .4; }
.kanban-dragging { transform: rotate(2deg); box-shadow: 0 10px 30px rgba(0,0,0,.15); }
</style>
@endif

@if($vista === 'calendario')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calEl = document.getElementById('kanban-calendar');
    const calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: '{{ route("kanban.calendar-data", $kanban) }}',
        eventClick: function(info) {
            abrirDetalle(info.event.id);
        },
        height: 'auto',
    });
    calendar.render();
});
</script>
@endif

@endsection
