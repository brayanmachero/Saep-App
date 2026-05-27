@extends('layouts.app')

@section('title', 'Analytics Talana · SAEP')

@push('styles')
<style>
    /* ── Contenedor principal ─────────────────────────────────────────── */
    .grafana-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding: 0;
    }

    /* ── Cabecera ─────────────────────────────────────────────────────── */
    .grafana-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        backdrop-filter: blur(12px);
    }
    .grafana-header-left {
        display: flex;
        align-items: center;
        gap: .85rem;
    }
    .grafana-header-icon {
        width: 44px; height: 44px;
        background: linear-gradient(135deg,#f97316,#ea580c);
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; color: #fff; flex-shrink: 0;
    }
    .grafana-header h1 {
        font-size: 1.35rem; font-weight: 700;
        color: #fff; margin: 0;
    }
    .grafana-header .subtitle {
        font-size: .78rem; color: rgba(255,255,255,.5);
        margin-top: 2px;
    }
    .badge-beta {
        background: linear-gradient(135deg,#f97316,#dc2626);
        color: #fff; font-size: .65rem; font-weight: 700;
        padding: 2px 8px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: .05em;
        vertical-align: middle; margin-left: 6px;
    }
    .grafana-actions {
        display: flex; gap: .65rem; flex-wrap: wrap;
    }
    .btn-grafana {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .45rem .9rem;
        border-radius: 8px; font-size: .82rem; font-weight: 600;
        text-decoration: none; cursor: pointer; border: none;
        transition: all .2s;
    }
    .btn-grafana-primary {
        background: linear-gradient(135deg,#f97316,#ea580c);
        color: #fff;
    }
    .btn-grafana-primary:hover { opacity: .88; color: #fff; }
    .btn-grafana-secondary {
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.8);
        border: 1px solid rgba(255,255,255,.12);
    }
    .btn-grafana-secondary:hover {
        background: rgba(255,255,255,.14);
        color: #fff;
    }

    /* ── KPI Cards ────────────────────────────────────────────────────── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: .9rem;
    }
    .kpi-card {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        padding: 1rem 1.1rem;
        display: flex; flex-direction: column; gap: .35rem;
        backdrop-filter: blur(8px);
        transition: transform .2s, border-color .2s;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: rgba(249,115,22,.35);
    }
    .kpi-card .kpi-label {
        font-size: .72rem; color: rgba(255,255,255,.5);
        text-transform: uppercase; letter-spacing: .06em; font-weight: 600;
    }
    .kpi-card .kpi-value {
        font-size: 2rem; font-weight: 800; color: #fff;
        line-height: 1;
    }
    .kpi-card .kpi-sub {
        font-size: .74rem; color: rgba(255,255,255,.45);
    }
    .kpi-card.kpi-danger  { border-color: rgba(239,68,68,.4); }
    .kpi-card.kpi-warning { border-color: rgba(249,115,22,.4); }
    .kpi-card.kpi-success { border-color: rgba(34,197,94,.4); }
    .kpi-card.kpi-info    { border-color: rgba(59,130,246,.4); }
    .kpi-card.kpi-danger  .kpi-value { color: #f87171; }
    .kpi-card.kpi-warning .kpi-value { color: #fb923c; }
    .kpi-card.kpi-success .kpi-value { color: #4ade80; }
    .kpi-card.kpi-info    .kpi-value { color: #60a5fa; }

    /* ── Sync status card ─────────────────────────────────────────────── */
    .sync-card {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        padding: 1rem 1.4rem;
        display: flex;
        flex-direction: column;
        gap: .65rem;
        backdrop-filter: blur(8px);
    }
    .sync-card.running  { border-color: rgba(59,130,246,.4); }
    .sync-card.stale    { border-color: rgba(251,146,60,.4); }
    .sync-card.fresh    { border-color: rgba(74,222,128,.2); }
    .sync-card.error    { border-color: rgba(239,68,68,.4); }

    .sync-card-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: .5rem;
    }
    .sync-card-title {
        display: flex; align-items: center; gap: .55rem;
        font-size: .82rem; font-weight: 700;
        color: rgba(255,255,255,.85); text-transform: uppercase; letter-spacing: .05em;
    }
    .sync-dot {
        width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
    }
    .sync-dot.fresh   { background: #4ade80; box-shadow: 0 0 0 2px rgba(74,222,128,.25); }
    .sync-dot.stale   { background: #fb923c; box-shadow: 0 0 0 2px rgba(251,146,60,.25); }
    .sync-dot.running { background: #60a5fa; animation: pulse-dot 1.2s ease-in-out infinite; }
    .sync-dot.error   { background: #f87171; box-shadow: 0 0 0 2px rgba(248,113,113,.25); }
    @keyframes pulse-dot {
        0%,100% { opacity: 1; }
        50%      { opacity: .35; }
    }

    .sync-rows {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: .5rem .75rem;
    }
    .sync-row {
        display: flex; flex-direction: column; gap: 2px;
    }
    .sync-row-label {
        font-size: .7rem; color: rgba(255,255,255,.4);
        text-transform: uppercase; letter-spacing: .05em; font-weight: 600;
    }
    .sync-row-value {
        font-size: .84rem; color: rgba(255,255,255,.85); font-weight: 500;
    }
    .sync-row-value.highlight { color: #60a5fa; }
    .sync-row-value.warn      { color: #fb923c; }
    .sync-row-value.success   { color: #4ade80; }
    .sync-row-value.danger    { color: #f87171; }

    .sync-progress {
        display: none;
        align-items: center; gap: .55rem;
        font-size: .8rem; color: #93c5fd;
        padding: .4rem .65rem;
        background: rgba(59,130,246,.1);
        border-radius: 6px;
        border: 1px solid rgba(59,130,246,.2);
    }
    .sync-progress.visible { display: flex; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .spin { display: inline-block; animation: spin .9s linear infinite; }

    .sync-error-msg {
        font-size: .78rem; color: #f87171;
        padding: .35rem .6rem;
        background: rgba(239,68,68,.1);
        border-radius: 6px; display: none;
    }
    .sync-error-msg.visible { display: block; }

    .btn-force-sync {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .42rem .9rem;
        border-radius: 8px; font-size: .82rem; font-weight: 600;
        cursor: pointer; border: none; transition: all .2s;
        background: linear-gradient(135deg,#f97316,#ea580c);
        color: #fff;
    }
    .btn-force-sync:hover:not(:disabled) { opacity: .88; }
    .btn-force-sync:disabled {
        opacity: .45; cursor: not-allowed;
    }

    .sync-strip {
        display: flex; align-items: center; gap: .5rem;
        font-size: .76rem; color: rgba(255,255,255,.45);
        padding: .5rem 0;
    }

    /* ── Iframe panel ─────────────────────────────────────────────────── */
    .grafana-panel {
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    .grafana-panel-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: .75rem 1.1rem;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: rgba(0,0,0,.15);
    }
    .grafana-panel-header .panel-title {
        font-size: .85rem; font-weight: 600; color: rgba(255,255,255,.85);
        display: flex; align-items: center; gap: .5rem;
    }
    .grafana-iframe-container {
        position: relative;
        width: 100%;
        /* 16:9 aspect ratio mínimo, ajustable */
        padding-bottom: min(62%, 820px);
        min-height: 600px;
        max-height: 90vh;
    }
    .grafana-iframe-container iframe {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        border: none;
        background: #0b1437;
    }
    .grafana-not-configured {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        min-height: 400px; gap: 1rem;
        text-align: center; color: rgba(255,255,255,.5);
    }
    .grafana-not-configured i {
        font-size: 3rem; color: rgba(249,115,22,.6);
    }
    .grafana-not-configured h3 {
        color: rgba(255,255,255,.7); font-size: 1.1rem;
    }
    .grafana-not-configured code {
        background: rgba(0,0,0,.3); padding: .2rem .5rem;
        border-radius: 4px; font-size: .82rem; color: #fb923c;
    }

    /* ── Setup card ──────────────────────────────────────────────────── */
    .setup-card {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(249,115,22,.2);
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
    }
    .setup-card h3 {
        color: #fb923c; font-size: 1rem; font-weight: 700;
        display: flex; align-items: center; gap: .5rem;
        margin-bottom: .75rem;
    }
    .setup-steps {
        display: flex; flex-direction: column; gap: .5rem;
    }
    .setup-step {
        display: flex; align-items: flex-start; gap: .75rem;
        font-size: .84rem; color: rgba(255,255,255,.7);
    }
    .step-num {
        width: 22px; height: 22px; border-radius: 50%;
        background: rgba(249,115,22,.2); border: 1px solid rgba(249,115,22,.4);
        color: #fb923c; font-size: .72rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; margin-top: 1px;
    }
    .setup-step code {
        background: rgba(0,0,0,.3);
        padding: .15rem .4rem; border-radius: 4px;
        font-size: .79rem; color: #93c5fd;
    }

    @media (max-width: 640px) {
        .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        .grafana-iframe-container { padding-bottom: 120%; min-height: 400px; }
    }

    /* ── Filters Bar ──────────────────────────────────────────────────────── */
    .filters-bar {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        padding: .85rem 1.25rem;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        backdrop-filter: blur(8px);
    }
    .filter-group {
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .filter-group label {
        font-size: .72rem;
        color: rgba(255,255,255,.5);
        text-transform: uppercase;
        letter-spacing: .06em;
        font-weight: 600;
        white-space: nowrap;
    }
    .filter-select {
        background: rgba(0,0,0,.35);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 7px;
        color: #fff;
        font-size: .82rem;
        padding: .35rem 2rem .35rem .7rem;
        cursor: pointer;
        outline: none;
        min-width: 150px;
        max-width: 240px;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23ffffff80' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right .65rem center;
        transition: border-color .2s;
    }
    .filter-select:focus { border-color: rgba(249,115,22,.5); }
    .filter-select option { background: #1a1d2e; color: #fff; }

    /* ── Charts Grid ──────────────────────────────────────────────────────── */
    .charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .chart-card {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        overflow: hidden;
        backdrop-filter: blur(8px);
        transition: border-color .2s;
    }
    .chart-card:hover { border-color: rgba(249,115,22,.25); }
    .chart-card.chart-wide { grid-column: span 2; }
    .chart-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .7rem 1.1rem;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: rgba(0,0,0,.12);
        font-size: .81rem;
        font-weight: 600;
        color: rgba(255,255,255,.8);
    }
    .chart-body {
        padding: .75rem 1rem 1rem;
        position: relative;
        min-height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .chart-body canvas { width: 100% !important; }
    .chart-loading {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(10,14,40,.55);
        border-radius: 0 0 12px 12px;
        color: rgba(255,255,255,.45);
        font-size: .82rem;
        gap: .45rem;
        z-index: 5;
    }
    .chart-empty {
        color: rgba(255,255,255,.25);
        font-size: .82rem;
        text-align: center;
        padding: 2.5rem 1rem;
    }

    /* ── Table Card ──────────────────────────────────────────────────────── */
    .table-card {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        overflow: hidden;
        backdrop-filter: blur(8px);
    }
    .table-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .7rem 1.1rem;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: rgba(0,0,0,.12);
        font-size: .81rem;
        font-weight: 600;
        color: rgba(255,255,255,.8);
    }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .data-table thead th {
        padding: .55rem 1rem;
        text-align: left;
        font-size: .69rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: rgba(255,255,255,.4);
        border-bottom: 1px solid rgba(255,255,255,.06);
        white-space: nowrap;
    }
    .data-table tbody tr {
        border-bottom: 1px solid rgba(255,255,255,.04);
        transition: background .12s;
    }
    .data-table tbody tr:hover { background: rgba(255,255,255,.04); }
    .data-table tbody td {
        padding: .55rem 1rem;
        color: rgba(255,255,255,.8);
        vertical-align: middle;
    }
    .badge-days {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: .71rem;
        font-weight: 700;
    }
    .badge-days.urgent { background: rgba(239,68,68,.18); color: #f87171; border: 1px solid rgba(239,68,68,.3); }
    .badge-days.warning { background: rgba(249,115,22,.18); color: #fb923c; border: 1px solid rgba(249,115,22,.3); }
    .badge-days.ok { background: rgba(34,197,94,.13); color: #4ade80; border: 1px solid rgba(34,197,94,.25); }

    @media (max-width: 768px) {
        .charts-grid { grid-template-columns: 1fr; }
        .chart-card.chart-wide { grid-column: span 1; }
        .filters-bar { flex-direction: column; align-items: flex-start; }
    }

    /* ── Grid 3 columnas para analytics compacto ─────────────────────── */
    .charts-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .85rem;
    }
    .charts-grid-3 .chart-card.span-2 { grid-column: span 2; }
    .charts-grid-3 .chart-card.span-3 { grid-column: span 3; }
    @media (max-width: 900px) {
        .charts-grid-3 { grid-template-columns: repeat(2, 1fr); }
        .charts-grid-3 .chart-card.span-2 { grid-column: span 2; }
        .charts-grid-3 .chart-card.span-3 { grid-column: span 2; }
    }
    @media (max-width: 600px) {
        .charts-grid-3 { grid-template-columns: 1fr; }
        .charts-grid-3 .chart-card.span-2,
        .charts-grid-3 .chart-card.span-3 { grid-column: span 1; }
    }

    /* ── Mini chart (altura reducida para mayor densidad) ─────────────── */
    .chart-body.chart-mini { min-height: 180px; }
    .chart-body.chart-xs   { min-height: 140px; }

    /* ── Sección separadora ───────────────────────────────────────────── */
    .section-divider {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .25rem 0;
    }
    .section-divider-label {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: rgba(255,255,255,.35);
        white-space: nowrap;
    }
    .section-divider-line {
        flex: 1;
        height: 1px;
        background: rgba(255,255,255,.07);
    }

    /* ── Mini tabla top ausentes ──────────────────────────────────────── */
    .mini-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
    .mini-table tr { border-bottom: 1px solid rgba(255,255,255,.05); }
    .mini-table tr:last-child { border-bottom: none; }
    .mini-table td { padding: .38rem .6rem; color: rgba(255,255,255,.8); vertical-align: middle; }
    .mini-table td:first-child { color: rgba(255,255,255,.4); font-size: .68rem; font-weight: 700; width: 22px; }
    .mini-table td:last-child { text-align: right; color: #fb923c; font-weight: 700; }
    .mini-bar {
        display: inline-block;
        height: 6px;
        border-radius: 3px;
        background: linear-gradient(90deg,#f97316,#ea580c);
        vertical-align: middle;
        margin-left: .4rem;
        opacity: .6;
    }

    /* ── Calendario de Renovaciones ──────────────────────────────────────────── */
    .cal-section {
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        overflow: hidden;
        backdrop-filter: blur(8px);
    }
    .cal-section-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: .7rem 1.1rem;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: rgba(0,0,0,.12);
        font-size: .81rem; font-weight: 600; color: rgba(255,255,255,.8);
        flex-wrap: wrap; gap: .5rem;
    }
    .cal-months-wrapper {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        padding: 1.25rem;
    }
    .cal-month-title {
        font-size: .84rem; font-weight: 700; color: rgba(255,255,255,.75);
        margin-bottom: .6rem; text-align: center; text-transform: capitalize;
    }
    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
    }
    .cal-dow {
        font-size: .62rem; font-weight: 700; color: rgba(255,255,255,.3);
        text-align: center; padding: .25rem 0;
    }
    .cal-cell {
        border-radius: 4px;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: .25rem 0; min-height: 34px;
        transition: background .15s;
    }
    .cal-cell:not(.cal-empty):not(.cal-past):hover { background: rgba(255,255,255,.07); cursor: default; }
    .cal-cell.cal-empty { pointer-events: none; }
    .cal-cell.cal-past .cal-day { opacity: .3; }
    .cal-cell.cal-today { background: rgba(249,115,22,.15); border: 1px solid rgba(249,115,22,.35); border-radius: 4px; }
    .cal-cell.cal-warm { background: rgba(251,146,60,.12); }
    .cal-cell.cal-hot  { background: rgba(239,68,68,.15); }
    .cal-day { font-size: .73rem; color: rgba(255,255,255,.65); line-height: 1; }
    .cal-badge {
        font-size: .58rem; font-weight: 800;
        padding: 1px 4px; border-radius: 8px; line-height: 1.4; margin-top: 1px;
    }
    .cal-warm .cal-badge { background: rgba(251,146,60,.3); color: #fb923c; }
    .cal-hot  .cal-badge { background: rgba(239,68,68,.25); color: #f87171; }
    .cal-legend {
        display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;
        font-size: .72rem; color: rgba(255,255,255,.4);
    }
    .cal-legend-dot {
        width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
    }
    @media (max-width: 900px) {
        .cal-months-wrapper { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .cal-months-wrapper { grid-template-columns: 1fr; }
        .cal-cell { min-height: 28px; }
    }
</style>
@endpush

@section('content')
<div class="grafana-wrapper">

    {{-- ── Cabecera ──────────────────────────────────────────────────────── --}}
    <div class="grafana-header">
        <div class="grafana-header-left">
            <div class="grafana-header-icon">
                <i class="bi bi-bar-chart-line-fill"></i>
            </div>
            <div>
                <h1>Analytics Talana <span class="badge-beta">Beta</span></h1>
                <div class="subtitle">Dashboard de RR.HH. en tiempo real · Solo superadministradores</div>
            </div>
        </div>

        <div class="grafana-actions">
            {{-- Trigger sync manual --}}
            <button id="btn-sync" class="btn-grafana btn-grafana-secondary" onclick="triggerSync()">
                <i class="bi bi-arrow-clockwise" id="sync-btn-icon"></i>
                <span id="sync-btn-label">Forzar sincronización</span>
            </button>

            {{-- Actualizar gráficos --}}
            <button class="btn-grafana btn-grafana-primary" onclick="loadCharts()">
                <i class="bi bi-arrow-repeat"></i> Actualizar gráficos
            </button>
        </div>
    </div>

    {{-- ── KPI Cards ─────────────────────────────────────────────────────── --}}
    <div class="kpi-grid" id="kpi-grid">
        <div class="kpi-card kpi-info">
            <div class="kpi-label">Trabajadores activos</div>
            <div class="kpi-value">{{ number_format($stats['total_trabajadores']) }}</div>
            <div class="kpi-sub">Sincronizados de Talana</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Contratos vigentes</div>
            <div class="kpi-value">{{ number_format($stats['contratos_vigentes']) }}</div>
            <div class="kpi-sub">{{ $stats['contratos_indefinidos'] }} indefinidos · {{ $stats['contratos_plazo_fijo'] }} plazo fijo</div>
        </div>
        <div class="kpi-card {{ $stats['proximos_vencer_7'] > 0 ? 'kpi-danger' : ($stats['proximos_vencer_30'] > 0 ? 'kpi-warning' : '') }}">
            <div class="kpi-label">Próximos a vencer</div>
            <div class="kpi-value">{{ number_format($stats['proximos_vencer_30']) }}</div>
            <div class="kpi-sub">
                @if($stats['proximos_vencer_7'] > 0)
                    <span style="color:#f87171;">⚠ {{ $stats['proximos_vencer_7'] }} vencen en 7 días</span>
                @else
                    En próximos 30 días
                @endif
            </div>
        </div>
        <div class="kpi-card {{ $stats['vencidos_activos'] > 0 ? 'kpi-danger' : 'kpi-success' }}">
            <div class="kpi-label">Vencidos activos</div>
            <div class="kpi-value">{{ number_format($stats['vencidos_activos']) }}</div>
            <div class="kpi-sub">{{ $stats['vencidos_activos'] > 0 ? 'Requieren atención' : 'Sin anomalías' }}</div>
        </div>
        <div class="kpi-card kpi-info">
            <div class="kpi-label">Marcas este mes</div>
            <div class="kpi-value">{{ number_format($stats['marcas_mes_actual']) }}</div>
            <div class="kpi-sub">{{ $stats['entradas_hoy'] }} entradas hoy</div>
        </div>
        <div class="kpi-card kpi-success">
            <div class="kpi-label">Asistencia 30 días</div>
            <div class="kpi-value">{{ number_format($stats['activos_con_marca_30d']) }}</div>
            <div class="kpi-sub">personas con marcas recientes</div>
        </div>
        <div class="kpi-card {{ $stats['proximos_vencer_90'] > 20 ? 'kpi-warning' : '' }}">
            <div class="kpi-label">Vencen en 90 días</div>
            <div class="kpi-value">{{ number_format($stats['proximos_vencer_90']) }}</div>
            <div class="kpi-sub">contratos plazo fijo</div>
        </div>

        {{-- ── RRHH: Vacaciones ──────────────────────────────────────── --}}
        @if($stats['total_vacaciones_dias'] > 0 || $stats['personas_sin_vacaciones'] >= 0)
        <div class="kpi-card kpi-info">
            <div class="kpi-label">Días vacaciones pendientes</div>
            <div class="kpi-value">{{ number_format($stats['total_vacaciones_dias']) }}</div>
            <div class="kpi-sub">{{ number_format($stats['personas_sin_vacaciones']) }} sin saldo acumulado</div>
        </div>
        @endif

        {{-- ── RRHH: Ausencias ──────────────────────────────────────── --}}
        @if($stats['ausencias_mes_actual'] > 0 || $stats['licencias_medicas_activas'] >= 0)
        <div class="kpi-card {{ $stats['ausencias_mes_actual'] > 10 ? 'kpi-warning' : '' }}">
            <div class="kpi-label">Ausencias este mes</div>
            <div class="kpi-value">{{ number_format($stats['ausencias_mes_actual']) }}</div>
            <div class="kpi-sub">aprobadas este mes</div>
        </div>
        <div class="kpi-card {{ $stats['licencias_medicas_activas'] > 5 ? 'kpi-warning' : '' }}">
            <div class="kpi-label">Licencias médicas activas</div>
            <div class="kpi-value">{{ number_format($stats['licencias_medicas_activas']) }}</div>
            <div class="kpi-sub">vigentes hoy</div>
        </div>
        @if($stats['faltas_injustificadas_30d'] > 0)
        <div class="kpi-card kpi-danger">
            <div class="kpi-label">Faltas injustificadas</div>
            <div class="kpi-value">{{ number_format($stats['faltas_injustificadas_30d']) }}</div>
            <div class="kpi-sub">últimos 30 días</div>
        </div>
        @endif
        @endif
    </div>

    {{-- ── Estado de sincronización ─────────────────────────────────────────── --}}
    @php
        $si = $syncInfo;
        $dotClass = $si['running'] ? 'running' : ($si['error'] ? 'error' : ($si['stale'] ? 'stale' : 'fresh'));
        $cardClass = $si['running'] ? 'running' : ($si['error'] ? 'error' : ($si['stale'] ? 'stale' : 'fresh'));
    @endphp
    <div class="sync-card {{ $cardClass }}" id="sync-card">

        <div class="sync-card-header">
            <div class="sync-card-title">
                <div class="sync-dot {{ $dotClass }}" id="sync-status-dot"></div>
                <span id="sync-status-label">
                    @if($si['running'])     Sincronización en curso...
                    @elseif($si['error'])   Error en la última sincronización
                    @elseif($si['stale'])   Datos desactualizados
                    @else                   Datos actualizados
                    @endif
                </span>
            </div>
            <button id="btn-force-sync" class="btn-force-sync"
                    onclick="triggerSync()"
                    @if($si['running']) disabled @endif>
                <i class="bi bi-arrow-clockwise" id="force-sync-icon"></i>
                <span id="force-sync-label">Forzar sincronización</span>
            </button>
        </div>

        {{-- Barra de progreso mientras corre --}}
        <div class="sync-progress {{ $si['running'] ? 'visible' : '' }}" id="sync-progress-bar">
            <i class="bi bi-arrow-repeat spin"></i>
            <span id="sync-progress-text">
                Sincronizando desde Talana... (puede tardar 1-3 minutos)
            </span>
        </div>

        {{-- Error --}}
        <div class="sync-error-msg {{ $si['error'] ? 'visible' : '' }}" id="sync-error-msg">
            {{ $si['error'] ?? '' }}
        </div>

        {{-- Detalle de sync por tabla --}}
        <div class="sync-rows">
            <div class="sync-row">
                <div class="sync-row-label">Contratos / Personas</div>
                <div class="sync-row-value {{ $si['stale'] ? 'warn' : 'success' }}" id="sync-last-contratos">
                    @if($si['last_contratos'])
                        {{ \Carbon\Carbon::parse($si['last_contratos'])->format('d/m/Y H:i') }}
                        &nbsp;<small style="opacity:.6;">({{ $si['last_contratos_human'] }})</small>
                    @else
                        <span class="warn">Sin sincronizar</span>
                    @endif
                </div>
            </div>
            <div class="sync-row">
                <div class="sync-row-label">Marcas de asistencia</div>
                <div class="sync-row-value {{ $si['last_marcas'] ? 'success' : 'warn' }}" id="sync-last-marcas">
                    @if($si['last_marcas'])
                        {{ \Carbon\Carbon::parse($si['last_marcas'])->format('d/m/Y H:i') }}
                        &nbsp;<small style="opacity:.6;">({{ $si['last_marcas_human'] }})</small>
                    @else
                        <span class="warn">Sin sincronizar</span>
                    @endif
                </div>
            </div>
            <div class="sync-row">
                <div class="sync-row-label">Próxima sincronización</div>
                <div class="sync-row-value highlight" id="sync-next">
                    06:00 AM
                    &nbsp;<small style="opacity:.6;">({{ $si['next_scheduled_human'] }})</small>
                </div>
            </div>
            <div class="sync-row">
                <div class="sync-row-label">Registros en base de datos</div>
                <div class="sync-row-value" id="sync-totals">
                    {{ number_format($si['total_personas']) }} personas ·
                    {{ number_format($si['total_contratos']) }} contratos ·
                    {{ number_format($si['total_marcas']) }} marcas
                </div>
            </div>
            <div class="sync-row">
                <div class="sync-row-label">RRHH (ausencias / vacaciones)</div>
                <div class="sync-row-value {{ $si['rrhh_error'] ? 'danger' : ($si['total_ausencias'] > 0 ? 'success' : 'warn') }}" id="sync-rrhh-totals">
                    @if($si['rrhh_error'])
                        <span class="danger">⚠ {{ $si['rrhh_error'] }}</span>
                    @elseif($si['total_ausencias'] > 0)
                        {{ number_format($si['total_ausencias']) }} ausencias · {{ number_format($si['total_saldo_vacaciones']) }} saldos vacac.
                        @if($si['rrhh_finished_at'])
                            &nbsp;<small style="opacity:.6;">({{ \Carbon\Carbon::parse($si['rrhh_finished_at'])->diffForHumans() }})</small>
                        @endif
                    @else
                        <span class="warn">Sin sincronizar — ejecutar <code>php artisan talana:sync-rrhh</code></span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filtros ──────────────────────────────────────────────────────────── --}}
    <div class="filters-bar">
        <div class="filter-group">
            <label>Centro de Costo</label>
            <select class="filter-select" id="f-centro" onchange="applyFilters()">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Tipo de Contrato</label>
            <select class="filter-select" id="f-tipo" onchange="applyFilters()">
                <option value="">Todos</option>
            </select>
        </div>
        <div id="charts-loader" style="margin-left:auto;display:none;align-items:center;gap:.4rem;font-size:.78rem;color:rgba(255,255,255,.4);">
            <i class="bi bi-arrow-repeat spin"></i> Actualizando...
        </div>
    </div>

    {{-- ── Gráficos ─────────────────────────────────────────────────────────── --}}
    <div class="charts-grid">

        {{-- Donut: Contratos por tipo --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-pie-chart-fill" style="color:#f97316;margin-right:.4rem;"></i>Contratos por Tipo</span>
            </div>
            <div class="chart-body" id="body-tipo">
                <canvas id="chart-tipo"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Barras H: Top centros de costo --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-bar-chart-horizontal-fill" style="color:#60a5fa;margin-right:.4rem;"></i>Top Centros de Costo</span>
            </div>
            <div class="chart-body" id="body-centros" style="min-height:260px;">
                <canvas id="chart-centros"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Barras V: Vencimientos 12 meses --}}
        <div class="chart-card chart-wide">
            <div class="chart-card-header">
                <span><i class="bi bi-calendar-x-fill" style="color:#f87171;margin-right:.4rem;"></i>Vencimientos — Próximos 12 Meses</span>
            </div>
            <div class="chart-body" id="body-venc" style="min-height:240px;">
                <canvas id="chart-venc"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Línea: Marcas por día --}}
        <div class="chart-card chart-wide">
            <div class="chart-card-header">
                <span><i class="bi bi-activity" style="color:#4ade80;margin-right:.4rem;"></i>Marcas de Asistencia — Últimos 30 Días</span>
            </div>
            <div class="chart-body" id="body-marcas" style="min-height:240px;">
                <canvas id="chart-marcas"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Barras H: Top cargos --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-briefcase-fill" style="color:#a78bfa;margin-right:.4rem;"></i>Top Cargos</span>
            </div>
            <div class="chart-body" id="body-cargos" style="min-height:260px;">
                <canvas id="chart-cargos"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Resumen numérico extra --}}
        <div class="chart-card" style="display:flex;flex-direction:column;justify-content:center;gap:.75rem;padding:1.25rem 1.4rem;">
            <div class="chart-card-header" style="margin:-1.25rem -1.4rem 0;padding:.7rem 1.1rem;">
                <span><i class="bi bi-clipboard2-data-fill" style="color:#fbbf24;margin-right:.4rem;"></i>Resumen Contratos Vigentes</span>
            </div>
            <div id="summary-list" style="display:flex;flex-direction:column;gap:.6rem;margin-top:.75rem;">
                <div style="color:rgba(255,255,255,.3);font-size:.82rem;text-align:center;padding:1rem;">
                    <i class="bi bi-arrow-repeat spin"></i> Cargando...
                </div>
            </div>
        </div>

        {{-- Barras H: Vencimientos por Centro de Costo (90d) --}}
        <div class="chart-card chart-wide">
            <div class="chart-card-header">
                <span><i class="bi bi-building-fill-exclamation" style="color:#f87171;margin-right:.4rem;"></i>Renovaciones por Centro de Costo — Próximos 90 Días</span>
            </div>
            <div class="chart-body" id="body-venc-centro" style="min-height:280px;">
                <canvas id="chart-venc-centro"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Donut: Ausencias por tipo --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-clipboard2-pulse-fill" style="color:#f472b6;margin-right:.4rem;"></i>Ausencias por Tipo — Últimos 12 Meses</span>
            </div>
            <div class="chart-body" id="body-ausencias-tipo">
                <canvas id="chart-ausencias-tipo"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Barras V: Ausencias por mes --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-calendar2-week-fill" style="color:#fbbf24;margin-right:.4rem;"></i>Ausentismo Mensual — Últimos 12 Meses</span>
            </div>
            <div class="chart-body" id="body-ausencias-mes" style="min-height:240px;">
                <canvas id="chart-ausencias-mes"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Barras V: Distribución de vacaciones --}}
        <div class="chart-card chart-wide">
            <div class="chart-card-header">
                <span><i class="bi bi-umbrella-fill" style="color:#34d399;margin-right:.4rem;"></i>Distribución de Saldo de Vacaciones</span>
            </div>
            <div class="chart-body" id="body-vacaciones-dist" style="min-height:220px;">
                <canvas id="chart-vacaciones-dist"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

    </div>

    {{-- ── ANALYTICS CRUZADO ─────────────────────────────────────────────────── --}}
    <div class="section-divider">
        <div class="section-divider-label"><i class="bi bi-diagram-3" style="margin-right:.35rem;"></i>Analytics Cruzado</div>
        <div class="section-divider-line"></div>
        <div style="font-size:.68rem;color:rgba(255,255,255,.25);">Relaciones entre tablas · JOIN personas × contratos × ausencias × vacaciones</div>
    </div>

    <div class="charts-grid-3">

        {{-- Correlación mensual: asistencia vs ausencias --}}
        <div class="chart-card span-2">
            <div class="chart-card-header">
                <span><i class="bi bi-graph-up-arrow" style="color:#a78bfa;margin-right:.4rem;"></i>Asistencia vs Ausentismo — Últimos 12 Meses</span>
                <span style="font-size:.7rem;color:rgba(255,255,255,.3);">personas únicas c/ marca × eventos de ausencia</span>
            </div>
            <div class="chart-body chart-mini" id="body-correlacion">
                <canvas id="chart-correlacion"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Marcas por día de semana --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-calendar-week" style="color:#60a5fa;margin-right:.4rem;"></i>Patrón Semanal de Asistencia</span>
            </div>
            <div class="chart-body chart-mini" id="body-dia-semana">
                <canvas id="chart-dia-semana"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Ausentismo días por centro de costo --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-building-x" style="color:#f87171;margin-right:.4rem;"></i>Ausentismo por Centro de Costo</span>
                <span style="font-size:.7rem;color:rgba(255,255,255,.3);">días totales · 12m</span>
            </div>
            <div class="chart-body chart-mini" id="body-ausencias-centro">
                <canvas id="chart-ausencias-centro"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Vacaciones pendientes por centro de costo --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-suitcase2" style="color:#34d399;margin-right:.4rem;"></i>Vacaciones Pendientes por Área</span>
                <span style="font-size:.7rem;color:rgba(255,255,255,.3);">días acumulados</span>
            </div>
            <div class="chart-body chart-mini" id="body-vac-centro">
                <canvas id="chart-vac-centro"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Headcount por tipo de contrato × centro de costo (stacked) --}}
        <div class="chart-card span-2">
            <div class="chart-card-header">
                <span><i class="bi bi-people-fill" style="color:#fbbf24;margin-right:.4rem;"></i>Headcount por Área y Tipo de Contrato</span>
                <span style="font-size:.7rem;color:rgba(255,255,255,.3);">solo contratos vigentes</span>
            </div>
            <div class="chart-body chart-mini" id="body-headcount">
                <canvas id="chart-headcount"></canvas>
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
            </div>
        </div>

        {{-- Top 10 ausentes --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <span><i class="bi bi-person-x-fill" style="color:#fb923c;margin-right:.4rem;"></i>Top 10 Ausentes</span>
                <span style="font-size:.7rem;color:rgba(255,255,255,.3);">últimos 12m · días totales</span>
            </div>
            <div class="chart-body chart-mini" id="body-top-ausentes" style="padding:.5rem .75rem;align-items:flex-start;">
                <div class="chart-loading"><i class="bi bi-arrow-repeat spin"></i>&nbsp;Cargando</div>
                <table class="mini-table" id="table-top-ausentes" style="display:none;"></table>
            </div>
        </div>

    </div>

    {{-- ── Calendario de Renovaciones ────────────────────────────────────────── --}}
    <div class="section-divider">
        <div class="section-divider-label"><i class="bi bi-calendar3" style="margin-right:.35rem;"></i>Contratos</div>
        <div class="section-divider-line"></div>
    </div>

    <div class="cal-section">
        <div class="cal-section-header">
            <span><i class="bi bi-calendar3" style="color:#60a5fa;margin-right:.4rem;"></i>Calendario de Renovaciones — Próximos 3 Meses</span>
            <div class="cal-legend">
                <div style="display:flex;align-items:center;gap:.35rem;"><div class="cal-legend-dot" style="background:rgba(251,146,60,.3);border:1px solid rgba(251,146,60,.5);"></div>1–9 contratos</div>
                <div style="display:flex;align-items:center;gap:.35rem;"><div class="cal-legend-dot" style="background:rgba(239,68,68,.25);border:1px solid rgba(239,68,68,.5);"></div>10+ contratos</div>
                <div style="display:flex;align-items:center;gap:.35rem;"><div class="cal-legend-dot" style="background:rgba(249,115,22,.15);border:1px solid rgba(249,115,22,.4);"></div>Hoy</div>
            </div>
        </div>
        <div class="cal-months-wrapper" id="cal-container">
            <div style="grid-column:1/-1;text-align:center;padding:2rem;color:rgba(255,255,255,.3);font-size:.82rem;">
                <i class="bi bi-arrow-repeat spin"></i> Cargando calendario...
            </div>
        </div>
    </div>

    {{-- ── Tabla: Contratos por vencer ──────────────────────────────────────── --}}
    <div class="table-card">
        <div class="table-card-header">
            <span><i class="bi bi-exclamation-triangle-fill" style="color:#f97316;margin-right:.4rem;"></i>Contratos por Vencer — Próximos 60 Días</span>
            <span id="table-count" style="font-size:.74rem;color:rgba(255,255,255,.4);font-weight:400;"></span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Trabajador</th>
                        <th>RUT</th>
                        <th>Cargo</th>
                        <th>Centro de Costo</th>
                        <th>Tipo</th>
                        <th>Vence</th>
                        <th>Días</th>
                    </tr>
                </thead>
                <tbody id="table-proximos-body">
                    <tr><td colspan="7" style="text-align:center;padding:2rem;color:rgba(255,255,255,.3);">
                        <i class="bi bi-arrow-repeat spin"></i> Cargando datos...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /grafana-wrapper --}}

{{-- Toast notification --}}
<div id="toast" style="
    position:fixed;bottom:1.5rem;right:1.5rem;
    background:rgba(15,20,50,.95);border:1px solid rgba(249,115,22,.4);
    color:#fff;padding:.75rem 1.25rem;border-radius:10px;
    font-size:.84rem;display:none;z-index:9999;
    backdrop-filter:blur(12px);box-shadow:0 4px 24px rgba(0,0,0,.5);
    max-width:320px;
"></div>
@endsection

@push('scripts')
<script>
const SYNC_URL        = '{{ route("grafana.sync") }}';
const SYNC_STATUS_URL = '{{ route("grafana.sync-status") }}';
const STATS_URL       = '{{ route("grafana.stats") }}';
const CSRF_TOKEN      = '{{ csrf_token() }}';

let syncPollingTimer  = null;
let isRunning         = {{ $syncInfo['running'] ? 'true' : 'false' }};

// ── Al cargar: si ya hay un sync corriendo, iniciar polling ───────────────
document.addEventListener('DOMContentLoaded', () => {
    if (isRunning) startPolling();
});

// ── Botón Forzar Sincronización ───────────────────────────────────────────
function triggerSync() {
    const btn       = document.getElementById('btn-force-sync');
    const icon      = document.getElementById('force-sync-icon');
    const label     = document.getElementById('force-sync-label');
    // También actualizar el botón del header si existe
    const btnHeader = document.getElementById('btn-sync');

    btn.disabled = true;
    icon.className = 'bi bi-arrow-repeat spin';
    label.textContent = 'Iniciando...';
    if (btnHeader) {
        btnHeader.disabled = true;
        document.getElementById('sync-btn-icon').className = 'bi bi-arrow-repeat spin';
        document.getElementById('sync-btn-label').textContent = 'Iniciando...';
    }

    fetch(SYNC_URL, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN':      CSRF_TOKEN,
            'Accept':            'application/json',
            'X-Requested-With':  'XMLHttpRequest',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast('✓ ' + data.message, 'success');
            setSyncRunning(true);
            startPolling();
        } else {
            showToast('⚠ ' + data.message, 'warn');
            setSyncRunning(data.running ?? false);
            resetSyncButton();
        }
    })
    .catch(() => {
        showToast('Error de red al iniciar sync', 'danger');
        resetSyncButton();
    });
}

function setSyncRunning(running) {
    isRunning = running;
    const dot      = document.getElementById('sync-status-dot');
    const card     = document.getElementById('sync-card');
    const progress = document.getElementById('sync-progress-bar');
    const label    = document.getElementById('sync-status-label');
    const btn      = document.getElementById('btn-force-sync');

    if (running) {
        dot.className      = 'sync-dot running';
        card.className     = 'sync-card running';
        progress.classList.add('visible');
        label.textContent  = 'Sincronización en curso...';
        btn.disabled       = true;
    } else {
        progress.classList.remove('visible');
        btn.disabled = false;
        resetSyncButton();
    }
}

function resetSyncButton() {
    const icon  = document.getElementById('force-sync-icon');
    const label = document.getElementById('force-sync-label');
    if (icon)  icon.className    = 'bi bi-arrow-clockwise';
    if (label) label.textContent = 'Forzar sincronización';

    const btnHeader = document.getElementById('btn-sync');
    if (btnHeader) {
        btnHeader.disabled = false;
        document.getElementById('sync-btn-icon').className  = 'bi bi-arrow-clockwise';
        document.getElementById('sync-btn-label').textContent = 'Forzar sincronización';
    }
}

// ── Polling de estado ─────────────────────────────────────────────────────
function startPolling() {
    if (syncPollingTimer) return; // ya está corriendo
    syncPollingTimer = setInterval(pollSyncStatus, 5000);
}

function stopPolling() {
    if (syncPollingTimer) {
        clearInterval(syncPollingTimer);
        syncPollingTimer = null;
    }
}

function pollSyncStatus() {
    fetch(SYNC_STATUS_URL, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(si => {
        updateSyncCard(si);

        if (!si.running) {
            stopPolling();
            // Refrescar los KPIs una vez terminado
            fetchStats();
        }
    })
    .catch(() => {}); // silencioso si falla el poll
}

function updateSyncCard(si) {
    const dotClass  = si.running ? 'running' : (si.error ? 'error' : (si.stale ? 'stale' : 'fresh'));
    const cardClass = si.running ? 'running' : (si.error ? 'error' : (si.stale ? 'stale' : 'fresh'));

    const dot      = document.getElementById('sync-status-dot');
    const card     = document.getElementById('sync-card');
    const label    = document.getElementById('sync-status-label');
    const progress = document.getElementById('sync-progress-bar');
    const errMsg   = document.getElementById('sync-error-msg');

    if (dot)  dot.className   = 'sync-dot ' + dotClass;
    if (card) card.className  = 'sync-card ' + cardClass;

    if (label) {
        label.textContent = si.running ? 'Sincronización en curso...'
            : si.error   ? 'Error en la última sincronización'
            : si.stale   ? 'Datos desactualizados'
            : 'Datos actualizados';
    }
    if (progress) progress.classList.toggle('visible', si.running);
    if (errMsg) {
        errMsg.textContent = si.error ?? '';
        errMsg.classList.toggle('visible', !!si.error);
    }

    // Actualizar filas de detalle
    const elContratos = document.getElementById('sync-last-contratos');
    if (elContratos && si.last_contratos) {
        const dt = new Date(si.last_contratos);
        elContratos.innerHTML = formatDate(dt) + ' &nbsp;<small style="opacity:.6;">(' + (si.last_contratos_human ?? '') + ')</small>';
        elContratos.className = 'sync-row-value ' + (si.stale ? 'warn' : 'success');
    }

    const elMarcas = document.getElementById('sync-last-marcas');
    if (elMarcas && si.last_marcas) {
        const dt = new Date(si.last_marcas);
        elMarcas.innerHTML = formatDate(dt) + ' &nbsp;<small style="opacity:.6;">(' + (si.last_marcas_human ?? '') + ')</small>';
        elMarcas.className = 'sync-row-value ' + (si.last_marcas ? 'success' : 'warn');
    }

    const elNext = document.getElementById('sync-next');
    if (elNext && si.next_scheduled_human) {
        elNext.innerHTML = '06:00 AM &nbsp;<small style="opacity:.6;">(' + si.next_scheduled_human + ')</small>';
    }

    const elTotals = document.getElementById('sync-totals');
    if (elTotals) {
        elTotals.textContent = formatNum(si.total_personas) + ' personas · '
            + formatNum(si.total_contratos) + ' contratos · '
            + formatNum(si.total_marcas) + ' marcas';
    }

    setSyncRunning(si.running);
}

// ── Actualizar KPIs vía AJAX ──────────────────────────────────────────────
function fetchStats() {
    fetch(STATS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (data.syncInfo) updateSyncCard(data.syncInfo);
            window.location.reload();
        })
        .catch(() => {});
}

// ── Helpers ───────────────────────────────────────────────────────────────
function formatDate(dt) {
    const d = String(dt.getDate()).padStart(2,'0');
    const m = String(dt.getMonth()+1).padStart(2,'0');
    const y = dt.getFullYear();
    const hh = String(dt.getHours()).padStart(2,'0');
    const mm = String(dt.getMinutes()).padStart(2,'0');
    return d+'/'+m+'/'+y+' '+hh+':'+mm;
}
function formatNum(n) {
    return Number(n).toLocaleString('es-CL');
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    const colors = {
        success: 'rgba(74,222,128,.5)',
        warn:    'rgba(249,115,22,.5)',
        danger:  'rgba(239,68,68,.5)',
    };
    t.style.borderColor = colors[type] ?? colors.warn;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 5000);
}
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin .9s linear infinite; }
</style>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// ─────────────────────────────────────────────────────────────────────────────
// Chart.js — Native Analytics Dashboard
// ─────────────────────────────────────────────────────────────────────────────
const CHARTS_URL = '{{ route("grafana.charts") }}';

const PALETTE = [
    '#f97316','#60a5fa','#4ade80','#f87171','#a78bfa',
    '#fbbf24','#34d399','#fb7185','#38bdf8','#c084fc',
    '#facc15','#2dd4bf','#e879f9','#a3e635','#f472b6',
];

Chart.defaults.color          = 'rgba(255,255,255,0.55)';
Chart.defaults.borderColor    = 'rgba(255,255,255,0.07)';
Chart.defaults.font.family    = "'Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.font.size      = 11;

const charts = {};

function showLoader(id, show) {
    const el = document.querySelector(`#body-${id} .chart-loading`);
    if (el) el.style.display = show ? 'flex' : 'none';
}

function destroyChart(id) {
    if (charts[id]) { charts[id].destroy(); delete charts[id]; }
}

function applyFilters() { loadCharts(); }

function loadCharts() {
    const centro = document.getElementById('f-centro').value;
    const tipo   = document.getElementById('f-tipo').value;
    const loader = document.getElementById('charts-loader');

    const params = new URLSearchParams();
    if (centro) params.append('centro_costo', centro);
    if (tipo)   params.append('tipo_contrato', tipo);

    const allChartIds = [
        'tipo','centros','venc','marcas','cargos','venc-centro',
        'ausencias-tipo','ausencias-mes','vacaciones-dist',
        'correlacion','dia-semana','ausencias-centro','vac-centro','headcount',
    ];

    loader.style.display = 'flex';
    allChartIds.forEach(id => showLoader(id, true));

    fetch(`${CHARTS_URL}?${params}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
        loader.style.display = 'none';
        allChartIds.forEach(id => showLoader(id, false));
        populateFilters(data.filters);
        renderTipo(data.contratos_por_tipo);
        renderCentros(data.por_centro_costo);
        renderVencimientos(data.vencimientos_por_mes);
        renderMarcas(data.marcas_por_dia, data.asistencia_diaria);
        renderCargos(data.cargos_top);
        renderSummary(data.contratos_por_tipo);
        renderTable(data.proximos_vencer);
        renderVencCentro(data.vencimientos_por_centro);
        renderCalendario(data.calendario_vencimientos);
        renderAusenciasTipo(data.ausencias_por_tipo);
        renderAusenciasMes(data.ausencias_por_mes);
        renderVacacionesDist(data.distribucion_vacaciones);
        // Analytics cruzados
        renderCorrelacion(data.correlacion_mensual);
        renderDiaSemana(data.marcas_dia_semana);
        renderAusenciasCentro(data.ausencias_por_centro);
        renderVacacionesCentro(data.vacaciones_por_centro);
        renderHeadcount(data.headcount_centro_tipo);
        renderTopAusentes(data.top_ausentes);
    })
    .catch(err => {
        loader.style.display = 'none';
        allChartIds.forEach(id => showLoader(id, false));
        console.error('Error cargando gráficos:', err);
        showToast('Error al cargar los gráficos', 'danger');
    });
}

// Poblar los <select> de filtros (solo en la primera carga)
function populateFilters(filters) {
    const centroSel = document.getElementById('f-centro');
    const tipoSel   = document.getElementById('f-tipo');
    const cVal = centroSel.value;
    const tVal = tipoSel.value;

    if (centroSel.options.length <= 1 && filters.centros_costo.length) {
        filters.centros_costo.forEach(c => centroSel.add(new Option(c, c)));
        if (cVal) centroSel.value = cVal;
    }
    if (tipoSel.options.length <= 1 && filters.tipos_contrato.length) {
        filters.tipos_contrato.forEach(t => tipoSel.add(new Option(t, t)));
        if (tVal) tipoSel.value = tVal;
    }
}

// ── Donut: Contratos por tipo ─────────────────────────────────────────────
function renderTipo(data) {
    destroyChart('tipo');
    const body = document.getElementById('body-tipo');
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin datos para mostrar</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-tipo"></canvas>';
    const total = data.reduce((a, d) => a + Number(d.total), 0);
    charts['tipo'] = new Chart(document.getElementById('chart-tipo'), {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.label || 'Sin tipo'),
            datasets: [{
                data: data.map(d => d.total),
                backgroundColor: PALETTE.slice(0, data.length),
                borderWidth: 2,
                borderColor: 'rgba(0,0,0,.35)',
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '60%',
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 11, padding: 10, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.raw} (${((Number(ctx.raw)/total)*100).toFixed(1)}%)`
                    }
                }
            }
        }
    });
}

