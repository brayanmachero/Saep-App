@extends('layouts.app')
@section('title', $cartaGantt->nombre)
@section('content')
@php
    $mesActual = (int) date('n');
    $totalAct = $cartaGantt->actividadesTotales;
    $vencidas = $cartaGantt->actividadesVencidas;
    $pct = $cartaGantt->porcentajeRealizado;
    $completadas = 0; $enProgreso = 0; $pendientes = 0; $porVencer = 0;
    foreach ($cartaGantt->categorias as $cat) {
        foreach ($cat->actividades as $a) {
            if ($a->estado === 'COMPLETADA') $completadas++;
            elseif ($a->estado === 'EN_PROGRESO') $enProgreso++;
            else $pendientes++;
            if ($a->estaPorVencer) $porVencer++;
        }
    }
@endphp
<div class="page-container">

    {{-- ========== HEADER ========== --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
        <div>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.35rem">
                <h2 class="page-heading" style="margin:0">{{ $cartaGantt->nombre }}</h2>
                <span class="badge {{ $cartaGantt->estadoBadge }}">{{ ucfirst(strtolower($cartaGantt->estado)) }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;font-size:.82rem;color:var(--text-muted);flex-wrap:wrap">
                <span style="background:var(--surface-color);padding:.2rem .6rem;border-radius:6px;font-weight:600;font-family:monospace;font-size:.78rem;border:1px solid var(--surface-border)">{{ $cartaGantt->codigo ?? '—' }}</span>
                <span><i class="bi bi-calendar3"></i> {{ $cartaGantt->anio }}</span>
                @if($cartaGantt->centroCosto)<span><i class="bi bi-building"></i> {{ $cartaGantt->centroCosto->nombre }}</span>@endif
                @if($cartaGantt->responsable)<span><i class="bi bi-person-fill"></i> {{ $cartaGantt->responsable->nombre_completo }}</span>@endif
            </div>
        </div>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <button class="sst-btn sst-btn-outline" onclick="document.getElementById('importModal').style.display='flex'">
                <i class="bi bi-cloud-upload"></i> Importar CSV
            </button>
            <a href="{{ route('carta-gantt.edit', $cartaGantt) }}" class="sst-btn sst-btn-outline"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('carta-gantt.index') }}" class="sst-btn sst-btn-outline"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @include('partials._alerts')

    {{-- ========== STATS CARDS ========== --}}
    <div class="sst-stats-grid">
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#6366f1,#818cf8)"><i class="bi bi-speedometer2"></i></div>
            <div style="flex:1">
                <div class="sst-stat-label">Avance Global</div>
                <div class="sst-stat-value" id="progressNum">{{ $pct }}%</div>
                <div class="sst-progress-track"><div class="sst-progress-fill" id="progressBar" style="width:{{ $pct }}%"></div></div>
            </div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#0ea5e9,#38bdf8)"><i class="bi bi-list-task"></i></div>
            <div>
                <div class="sst-stat-label">Total Actividades</div>
                <div class="sst-stat-value">{{ $totalAct }}</div>
            </div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#10b981,#34d399)"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="sst-stat-label">Completadas</div>
                <div class="sst-stat-value" style="color:#10b981">{{ $completadas }}</div>
            </div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="sst-stat-label">En Progreso</div>
                <div class="sst-stat-value" style="color:#f59e0b">{{ $enProgreso }}</div>
            </div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#ef4444,#f87171)"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div>
                <div class="sst-stat-label">Vencidas</div>
                <div class="sst-stat-value" style="color:#ef4444">{{ $vencidas }}</div>
            </div>
        </div>
    </div>

    {{-- ========== LEYENDA ========== --}}
    <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:1rem;font-size:.78rem;color:var(--text-muted);flex-wrap:wrap">
        <span style="font-weight:600">Leyenda:</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span class="gantt-cell gantt-plan" style="width:20px;height:20px;font-size:.6rem;cursor:default">○</span> Programado</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span class="gantt-cell gantt-done" style="width:20px;height:20px;font-size:.6rem;cursor:default">✓</span> Realizado</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span style="width:20px;height:20px;border-radius:4px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);display:inline-block"></span> Vencida</span>
        <span style="display:flex;align-items:center;gap:.3rem"><span style="width:20px;height:20px;border-radius:4px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);display:inline-block"></span> Próxima a vencer</span>
        <span style="margin-left:auto;font-style:italic">Clic en celda para marcar/desmarcar</span>
    </div>

    {{-- ========== TABLA GANTT POR CATEGORÍA ========== --}}
    @foreach($cartaGantt->categorias as $categoria)
    @php
        $catActs = $categoria->actividades;
        $catProg = 0; $catReal = 0;
        foreach ($catActs as $a) {
            foreach ($a->seguimiento as $s) {
                if ($s->programado) $catProg++;
                if ($s->realizado) $catReal++;
            }
        }
        $catPct = $catProg > 0 ? (int) round($catReal / $catProg * 100) : 0;
    @endphp
    <div class="sst-cat-card">

        {{-- Categoría Header --}}
        <div class="sst-cat-header">
            <div style="display:flex;align-items:center;gap:.75rem">
                <div class="sst-cat-icon"><i class="bi bi-folder2-open"></i></div>
                <div>
                    <h3 class="sst-cat-title">{{ $categoria->nombre }}</h3>
                    <span style="font-size:.72rem;color:var(--text-muted)">{{ $catActs->count() }} actividades · {{ $catPct }}% avance</span>
                </div>
                <div class="sst-cat-progress"><div class="sst-cat-progress-fill" style="width:{{ $catPct }}%"></div></div>
            </div>
            <div style="display:flex;gap:.35rem">
                <button class="sst-btn sst-btn-sm sst-btn-primary" onclick="toggleAddActividad({{ $categoria->id }})">
                    <i class="bi bi-plus-lg"></i> Actividad
                </button>
                <form method="POST" action="{{ route('carta-gantt.categorias.destroy', $categoria) }}"
                      onsubmit="return confirm('¿Eliminar esta categoría y todas sus actividades?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="sst-btn sst-btn-sm sst-btn-danger"><i class="bi bi-trash3"></i></button>
                </form>
            </div>
        </div>

        {{-- Formulario Agregar Actividad (oculto) --}}
        <div id="addAct-{{ $categoria->id }}" style="display:none" class="sst-add-form">
            <form method="POST" action="{{ route('carta-gantt.actividades.store', $categoria) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.65rem;margin-bottom:.65rem">
                    <div class="form-group" style="margin:0">
                        <label class="sst-label">Nombre *</label>
                        <input type="text" name="nombre" required class="form-input" placeholder="Ej: Inspección de EPP">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="sst-label">Responsable</label>
                        <select name="responsable_id" class="form-input">
                            <option value="">— Sin asignar —</option>
                            @foreach($usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} {{ $u->apellido_paterno ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="sst-label">Prioridad</label>
                        <select name="prioridad" class="form-input">
                            @foreach(\App\Models\SstActividad::prioridadesMap() as $k => $v)
                            <option value="{{ $k }}" {{ $k === 'MEDIA' ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="sst-label">Periodicidad</label>
                        <select name="periodicidad" class="form-input">
                            <option value="">— Ninguna —</option>
                            @foreach(\App\Models\SstActividad::periodicidadesMap() as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="sst-label">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" class="form-input">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="sst-label">Fecha fin</label>
                        <input type="date" name="fecha_fin" class="form-input">
                    </div>
                </div>
                <div style="margin-bottom:.65rem">
                    <label class="sst-label" style="display:block;margin-bottom:.3rem">Meses programados</label>
                    <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                        @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mesNom)
                        <label class="sst-mes-check">
                            <input type="checkbox" name="meses_prog[]" value="{{ $idx + 1 }}"> {{ $mesNom }}
                        </label>
                        @endforeach
                    </div>
                </div>
                <div style="display:flex;gap:.4rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-sm sst-btn-outline" onclick="toggleAddActividad({{ $categoria->id }})">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-sm sst-btn-primary"><i class="bi bi-plus-lg"></i> Agregar</button>
                </div>
            </form>
        </div>

        {{-- Tabla Gantt --}}
        <div class="sst-table-wrap">
        <table class="sst-gantt">
            <thead>
                <tr>
                    <th class="sst-th-sticky" style="min-width:220px">Actividad</th>
                    <th style="min-width:100px">Responsable</th>
                    <th style="width:55px;text-align:center">Prior.</th>
                    <th style="width:70px;text-align:center">Estado</th>
                    @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mesNom)
                    <th class="sst-th-mes {{ ($idx + 1) === $mesActual ? 'sst-mes-actual' : '' }}">{{ $mesNom }}</th>
                    @endforeach
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($catActs as $act)
            <tr class="{{ $act->estaVencida ? 'sst-row-vencida' : ($act->estaPorVencer ? 'sst-row-por-vencer' : '') }}">
                <td class="sst-td-name">
                    <div style="font-weight:600;line-height:1.25;color:var(--text-main)">{{ $act->nombre }}</div>
                    @if($act->fecha_inicio || $act->fecha_fin || $act->periodicidad)
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:.15rem;display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                        @if($act->fecha_inicio)<span><i class="bi bi-arrow-right-short"></i>{{ $act->fecha_inicio->format('d/m') }}</span>@endif
                        @if($act->fecha_fin)
                        <span style="{{ $act->estaVencida ? 'color:#ef4444;font-weight:600' : '' }}"><i class="bi bi-flag-fill" style="font-size:.6rem"></i> {{ $act->fecha_fin->format('d/m/Y') }}</span>
                        @endif
                        @if($act->periodicidad)<span class="sst-tag-tiny">{{ \App\Models\SstActividad::periodicidadesMap()[$act->periodicidad] ?? $act->periodicidad }}</span>@endif
                    </div>
                    @endif
                </td>
                <td style="font-size:.78rem;color:var(--text-muted)">
                    @if($act->responsableUser)
                    <span style="display:flex;align-items:center;gap:.3rem">
                        <span class="sst-avatar">{{ strtoupper(substr($act->responsableUser->name, 0, 1)) }}</span>
                        {{ $act->responsableUser->name }}
                    </span>
                    @else
                    <span style="opacity:.5">{{ $act->responsable ?? '—' }}</span>
                    @endif
                </td>
                <td style="text-align:center">
                    @php $pc = ['ALTA'=>'#ef4444','MEDIA'=>'#f59e0b','BAJA'=>'#10b981']; @endphp
                    <span class="sst-priority-dot" style="background:{{ $pc[$act->prioridad] ?? '#9ca3af' }}" title="{{ $act->prioridad }}"></span>
                </td>
                <td style="text-align:center">
                    <span class="sst-status-pill sst-status-{{ strtolower($act->estado) }}">
                        {{ \App\Models\SstActividad::estadosMap()[$act->estado] ?? $act->estado }}
                    </span>
                </td>
                @for($m = 1; $m <= 12; $m++)
                @php
                    $seg = $act->seguimientoPorMes[$m] ?? ['programado'=>false,'realizado'=>false];
                    $realizado   = (bool)($seg['realizado']   ?? false);
                    $planificado = (bool)($seg['programado']  ?? false);
                @endphp
                <td class="sst-td-mes {{ $m === $mesActual ? 'sst-col-actual' : '' }}">
                    <button type="button"
                            class="gantt-cell {{ $realizado ? 'gantt-done' : ($planificado ? 'gantt-plan' : '') }}"
                            data-actividad="{{ $act->id }}"
                            data-mes="{{ $m }}"
                            data-realizado="{{ $realizado ? '1' : '0' }}"
                            onclick="toggleSeguimiento(this)"
                            title="{{ $realizado ? '✓ Realizado' : ($planificado ? '○ Planificado – clic para marcar' : 'Sin programar') }}">
                        @if($realizado)<i class="bi bi-check-lg" style="font-size:.7rem"></i>
                        @elseif($planificado)<span style="font-size:.65rem">○</span>
                        @endif
                    </button>
                </td>
                @endfor
                <td>
                    <div style="display:flex;gap:.15rem;justify-content:center">
                        <button type="button" class="sst-icon-btn" title="Plan de Acción ({{ $act->planesAccion->count() }})"
                                onclick="togglePlanes({{ $act->id }})">
                            <i class="bi bi-clipboard2-check"></i>
                            @if($act->planesAccion->count())<span class="sst-badge-count">{{ $act->planesAccion->count() }}</span>@endif
                        </button>
                        <form method="POST" action="{{ route('carta-gantt.actividades.destroy', $act) }}"
                              onsubmit="return confirm('¿Eliminar esta actividad?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="sst-icon-btn sst-icon-danger" title="Eliminar"><i class="bi bi-trash3"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            {{-- Planes de Acción inline --}}
            <tr id="planes-{{ $act->id }}" style="display:none">
                <td colspan="17" style="padding:0">
                    <div class="sst-planes-panel">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                            <h4 style="margin:0;font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">
                                <i class="bi bi-clipboard2-check"></i> Planes de Acción — {{ $act->nombre }}
                            </h4>
                        </div>
                        @if($act->planesAccion->count())
                        <div class="sst-planes-list">
                        @foreach($act->planesAccion as $plan)
                            <div class="sst-plan-item {{ $plan->estaVencido ? 'sst-plan-vencido' : '' }}">
                                <div style="flex:1">
                                    <div style="font-weight:500;font-size:.82rem">{{ $plan->accion }}</div>
                                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem">
                                        {{ $plan->responsable ?? 'Sin responsable' }}
                                        @if($plan->fecha_compromiso) · <span style="{{ $plan->estaVencido ? 'color:#ef4444;font-weight:600' : '' }}">{{ $plan->fecha_compromiso->format('d/m/Y') }}</span>@endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('carta-gantt.plan-accion.update', $plan) }}" style="display:flex;align-items:center;gap:.3rem">
                                    @csrf @method('PATCH')
                                    <select name="estado" class="sst-plan-select" onchange="this.form.submit()">
                                        @foreach(\App\Models\SstPlanAccion::estadosMap() as $ek => $ev)
                                        <option value="{{ $ek }}" {{ $plan->estado === $ek ? 'selected' : '' }}>{{ $ev }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('carta-gantt.plan-accion.destroy', $plan) }}" onsubmit="return confirm('¿Eliminar?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="sst-icon-btn sst-icon-danger" style="font-size:.7rem"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </div>
                        @endforeach
                        </div>
                        @else
                        <p style="color:var(--text-muted);font-size:.78rem;margin:.25rem 0 .75rem;font-style:italic">Sin planes de acción.</p>
                        @endif
                        <form method="POST" action="{{ route('carta-gantt.plan-accion.store', $act) }}"
                              style="display:flex;gap:.4rem;align-items:flex-end;flex-wrap:wrap;margin-top:.5rem">
                            @csrf
                            <div style="flex:1;min-width:160px"><label class="sst-label">Acción *</label><input type="text" name="accion" required class="form-input" style="font-size:.78rem" placeholder="Descripción"></div>
                            <div style="width:110px"><label class="sst-label">Responsable</label><input type="text" name="responsable" class="form-input" style="font-size:.78rem" placeholder="Nombre"></div>
                            <div style="width:120px"><label class="sst-label">Compromiso</label><input type="date" name="fecha_compromiso" class="form-input" style="font-size:.78rem"></div>
                            <button type="submit" class="sst-btn sst-btn-sm sst-btn-primary"><i class="bi bi-plus"></i> Agregar</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="17" style="padding:2rem;color:var(--text-muted);font-style:italic;text-align:center">
                Sin actividades. <button class="sst-link" onclick="toggleAddActividad({{ $categoria->id }})">Agregar primera actividad</button>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endforeach

    {{-- ========== AGREGAR CATEGORÍA ========== --}}
    <div class="sst-add-cat-card">
        <button class="sst-btn sst-btn-outline" onclick="toggleAddCat()" style="width:100%">
            <i class="bi bi-folder-plus"></i> Agregar Categoría
        </button>
        <div id="addCat" style="display:none;margin-top:1rem">
            <form method="POST" action="{{ route('carta-gantt.categorias.store', $cartaGantt) }}"
                  style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div style="flex:1;min-width:200px"><label class="sst-label">Nombre de la categoría *</label><input type="text" name="nombre" required class="form-input" placeholder="Ej: Capacitaciones"></div>
                <div style="width:80px"><label class="sst-label">Orden</label><input type="number" name="orden" value="{{ $cartaGantt->categorias->count() + 1 }}" class="form-input" min="1"></div>
                <button type="submit" class="sst-btn sst-btn-sm sst-btn-primary">Agregar</button>
                <button type="button" class="sst-btn sst-btn-sm sst-btn-outline" onclick="toggleAddCat()">Cancelar</button>
            </form>
        </div>
    </div>
</div>

{{-- ========== MODAL IMPORTACIÓN CSV ========== --}}
<div id="importModal" class="sst-modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="sst-modal">
        <div class="sst-modal-header">
            <h3 style="margin:0;font-size:1rem;font-weight:700"><i class="bi bi-cloud-upload"></i> Importar Actividades desde CSV</h3>
            <button onclick="document.getElementById('importModal').style.display='none'" class="sst-icon-btn"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sst-modal-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin:0 0 1rem">
                Sube un archivo CSV con las actividades. Se crearán las categorías automáticamente si no existen.
            </p>
            <div style="background:var(--surface-color);border:1px solid var(--surface-border);border-radius:10px;padding:1rem;margin-bottom:1rem">
                <p style="font-size:.78rem;font-weight:600;margin:0 0 .5rem;color:var(--text-main)"><i class="bi bi-info-circle"></i> Formato requerido (separador: punto y coma ;)</p>
                <div style="font-family:monospace;font-size:.72rem;color:var(--text-muted);overflow-x:auto;white-space:nowrap">
                    categoria;nombre;responsable_email;prioridad;periodicidad;fecha_inicio;fecha_fin;meses_programados<br>
                    <span style="color:var(--accent-color)">Capacitaciones;Inducción SST;bmachero@saep.cl;ALTA;MENSUAL;2026-01-15;2026-12-31;1,3,6,9,12</span>
                </div>
            </div>
            <a href="{{ route('carta-gantt.plantilla') }}" class="sst-btn sst-btn-outline sst-btn-sm" style="margin-bottom:1rem;display:inline-flex">
                <i class="bi bi-download"></i> Descargar Plantilla CSV
            </a>
            <form method="POST" action="{{ route('carta-gantt.importar', $cartaGantt) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="sst-label">Archivo CSV *</label>
                    <input type="file" name="archivo" required accept=".csv,.txt" class="form-input">
                </div>
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-outline" onclick="document.getElementById('importModal').style.display='none'">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-primary"><i class="bi bi-cloud-upload"></i> Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ===== STATS ===== */
.sst-stats-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.85rem;margin-bottom:1.5rem; }
.sst-stat-card { background:var(--surface-color);border:1px solid var(--surface-border);border-radius:14px;padding:1rem 1.15rem;display:flex;align-items:center;gap:.85rem;transition:transform .15s,box-shadow .15s; }
.sst-stat-card:hover { transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.06); }
.sst-stat-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.1rem;flex-shrink:0; }
.sst-stat-label { font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:600; }
.sst-stat-value { font-size:1.5rem;font-weight:800;color:var(--text-main);line-height:1.2; }
.sst-progress-track { height:6px;background:var(--surface-border);border-radius:9999px;margin-top:.35rem;width:100%;min-width:80px; }
.sst-progress-fill { height:6px;border-radius:9999px;background:linear-gradient(90deg,#6366f1,#a78bfa);transition:width .4s ease; }

/* ===== BOTONES ===== */
.sst-btn { display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .85rem;border-radius:8px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap; }
.sst-btn-primary { background:var(--primary-color,#0f1b4c);color:white; }
.sst-btn-primary:hover { opacity:.9;transform:translateY(-1px); }
.sst-btn-outline { background:transparent;border:1px solid var(--surface-border);color:var(--text-main); }
.sst-btn-outline:hover { border-color:var(--primary-color);color:var(--primary-color);background:rgba(15,27,76,.03); }
.sst-btn-sm { padding:.3rem .65rem;font-size:.75rem; }
.sst-btn-danger { background:#fef2f2;color:#ef4444;border:1px solid rgba(239,68,68,.2); }
.sst-btn-danger:hover { background:#fee2e2; }

/* ===== CATEGORÍA CARD ===== */
.sst-cat-card { background:var(--surface-color);border:1px solid var(--surface-border);border-radius:14px;overflow:hidden;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04); }
.sst-cat-header { display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.15rem;border-bottom:1px solid var(--surface-border);flex-wrap:wrap;gap:.5rem; }
.sst-cat-icon { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--primary-color,#0f1b4c),#2d3a6e);color:white;display:flex;align-items:center;justify-content:center;font-size:.85rem; }
.sst-cat-title { margin:0;font-size:.95rem;font-weight:700;color:var(--text-main); }
.sst-cat-progress { width:80px;height:5px;background:var(--surface-border);border-radius:9999px;overflow:hidden; }
.sst-cat-progress-fill { height:100%;background:linear-gradient(90deg,#10b981,#34d399);border-radius:9999px;transition:width .3s; }

/* ===== FORMULARIO ADD ===== */
.sst-add-form { padding:1.15rem;background:var(--bg-color);border-bottom:1px solid var(--surface-border); }
.sst-label { font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.2rem; }
.sst-mes-check { display:inline-flex;align-items:center;gap:.2rem;font-size:.78rem;cursor:pointer;padding:.25rem .45rem;border-radius:6px;border:1px solid var(--surface-border);transition:all .15s; }
.sst-mes-check:hover { border-color:var(--primary-color);background:rgba(15,27,76,.03); }

/* ===== TABLA GANTT ===== */
.sst-table-wrap { overflow-x:auto;-webkit-overflow-scrolling:touch; }
.sst-gantt { width:100%;border-collapse:collapse;font-size:.8rem; }
.sst-gantt thead { position:sticky;top:0;z-index:2; }
.sst-gantt thead tr { background:var(--bg-color); }
.sst-gantt th { padding:.6rem .4rem;font-weight:700;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);border-bottom:2px solid var(--surface-border);white-space:nowrap;text-align:left; }
.sst-th-mes { text-align:center !important;width:44px; }
.sst-mes-actual { color:var(--primary-color,#0f1b4c) !important;position:relative; }
.sst-mes-actual::after { content:'';position:absolute;bottom:0;left:25%;right:25%;height:2px;background:var(--primary-color,#0f1b4c);border-radius:1px; }
.sst-gantt td { padding:.55rem .4rem;border-bottom:1px solid var(--surface-border);vertical-align:middle; }
.sst-gantt tbody tr { transition:background .1s; }
.sst-gantt tbody tr:hover { background:rgba(99,102,241,.03); }
.sst-td-name { padding-left:1rem !important; }
.sst-td-mes { text-align:center;padding:.35rem .1rem !important; }
.sst-col-actual { background:rgba(99,102,241,.04); }
.sst-row-vencida { background:rgba(239,68,68,.04) !important; }
.sst-row-vencida:hover { background:rgba(239,68,68,.07) !important; }
.sst-row-por-vencer { background:rgba(245,158,11,.04) !important; }
.sst-row-por-vencer:hover { background:rgba(245,158,11,.07) !important; }

/* ===== CELDAS GANTT ===== */
.gantt-cell { width:30px;height:30px;border-radius:7px;border:1.5px solid var(--surface-border);background:transparent;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;font-size:.75rem;color:var(--text-muted);line-height:1; }
.gantt-cell:hover { border-color:var(--primary-color);color:var(--primary-color);transform:scale(1.12);box-shadow:0 2px 8px rgba(99,102,241,.15); }
.gantt-plan { background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.4);color:#6366f1; }
.gantt-done { background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.5);color:#10b981;font-weight:700; }
.gantt-done:hover { background:rgba(16,185,129,.2);border-color:#10b981; }

/* ===== BADGES Y ELEMENTOS ===== */
.sst-avatar { width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a78bfa);color:white;display:inline-flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;flex-shrink:0; }
.sst-priority-dot { width:10px;height:10px;border-radius:50%;display:inline-block; }
.sst-status-pill { font-size:.65rem;font-weight:700;padding:.15rem .5rem;border-radius:9999px;white-space:nowrap;text-transform:uppercase;letter-spacing:.03em; }
.sst-status-pendiente { background:rgba(245,158,11,.12);color:#d97706; }
.sst-status-en_progreso { background:rgba(99,102,241,.1);color:#6366f1; }
.sst-status-completada { background:rgba(16,185,129,.1);color:#059669; }
.sst-status-cancelada { background:rgba(107,114,128,.1);color:#6b7280; }
.sst-tag-tiny { font-size:.62rem;background:var(--surface-color);border:1px solid var(--surface-border);padding:.1rem .35rem;border-radius:4px;font-weight:500; }

/* ===== ICON BTN ===== */
.sst-icon-btn { position:relative;background:none;border:none;cursor:pointer;padding:.25rem .35rem;color:var(--text-muted);font-size:.82rem;border-radius:6px;transition:all .15s; }
.sst-icon-btn:hover { background:rgba(99,102,241,.08);color:var(--primary-color); }
.sst-icon-danger:hover { background:rgba(239,68,68,.08);color:#ef4444; }
.sst-badge-count { position:absolute;top:-3px;right:-4px;width:14px;height:14px;border-radius:50%;background:#6366f1;color:white;font-size:.55rem;display:flex;align-items:center;justify-content:center;font-weight:700; }

/* ===== PLANES DE ACCIÓN ===== */
.sst-planes-panel { padding:.85rem 1.15rem;background:var(--bg-color);border-top:2px solid var(--surface-border); }
.sst-planes-list { display:flex;flex-direction:column;gap:.4rem;margin-bottom:.75rem; }
.sst-plan-item { display:flex;align-items:center;gap:.65rem;padding:.55rem .75rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:8px;transition:all .15s; }
.sst-plan-item:hover { border-color:rgba(99,102,241,.3); }
.sst-plan-vencido { border-left:3px solid #ef4444; }
.sst-plan-select { font-size:.72rem;padding:.2rem .35rem;border:1px solid var(--surface-border);border-radius:6px;background:var(--surface-color);color:var(--text-main);cursor:pointer; }

/* ===== ADD CATEGORY ===== */
.sst-add-cat-card { border:2px dashed var(--surface-border);border-radius:14px;padding:1.15rem;text-align:center;margin-bottom:1.5rem;transition:border-color .15s; }
.sst-add-cat-card:hover { border-color:var(--primary-color); }

/* ===== LINK ===== */
.sst-link { background:none;border:none;color:var(--primary-color);cursor:pointer;font-weight:600;text-decoration:underline;font-size:inherit; }

/* ===== MODAL ===== */
.sst-modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem; }
.sst-modal { background:var(--surface-color);border:1px solid var(--surface-border);border-radius:16px;width:100%;max-width:580px;box-shadow:0 20px 60px rgba(0,0,0,.15);overflow:hidden; }
.sst-modal-header { display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--surface-border); }
.sst-modal-body { padding:1.25rem; }

/* ===== DARK MODE ===== */
body.dark-mode .sst-gantt thead tr { background:var(--bg-color); }
body.dark-mode .sst-add-form { background:rgba(255,255,255,.02); }
body.dark-mode .sst-planes-panel { background:rgba(255,255,255,.02); }
body.dark-mode .sst-col-actual { background:rgba(99,102,241,.08); }
body.dark-mode .gantt-cell { border-color:rgba(255,255,255,.12); }
body.dark-mode .gantt-plan { background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.4); }
body.dark-mode .gantt-done { background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.4); }
body.dark-mode .sst-btn-danger { background:rgba(239,68,68,.1); }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .sst-stats-grid { grid-template-columns:repeat(2,1fr); }
    .sst-cat-header { flex-direction:column;align-items:flex-start; }
    .sst-gantt { font-size:.72rem; }
    .gantt-cell { width:26px;height:26px;font-size:.65rem; }
}
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
        if (!res.ok) throw new Error('Error');

        btn.dataset.realizado = newState ? '1' : '0';
        if (newState) {
            btn.className = 'gantt-cell gantt-done';
            btn.innerHTML = '<i class="bi bi-check-lg" style="font-size:.7rem"></i>';
            btn.title = '✓ Realizado';
        } else {
            btn.className = 'gantt-cell gantt-plan';
            btn.innerHTML = '<span style="font-size:.65rem">○</span>';
            btn.title = '○ Planificado – clic para marcar';
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
