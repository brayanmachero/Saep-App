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
                    <tr>
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
            alert(info.event.title + '\n' + (info.event.extendedProps.columna || ''));
        },
        height: 'auto',
    });
    calendar.render();
});
</script>
@endif

@endsection