// ── Barras H: Top centros de costo ───────────────────────────────────────
function renderCentros(data) {
    destroyChart('centros');
    const body = document.getElementById('body-centros');
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin datos para mostrar</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-centros" style="max-height:320px;"></canvas>';
    const height = Math.max(220, data.length * 30);
    body.style.minHeight = height + 'px';
    charts['centros'] = new Chart(document.getElementById('chart-centros'), {
        type: 'bar',
        data: {
            labels: data.map(d => truncate(d.label || 'Sin centro', 30)),
            datasets: [{
                label: 'Contratos',
                data: data.map(d => d.total),
                backgroundColor: 'rgba(96,165,250,0.65)',
                borderColor: '#60a5fa',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)' } },
                y: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,.72)', font: { size: 10.5 } } }
            }
        }
    });
}

// ── Barras V: Vencimientos 12 meses ─────────────────────────────────────
function renderVencimientos(data) {
    destroyChart('venc');
    const body = document.getElementById('body-venc');
    body.innerHTML = '<canvas id="chart-venc"></canvas>';
    // Construir todos los meses incluso si no hay datos
    const labels = [], counts = [];
    for (let i = 0; i < 12; i++) {
        const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() + i);
        const key   = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
        const label = d.toLocaleString('es', { month: 'short', year: '2-digit' });
        labels.push(label);
        const found = data ? data.find(r => r.label === key) : null;
        counts.push(found ? Number(found.total) : 0);
    }
    charts['venc'] = new Chart(document.getElementById('chart-venc'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Contratos que vencen',
                data: counts,
                backgroundColor: counts.map((v,i) => i < 2 ? 'rgba(248,113,113,.7)' : i < 4 ? 'rgba(251,146,60,.7)' : 'rgba(74,222,128,.5)'),
                borderColor:     counts.map((v,i) => i < 2 ? '#f87171'              : i < 4 ? '#fb923c'              : 'rgba(74,222,128,.8)'),
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.6)' } },
                y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)', stepSize: 1 }, beginAtZero: true }
            }
        }
    });
}

