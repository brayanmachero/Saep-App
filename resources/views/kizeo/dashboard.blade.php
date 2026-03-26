@extends('layouts.app')
@section('title', 'Dashboard PDR — Kizeo Analytics')
@section('content')
<div class="page-container">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-heading"><i class="bi bi-activity" style="color:var(--accent-color)"></i> Dashboard Prevención de Riesgos</h2>
            <p class="page-subheading">Indicadores en tiempo real desde Kizeo Forms</p>
            <p id="dashboard-cache-status" style="font-size:.68rem;color:var(--text-muted);margin:.15rem 0 0"></p>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            <div class="filter-form" style="margin:0;padding:0;background:none;border:none;box-shadow:none;display:flex;gap:.5rem;align-items:center">
                <input type="date" id="filter-start" class="form-input" style="width:140px;font-size:.82rem">
                <span style="color:var(--text-muted);font-size:.8rem">a</span>
                <input type="date" id="filter-end" class="form-input" style="width:140px;font-size:.82rem">
                <button onclick="loadDashboard()" class="btn-premium" style="padding:.45rem .85rem;font-size:.82rem">
                    <i class="bi bi-funnel-fill"></i> Filtrar
                </button>
                <button onclick="forceRefreshAll()" class="btn-ghost" style="padding:.45rem .65rem;font-size:.82rem" title="Forzar actualización completa desde Kizeo API">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button onclick="clearFilters()" class="btn-ghost" style="padding:.45rem .65rem;font-size:.82rem">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Loading --}}
    <div id="loading-zone" class="glass-card" style="text-align:center;padding:3rem">
        <div class="kizeo-spinner"></div>
        <p id="loading-text" style="color:var(--text-muted);margin-top:1rem;font-size:.9rem">Conectando con Kizeo Forms...</p>
    </div>

    {{-- Error --}}
    <div id="error-zone" class="glass-card" style="display:none;text-align:center;padding:2rem;border-left:4px solid var(--danger)">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:2rem;color:var(--danger)"></i>
        <p id="error-text" style="color:var(--danger);margin-top:.5rem;font-weight:600"></p>
        <button onclick="loadDashboard()" class="btn-ghost" style="margin-top:1rem"><i class="bi bi-arrow-clockwise"></i> Reintentar</button>
    </div>

    {{-- Dashboard Content --}}
    <div id="dashboard-content" style="display:none">

        {{-- KPIs --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:1rem;margin-bottom:1.5rem">
            <div class="glass-card kpi-card" style="padding:1rem 1.25rem;border-left:4px solid #3b82f6" data-kpi="total">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Total Registros</p>
                        <h2 id="kpi-total" style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1">0</h2>
                    </div>
                    <i class="bi bi-files" style="font-size:1.5rem;color:#93c5fd"></i>
                </div>
                <div class="kpi-tooltip" id="tooltip-total">Suma de todos los registros de formularios PDR en el periodo seleccionado.</div>
            </div>
            <div class="glass-card kpi-card" style="padding:1rem 1.25rem;border-left:4px solid #f97316" data-kpi="incidentes">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Incidentes</p>
                        <h2 id="kpi-incidentes" style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1;color:#f97316">0</h2>
                    </div>
                    <i class="bi bi-exclamation-triangle" style="font-size:1.5rem;color:#fdba74"></i>
                </div>
                <div class="kpi-tooltip" id="tooltip-incidentes">Registros de formularios que contienen "incidente" o "accidente" en su nombre.</div>
            </div>
            <div class="glass-card kpi-card" style="padding:1rem 1.25rem;border-left:4px solid #22c55e" data-kpi="charlas">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Charlas / Cap.</p>
                        <h2 id="kpi-charlas" style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1">0</h2>
                    </div>
                    <i class="bi bi-people-fill" style="font-size:1.5rem;color:#86efac"></i>
                </div>
                <div class="kpi-tooltip" id="tooltip-charlas">Formularios de charlas, capacitaciones y reuniones completadas.</div>
            </div>
            <div class="glass-card kpi-card" style="padding:1rem 1.25rem;border-left:4px solid #8b5cf6" data-kpi="inspecciones">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Inspecciones</p>
                        <h2 id="kpi-inspecciones" style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1">0</h2>
                    </div>
                    <i class="bi bi-search" style="font-size:1.5rem;color:#c4b5fd"></i>
                </div>
                <div class="kpi-tooltip" id="tooltip-inspecciones">Inspecciones, AST, visitas y observaciones de conducta.</div>
            </div>
            <div class="glass-card kpi-card" style="padding:1rem 1.25rem;border-left:4px solid #14b8a6" data-kpi="auditores">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Auditores Act.</p>
                        <h2 id="kpi-auditores" style="font-size:1.8rem;font-weight:800;margin:.15rem 0 0;line-height:1;color:#14b8a6">0</h2>
                    </div>
                    <i class="bi bi-person-badge-fill" style="font-size:1.5rem;color:#5eead4"></i>
                </div>
                <div class="kpi-tooltip" id="tooltip-auditores">Cantidad de usuarios distintos que han registrado datos en este periodo.</div>
            </div>
        </div>

        {{-- Gráficos fila 1 --}}
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem;min-width:0">
            <div class="glass-card" style="padding:1rem 1.25rem">
                <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem;font-weight:700">
                    <i class="bi bi-graph-up"></i> Curva de Actividad Diaria
                </h3>
                <div style="position:relative;height:260px">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="glass-card" style="padding:1rem 1.25rem">
                <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem;font-weight:700">
                    <i class="bi bi-pie-chart-fill"></i> Distribución por Formulario
                </h3>
                <div style="position:relative;height:260px">
                    <canvas id="distChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Gráficos fila 2 --}}
        <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1rem;margin-bottom:1.5rem;min-width:0">
            <div class="glass-card" style="padding:1rem 1.25rem">
                <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem;font-weight:700">
                    <i class="bi bi-trophy-fill"></i> Top 10 Auditores
                </h3>
                <div style="position:relative;height:300px">
                    <canvas id="auditorsChart"></canvas>
                </div>
            </div>
            <div class="glass-card" style="padding:1rem 1.25rem;max-height:370px;display:flex;flex-direction:column">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                    <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin:0">
                        <i class="bi bi-people"></i> Productividad por Auditor
                    </h3>
                    <span style="font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Seguimiento &amp; Inactividad</span>
                </div>
                <div class="glass-table-container" style="flex:1;overflow-y:auto">
                    <table class="glass-table" style="font-size:.8rem">
                        <thead>
                            <tr>
                                <th style="text-align:left">Inspector / Auditor</th>
                                <th style="text-align:center;width:100px">Documentos</th>
                                <th style="text-align:center;width:130px">Última Visita</th>
                                <th style="text-align:center;width:120px">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="auditors-table"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════ INDICADORES DE CUMPLIMIENTO ══════════ --}}
        <div id="compliance-section" style="display:none;margin-bottom:1.5rem">
            <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:.75rem">
                <i class="bi bi-shield-check" style="color:#22c55e"></i> Indicadores de Cumplimiento SST
            </h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem">
                {{-- Días sin accidentes --}}
                <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #22c55e;text-align:center">
                    <p style="font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Días sin Accidentes</p>
                    <h2 id="comp-dias-sin" style="font-size:2.5rem;font-weight:900;margin:.25rem 0 .15rem;line-height:1;color:#16a34a">0</h2>
                    <p id="comp-last-incident" style="font-size:.68rem;color:var(--text-muted);margin:0"></p>
                </div>
                {{-- Tasa de cobertura --}}
                <div class="glass-card" style="padding:1rem 1.25rem;border-left:4px solid #3b82f6">
                    <p style="font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;margin:0">Cobertura del Periodo</p>
                    <div style="display:flex;align-items:baseline;gap:.3rem;margin:.25rem 0 .15rem">
                        <h2 id="comp-coverage" style="font-size:2rem;font-weight:900;line-height:1;color:#2563eb;margin:0">0%</h2>
                    </div>
                    <p id="comp-coverage-detail" style="font-size:.68rem;color:var(--text-muted);margin:0"></p>
                    <div style="margin-top:.4rem;background:rgba(59,130,246,.1);border-radius:20px;height:6px;overflow:hidden">
                        <div id="comp-coverage-bar" style="height:100%;border-radius:20px;background:#3b82f6;transition:width .5s;width:0%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ CALENDARIO DE ACTIVIDADES SST ══════════ --}}
        <div id="calendar-section" style="display:none;margin-bottom:1.5rem">
            <div class="glass-card" style="padding:1.25rem;overflow:hidden">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem">
                    <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin:0">
                        <i class="bi bi-calendar3" style="color:#6366f1"></i> Calendario de Actividades SST
                    </h3>
                    <div style="display:flex;gap:.75rem;align-items:center;font-size:.68rem;color:var(--text-muted)">
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:.2rem"></span>Incidentes</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:.2rem"></span>Charlas</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#3b82f6;margin-right:.2rem"></span>Inspecciones</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f97316;margin-right:.2rem"></span>Visitas</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#94a3b8;margin-right:.2rem"></span>Otros</span>
                    </div>
                </div>
                {{-- Calendar navigation --}}
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                    <button onclick="calNav(-1)" class="btn-ghost" style="padding:.3rem .6rem;font-size:.8rem"><i class="bi bi-chevron-left"></i></button>
                    <h4 id="cal-month-label" style="font-size:.95rem;font-weight:700;margin:0;text-transform:capitalize"></h4>
                    <button onclick="calNav(1)" class="btn-ghost" style="padding:.3rem .6rem;font-size:.8rem"><i class="bi bi-chevron-right"></i></button>
                </div>
                {{-- Calendar grid --}}
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;font-size:.7rem">
                    <div class="cal-header">Lun</div>
                    <div class="cal-header">Mar</div>
                    <div class="cal-header">Mié</div>
                    <div class="cal-header">Jue</div>
                    <div class="cal-header">Vie</div>
                    <div class="cal-header" style="color:#ef4444">Sáb</div>
                    <div class="cal-header" style="color:#ef4444">Dom</div>
                </div>
                <div id="cal-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px"></div>
                {{-- Day detail popup --}}
                <div id="cal-detail" style="display:none;margin-top:.75rem;background:rgba(99,102,241,.04);border:1px solid rgba(99,102,241,.12);border-radius:8px;padding:.75rem 1rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                        <h4 id="cal-detail-title" style="font-size:.82rem;font-weight:700;margin:0;color:var(--primary-color)"></h4>
                        <button onclick="document.getElementById('cal-detail').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:.9rem"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div id="cal-detail-body" style="font-size:.78rem"></div>
                </div>
            </div>
        </div>

        {{-- ══════════ PANEL DE ALERTAS ══════════ --}}
        <div id="alerts-section" style="display:none;margin-bottom:1.5rem">
            <div class="glass-card" style="padding:1.25rem;overflow:hidden">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                    <h3 style="font-size:.82rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin:0">
                        <i class="bi bi-bell-fill" style="color:#f97316"></i> Alertas y Observaciones
                        <span id="alerts-count" class="badge warning" style="font-size:.65rem;margin-left:.3rem;vertical-align:middle"></span>
                    </h3>
                </div>
                <div id="alerts-container"></div>
                <div id="alerts-empty" style="display:none;text-align:center;padding:1.5rem;color:var(--text-muted);font-style:italic">
                    <i class="bi bi-check-circle-fill" style="font-size:1.5rem;color:#22c55e;display:block;margin-bottom:.4rem"></i>
                    Sin alertas pendientes. ¡Todo en orden!
                </div>
            </div>
        </div>

        {{-- Deep Analytics: Auto-carga todos los formularios --}}
        <div class="glass-card" style="padding:1.25rem;margin-bottom:1.5rem;border-top:3px solid var(--primary-color);overflow:hidden">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;flex-wrap:wrap;gap:.75rem">
                <div>
                    <h3 style="font-size:.88rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin:0">
                        <i class="bi bi-search-heart"></i> Deep Analytics — Análisis Profundo
                    </h3>
                    <p id="deep-cache-status" style="font-size:.68rem;color:var(--text-muted);margin:.25rem 0 0"></p>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    <select id="deep-form-filter" class="form-input" style="width:200px;font-size:.8rem">
                        <option value="">Todos los formularios</option>
                    </select>
                    <input type="text" id="deep-search" class="form-input" placeholder="Buscar usuario, asistente, texto..." style="width:220px;font-size:.8rem" oninput="filterDeepTable()">
                    <button onclick="forceRefreshDeep()" class="btn-ghost" style="padding:.4rem .65rem;font-size:.82rem" title="Forzar actualización desde Kizeo API">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            {{-- Quick filter segmenters --}}
            <div id="deep-segmenters" style="display:none;margin-bottom:.75rem">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center">
                    <span style="font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-right:.25rem">Filtros rápidos:</span>
                    <button class="deep-seg-btn active" data-seg="all" onclick="applySegmenter('all')">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Todos
                    </button>
                    <button class="deep-seg-btn" data-seg="incidentes" onclick="applySegmenter('incidentes')">
                        <i class="bi bi-exclamation-triangle"></i> Incidentes
                    </button>
                    <button class="deep-seg-btn" data-seg="charlas" onclick="applySegmenter('charlas')">
                        <i class="bi bi-people-fill"></i> Charlas
                    </button>
                    <button class="deep-seg-btn" data-seg="inspecciones" onclick="applySegmenter('inspecciones')">
                        <i class="bi bi-search"></i> Inspecciones
                    </button>
                    <button class="deep-seg-btn" data-seg="visitas" onclick="applySegmenter('visitas')">
                        <i class="bi bi-geo-alt-fill"></i> Visitas
                    </button>
                    <button class="deep-seg-btn" data-seg="hoy" onclick="applySegmenter('hoy')">
                        <i class="bi bi-calendar-check"></i> Hoy
                    </button>
                    <button class="deep-seg-btn" data-seg="semana" onclick="applySegmenter('semana')">
                        <i class="bi bi-calendar-week"></i> Última semana
                    </button>
                </div>
            </div>

            {{-- Deep loading --}}
            <div id="deep-loading" style="display:none;text-align:center;padding:2rem">
                <div class="kizeo-spinner"></div>
                <p id="deep-loading-text" style="color:var(--text-muted);margin-top:.75rem;font-size:.85rem">Cargando análisis profundo de todos los formularios...</p>
            </div>

            {{-- Deep KPIs --}}
            <div id="deep-kpis" style="display:none;margin-bottom:.75rem">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem;margin-bottom:.75rem">
                    <div style="background:rgba(99,102,241,.06);border-radius:10px;padding:.6rem .85rem;border:1px solid rgba(99,102,241,.15)">
                        <p style="font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#6366f1;font-weight:700;margin:0">Registros</p>
                        <h2 id="deep-total" style="font-size:1.4rem;font-weight:800;margin:.1rem 0 0;color:#4338ca">0</h2>
                    </div>
                    <div style="background:rgba(168,85,247,.06);border-radius:10px;padding:.6rem .85rem;border:1px solid rgba(168,85,247,.15)">
                        <p style="font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#a855f7;font-weight:700;margin:0">Campos</p>
                        <h2 id="deep-fields" style="font-size:1.4rem;font-weight:800;margin:.1rem 0 0;color:#7c3aed">0</h2>
                    </div>
                    <div style="background:rgba(34,197,94,.06);border-radius:10px;padding:.6rem .85rem;border:1px solid rgba(34,197,94,.15)">
                        <p style="font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#22c55e;font-weight:700;margin:0">Formularios</p>
                        <h2 id="deep-forms-count" style="font-size:1.4rem;font-weight:800;margin:.1rem 0 0;color:#16a34a">0</h2>
                    </div>
                    <div style="background:rgba(249,115,22,.06);border-radius:10px;padding:.6rem .85rem;border:1px solid rgba(249,115,22,.15)">
                        <p style="font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#f97316;font-weight:700;margin:0">Mostrando</p>
                        <h2 id="deep-showing" style="font-size:1.4rem;font-weight:800;margin:.1rem 0 0;color:#ea580c">0</h2>
                    </div>
                </div>

                {{-- Form breakdown mini-badges --}}
                <div id="deep-form-breakdown" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.5rem"></div>
            </div>

            {{-- Results count --}}
            <div id="deep-results-info" style="display:none;font-size:.7rem;color:var(--text-muted);margin-bottom:.5rem">
                <span id="deep-results-text"></span>
            </div>

            {{-- Deep Table --}}
            <div id="deep-table-container" style="display:none">
                <div class="glass-table-container deep-table-scroll" style="max-height:550px;overflow:auto">
                    <table class="glass-table deep-sticky-table" style="font-size:.75rem;min-width:700px">
                        <thead id="deep-thead"></thead>
                        <tbody id="deep-tbody"></tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            <div id="deep-pagination" style="display:none;margin-top:.75rem;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
                <span id="deep-page-info" style="font-size:.72rem;color:var(--text-muted)"></span>
                <div style="display:flex;gap:.35rem">
                    <button onclick="deepPage('prev')" id="deep-prev-btn" class="btn-ghost" style="padding:.3rem .6rem;font-size:.75rem"><i class="bi bi-chevron-left"></i> Anterior</button>
                    <button onclick="deepPage('next')" id="deep-next-btn" class="btn-ghost" style="padding:.3rem .6rem;font-size:.75rem">Siguiente <i class="bi bi-chevron-right"></i></button>
                </div>
            </div>

            <div id="deep-empty" style="display:none;text-align:center;padding:2rem;color:var(--text-muted);font-style:italic">
                Sin registros profundos en este periodo.
            </div>
        </div>

    </div>
