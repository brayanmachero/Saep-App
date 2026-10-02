<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
/* ── Reset & Page ── */
* { margin:0; padding:0; box-sizing:border-box; }
@page { margin: 0 0 42px 0; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; background: #fff; }

/* ── Content wrapper with lateral padding ── */
.content { padding: 0 50px; }

/* ── Fixed footer (every page) ── */
.fixed-footer {
    position: fixed;
    bottom: -10px;
    left: 0;
    width: 100%;
    height: 31px;
    background: transparent;
    border-top: 1px solid #dbe3ee;
    padding: 8px 50px 0;
    font-size: 7px;
    color: #64748b;
}
.ff-row { display: table; width: 100%; }
.ff-cell { display: table-cell; vertical-align: middle; }
.ff-brand { font-size: 8px; font-weight: 700; color: #0f1b4c; letter-spacing: .6px; }
.ff-separator { color: #f97316; padding: 0 6px; }
.ff-meta { text-align: center; color: #64748b; }

/* ── Header band ── */
.header-band {
    background: #0f1b4c;
    padding: 11px 38px;
    display: table;
    width: 100%;
}
.hdr-logo { display: table-cell; vertical-align: middle; width: 164px; }
.hdr-logo-box { padding: 7px 12px 7px 0; }
.hdr-logo img { max-height: 40px; max-width: 145px; }
.hdr-center { display: table-cell; vertical-align: middle; text-align: center; }
.hdr-center h1 { font-size: 14px; font-weight: 700; color: #ffffff; text-transform: uppercase; letter-spacing: 1px; }
.hdr-center p { font-size: 8px; color: rgba(255,255,255,0.6); margin-top: 2px; letter-spacing: 0.5px; }
.hdr-right { display: table-cell; vertical-align: middle; text-align: right; width: 160px; }
.hdr-right .code { font-size: 11px; font-weight: 800; color: #f97316; }
.hdr-right .date { font-size: 7.5px; color: rgba(255,255,255,0.5); margin-top: 2px; }

/* ── Orange accent line ── */
.accent-line { height: 3px; background: #f97316; }

/* ── Program facts: two rows of individually spaced cards ── */
.info-grid { margin: 13px 0 0; }
.info-row { display: table; width: 100%; }
.info-row + .info-row { margin-top: 7px; }
.info-item { display: table-cell; width: 33%; vertical-align: middle; padding: 9px 11px; background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #0f1b4c; }
.info-gap { display: table-cell; width: 7px; }
.info-item .label { font-size: 7px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.45px; }
.info-item .value { font-size: 9px; font-weight: 700; color: #0f172a; margin-top: 4px; line-height: 1.25; }

/* ── KPI cards ── */
.kpi-row { display: table; width: 100%; margin: 16px 0 14px; }
.kpi-card { display: table-cell; text-align: center; padding: 12px 5px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; vertical-align: middle; }
.kpi-card .kpi-num { font-size: 22px; font-weight: 700; line-height: 1; }
.kpi-card .kpi-label { font-size: 7px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; color: #64748b; margin-top: 3px; }
.kpi-blue .kpi-num   { color: #0f1b4c; }
.kpi-green .kpi-num  { color: #059669; }
.kpi-yellow .kpi-num { color: #d97706; }
.kpi-red .kpi-num    { color: #dc2626; }
.kpi-gray .kpi-num   { color: #6b7280; }
.kpi-purple .kpi-num { color: #7c3aed; }
.kpi-spacer { display: table-cell; width: 5px; }

/* ── Section titles ── */
.section { margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0; }
.section-inner { display: table; width: 100%; }
.section-bar { display: table-cell; width: 4px; background: #0f1b4c; border-radius: 2px; }
.section-text { display: table-cell; vertical-align: middle; padding-left: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #1e293b; letter-spacing: 0.5px; }

/* ── Two-column layout ── */
.two-col { display: table; width: 100%; margin: 14px 0; }
.col-left { display: table-cell; width: 38%; vertical-align: top; padding-right: 22px; }
.col-right { display: table-cell; width: 62%; vertical-align: top; }

/* ── Three-column layout ── */
.three-col { display: table; width: 100%; margin: 8px 0; }
.col-33 { display: table-cell; width: 33.33%; vertical-align: top; padding: 0 8px; }
.col-33:first-child { padding-left: 0; }
.col-33:last-child { padding-right: 0; }

/* ── Actual progress, drawn to scale (unlike a decorative ring) ── */
.progress-panel { padding: 13px 15px 12px; background: #f8fafc; border: 1px solid #dbe3ee; border-left: 4px solid #f97316; }
.progress-eyebrow { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.progress-main { display: table; width: 100%; margin: 5px 0 7px; }
.progress-number { display: table-cell; color: #0f1b4c; font-size: 31px; line-height: 1.1; font-weight: 700; }
.progress-fraction { display: table-cell; color: #475569; font-size: 10px; text-align: right; vertical-align: bottom; padding-bottom: 5px; }
.progress-track { height: 13px; width: 100%; background: #e2e8f0; }
.progress-fill { height: 13px; background: #f97316; }
.progress-caption { margin-top: 5px; color: #64748b; font-size: 8px; }

/* ── Status summary cards ── */
.status-grid { margin-top: 8px; }
.status-row { display: table; width: 100%; }
.status-row + .status-row { margin-top: 6px; }
.status-cell { display: table-cell; width: 49%; vertical-align: middle; padding: 6px 8px; border: 1px solid #e2e8f0; background: #fff; }
.status-gap { display: table-cell; width: 6px; }
.status-inner { display: table; width: 100%; }
.status-name { display: table-cell; vertical-align: middle; font-size: 8px; color: #475569; font-weight: 700; white-space: nowrap; }
.status-count { display: table-cell; vertical-align: middle; text-align: right; font-size: 11px; color: #0f1b4c; font-weight: 700; }
.status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 4px; margin-right: 5px; vertical-align: middle; }
.dot-completada  { background: #059669; }
.dot-progreso    { background: #2563eb; }
.dot-pendiente   { background: #94a3b8; }
.dot-cancelada   { background: #dc2626; }

/* ── Bar chart ── */
.bar-chart { width: 100%; }
.bar-row { display: table; width: 100%; margin-bottom: 4px; }
.bar-label { display: table-cell; width: 30px; font-size: 8px; font-weight: 700; color: #475569; vertical-align: middle; text-align: center; }
.bar-track { display: table-cell; vertical-align: middle; padding: 0 4px; }
.bar-bg { height: 14px; background: #f1f5f9; border-radius: 3px; position: relative; overflow: hidden; }
.bar-fill-prog { height: 14px; background: #cbd5e1; border-radius: 3px 0 0 3px; position: absolute; top: 0; left: 0; }
.bar-fill-real { height: 14px; background: #0f1b4c; border-radius: 3px 0 0 3px; position: absolute; top: 0; left: 0; }
.bar-val { display: table-cell; width: 32px; font-size: 8px; font-weight: 700; color: #0f1b4c; vertical-align: middle; text-align: right; }

/* ── Priority bars ── */
.pri-row { display: table; width: 100%; margin-bottom: 6px; }
.pri-label { display: table-cell; width: 50px; font-size: 8px; font-weight: 700; vertical-align: middle; }
.pri-bar-wrap { display: table-cell; vertical-align: middle; padding: 0 6px; background: #f1f5f9; }
.pri-bar { height: 12px; border-radius: 3px; }
.pri-count { display: table-cell; width: 26px; font-size: 8px; font-weight: 700; color: #475569; vertical-align: middle; text-align: right; }
.pri-alta  { background: #dc2626; }
.pri-media { background: #f59e0b; }
.pri-baja  { background: #10b981; }

/* ── Category progress table ── */
.cat-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
.cat-table th { background: #f1f5f9; color: #475569; font-size: 8px; font-weight: 700; padding: 6px 8px; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #e2e8f0; }
.cat-table td { padding: 5px 8px; font-size: 9px; border: 1px solid #e2e8f0; vertical-align: middle; }
.cat-table tr:nth-child(even) td { background: #f8fafc; }
.cat-bar { height: 10px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.cat-bar-fill { height: 10px; border-radius: 3px; }

/* ── Data table ── */
.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
.data-table th { background: #0f1b4c; color: #fff; font-size: 7.5px; font-weight: 700; padding: 6px 7px; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; }
.data-table td { padding: 3px 7px; font-size: 9px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
.data-table tr:nth-child(even) td { background: #f8fafc; }
.data-table thead { display: table-header-group; }
.data-table tr { page-break-inside: avoid; }

/* Mini progress bar */
.mini-bar { height: 9px; background: #e2e8f0; border-radius: 3px; overflow: hidden; width: 65px; display: inline-block; vertical-align: middle; }
.mini-fill { height: 9px; border-radius: 3px; }
.fill-green  { background: #059669; }
.fill-blue   { background: #2563eb; }
.fill-orange { background: #f59e0b; }
.fill-red    { background: #dc2626; }
.fill-gray   { background: #94a3b8; }

/* Chips */
.chip { padding: 2px 6px; border-radius: 3px; font-size: 7px; font-weight: 700; text-transform: uppercase; }
.chip-green  { background: #dcfce7; color: #15803d; }
.chip-blue   { background: #dbeafe; color: #1d4ed8; }
.chip-orange { background: #fef3c7; color: #92400e; }
.chip-red    { background: #fee2e2; color: #991b1b; }
.chip-gray   { background: #f1f5f9; color: #475569; }
.chip-purple { background: #ede9fe; color: #6d28d9; }

/* ── Gantt grid ── */
.gantt-mini { width: 100%; border-collapse: collapse; margin-top: 6px; }
.gantt-mini th { background: #f1f5f9; color: #475569; font-size: 7px; font-weight: 700; padding: 4px 3px; text-align: center; border: 1px solid #e2e8f0; width: 7%; }
.gantt-mini th:first-child { width: 16%; text-align: left; padding-left: 6px; }
.gantt-mini .table-caption th { width: auto; text-align: left; padding: 5px 7px; background: #f8fafc; color: #0f1b4c; font-size: 8px; border-bottom: 1px solid #dbe3ee; }
.gantt-mini td { padding: 1px 3px; font-size: 7.5px; text-align: center; border: 1px solid #e2e8f0; height: 13px; vertical-align: middle; }
.gantt-mini thead { display: table-header-group; }
.gantt-mini tr { page-break-inside: avoid; }
.gantt-mini td:first-child { text-align: left; padding-left: 6px; font-weight: 600; font-size: 8px; }
.g-prog { background: #dbeafe; }
.g-done { background: #059669; color: #fff; font-weight: 700; font-size: 7px; }
.g-partial { background: #fef3c7; color: #92400e; font-weight: 700; font-size: 7px; }
.g-miss { background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 7px; }
.g-future { background: #f0fdf4; }
.g-reprog { background: #ede9fe; color: #6d28d9; font-weight: 700; font-size: 7px; }
.period-note { margin: 10px 0 0; padding: 8px 11px; background: #f8fafc; border-left: 3px solid #f97316; color: #475569; font-size: 8.5px; }
.month-detail th:first-child { width: 39%; }
.month-detail th { padding: 3px; }
.month-detail td { padding: 1px 3px; height: 13px; font-size: 8px; }
.month-detail td:first-child { font-size: 8px; }
.month-detail .week-value { font-size: 9px; font-weight: 800; }
.detail-content .section { margin: 8px 0 5px; }
.detail-content .category-block { margin-top: 5px; }
.summary-table td { padding: 1px 5px; font-size: 8px; line-height: 1.08; }
.summary-table th { padding: 4px 5px; font-size: 7px; }
.summary-table .mini-bar, .summary-table .mini-fill { height: 7px; }
.matrix-legend { margin: 8px 0 10px; padding: 7px 10px; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; page-break-inside: avoid; }

.page-break { page-break-after: always; }
.avoid-break { page-break-inside: avoid; }
</style>
</head>
<body>

{{-- ═══════════ FIXED FOOTER (appears on every page) ═══════════ --}}
<div class="fixed-footer">
    <div class="ff-row">
        <div class="ff-cell" style="width:24%;"><span class="ff-brand">SAEP</span><span class="ff-separator">&bull;</span>Reporte SST</div>
        <div class="ff-cell ff-meta" style="width:53%;">Documento de uso interno &bull; {{ $cartaGantt->codigo }} &bull; {{ date('d/m/Y') }}</div>
        <div class="ff-cell" style="width:23%;"></div>
    </div>
</div>

@php
    $maxProg = max(1, collect($mesesData)->max('prog'));
    $logoUrl = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('brand/wp/logo-saep-email.png')));
    $maxSemana = max(1, collect($semanasReporte)->max('realizado'));
    $pendienteUnidades = max(0, $totalProgramado - $totalRealizado);
@endphp

{{-- ═══════════════ PAGE 1: EXECUTIVE SUMMARY ═══════════════ --}}
<div class="header-band">
    <div class="hdr-logo"><div class="hdr-logo-box"><img src="{{ $logoUrl }}" alt="SAEP"></div></div>
    <div class="hdr-center">
        <h1>Reporte SST {{ ucfirst($tipoReporte) }}</h1>
        <p>Programa de Seguridad y Salud en el Trabajo &bull; {{ $periodoEtiqueta }}</p>
    </div>
    <div class="hdr-right">
        <div class="code">{{ $cartaGantt->codigo }}</div>
        <div class="date">Generado: {{ date('d/m/Y H:i') }}</div>
    </div>
</div>
<div class="accent-line"></div>

<div class="content">
    {{-- Program facts --}}
    <div class="info-grid">
        <div class="info-row">
            <div class="info-item">
                <div class="label">Programa</div>
                <div class="value">{{ $cartaGantt->titulo }}</div>
            </div>
            <div class="info-gap"></div>
            <div class="info-item">
                <div class="label">Centro de costo</div>
                <div class="value">{{ $cartaGantt->centroCosto->nombre ?? '—' }}</div>
            </div>
            <div class="info-gap"></div>
            <div class="info-item">
                <div class="label">Responsable</div>
                <div class="value">{{ $cartaGantt->responsable->nombre_completo ?? '—' }}</div>
            </div>
        </div>
        <div class="info-row">
            <div class="info-item">
                <div class="label">Periodo del informe</div>
                <div class="value">{{ $periodoEtiqueta }}</div>
            </div>
            <div class="info-gap"></div>
            <div class="info-item">
                <div class="label">Año</div>
                <div class="value">{{ $cartaGantt->anio }}</div>
            </div>
            <div class="info-gap"></div>
            <div class="info-item">
                <div class="label">Estado</div>
                <div class="value">{{ ucfirst($cartaGantt->estado) }}</div>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="kpi-row">
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

    {{-- Executive reading: precise progress, status and distribution over time --}}
    <div style="height:1px;background:#e2e8f0;margin:6px 0;"></div>
    <div class="two-col">
        <div class="col-left">
            <div class="progress-panel">
                <div class="progress-eyebrow">Cumplimiento del periodo</div>
                <div class="progress-main">
                    <div class="progress-number">{{ $pct }}%</div>
                    <div class="progress-fraction">{{ $totalRealizado }} de {{ $totalProgramado }} ejecuciones</div>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:{{ min(100, max(0, $pct)) }}%;{{ $pct >= 100 ? 'background:#059669;' : '' }}"></div></div>
                <div class="progress-caption">{{ $pendienteUnidades }} por ejecutar en el periodo seleccionado</div>
            </div>
            <div class="status-grid">
                <div class="status-row">
                    <div class="status-cell"><div class="status-inner"><div class="status-name"><span class="status-dot dot-completada"></span>Completadas</div><div class="status-count">{{ $completadas }}</div></div></div>
                    <div class="status-gap"></div>
                    <div class="status-cell"><div class="status-inner"><div class="status-name"><span class="status-dot dot-progreso"></span>En progreso</div><div class="status-count">{{ $enProgreso }}</div></div></div>
                </div>
                <div class="status-row">
                    <div class="status-cell"><div class="status-inner"><div class="status-name"><span class="status-dot dot-pendiente"></span>Pendientes</div><div class="status-count">{{ $pendientes }}</div></div></div>
                    <div class="status-gap"></div>
                    <div class="status-cell"><div class="status-inner"><div class="status-name"><span class="status-dot dot-cancelada"></span>Canceladas</div><div class="status-count">{{ $canceladas }}</div></div></div>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <div style="font-size:9px;font-weight:800;color:#0f1b4c;text-transform:uppercase;margin-bottom:5px;letter-spacing:0.5px;">Distribución por Prioridad</div>
                @foreach(['ALTA' => 'pri-alta', 'MEDIA' => 'pri-media', 'BAJA' => 'pri-baja'] as $pri => $cls)
                <div class="pri-row">
                    <div class="pri-label" style="color:{{ $pri === 'ALTA' ? '#dc2626' : ($pri === 'MEDIA' ? '#d97706' : '#059669') }};">{{ $pri }}</div>
                    <div class="pri-bar-wrap">
                        <div class="pri-bar {{ $cls }}" style="width:{{ $totalAct > 0 ? round(($prioridades[$pri] / $totalAct) * 100) : 0 }}%;"></div>
                    </div>
                    <div class="pri-count">{{ $prioridades[$pri] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="col-right">
            <div style="font-size:9px;font-weight:800;color:#0f1b4c;text-transform:uppercase;margin-bottom:6px;letter-spacing:0.5px;">
                {{ $tipoReporte === 'mensual' ? 'Ejecuciones registradas por semana' : 'Cumplimiento por mes (programado vs realizado)' }}
            </div>
            <div style="font-size:8px;color:#64748b;margin-bottom:8px;">
                @if($tipoReporte === 'mensual')
                    Distribución de {{ $totalRealizado }} ejecuciones registradas en {{ $periodoEtiqueta }}.
                @else
                    Meta {{ $totalProgramado }} &bull; Realizado {{ $totalRealizado }}. Cada barra compara contra el mes con mayor meta.
                @endif
            </div>
            <div class="bar-chart">
                @if($tipoReporte === 'mensual')
                @foreach($semanasReporte as $semana)
                    <div class="bar-row" style="margin-bottom:9px;">
                        <div class="bar-label" style="width:70px;text-align:left;">S{{ $semana['numero'] }} ({{ $semana['desde'] }}-{{ $semana['hasta'] }})</div>
                        <div class="bar-track"><div class="bar-bg"><div class="bar-fill-real" style="width:{{ round($semana['realizado'] / $maxSemana * 100) }}%;"></div></div></div>
                        <div class="bar-val">{{ $semana['realizado'] }}</div>
                    </div>
                @endforeach
                @else
                @foreach($mesesSeleccionados as $m)
                    @php
                        $d = $mesesData[$m];
                        $wProg = $maxProg > 0 ? round(($d['prog'] / $maxProg) * 100) : 0;
                        $wReal = $maxProg > 0 ? round(($d['real'] / $maxProg) * 100) : 0;
                    @endphp
                    <div class="bar-row">
                        <div class="bar-label" style="{{ $m === $mesActual ? 'color:#0f1b4c;font-weight:900;' : '' }}">{{ $mesesNombres[$m] }}</div>
                        <div class="bar-track">
                            <div class="bar-bg" style="position:relative;">
                                @if($d['prog'] > 0)
                                <div class="bar-fill-prog" style="width:{{ $wProg }}%;"></div>
                                <div class="bar-fill-real" style="width:{{ $wReal }}%;"></div>
                                @endif
                            </div>
                        </div>
                        <div class="bar-val">{{ $d['prog'] > 0 ? $d['pct'].'%' : '—' }}</div>
                    </div>
                @endforeach
                @endif
            </div>
            @if($tipoReporte !== 'mensual')
            <div style="margin-top:5px;">
                <span style="display:inline-block;width:12px;height:7px;background:#cbd5e1;border-radius:2px;"></span>
                <span style="font-size:7.5px;color:#94a3b8;">Programado</span>&nbsp;&nbsp;
                <span style="display:inline-block;width:12px;height:7px;background:#0f1b4c;border-radius:2px;"></span>
                <span style="font-size:7.5px;color:#94a3b8;">Realizado</span>
            </div>
            @else
            <div style="margin-top:7px;font-size:8px;color:#64748b;">Barras relativas a la semana con más registros. "Sin semana" conserva avances históricos sin fecha semanal registrada.
                @if(array_sum($sinSemanaPorActividad) > 0) {{ array_sum($sinSemanaPorActividad) }} ejecución(es) sin semana incluida(s) en el total.@endif
            </div>
            @endif
        </div>
    </div>

    {{-- ── Category Progress Summary ── --}}
    <div style="height:1px;background:#e2e8f0;margin:6px 0;"></div>
    <div class="section">
        <div class="section-inner">
            <div class="section-bar"></div>
            <div class="section-text">Avance por Categoría</div>
        </div>
    </div>
    <table class="cat-table">
        <thead>
            <tr>
                <th style="width:30%;">Categoría</th>
                <th style="width:10%;text-align:center;">Total</th>
                <th style="width:10%;text-align:center;">Completadas</th>
                <th style="width:10%;text-align:center;">En Progreso</th>
                <th style="width:10%;text-align:center;">Pendientes</th>
                <th style="width:30%;">Avance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($catStats as $cs)
            <tr>
                <td style="font-weight:700;">{{ $cs['nombre'] }}</td>
                <td style="text-align:center;font-weight:700;color:#0f1b4c;">{{ $cs['total'] }}</td>
                <td style="text-align:center;font-weight:700;color:#059669;">{{ $cs['comp'] }}</td>
                <td style="text-align:center;font-weight:700;color:#2563eb;">{{ $cs['prog'] }}</td>
                <td style="text-align:center;font-weight:700;color:#94a3b8;">{{ $cs['pend'] }}</td>
                <td>
                    <div style="display:table;width:100%;">
                        <div style="display:table-cell;vertical-align:middle;width:75%;">
                            <div class="cat-bar">
                                <div class="cat-bar-fill" style="width:{{ $cs['pct'] }}%;background:{{ $cs['pct'] >= 75 ? '#059669' : ($cs['pct'] >= 50 ? '#2563eb' : ($cs['pct'] >= 25 ? '#f59e0b' : '#dc2626')) }};"></div>
                            </div>
                        </div>
                        <div style="display:table-cell;vertical-align:middle;padding-left:6px;font-weight:800;font-size:9px;color:#0f1b4c;">{{ $cs['pct'] }}%</div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($tipoReporte === 'mensual' && $totalAct === 0)
    <div class="period-note">No hay actividades programadas en {{ $periodoEtiqueta }}. Selecciona otro mes para consultar su avance.</div>
    @endif
</div>

<div class="page-break"></div>

{{-- ═══════════════ PAGE 2: GANTT DETAIL ═══════════════ --}}
<div class="header-band">
    <div class="hdr-logo"><div class="hdr-logo-box"><img src="{{ $logoUrl }}" alt="SAEP"></div></div>
    <div class="hdr-center">
        <h1>{{ $tipoReporte === 'mensual' ? 'Detalle semanal de actividades' : 'Detalle de actividades por categoría' }}</h1>
        <p>{{ $cartaGantt->titulo }} &bull; {{ $periodoEtiqueta }}</p>
    </div>
    <div class="hdr-right">
        <div class="code">{{ $cartaGantt->codigo }}</div>
        <div class="date">Generado: {{ date('d/m/Y H:i') }}</div>
    </div>
</div>
<div class="accent-line"></div>

<div class="content detail-content">
    @if($tipoReporte !== 'mensual')
    <div class="matrix-legend">
        <span style="font-size:8px;font-weight:700;text-transform:uppercase;margin-right:12px;">Cómo leer la matriz:</span>
        <span style="display:inline-block;width:12px;height:9px;background:#059669;border-radius:2px;margin-right:3px;vertical-align:middle;"></span><span style="font-size:8px;">Completado</span>&nbsp;&nbsp;
        <span style="display:inline-block;width:12px;height:9px;background:#fef3c7;border:1px solid #e2e8f0;border-radius:2px;margin-right:3px;vertical-align:middle;"></span><span style="font-size:8px;">Parcial</span>&nbsp;&nbsp;
        <span style="display:inline-block;width:12px;height:9px;background:#fee2e2;border:1px solid #e2e8f0;border-radius:2px;margin-right:3px;vertical-align:middle;"></span><span style="font-size:8px;">No cumplido</span>&nbsp;&nbsp;
        <span style="display:inline-block;width:12px;height:9px;background:#f0fdf4;border:1px solid #e2e8f0;border-radius:2px;margin-right:3px;vertical-align:middle;"></span><span style="font-size:8px;">Futuro</span>&nbsp;&nbsp;
        <span style="display:inline-block;width:12px;height:9px;background:#ede9fe;border:1px solid #e2e8f0;border-radius:2px;margin-right:3px;vertical-align:middle;"></span><span style="font-size:8px;">Reprogramado</span>
    </div>
    @endif
    @foreach($cartaGantt->categorias->sortBy('orden') as $categoria)
    @php $actividadesCategoria = $categoria->actividades->filter(fn ($actividad) => isset($resumenActividades[$actividad->id])); @endphp
    @if($actividadesCategoria->isNotEmpty())
    <div class="category-block">
        <div class="section">
            <div class="section-inner">
                <div class="section-bar"></div>
                <div class="section-text">{{ $categoria->nombre }}</div>
            </div>
        </div>

        <table class="gantt-mini {{ $tipoReporte === 'mensual' ? 'month-detail' : '' }}">
            <thead>
                <tr class="table-caption"><th colspan="{{ $tipoReporte === 'mensual' ? count($semanasReporte) + 3 : count($mesesSeleccionados) + 1 }}">{{ $categoria->nombre }} &bull; {{ $periodoEtiqueta }} &bull; {{ $cartaGantt->codigo }}</th></tr>
                <tr>
                    <th style="width:{{ $tipoReporte === 'mensual' ? '39%' : ($tipoReporte === 'semestral' ? '28%' : '23%') }};">Actividad</th>
                    @if($tipoReporte === 'mensual')
                    <th>Real / Meta</th>
                    @foreach($semanasReporte as $semana)
                    <th>S{{ $semana['numero'] }}<br>{{ $semana['desde'] }}-{{ $semana['hasta'] }}</th>
                    @endforeach
                    <th>Sin semana</th>
                    @else
                    @foreach($mesesSeleccionados as $m)
                    <th style="{{ $m === $mesActual ? 'background:#0f1b4c;color:#fff;' : '' }}">{{ $mesesNombres[$m] }}</th>
                    @endforeach
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($actividadesCategoria->sortBy('orden') as $act)
                @php
                    $segPorMes = $act->seguimientoPorMes;
                    $reprogMeses = $act->reprogramaciones->pluck('mes_nuevo')->unique()->toArray();
                    $resumen = $resumenActividades[$act->id];
                @endphp
                <tr>
                    <td>
                        {{ Str::limit($act->nombre, $tipoReporte === 'mensual' ? 62 : ($tipoReporte === 'semestral' ? 52 : 38)) }}
                        @if($resumen['vencida'])
                            <span class="chip chip-red">V</span>
                        @elseif($resumen['estado'] === 'COMPLETADA')
                            <span class="chip chip-green">OK</span>
                        @endif
                    </td>
                    @if($tipoReporte === 'mensual')
                    <td style="font-weight:800;color:#0f1b4c;">{{ $resumen['realizado'] }}/{{ $resumen['programado'] }}</td>
                    @foreach($semanasReporte as $clave => $semana)
                    @php $avanceSemana = $semanasPorActividad[$act->id][$clave] ?? 0; @endphp
                    <td class="{{ $avanceSemana > 0 ? ($resumen['programado'] === 1 ? 'g-done' : 'g-partial') : '' }} week-value">{{ $avanceSemana > 0 ? $avanceSemana : '·' }}</td>
                    @endforeach
                    <td style="color:#64748b;">{{ ($sinSemanaPorActividad[$act->id] ?? 0) ?: '·' }}</td>
                    @else
                    @foreach($mesesSeleccionados as $m)
                    @php
                        $s = $segPorMes[$m] ?? null;
                        $prog = $s['programado'] ?? false;
                        $real = $s['realizado'] ?? false;
                        $cantR = $s['cantidad_realizada'] ?? 0;
                        $cantP = $s['cantidad_programada'] ?? $act->cantidad_programada;
                        $isReprog = in_array($m, $reprogMeses);
                        $mesVencido = \Carbon\Carbon::create($cartaGantt->anio, $m)->endOfMonth()->isPast();
                    @endphp
                    <td class="@if($isReprog && $prog) g-reprog
                               @elseif($prog && $real) g-done
                               @elseif($prog && $cantR > 0 && !$real) g-partial
                               @elseif($prog && $mesVencido && !$real && $cantR === 0) g-miss
                               @elseif($prog && !$mesVencido) g-future
                               @elseif($prog) g-prog
                               @endif">
                        @if($prog && $real)
                            &#10003;
                        @elseif($prog && $cantR > 0)
                            {{ $cantR }}/{{ $cantP }}
                        @elseif($prog && $mesVencido)
                            &#10007;
                        @elseif($prog)
                            &bull;
                        @endif
                    </td>
                    @endforeach
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endforeach

    @if($tipoReporte === 'mensual' && ($vencidas->isNotEmpty() || $reprogramaciones->isNotEmpty()))
    <div class="section"><div class="section-inner"><div class="section-bar"></div><div class="section-text">Alertas del periodo</div></div></div>
    @if($vencidas->isNotEmpty())
    <div class="period-note">{{ $vencidas->count() }} actividad(es) con programación vencida en {{ $periodoEtiqueta }}: {{ $vencidas->take(8)->pluck('nombre')->join('; ') }}{{ $vencidas->count() > 8 ? '; y otras.' : '.' }}</div>
    @endif
    @if($reprogramaciones->isNotEmpty())
    <table class="data-table">
        <thead><tr><th>Actividad reprogramada</th><th>Mes original</th><th>Mes nuevo</th><th>Motivo</th><th>Fecha</th></tr></thead>
        <tbody>
            @foreach($reprogramaciones as $rep)
            <tr>
                <td>{{ $rep->actividad->nombre ?? '—' }}</td>
                <td>{{ $mesesNombres[$rep->mes_original] ?? $rep->mes_original }}</td>
                <td>{{ $mesesNombres[$rep->mes_nuevo] ?? $rep->mes_nuevo }}</td>
                <td>{{ Str::limit($rep->motivo, 75) }}</td>
                <td>{{ $rep->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    @endif
</div>

@if($tipoReporte !== 'mensual')
<div class="page-break"></div>

{{-- ═══════════════ PAGE 3: REPROGRAMACIONES & RESUMEN ═══════════════ --}}
<div class="header-band">
    <div class="hdr-logo"><div class="hdr-logo-box"><img src="{{ $logoUrl }}" alt="SAEP"></div></div>
    <div class="hdr-center">
        <h1>Reprogramaciones y Resumen de Actividades</h1>
        <p>{{ $cartaGantt->titulo }} &bull; {{ $periodoEtiqueta }}</p>
    </div>
    <div class="hdr-right">
        <div class="code">{{ $cartaGantt->codigo }}</div>
        <div class="date">Generado: {{ date('d/m/Y H:i') }}</div>
    </div>
</div>
<div class="accent-line"></div>

<div class="content">
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
                $seguimientosProgramados = collect($act->seguimientoPorMes)->filter(fn($s) => $s['programado']);
                $actProg = $seguimientosProgramados->sum(fn($s) => max(1, (int) ($s['cantidad_programada'] ?? $act->cantidad_programada ?? 1)));
                $actReal = $seguimientosProgramados->sum(function ($s) use ($act) {
                    $cantidad = max(1, (int) ($s['cantidad_programada'] ?? $act->cantidad_programada ?? 1));
                    return $s['realizado'] ? $cantidad : min($cantidad, (int) ($s['cantidad_realizada'] ?? 0));
                });
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
                    <span style="font-size:7.5px;font-weight:700;margin-left:3px;">{{ $actPct }}%</span>
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
                <td><span class="chip chip-gray">{{ $mesesNombres[$rep->mes_original] ?? $rep->mes_original }}</span></td>
                <td><span class="chip chip-purple">{{ $mesesNombres[$rep->mes_nuevo] ?? $rep->mes_nuevo }}</span></td>
                <td>{{ Str::limit($rep->motivo, 60) }}</td>
                <td>{{ $rep->usuario->nombre_completo ?? '—' }}</td>
                <td>{{ $rep->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Full activity detail --}}
    <div class="section" style="margin-top:14px;">
        <div class="section-inner">
            <div class="section-bar"></div>
            <div class="section-text">Resumen Completo de Actividades</div>
        </div>
    </div>
    <table class="data-table summary-table">
        <thead>
            <tr>
                <th style="width:28%;">Actividad</th>
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
                @foreach($cat->actividades->sortBy('orden')->filter(fn ($actividad) => isset($resumenActividades[$actividad->id])) as $act)
                @php
                    $actPct = $resumenActividades[$act->id]['pct'];
                    $estadoPeriodo = $resumenActividades[$act->id]['estado'];
                    $fillCls = $estadoPeriodo === 'COMPLETADA' ? 'fill-green' : ($actPct >= 50 ? 'fill-blue' : ($actPct > 0 ? 'fill-orange' : 'fill-gray'));
                    $estadoCls = match($estadoPeriodo) {
                        'COMPLETADA' => 'chip-green',
                        'EN_PROGRESO' => 'chip-blue',
                        'CANCELADA' => 'chip-red',
                        default => 'chip-gray'
                    };
                @endphp
                <tr>
                    <td style="font-weight:600;">{{ Str::limit($act->nombre, 55) }}</td>
                    <td>{{ $cat->nombre }}</td>
                    <td>{{ $act->nombreResponsable }}</td>
                    <td>
                        <span class="chip {{ $act->prioridad === 'ALTA' ? 'chip-red' : ($act->prioridad === 'MEDIA' ? 'chip-orange' : 'chip-green') }}">
                            {{ $act->prioridad }}
                        </span>
                    </td>
                    <td style="font-size:7.5px;">{{ $act->periodicidad ?? 'ÚNICA' }}</td>
                    <td><span class="chip {{ $estadoCls }}">{{ str_replace('_', ' ', $estadoPeriodo) }}</span></td>
                    <td>
                        <div class="mini-bar">
                            <div class="mini-fill {{ $fillCls }}" style="width:{{ min($actPct, 100) }}%;"></div>
                        </div>
                        <span style="font-size:7.5px;font-weight:700;margin-left:2px;">{{ $actPct }}%</span>
                    </td>
                    <td style="text-align:center;font-weight:700;{{ $act->reprogramaciones->count() > 0 ? 'color:#7c3aed;' : 'color:#94a3b8;' }}">
                        {{ $act->reprogramaciones->count() }}
                    </td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

</div>

@endif

</body>
</html>