// ── Línea: Marcas por día (+ personas únicas) ────────────────────────────
function renderMarcas(data, asistData) {
    destroyChart('marcas');
    const body = document.getElementById('body-marcas');
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin marcas en los últimos 30 días</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-marcas"></canvas>';
    const labels = data.map(d => new Date(d.label + 'T00:00:00').toLocaleDateString('es', { day: '2-digit', month: 'short' }));
    const asistMap = {};
    (asistData || []).forEach(d => { asistMap[d.label] = Number(d.total); });
    const personasVals = data.map(d => asistMap[d.label] ?? null);
    charts['marcas'] = new Chart(document.getElementById('chart-marcas'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Total marcas',
                    data: data.map(d => d.total),
                    borderColor: '#4ade80',
                    backgroundColor: 'rgba(74,222,128,0.08)',
                    borderWidth: 2, tension: 0.35, fill: true,
                    pointRadius: 2, pointHoverRadius: 5, pointBackgroundColor: '#4ade80',
                    yAxisID: 'y',
                },
                {
                    label: 'Personas únicas',
                    data: personasVals,
                    borderColor: '#60a5fa',
                    backgroundColor: 'rgba(96,165,250,0)',
                    borderWidth: 2, borderDash: [5,3],
                    tension: 0.35, fill: false,
                    pointRadius: 2, pointHoverRadius: 5, pointBackgroundColor: '#60a5fa',
                    yAxisID: 'y2',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { boxWidth: 10, padding: 12, font: { size: 11 } } }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: 'rgba(255,255,255,.5)', maxTicksLimit: 16 } },
                y:  { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: '#4ade80' }, beginAtZero: true, position: 'left' },
                y2: { grid: { display: false }, ticks: { color: '#60a5fa' }, beginAtZero: true, position: 'right' }
            }
        }
    });
}

