@extends('layouts.app')
@section('title', 'Dashboard Ley Karin')

@push('styles')
<style>
/* ====================================================
   MULTI-SELECT DROPDOWN COMPONENT
   ==================================================== */
.ms-wrap { position:relative; display:inline-block; width:100%; }
.ms-btn {
    display:flex; align-items:center; justify-content:space-between; gap:.4rem;
    width:100%; min-height:36px; padding:.35rem .65rem;
    background:var(--glass-bg,rgba(255,255,255,.85));
    border:1px solid rgba(15,27,76,.15); border-radius:8px;
    cursor:pointer; font-size:.8rem; color:var(--text-main);
    text-align:left; transition:border-color .2s;
}
.ms-btn:hover { border-color:var(--primary-color,#0f1b4c); }
.ms-btn.open  { border-color:var(--primary-color,#0f1b4c); box-shadow:0 0 0 3px rgba(15,27,76,.08); }
.ms-pills { display:flex; flex-wrap:wrap; gap:.25rem; flex:1; min-width:0; }
.ms-pill {
    background:rgba(15,27,76,.1); color:var(--primary-color,#0f1b4c);
    border-radius:4px; padding:.1rem .4rem; font-size:.72rem; font-weight:600;
    white-space:nowrap; max-width:120px; overflow:hidden; text-overflow:ellipsis;
}
.ms-placeholder { color:var(--text-muted); font-size:.8rem; }
.ms-arrow { font-size:.7rem; color:var(--text-muted); flex-shrink:0; transition:transform .2s; }
.ms-btn.open .ms-arrow { transform:rotate(180deg); }
.ms-dropdown {
    position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:9999;
    background:var(--glass-bg,#fff); border:1px solid rgba(15,27,76,.15);
    border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12);
    padding:.4rem 0; max-height:220px; overflow-y:auto; display:none;
}
.ms-dropdown.open { display:block; }
.ms-option {
    display:flex; align-items:center; gap:.5rem;
    padding:.4rem .75rem; cursor:pointer; font-size:.82rem;
    transition:background .15s;
}
.ms-option:hover { background:rgba(15,27,76,.06); }
.ms-option input[type=checkbox] { width:14px; height:14px; cursor:pointer; accent-color:var(--primary-color,#0f1b4c); }

/* ====================================================
   CHART SECTION HEADERS
   ==================================================== */
.dash-section-title {
    font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
    color:var(--text-muted); display:flex; align-items:center; gap:.5rem;
    margin:1.5rem 0 .75rem; padding-bottom:.5rem;
    border-bottom:1px solid rgba(15,27,76,.08);
}
.dash-section-title i { font-size:.9rem; }

/* ====================================================
   KPI CARDS
   ==================================================== */
.kpi-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
    gap:.75rem; margin-bottom:1.5rem;
}
.kpi-card {
    padding:.9rem 1rem; display:flex; flex-direction:column; gap:.2rem;
    border-left:3px solid transparent;
}
.kpi-card .kpi-val { font-size:1.55rem; font-weight:800; line-height:1; }
.kpi-card .kpi-lbl { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); }

/* ====================================================
   CHART GRIDS
   ==================================================== */
.chart-row-2 { display:grid; grid-template-columns:2fr 1fr; gap:1rem; margin-bottom:1rem; }
.chart-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
.chart-row-2-equal { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
@media(max-width:900px) {
    .chart-row-2,.chart-row-3,.chart-row-2-equal { grid-template-columns:1fr; }
}
.chart-card { padding:1rem 1.25rem; }
.chart-card h4 {
    font-size:.75rem; text-transform:uppercase; letter-spacing:.04em;
    color:var(--text-muted); margin:0 0 .75rem; font-weight:700;
    display:flex; align-items:center; gap:.4rem;
}
.chart-canvas-wrap { position:relative; }
</style>
@endpush

@section('content')
<div class="page-container">

    @include('partials._alerts')

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-bar-chart-line-fill" style="color:#dc2626;"></i> Dashboard Ley Karin
            </h2>
            <p class="page-subheading">Indicadores y estadísticas — Ley 21.643</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <a href="{{ route('ley-karin.index') }}" class="btn-ghost">
                <i class="bi bi-list-ul"></i> Ver listado
            </a>
            @if(auth()->user()->tieneAcceso('ley_karin','puede_crear'))
            <a href="{{ route('ley-karin.create') }}" class="btn-premium">
                <i class="bi bi-plus-circle-fill"></i> Nueva Denuncia
            </a>
            @endif
        </div>
    </div>

    {{-- =====================================================
         PANEL DE FILTROS
         ===================================================== --}}
    <div class="glass-card" style="margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('ley-karin.dashboard') }}" id="filterForm">
            <div style="display:flex;flex-wrap:wrap;gap:.65rem;align-items:flex-end;">

                {{-- Rango de fechas --}}
                <div>
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Desde</label>
                    <input type="date" name="desde" value="{{ request('desde') }}" class="form-input" style="font-size:.8rem;padding:.35rem .6rem;width:140px;">
                </div>
                <div>
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Hasta</label>
                    <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-input" style="font-size:.8rem;padding:.35rem .6rem;width:140px;">
                </div>

                {{-- Tipo --}}
                <div style="width:180px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Tipo</label>
                    @php $selTipo = (array)request('tipo', []) @endphp
                    <div class="ms-wrap" data-name="tipo">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selTipo as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::tiposMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::tiposMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="tipo[]" value="{{ $val }}" {{ in_array($val,$selTipo)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Estado --}}
                <div style="width:180px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Estado</label>
                    @php $selEstado = (array)request('estado', []) @endphp
                    <div class="ms-wrap" data-name="estado">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selEstado as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::estadosMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::estadosMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="estado[]" value="{{ $val }}" {{ in_array($val,$selEstado)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Canal --}}
                <div style="width:180px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Canal</label>
                    @php $selCanal = (array)request('canal', []) @endphp
                    <div class="ms-wrap" data-name="canal">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selCanal as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::canalesMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::canalesMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="canal[]" value="{{ $val }}" {{ in_array($val,$selCanal)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Centro de Costo --}}
                <div style="width:200px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Centro de Costo</label>
                    @php $selCentro = (array)request('centro_costo_id', []) @endphp
                    <div class="ms-wrap" data-name="centro_costo_id">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selCentro as $cid)
                                    @php $cc = $centros->firstWhere('id', $cid) @endphp
                                    <span class="ms-pill">{{ $cc?->nombre ?? $cid }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach($centros as $cc)
                            <label class="ms-option">
                                <input type="checkbox" name="centro_costo_id[]" value="{{ $cc->id }}" {{ in_array($cc->id,$selCentro)?'checked':'' }}>
                                {{ $cc->nombre }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Denunciante: Sexo --}}
                <div style="width:160px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Sexo Denunciante</label>
                    @php $selDnSexo = (array)request('denunciante_sexo', []) @endphp
                    <div class="ms-wrap">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selDnSexo as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::sexosMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::sexosMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="denunciante_sexo[]" value="{{ $val }}" {{ in_array($val,$selDnSexo)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Denunciante: Rango Etario --}}
                <div style="width:160px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Rango Denunciante</label>
                    @php $selDnRango = (array)request('denunciante_rango_etario', []) @endphp
                    <div class="ms-wrap">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selDnRango as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::rangosEtariosMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::rangosEtariosMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="denunciante_rango_etario[]" value="{{ $val }}" {{ in_array($val,$selDnRango)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Denunciante: Empresa --}}
                <div style="width:160px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Empresa Denunciante</label>
                    @php $selDnEmpresa = (array)request('denunciante_empresa', []) @endphp
                    <div class="ms-wrap">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selDnEmpresa as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::empresasDenuncianteMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::empresasDenuncianteMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="denunciante_empresa[]" value="{{ $val }}" {{ in_array($val,$selDnEmpresa)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Denunciado: Sexo --}}
                <div style="width:160px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Sexo Denunciado</label>
                    @php $selDdSexo = (array)request('denunciado_sexo', []) @endphp
                    <div class="ms-wrap">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selDdSexo as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::sexosMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::sexosMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="denunciado_sexo[]" value="{{ $val }}" {{ in_array($val,$selDdSexo)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Denunciado: Rango Etario --}}
                <div style="width:160px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Rango Denunciado</label>
                    @php $selDdRango = (array)request('denunciado_rango_etario', []) @endphp
                    <div class="ms-wrap">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selDdRango as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::rangosEtariosMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::rangosEtariosMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="denunciado_rango_etario[]" value="{{ $val }}" {{ in_array($val,$selDdRango)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Denunciado: Empresa --}}
                <div style="width:160px;">
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Empresa Denunciado</label>
                    @php $selDdEmpresa = (array)request('denunciado_empresa', []) @endphp
                    <div class="ms-wrap">
                        <button type="button" class="ms-btn" onclick="msToggle(this)">
                            <span class="ms-pills">
                                @forelse($selDdEmpresa as $v)
                                    <span class="ms-pill">{{ \App\Models\LeyKarin::empresasDenunciadoMap()[$v] ?? $v }}</span>
                                @empty
                                    <span class="ms-placeholder">Todos</span>
                                @endforelse
                            </span>
                            <i class="bi bi-chevron-down ms-arrow"></i>
                        </button>
                        <div class="ms-dropdown">
                            @foreach(\App\Models\LeyKarin::empresasDenunciadoMap() as $val => $lbl)
                            <label class="ms-option">
                                <input type="checkbox" name="denunciado_empresa[]" value="{{ $val }}" {{ in_array($val,$selDdEmpresa)?'checked':'' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Anónima --}}
                <div>
                    <label style="font-size:.68rem;text-transform:uppercase;color:var(--text-muted);display:block;margin-bottom:.25rem;">Anónima</label>
                    <select name="anonima" class="form-input" style="font-size:.8rem;padding:.35rem .6rem;width:110px;">
                        <option value="">Todos</option>
                        <option value="1" {{ request('anonima')==='1'?'selected':'' }}>Sí</option>
                        <option value="0" {{ request('anonima')==='0'?'selected':'' }}>No</option>
                    </select>
                </div>

                {{-- Botones --}}
                <div style="display:flex;gap:.4rem;align-items:flex-end;padding-bottom:1px;">
                    <button type="submit" class="btn-premium" style="padding:.45rem 1rem;font-size:.82rem;">
                        <i class="bi bi-funnel-fill"></i> Filtrar
                    </button>
                    @if(request()->except([]))
                    <a href="{{ route('ley-karin.dashboard') }}" class="btn-ghost" style="padding:.45rem .7rem;font-size:.82rem;" title="Limpiar filtros">
                        <i class="bi bi-x-circle"></i>
                    </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- =====================================================
         KPIs
         ===================================================== --}}
    <div class="kpi-grid">
        @php
        $kpiCards = [
            ['val'=>$kpis['total'],            'lbl'=>'Total Casos',          'color'=>'#0f1b4c', 'icon'=>'bi-folder-fill'],
            ['val'=>$kpis['recibidas'],         'lbl'=>'Recibidas',            'color'=>'#0891b2', 'icon'=>'bi-inbox-fill'],
            ['val'=>$kpis['en_investigacion'],  'lbl'=>'En Investigación',     'color'=>'#d97706', 'icon'=>'bi-search'],
            ['val'=>$kpis['resueltas'],         'lbl'=>'Resueltas',            'color'=>'#16a34a', 'icon'=>'bi-check-circle-fill'],
            ['val'=>$kpis['derivadas'],         'lbl'=>'Derivadas a DT',       'color'=>'#7c3aed', 'icon'=>'bi-arrow-right-circle-fill'],
            ['val'=>$kpis['archivadas'],        'lbl'=>'Archivadas',           'color'=>'#64748b', 'icon'=>'bi-archive-fill'],
            ['val'=>$kpis['anonimas'],          'lbl'=>'Anónimas',             'color'=>'#6366f1', 'icon'=>'bi-incognito'],
            ['val'=>$kpis['terceros'],          'lbl'=>'Por Tercero',          'color'=>'#ec4899', 'icon'=>'bi-people-fill'],
            ['val'=>$kpis['con_medidas'],       'lbl'=>'Con Medidas Cautelares','color'=>'#dc2626','icon'=>'bi-shield-fill-exclamation'],
            ['val'=>$kpis['promedio_dias'] !== null ? $kpis['promedio_dias'].' días' : '—',
                                                'lbl'=>'Prom. Días Resolución','color'=>'#0ea5e9', 'icon'=>'bi-clock-fill'],
        ];
        @endphp
        @foreach($kpiCards as $kc)
        <div class="glass-card kpi-card" style="border-left-color:{{ $kc['color'] }};">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem;">
                <div style="width:32px;height:32px;border-radius:8px;background:{{ $kc['color'] }}20;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi {{ $kc['icon'] }}" style="font-size:.85rem;color:{{ $kc['color'] }};"></i>
                </div>
                <span class="kpi-val" style="color:{{ $kc['color'] }};">{{ $kc['val'] }}</span>
            </div>
            <span class="kpi-lbl">{{ $kc['lbl'] }}</span>
        </div>
        @endforeach
    </div>

    {{-- =====================================================
         SECCIÓN: GENERAL
         ===================================================== --}}
    <div class="dash-section-title">
        <i class="bi bi-graph-up-arrow" style="color:#0f1b4c;"></i> Visión General
    </div>

    {{-- Tendencia mensual (2/3) + Por tipo (1/3) --}}
    <div class="chart-row-2">
        <div class="glass-card chart-card">
            <h4><i class="bi bi-bar-chart-steps"></i> Tendencia Mensual de Denuncias</h4>
            <div class="chart-canvas-wrap" style="height:260px;"><canvas id="chartTrend"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-pie-chart-fill"></i> Distribución por Tipo</h4>
            <div class="chart-canvas-wrap" style="height:260px;display:flex;align-items:center;justify-content:center;"><canvas id="chartTipo"></canvas></div>
        </div>
    </div>

    {{-- Por estado + Por canal --}}
    <div class="chart-row-2-equal" style="margin-bottom:1rem;">
        <div class="glass-card chart-card">
            <h4><i class="bi bi-circle-half"></i> Por Estado Actual</h4>
            <div class="chart-canvas-wrap" style="height:250px;display:flex;align-items:center;justify-content:center;"><canvas id="chartEstado"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-broadcast"></i> Canal de Denuncia</h4>
            <div class="chart-canvas-wrap" style="height:250px;"><canvas id="chartCanal"></canvas></div>
        </div>
    </div>

    {{-- Por centro de costo --}}
    <div class="glass-card chart-card" style="margin-bottom:1rem;">
        <h4><i class="bi bi-building"></i> Denuncias por Centro de Costo (Top 10)</h4>
        <div class="chart-canvas-wrap" style="height:{{ max(160, count($byCentro) * 36) }}px;"><canvas id="chartCentro"></canvas></div>
    </div>

    {{-- =====================================================
         SECCIÓN: DENUNCIANTE
         ===================================================== --}}
    <div class="dash-section-title">
        <i class="bi bi-person-fill" style="color:#0891b2;"></i> Perfil del Denunciante
    </div>

    <div class="chart-row-3">
        <div class="glass-card chart-card">
            <h4><i class="bi bi-gender-ambiguous"></i> Sexo</h4>
            <div class="chart-canvas-wrap" style="height:220px;display:flex;align-items:center;justify-content:center;"><canvas id="chartDnSexo"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-person-vcard"></i> Rango Etario</h4>
            <div class="chart-canvas-wrap" style="height:220px;"><canvas id="chartDnRango"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-briefcase-fill"></i> Cargo</h4>
            <div class="chart-canvas-wrap" style="height:220px;"><canvas id="chartDnCargo"></canvas></div>
        </div>
    </div>

    <div class="chart-row-2-equal">
        <div class="glass-card chart-card">
            <h4><i class="bi bi-buildings-fill"></i> Empresa</h4>
            <div class="chart-canvas-wrap" style="height:220px;display:flex;align-items:center;justify-content:center;"><canvas id="chartDnEmpresa"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-diagram-3-fill"></i> Jerarquía del Denunciado respecto al Denunciante</h4>
            <div class="chart-canvas-wrap" style="height:220px;"><canvas id="chartDnJerarquia"></canvas></div>
        </div>
    </div>

    {{-- =====================================================
         SECCIÓN: DENUNCIADO
         ===================================================== --}}
    <div class="dash-section-title">
        <i class="bi bi-person-x-fill" style="color:#dc2626;"></i> Perfil del Denunciado
    </div>

    <div class="chart-row-3">
        <div class="glass-card chart-card">
            <h4><i class="bi bi-gender-ambiguous"></i> Sexo</h4>
            <div class="chart-canvas-wrap" style="height:220px;display:flex;align-items:center;justify-content:center;"><canvas id="chartDdSexo"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-person-vcard"></i> Rango Etario</h4>
            <div class="chart-canvas-wrap" style="height:220px;"><canvas id="chartDdRango"></canvas></div>
        </div>
        <div class="glass-card chart-card">
            <h4><i class="bi bi-briefcase-fill"></i> Cargo</h4>
            <div class="chart-canvas-wrap" style="height:220px;"><canvas id="chartDdCargo"></canvas></div>
        </div>
    </div>

    <div class="glass-card chart-card" style="max-width:480px;margin-bottom:1rem;">
        <h4><i class="bi bi-buildings-fill"></i> Empresa del Denunciado</h4>
        <div class="chart-canvas-wrap" style="height:220px;display:flex;align-items:center;justify-content:center;"><canvas id="chartDdEmpresa"></canvas></div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// ============================================================
// MULTI-SELECT DROPDOWN LOGIC
// ============================================================
function msToggle(btn) {
    const wrap = btn.closest('.ms-wrap');
    const dropdown = wrap.querySelector('.ms-dropdown');
    const isOpen = dropdown.classList.contains('open');
    // Close all others
    document.querySelectorAll('.ms-dropdown.open').forEach(d => {
        d.classList.remove('open');
        d.previousElementSibling?.classList.remove('open');
    });
    if (!isOpen) {
        dropdown.classList.add('open');
        btn.classList.add('open');
    }
}
// Close on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.ms-wrap')) {
        document.querySelectorAll('.ms-dropdown.open').forEach(d => {
            d.classList.remove('open');
            d.previousElementSibling?.classList.remove('open');
        });
    }
});
// Update pills when checkbox changes
document.querySelectorAll('.ms-dropdown input[type=checkbox]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        const wrap = this.closest('.ms-wrap');
        const pillsEl = wrap.querySelector('.ms-pills');
        const checked = wrap.querySelectorAll('input[type=checkbox]:checked');
        pillsEl.innerHTML = checked.length === 0
            ? '<span class="ms-placeholder">Todos</span>'
            : Array.from(checked).map(c => `<span class="ms-pill">${c.parentElement.textContent.trim()}</span>`).join('');
    });
});

