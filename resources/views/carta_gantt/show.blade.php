@extends('layouts.app')
@section('title', $cartaGantt->nombre)
@section('content')
<div class="page-container" style="max-width:1400px">
    <div class="page-header">
        <div>
            <h1>{{ $cartaGantt->nombre }}</h1>
            <p style="color:var(--text-muted);margin:0">
                Año {{ $cartaGantt->anio }} 
                @if($cartaGantt->centroCosto) · {{ $cartaGantt->centroCosto->nombre }} @endif
                · <span class="badge badge-{{ $cartaGantt->estado === 'activo' ? 'success' : 'secondary' }}">{{ ucfirst($cartaGantt->estado) }}</span>
            </p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('carta-gantt.edit', $cartaGantt) }}" class="btn-secondary">
                <i class="bi bi-pencil-fill"></i> Editar
            </a>
            <a href="{{ route('carta-gantt.index') }}" class="btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    @include('partials._alerts')

    {{-- Avance Global --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.5rem">
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
            <div>
                <div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Avance Global</div>
                <div style="font-size:2rem;font-weight:700;color:var(--primary)">{{ $cartaGantt->porcentajeRealizado }}%</div>
            </div>
            <div style="flex:1;min-width:200px">
                <div style="background:#e5e7eb;border-radius:9999px;height:14px">
                    <div style="width:{{ $cartaGantt->porcentajeRealizado }}%;background:linear-gradient(90deg,var(--primary),var(--accent));height:14px;border-radius:9999px;transition:width .3s"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grid Gantt --}}
    @foreach($cartaGantt->categorias as $categoria)
    <div class="glass-card" style="margin-bottom:1.5rem;padding:0;overflow:hidden">
        {{-- Header categoría --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:rgba(99,102,241,.08);border-bottom:1px solid var(--border-color)">
            <h3 style="margin:0;font-size:1rem">{{ $categoria->nombre }}</h3>
            <button class="btn-secondary" style="padding:.3rem .75rem;font-size:.8rem"
                    onclick="toggleAddActividad({{ $categoria->id }})">
                <i class="bi bi-plus"></i> Actividad
            </button>
        </div>

        {{-- Form nueva actividad (oculto) --}}
        <div id="addAct-{{ $categoria->id }}" style="display:none;padding:1rem 1.25rem;background:#f9fafb;border-bottom:1px solid var(--border-color)">
            <form method="POST" action="{{ route('carta-gantt.actividades.store', $categoria) }}"
                  style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div class="form-group" style="flex:1;min-width:240px;margin:0">
                    <label style="font-size:.8rem">Nombre de la actividad</label>
                    <input type="text" name="nombre" required class="form-control" placeholder="Ej: Inspección de EPP">
                </div>
                <div class="form-group" style="width:130px;margin:0">
                    <label style="font-size:.8rem">Responsable (nombre)</label>
                    <input type="text" name="responsable" class="form-control" placeholder="Nombre">
                </div>
                <button type="submit" class="btn-premium" style="padding:.45rem 1rem">Agregar</button>
                <button type="button" class="btn-secondary" style="padding:.45rem .75rem"
                        onclick="toggleAddActividad({{ $categoria->id }})">Cancelar</button>
            </form>
        </div>

        {{-- Tabla Gantt --}}
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr style="background:#f1f5f9">
                    <th style="padding:.6rem 1.25rem;text-align:left;font-weight:600;min-width:220px;border-bottom:1px solid var(--border-color)">Actividad</th>
                    <th style="padding:.6rem .5rem;text-align:left;border-bottom:1px solid var(--border-color);min-width:100px">Responsable</th>
                    @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mes)
                    <th style="padding:.6rem .4rem;text-align:center;border-bottom:1px solid var(--border-color);width:54px">{{ $mes }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @forelse($categoria->actividades as $act)
            <tr style="border-bottom:1px solid var(--border-color)">
                <td style="padding:.6rem 1.25rem;font-weight:500">{{ $act->nombre }}</td>
                <td style="padding:.6rem .5rem;color:var(--text-muted);font-size:.8rem">
                    {{ $act->responsable ?? '—' }}
                </td>
                @for($m = 1; $m <= 12; $m++)
                @php
                    $seg = $act->seguimientoPorMes[$m] ?? ['programado'=>false,'realizado'=>false];
                    $realizado   = (bool)($seg['realizado']   ?? false);
                    $planificado = (bool)($seg['programado']  ?? false);
                @endphp
                <td style="text-align:center;padding:.4rem .25rem">
                    <button type="button"
                            class="gantt-cell {{ $realizado ? 'gantt-done' : ($planificado ? 'gantt-plan' : '') }}"
                            data-actividad="{{ $act->id }}"
                            data-mes="{{ $m }}"
                            data-realizado="{{ $realizado ? '1' : '0' }}"
                            onclick="toggleSeguimiento(this)"
                            title="{{ $realizado ? 'Realizado — click para desmarcar' : ($planificado ? 'Planificado — click para marcar realizado' : 'Click para marcar') }}">
                        @if($realizado)
                            <i class="bi bi-check2"></i>
                        @elseif($planificado)
                            <i class="bi bi-circle"></i>
                        @else
                            <span style="opacity:.2">·</span>
                        @endif
                    </button>
                </td>
                @endfor
            </tr>
            @empty
            <tr><td colspan="14" style="padding:1rem 1.25rem;color:var(--text-muted);font-style:italic">
                Sin actividades — haz clic en "+ Actividad" para agregar.
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endforeach

    {{-- Agregar nueva categoría --}}
    <div class="glass-card" style="border:2px dashed var(--border-color);background:transparent">
        <div style="text-align:center;padding:.5rem">
            <button class="btn-secondary" onclick="toggleAddCat()">
                <i class="bi bi-folder-plus"></i> Agregar Categoría
            </button>
        </div>
        <div id="addCat" style="display:none;padding:1rem">
            <form method="POST" action="{{ route('carta-gantt.categorias.store', $cartaGantt) }}"
                  style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div class="form-group" style="flex:1;min-width:240px;margin:0">
                    <label style="font-size:.8rem">Nombre de la categoría</label>
                    <input type="text" name="nombre" required class="form-control" placeholder="Ej: Capacitaciones">
                </div>
                <div class="form-group" style="width:80px;margin:0">
                    <label style="font-size:.8rem">Orden</label>
                    <input type="number" name="orden" value="{{ $cartaGantt->categorias->count() + 1 }}" class="form-control" min="1">
                </div>
                <button type="submit" class="btn-premium" style="padding:.45rem 1rem">Agregar</button>
                <button type="button" class="btn-secondary" style="padding:.45rem .75rem" onclick="toggleAddCat()">Cancelar</button>
            </form>
        </div>
    </div>
</div>

<style>
.gantt-cell {
    width: 32px; height: 32px; border-radius: 6px;
    border: 1px solid #d1d5db;
    background: transparent; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: all .15s; font-size: .85rem; color: #6b7280;
}
.gantt-cell:hover { border-color: var(--primary); color: var(--primary); }
.gantt-plan { background: #e0e7ff; border-color: #818cf8; color: #4338ca; }
.gantt-done { background: #dcfce7; border-color: #4ade80; color: #16a34a; }
.gantt-done:hover { background: #bbf7d0; }
</style>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function toggleAddActividad(catId) {
    const el = document.getElementById('addAct-' + catId);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function toggleAddCat() {
    const el = document.getElementById('addCat');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

async function toggleSeguimiento(btn) {
    const actividadId = btn.dataset.actividad;
    const mes = parseInt(btn.dataset.mes);
    const currentState = btn.dataset.realizado === '1';
    const newState = !currentState;

    btn.disabled = true;
    btn.style.opacity = '.5';

    try {
        const res = await fetch(`/carta-gantt/actividades/${actividadId}/seguimiento`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ mes, realizado: newState })
        });

        if (!res.ok) throw new Error('Error al actualizar');

        btn.dataset.realizado = newState ? '1' : '0';

        if (newState) {
            btn.className = 'gantt-cell gantt-done';
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            btn.title = 'Realizado — click para desmarcar';
        } else {
            btn.className = 'gantt-cell gantt-plan';
            btn.innerHTML = '<i class="bi bi-circle"></i>';
            btn.title = 'Planificado — click para marcar realizado';
        }

        // Actualizar porcentaje global en la barra
        updateProgress();
    } catch (e) {
        alert('No se pudo actualizar el seguimiento. Intenta nuevamente.');
    }

    btn.disabled = false;
    btn.style.opacity = '1';
}

async function updateProgress() {
    try {
        const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        // Solo recalculamos visualmente basándonos en celdas actuales
        const total = document.querySelectorAll('.gantt-cell').length;
        const done  = document.querySelectorAll('.gantt-done').length;
        if (total === 0) return;
        const pct = Math.round((done / total) * 100);
        const bar = document.querySelector('[style*="linear-gradient"]');
        if (bar) bar.style.width = pct + '%';
        const num = document.querySelector('[style*="font-size:2rem"]');
        if (num) num.textContent = pct + '%';
    } catch(e) {}
}
</script>
@endsection