</div>

{{-- Slide-out Detail Panel --}}
<div id="slideout-overlay" class="slideout-overlay" onclick="closeSlideout()"></div>
<div id="slideout-panel" class="slideout-panel">
    <div class="slideout-header">
        <div>
            <h3 style="margin:0;font-size:1rem;font-weight:700"><i class="bi bi-journal-text" style="color:var(--accent-color)"></i> <span id="slideout-title">Detalle del Registro</span></h3>
            <p id="slideout-subtitle" style="margin:0;font-size:.75rem;color:var(--text-muted)"></p>
        </div>
        <button onclick="closeSlideout()" style="background:none;border:none;cursor:pointer;font-size:1.25rem;color:var(--text-muted);padding:.25rem"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="slideout-loading" style="display:none;text-align:center;padding:3rem">
        <div class="kizeo-spinner"></div>
        <p style="color:var(--text-muted);margin-top:1rem;font-size:.85rem">Cargando registro completo...</p>
    </div>
    <div id="slideout-body" class="slideout-body"></div>
</div>

{{-- Media Modal --}}
<div id="media-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:10001;justify-content:center;align-items:center;padding:2rem">
    <div class="glass-card" style="max-width:640px;width:100%;padding:1.25rem;position:relative">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;border-bottom:1px solid var(--border-color);padding-bottom:.5rem">
            <h3 style="margin:0;font-size:.95rem;font-weight:700"><i class="bi bi-camera-fill" style="color:var(--accent-color)"></i> Evidencia</h3>
            <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;font-size:1.25rem;color:var(--text-muted)"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="modal-content" style="min-height:200px;display:flex;align-items:center;justify-content:center;background:var(--bg-color);border-radius:8px">
            <div class="kizeo-spinner" id="modal-spinner"></div>
            <img id="modal-image" style="display:none;max-height:60vh;max-width:100%;object-fit:contain;border-radius:8px" src="" alt="Evidencia">
        </div>
    </div>
