@extends('layouts.app')
@section('title', $cartaGantt->nombre)
@section('content')
@php
    $mesActual = (int) date('n');
    $semanaActual = (int) date('W');
    $diaActual = (int) date('j');
    $anioPrograma = (int) $cartaGantt->anio;
    $totalAct = $cartaGantt->actividadesTotales;
    $pct = $cartaGantt->porcentajeRealizado;
    $completadas = 0; $enProgreso = 0; $pendientes = 0; $porVencer = 0; $vencidosMes = 0;
    $allActividades = collect();
    foreach ($cartaGantt->categorias as $cat) {
        foreach ($cat->actividades as $a) {
            if ($a->estado === 'COMPLETADA') $completadas++;
            elseif ($a->estado === 'EN_PROGRESO') $enProgreso++;
            else $pendientes++;
            if ($a->estaPorVencer) $porVencer++;
            // Contar meses vencidos (programado + pasado + no realizado)
            foreach ($a->seguimiento_por_mes as $m => $s) {
                if ($s['programado'] && !$s['realizado'] && $m < $mesActual) $vencidosMes++;
            }
            $allActividades->push($a);
        }
    }
    $mesesNombres = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $actividadesJson = $allActividades->map(function($a) {
        return [
            'id' => $a->id,
            'nombre' => $a->nombre,
            'descripcion' => $a->descripcion,
            'responsable' => $a->nombre_responsable,
            'responsable_id' => $a->responsable_id,
            'categoria' => $a->categoria->nombre ?? '—',
            'prioridad' => $a->prioridad,
            'estado' => $a->estado,
            'periodicidad' => $a->periodicidad,
            'cantidad_programada' => (int) ($a->cantidad_programada ?? 1),
            'fecha_inicio' => $a->fecha_inicio ? $a->fecha_inicio->format('Y-m-d') : null,
            'fecha_fin' => $a->fecha_fin ? $a->fecha_fin->format('Y-m-d') : null,
            'seguimiento' => $a->seguimiento_por_mes,
        ];
    })->values();
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
            <a href="{{ route('carta-gantt.reporte-pdf', $cartaGantt) }}" class="sst-btn sst-btn-outline" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i> Reporte PDF
            </a>
            @if($puedeCrear || $puedeEditar)
            <button class="sst-btn sst-btn-outline" onclick="document.getElementById('importModal').style.display='flex'">
                <i class="bi bi-cloud-upload"></i> Importar CSV
            </button>
            @endif
            @if($puedeEditar)
            <a href="{{ route('carta-gantt.edit', $cartaGantt) }}" class="sst-btn sst-btn-outline"><i class="bi bi-pencil"></i> Editar</a>
            @endif
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
        @php
            $mesProgTotal = 0; $mesRealTotal = 0;
            foreach ($allActividades as $a) {
                $sMes = $a->seguimiento_por_mes[$mesActual] ?? null;
                if ($sMes && $sMes['programado']) { $mesProgTotal++; if ($sMes['realizado']) $mesRealTotal++; }
            }
            $mesPct = $mesProgTotal > 0 ? (int) round($mesRealTotal / $mesProgTotal * 100) : 0;
            $totalReprogramaciones = 0;
            $actConReprog = 0;
            $actPorVencer = 0;
            foreach ($allActividades as $a) {
                $repCount = $a->reprogramaciones->count();
                $totalReprogramaciones += $repCount;
                if ($repCount > 0) $actConReprog++;
                if ($a->estaPorVencer) $actPorVencer++;
            }
        @endphp
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#a78bfa)"><i class="bi bi-calendar-check"></i></div>
            <div style="flex:1">
                <div class="sst-stat-label">Avance {{ $mesesNombres[$mesActual] }}</div>
                <div class="sst-stat-value" id="monthProgressNum">{{ $mesPct }}%</div>
                <div class="sst-progress-track"><div class="sst-progress-fill" id="monthProgressBar" style="width:{{ $mesPct }}%;background:linear-gradient(90deg,#8b5cf6,#a78bfa)"></div></div>
            </div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#10b981,#34d399)"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="sst-stat-label">Completadas</div><div class="sst-stat-value" id="statCompletadas" style="color:#10b981">{{ $completadas }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="bi bi-clock-history"></i></div>
            <div><div class="sst-stat-label">En Progreso</div><div class="sst-stat-value" id="statEnProgreso" style="color:#f59e0b">{{ $enProgreso }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#ef4444,#f87171)"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div><div class="sst-stat-label">Meses Vencidos</div><div class="sst-stat-value" id="statVencidas" style="color:#ef4444">{{ $vencidosMes }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#6366f1,#818cf8)"><i class="bi bi-calendar2-range"></i></div>
            <div><div class="sst-stat-label">Reprogramaciones</div><div class="sst-stat-value" style="color:#6366f1">{{ $totalReprogramaciones }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#94a3b8,#cbd5e1)"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="sst-stat-label">Pendientes</div><div class="sst-stat-value" style="color:#94a3b8">{{ $pendientes }}</div></div>
        </div>
        @if($actPorVencer > 0)
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#f97316,#fb923c)"><i class="bi bi-bell-fill"></i></div>
            <div><div class="sst-stat-label">Por Vencer (7d)</div><div class="sst-stat-value" style="color:#f97316">{{ $actPorVencer }}</div></div>
        </div>
        @endif
    </div>

    {{-- ========== TOOLBAR: VISTA + LEYENDA ========== --}}
    <div class="sst-toolbar">
        <div class="sst-view-switcher">
            <button class="sst-view-btn active" data-view="anual" onclick="switchView('anual')">
                <i class="bi bi-calendar3-range"></i> Anual
            </button>
            <button class="sst-view-btn" data-view="semestral" onclick="switchView('semestral')">
                <i class="bi bi-calendar3-event"></i> Semestre
            </button>
            <button class="sst-view-btn" data-view="mensual" onclick="switchView('mensual')">
                <i class="bi bi-calendar-month"></i> Mes
            </button>
            <button class="sst-view-btn" data-view="semanal" onclick="switchView('semanal')">
                <i class="bi bi-calendar-week"></i> Semana
            </button>
        </div>

        {{-- Navegación período --}}
        <div class="sst-period-nav" id="periodNav" style="display:none">
            <button class="sst-icon-btn" onclick="navigatePeriod(-1)" title="Anterior"><i class="bi bi-chevron-left"></i></button>
            <span id="periodLabel" style="font-weight:600;font-size:.85rem;min-width:150px;text-align:center"></span>
            <button class="sst-icon-btn" onclick="navigatePeriod(1)" title="Siguiente"><i class="bi bi-chevron-right"></i></button>
            <button class="sst-btn sst-btn-sm sst-btn-outline" onclick="navigateToToday()" style="margin-left:.3rem"><i class="bi bi-geo-alt"></i> Hoy</button>
        </div>

        <div class="sst-legend">
            <span><span class="gantt-cell gantt-plan" style="width:18px;height:18px;font-size:.55rem;cursor:default">○</span> Programado</span>
            <span><span class="gantt-cell gantt-done" style="width:18px;height:18px;font-size:.55rem;cursor:default">✓</span> Realizado</span>
            <span><span class="gantt-cell gantt-partial" style="width:18px;height:18px;font-size:.5rem;cursor:default">2/4</span> Parcial</span>
            <span><span style="width:18px;height:18px;border-radius:4px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);display:inline-block"></span> Vencida</span>
        </div>
    </div>

    {{-- ========== VISTA ANUAL (por defecto) ========== --}}
    <div id="view-anual">
    @foreach($cartaGantt->categorias as $categoria)
    @php
        $catActs = $categoria->actividades;
        $catProg = 0; $catReal = 0;
        foreach ($catActs as $a) {
            $cp = max(1, (int) ($a->cantidad_programada ?? 1));
            foreach ($a->seguimiento as $s) {
                if ($s->programado) $catProg += $cp;
                $catReal += (int) ($s->cantidad_realizada ?? ($s->realizado ? $cp : 0));
            }
        }
        $catPct = $catProg > 0 ? (int) round($catReal / $catProg * 100) : 0;
    @endphp
    <div class="sst-cat-card">
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
                @if($puedeCrear || $puedeEditar)
                <button class="sst-btn sst-btn-sm sst-btn-primary" onclick="toggleAddActividad({{ $categoria->id }})">
                    <i class="bi bi-plus-lg"></i> Actividad
                </button>
                @endif
                @if($puedeEliminar)
                <form method="POST" action="{{ route('carta-gantt.categorias.destroy', $categoria) }}"
                      onsubmit="return confirm('¿Eliminar esta categoría y todas sus actividades?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="sst-btn sst-btn-sm sst-btn-danger"><i class="bi bi-trash3"></i></button>
                </form>
                @endif
            </div>
        </div>

        {{-- Formulario Agregar Actividad (oculto) --}}
        <div id="addAct-{{ $categoria->id }}" style="display:none" class="sst-add-form">
            <form method="POST" action="{{ route('carta-gantt.actividades.store', $categoria) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.65rem;margin-bottom:.65rem">
                    <div class="form-group" style="margin:0"><label class="sst-label">Nombre *</label><input type="text" name="nombre" required class="form-input" placeholder="Ej: Inspección de EPP"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Responsable</label>
                        <select name="responsable_id" class="form-input"><option value="">— Sin asignar —</option>
                            @foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->name }} {{ $u->apellido_paterno ?? '' }}</option>@endforeach
                        </select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Prioridad</label>
                        <select name="prioridad" class="form-input">@foreach(\App\Models\SstActividad::prioridadesMap() as $k => $v)<option value="{{ $k }}" {{ $k === 'MEDIA' ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Periodicidad</label>
                        <select name="periodicidad" class="form-input"><option value="">— Ninguna —</option>@foreach(\App\Models\SstActividad::periodicidadesMap() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Cantidad <small style="text-transform:none;font-weight:400">(repeticiones/mes)</small></label><input type="number" name="cantidad_programada" class="form-input" value="1" min="1" max="999" placeholder="1"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha inicio</label><input type="date" name="fecha_inicio" class="form-input"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha fin</label><input type="date" name="fecha_fin" class="form-input"></div>
                    <div class="form-group" style="margin:0;grid-column:1/-1"><label class="sst-label">Descripción</label><textarea name="descripcion" class="form-input" rows="2" placeholder="Descripción o instrucciones..."></textarea></div>
                </div>
                <div style="margin-bottom:.65rem">
                    <label class="sst-label" style="display:block;margin-bottom:.3rem">Meses programados <small style="text-transform:none;font-weight:400">(se auto-calculan con periodicidad)</small></label>
                    <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                        @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mesNom)
                        <label class="sst-mes-check"><input type="checkbox" name="meses_prog[]" value="{{ $idx + 1 }}"> {{ $mesNom }}</label>
                        @endforeach
                    </div>
                </div>
                <div style="display:flex;gap:.4rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-sm sst-btn-outline" onclick="toggleAddActividad({{ $categoria->id }})">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-sm sst-btn-primary"><i class="bi bi-plus-lg"></i> Agregar</button>
                </div>
            </form>
        </div>

        {{-- Tabla Gantt Anual --}}
        <div class="sst-table-wrap">
        <table class="sst-gantt">
            <thead><tr>
                <th class="sst-th-sticky" style="min-width:220px">Actividad</th>
                <th style="min-width:100px">Responsable</th>
                <th style="width:55px;text-align:center">Prior.</th>
                <th style="width:70px;text-align:center">Estado</th>
                @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mesNom)
                <th class="sst-th-mes {{ ($idx + 1) === $mesActual ? 'sst-mes-actual' : '' }}">{{ $mesNom }}</th>
                @endforeach
                <th style="width:70px"></th>
            </tr></thead>
            <tbody>
            @forelse($catActs as $act)
            @include('carta_gantt._activity_row', ['act' => $act, 'mesActual' => $mesActual])
            @empty
            <tr><td colspan="17" style="padding:2rem;color:var(--text-muted);font-style:italic;text-align:center">
                Sin actividades. @if($puedeCrear || $puedeEditar)<button class="sst-link" onclick="toggleAddActividad({{ $categoria->id }})">Agregar primera actividad</button>@endif
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endforeach
    </div>

    {{-- ========== AGREGAR CATEGORÍA ========== --}}
    @if($puedeCrear || $puedeEditar)
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
    @endif
</div>

{{-- ========== MODAL EDITAR ACTIVIDAD ========== --}}
<div id="editModal" class="sst-modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="sst-modal" style="max-width:640px">
        <div class="sst-modal-header">
            <h3 style="margin:0;font-size:1rem;font-weight:700"><i class="bi bi-pencil-square"></i> Editar Actividad</h3>
            <button onclick="document.getElementById('editModal').style.display='none'" class="sst-icon-btn"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sst-modal-body">
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="sst-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
                    <div class="form-group" style="margin:0;grid-column:1/-1"><label class="sst-label">Nombre *</label><input type="text" name="nombre" id="edit-nombre" required class="form-input"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Responsable</label>
                        <select name="responsable_id" id="edit-responsable" class="form-input"><option value="">— Sin asignar —</option>
                            @foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->name }} {{ $u->apellido_paterno ?? '' }}</option>@endforeach
                        </select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Prioridad</label>
                        <select name="prioridad" id="edit-prioridad" class="form-input">@foreach(\App\Models\SstActividad::prioridadesMap() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Estado</label>
                        <select name="estado" id="edit-estado" class="form-input">@foreach(\App\Models\SstActividad::estadosMap() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Periodicidad</label>
                        <select name="periodicidad" id="edit-periodicidad" class="form-input"><option value="">— Ninguna —</option>@foreach(\App\Models\SstActividad::periodicidadesMap() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Cantidad <small style="text-transform:none;font-weight:400">(repeticiones/mes)</small></label><input type="number" name="cantidad_programada" id="edit-cantidad" class="form-input" value="1" min="1" max="999"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha inicio</label><input type="date" name="fecha_inicio" id="edit-fecha-inicio" class="form-input"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha fin</label><input type="date" name="fecha_fin" id="edit-fecha-fin" class="form-input"></div>
                    <div class="form-group" style="margin:0;grid-column:1/-1"><label class="sst-label">Descripción</label><textarea name="descripcion" id="edit-descripcion" class="form-input" rows="2" placeholder="Descripción o instrucciones..."></textarea></div>
                </div>
                <div style="margin-bottom:.85rem">
                    <input type="hidden" name="has_meses_prog" value="1">
                    <label class="sst-label" style="display:block;margin-bottom:.3rem">Meses Programados <small style="text-transform:none;font-weight:400">(marcar los meses donde se debe ejecutar)</small></label>
                    <div id="edit-meses-grid" style="display:flex;gap:.35rem;flex-wrap:wrap">
                        @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $idx => $mesNom)
                        <label class="sst-mes-check"><input type="checkbox" name="meses_prog[]" value="{{ $idx + 1 }}" id="edit-mes-{{ $idx + 1 }}"> {{ $mesNom }}</label>
                        @endforeach
                    </div>
                </div>
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-outline" onclick="document.getElementById('editModal').style.display='none'">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-primary"><i class="bi bi-check-lg"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========== MODAL IMPORTACIÓN CSV ========== --}}
<div id="importModal" class="sst-modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="sst-modal" style="max-width:720px">
        <div class="sst-modal-header" style="background:linear-gradient(135deg,var(--accent-color),#818cf8);border-bottom:none">
            <h3 style="margin:0;font-size:1rem;font-weight:700;color:#fff"><i class="bi bi-cloud-upload"></i> Importar Actividades desde CSV</h3>
            <button onclick="document.getElementById('importModal').style.display='none'" class="sst-icon-btn" style="color:#fff;border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.1)"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sst-modal-body" style="padding:1.25rem">
            {{-- Pasos --}}
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:1.25rem">
                <div style="text-align:center;padding:.6rem .4rem;background:rgba(99,102,241,.06);border-radius:10px;border:1px solid rgba(99,102,241,.15)">
                    <div style="width:28px;height:28px;border-radius:50%;background:var(--accent-color);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;margin-bottom:.3rem">1</div>
                    <div style="font-size:.72rem;font-weight:600;color:var(--text-main)">Descarga la plantilla</div>
                    <div style="font-size:.65rem;color:var(--text-muted)">CSV con formato correcto</div>
                </div>
                <div style="text-align:center;padding:.6rem .4rem;background:rgba(245,158,11,.06);border-radius:10px;border:1px solid rgba(245,158,11,.15)">
                    <div style="width:28px;height:28px;border-radius:50%;background:#f59e0b;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;margin-bottom:.3rem">2</div>
                    <div style="font-size:.72rem;font-weight:600;color:var(--text-main)">Completa los datos</div>
                    <div style="font-size:.65rem;color:var(--text-muted)">Una actividad por fila</div>
                </div>
                <div style="text-align:center;padding:.6rem .4rem;background:rgba(16,185,129,.06);border-radius:10px;border:1px solid rgba(16,185,129,.15)">
                    <div style="width:28px;height:28px;border-radius:50%;background:#10b981;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;margin-bottom:.3rem">3</div>
                    <div style="font-size:.72rem;font-weight:600;color:var(--text-main)">Sube e importa</div>
                    <div style="font-size:.65rem;color:var(--text-muted)">Se crearán las actividades</div>
                </div>
            </div>

            {{-- Tabla de campos --}}
            <div style="background:var(--bg-color);border:1px solid var(--surface-border);border-radius:10px;overflow:hidden;margin-bottom:1rem">
                <div style="padding:.6rem .8rem;background:var(--surface-color);border-bottom:1px solid var(--surface-border);display:flex;align-items:center;gap:.4rem">
                    <i class="bi bi-table" style="color:var(--accent-color)"></i>
                    <span style="font-size:.78rem;font-weight:700;color:var(--text-main)">Columnas del CSV</span>
                    <span style="font-size:.65rem;color:var(--text-muted);margin-left:auto">Separador: punto y coma ( ; )</span>
                </div>
                <table style="width:100%;font-size:.73rem;border-collapse:collapse">
                    <thead>
                        <tr style="background:var(--surface-color)">
                            <th style="padding:.4rem .6rem;text-align:left;font-weight:700;color:var(--text-muted);text-transform:uppercase;font-size:.62rem;letter-spacing:.03em;border-bottom:1px solid var(--surface-border)">Columna</th>
                            <th style="padding:.4rem .6rem;text-align:center;font-weight:700;color:var(--text-muted);text-transform:uppercase;font-size:.62rem;letter-spacing:.03em;border-bottom:1px solid var(--surface-border);width:50px"></th>
                            <th style="padding:.4rem .6rem;text-align:left;font-weight:700;color:var(--text-muted);text-transform:uppercase;font-size:.62rem;letter-spacing:.03em;border-bottom:1px solid var(--surface-border)">Descripción</th>
                            <th style="padding:.4rem .6rem;text-align:left;font-weight:700;color:var(--text-muted);text-transform:uppercase;font-size:.62rem;letter-spacing:.03em;border-bottom:1px solid var(--surface-border)">Ejemplo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">categoria</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:#fee2e2;color:#dc2626;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Req.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Nombre de la categoría. Si no existe, se crea automáticamente.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">Capacitaciones</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">nombre</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:#fee2e2;color:#dc2626;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Req.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Nombre de la actividad o tarea SST.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">Inducción SST</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">responsable_email</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Email del usuario responsable. Si no se encuentra, se guarda como texto.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">user@saep.cl</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">prioridad</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">ALTA, MEDIA o BAJA. Por defecto: MEDIA.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">ALTA</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">periodicidad</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">UNICA, DIARIA, SEMANAL, QUINCENAL, MENSUAL, BIMENSUAL, TRIMESTRAL, SEMESTRAL o ANUAL.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">MENSUAL</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">cantidad</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Repeticiones por mes. Si la tarea se debe hacer 4 veces al mes, poner 4. Por defecto: 1.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">4</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">fecha_inicio</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Formato AAAA-MM-DD. Se auto-calcula desde los meses programados si se omite.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">{{ $cartaGantt->anio }}-01-15</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--surface-border)">
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">fecha_fin</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Formato AAAA-MM-DD. Se auto-calcula desde los meses programados si se omite.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">{{ $cartaGantt->anio }}-12-31</td>
                        </tr>
                        <tr>
                            <td style="padding:.35rem .6rem;font-weight:600;color:var(--text-main)">meses_programados</td>
                            <td style="padding:.35rem .6rem;text-align:center"><span style="background:rgba(99,102,241,.1);color:#6366f1;padding:.1rem .35rem;border-radius:4px;font-size:.6rem;font-weight:700">Opc.</span></td>
                            <td style="padding:.35rem .6rem;color:var(--text-muted)">Números del 1 al 12 separados por coma. Si se omite, se calculan desde la periodicidad.</td>
                            <td style="padding:.35rem .6rem;font-family:monospace;color:var(--accent-color);font-size:.68rem">1,3,6,9,12</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Tips --}}
            <div class="sst-import-tips" style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.15rem">
                <div style="padding:.5rem .65rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:8px;font-size:.7rem">
                    <i class="bi bi-check-circle-fill" style="color:#10b981"></i>
                    <strong style="color:var(--text-main)">Auto-categorías:</strong>
                    <span style="color:var(--text-muted)">Si la categoría no existe, se crea automáticamente.</span>
                </div>
                <div style="padding:.5rem .65rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:8px;font-size:.7rem">
                    <i class="bi bi-check-circle-fill" style="color:#10b981"></i>
                    <strong style="color:var(--text-main)">Auto-meses:</strong>
                    <span style="color:var(--text-muted)">Sin meses_programados, se calculan desde la periodicidad.</span>
                </div>
                <div style="padding:.5rem .65rem;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);border-radius:8px;font-size:.7rem">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b"></i>
                    <strong style="color:var(--text-main)">Separador:</strong>
                    <span style="color:var(--text-muted)">El CSV debe usar punto y coma ( ; ) no coma.</span>
                </div>
                <div style="padding:.5rem .65rem;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);border-radius:8px;font-size:.7rem">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b"></i>
                    <strong style="color:var(--text-main)">Codificación:</strong>
                    <span style="color:var(--text-muted)">Guardar como UTF-8 para conservar tildes y ñ.</span>
                </div>
            </div>

            {{-- Descargar + Upload --}}
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.15rem;padding:.65rem .8rem;background:rgba(99,102,241,.04);border:1px dashed rgba(99,102,241,.25);border-radius:10px">
                <i class="bi bi-file-earmark-spreadsheet" style="font-size:1.5rem;color:var(--accent-color)"></i>
                <div style="flex:1">
                    <div style="font-size:.78rem;font-weight:600;color:var(--text-main)">¿Primera vez? Descarga la plantilla</div>
                    <div style="font-size:.68rem;color:var(--text-muted)">Incluye las cabeceras y un ejemplo de fila con todos los campos.</div>
                </div>
                <a href="{{ route('carta-gantt.plantilla') }}" class="sst-btn sst-btn-sm sst-btn-primary" style="white-space:nowrap"><i class="bi bi-download"></i> Descargar Plantilla</a>
            </div>

            <form method="POST" action="{{ route('carta-gantt.importar', $cartaGantt) }}" enctype="multipart/form-data">
                @csrf
                <div style="margin-bottom:1rem">
                    <label class="sst-label" style="margin-bottom:.4rem">Archivo CSV *</label>
                    <div style="position:relative;border:2px dashed var(--surface-border);border-radius:10px;padding:1.25rem;text-align:center;transition:all .2s;cursor:pointer" id="dropZone"
                         ondragover="event.preventDefault();this.style.borderColor='var(--accent-color)';this.style.background='rgba(99,102,241,.04)'"
                         ondragleave="this.style.borderColor='var(--surface-border)';this.style.background='transparent'"
                         ondrop="event.preventDefault();this.style.borderColor='var(--surface-border)';this.style.background='transparent';document.getElementById('csvFileInput').files=event.dataTransfer.files;document.getElementById('csvFileName').textContent=event.dataTransfer.files[0]?.name||'';document.getElementById('csvFileInfo').style.display='flex'"
                         onclick="document.getElementById('csvFileInput').click()">
                        <input type="file" name="archivo" id="csvFileInput" required accept=".csv,.txt" class="form-input" style="display:none"
                               onchange="document.getElementById('csvFileName').textContent=this.files[0]?.name||'';document.getElementById('csvFileInfo').style.display=this.files[0]?'flex':'none'">
                        <div id="csvFileInfo" style="display:none;align-items:center;justify-content:center;gap:.5rem">
                            <i class="bi bi-file-earmark-check" style="font-size:1.3rem;color:#10b981"></i>
                            <span id="csvFileName" style="font-size:.82rem;font-weight:600;color:var(--text-main)"></span>
                        </div>
                        <div id="csvPlaceholder">
                            <i class="bi bi-cloud-arrow-up" style="font-size:1.8rem;color:var(--text-muted);display:block;margin-bottom:.3rem"></i>
                            <div style="font-size:.82rem;font-weight:600;color:var(--text-main)">Arrastra tu archivo CSV aquí</div>
                            <div style="font-size:.7rem;color:var(--text-muted);margin-top:.15rem">o haz clic para seleccionar · Máx. 5 MB · Formato .csv o .txt</div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-outline" onclick="document.getElementById('importModal').style.display='none'">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-primary"><i class="bi bi-cloud-upload"></i> Importar Actividades</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========== MODAL DETALLE ACTIVIDAD ========== --}}