// ── Barras H: Top cargos ─────────────────────────────────────────────────
function renderCargos(data) {
    destroyChart('cargos');
    const body = document.getElementById('body-cargos');
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin datos para mostrar</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-cargos" style="max-height:320px;"></canvas>';
    const height = Math.max(220, data.length * 30);
    body.style.minHeight = height + 'px';
    charts['cargos'] = new Chart(document.getElementById('chart-cargos'), {
        type: 'bar',
        data: {
            labels: data.map(d => truncate(d.label || 'Sin cargo', 30)),
            datasets: [{
                label: 'Trabajadores',
                data: data.map(d => d.total),
                backgroundColor: 'rgba(167,139,250,0.65)',
                borderColor: '#a78bfa',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)' } },
                y: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,.72)', font: { size: 10.5 } } }
            }
        }
    });
}

// ── Resumen numérico ─────────────────────────────────────────────────────
function renderSummary(data) {
    const el = document.getElementById('summary-list');
    if (!el) return;
    if (!data || !data.length) { el.innerHTML = '<div style="color:rgba(255,255,255,.25);font-size:.82rem;">Sin datos</div>'; return; }
    const total = data.reduce((a, d) => a + Number(d.total), 0);
    el.innerHTML = data.map((d, i) => {
        const pct = total ? ((Number(d.total)/total)*100).toFixed(0) : 0;
        return `
        <div style="display:flex;align-items:center;gap:.65rem;">
            <div style="width:10px;height:10px;border-radius:50%;background:${PALETTE[i] ?? '#888'};flex-shrink:0;"></div>
            <div style="flex:1;font-size:.82rem;color:rgba(255,255,255,.75);">${truncate(d.label || 'Sin tipo', 32)}</div>
            <div style="font-weight:700;color:#fff;font-size:.88rem;">${Number(d.total).toLocaleString('es-CL')}</div>
            <div style="font-size:.74rem;color:rgba(255,255,255,.38);min-width:36px;text-align:right;">${pct}%</div>
        </div>
        <div style="height:4px;background:rgba(255,255,255,.06);border-radius:2px;margin-left:1.5rem;">
            <div style="height:100%;width:${pct}%;background:${PALETTE[i] ?? '#888'};border-radius:2px;transition:width .5s;"></div>
        </div>`;
    }).join('');
}