</div>

@push('styles')
<style>
.kizeo-spinner {
    width: 28px; height: 28px; border: 3px solid var(--border-color);
    border-top-color: var(--accent-color); border-radius: 50%;
    animation: kizeo-spin .8s linear infinite; margin: 0 auto;
}
@keyframes kizeo-spin { to { transform: rotate(360deg); } }

/* KPI Tooltips */
.kpi-card { position: relative; cursor: default; transition: transform .15s, box-shadow .15s; }
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.kpi-tooltip {
    display: none; position: absolute; bottom: -8px; left: 50%; transform: translate(-50%, 100%);
    background: #0f1b4c; color: #fff; font-size: .72rem; padding: .45rem .7rem; border-radius: 6px;
    white-space: nowrap; z-index: 100; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,.2);
    max-width: 280px; white-space: normal; text-align: center; line-height: 1.3;
}
.kpi-tooltip::before {
    content: ''; position: absolute; top: -5px; left: 50%; transform: translateX(-50%);
    border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 5px solid #0f1b4c;
}
.kpi-card:hover .kpi-tooltip { display: block; }

/* Slide-out Panel */
.slideout-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9998;
    transition: opacity .3s; opacity: 0;
}
.slideout-overlay.active { display: block; opacity: 1; }
.slideout-panel {
    position: fixed; top: 0; right: -620px; width: 580px; max-width: 92vw; height: 100vh;
    background: var(--card-bg, #fff); z-index: 9999; transition: right .35s cubic-bezier(.4,0,.2,1);
    box-shadow: -8px 0 30px rgba(0,0,0,.15); display: flex; flex-direction: column;
    border-left: 3px solid var(--accent-color);
}
.slideout-panel.active { right: 0; }
.slideout-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, rgba(15,27,76,.03), rgba(249,115,22,.03));
}
.slideout-body {
    flex: 1; overflow-y: auto; padding: 1.5rem 1.75rem;
}

/* Slide-out field rows */
.field-row {
    display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid rgba(0,0,0,.04);
    align-items: flex-start;
}
.field-row:last-child { border-bottom: none; }
.field-label {
    font-size: .72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .04em; min-width: 150px; flex-shrink: 0; padding-top: .1rem;
}
.field-value { font-size: .82rem; color: var(--text-color); word-break: break-word; flex: 1; }
.field-section {
    background: linear-gradient(135deg, rgba(15,27,76,.06), rgba(249,115,22,.04));
    padding: .5rem .75rem; border-radius: 6px; margin: .75rem 0 .25rem;
    font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
    color: var(--primary-color);
}
.field-media { max-width: 100%; max-height: 200px; border-radius: 8px; cursor: pointer; margin-top: .25rem; }

/* Clickable table rows */
.clickable-row { cursor: pointer; transition: background .15s; }
.clickable-row:hover { background: rgba(249,115,22,.06) !important; }
.clickable-row td:last-child { text-align: center; }

