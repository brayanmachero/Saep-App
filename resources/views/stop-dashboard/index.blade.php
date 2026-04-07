@extends('layouts.app')
@section('title', 'Dashboard Tarjeta STOP')
@section('content')
<div class="page-container">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-hand-index-fill" style="color:var(--accent-color)"></i> Dashboard Tarjeta STOP</h2>
            <p class="page-subheading">
                Observaciones de seguridad &mdash; Google Sheets
                @if(isset($fileInfo))
                    <span style="font-size:.72rem;color:var(--text-muted);margin-left:.5rem">
                        <i class="bi bi-cloud-check"></i> {{ $fileInfo['name'] ?? 'N/A' }}
                        @if(isset($fileInfo['modifiedTime']))
                            | Actualizado {{ \Carbon\Carbon::parse($fileInfo['modifiedTime'])->diffForHumans() }}
                        @endif
                    </span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center">
            <form method="POST" action="{{ route('stop-dashboard.sync') }}" id="sync-form">
                @csrf
                <button type="submit" class="btn-premium" id="sync-btn" style="padding:.5rem 1rem;font-size:.82rem">
                    <i class="bi bi-arrow-clockwise" id="sync-icon"></i> Actualizar datos
                </button>
            </form>
        </div>
    </div>

    {{-- Mensajes --}}
    @if(session('success'))
    <div class="glass-card" style="padding:.75rem 1.25rem;margin-bottom:1rem;border-left:4px solid #22c55e;font-size:.85rem;color:#15803d;background:rgba(34,197,94,.06)">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Filtros --}}
    @if(isset($filterOptions) && !empty($filterOptions))
    <div class="glass-card" style="padding:1rem 1.25rem;margin-bottom:1rem">
        <form method="GET" action="{{ route('stop-dashboard') }}" id="filter-form">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem">
                <i class="bi bi-funnel-fill" style="color:var(--accent-color)"></i>
                <h3 style="font-size:.85rem;font-weight:600;margin:0;color:var(--text-primary)">Filtros</h3>
                @php $activeCount = count($filters ?? []); @endphp
                @if($activeCount > 0)
                    <span style="background:var(--accent-color);color:#fff;font-size:.68rem;padding:.1rem .45rem;border-radius:10px;font-weight:700">{{ $activeCount }} activo{{ $activeCount > 1 ? 's' : '' }}</span>
                @endif
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-bottom:.75rem">
                {{-- Empresa Observador --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">Empresa Observador</label>
                    <select name="empresa_observador" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach($filterOptions['empresas_observador'] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($filters['empresa_observador'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Empresa Observado --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">Empresa Observado</label>
                    <select name="empresa_observado" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach($filterOptions['empresas_observado'] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($filters['empresa_observado'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Tipo Observacion --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">Tipo Observacion</label>
                    <select name="tipo_observacion" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($filterOptions['tipos_observacion'] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($filters['tipo_observacion'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Centro --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">Centro de Trabajo</label>
                    <select name="centro" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($filterOptions['centros'] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($filters['centro'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Anio --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">A&ntilde;o</label>
                    <select name="anio" class="filter-select" onchange="this.form.submit()">
                        <option value="">Todos (acumulado)</option>
                        @foreach($filterOptions['anios'] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($filters['anio'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Fecha Desde --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="filter-select" value="{{ $filters['fecha_desde'] ?? '' }}" onchange="this.form.submit()">
                </div>
                {{-- Fecha Hasta --}}
                <div>
                    <label style="font-size:.72rem;font-weight:600;color:var(--text-muted);display:block;margin-bottom:.2rem">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="filter-select" value="{{ $filters['fecha_hasta'] ?? '' }}" onchange="this.form.submit()">
                </div>
            </div>
            <div style="display:flex;gap:.5rem">
                @if($activeCount > 0)
                    <a href="{{ route('stop-dashboard') }}" style="font-size:.78rem;color:#ef4444;text-decoration:none;display:inline-flex;align-items:center;gap:.25rem">
                        <i class="bi bi-x-circle"></i> Limpiar filtros
                    </a>
                @endif
            </div>
        </form>
    </div>
    @endif

    @if(isset($error))
    <div class="glass-card" style="padding:2rem;text-align:center;margin-bottom:1rem;border-left:4px solid #ef4444">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:2rem;color:#ef4444;display:block;margin-bottom:.75rem"></i>
        <p style="color:#ef4444;font-weight:600;margin-bottom:.5rem">{{ str_contains($error, 'filtros') ? 'Sin resultados' : 'Error de conexion' }}</p>
        <p style="color:var(--text-muted);font-size:.85rem">{{ $error }}</p>
    </div>
    @endif

    @if(isset($analytics))
    @php
        $totalRows = $analytics['totalRows'] ?? 0;
        $clasificacion = $analytics['clasificacion'] ?? [];
        $centros = $analytics['centros'] ?? [];
        $areas = $analytics['areas'] ?? [];
        $tiposObservacion = $analytics['tiposObservacion'] ?? [];
        $internoExterno = $analytics['internoExterno'] ?? [];
        $empresas = $analytics['empresas'] ?? [];
        $empresasObs = $analytics['empresasObservador'] ?? [];
        $turnos = $analytics['turnos'] ?? [];
        $antiguedades = $analytics['antiguedades'] ?? [];
        $cargos = $analytics['cargos'] ?? [];
        $topObservadores = $analytics['topObservadores'] ?? [];
        $negPorTipo = $analytics['negPorTipo'] ?? [];
        $posPorTipo = $analytics['posPorTipo'] ?? [];
        $topNeg = $analytics['topNegTrabajadores'] ?? [];
        $topPos = $analytics['topPosTrabajadores'] ?? [];
        $byMonth = $analytics['byMonth'] ?? [];
        $byYear = $analytics['byYear'] ?? [];

        $positivas = $clasificacion['Positiva'] ?? $clasificacion['positiva'] ?? 0;
        $negativas = $clasificacion['Negativa'] ?? $clasificacion['negativa'] ?? 0;
        $pctPositiva = $totalRows > 0 ? round(($positivas / $totalRows) * 100, 1) : 0;
        $pctNegativa = $totalRows > 0 ? round(($negativas / $totalRows) * 100, 1) : 0;

        $checklistCategories = ($checklist['categories'] ?? []);
    @endphp

    {{-- KPIs principales --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:1rem;margin-bottom:1.5rem">
        <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #3b82f6">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Total Observaciones</p>
                    <h2 style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1">{{ number_format($totalRows) }}</h2>
                </div>
                <i class="bi bi-files" style="font-size:1.5rem;color:#93c5fd"></i>
            </div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #22c55e">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Positivas</p>
                    <h2 style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1;color:#22c55e">{{ number_format($positivas) }}</h2>
                </div>
                <i class="bi bi-hand-thumbs-up-fill" style="font-size:1.5rem;color:#86efac"></i>
            </div>
            <p style="font-size:.7rem;color:var(--text-muted);margin:.35rem 0 0">{{ $pctPositiva }}% del total</p>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #ef4444">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Negativas</p>
                    <h2 style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1;color:#ef4444">{{ number_format($negativas) }}</h2>
                </div>
                <i class="bi bi-hand-thumbs-down-fill" style="font-size:1.5rem;color:#fca5a5"></i>
            </div>
            <p style="font-size:.7rem;color:var(--text-muted);margin:.35rem 0 0">{{ $pctNegativa }}% del total</p>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #8b5cf6">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Centros</p>
                    <h2 style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1">{{ count($centros) }}</h2>
                </div>
                <i class="bi bi-building" style="font-size:1.5rem;color:#c4b5fd"></i>
            </div>
        </div>
        <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #f97316">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Observadores</p>
                    <h2 style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1">{{ count($topObservadores) }}</h2>
                </div>
                <i class="bi bi-people-fill" style="font-size:1.5rem;color:#fdba74"></i>
            </div>
        </div>
    </div>

    {{-- Fila 1: Tendencia mensual + Clasificacion --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
                <i class="bi bi-graph-up" style="color:#3b82f6"></i> Tendencia Mensual
            </h3>
            <div style="position:relative;height:250px"><canvas id="timelineChart"></canvas></div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
                <i class="bi bi-pie-chart-fill" style="color:#8b5cf6"></i> Clasificacion
            </h3>
            <div style="position:relative;height:200px;max-width:220px;margin:0 auto"><canvas id="clasificacionChart"></canvas></div>
            <div style="display:flex;justify-content:center;gap:1.5rem;margin-top:.75rem;font-size:.8rem">
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;margin-right:.3rem"></span>Positiva: {{ number_format($positivas) }}</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;margin-right:.3rem"></span>Negativa: {{ number_format($negativas) }}</span>
            </div>
        </div>
    </div>

    {{-- Fila 2: Trabajadores con mas tarjetas negativas + positivas --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-person-x-fill" style="color:#ef4444"></i> Trabajadores con mas Tarjetas Negativas
            </h3>
            <div style="max-height:320px;overflow-y:auto">
                <table class="glass-table" style="font-size:.78rem">
                    <thead><tr><th style="width:30px">#</th><th>Trabajador</th><th style="text-align:center">Neg.</th><th style="width:90px"></th></tr></thead>
                    <tbody>
                        @php $rank = 1; $maxNeg = !empty($topNeg) ? max($topNeg) : 1; @endphp
                        @foreach($topNeg as $nombre => $count)
                        <tr>
                            <td style="text-align:center;font-weight:700;color:{{ $rank <= 3 ? '#ef4444' : 'var(--text-muted)' }}">{{ $rank }}</td>
                            <td title="{{ $nombre }}" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-transform:capitalize">{{ mb_strtolower($nombre) }}</td>
                            <td style="text-align:center;font-weight:600;color:#ef4444">{{ number_format($count) }}</td>
                            <td>
                                <div style="background:rgba(239,68,68,.1);border-radius:4px;overflow:hidden;height:14px">
                                    <div style="background:#ef4444;height:100%;width:{{ round(($count / $maxNeg) * 100) }}%;border-radius:4px"></div>
                                </div>
                            </td>
                        </tr>
                        @php $rank++; @endphp
                        @endforeach
                        @if(empty($topNeg))
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Sin datos</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-person-check-fill" style="color:#22c55e"></i> Trabajadores con mas Tarjetas Positivas
            </h3>
            <div style="max-height:320px;overflow-y:auto">
                <table class="glass-table" style="font-size:.78rem">
                    <thead><tr><th style="width:30px">#</th><th>Trabajador</th><th style="text-align:center">Pos.</th><th style="width:90px"></th></tr></thead>
                    <tbody>
                        @php $rank = 1; $maxPos = !empty($topPos) ? max($topPos) : 1; @endphp
                        @foreach($topPos as $nombre => $count)
                        <tr>
                            <td style="text-align:center;font-weight:700;color:{{ $rank <= 3 ? '#22c55e' : 'var(--text-muted)' }}">{{ $rank }}</td>
                            <td title="{{ $nombre }}" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-transform:capitalize">{{ mb_strtolower($nombre) }}</td>
                            <td style="text-align:center;font-weight:600;color:#22c55e">{{ number_format($count) }}</td>
                            <td>
                                <div style="background:rgba(34,197,94,.1);border-radius:4px;overflow:hidden;height:14px">
                                    <div style="background:#22c55e;height:100%;width:{{ round(($count / $maxPos) * 100) }}%;border-radius:4px"></div>
                                </div>
                            </td>
                        </tr>
                        @php $rank++; @endphp
                        @endforeach
                        @if(empty($topPos))
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Sin datos</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Fila 3: Tipo de faltas negativas + felicitaciones positivas --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444"></i> Tipos de Falta � Tarjetas Negativas
            </h3>
            <div style="position:relative;height:250px"><canvas id="negPorTipoChart"></canvas></div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-star-fill" style="color:#22c55e"></i> Tipos de Felicitacion � Tarjetas Positivas
            </h3>
            <div style="position:relative;height:250px"><canvas id="posPorTipoChart"></canvas></div>
        </div>
    </div>

    {{-- Fila 4: Centros + Areas --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-building" style="color:#3b82f6"></i> Por Centro de Trabajo
            </h3>
            <div style="max-height:350px;overflow-y:auto">
                <table class="glass-table" style="font-size:.8rem">
                    <thead><tr><th>Centro</th><th style="text-align:center">Cant.</th><th style="text-align:center">%</th><th style="width:100px"></th></tr></thead>
                    <tbody>
                        @php $maxC = !empty($centros) ? max($centros) : 1; @endphp
                        @foreach($centros as $c => $count)
                        <tr>
                            <td title="{{ $c }}" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c }}</td>
                            <td style="text-align:center;font-weight:600">{{ number_format($count) }}</td>
                            <td style="text-align:center;color:var(--text-muted)">{{ round(($count / $totalRows) * 100, 1) }}%</td>
                            <td><div style="background:rgba(59,130,246,.1);border-radius:4px;overflow:hidden;height:16px"><div style="background:linear-gradient(90deg,#3b82f6,#8b5cf6);height:100%;width:{{ round(($count / $maxC) * 100) }}%;border-radius:4px"></div></div></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-geo-alt-fill" style="color:#06b6d4"></i> Por Area / Zona
            </h3>
            <div style="max-height:350px;overflow-y:auto">
                <table class="glass-table" style="font-size:.8rem">
                    <thead><tr><th>Area</th><th style="text-align:center">Cant.</th><th style="width:90px"></th></tr></thead>
                    <tbody>
                        @php $maxA = !empty($areas) ? max($areas) : 1; @endphp
                        @foreach(array_slice($areas, 0, 20, true) as $a => $count)
                        <tr>
                            <td title="{{ $a }}" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $a }}</td>
                            <td style="text-align:center;font-weight:600">{{ number_format($count) }}</td>
                            <td><div style="background:rgba(6,182,212,.1);border-radius:4px;overflow:hidden;height:14px"><div style="background:#06b6d4;height:100%;width:{{ round(($count / $maxA) * 100) }}%;border-radius:4px"></div></div></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Fila 5: Top Observadores + Empresa del observador --}}
    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-trophy-fill" style="color:#f59e0b"></i> Top 20 Observadores (quien paso la tarjeta)
            </h3>
            <div style="max-height:380px;overflow-y:auto">
                <table class="glass-table" style="font-size:.78rem">
                    <thead><tr><th style="width:30px">#</th><th>Observador</th><th style="text-align:center">Obs.</th><th style="width:100px"></th></tr></thead>
                    <tbody>
                        @php $rank = 1; $maxObs = !empty($topObservadores) ? max($topObservadores) : 1; @endphp
                        @foreach($topObservadores as $nombre => $count)
                        <tr>
                            <td style="text-align:center;font-weight:700;color:{{ $rank <= 3 ? '#f59e0b' : 'var(--text-muted)' }}">@if($rank <= 3)<i class="bi bi-trophy-fill"></i> @endif{{ $rank }}</td>
                            <td title="{{ $nombre }}" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-transform:capitalize">{{ mb_strtolower($nombre) }}</td>
                            <td style="text-align:center;font-weight:600">{{ number_format($count) }}</td>
                            <td><div style="background:rgba(245,158,11,.1);border-radius:4px;overflow:hidden;height:14px"><div style="background:linear-gradient(90deg,#f59e0b,#f97316);height:100%;width:{{ round(($count / $maxObs) * 100) }}%;border-radius:4px"></div></div></td>
                        </tr>
                        @php $rank++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-briefcase-fill" style="color:#6366f1"></i> Empresa del Observador
            </h3>
            <div style="max-height:380px;overflow-y:auto">
                <table class="glass-table" style="font-size:.78rem">
                    <thead><tr><th>Empresa</th><th style="text-align:center">Cant.</th><th style="width:90px"></th></tr></thead>
                    <tbody>
                        @php $maxEO = !empty($empresasObs) ? max($empresasObs) : 1; @endphp
                        @foreach($empresasObs as $emp => $count)
                        <tr>
                            <td title="{{ $emp }}" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $emp }}</td>
                            <td style="text-align:center;font-weight:600">{{ number_format($count) }}</td>
                            <td><div style="background:rgba(99,102,241,.1);border-radius:4px;overflow:hidden;height:14px"><div style="background:#6366f1;height:100%;width:{{ round(($count / $maxEO) * 100) }}%;border-radius:4px"></div></div></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Fila 6: Antiguedad + Cargo + Interno/Externo --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
                <i class="bi bi-hourglass-split" style="color:#8b5cf6"></i> Antiguedad Observados
            </h3>
            <div style="position:relative;height:260px"><canvas id="antiguedadChart"></canvas></div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
                <i class="bi bi-clock-fill" style="color:#f97316"></i> Turno
            </h3>
            <div style="position:relative;height:220px"><canvas id="turnoChart"></canvas></div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
                <i class="bi bi-person-badge-fill" style="color:#ec4899"></i> Interno / Externo
            </h3>
            <div style="position:relative;height:180px;max-width:200px;margin:0 auto"><canvas id="internoExternoChart"></canvas></div>
            <div style="display:flex;justify-content:center;gap:1rem;margin-top:.75rem;font-size:.78rem">
                @foreach($internoExterno as $tipo => $count)
                <span style="color:var(--text-muted)">{{ $tipo }}: <strong>{{ number_format($count) }}</strong></span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Fila 7: Empresas Observados + Cargos + Timeline anual --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-briefcase-fill" style="color:#14b8a6"></i> Empresas Observados
            </h3>
            <div style="max-height:300px;overflow-y:auto">
                <table class="glass-table" style="font-size:.78rem">
                    <thead><tr><th>Empresa</th><th style="text-align:center">Cant.</th><th style="width:80px"></th></tr></thead>
                    <tbody>
                        @php $maxEmp = !empty($empresas) ? max($empresas) : 1; @endphp
                        @foreach($empresas as $emp => $count)
                        <tr>
                            <td title="{{ $emp }}" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $emp }}</td>
                            <td style="text-align:center;font-weight:600">{{ number_format($count) }}</td>
                            <td><div style="background:rgba(20,184,166,.1);border-radius:4px;overflow:hidden;height:14px"><div style="background:#14b8a6;height:100%;width:{{ round(($count / $maxEmp) * 100) }}%;border-radius:4px"></div></div></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem;color:var(--text-primary)">
                <i class="bi bi-person-vcard-fill" style="color:#a855f7"></i> Cargos Observados
            </h3>
            <div style="max-height:300px;overflow-y:auto">
                <table class="glass-table" style="font-size:.78rem">
                    <thead><tr><th>Cargo</th><th style="text-align:center">Cant.</th><th style="width:80px"></th></tr></thead>
                    <tbody>
                        @php $maxCargo = !empty($cargos) ? max($cargos) : 1; @endphp
                        @foreach($cargos as $cargo => $count)
                        <tr>
                            <td title="{{ $cargo }}" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $cargo }}</td>
                            <td style="text-align:center;font-weight:600">{{ number_format($count) }}</td>
                            <td><div style="background:rgba(168,85,247,.1);border-radius:4px;overflow:hidden;height:14px"><div style="background:#a855f7;height:100%;width:{{ round(($count / $maxCargo) * 100) }}%;border-radius:4px"></div></div></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="glass-card" style="padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
                <i class="bi bi-calendar3" style="color:#14b8a6"></i> Observaciones por Ano
            </h3>
            <div style="position:relative;height:220px"><canvas id="yearChart"></canvas></div>
        </div>
    </div>

    {{-- Fila 8: Checklist de cumplimiento --}}
    @if(!empty($checklistCategories))
    <div class="glass-card" style="padding:1.25rem;margin-bottom:1rem">
        <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1rem;color:var(--text-primary)">
            <i class="bi bi-clipboard-check-fill" style="color:#22c55e"></i> Cumplimiento de Checklist
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
            @foreach($checklistCategories as $catName => $cat)
            <div style="background:rgba(128,128,128,.04);border-radius:10px;padding:1rem;border:1px solid rgba(128,128,128,.08)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                    <h4 style="font-size:.82rem;font-weight:600;margin:0;color:var(--text-primary)">{{ $catName }}</h4>
                    <span style="font-size:1.1rem;font-weight:800;color:{{ $cat['pct_cumple'] >= 80 ? '#22c55e' : ($cat['pct_cumple'] >= 60 ? '#f59e0b' : '#ef4444') }}">{{ $cat['pct_cumple'] }}%</span>
                </div>
                <div style="background:rgba(239,68,68,.15);border-radius:6px;overflow:hidden;height:22px;margin-bottom:.5rem">
                    <div style="background:{{ $cat['pct_cumple'] >= 80 ? '#22c55e' : ($cat['pct_cumple'] >= 60 ? '#f59e0b' : '#ef4444') }};height:100%;width:{{ $cat['pct_cumple'] }}%;border-radius:6px;display:flex;align-items:center;justify-content:center">
                        @if($cat['pct_cumple'] > 15)
                        <span style="font-size:.68rem;font-weight:700;color:#fff">{{ number_format($cat['cumple']) }} cumplen</span>
                        @endif
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted)">
                    <span><i class="bi bi-check-circle-fill" style="color:#22c55e"></i> {{ number_format($cat['cumple']) }}</span>
                    <span><i class="bi bi-x-circle-fill" style="color:#ef4444"></i> {{ number_format($cat['no_cumple']) }}</span>
                    <span>Total: {{ number_format($cat['total']) }}</span>
                </div>
                @if(!empty($cat['questions']))
                <details style="margin-top:.5rem">
                    <summary style="font-size:.72rem;color:var(--text-muted);cursor:pointer;user-select:none">Ver detalle por pregunta</summary>
                    <div style="margin-top:.4rem;max-height:200px;overflow-y:auto">
                        @foreach(array_slice($cat['questions'], 0, 8, true) as $q => $qStats)
                        @php $qTotal = $qStats['cumple'] + $qStats['no_cumple']; $qPct = $qTotal > 0 ? round(($qStats['cumple'] / $qTotal) * 100, 1) : 0; @endphp
                        <div style="font-size:.72rem;padding:.3rem 0;border-bottom:1px solid rgba(128,128,128,.06)">
                            <div style="display:flex;justify-content:space-between;margin-bottom:.15rem">
                                <span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $q }}">{{ \Illuminate\Support\Str::limit($q, 35) }}</span>
                                <span style="font-weight:600;color:{{ $qPct >= 80 ? '#22c55e' : ($qPct >= 60 ? '#f59e0b' : '#ef4444') }}">{{ $qPct }}%</span>
                            </div>
                            <div style="background:rgba(128,128,128,.08);border-radius:3px;overflow:hidden;height:6px">
                                <div style="background:{{ $qPct >= 80 ? '#22c55e' : ($qPct >= 60 ? '#f59e0b' : '#ef4444') }};height:100%;width:{{ $qPct }}%;border-radius:3px"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </details>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @endif {{-- fin isset analytics --}}
</div>

@push('styles')
<style>
.filter-select {
    width: 100%;
    padding: .4rem .6rem;
    font-size: .78rem;
    border: 1px solid rgba(128,128,128,.15);
    border-radius: 8px;
    background: var(--bg-card, #fff);
    color: var(--text-primary);
    outline: none;
    transition: border-color .2s;
}
.filter-select:focus { border-color: var(--accent-color); }
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
#sync-icon { display:inline-block; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const colors = ['#3b82f6','#8b5cf6','#f59e0b','#22c55e','#ef4444','#06b6d4','#ec4899','#f97316','#14b8a6','#6366f1','#a855f7','#84cc16'];
    const gridColor = 'rgba(128,128,128,0.08)';

    const syncForm = document.getElementById('sync-form');
    if (syncForm) {
        syncForm.addEventListener('submit', function() {
            document.getElementById('sync-btn').disabled = true;
            document.getElementById('sync-btn').style.opacity = '0.7';
            document.getElementById('sync-icon').style.animation = 'spin 1s linear infinite';
        });
    }

    @if(isset($analytics))

    // 1. Timeline Mensual
    @if(!empty($byMonth))
    new Chart(document.getElementById('timelineChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($byMonth)) !!},
            datasets: [{
                label: 'Observaciones',
                data: {!! json_encode(array_values($byMonth)) !!},
                backgroundColor: 'rgba(59,130,246,0.6)',
                borderColor: '#3b82f6',
                borderWidth: 1, borderRadius: 4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 10 } } }
            }
        }
    });
    @endif

    // 2. Clasificacion
    @if(!empty($clasificacion))
    new Chart(document.getElementById('clasificacionChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_keys($clasificacion)) !!},
            datasets: [{ data: {!! json_encode(array_values($clasificacion)) !!}, backgroundColor: ['#22c55e','#ef4444','#f59e0b','#8b5cf6'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { display: false } } }
    });
    @endif

    // 3. Faltas Negativas por Tipo
    @if(!empty($negPorTipo))
    new Chart(document.getElementById('negPorTipoChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($negPorTipo)) !!},
            datasets: [{ label: 'Negativas', data: {!! json_encode(array_values($negPorTipo)) !!}, backgroundColor: 'rgba(239,68,68,0.6)', borderColor: '#ef4444', borderWidth: 1, borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } }
        }
    });
    @endif

    // 4. Felicitaciones Positivas por Tipo
    @if(!empty($posPorTipo))
    new Chart(document.getElementById('posPorTipoChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($posPorTipo)) !!},
            datasets: [{ label: 'Positivas', data: {!! json_encode(array_values($posPorTipo)) !!}, backgroundColor: 'rgba(34,197,94,0.6)', borderColor: '#22c55e', borderWidth: 1, borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } }
        }
    });
    @endif

    // 5. Turnos
    @if(!empty($turnos))
    new Chart(document.getElementById('turnoChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_keys($turnos)) !!},
            datasets: [{ data: {!! json_encode(array_values($turnos)) !!}, backgroundColor: ['#f97316','#3b82f6','#8b5cf6','#22c55e','#ef4444'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '50%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 10 } } } } }
    });
    @endif

    // 6. Interno/Externo
    @if(!empty($internoExterno))
    new Chart(document.getElementById('internoExternoChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_keys($internoExterno)) !!},
            datasets: [{ data: {!! json_encode(array_values($internoExterno)) !!}, backgroundColor: ['#ec4899','#06b6d4','#f59e0b'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { display: false } } }
    });
    @endif

    // 7. Antiguedad
    @if(!empty($antiguedades))
    new Chart(document.getElementById('antiguedadChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($antiguedades)) !!},
            datasets: [{ label: 'Observados', data: {!! json_encode(array_values($antiguedades)) !!}, backgroundColor: 'rgba(139,92,246,0.6)', borderColor: '#8b5cf6', borderWidth: 1, borderRadius: 4 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } }, x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } } }
        }
    });
    @endif

    // 8. Por Ano
    @if(!empty($byYear))
    new Chart(document.getElementById('yearChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($byYear)) !!},
            datasets: [{ label: 'Observaciones', data: {!! json_encode(array_values($byYear)) !!}, backgroundColor: ['#14b8a6','#3b82f6','#8b5cf6','#f59e0b','#ef4444'], borderWidth: 0, borderRadius: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } }, x: { grid: { display: false } } }
        }
    });
    @endif

    @endif
});
</script>
@endpush
@endsection
