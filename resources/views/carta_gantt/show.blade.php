@extends('layouts.app')
@section('title', $cartaGantt->nombre)
@section('content')
<div class="page-container">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-bar-chart-steps" style="color:var(--primary-color)"></i> {{ $cartaGantt->nombre }}</h2>
            <p class="page-subheading">
                <code style="background:var(--surface-bg);padding:.15rem .5rem;border-radius:6px;font-weight:600;font-size:.82rem">{{ $cartaGantt->codigo ?? '—' }}</code>
                · Año {{ $cartaGantt->anio }}
                @if($cartaGantt->centroCosto) · {{ $cartaGantt->centroCosto->nombre }} @endif
                · <span class="badge {{ $cartaGantt->estadoBadge }}">{{ ucfirst(strtolower($cartaGantt->estado)) }}</span>
            </p>
        </div>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('carta-gantt.edit', $cartaGantt) }}" class="btn-ghost"><i class="bi bi-pencil-fill"></i> Editar</a>
            <a href="{{ route('carta-gantt.index') }}" class="btn-ghost"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @include('partials._alerts')

    {{-- Avance Global + Resumen --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.5rem">
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
            <div>
                <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Avance Global</div>
                <div id="progressNum" style="font-size:2rem;font-weight:700;color:var(--primary)">{{ $cartaGantt->porcentajeRealizado }}%</div>
            </div>
            <div style="flex:1;min-width:200px">
                <div style="background:#e5e7eb;border-radius:9999px;height:14px">
                    <div id="progressBar" style="width:{{ $cartaGantt->porcentajeRealizado }}%;background:linear-gradient(90deg,var(--primary),var(--accent));height:14px;border-radius:9999px;transition:width .3s"></div>
                </div>
            </div>
            <div style="display:flex;gap:1.5rem;font-size:.85rem;color:var(--text-muted)">
                <span><i class="bi bi-list-check"></i> {{ $cartaGantt->actividadesTotales }} actividades</span>
                @if($cartaGantt->actividadesVencidas > 0)
                <span style="color:var(--danger)"><i class="bi bi-exclamation-triangle-fill"></i> {{ $cartaGantt->actividadesVencidas }} vencidas</span>
                @endif
                @if($cartaGantt->responsable)
                <span><i class="bi bi-person-fill"></i> {{ $cartaGantt->responsable->name }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Grid Gantt por Categoría --}}
    @foreach($cartaGantt->categorias as $categoria)
    <div class="glass-card" style="margin-bottom:1.5rem;padding:0;overflow:hidden">

        {{-- Categoría Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.25rem;background:rgba(15,27,76,.06);border-bottom:1px solid var(--border-color)">
            <h3 style="margin:0;font-size:1rem;font-weight:600"><i class="bi bi-folder2-open" style="color:var(--primary-color);margin-right:.35rem"></i> {{ $categoria->nombre }}</h3>
            <div style="display:flex;gap:.4rem">
                <button class="btn-ghost" style="padding:.3rem .75rem;font-size:.78rem"
                        onclick="toggleAddActividad({{ $categoria->id }})">
                    <i class="bi bi-plus-lg"></i> Actividad
                </button>
                <form method="POST" action="{{ route('carta-gantt.categorias.destroy', $categoria) }}"
                      onsubmit="return confirm('¿Eliminar esta categoría y todas sus actividades?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost" style="padding:.3rem .5rem;font-size:.78rem;color:var(--danger)">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
            </div>
        </div>

        {{-- Formulario Agregar Actividad (oculto) --}}
        <div id="addAct-{{ $categoria->id }}" style="display:none;padding:1.25rem;background:#f9fafb;border-bottom:1px solid var(--border-color)">
            <form method="POST" action="{{ route('carta-gantt.actividades.store', $categoria) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-bottom:.75rem">
                    <div class="form-group" style="margin:0">
                        <label style="font-size:.78rem">Nombre de la actividad *</label>
                        <input type="text" name="nombre" required class="form-input" placeholder="Ej: Inspección de EPP">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size:.78rem">Responsable</label>
                        <select name="responsable_id" class="form-input">
                            <option value="">— Sin asignar —</option>
                            @foreach($usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} {{ $u->apellido_paterno ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size:.78rem">Prioridad</label>
                        <select name="prioridad" class="form-input">
                            @foreach(\App\Models\SstActividad::prioridadesMap() as $k => $v)
                            <option value="{{ $k }}" {{ $k === 'MEDIA' ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size:.78rem">Periodicidad</label>
                        <select name="periodicidad" class="form-input">
                            <option value="">— Sin periodicidad —</option>
                            @foreach(\App\Models\SstActividad::periodicidadesMap() as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size:.78rem">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" class="form-input">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size:.78rem">Fecha fin</label>
                        <input type="date" name="fecha_fin" class="form-input">
                    </div>
                </div>
                {{-- Meses programados --}}
                <div style="margin-bottom:.75rem">
                    <label style="font-size:.78rem;font-weight:600;display:block;margin-bottom:.35rem">Meses programados</label>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mesNom)
                        <label style="display:flex;align-items:center;gap:.25rem;font-size:.8rem;cursor:pointer">
                            <input type="checkbox" name="meses_prog[]" value="{{ $idx + 1 }}"> {{ $mesNom }}
                        </label>
                        @endforeach
                    </div>
                </div>
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" class="btn-ghost" style="padding:.4rem .75rem" onclick="toggleAddActividad({{ $categoria->id }})">Cancelar</button>
                    <button type="submit" class="btn-premium" style="padding:.4rem 1rem"><i class="bi bi-plus-lg"></i> Agregar</button>
                </div>
            </form>
        </div>

        {{-- Tabla Gantt --}}
        <div style="overflow-x:auto">
        <table class="gantt-table">
            <thead>
                <tr>
                    <th style="text-align:left;min-width:200px;padding-left:1.25rem">Actividad</th>
                    <th style="text-align:left;min-width:110px">Responsable</th>
                    <th style="text-align:center;width:60px">Prioridad</th>
                    <th style="text-align:center;width:60px">Estado</th>
                    @foreach(['E','F','M','A','M','J','J','A','S','O','N','D'] as $mesLetra)
                    <th style="text-align:center;width:42px">{{ $mesLetra }}</th>
                    @endforeach
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($categoria->actividades as $act)
            <tr class="{{ $act->estaVencida ? 'row-vencida' : '' }}">
                <td style="padding-left:1.25rem">
                    <div style="font-weight:500;line-height:1.2">{{ $act->nombre }}</div>
                    @if($act->fecha_inicio || $act->fecha_fin)
                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem">
                        @if($act->fecha_inicio){{ $act->fecha_inicio->format('d/m') }}@endif
                        @if($act->fecha_inicio && $act->fecha_fin) → @endif
                        @if($act->fecha_fin){{ $act->fecha_fin->format('d/m') }}@endif
                        @if($act->periodicidad) · {{ \App\Models\SstActividad::periodicidadesMap()[$act->periodicidad] ?? $act->periodicidad }}@endif
                    </div>
                    @endif
                </td>
                <td style="font-size:.8rem;color:var(--text-muted)">{{ $act->nombreResponsable }}</td>
                <td style="text-align:center"><span class="badge {{ $act->prioridadBadge }}" style="font-size:.68rem">{{ $act->prioridad }}</span></td>
                <td style="text-align:center"><span class="badge {{ $act->estadoBadge }}" style="font-size:.68rem">{{ \App\Models\SstActividad::estadosMap()[$act->estado] ?? $act->estado }}</span></td>
                @for($m = 1; $m <= 12; $m++)
                @php
                    $seg = $act->seguimientoPorMes[$m] ?? ['programado'=>false,'realizado'=>false];
                    $realizado   = (bool)($seg['realizado']   ?? false);
                    $planificado = (bool)($seg['programado']  ?? false);
                @endphp
                <td style="text-align:center;padding:.4rem .15rem">
                    <button type="button"
                            class="gantt-cell {{ $realizado ? 'gantt-done' : ($planificado ? 'gantt-plan' : '') }}"
                            data-actividad="{{ $act->id }}"
                            data-mes="{{ $m }}"
                            data-realizado="{{ $realizado ? '1' : '0' }}"
                            onclick="toggleSeguimiento(this)"
                            title="{{ $realizado ? 'Realizado' : ($planificado ? 'Planificado' : 'Sin programar') }}">
                        @if($realizado) ✓
                        @elseif($planificado) ○
                        @else <span style="opacity:.15">·</span>
                        @endif
                    </button>
                </td>
                @endfor
                <td>
                    <div style="display:flex;gap:.2rem;justify-content:center">
                        <button type="button" class="gantt-action-btn" title="Plan de Acción"
                                onclick="togglePlanes({{ $act->id }})">
                            <i class="bi bi-clipboard2-check"></i>
                        </button>
                        <form method="POST" action="{{ route('carta-gantt.actividades.destroy', $act) }}"
                              onsubmit="return confirm('¿Eliminar esta actividad?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="gantt-action-btn" style="color:var(--danger)" title="Eliminar">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            {{-- Planes de Acción inline (colapsado) --}}
            <tr id="planes-{{ $act->id }}" style="display:none">
                <td colspan="17" style="padding:0;background:#f8fafc">
                    <div style="padding:.75rem 1.25rem;border-top:1px dashed var(--border-color)">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                            <h4 style="margin:0;font-size:.82rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">
                                <i class="bi bi-clipboard2-check"></i> Planes de Acción — {{ $act->nombre }}
                            </h4>
                        </div>
                        @if($act->planesAccion->count())
                        <table style="width:100%;font-size:.8rem;border-collapse:collapse;margin-bottom:.75rem">
                            <thead>
                                <tr style="background:#eef2ff">
                                    <th style="padding:.4rem .5rem;text-align:left">Acción</th>
                                    <th style="padding:.4rem .5rem;text-align:left;width:120px">Responsable</th>
                                    <th style="padding:.4rem .5rem;text-align:center;width:90px">Compromiso</th>
                                    <th style="padding:.4rem .5rem;text-align:center;width:100px">Estado</th>
                                    <th style="padding:.4rem .5rem;width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($act->planesAccion as $plan)
                            <tr style="border-bottom:1px solid #e5e7eb">
                                <td style="padding:.4rem .5rem">{{ $plan->accion }}</td>
                                <td style="padding:.4rem .5rem;color:var(--text-muted)">{{ $plan->responsable ?? '—' }}</td>
                                <td style="padding:.4rem .5rem;text-align:center">
                                    {{ $plan->fecha_compromiso ? \Carbon\Carbon::parse($plan->fecha_compromiso)->format('d/m/Y') : '—' }}
                                </td>
                                <td style="padding:.4rem .5rem;text-align:center">
                                    <form method="POST" action="{{ route('carta-gantt.plan-accion.update', $plan) }}" style="display:inline">
                                        @csrf @method('PATCH')
                                        <select name="estado" class="form-input" style="font-size:.75rem;padding:.2rem .4rem;width:auto" onchange="this.form.submit()">
                                            @foreach(\App\Models\SstPlanAccion::estadosMap() as $ek => $ev)
                                            <option value="{{ $ek }}" {{ $plan->estado === $ek ? 'selected' : '' }}>{{ $ev }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td style="padding:.4rem .5rem;text-align:center">
                                    <form method="POST" action="{{ route('carta-gantt.plan-accion.destroy', $plan) }}"
                                          onsubmit="return confirm('¿Eliminar?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="gantt-action-btn" style="color:var(--danger)"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                        @else
                        <p style="color:var(--text-muted);font-size:.8rem;margin:.25rem 0 .75rem;font-style:italic">Sin planes de acción registrados.</p>
                        @endif
                        {{-- Form nuevo plan --}}
                        <form method="POST" action="{{ route('carta-gantt.plan-accion.store', $act) }}"
                              style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
                            @csrf
                            <div class="form-group" style="flex:1;min-width:180px;margin:0">
                                <label style="font-size:.72rem">Acción *</label>
                                <input type="text" name="accion" required class="form-input" style="font-size:.8rem" placeholder="Descripción de la acción">
                            </div>
                            <div class="form-group" style="width:120px;margin:0">
                                <label style="font-size:.72rem">Responsable</label>
                                <input type="text" name="responsable" class="form-input" style="font-size:.8rem" placeholder="Nombre">
                            </div>
                            <div class="form-group" style="width:130px;margin:0">
                                <label style="font-size:.72rem">F. Compromiso</label>
                                <input type="date" name="fecha_compromiso" class="form-input" style="font-size:.8rem">
                            </div>
                            <button type="submit" class="btn-premium" style="padding:.35rem .75rem;font-size:.78rem"><i class="bi bi-plus"></i> Agregar</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="17" style="padding:1.25rem;color:var(--text-muted);font-style:italic;text-align:center">
                Sin actividades — haz clic en "<i class="bi bi-plus-lg"></i> Actividad" para agregar.
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
            <button class="btn-ghost" onclick="toggleAddCat()">
                <i class="bi bi-folder-plus"></i> Agregar Categoría
            </button>
        </div>
        <div id="addCat" style="display:none;padding:1rem">
            <form method="POST" action="{{ route('carta-gantt.categorias.store', $cartaGantt) }}"
                  style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div class="form-group" style="flex:1;min-width:240px;margin:0">
                    <label style="font-size:.8rem">Nombre de la categoría</label>
                    <input type="text" name="nombre" required class="form-input" placeholder="Ej: Capacitaciones">
                </div>
                <div class="form-group" style="width:80px;margin:0">
                    <label style="font-size:.8rem">Orden</label>
                    <input type="number" name="orden" value="{{ $cartaGantt->categorias->count() + 1 }}" class="form-input" min="1">
                </div>
                <button type="submit" class="btn-premium" style="padding:.45rem 1rem">Agregar</button>
                <button type="button" class="btn-ghost" style="padding:.45rem .75rem" onclick="toggleAddCat()">Cancelar</button>
            </form>
        </div>
    </div>
</div>

<style>
.gantt-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.gantt-table thead tr { background:#f0f3f8; }
.gantt-table th { padding:.55rem .35rem; font-weight:600; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); border-bottom:1px solid var(--border-color); white-space:nowrap; }
.gantt-table td { padding:.5rem .35rem; border-bottom:1px solid var(--border-color); vertical-align:middle; }
.gantt-table tbody tr:hover { background:rgba(15,27,76,.02); }
.row-vencida { background:rgba(239,68,68,.04) !important; }
.row-vencida:hover { background:rgba(239,68,68,.07) !important; }

.gantt-cell {
    width:30px; height:30px; border-radius:6px;
    border:1px solid #d1d5db; background:transparent; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center;
    transition:all .15s; font-size:.78rem; color:#9ca3af; line-height:1;
}
.gantt-cell:hover { border-color:var(--primary-color); color:var(--primary-color); transform:scale(1.1); }
.gantt-plan { background:#e0e7ff; border-color:#818cf8; color:#4338ca; }
.gantt-done { background:#dcfce7; border-color:#4ade80; color:#16a34a; font-weight:700; }
.gantt-done:hover { background:#bbf7d0; }

.gantt-action-btn {
    background:none; border:none; cursor:pointer; padding:.2rem .35rem;
    color:var(--text-muted); font-size:.82rem; border-radius:4px;
    transition:all .15s;
}
.gantt-action-btn:hover { background:rgba(15,27,76,.06); color:var(--primary-color); }
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
function togglePlanes(actId) {
    const el = document.getElementById('planes-' + actId);
    el.style.display = el.style.display === 'none' ? 'table-row' : 'none';
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ mes, realizado: newState })
        });
        if (!res.ok) throw new Error('Error al actualizar');

        btn.dataset.realizado = newState ? '1' : '0';
        if (newState) {
            btn.className = 'gantt-cell gantt-done';
            btn.textContent = '✓';
            btn.title = 'Realizado';
        } else {
            btn.className = 'gantt-cell gantt-plan';
            btn.textContent = '○';
            btn.title = 'Planificado';
        }
        updateProgress();
    } catch (e) {
        alert('No se pudo actualizar. Intenta nuevamente.');
    }
    btn.disabled = false;
    btn.style.opacity = '1';
}

function updateProgress() {
    const planned = document.querySelectorAll('.gantt-plan, .gantt-done').length;
    const done = document.querySelectorAll('.gantt-done').length;
    if (planned === 0) return;
    const pct = Math.round((done / planned) * 100);
    const bar = document.getElementById('progressBar');
    const num = document.getElementById('progressNum');
    if (bar) bar.style.width = pct + '%';
    if (num) num.textContent = pct + '%';
}
</script>
@endsection
