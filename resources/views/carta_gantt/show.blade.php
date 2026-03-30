@extends('layouts.app')
@section('title', $cartaGantt->nombre)
@section('content')
@php
    $mesActual = (int) date('n');
    $semanaActual = (int) date('W');
    $diaActual = (int) date('j');
    $anioPrograma = (int) $cartaGantt->anio;
    $totalAct = $cartaGantt->actividadesTotales;
    $vencidas = $cartaGantt->actividadesVencidas;
    $pct = $cartaGantt->porcentajeRealizado;
    $completadas = 0; $enProgreso = 0; $pendientes = 0; $porVencer = 0;
    $allActividades = collect();
    foreach ($cartaGantt->categorias as $cat) {
        foreach ($cat->actividades as $a) {
            if ($a->estado === 'COMPLETADA') $completadas++;
            elseif ($a->estado === 'EN_PROGRESO') $enProgreso++;
            else $pendientes++;
            if ($a->estaPorVencer) $porVencer++;
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
            <div><div class="sst-stat-label">Total Actividades</div><div class="sst-stat-value">{{ $totalAct }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#10b981,#34d399)"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="sst-stat-label">Completadas</div><div class="sst-stat-value" style="color:#10b981">{{ $completadas }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="bi bi-clock-history"></i></div>
            <div><div class="sst-stat-label">En Progreso</div><div class="sst-stat-value" style="color:#f59e0b">{{ $enProgreso }}</div></div>
        </div>
        <div class="sst-stat-card">
            <div class="sst-stat-icon" style="background:linear-gradient(135deg,#ef4444,#f87171)"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div><div class="sst-stat-label">Vencidas</div><div class="sst-stat-value" style="color:#ef4444">{{ $vencidas }}</div></div>
        </div>
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
            foreach ($a->seguimiento as $s) { if ($s->programado) $catProg++; if ($s->realizado) $catReal++; }
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
                    <div class="form-group" style="margin:0"><label class="sst-label">Nombre *</label><input type="text" name="nombre" required class="form-input" placeholder="Ej: Inspección de EPP"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Responsable</label>
                        <select name="responsable_id" class="form-input"><option value="">— Sin asignar —</option>
                            @foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->name }} {{ $u->apellido_paterno ?? '' }}</option>@endforeach
                        </select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Prioridad</label>
                        <select name="prioridad" class="form-input">@foreach(\App\Models\SstActividad::prioridadesMap() as $k => $v)<option value="{{ $k }}" {{ $k === 'MEDIA' ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Periodicidad</label>
                        <select name="periodicidad" class="form-input"><option value="">— Ninguna —</option>@foreach(\App\Models\SstActividad::periodicidadesMap() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha inicio</label><input type="date" name="fecha_inicio" class="form-input"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha fin</label><input type="date" name="fecha_fin" class="form-input"></div>
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
                Sin actividades. <button class="sst-link" onclick="toggleAddActividad({{ $categoria->id }})">Agregar primera actividad</button>
            </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endforeach
    </div>

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
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
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
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha inicio</label><input type="date" name="fecha_inicio" id="edit-fecha-inicio" class="form-input"></div>
                    <div class="form-group" style="margin:0"><label class="sst-label">Fecha fin</label><input type="date" name="fecha_fin" id="edit-fecha-fin" class="form-input"></div>
                    <div class="form-group" style="margin:0;grid-column:1/-1"><label class="sst-label">Descripción</label><textarea name="descripcion" id="edit-descripcion" class="form-input" rows="2" placeholder="Descripción o instrucciones..."></textarea></div>
                </div>
                <div style="margin-bottom:.85rem">
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
    <div class="sst-modal">
        <div class="sst-modal-header">
            <h3 style="margin:0;font-size:1rem;font-weight:700"><i class="bi bi-cloud-upload"></i> Importar Actividades desde CSV</h3>
            <button onclick="document.getElementById('importModal').style.display='none'" class="sst-icon-btn"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sst-modal-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin:0 0 1rem">Sube un archivo CSV con las actividades. Se crearán las categorías automáticamente si no existen.</p>
            <div style="background:var(--surface-color);border:1px solid var(--surface-border);border-radius:10px;padding:1rem;margin-bottom:1rem">
                <p style="font-size:.78rem;font-weight:600;margin:0 0 .5rem;color:var(--text-main)"><i class="bi bi-info-circle"></i> Formato requerido (separador: punto y coma ;)</p>
                <div style="font-family:monospace;font-size:.72rem;color:var(--text-muted);overflow-x:auto;white-space:nowrap">
                    categoria;nombre;responsable_email;prioridad;periodicidad;fecha_inicio;fecha_fin;meses_programados<br>
                    <span style="color:var(--accent-color)">Capacitaciones;Inducción SST;user@saep.cl;ALTA;MENSUAL;2026-01-15;2026-12-31;1,3,6,9,12</span>
                </div>
            </div>
            <a href="{{ route('carta-gantt.plantilla') }}" class="sst-btn sst-btn-outline sst-btn-sm" style="margin-bottom:1rem;display:inline-flex"><i class="bi bi-download"></i> Descargar Plantilla CSV</a>
            <form method="POST" action="{{ route('carta-gantt.importar', $cartaGantt) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group" style="margin-bottom:1rem"><label class="sst-label">Archivo CSV *</label><input type="file" name="archivo" required accept=".csv,.txt" class="form-input"></div>
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <button type="button" class="sst-btn sst-btn-outline" onclick="document.getElementById('importModal').style.display='none'">Cancelar</button>
                    <button type="submit" class="sst-btn sst-btn-primary"><i class="bi bi-cloud-upload"></i> Importar</button>
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

@include('carta_gantt._styles')
@include('carta_gantt._scripts', ['actividadesJson' => $actividadesJson, 'anioPrograma' => $anioPrograma, 'mesActual' => $mesActual, 'mesesNombres' => $mesesNombres])
@endsection