// ── Tabla: Contratos por vencer ──────────────────────────────────────────
function renderTable(data) {
    const tbody = document.getElementById('table-proximos-body');
    const count = document.getElementById('table-count');
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:rgba(255,255,255,.3);">Sin contratos por vencer en los próximos 60 días</td></tr>';
        if (count) count.textContent = '';
        return;
    }
    if (count) count.textContent = `${data.length} contrato${data.length !== 1 ? 's' : ''}`;
    const today = new Date(); today.setHours(0,0,0,0);
    tbody.innerHTML = data.map(row => {
        const vence = new Date((row.hasta || '').substring(0, 10) + 'T00:00:00');
        const dias  = Math.ceil((vence - today) / 86400000);
        const cls   = dias <= 7 ? 'urgent' : dias <= 30 ? 'warning' : 'ok';
        const fecha = vence.toLocaleDateString('es-CL', { day:'2-digit', month:'short', year:'numeric' });
        return `<tr>
            <td><strong style="color:#fff;">${row.persona_nombre || '—'}</strong></td>
            <td style="font-family:monospace;font-size:.78rem;color:rgba(255,255,255,.5);">${row.persona_rut || '—'}</td>
            <td>${row.cargo_nombre || '—'}</td>
            <td style="color:rgba(255,255,255,.6);font-size:.78rem;">${row.centro_costo_nombre || '—'}</td>
            <td><span style="font-size:.73rem;color:rgba(255,255,255,.45);">${row.tipo_contrato_nombre || '—'}</span></td>
            <td style="white-space:nowrap;">${fecha}</td>
            <td><span class="badge-days ${cls}">${dias}d</span></td>
        </tr>`;
    }).join('');
}