// ============================================================
// CHART.JS GLOBAL DEFAULTS
// ============================================================
const isDark = document.body.classList.contains('dark-mode');
const gridColor = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
const textColor = isDark ? '#94a3b8' : '#64748b';

Chart.defaults.color = textColor;
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
Chart.defaults.plugins.legend.labels.boxWidth = 8;

const palette = [
    '#6366f1','#f97316','#06b6d4','#22c55e','#ef4444','#a855f7',
    '#ec4899','#14b8a6','#f59e0b','#3b82f6','#84cc16','#e11d48',
    '#8b5cf6','#0ea5e9','#d946ef','#64748b'
];

// ============================================================
// HELPERS
// ============================================================
function labeledChart(rawData, labelsMap) {
    // rawData: {KEY: count, ...}   labelsMap: {KEY: 'Nombre legible', ...}
    const keys = Object.keys(rawData);
    return {
        labels: keys.map(k => labelsMap[k] ?? k),
        values: keys.map(k => rawData[k]),
    };
}

function donut(id, data, labelsMap) {
    const d = labeledChart(data, labelsMap);
    if (!d.values.length) return;
    new Chart(document.getElementById(id), {
        type: 'doughnut',
        data: {
            labels: d.labels,
            datasets: [{ data: d.values, backgroundColor: palette, borderWidth: 2, hoverOffset: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10 } },
                tooltip: { callbacks: {
                    label: ctx => ` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed / ctx.dataset.data.reduce((a,b)=>a+b,0)*100)}%)`
                }}
            }
        }
    });
}