<div id="detailModal" class="sst-modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="sst-modal" style="max-width:700px">
        <div class="sst-modal-header">
            <h3 id="detail-title" style="margin:0;font-size:1rem;font-weight:700"><i class="bi bi-info-circle"></i> Detalle</h3>
            <button onclick="document.getElementById('detailModal').style.display='none'" class="sst-icon-btn"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sst-modal-body" id="detail-body"></div>
    </div>
</div>

{{-- Modal Reprogramar --}}
<div id="reprogramarModal" class="sst-modal-overlay" style="display:none" onclick="if(event.target===this)closeReprogramar()">
    <div class="sst-modal" style="max-width:480px">
        <div class="sst-modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;">
            <h3 style="margin:0;font-size:1rem;font-weight:700"><i class="bi bi-calendar2-range"></i> Reprogramar Actividad</h3>
            <button onclick="closeReprogramar()" class="sst-icon-btn" style="color:#fff"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sst-modal-body">
            <form method="POST" id="reprogramarForm">
                @csrf
                <div style="margin-bottom:.85rem">
                    <label class="sst-label">Mes vencido a reprogramar *</label>
                    <select name="mes_original" id="reprog_mes_original" required class="form-input" style="font-size:.85rem"></select>
                </div>
                <div style="margin-bottom:.85rem">
                    <label class="sst-label">Nuevo mes (actual o futuro) *</label>
                    <select name="mes_nuevo" id="reprog_mes_nuevo" required class="form-input" style="font-size:.85rem"></select>
                </div>
                <div style="margin-bottom:.85rem">
                    <label class="sst-label">Motivo de reprogramación *</label>
                    <textarea name="motivo" id="reprog_motivo" required class="form-input" rows="3" maxlength="500" placeholder="Explique por qué se reprograma esta actividad..." style="font-size:.85rem;resize:vertical"></textarea>
                </div>
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-outline" onclick="closeReprogramar()">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-primary" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                        <i class="bi bi-calendar2-range"></i> Reprogramar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('carta_gantt._styles')
@include('carta_gantt._scripts', ['actividadesJson' => $actividadesJson, 'anioPrograma' => $anioPrograma, 'mesActual' => $mesActual, 'mesesNombres' => $mesesNombres])
@endsection