// ── Helper ───────────────────────────────────────────────────────────────
function truncate(str, len) {
    return str && str.length > len ? str.slice(0, len) + '…' : (str || '');
}

// ── Barras H: Renovaciones por Centro de Costo (90d) ─────────────────────
function renderVencCentro(data) {
    destroyChart('venc-centro');
    const body = document.getElementById('body-venc-centro');
    if (!body) return;
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin contratos por vencer en los próximos 90 días</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-venc-centro" style="max-height:360px;"></canvas>';
    const height = Math.max(240, data.length * 30);
    body.style.minHeight = height + 'px';
    charts['venc-centro'] = new Chart(document.getElementById('chart-venc-centro'), {
        type: 'bar',
        data: {
            labels: data.map(d => truncate(d.label || 'Sin centro', 30)),
            datasets: [{
                label: 'Contratos por vencer',
                data: data.map(d => d.total),
                backgroundColor: 'rgba(248,113,113,0.6)',
                borderColor: '#f87171',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)' } },
                y: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,.72)', font: { size: 10.5 } } }
            }
        }
    });
}

// ── Calendario de renovaciones ────────────────────────────────────────────
function renderCalendario(data) {
    const el = document.getElementById('cal-container');
    if (!el) return;
    const lookup = {};
    (data || []).forEach(d => { lookup[d.fecha] = Number(d.total); });
    const today = new Date(); today.setHours(0,0,0,0);
    let html = '';
    for (let mo = 0; mo < 3; mo++) {
        const ref   = new Date(today.getFullYear(), today.getMonth() + mo, 1);
        const year  = ref.getFullYear(), month = ref.getMonth();
        const label = ref.toLocaleString('es', { month: 'long', year: 'numeric' });
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDay    = (new Date(year, month, 1).getDay() + 6) % 7; // Mon=0
        const dowHeader   = ['Lu','Ma','Mi','Ju','Vi','Sá','Do'].map(d =>
            `<div class="cal-dow">${d}</div>`).join('');
        const emptyCells  = Array(firstDay).fill('<div class="cal-cell cal-empty"></div>').join('');
        const dayCells    = Array.from({length: daysInMonth}, (_, i) => {
            const day     = i + 1;
            const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const count   = lookup[dateStr] || 0;
            const dt      = new Date(year, month, day);
            const isToday = dt.getTime() === today.getTime();
            const isPast  = dt < today;
            const cls     = ['cal-cell',
                isPast  ? 'cal-past' : '',
                isToday ? 'cal-today' : '',
                !isPast && count >= 10 ? 'cal-hot'  : '',
                !isPast && count > 0 && count < 10 ? 'cal-warm' : '',
            ].filter(Boolean).join(' ');
            const tip   = count > 0 ? `: ${count} contrato${count > 1 ? 's' : ''} vence${count > 1 ? 'n' : ''}` : '';
            const badge = count > 0 ? `<span class="cal-badge">${count}</span>` : '';
            return `<div class="${cls}" title="${dateStr}${tip}"><span class="cal-day">${day}</span>${badge}</div>`;
        }).join('');
        html += `<div class="cal-month">
            <div class="cal-month-title">${label.charAt(0).toUpperCase() + label.slice(1)}</div>
            <div class="cal-grid">${dowHeader}${emptyCells}${dayCells}</div>
        </div>`;
    }
    el.innerHTML = html;
}