function hBar(id, data, labelsMap, color) {
    const d = labeledChart(data, labelsMap);
    if (!d.values.length) return;
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [{ data: d.values, backgroundColor: color ?? 'rgba(99,102,241,.7)', borderRadius: 4, borderSkipped: false }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                y: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });
}

function vBar(id, data, labelsMap, color) {
    const d = labeledChart(data, labelsMap);
    if (!d.values.length) return;
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [{ data: d.values, backgroundColor: color ?? palette, borderRadius: 4 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                x: { grid: { display: false } }
            }
        }
    });
}

// ============================================================
// LABELS MAPS (PHP → JS)
// ============================================================
const tiposMap      = @json(\App\Models\LeyKarin::tiposMap());
const estadosMap    = @json(\App\Models\LeyKarin::estadosMap());
const canalesMap    = @json(\App\Models\LeyKarin::canalesMap());
const rangosMap     = @json(\App\Models\LeyKarin::rangosEtariosMap());
const sexosMap      = @json(\App\Models\LeyKarin::sexosMap());
const cargosMap     = @json(\App\Models\LeyKarin::cargosTipoMap());
const empDnMap      = @json(\App\Models\LeyKarin::empresasDenuncianteMap());
const empDdMap      = @json(\App\Models\LeyKarin::empresasDenunciadoMap());
const jerarquiasMap = @json(\App\Models\LeyKarin::jerarquiasMap());

