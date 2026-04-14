@extends('layouts.app')
@section('title','Tablero Kanban')
@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-kanban" style="color:var(--primary-color)"></i> Tableros Kanban</h2>
            <p class="page-subheading">Gestión visual de tareas con arrastrar y soltar</p>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <a href="{{ route('kanban.dashboard') }}" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
                <i class="bi bi-graph-up-arrow"></i> Dashboard
            </a>
            <a href="{{ route('kanban.buscar') }}" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
                <i class="bi bi-search"></i> Buscar
            </a>
            <a href="{{ route('kanban.mis-tareas') }}" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
                <i class="bi bi-person-check"></i> Mis Tareas
                @if($misTareasCount > 0)
                <span style="background:#ef4444;color:#fff;padding:.1rem .4rem;border-radius:10px;font-size:.7rem;margin-left:.3rem;font-weight:700;">{{ $misTareasCount }}</span>
                @endif
            </a>
            @if(auth()->user()->tieneAcceso('kanban', 'puede_crear'))
            <button onclick="document.getElementById('modal-plantilla').style.display='flex'" class="btn-secondary" style="padding:.45rem .85rem;font-size:.82rem;">
                <i class="bi bi-clipboard2-plus"></i> Desde Plantilla
            </button>
            <a href="{{ route('kanban.create') }}" class="btn-premium">
                <i class="bi bi-plus-lg"></i> Nuevo Tablero
            </a>
            @endif
        </div>
    </div>

    @include('partials._alerts')

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Tableros</div>
            <div style="font-size:1.8rem;font-weight:700;color:var(--primary-color);">{{ $tableros->count() }}</div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;text-align:center;">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;">Total Tareas</div>
            <div style="font-size:1.8rem;font-weight:700;color:#3b82f6;">{{ $tableros->sum('tareas_count') }}</div>
        </div>
    </div>

    {{-- Grid de tableros --}}
    @if($tableros->isEmpty())
        <div class="glass-card" style="padding:3rem;text-align:center;">
            <i class="bi bi-kanban" style="font-size:3rem;color:var(--text-muted);opacity:.4;"></i>
            <p style="margin-top:1rem;color:var(--text-muted);">No hay tableros creados aún.</p>
            @if(auth()->user()->tieneAcceso('kanban', 'puede_crear'))
            <a href="{{ route('kanban.create') }}" class="btn-premium" style="margin-top:1rem;display:inline-flex;">
                <i class="bi bi-plus-lg"></i> Crear primer tablero
            </a>
            @endif
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;">
            @foreach($tableros as $tablero)
            <a href="{{ route('kanban.show', $tablero) }}" class="glass-card tablero-card" style="padding:1.25rem;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s;cursor:pointer;position:relative;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 25px rgba(0,0,0,.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">

                {{-- Menú de acciones (tres puntos) --}}
                <div class="tablero-menu-wrap" style="position:absolute;top:.6rem;right:.6rem;z-index:10;" onclick="event.stopPropagation();event.preventDefault();">
                    <button type="button" onclick="toggleTableroMenu({{ $tablero->id }})" class="tablero-menu-btn" style="background:var(--card-bg);border:1px solid var(--border-color);border-radius:6px;padding:.25rem .45rem;cursor:pointer;font-size:.78rem;color:var(--text-muted);transition:all .12s;line-height:1;" title="Opciones">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <div id="tablero-menu-{{ $tablero->id }}" class="tablero-dropdown" style="display:none;position:absolute;top:100%;right:0;margin-top:.3rem;background:var(--card-bg);border:1px solid var(--border-color);border-radius:8px;box-shadow:0 8px 25px rgba(0,0,0,.15);min-width:160px;overflow:hidden;z-index:20;">
                        {{-- Duplicar --}}
                        <form method="POST" action="{{ route('kanban.duplicar', $tablero) }}">
                            @csrf
                            <button type="submit" style="width:100%;text-align:left;background:none;border:none;padding:.55rem .85rem;cursor:pointer;font-size:.78rem;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;transition:background .1s;" onmouseover="this.style.background='var(--bg-color)'" onmouseout="this.style.background='none'">
                                <i class="bi bi-copy" style="color:var(--primary-color);font-size:.82rem;"></i> Duplicar tablero
                            </button>
                        </form>
                        {{-- Eliminar (archivar) --}}
                        <form method="POST" action="{{ route('kanban.destroy', $tablero) }}" onsubmit="return confirm('¿Archivar el tablero «{{ addslashes($tablero->nombre) }}»?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="width:100%;text-align:left;background:none;border:none;padding:.55rem .85rem;cursor:pointer;font-size:.78rem;color:#dc2626;display:flex;align-items:center;gap:.5rem;transition:background .1s;border-top:1px solid var(--border-color);" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">
                                <i class="bi bi-archive" style="font-size:.82rem;"></i> Archivar tablero
                            </button>
                        </form>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;padding-right:2rem;">
                    <h3 style="font-size:1.05rem;font-weight:700;margin:0;color:var(--text-primary);min-width:0;">
                        <i class="bi bi-kanban" style="color:var(--primary-color);margin-right:.4rem;"></i>
                        {{ $tablero->nombre }}
                    </h3>
                    <span style="font-size:.7rem;background:var(--primary-color);color:#fff;padding:.15rem .5rem;border-radius:10px;font-weight:600;white-space:nowrap;flex-shrink:0;">
                        {{ $tablero->tareas_count }} tareas
                    </span>
                </div>
                @if($tablero->descripcion)
                <p style="font-size:.82rem;color:var(--text-muted);margin:0 0 .75rem;line-height:1.4;">
                    {{ Str::limit($tablero->descripcion, 100) }}
                </p>
                @endif
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:.75rem;color:var(--text-muted);">
                    <span>
                        <i class="bi bi-person"></i> {{ $tablero->creador?->name ?? 'Sistema' }}
                    </span>
                    @if($tablero->miembros->count() > 1)
                    <span title="{{ $tablero->miembros->pluck('name')->join(', ') }}">
                        <i class="bi bi-people"></i> {{ $tablero->miembros->count() }}
                    </span>
                    @endif
                    @if($tablero->centroCosto)
                    <span>
                        <i class="bi bi-building"></i> {{ $tablero->centroCosto->nombre }}
                    </span>
                    @endif
                    <span>{{ $tablero->updated_at->diffForHumans() }}</span>
                </div>
            </a>
            @endforeach
        </div>
    @endif
