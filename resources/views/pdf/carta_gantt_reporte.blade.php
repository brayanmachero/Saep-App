<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
/* ── Reset & Base ── */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; background: #fff; }

/* ── Header ── */
.top-bar { height: 6px; background: linear-gradient(90deg, #0f1b4c, #2563eb); }
.header { padding: 14px 24px 10px; display: table; width: 100%; border-bottom: 2px solid #e2e8f0; }
.hdr-logo { display: table-cell; vertical-align: middle; width: 110px; }
.hdr-logo img { max-height: 38px; max-width: 110px; }
.hdr-center { display: table-cell; vertical-align: middle; text-align: center; }
.hdr-center h1 { font-size: 13px; font-weight: 900; color: #0f1b4c; text-transform: uppercase; letter-spacing: 0.5px; }
.hdr-center p { font-size: 8px; color: #64748b; margin-top: 2px; }
.hdr-right { display: table-cell; vertical-align: middle; text-align: right; width: 140px; }
.hdr-right .code { font-size: 10px; font-weight: 800; color: #0f1b4c; }
.hdr-right .date { font-size: 7.5px; color: #94a3b8; margin-top: 2px; }

/* ── Info grid ── */
.info-strip { display: table; width: 100%; margin: 10px 0 0; }
.info-item { display: table-cell; padding: 7px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3px solid #0f1b4c; }
.info-item .label { font-size: 6.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
.info-item .value { font-size: 9px; font-weight: 700; color: #0f172a; margin-top: 1px; }

/* ── KPI cards (inline) ── */
.kpi-row { display: table; width: 100%; margin: 12px 0; }
.kpi-card { display: table-cell; text-align: center; padding: 10px 6px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; vertical-align: middle; }
.kpi-card .kpi-num { font-size: 22px; font-weight: 900; line-height: 1; }
.kpi-card .kpi-label { font-size: 6.5px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; color: #64748b; margin-top: 3px; }
.kpi-blue .kpi-num   { color: #0f1b4c; }
.kpi-green .kpi-num  { color: #059669; }
.kpi-yellow .kpi-num { color: #d97706; }
.kpi-red .kpi-num    { color: #dc2626; }
.kpi-gray .kpi-num   { color: #6b7280; }
.kpi-purple .kpi-num { color: #7c3aed; }
.kpi-spacer { display: table-cell; width: 6px; }

/* ── Section titles ── */
.section { margin: 14px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #e2e8f0; }
.section-inner { display: table; width: 100%; }
.section-bar { display: table-cell; width: 4px; background: #0f1b4c; border-radius: 2px; }
.section-text { display: table-cell; vertical-align: middle; padding-left: 8px; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #1e293b; letter-spacing: 0.5px; }

/* ── Progress ring (CSS circle) ── */
.ring-container { display: table; width: 100%; margin: 8px 0; }
.ring-left { display: table-cell; width: 160px; vertical-align: middle; text-align: center; }
.ring-right { display: table-cell; vertical-align: middle; padding-left: 16px; }

.ring-circle { width: 110px; height: 110px; border-radius: 55px; border: 14px solid #e2e8f0; position: relative; margin: 0 auto; }
.ring-inner { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
.ring-pct { font-size: 26px; font-weight: 900; color: #0f1b4c; }
.ring-sub { font-size: 7px; color: #94a3b8; text-transform: uppercase; font-weight: 700; }

/* ── Bar chart (CSS horizontal bars) ── */
.bar-chart { width: 100%; }
.bar-row { display: table; width: 100%; margin-bottom: 4px; }
.bar-label { display: table-cell; width: 36px; font-size: 8px; font-weight: 700; color: #475569; vertical-align: middle; text-align: center; }
.bar-track { display: table-cell; vertical-align: middle; padding: 0 4px; }
.bar-bg { height: 14px; background: #f1f5f9; border-radius: 3px; position: relative; overflow: hidden; }
.bar-fill-prog { height: 14px; background: #cbd5e1; border-radius: 3px 0 0 3px; position: absolute; top: 0; left: 0; }
.bar-fill-real { height: 14px; background: #0f1b4c; border-radius: 3px 0 0 3px; position: absolute; top: 0; left: 0; }
.bar-val { display: table-cell; width: 34px; font-size: 7.5px; font-weight: 700; color: #0f1b4c; vertical-align: middle; text-align: right; }

/* ── Priority mini bars ── */
.pri-row { display: table; width: 100%; margin-bottom: 5px; }
.pri-label { display: table-cell; width: 50px; font-size: 8px; font-weight: 700; vertical-align: middle; }
.pri-bar-wrap { display: table-cell; vertical-align: middle; padding: 0 6px; }
.pri-bar { height: 12px; border-radius: 3px; }
.pri-count { display: table-cell; width: 26px; font-size: 8px; font-weight: 700; color: #475569; vertical-align: middle; text-align: right; }
.pri-alta  { background: #dc2626; }
.pri-media { background: #f59e0b; }
.pri-baja  { background: #10b981; }

/* ── Status distribution ── */
.status-row { display: table; width: 100%; margin: 6px 0; }
.status-pill { display: table-cell; text-align: center; padding: 6px 2px; }
.status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 5px; margin-right: 3px; vertical-align: middle; }
.status-label { font-size: 7.5px; font-weight: 700; color: #475569; }
.status-num { font-size: 13px; font-weight: 900; display: block; margin-top: 1px; }
.dot-completada  { background: #059669; }
.dot-progreso    { background: #2563eb; }
.dot-pendiente   { background: #94a3b8; }
.dot-cancelada   { background: #dc2626; }

/* ── Generic table ── */
.data-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
.data-table th { background: #0f1b4c; color: #fff; font-size: 7px; font-weight: 700; padding: 5px 6px; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; }
.data-table td { padding: 4px 6px; font-size: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.data-table tr:nth-child(even) td { background: #f8fafc; }
.data-table .cat-row td { background: #eef2ff; font-weight: 800; font-size: 8.5px; color: #0f1b4c; border-bottom: 2px solid #c7d2fe; }

/* Progress bar inside table */
.mini-bar { height: 8px; background: #e2e8f0; border-radius: 3px; overflow: hidden; width: 60px; display: inline-block; vertical-align: middle; }
.mini-fill { height: 8px; border-radius: 3px; }
.fill-green  { background: #059669; }
.fill-blue   { background: #2563eb; }
.fill-orange { background: #f59e0b; }
.fill-red    { background: #dc2626; }
.fill-gray   { background: #94a3b8; }

/* Chips/badges */
.chip { padding: 2px 6px; border-radius: 3px; font-size: 6.5px; font-weight: 700; text-transform: uppercase; }
.chip-green  { background: #dcfce7; color: #15803d; }
.chip-blue   { background: #dbeafe; color: #1d4ed8; }
.chip-orange { background: #fef3c7; color: #92400e; }
.chip-red    { background: #fee2e2; color: #991b1b; }
.chip-gray   { background: #f1f5f9; color: #475569; }
.chip-purple { background: #ede9fe; color: #6d28d9; }

/* Gantt mini-grid */
.gantt-mini { width: 100%; border-collapse: collapse; margin-top: 4px; }
.gantt-mini th { background: #f1f5f9; color: #475569; font-size: 6.5px; font-weight: 700; padding: 3px 2px; text-align: center; border: 1px solid #e2e8f0; width: 7%; }
.gantt-mini th:first-child { width: 16%; text-align: left; padding-left: 6px; }
.gantt-mini td { padding: 3px 2px; font-size: 7px; text-align: center; border: 1px solid #e2e8f0; height: 18px; vertical-align: middle; }
.gantt-mini td:first-child { text-align: left; padding-left: 6px; font-weight: 600; font-size: 7.5px; }
.g-prog { background: #dbeafe; }
.g-done { background: #059669; color: #fff; font-weight: 700; font-size: 6.5px; }
.g-partial { background: #fef3c7; color: #92400e; font-weight: 700; font-size: 6.5px; }
.g-miss { background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 6.5px; }
.g-future { background: #f0fdf4; }
.g-reprog { background: #ede9fe; color: #6d28d9; font-weight: 700; font-size: 6.5px; }

/* Footer */
.footer { margin-top: 12px; background: #0f172a; color: #94a3b8; padding: 10px 18px; font-size: 7px; }
.footer-row { display: table; width: 100%; }
.footer-cell { display: table-cell; vertical-align: middle; }
.footer-right { text-align: right; }

.page-break { page-break-after: always; }
.avoid-break { page-break-inside: avoid; }
</style>
</head>
<body>

@php
    $mesesCortos = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $maxProg = max(1, collect($mesesData)->max('prog'));
@endphp

{{-- =========================== PAGE 1: EXECUTIVE SUMMARY =========================== --}}
<div class="top-bar"></div>

<div class="header">
    <div class="hdr-logo">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
    </div>
    <div class="hdr-center">
        <h1>Reporte Gerencial SST</h1>
        <p>Programa de Seguridad y Salud en el Trabajo &bull; Informe de Avance</p>
    </div>
    <div class="hdr-right">
        <div class="code">{{ $cartaGantt->codigo }}</div>
        <div class="date">Generado: {{ date('d/m/Y H:i') }}</div>
    </div>
</div>

<div class="info-strip">
    <div class="info-item">
        <div class="label">Programa</div>
        <div class="value">{{ $cartaGantt->titulo }}</div>
    </div>
    <div class="info-item">
        <div class="label">Año</div>
        <div class="value">{{ $cartaGantt->anio }}</div>
    </div>
    <div class="info-item">
        <div class="label">Centro de Costo</div>
        <div class="value">{{ $cartaGantt->centroCosto->nombre ?? '—' }}</div>
    </div>
    <div class="info-item">
        <div class="label">Responsable</div>
        <div class="value">{{ $cartaGantt->responsable->nombre_completo ?? '—' }}</div>
    </div>
    <div class="info-item">
        <div class="label">Estado</div>
        <div class="value">{{ $cartaGantt->estado }}</div>
    </div>
</div>

{{-- KPIs --}}
<div class="kpi-row">
    <div class="kpi-card kpi-blue">
        <div class="kpi-num">{{ $pct }}%</div>
        <div class="kpi-label">Avance Global</div>
    </div>
    <div class="kpi-spacer"></div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-num">{{ $totalAct }}</div>
        <div class="kpi-label">Actividades</div>
    </div>
    <div class="kpi-spacer"></div>
    <div class="kpi-card kpi-green">
        <div class="kpi-num">{{ $completadas }}</div>
        <div class="kpi-label">Completadas</div>
    </div>
    <div class="kpi-spacer"></div>
    <div class="kpi-card kpi-yellow">
        <div class="kpi-num">{{ $enProgreso }}</div>
        <div class="kpi-label">En Progreso</div>
    </div>
    <div class="kpi-spacer"></div>
    <div class="kpi-card kpi-gray">
        <div class="kpi-num">{{ $pendientes }}</div>
        <div class="kpi-label">Pendientes</div>
    </div>
    <div class="kpi-spacer"></div>
    <div class="kpi-card kpi-red">
        <div class="kpi-num">{{ $vencidas->count() }}</div>
        <div class="kpi-label">Vencidas</div>
    </div>
    <div class="kpi-spacer"></div>
    <div class="kpi-card kpi-purple">
        <div class="kpi-num">{{ $reprogramaciones->count() }}</div>
        <div class="kpi-label">Reprogramaciones</div>
    </div>
</div>

{{-- Charts area --}}
<div class="ring-container">
    {{-- Left: Progress circle + Status distribution --}}
    <div class="ring-left">
        <div class="ring-circle" style="border-color: {{ $pct >= 75 ? '#059669' : ($pct >= 50 ? '#2563eb' : ($pct >= 25 ? '#f59e0b' : '#dc2626')) }};">
            <div class="ring-inner">
                <div class="ring-pct">{{ $pct }}%</div>
                <div class="ring-sub">Cumplimiento</div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <span class="status-dot dot-completada"></span><span class="status-label">Completadas {{ $completadas }}</span>&nbsp;&nbsp;
            <span class="status-dot dot-progreso"></span><span class="status-label">Progreso {{ $enProgreso }}</span><br>
            <span class="status-dot dot-pendiente"></span><span class="status-label">Pendientes {{ $pendientes }}</span>&nbsp;&nbsp;
            <span class="status-dot dot-cancelada"></span><span class="status-label">Canceladas {{ $canceladas }}</span>
        </div>
    </div>

    {{-- Right: Monthly progress bars --}}
    <div class="ring-right">
        <div style="font-size:8px;font-weight:800;color:#0f1b4c;text-transform:uppercase;margin-bottom:6px;letter-spacing:0.5px;">
            Cumplimiento Mensual (Programado vs Realizado)
        </div>
        <div class="bar-chart">
            @for($m = 1; $m <= 12; $m++)
                @php
                    $d = $mesesData[$m];
                    $wProg = $maxProg > 0 ? round(($d['prog'] / $maxProg) * 100) : 0;
                    $wReal = $maxProg > 0 ? round(($d['real'] / $maxProg) * 100) : 0;
                    $isFuture = $m > $mesActual;
                @endphp
                <div class="bar-row">
                    <div class="bar-label" style="{{ $m === $mesActual ? 'color:#0f1b4c;font-weight:900;' : '' }}">{{ $mesesCortos[$m] }}</div>
                    <div class="bar-track">
                        <div class="bar-bg" style="position:relative;">
                            @if($d['prog'] > 0)
                            <div class="bar-fill-prog" style="width:{{ $wProg }}%;"></div>
                            <div class="bar-fill-real" style="width:{{ $wReal }}%;opacity:{{ $isFuture ? '0.3' : '1' }};"></div>
                            @endif
                        </div>
                    </div>
                    <div class="bar-val">{{ $d['pct'] }}%</div>
                </div>
            @endfor
        </div>
        <div style="margin-top:4px;">
            <span style="display:inline-block;width:10px;height:6px;background:#cbd5e1;border-radius:2px;"></span>
            <span style="font-size:6.5px;color:#94a3b8;">Programado</span>&nbsp;&nbsp;
            <span style="display:inline-block;width:10px;height:6px;background:#0f1b4c;border-radius:2px;"></span>
            <span style="font-size:6.5px;color:#94a3b8;">Realizado</span>
        </div>
    </div>
</div>

{{-- Priority distribution --}}
<div style="margin-top: 8px;">
    <div style="font-size:8px;font-weight:800;color:#0f1b4c;text-transform:uppercase;margin-bottom:4px;">Distribución por Prioridad</div>
    @php $maxPri = max(1, max($prioridades['ALTA'], $prioridades['MEDIA'], $prioridades['BAJA'])); @endphp
    @foreach(['ALTA' => 'pri-alta', 'MEDIA' => 'pri-media', 'BAJA' => 'pri-baja'] as $pri => $cls)
    <div class="pri-row">
        <div class="pri-label" style="color:{{ $pri === 'ALTA' ? '#dc2626' : ($pri === 'MEDIA' ? '#d97706' : '#059669') }};">{{ $pri }}</div>
        <div class="pri-bar-wrap">
            <div class="pri-bar {{ $cls }}" style="width:{{ round(($prioridades[$pri] / $maxPri) * 100) }}%;"></div>
        </div>
        <div class="pri-count">{{ $prioridades[$pri] }}</div>
    </div>
    @endforeach
</div>

<div class="footer">
    <div class="footer-row">
        <div class="footer-cell">SAEP &bull; Sistema de Administración Empresarial de Prevención &bull; Reporte Gerencial SST</div>
        <div class="footer-cell footer-right">Página 1 &bull; {{ $cartaGantt->codigo }} &bull; {{ date('d/m/Y') }}</div>
    </div>
</div>

<div class="page-break"></div>

{{-- =========================== PAGE 2: GANTT DETAIL =========================== --}}
<div class="top-bar"></div>
<div class="header">
    <div class="hdr-logo">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
    </div>
    <div class="hdr-center">
        <h1>Detalle de Actividades por Categoría</h1>
        <p>{{ $cartaGantt->titulo }} &bull; {{ $cartaGantt->codigo }}</p>
    </div>
    <div class="hdr-right">
        <div class="date">Generado: {{ date('d/m/Y H:i') }}</div>
    </div>
</div>

@foreach($cartaGantt->categorias->sortBy('orden') as $categoria)
<div class="avoid-break" style="margin-top:10px;">
    <div class="section">
        <div class="section-inner">
            <div class="section-bar"></div>
            <div class="section-text">{{ $categoria->nombre }}</div>
        </div>
    </div>

    <table class="gantt-mini">
        <thead>
            <tr>
                <th>Actividad</th>
                @for($m = 1; $m <= 12; $m++)
                <th style="{{ $m === $mesActual ? 'background:#0f1b4c;color:#fff;' : '' }}">{{ $mesesCortos[$m] }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($categoria->actividades->sortBy('orden') as $act)
            @php
                $segPorMes = $act->seguimientoPorMes;
                $reprogMeses = $act->reprogramaciones->pluck('mes_nuevo')->unique()->toArray();
            @endphp
            <tr>
                <td>
                    {{ Str::limit($act->nombre, 30) }}
                    @if($act->estaVencida)
                        <span class="chip chip-red">V</span>
                    @elseif($act->estado === 'COMPLETADA')
                        <span class="chip chip-green">OK</span>
                    @endif
                </td>
                @for($m = 1; $m <= 12; $m++)
                @php
                    $s = $segPorMes[$m] ?? null;
                    $prog = $s['programado'] ?? false;
                    $real = $s['realizado'] ?? false;
                    $cantR = $s['cantidad_realizada'] ?? 0;
                    $cantP = $act->cantidad_programada;
                    $isReprog = in_array($m, $reprogMeses);
                @endphp
                <td class="@if($isReprog && $prog) g-reprog
                           @elseif($prog && $real) g-done
                           @elseif($prog && $cantR > 0 && !$real) g-partial
                           @elseif($prog && $m < $mesActual && !$real && $cantR === 0) g-miss
                           @elseif($prog && $m >= $mesActual) g-future
                           @elseif($prog) g-prog
                           @endif">
                    @if($prog && $real)
                        &#10003;
                    @elseif($prog && $cantR > 0)
                        {{ $cantR }}/{{ $cantP }}
                    @elseif($prog && $m < $mesActual)
                        &#10007;
                    @elseif($prog)
                        &bull;
                    @endif
                </td>
                @endfor
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

<div class="footer">
    <div class="footer-row">
        <div class="footer-cell">
            <span style="display:inline-block;width:10px;height:8px;background:#059669;border-radius:2px;margin-right:3px;"></span>Completado&nbsp;&nbsp;
            <span style="display:inline-block;width:10px;height:8px;background:#fef3c7;border-radius:2px;margin-right:3px;border:1px solid #e2e8f0;"></span>Parcial&nbsp;&nbsp;
            <span style="display:inline-block;width:10px;height:8px;background:#fee2e2;border-radius:2px;margin-right:3px;border:1px solid #e2e8f0;"></span>No cumplido&nbsp;&nbsp;
            <span style="display:inline-block;width:10px;height:8px;background:#f0fdf4;border-radius:2px;margin-right:3px;border:1px solid #e2e8f0;"></span>Futuro&nbsp;&nbsp;
            <span style="display:inline-block;width:10px;height:8px;background:#ede9fe;border-radius:2px;margin-right:3px;border:1px solid #e2e8f0;"></span>Reprogramado
        </div>
        <div class="footer-cell footer-right">Página 2 &bull; {{ $cartaGantt->codigo }} &bull; {{ date('d/m/Y') }}</div>
    </div>
</div>

<div class="page-break"></div>

{{-- =========================== PAGE 3: ALERTAS & REPROGRAMACIONES =========================== --}}
<div class="top-bar"></div>
<div class="header">
    <div class="hdr-logo">
        <img src="https://saep.cl/wp-content/uploads/2023/11/Logo_Saep.svg" alt="SAEP">
    </div>
    <div class="hdr-center">
        <h1>Alertas, Reprogramaciones y Detalle</h1>
        <p>{{ $cartaGantt->titulo }} &bull; {{ $cartaGantt->codigo }}</p>
    </div>
    <div class="hdr-right">
        <div class="date">Generado: {{ date('d/m/Y H:i') }}</div>
    </div>
</div>

{{-- Vencidas --}}
@if($vencidas->count())
<div class="section">
    <div class="section-inner">
        <div class="section-bar" style="background:#dc2626;"></div>
        <div class="section-text" style="color:#dc2626;">Actividades Vencidas ({{ $vencidas->count() }})</div>
    </div>
</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:25%;">Actividad</th>
            <th>Categoría</th>
            <th>Responsable</th>
            <th>Prioridad</th>
            <th>Fecha Fin</th>
            <th>Estado</th>
            <th>Avance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($vencidas as $act)
        @php
            $actProg = $act->seguimiento->where('programado', true)->sum(fn($s) => $act->cantidad_programada);
            $actReal = $act->seguimiento->sum('cantidad_realizada');
            $actPct  = $actProg > 0 ? round(($actReal / $actProg) * 100) : 0;
        @endphp
        <tr>
            <td style="font-weight:600;">{{ $act->nombre }}</td>
            <td>{{ $act->categoria->nombre ?? '—' }}</td>
            <td>{{ $act->nombreResponsable }}</td>
            <td>
                <span class="chip {{ $act->prioridad === 'ALTA' ? 'chip-red' : ($act->prioridad === 'MEDIA' ? 'chip-orange' : 'chip-green') }}">
                    {{ $act->prioridad }}
                </span>
            </td>
            <td>{{ $act->fecha_fin ? $act->fecha_fin->format('d/m/Y') : '—' }}</td>
            <td><span class="chip chip-red">{{ str_replace('_', ' ', $act->estado) }}</span></td>
            <td>
                <div class="mini-bar">
                    <div class="mini-fill fill-red" style="width:{{ min($actPct, 100) }}%;"></div>
                </div>
                <span style="font-size:7px;font-weight:700;margin-left:3px;">{{ $actPct }}%</span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Por Vencer --}}
@if($porVencer->count())
<div class="section" style="margin-top:14px;">
    <div class="section-inner">
        <div class="section-bar" style="background:#f59e0b;"></div>
        <div class="section-text" style="color:#d97706;">Actividades Por Vencer - 7 días ({{ $porVencer->count() }})</div>
    </div>
</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:25%;">Actividad</th>
            <th>Categoría</th>
            <th>Responsable</th>
            <th>Prioridad</th>
            <th>Fecha Fin</th>
            <th>Días Restantes</th>
        </tr>
    </thead>
    <tbody>
        @foreach($porVencer as $act)
        <tr>
            <td style="font-weight:600;">{{ $act->nombre }}</td>
            <td>{{ $act->categoria->nombre ?? '—' }}</td>
            <td>{{ $act->nombreResponsable }}</td>
            <td>
                <span class="chip {{ $act->prioridad === 'ALTA' ? 'chip-red' : ($act->prioridad === 'MEDIA' ? 'chip-orange' : 'chip-green') }}">
                    {{ $act->prioridad }}
                </span>
            </td>
            <td>{{ $act->fecha_fin ? $act->fecha_fin->format('d/m/Y') : '—' }}</td>
            <td style="font-weight:700;color:#d97706;">
                {{ $act->fecha_fin ? (int) now()->diffInDays($act->fecha_fin, false) : '—' }} días
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Reprogramaciones --}}
@if($reprogramaciones->count())
<div class="section" style="margin-top:14px;">
    <div class="section-inner">
        <div class="section-bar" style="background:#7c3aed;"></div>
        <div class="section-text" style="color:#7c3aed;">Historial de Reprogramaciones ({{ $reprogramaciones->count() }})</div>
    </div>
</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:22%;">Actividad</th>
            <th>Mes Original</th>
            <th>Mes Nuevo</th>
            <th style="width:30%;">Motivo</th>
            <th>Reprogramado Por</th>
            <th>Fecha</th>
        </tr>
    </thead>
    <tbody>
        @foreach($reprogramaciones as $rep)
        <tr>
            <td style="font-weight:600;">{{ $rep->actividad->nombre ?? '—' }}</td>
            <td><span class="chip chip-gray">{{ $mesesCortos[$rep->mes_original] ?? $rep->mes_original }}</span></td>
            <td><span class="chip chip-purple">{{ $mesesCortos[$rep->mes_nuevo] ?? $rep->mes_nuevo }}</span></td>
            <td>{{ Str::limit($rep->motivo, 60) }}</td>
            <td>{{ $rep->usuario->nombre_completo ?? '—' }}</td>
            <td>{{ $rep->created_at->format('d/m/Y') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Full activity detail table --}}
<div class="section" style="margin-top:14px;">
    <div class="section-inner">
        <div class="section-bar"></div>
        <div class="section-text">Resumen Completo de Actividades</div>
    </div>
</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:22%;">Actividad</th>
            <th>Categoría</th>
            <th>Responsable</th>
            <th>Prioridad</th>
            <th>Periodicidad</th>
            <th>Estado</th>
            <th>Avance</th>
            <th>Reprogs</th>
        </tr>
    </thead>
    <tbody>
        @foreach($cartaGantt->categorias->sortBy('orden') as $cat)
            @foreach($cat->actividades->sortBy('orden') as $act)
            @php
                $actProg = $act->seguimiento->where('programado', true)->sum(fn($s) => $act->cantidad_programada);
                $actReal = $act->seguimiento->sum('cantidad_realizada');
                $actPct  = $actProg > 0 ? round(($actReal / $actProg) * 100) : 0;
                $fillCls = $act->estado === 'COMPLETADA' ? 'fill-green' : ($actPct >= 50 ? 'fill-blue' : ($actPct > 0 ? 'fill-orange' : 'fill-gray'));
                $estadoCls = match($act->estado) {
                    'COMPLETADA' => 'chip-green',
                    'EN_PROGRESO' => 'chip-blue',
                    'CANCELADA' => 'chip-red',
                    default => 'chip-gray'
                };
            @endphp
            <tr>
                <td style="font-weight:600;">{{ Str::limit($act->nombre, 35) }}</td>
                <td>{{ $cat->nombre }}</td>
                <td>{{ $act->nombreResponsable }}</td>
                <td>
                    <span class="chip {{ $act->prioridad === 'ALTA' ? 'chip-red' : ($act->prioridad === 'MEDIA' ? 'chip-orange' : 'chip-green') }}">
                        {{ $act->prioridad }}
                    </span>
                </td>
                <td style="font-size:7px;">{{ $act->periodicidad ?? 'ÚNICA' }}</td>
                <td><span class="chip {{ $estadoCls }}">{{ str_replace('_', ' ', $act->estado) }}</span></td>
                <td>
                    <div class="mini-bar">
                        <div class="mini-fill {{ $fillCls }}" style="width:{{ min($actPct, 100) }}%;"></div>
                    </div>
                    <span style="font-size:7px;font-weight:700;margin-left:2px;">{{ $actPct }}%</span>
                </td>
                <td style="text-align:center;font-weight:700;{{ $act->reprogramaciones->count() > 0 ? 'color:#7c3aed;' : 'color:#94a3b8;' }}">
                    {{ $act->reprogramaciones->count() }}
                </td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

@if(!$vencidas->count() && !$porVencer->count() && !$reprogramaciones->count())
<div style="text-align:center;padding:30px;color:#94a3b8;">
    <div style="font-size:14px;margin-bottom:6px;">&#10003;</div>
    <div style="font-size:10px;font-weight:700;">Sin alertas activas</div>
    <div style="font-size:8px;">No hay actividades vencidas, por vencer ni reprogramaciones registradas.</div>
</div>
@endif

<div class="footer">
    <div class="footer-row">
        <div class="footer-cell">SAEP &bull; Sistema de Administración Empresarial de Prevención &bull; Reporte Gerencial SST</div>
        <div class="footer-cell footer-right">Página 3 &bull; {{ $cartaGantt->codigo }} &bull; {{ date('d/m/Y') }}</div>
    </div>
</div>

</body>
</html>