/* Deep Analytics Sticky Table */
.deep-table-scroll { position: relative; }
.deep-sticky-table thead th {
    position: sticky; top: 0; z-index: 10;
    background: var(--card-bg, #fff); box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
body.dark-mode .deep-sticky-table thead th { background: var(--card-bg, #1e293b); }

/* Segmenter buttons */
.deep-seg-btn {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .3rem .65rem; font-size: .7rem; font-weight: 600;
    border: 1px solid var(--border-color, #e2e8f0); border-radius: 20px;
    background: transparent; color: var(--text-muted); cursor: pointer;
    transition: all .15s; white-space: nowrap;
}
.deep-seg-btn:hover { border-color: var(--accent-color); color: var(--accent-color); }
.deep-seg-btn.active {
    background: var(--primary-color, #0f1b4c); color: #fff;
    border-color: var(--primary-color, #0f1b4c);
}

/* Calendar */
.cal-header { text-align:center; font-weight:700; font-size:.65rem; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); padding:.4rem 0; }
.cal-cell {
    min-height:52px; border:1px solid var(--border-color,#e2e8f0); border-radius:6px;
    padding:.2rem .3rem; cursor:pointer; transition:background .15s; position:relative;
    background:var(--card-bg,#fff);
}
.cal-cell:hover { background:rgba(99,102,241,.06); }
.cal-cell.cal-today { border-color:#6366f1; box-shadow:inset 0 0 0 1px #6366f1; }
.cal-cell.cal-empty { background:transparent; border-color:transparent; cursor:default; }
.cal-cell .cal-day { font-size:.7rem; font-weight:700; color:var(--text-color); margin-bottom:.15rem; }
.cal-cell .cal-dots { display:flex; flex-wrap:wrap; gap:2px; }
.cal-cell .cal-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }

/* Alert cards */
.alert-card {
    display:flex; gap:.75rem; padding:.75rem 1rem; border-radius:8px;
    margin-bottom:.5rem; border-left:4px solid transparent;
    background:rgba(99,102,241,.03); transition:background .15s; align-items:flex-start;
}
.alert-card:hover { background:rgba(99,102,241,.06); }
.alert-card.alert-danger { border-left-color:#ef4444; background:rgba(239,68,68,.04); }
.alert-card.alert-warning { border-left-color:#f59e0b; background:rgba(245,158,11,.04); }
.alert-card.alert-info { border-left-color:#3b82f6; background:rgba(59,130,246,.04); }
.alert-card.alert-success { border-left-color:#22c55e; background:rgba(34,197,94,.04); }
.alert-card .alert-icon { font-size:1rem; min-width:1.2rem; text-align:center; margin-top:.1rem; }
.alert-card .alert-body { flex:1; }
.alert-card .alert-title { font-weight:700; font-size:.78rem; margin:0 0 .15rem; }
.alert-card .alert-detail { font-size:.72rem; color:var(--text-muted); margin:0; }

body.dark-mode .cal-cell { background:var(--card-bg,#1e293b); border-color:rgba(255,255,255,.08); }
body.dark-mode .alert-card { background:rgba(255,255,255,.02); }
body.dark-mode .alert-card.alert-danger { background:rgba(239,68,68,.06); }
body.dark-mode .alert-card.alert-warning { background:rgba(245,158,11,.06); }
body.dark-mode .alert-card.alert-info { background:rgba(59,130,246,.06); }
body.dark-mode .alert-card.alert-success { background:rgba(34,197,94,.06); }
</style>
@endpush

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const CHART_COLORS = ['#0f1b4c','#f97316','#22c55e','#8b5cf6','#14b8a6','#3b82f6','#ef4444','#eab308','#ec4899','#6366f1'];

let trendChart = null, distChart = null, auditorsChart = null;
let dashboardForms = [];

document.addEventListener('DOMContentLoaded', () => {
    // Default: mes actual
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const lastDay = new Date(y, today.getMonth() + 1, 0).getDate();
    document.getElementById('filter-start').value = `${y}-${m}-01`;
    document.getElementById('filter-end').value = `${y}-${m}-${String(lastDay).padStart(2,'0')}`;
    loadDashboard();
});

function clearFilters() {
    document.getElementById('filter-start').value = '';
    document.getElementById('filter-end').value = '';
    loadDashboard();
}

function forceRefreshAll() {
    loadDashboard(true);
}

async function loadDashboard(forceRefresh = false) {
    const startDate = document.getElementById('filter-start').value;
    const endDate = document.getElementById('filter-end').value;

    document.getElementById('loading-zone').style.display = 'block';
    document.getElementById('dashboard-content').style.display = 'none';
    document.getElementById('error-zone').style.display = 'none';
    document.getElementById('loading-text').textContent = forceRefresh
        ? 'Actualizando datos desde Kizeo API (sin caché)...'
        : 'Conectando con Kizeo Forms...';

    try {
        const params = new URLSearchParams();
        if (startDate) params.set('start_date', startDate);
        if (endDate) params.set('end_date', endDate);
        if (forceRefresh) params.set('force_refresh', '1');

        const res = await fetch(`/kizeo/api/dashboard?${params}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.error || `HTTP ${res.status}`);
        }

        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Error desconocido');

        renderDashboard(json.data);
        document.getElementById('loading-zone').style.display = 'none';
        document.getElementById('dashboard-content').style.display = 'block';

    } catch (e) {
        document.getElementById('loading-zone').style.display = 'none';
        document.getElementById('error-zone').style.display = 'block';
        document.getElementById('error-text').textContent = e.message;
    }
}

function renderDashboard(data) {
    const s = data.stats;
    document.getElementById('kpi-total').textContent = s.total.toLocaleString();
    document.getElementById('kpi-incidentes').textContent = s.incidentes.toLocaleString();
    document.getElementById('kpi-charlas').textContent = s.charlas.toLocaleString();
    document.getElementById('kpi-inspecciones').textContent = s.inspecciones.toLocaleString();
    document.getElementById('kpi-auditores').textContent = s.auditores.toLocaleString();

    // Trend chart
    const dailyLabels = Object.keys(data.dailyActivity);
    const dailyValues = Object.values(data.dailyActivity);
    renderTrend(dailyLabels, dailyValues);

    // Distribution chart
    const distLabels = data.formDistribution.map(f => f.label);
    const distValues = data.formDistribution.map(f => f.count);
    renderDist(distLabels, distValues);

    // Auditors chart + table
    const auditors = Object.entries(data.auditorsData);
    const top10 = auditors.slice(0, 10);
    renderAuditors(top10.map(a => a[0]), top10.map(a => a[1].count));
    renderAuditorsTable(auditors);

    // Store forms and auto-load deep analytics
    dashboardForms = data.forms || [];

    // Show cache timestamp on main dashboard
    if (data.cached_at) {
        document.getElementById('dashboard-cache-status').innerHTML =
            `<i class="bi bi-database-check" style="color:#22c55e"></i> Última actualización: ${data.cached_at} · TTL 4hrs · <a href="javascript:forceRefreshAll()" style="color:var(--accent-color);text-decoration:underline;font-weight:600">Forzar refresh</a>`;
    }

    // Auto-cargar deep analytics (todos los formularios)
    loadDeepDataAll();

    // ── New sections: compliance, calendar, alerts ──
    renderCompliance(data.compliance || {});
    renderCalendar(data.calendar || {});
    renderAlerts(data.alerts || []);
}

function renderTrend(labels, values) {
    if (trendChart) trendChart.destroy();
    trendChart = new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Registros',
                data: values,
                borderColor: '#0f1b4c',
                backgroundColor: 'rgba(15,27,76,.08)',
                borderWidth: 2, fill: true, tension: 0.35, pointRadius: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 15, font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { font: { size: 10 } } }
            }
        }
    });
}

function renderDist(labels, values) {
    if (distChart) distChart.destroy();
    distChart = new Chart(document.getElementById('distChart'), {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data: values, backgroundColor: CHART_COLORS }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } }
        }
    });
}

function renderAuditors(labels, values) {
    if (auditorsChart) auditorsChart.destroy();
    auditorsChart = new Chart(document.getElementById('auditorsChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ data: values, backgroundColor: '#22c55e', borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { font: { size: 10 } } },
                y: { ticks: { font: { size: 10 } } }
            }
        }
    });
}

function renderAuditorsTable(auditors) {
    const tbody = document.getElementById('auditors-table');
    tbody.innerHTML = '';

    // Determinar fecha más reciente del pool para calcular inactividad
    let maxDate = 0;
    auditors.forEach(([, d]) => {
        const t = new Date(d.lastDate).getTime();
        if (t > maxDate) maxDate = t;
    });

    auditors.forEach(([name, d]) => {
        const lastT = new Date(d.lastDate).getTime();
        const diffDays = Math.ceil(Math.abs(maxDate - lastT) / 86400000);
        const initials = name.substring(0, 2).toUpperCase();
        const shortForm = d.lastForm.length > 30 ? d.lastForm.substring(0, 30) + '…' : d.lastForm;

        let badge;
        if (diffDays >= 7) {
            badge = `<span class="badge danger" style="font-size:.68rem">Inactivo (${diffDays}d)</span>`;
        } else {
            badge = `<span class="badge success" style="font-size:.68rem">Activo</span>`;
        }

        tbody.innerHTML += `<tr>
            <td>
                <div style="display:flex;align-items:center;gap:.5rem">
                    <div style="width:24px;height:24px;border-radius:50%;background:rgba(249,115,22,.15);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;color:#f97316">${initials}</div>
                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="${name}">${name}</span>
                </div>
            </td>
            <td style="text-align:center;font-weight:600;color:#3b82f6">${d.count}</td>
            <td style="text-align:center">
                <div>${d.lastDate}</div>
                <div style="font-size:.68rem;color:var(--text-muted)" title="${d.lastForm}">${shortForm}</div>
            </td>
            <td style="text-align:center">${badge}</td>
        </tr>`;
    });

    if (auditors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:1.5rem;font-style:italic">Sin datos de auditores.</td></tr>';
    }
}

// ===== COMPLIANCE INDICATORS =====
function renderCompliance(c) {
    const sec = document.getElementById('compliance-section');
    if (!c || Object.keys(c).length === 0) { sec.style.display = 'none'; return; }
    sec.style.display = '';

    document.getElementById('comp-dias-sin').textContent = c.diasSinAccidente ?? '—';
    const lastInc = c.lastIncident;
    document.getElementById('comp-last-incident').textContent = lastInc
        ? `Último incidente: ${lastInc}` : 'Sin incidentes registrados en el periodo';

    const cov = c.coverageRate ?? 0;
    document.getElementById('comp-coverage').textContent = cov + '%';
    document.getElementById('comp-coverage-detail').textContent = `${c.activeDays ?? 0} días activos de ${c.totalDays ?? 0}`;
    document.getElementById('comp-coverage-bar').style.width = Math.min(cov, 100) + '%';
}

// ===== CALENDAR =====
let calEvents = [];
let calTypeByDay = {};
let calYear, calMonth;

function renderCalendar(cal) {
    const sec = document.getElementById('calendar-section');
    if (!cal || !cal.events) { sec.style.display = 'none'; return; }
    sec.style.display = '';

    calEvents = cal.events || [];
    calTypeByDay = cal.typeByDay || {};

    // Start at the month of the first event or current month
    const now = new Date();
    calYear = now.getFullYear();
    calMonth = now.getMonth();

    drawCalendar();
}

function calNav(dir) {
    calMonth += dir;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    if (calMonth < 0) { calMonth = 11; calYear--; }
    drawCalendar();
}

const CAL_TYPE_COLORS = { incidente:'#ef4444', charla:'#22c55e', inspeccion:'#3b82f6', visita:'#f97316', otro:'#94a3b8' };
const CAL_MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function drawCalendar() {
    document.getElementById('cal-month-label').textContent = `${CAL_MONTHS[calMonth]} ${calYear}`;
    document.getElementById('cal-detail').style.display = 'none';

    const grid = document.getElementById('cal-grid');
    grid.innerHTML = '';

    const first = new Date(calYear, calMonth, 1);
    const last = new Date(calYear, calMonth + 1, 0);
    let startDow = (first.getDay() + 6) % 7; // Monday = 0

    const today = new Date();
    const todayStr = today.toISOString().slice(0, 10);

    // Empty cells before first day
    for (let i = 0; i < startDow; i++) {
        grid.innerHTML += '<div class="cal-cell cal-empty"></div>';
    }

    for (let d = 1; d <= last.getDate(); d++) {
        const dateStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = dateStr === todayStr;
        const dayData = calTypeByDay[dateStr] || {};
        const totalForDay = Object.values(dayData).reduce((s,v) => s + v, 0);

        let dotsHtml = '';
        for (const [type, count] of Object.entries(dayData)) {
            const color = CAL_TYPE_COLORS[type] || '#94a3b8';
            for (let i = 0; i < Math.min(count, 5); i++) {
                dotsHtml += `<div class="cal-dot" style="background:${color}" title="${type}: ${count}"></div>`;
            }
            if (count > 5) dotsHtml += `<span style="font-size:.55rem;color:var(--text-muted)">+${count-5}</span>`;
        }

        grid.innerHTML += `<div class="cal-cell${isToday ? ' cal-today' : ''}" onclick="showCalDay('${dateStr}')" title="${totalForDay} actividades">
            <div class="cal-day">${d}</div>
            <div class="cal-dots">${dotsHtml}</div>
        </div>`;
    }
}

function showCalDay(dateStr) {
    const detail = document.getElementById('cal-detail');
    const body = document.getElementById('cal-detail-body');
    const dayEvents = calEvents.filter(e => e.date === dateStr);

    const parts = dateStr.split('-');
    const label = `${parseInt(parts[2])} de ${CAL_MONTHS[parseInt(parts[1])-1]} ${parts[0]}`;
    document.getElementById('cal-detail-title').textContent = label;

    if (dayEvents.length === 0) {
        body.innerHTML = '<p style="color:var(--text-muted);font-style:italic;margin:0">Sin actividades registradas este día.</p>';
    } else {
        body.innerHTML = dayEvents.map(ev => {
            const color = CAL_TYPE_COLORS[ev.category] || '#94a3b8';
            return `<div style="display:flex;align-items:center;gap:.5rem;padding:.3rem 0;border-bottom:1px solid var(--border-color,#e2e8f0)">
                <span style="width:8px;height:8px;border-radius:50%;background:${color};flex-shrink:0"></span>
                <span style="font-weight:600;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${ev.form}</span>
                <span style="color:var(--text-muted);font-size:.7rem;white-space:nowrap">${ev.user}</span>
            </div>`;
        }).join('');
    }

    detail.style.display = '';
}

// ===== ALERTS PANEL =====
function renderAlerts(alerts) {
    const sec = document.getElementById('alerts-section');
    if (!alerts) { sec.style.display = 'none'; return; }
    sec.style.display = '';

    const container = document.getElementById('alerts-container');
    const emptyEl = document.getElementById('alerts-empty');
    const countEl = document.getElementById('alerts-count');

    if (alerts.length === 0) {
        container.innerHTML = '';
        emptyEl.style.display = '';
        countEl.textContent = '';
        return;
    }

    emptyEl.style.display = 'none';
    countEl.textContent = alerts.length;

    const catMap = { danger:'alert-danger', warning:'alert-warning', info:'alert-info', success:'alert-success' };

    container.innerHTML = alerts.map(a => {
        const cls = catMap[a.category] || 'alert-info';
        return `<div class="alert-card ${cls}">
            <div class="alert-icon">${a.icon || '<i class="bi bi-info-circle"></i>'}</div>
            <div class="alert-body">
                <p class="alert-title">${a.title}</p>
                <p class="alert-detail">${a.detail}</p>
            </div>
        </div>`;
    }).join('');
}

// ===== DEEP ANALYTICS — AUTO-LOAD ALL FORMS =====

let allDeepRecords = []; // All deep records
let filteredDeepRecords = []; // After filters applied
let deepCurrentPage = 1;
const deepPageSize = 25;
let activeSegmenter = 'all';

async function loadDeepDataAll(forceRefresh = false) {
    const startDate = document.getElementById('filter-start').value;
    const endDate = document.getElementById('filter-end').value;

    document.getElementById('deep-loading').style.display = 'block';
    document.getElementById('deep-loading-text').textContent = forceRefresh
        ? 'Actualizando desde Kizeo API (sin caché)... esto puede tomar 1-2 minutos'
        : 'Cargando análisis profundo de todos los formularios... primera carga puede tomar 1-2 min';
    document.getElementById('deep-kpis').style.display = 'none';
    document.getElementById('deep-table-container').style.display = 'none';
    document.getElementById('deep-empty').style.display = 'none';
    document.getElementById('deep-cache-status').textContent = '';
    document.getElementById('deep-segmenters').style.display = 'none';

    try {
        const params = new URLSearchParams();
        if (startDate) params.set('start_date', startDate);
        if (endDate) params.set('end_date', endDate);
        if (forceRefresh) params.set('force_refresh', '1');

        const res = await fetch(`/kizeo/api/deep-all?${params}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        allDeepRecords = json.data.records || [];
        renderDeepAll(json.data);
    } catch (e) {
        document.getElementById('deep-empty').style.display = 'block';
        document.getElementById('deep-empty').textContent = 'Error al cargar deep analytics: ' + e.message;
    }

    document.getElementById('deep-loading').style.display = 'none';
}

function forceRefreshDeep() {
    loadDeepDataAll(true);
}

function renderDeepAll(data) {
    const records = data.records || [];
    const formStats = data.formStats || [];

    // Cache status
    if (data.cached_at) {
        document.getElementById('deep-cache-status').innerHTML =
            `<i class="bi bi-database-check" style="color:#22c55e"></i> Caché: ${data.cached_at} · TTL 30min · <a href="javascript:forceRefreshDeep()" style="color:var(--accent-color);text-decoration:underline;font-weight:600">Forzar refresh</a>`;
    }

    // Populate form filter dropdown
    const sel = document.getElementById('deep-form-filter');
    sel.innerHTML = '<option value="">Todos los formularios</option>';
    formStats.forEach(fs => {
        sel.innerHTML += `<option value="${fs.form_id}">${fs.form_name} (${fs.records})</option>`;
    });
    sel.onchange = () => { deepCurrentPage = 1; filterDeepTable(); };

    if (!records.length) {
        document.getElementById('deep-empty').style.display = 'block';
        document.getElementById('deep-empty').textContent = 'Sin registros profundos en este periodo.';
        return;
    }

    // KPIs
    document.getElementById('deep-total').textContent = records.length;
    document.getElementById('deep-fields').textContent = data.totalFields || 0;
    document.getElementById('deep-forms-count').textContent = formStats.length;
    document.getElementById('deep-kpis').style.display = 'block';

    // Show segmenters
    document.getElementById('deep-segmenters').style.display = 'block';

    // Form breakdown badges
    const breakdown = document.getElementById('deep-form-breakdown');
    breakdown.innerHTML = '';
    formStats.forEach(fs => {
        const isActive = document.getElementById('deep-form-filter').value === String(fs.form_id);
        breakdown.innerHTML += `<span onclick="quickFilterForm('${fs.form_id}')" style="cursor:pointer;background:${isActive ? 'rgba(249,115,22,.12)' : 'rgba(15,27,76,.06)'};border:1px solid ${isActive ? 'rgba(249,115,22,.3)' : 'rgba(15,27,76,.1)'};border-radius:20px;padding:.22rem .6rem;font-size:.68rem;font-weight:600;white-space:nowrap;transition:all .15s">
            ${fs.form_name} <span style="color:var(--accent-color);font-weight:800;margin-left:.15rem">${fs.records}</span>
        </span>`;
    });

    // Reset filters and render
    activeSegmenter = 'all';
    deepCurrentPage = 1;
    filterDeepTable();
}

function quickFilterForm(formId) {
    const sel = document.getElementById('deep-form-filter');
    sel.value = sel.value === formId ? '' : formId;
    deepCurrentPage = 1;
    filterDeepTable();
}

function applySegmenter(seg) {
    activeSegmenter = seg;
    deepCurrentPage = 1;
    // Update active button state
    document.querySelectorAll('.deep-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.seg === seg));
    filterDeepTable();
}

function filterDeepTable() {
    const formFilter = document.getElementById('deep-form-filter').value;
    const searchTerm = (document.getElementById('deep-search').value || '').toLowerCase().trim();
    const today = new Date().toISOString().split('T')[0];
    const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];

    let filtered = allDeepRecords;

    // Form filter
    if (formFilter) {
        filtered = filtered.filter(r => String(r._form_id) === formFilter);
    }

    // Segmenter filter
    if (activeSegmenter !== 'all') {
        filtered = filtered.filter(r => {
            const fname = (r._form_name || '').toLowerCase();
            const date = (r.update_time || r.create_time || '').split(' ')[0];
            switch (activeSegmenter) {
                case 'incidentes': return fname.includes('incidente') || fname.includes('accidente');
                case 'charlas': return fname.includes('charla') || fname.includes('reunión') || fname.includes('reunion') || fname.includes('cphs');
                case 'inspecciones': return fname.includes('inspección') || fname.includes('inspeccion') || fname.includes('ast') || fname.includes('observación') || fname.includes('observacion') || fname.includes('seguro');
                case 'visitas': return fname.includes('visita') || fname.includes('registro de visita');
                case 'hoy': return date === today;
                case 'semana': return date >= weekAgo;
                default: return true;
            }
        });
    }

    // Text search (includes field values AND attendee/signatory names)
    if (searchTerm) {
        filtered = filtered.filter(r => {
            const user = (r._user_display || r.user_name || '').toLowerCase();
            const form = (r._form_name || '').toLowerCase();
            const date = (r.update_time || r.create_time || '').toLowerCase();
            // Search in top-level field values
            let fieldText = '';
            const fields = r.fields || {};
            for (const k of Object.keys(fields)) {
                const v = fields[k]?.value;
                if (typeof v === 'string') fieldText += ' ' + v.toLowerCase();
            }
            // Search in attendee/signatory names (from sub-records)
            const attendees = (r._attendee_names || []).join(' ').toLowerCase();
            return user.includes(searchTerm) || form.includes(searchTerm) || date.includes(searchTerm) || fieldText.includes(searchTerm) || attendees.includes(searchTerm);
        });
    }

    filteredDeepRecords = filtered;

    // Update "showing" KPI
    document.getElementById('deep-showing').textContent = filtered.length;

    renderDeepTablePage();
}

function renderDeepTablePage() {
    const records = filteredDeepRecords;

    if (!records.length) {
        document.getElementById('deep-table-container').style.display = 'none';
        document.getElementById('deep-pagination').style.display = 'none';
        document.getElementById('deep-results-info').style.display = 'none';
        document.getElementById('deep-empty').style.display = 'block';
        document.getElementById('deep-empty').textContent = 'Sin registros para este filtro.';
        return;
    }

    document.getElementById('deep-empty').style.display = 'none';

    // Pagination calc
    const totalPages = Math.ceil(records.length / deepPageSize);
    if (deepCurrentPage > totalPages) deepCurrentPage = totalPages;
    if (deepCurrentPage < 1) deepCurrentPage = 1;
    const startIdx = (deepCurrentPage - 1) * deepPageSize;
    const pageRecords = records.slice(startIdx, startIdx + deepPageSize);

    // Detect common field keys across ALL filtered records (not just page)
    const fieldFreq = {};
    records.forEach(r => {
        Object.keys(r.fields || {}).forEach(k => {
            if (k.startsWith('_') || k === 'id') return;
            fieldFreq[k] = (fieldFreq[k] || 0) + 1;
        });
    });

    // Sort by frequency and take top 8
    const keys = Object.entries(fieldFreq)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 8)
        .map(e => e[0]);

    // Table headers — sticky
    const thead = document.getElementById('deep-thead');
    thead.innerHTML = `<tr>
        <th style="white-space:nowrap;font-size:.7rem;padding:.6rem .5rem">Fecha</th>
        <th style="font-size:.7rem;padding:.6rem .5rem">Formulario</th>
        <th style="font-size:.7rem;padding:.6rem .5rem">Usuario</th>
        <th style="text-align:center;font-size:.7rem;padding:.6rem .5rem;white-space:nowrap" title="Firmas / Asistentes"><i class="bi bi-pen"></i> Firmas</th>
        ${keys.map(k => `<th style="white-space:nowrap;font-size:.7rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;padding:.6rem .5rem" title="${formatFieldName(k)}">${formatFieldName(k)}</th>`).join('')}
        <th style="text-align:center;width:40px;font-size:.7rem;padding:.6rem .5rem"><i class="bi bi-eye"></i></th>
    </tr>`;

    // Table rows
    const tbody = document.getElementById('deep-tbody');
    tbody.innerHTML = '';

    pageRecords.forEach(r => {
        const fields = r.fields || {};
        const date = (r.update_time || r.create_time || '').split(' ')[0];
        const user = r._user_display || r.user_name || `ID-${r.user_id || '?'}`;
        const shortUser = user.length > 20 ? user.substring(0, 20) + '…' : user;
        const recordId = r.id || '';
        const formId = r._form_id || r.form_id || '';
        const formName = r._form_name || 'Formulario';
        const shortForm = formName.length > 20 ? formName.substring(0, 20) + '…' : formName;

        let cells = `<td style="white-space:nowrap;font-size:.73rem;padding:.5rem">${date}</td>`;
        cells += `<td style="padding:.5rem" title="${escapeHtml(formName)}"><span class="badge secondary" style="font-size:.62rem;white-space:nowrap">${escapeHtml(shortForm)}</span></td>`;
        cells += `<td style="white-space:nowrap;font-size:.73rem;padding:.5rem" title="${escapeHtml(user)}">${escapeHtml(shortUser)}</td>`;

        // Firmas / Asistentes column
        const fTotal = r._firmas_total || 0;
        const fSigned = r._firmas_signed || 0;
        const asist = r._asistentes || 0;
        let firmaCell = '';
        if (fTotal === 0 && asist === 0) {
            firmaCell = '<span style="color:var(--text-muted);font-size:.68rem">—</span>';
        } else {
            const pct = fTotal > 0 ? Math.round((fSigned / fTotal) * 100) : 0;
            const color = fTotal === 0 ? '#94a3b8' : fSigned === fTotal ? '#22c55e' : fSigned === 0 ? '#ef4444' : '#f59e0b';
            const icon = fSigned === fTotal ? 'check-circle-fill' : fSigned === 0 ? 'x-circle-fill' : 'exclamation-circle-fill';
            firmaCell = `<div style="text-align:center;line-height:1.2">`;
            firmaCell += `<i class="bi bi-${icon}" style="color:${color};font-size:.75rem" title="${fSigned}/${fTotal} firmados"></i>`;
            firmaCell += `<div style="font-size:.65rem;font-weight:700;color:${color}">${fSigned}/${fTotal}</div>`;
            if (asist > 0) firmaCell += `<div style="font-size:.58rem;color:var(--text-muted)">${asist} asist.</div>`;
            firmaCell += `</div>`;
        }
        cells += `<td style="padding:.5rem;text-align:center">${firmaCell}</td>`;

        keys.forEach(k => {
            const field = fields[k];
            let val = '<span style="color:var(--text-muted)">—</span>';
            if (field) {
                if (typeof field.value === 'string' || typeof field.value === 'number') {
                    let sv = String(field.value);
                    if (sv.match(/\.(jpg|jpeg|png|gif)$/i) || (field.type && field.type === 'photo')) {
                        val = `<button onclick="event.stopPropagation();showMedia('${formId}','${r.id}','${sv}')" class="btn-ghost" style="padding:.1rem .35rem;font-size:.65rem"><i class="bi bi-image"></i></button>`;
                    } else if (field.type === 'signature') {
                        val = `<button onclick="event.stopPropagation();showMedia('${formId}','${r.id}','${sv}')" class="btn-ghost" style="padding:.1rem .35rem;font-size:.65rem"><i class="bi bi-pen"></i></button>`;
                    } else if (sv.length > 28) {
                        val = `<span title="${escapeHtml(sv)}" style="font-size:.72rem">${escapeHtml(sv.substring(0, 28))}…</span>`;
                    } else {
                        val = `<span style="font-size:.72rem">${escapeHtml(sv)}</span>`;
                    }
                } else if (Array.isArray(field.value)) {
                    val = `<span class="badge info" style="font-size:.6rem">${field.value.length} items</span>`;
                } else if (typeof field.value === 'object' && field.value !== null) {
                    val = '<span class="badge secondary" style="font-size:.6rem">obj</span>';
                }
            }
            cells += `<td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;padding:.5rem">${val}</td>`;
        });

        const detailBtn = recordId
            ? `<button class="btn-ghost" style="padding:.1rem .35rem;font-size:.65rem" onclick="event.stopPropagation();openSlideout('${formId}','${recordId}','${escapeHtml(formName)}')"><i class="bi bi-eye"></i></button>`
            : '—';
        cells += `<td style="text-align:center;padding:.5rem">${detailBtn}</td>`;

        const clickAttr = recordId ? `onclick="openSlideout('${formId}','${recordId}','${escapeHtml(formName)}')"` : '';
        const rowClass = recordId ? 'class="clickable-row"' : '';
        tbody.innerHTML += `<tr ${rowClass} ${clickAttr}>${cells}</tr>`;
    });

    document.getElementById('deep-table-container').style.display = 'block';

    // Results info
    document.getElementById('deep-results-info').style.display = 'block';
    document.getElementById('deep-results-text').innerHTML =
        `Mostrando <strong>${startIdx + 1}–${Math.min(startIdx + deepPageSize, records.length)}</strong> de <strong>${records.length}</strong> registros` +
        (records.length !== allDeepRecords.length ? ` <span style="color:var(--accent-color)">(filtrado de ${allDeepRecords.length} total)</span>` : '');

    // Pagination
    if (totalPages > 1) {
        document.getElementById('deep-pagination').style.display = 'flex';
        document.getElementById('deep-page-info').textContent = `Página ${deepCurrentPage} de ${totalPages}`;
        document.getElementById('deep-prev-btn').disabled = deepCurrentPage <= 1;
        document.getElementById('deep-next-btn').disabled = deepCurrentPage >= totalPages;
    } else {
        document.getElementById('deep-pagination').style.display = 'none';
    }
}

function deepPage(dir) {
    const totalPages = Math.ceil(filteredDeepRecords.length / deepPageSize);
    if (dir === 'next' && deepCurrentPage < totalPages) deepCurrentPage++;
    if (dir === 'prev' && deepCurrentPage > 1) deepCurrentPage--;
    renderDeepTablePage();
    // Scroll table to top
    const container = document.querySelector('.deep-table-scroll');
    if (container) container.scrollTop = 0;
}

function formatFieldName(key) {
    return key.replace(/_+/g, ' ').replace(/^\s+|\s+$/g, '').replace(/\b\w/g, c => c.toUpperCase());
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MEDIA MODAL =====

async function showMedia(formId, recordId, mediaId) {
    const modal = document.getElementById('media-modal');
    modal.style.display = 'flex';
    document.getElementById('modal-spinner').style.display = 'block';
    document.getElementById('modal-image').style.display = 'none';

    try {
        const res = await fetch(`/kizeo/api/media/${formId}/${recordId}/${mediaId}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        const data = await res.json();
        document.getElementById('modal-spinner').style.display = 'none';
        if (data && data.base64) {
            const img = document.getElementById('modal-image');
            img.src = `data:${data.type};base64,${data.base64}`;
            img.style.display = 'block';
        } else {
            alert('Evidencia no disponible.');
            closeModal();
        }
    } catch (e) {
        alert('Error al cargar media.');
        closeModal();
    }
}

function closeModal() {
    document.getElementById('media-modal').style.display = 'none';
}

// ===== SLIDE-OUT DETAIL PANEL =====

async function openSlideout(formId, recordId, formName) {
    const overlay = document.getElementById('slideout-overlay');
    const panel = document.getElementById('slideout-panel');
    const body = document.getElementById('slideout-body');
    const loading = document.getElementById('slideout-loading');

    document.getElementById('slideout-title').textContent = formName || 'Detalle del Registro';
    document.getElementById('slideout-subtitle').textContent = `Registro #${recordId}`;
    body.innerHTML = '';
    loading.style.display = 'block';

    overlay.classList.add('active');
    panel.classList.add('active');
    document.body.style.overflow = 'hidden';

    try {
        const res = await fetch(`/kizeo/api/record/${formId}/${recordId}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Error desconocido');

        loading.style.display = 'none';
        renderSlideoutContent(json.record, formId);
    } catch (e) {
        loading.style.display = 'none';
        body.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--danger)">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:2rem"></i>
            <p style="margin-top:.75rem;font-weight:600">${escapeHtml(e.message)}</p>
        </div>`;
    }
}

function closeSlideout() {
    document.getElementById('slideout-overlay').classList.remove('active');
    document.getElementById('slideout-panel').classList.remove('active');
    document.body.style.overflow = '';
}

function renderSlideoutContent(record, formId) {
    const body = document.getElementById('slideout-body');
    let html = '';

    // Meta info card
    const createDate = record.create_time || '';
    const updateDate = record.update_time || '';
    const userName = record._user_display || record.user_name || `ID-${record.user_id}`;
    const firstName = record.first_name || '';
    const lastName = record.last_name || '';
    const fullName = [firstName, lastName].filter(Boolean).join(' ');

    html += `<div style="background:linear-gradient(135deg,rgba(15,27,76,.04),rgba(249,115,22,.03));border-radius:8px;padding:.85rem 1rem;margin-bottom:1rem;border:1px solid rgba(15,27,76,.08)">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
            <div><span style="font-size:.65rem;text-transform:uppercase;color:var(--text-muted);font-weight:700">Usuario</span><br><span style="font-size:.82rem;font-weight:600">${escapeHtml(fullName || userName)}</span></div>
            <div><span style="font-size:.65rem;text-transform:uppercase;color:var(--text-muted);font-weight:700">Record #</span><br><span style="font-size:.82rem;font-weight:600">${record.record_number || record.id}</span></div>
            <div><span style="font-size:.65rem;text-transform:uppercase;color:var(--text-muted);font-weight:700">Creado</span><br><span style="font-size:.82rem">${createDate}</span></div>
            <div><span style="font-size:.65rem;text-transform:uppercase;color:var(--text-muted);font-weight:700">Actualizado</span><br><span style="font-size:.82rem">${updateDate}</span></div>
        </div>
    </div>`;

    // Fields
    const fields = record.fields || {};
    const fieldEntries = Object.entries(fields).filter(([k]) => !k.startsWith('_') && k !== 'id');

    if (fieldEntries.length === 0) {
        html += '<p style="color:var(--text-muted);text-align:center;padding:1.5rem;font-style:italic">Sin campos disponibles para este registro.</p>';
    } else {
        fieldEntries.forEach(([key, field]) => {
            const type = field.type || '';
            const subtype = field.subtype || '';
            const value = field.value;

            // Section headers
            if (type === 'section' || type === 'separator') {
                html += `<div class="field-section"><i class="bi bi-dash-lg"></i> ${formatFieldName(key)}</div>`;
                return;
            }

            // Skip hidden fields
            if (field.hidden === true || field.hidden === 'true') return;

            const label = formatFieldName(key);
            let rendered = '';

            if (value === null || value === undefined || value === '') {
                rendered = '<span style="color:var(--text-muted);font-style:italic">Sin dato</span>';
            } else if (type === 'photo' || type === 'signature') {
                const mediaVal = typeof value === 'string' ? value : '';
                if (mediaVal) {
                    rendered = `<button onclick="showMedia('${formId}','${record.id}','${escapeHtml(mediaVal)}')" class="btn-premium" style="padding:.3rem .7rem;font-size:.75rem">
                        <i class="bi bi-${type === 'signature' ? 'pen' : 'camera'}"></i> ${type === 'signature' ? 'Ver firma' : 'Ver foto'}
                    </button>`;
                } else {
                    rendered = `<span class="badge secondary" style="font-size:.68rem">${type === 'signature' ? 'Firma' : 'Foto'} adjunta</span>`;
                }
            } else if (Array.isArray(value)) {
                if (value.length === 0) {
                    rendered = '<span style="color:var(--text-muted);font-style:italic">Lista vacía</span>';
                } else if (isSubRecordArray(value)) {
                    rendered = renderSubRecords(value, formId, record.id);
                } else {
                    rendered = '<ul style="margin:0;padding-left:1.2rem;font-size:.8rem">';
                    value.slice(0, 20).forEach(item => {
                        if (typeof item === 'object' && item !== null) {
                            rendered += `<li style="margin-bottom:.2rem">${escapeHtml(JSON.stringify(item))}</li>`;
                        } else {
                            rendered += `<li style="margin-bottom:.2rem">${escapeHtml(String(item))}</li>`;
                        }
                    });
                    if (value.length > 20) rendered += `<li style="color:var(--text-muted)">... y ${value.length - 20} más</li>`;
                    rendered += '</ul>';
                }
            } else if (typeof value === 'object' && value !== null) {
                if (isSubRecord(value)) {
                    rendered = renderSingleSubRecord(value, formId, record.id);
                } else {
                    rendered = `<pre style="font-size:.72rem;background:var(--bg-color);padding:.5rem;border-radius:6px;overflow-x:auto;margin:0">${escapeHtml(JSON.stringify(value, null, 2))}</pre>`;
                }
            } else {
                const strVal = String(value);
                // Check if it looks like a date
                if (strVal.match(/^\d{4}-\d{2}-\d{2}/)) {
                    rendered = `<span style="font-weight:500"><i class="bi bi-calendar-event" style="color:#3b82f6;margin-right:.3rem"></i>${escapeHtml(strVal)}</span>`;
                } else if (strVal.match(/^\d{2}:\d{2}/)) {
                    rendered = `<span style="font-weight:500"><i class="bi bi-clock" style="color:#8b5cf6;margin-right:.3rem"></i>${escapeHtml(strVal)}</span>`;
                } else {
                    rendered = strVal.length > 150 ? formatLongText(strVal) : escapeHtml(strVal);
                }
            }

            html += `<div class="field-row">
                <div class="field-label">${label}</div>
                <div class="field-value">${rendered}</div>
            </div>`;
        });
    }

    body.innerHTML = html;
}

// Close slideout with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeSlideout();
        closeModal();
    }
});

// ===== LONG TEXT FORMATTER =====

/**
 * Detect structured text from Kizeo forms and format it into readable HTML.
 * Handles numbered sections (1. 2. 3.), bullet markers, emoji markers, and long paragraphs.
 */
function formatLongText(text) {
    let safe = escapeHtml(text);

    // Split by numbered sections like "1." "2." etc at start or after space/newline
    // Also handle patterns like "1. Title text" where number starts a new topic
    safe = safe.replace(/(\d+)\.\s+/g, function(match, num) {
        return `</p><div style="margin:.5rem 0 .2rem;font-weight:700;color:var(--primary-color,#0f1b4c);font-size:.82rem"><span style="background:var(--primary-color,#0f1b4c);color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;font-size:.65rem;margin-right:.35rem">${num}</span>`;
    });

    // Close section divs (add closing after next numbered section or end)
    safe = safe.replace(/<\/div>(\s*<\/p>)/g, '</div>$1');

    // Highlight emoji markers as bullet-like items
    safe = safe.replace(/([\u2705\u274C\u2714\uFE0F\u26D4\uD83D\uDEAB\u{1F7E2}\u{1F534}\u{1F7E1}])\s*/gu, '<br><span style="margin-right:.25rem">$1</span>');

    // Convert dash bullets
    safe = safe.replace(/\s[-–—]\s/g, '<br><span style="color:var(--accent-color);margin-right:.3rem">•</span>');

    // Detect "Mensaje clave:" as highlight
    safe = safe.replace(/(Mensaje clave:?)/gi, '<br><strong style="color:#3b82f6;font-size:.78rem">💬 $1</strong>');

    // Words in ALL CAPS that look like warnings/labels (3+ capital letters)
    safe = safe.replace(/\b([A-ZÁÉÍÓÚÑ]{3,})\b/g, '<strong>$1</strong>');

    // Clean up leading empty <p> tags
    safe = safe.replace(/^<\/p>/, '');

    // Wrap in a styled container
    return `<div style="font-size:.8rem;line-height:1.65;max-height:400px;overflow-y:auto;padding:.75rem;background:var(--bg-color,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:8px">
        <p style="margin:0">${safe}</p>
    </div>`;
}

// ===== SUB-RECORD HELPERS (Kizeo table/list fields like Asistencia) =====

/**
 * Detect if an object is a Kizeo sub-record (its values are field objects with a 'value' key).
 */
function isSubRecord(obj) {
    if (typeof obj !== 'object' || obj === null || Array.isArray(obj)) return false;
    const keys = Object.keys(obj);
    if (keys.length === 0) return false;
    let fieldCount = 0;
    for (const k of keys) {
        const v = obj[k];
        if (typeof v === 'object' && v !== null && 'value' in v) fieldCount++;
    }
    return fieldCount >= Math.ceil(keys.length * 0.5);
}

/**
 * Detect if an array contains sub-records.
 */
function isSubRecordArray(arr) {
    if (!arr.length) return false;
    // Check first item
    return isSubRecord(arr[0]);
}

/**
 * Render a single sub-record as a mini card.
 */
function renderSingleSubRecord(obj, formId, recordId) {
    return renderSubRecordCard(obj, 0, formId, recordId);
}

/**
 * Render array of sub-records as a list of cards.
 */
function renderSubRecords(arr, formId, recordId) {
    let html = `<div style="font-size:.72rem;color:var(--text-muted);margin-bottom:.5rem;font-weight:600">${arr.length} registro${arr.length !== 1 ? 's' : ''}</div>`;
    arr.forEach((item, idx) => {
        html += renderSubRecordCard(item, idx, formId, recordId);
    });
    return html;
}

/**
 * Render one sub-record as a styled card.
 */
function renderSubRecordCard(obj, idx, formId, recordId) {
    const keys = Object.keys(obj);
    let cardHtml = `<div style="background:var(--bg-color,#f8fafc);border:1px solid var(--border-color,#e2e8f0);border-radius:8px;padding:.65rem .85rem;margin-bottom:.5rem;position:relative">`;
    cardHtml += `<div style="position:absolute;top:.4rem;right:.6rem;font-size:.62rem;font-weight:800;color:var(--text-muted);background:rgba(0,0,0,.04);border-radius:10px;padding:.1rem .45rem">#${idx + 1}</div>`;

    keys.forEach(fieldKey => {
        const fieldObj = obj[fieldKey];
        if (typeof fieldObj !== 'object' || fieldObj === null || !('value' in fieldObj)) return;

        const fType = fieldObj.type || '';
        const fValue = fieldObj.value;
        const fLabel = formatFieldName(fieldKey);

        // Skip hidden sub-fields
        if (fieldObj.hidden === true || fieldObj.hidden === 'true') return;

        let fRendered = '';

        if (fValue === null || fValue === undefined || fValue === '') {
            fRendered = '<span style="color:var(--text-muted);font-style:italic;font-size:.75rem">—</span>';
        } else if (fType === 'signature') {
            const mediaVal = typeof fValue === 'string' ? fValue : '';
            if (mediaVal) {
                fRendered = `<button onclick="event.stopPropagation();showMedia('${formId}','${recordId}','${escapeHtml(mediaVal)}')" class="btn-premium" style="padding:.2rem .55rem;font-size:.68rem">
                    <i class="bi bi-pen"></i> Ver firma
                </button>`;
            } else {
                fRendered = '<span class="badge secondary" style="font-size:.62rem">Firma adjunta</span>';
            }
        } else if (fType === 'photo') {
            const mediaVal = typeof fValue === 'string' ? fValue : '';
            if (mediaVal) {
                fRendered = `<button onclick="event.stopPropagation();showMedia('${formId}','${recordId}','${escapeHtml(mediaVal)}')" class="btn-premium" style="padding:.2rem .55rem;font-size:.68rem">
                    <i class="bi bi-camera"></i> Ver foto
                </button>`;
            } else {
                fRendered = '<span class="badge secondary" style="font-size:.62rem">Foto adjunta</span>';
            }
        } else if (fType === 'choice' && fieldObj.valuesAsArray && Array.isArray(fieldObj.valuesAsArray)) {
            fRendered = fieldObj.valuesAsArray.map(v => `<span class="badge info" style="font-size:.65rem;margin-right:.2rem">${escapeHtml(String(v))}</span>`).join('');
        } else if (Array.isArray(fValue)) {
            fRendered = fValue.map(v => escapeHtml(String(v))).join(', ');
        } else if (typeof fValue === 'object' && fValue !== null) {
            fRendered = `<span style="font-size:.72rem;color:var(--text-muted)">${escapeHtml(JSON.stringify(fValue))}</span>`;
        } else {
            fRendered = `<span style="font-size:.8rem">${escapeHtml(String(fValue))}</span>`;
        }

        cardHtml += `<div style="display:flex;gap:.5rem;align-items:baseline;padding:.2rem 0">
            <span style="font-size:.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;min-width:100px;flex-shrink:0">${fLabel}</span>
            <span style="flex:1">${fRendered}</span>
        </div>`;
    });

    cardHtml += '</div>';
    return cardHtml;
}
</script>
@endsection