// ============================================================
// RAW DATA (from PHP)
// ============================================================
const trend          = @json($trend);
const byTipo         = @json($byTipo);
const byEstado       = @json($byEstado);
const byCanal        = @json($byCanal);
const byCentro       = @json($byCentro);
const byDnSexo       = @json($byDenuncianteSexo);
const byDnRango      = @json($byDenuncianteRango);
const byDnCargo      = @json($byDenuncianteCargo);
const byDnEmpresa    = @json($byDenuncianteEmpresa);
const byDnJerarquia  = @json($byDenuncianteJerarquia);
const byDdSexo       = @json($byDenunciadoSexo);
const byDdRango      = @json($byDenunciadoRango);
const byDdCargo      = @json($byDenunciadoCargo);
const byDdEmpresa    = @json($byDenunciadoEmpresa);

// ============================================================
// RENDER CHARTS
// ============================================================

// Tendencia mensual
(function() {
    const keys = Object.keys(trend);
    const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    new Chart(document.getElementById('chartTrend'), {
        type: 'bar',
        data: {
            labels: keys.map(k => { const [y,m] = k.split('-'); return months[parseInt(m)-1]+' '+y.slice(2); }),
            datasets: [{
                label: 'Denuncias',
                data: Object.values(trend),
                backgroundColor: 'rgba(99,102,241,.65)',
                borderColor: '#6366f1', borderWidth: 1.5, borderRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                x: { grid: { display: false } }
            }
        }
    });
})();

// Donuts
donut('chartTipo',     byTipo,      tiposMap);
donut('chartEstado',   byEstado,    estadosMap);
donut('chartDnSexo',   byDnSexo,   sexosMap);
donut('chartDnEmpresa',byDnEmpresa, empDnMap);
donut('chartDdSexo',   byDdSexo,   sexosMap);
donut('chartDdEmpresa',byDdEmpresa, empDdMap);

// Horizontal bars
hBar('chartCanal',      byCanal,       canalesMap,    'rgba(6,182,212,.7)');
hBar('chartCentro',     byCentro,      {},            'rgba(249,115,22,.7)');
hBar('chartDnCargo',    byDnCargo,     cargosMap,     'rgba(99,102,241,.7)');
hBar('chartDnJerarquia',byDnJerarquia, jerarquiasMap, 'rgba(168,85,247,.7)');
hBar('chartDdCargo',    byDdCargo,     cargosMap,     'rgba(239,68,68,.7)');

// Vertical bars
vBar('chartDnRango', byDnRango, rangosMap, ['rgba(14,165,233,.7)','rgba(99,102,241,.7)','rgba(168,85,247,.7)']);
vBar('chartDdRango', byDdRango, rangosMap, ['rgba(14,165,233,.7)','rgba(99,102,241,.7)','rgba(168,85,247,.7)']);
</script>
@endpush