// ── Donut: Ausencias por tipo ─────────────────────────────────────────────
function renderAusenciasTipo(data) {
    destroyChart('ausencias-tipo');
    const body = document.getElementById('body-ausencias-tipo');
    if (!body) return;
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin datos de ausencias — ejecutar sync RRHH</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-ausencias-tipo"></canvas>';
    const total = data.reduce((a, d) => a + Number(d.total), 0);
    charts['ausencias-tipo'] = new Chart(document.getElementById('chart-ausencias-tipo'), {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.label || 'Sin tipo'),
            datasets: [{
                data: data.map(d => d.total),
                backgroundColor: ['#f472b6','#f87171','#fb923c','#fbbf24','#a78bfa','#60a5fa'],
                borderWidth: 2,
                borderColor: 'rgba(0,0,0,.35)',
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '58%',
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 11, padding: 10, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.raw} (${((Number(ctx.raw)/total)*100).toFixed(1)}%)`
                    }
                }
            }
        }
    });
}

// ── Barras V: Ausentismo mensual ──────────────────────────────────────────
function renderAusenciasMes(data) {
    destroyChart('ausencias-mes');
    const body = document.getElementById('body-ausencias-mes');
    if (!body) return;
    body.innerHTML = '<canvas id="chart-ausencias-mes"></canvas>';
    const labels = [], counts = [];
    for (let i = 11; i >= 0; i--) {
        const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - i);
        const key   = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
        const label = d.toLocaleString('es', { month: 'short', year: '2-digit' });
        labels.push(label);
        const found = data ? data.find(r => r.label === key) : null;
        counts.push(found ? Number(found.total) : 0);
    }
    charts['ausencias-mes'] = new Chart(document.getElementById('chart-ausencias-mes'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Ausencias aprobadas',
                data: counts,
                backgroundColor: 'rgba(251,191,36,0.55)',
                borderColor: '#fbbf24',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.6)' } },
                y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)', stepSize: 1 }, beginAtZero: true }
            }
        }
    });
}

// ── Barras V: Distribución de vacaciones ─────────────────────────────────
function renderVacacionesDist(data) {
    destroyChart('vacaciones-dist');
    const body = document.getElementById('body-vacaciones-dist');
    if (!body) return;
    if (!data || !data.length || data.every(d => d.total === 0)) {
        body.innerHTML = '<div class="chart-empty">Sin datos de vacaciones — ejecutar sync RRHH</div>';
        return;
    }
    body.innerHTML = '<canvas id="chart-vacaciones-dist"></canvas>';
    const bgColors = ['rgba(248,113,113,.6)','rgba(251,146,60,.6)','rgba(251,191,36,.6)','rgba(74,222,128,.5)','rgba(52,211,153,.7)'];
    const bdColors = ['#f87171','#fb923c','#fbbf24','#4ade80','#34d399'];
    charts['vacaciones-dist'] = new Chart(document.getElementById('chart-vacaciones-dist'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.label),
            datasets: [{
                label: 'Empleados',
                data: data.map(d => d.total),
                backgroundColor: bgColors.slice(0, data.length),
                borderColor: bdColors.slice(0, data.length),
                borderWidth: 1,
                borderRadius: 5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.65)' } },
                y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)', stepSize: 1 }, beginAtZero: true }
            }
        }
    });
}

// ═══════════════════════════════════════════════════════════════════════════
// ANALYTICS CRUZADOS
// ═══════════════════════════════════════════════════════════════════════════

// ── Línea doble: Correlación asistencia vs ausencias mensual ─────────────
function renderCorrelacion(data) {
    destroyChart('correlacion');
    const body = document.getElementById('body-correlacion');
    if (!body) return;
    if (!data || !data.length) {
        body.innerHTML = '<div class="chart-empty">Sin datos cruzados</div>'; return;
    }
    body.innerHTML = '<canvas id="chart-correlacion"></canvas>';
    charts['correlacion'] = new Chart(document.getElementById('chart-correlacion'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.label),
            datasets: [
                {
                    label: 'Personas c/ asistencia',
                    data: data.map(d => d.asistencia),
                    type: 'line',
                    borderColor: '#60a5fa',
                    backgroundColor: 'rgba(96,165,250,.12)',
                    fill: true,
                    tension: .35,
                    pointRadius: 3,
                    borderWidth: 2,
                    yAxisID: 'yLeft',
                },
                {
                    label: 'Eventos de ausencia',
                    data: data.map(d => d.ausencias),
                    backgroundColor: 'rgba(248,113,113,.45)',
                    borderColor: '#f87171',
                    borderWidth: 1,
                    borderRadius: 3,
                    yAxisID: 'yRight',
                },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: 'rgba(255,255,255,.65)', boxWidth: 12, font: { size: 11 } } } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.55)', font: { size: 10 } } },
                yLeft: { position: 'left', grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: '#60a5fa', font: { size: 10 } }, beginAtZero: true },
                yRight: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#f87171', font: { size: 10 } }, beginAtZero: true },
            }
        }
    });
}

// ── Barras H: Patrón semanal de asistencia ───────────────────────────────
function renderDiaSemana(data) {
    destroyChart('dia-semana');
    const body = document.getElementById('body-dia-semana');
    if (!body) return;
    if (!data || !data.length) { body.innerHTML = '<div class="chart-empty">Sin datos</div>'; return; }
    body.innerHTML = '<canvas id="chart-dia-semana"></canvas>';
    const max = Math.max(...data.map(d => d.total)) || 1;
    charts['dia-semana'] = new Chart(document.getElementById('chart-dia-semana'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.label),
            datasets: [{
                data: data.map(d => d.total),
                backgroundColor: data.map(d =>
                    d.label === 'Lun' ? 'rgba(96,165,250,.7)' :
                    d.label === 'Vie' ? 'rgba(251,146,60,.65)' :
                    d.total === max   ? 'rgba(74,222,128,.6)' :
                    'rgba(255,255,255,.2)'),
                borderRadius: 4,
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: {
                callbacks: { label: ctx => ` ${ctx.raw.toLocaleString('es-CL')} entradas` }
            }},
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.6)', font: { size: 11 } } },
                y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.4)', font: { size: 10 } }, beginAtZero: true },
            }
        }
    });
}

// ── Barras H: Ausentismo por centro de costo ─────────────────────────────
function renderAusenciasCentro(data) {
    destroyChart('ausencias-centro');
    const body = document.getElementById('body-ausencias-centro');
    if (!body) return;
    if (!data || !data.length) { body.innerHTML = '<div class="chart-empty">Sin datos — sync RRHH pendiente</div>'; return; }
    body.innerHTML = '<canvas id="chart-ausencias-centro"></canvas>';
    charts['ausencias-centro'] = new Chart(document.getElementById('chart-ausencias-centro'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.label.length > 20 ? d.label.slice(0,18)+'…' : d.label),
            datasets: [{
                label: 'Días de ausencia',
                data: data.map(d => d.dias_total),
                backgroundColor: 'rgba(248,113,113,.5)',
                borderColor: '#f87171',
                borderWidth: 1,
                borderRadius: 3,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.4)', font: { size: 10 } }, beginAtZero: true },
                y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: 'rgba(255,255,255,.65)', font: { size: 10 } } },
            }
        }
    });
}

// ── Barras H: Vacaciones pendientes por área ─────────────────────────────
function renderVacacionesCentro(data) {
    destroyChart('vac-centro');
    const body = document.getElementById('body-vac-centro');
    if (!body) return;
    if (!data || !data.length) { body.innerHTML = '<div class="chart-empty">Sin datos — sync RRHH pendiente</div>'; return; }
    body.innerHTML = '<canvas id="chart-vac-centro"></canvas>';
    charts['vac-centro'] = new Chart(document.getElementById('chart-vac-centro'), {
        type: 'bar',
        data: {
            labels: data.map(d => d.label.length > 20 ? d.label.slice(0,18)+'…' : d.label),
            datasets: [
                {
                    label: 'Días pendientes',
                    data: data.map(d => d.dias_total),
                    backgroundColor: 'rgba(52,211,153,.55)',
                    borderColor: '#34d399',
                    borderWidth: 1,
                    borderRadius: 3,
                },
                {
                    label: 'Personas',
                    data: data.map(d => d.personas),
                    backgroundColor: 'rgba(251,191,36,.35)',
                    borderColor: '#fbbf24',
                    borderWidth: 1,
                    borderRadius: 3,
                },
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: 'rgba(255,255,255,.6)', boxWidth: 10, font: { size: 10 } } } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.4)', font: { size: 10 } }, beginAtZero: true },
                y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: 'rgba(255,255,255,.65)', font: { size: 10 } } },
            }
        }
    });
}

// ── Barras apiladas: Headcount por área × tipo contrato ──────────────────
function renderHeadcount(data) {
    destroyChart('headcount');
    const body = document.getElementById('body-headcount');
    if (!body) return;
    if (!data || !data.length) { body.innerHTML = '<div class="chart-empty">Sin datos</div>'; return; }
    body.innerHTML = '<canvas id="chart-headcount"></canvas>';

    const centros = [...new Set(data.map(d => d.centro))].slice(0, 12);
    const tipos   = [...new Set(data.map(d => d.tipo))];
    const palette = ['rgba(96,165,250,.7)','rgba(52,211,153,.7)','rgba(251,191,36,.7)','rgba(248,113,113,.7)','rgba(167,139,250,.7)','rgba(251,146,60,.7)'];

    const datasets = tipos.map((tipo, i) => ({
        label: tipo,
        data: centros.map(c => {
            const row = data.find(d => d.centro === c && d.tipo === tipo);
            return row ? row.total : 0;
        }),
        backgroundColor: palette[i % palette.length],
        borderRadius: 3,
        borderWidth: 0,
    }));

    charts['headcount'] = new Chart(document.getElementById('chart-headcount'), {
        type: 'bar',
        data: { labels: centros.map(c => c.length > 18 ? c.slice(0,16)+'…' : c), datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: 'rgba(255,255,255,.6)', boxWidth: 10, font: { size: 10 } } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                x: { stacked: true, grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.6)', font: { size: 10 } } },
                y: { stacked: true, grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.4)', font: { size: 10 } }, beginAtZero: true },
            }
        }
    });
}

// ── Mini tabla: Top 10 ausentes ──────────────────────────────────────────
function renderTopAusentes(data) {
    const loader = document.querySelector('#body-top-ausentes .chart-loading');
    const table  = document.getElementById('table-top-ausentes');
    if (!table) return;
    if (loader) loader.style.display = 'none';
    if (!data || !data.length) {
        table.style.display = 'none';
        const body = document.getElementById('body-top-ausentes');
        if (body) body.insertAdjacentHTML('beforeend', '<div class="chart-empty">Sin datos — sync RRHH pendiente</div>');
        return;
    }
    const max = Math.max(...data.map(d => d.dias_total)) || 1;
    table.style.display = 'table';
    table.innerHTML = data.map((d, i) => {
        const bar = Math.round((d.dias_total / max) * 60);
        const nombre = d.label.split(' ').slice(0, 3).join(' ');
        return `<tr>
            <td>${i + 1}</td>
            <td>${nombre}<span class="mini-bar" style="width:${bar}px"></span></td>
            <td>${d.dias_total}d <span style="color:rgba(255,255,255,.35);font-size:.68rem;">(${d.eventos})</span></td>
        </tr>`;
    }).join('');
}

// ── Carga inicial de gráficos ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadCharts();
});
</script>
@endpush