</div>

{{-- Modal: Crear desde Plantilla --}}
<div id="modal-plantilla" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;justify-content:center;align-items:center;backdrop-filter:blur(2px);" onclick="if(event.target===this)this.style.display='none'">
    <div class="glass-card" style="padding:1.5rem;width:90%;max-width:520px;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="margin:0;font-size:1rem;font-weight:700;"><i class="bi bi-clipboard2-plus" style="color:var(--primary-color);"></i> Crear desde Plantilla</h3>
            <button onclick="document.getElementById('modal-plantilla').style.display='none'" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <form method="POST" action="{{ route('kanban.plantilla') }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.3rem;">Nombre del tablero *</label>
                <input type="text" name="nombre" class="form-input" required maxlength="200" placeholder="Ej: Sprint Q1 2025">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.3rem;">Descripción</label>
                <textarea name="descripcion" class="form-input" rows="2" placeholder="Descripción opcional"></textarea>
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.3rem;">Centro de Costo</label>
                <select name="centro_costo_id" class="form-input">
                    <option value="">— Sin asignar —</option>
                    @foreach(\App\Models\CentroCosto::orderBy('nombre')->get() as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.5rem;">Plantilla *</label>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    @php
                    $plantillas = [
                        ['id' => 'proyecto', 'icon' => 'bi-folder2-open', 'name' => 'Proyecto', 'desc' => 'Backlog → Diseño → Desarrollo → Testing → Deploy'],
                        ['id' => 'sprint',   'icon' => 'bi-lightning-charge', 'name' => 'Sprint Ágil', 'desc' => 'Backlog → Sprint → En Progreso → Review → Done'],
                        ['id' => 'rrhh',     'icon' => 'bi-person-badge', 'name' => 'Gestión RRHH', 'desc' => 'Solicitudes → En Revisión → Aprobado → Implementado'],
                        ['id' => 'sst',      'icon' => 'bi-shield-check', 'name' => 'SST / Seguridad', 'desc' => 'Identificados → Evaluación → Mitigación → Cerrado'],
                        ['id' => 'basico',   'icon' => 'bi-kanban', 'name' => 'Básico', 'desc' => 'Por Hacer → En Progreso → Completado'],
                    ];
                    @endphp
                    @foreach($plantillas as $pl)
                    <label style="display:flex;align-items:center;gap:.75rem;padding:.65rem .85rem;border-radius:8px;border:2px solid var(--border-color);cursor:pointer;transition:border-color .12s;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--border-color)'" onclick="this.parentElement.querySelectorAll('label').forEach(l => l.style.borderColor='var(--border-color)');this.style.borderColor='var(--primary-color)';">
                        <input type="radio" name="plantilla" value="{{ $pl['id'] }}" required style="width:16px;height:16px;">
                        <i class="bi {{ $pl['icon'] }}" style="font-size:1.1rem;color:var(--primary-color);"></i>
                        <div>
                            <div style="font-size:.85rem;font-weight:700;">{{ $pl['name'] }}</div>
                            <div style="font-size:.72rem;color:var(--text-muted);">{{ $pl['desc'] }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn-premium" style="width:100%;justify-content:center;">
                <i class="bi bi-check-lg"></i> Crear Tablero
            </button>
        </form>
    </div>
</div>

<script>
function toggleTableroMenu(id) {
    // Close all other menus first
    document.querySelectorAll('.tablero-dropdown').forEach(d => {
        if (d.id !== 'tablero-menu-' + id) d.style.display = 'none';
    });
    const menu = document.getElementById('tablero-menu-' + id);
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
// Close menus on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.tablero-menu-wrap')) {
        document.querySelectorAll('.tablero-dropdown').forEach(d => d.style.display = 'none');
    }
});
</script>
@endsection
