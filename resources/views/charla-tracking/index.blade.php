@extends('layouts.app')
@section('title', 'Seguimiento Charlas SST')
@section('content')
<div class="page-container charla-dashboard">
    @php
        $filterDisplayLabels = [
            'desde' => 'Desde',
            'hasta' => 'Hasta',
            'tipo_fecha' => 'Fecha usada',
            'estado' => 'Estado',
            'buscar' => 'Buscar',
        ];
        $activeFilterBadges = collect($filters ?? [])
            ->filter(fn ($value, $key) => $value !== null && $value !== '' && !($key === 'estado' && $value === 'todos'))
            ->map(fn ($value, $key) => ($filterDisplayLabels[$key] ?? $key) . ': ' . $value)
            ->values()
            ->all();
        $activeFilterSummary = !empty($activeFilterBadges)
            ? implode(' · ', $activeFilterBadges)
            : 'Sin filtros aplicados';
        $sendReportConfirm = '¿Enviar el reporte de Charlas SST a los destinatarios configurados con estos filtros? ' . $activeFilterSummary;
    @endphp

    {{-- Header --}}
    <div class="page-header charla-hero">
        <div>
            <span class="charla-eyebrow">PREVENCIÓN · SEGURIDAD Y SALUD</span>
            <h2 class="page-heading"><i class="bi bi-clipboard-data" style="color:var(--accent-color)"></i> Seguimiento Charlas de Seguridad</h2>
            <p class="page-subheading">
                Seguimiento de registros y respuestas del formulario PDR Charla de Seguridad en Kizeo.
                @if($ultimaSync)
                    <span style="font-size:.72rem;color:var(--text-muted);margin-left:.5rem">
                        <i class="bi bi-arrow-repeat"></i> Última sincronización: {{ \Carbon\Carbon::parse($ultimaSync)->diffForHumans() }}
                    </span>
                @endif
            </p>
        </div>
        <div class="charla-header-actions">
            <a href="{{ route('charla-tracking.email-preview', $filters ?? []) }}" target="_blank" class="btn-secondary" style="padding:.5rem 1rem;font-size:.82rem;text-decoration:none">
                <i class="bi bi-envelope-open"></i> Vista Previa Email
            </a>
            <form method="POST" action="{{ route('charla-tracking.send-report') }}" id="send-report-form" style="display:inline" onsubmit="return confirm(@js($sendReportConfirm))">
                @csrf
                @foreach(($filters ?? []) as $fk => $fv)
                    @if($fv !== null && $fv !== '')<input type="hidden" name="{{ $fk }}" value="{{ $fv }}">@endif
                @endforeach
                <button type="submit" class="btn-secondary" style="padding:.5rem 1rem;font-size:.82rem;background:#1e40af;color:#fff;border:none;cursor:pointer">
                    <i class="bi bi-send-fill"></i> Enviar Reporte Ahora
                </button>
            </form>
            <form method="POST" action="{{ route('charla-tracking.sync') }}" id="sync-form">
                @csrf
                <button type="submit" class="btn-premium" id="sync-btn" style="padding:.5rem 1rem;font-size:.82rem">
                    <i class="bi bi-arrow-clockwise" id="sync-icon"></i> Sincronizar desde Kizeo
                </button>
            </form>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('charla-tracking.index') }}" class="filter-form charla-filters">
        <div class="filter-group">
            <label for="tipo-fecha-charla">Analizar por</label>
            <select id="tipo-fecha-charla" name="tipo_fecha" class="form-input">
                <option value="registro" {{ $tipoFecha === 'registro' ? 'selected' : '' }}>Fecha de registro · histórico Kizeo</option>
                <option value="creacion" {{ $tipoFecha === 'creacion' ? 'selected' : '' }}>Fecha de creación · asignaciones</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}" class="form-input">
        </div>
        <div class="filter-group">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}" class="form-input">
        </div>
        <div class="filter-group">
            <label for="estado-charla">Estado actual</label>
            <select id="estado-charla" name="estado" class="form-input">
                <option value="todos" {{ $estado === 'todos' ? 'selected' : '' }}>Todos</option>
                <option value="completado" {{ $estado === 'completado' ? 'selected' : '' }}>Completado</option>
                <option value="pendiente" {{ $estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="transferido" {{ $estado === 'transferido' ? 'selected' : '' }}>Transferido</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Buscar</label>
            <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Usuario, título, lugar..." class="form-input">
        </div>
        <div class="filter-group" style="align-self:flex-end;display:flex;gap:.5rem">
            <button type="submit" class="btn-secondary"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            <a href="{{ route('charla-tracking.index') }}" class="btn-ghost"><i class="bi bi-x-circle"></i></a>
        </div>
    </form>

    <p style="font-size:.82rem;color:var(--text-muted);margin:.25rem 0 1rem">
        @if($esHistorico)
            Mostrando formularios registrados en Kizeo entre {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} y {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}, incluidos los transferidos pendientes. Este total es comparable con el histórico de Kizeo filtrado por fecha de registro.
        @else
            Mostrando formularios creados entre {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} y {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }} con su estado actual. Un formulario respondido después del período sigue perteneciendo a este grupo.
        @endif
    </p>

    <div class="glass-card charla-sync-status" style="padding:1rem 1.25rem;margin-bottom:1rem;border-left:4px solid {{ $sincronizacionAtrasada ? '#dc2626' : '#16a34a' }}">
        <strong>{{ $sincronizacionAtrasada ? 'Datos desactualizados' : 'Datos sincronizados' }}</strong>
        <span style="color:var(--text-muted);font-size:.83rem;margin-left:.5rem">
            {{ $ultimaSync ? 'Última sincronización completa: '.\Carbon\Carbon::parse($ultimaSync)->format('d/m/Y H:i') : 'Aún no hay una sincronización completa registrada.' }}
            @if($sincronizacionAtrasada) Los indicadores pueden ser menores que los de Kizeo hasta completar la sincronización. @endif
        </span>
    </div>

    <div class="glass-card charla-period-comparison" style="padding:1.2rem 1.35rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <strong style="display:block;font-size:.93rem">Resumen del período</strong>
                <span style="color:var(--text-muted);font-size:.8rem">El histórico de Kizeo usa la fecha de registro, incluso para transferencias pendientes. Las asignaciones usan la fecha de creación.</span>
            </div>
            <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
                <div><strong style="display:block;font-size:1.5rem;color:#15803d">{{ number_format($registradasPeriodo) }}</strong><small>Registradas en Kizeo</small></div>
                <div><strong style="display:block;font-size:1.5rem;color:#2563eb">{{ number_format($creadasPeriodo) }}</strong><small>Creadas en el período</small></div>
            </div>
        </div>
    </div>

    @if(!empty($activeFilterBadges))
    <div class="charla-active-filters">
        <span class="charla-active-filters-label">Filtros activos</span>
        @foreach($activeFilterBadges as $badge)
            <span class="charla-filter-chip">{{ $badge }}</span>
        @endforeach
    </div>
    @endif

    @if(isset($charlaActionLogs) && $charlaActionLogs->isNotEmpty())
    @php
        $actionLabels = [
            'sync' => 'Sincronizacion',
            'report_send_now' => 'Envio manual',
            'report_scheduled_send' => 'Envio programado',
        ];
        $statusLabels = [
            'success' => 'Correcto',
            'failed' => 'Error',
            'skipped' => 'Omitido',
            'partial' => 'Parcial',
            'queued' => 'En cola',
        ];
        $statusStyles = [
            'success' => 'background:#dcfce7;color:#166534',
            'failed' => 'background:#fee2e2;color:#991b1b',
            'skipped' => 'background:#fef3c7;color:#92400e',
            'partial' => 'background:#dbeafe;color:#1e40af',
        ];
    @endphp
    <details class="glass-card charla-audit">
        <summary>
            <div style="display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-shield-check"></i>
                <span>Actividad y auditoría</span>
                <span class="charla-audit-count">{{ $charlaActionLogs->count() }}</span>
            </div>
            <span class="charla-audit-toggle"><span class="audit-show">Ver acciones</span><span class="audit-hide">Ocultar acciones</span><i class="bi bi-chevron-down"></i></span>
        </summary>
        <div class="charla-activity-list">
            @foreach($charlaActionLogs as $log)
                <div class="charla-activity-row">
                    <div>
                        <strong style="display:block;font-size:.78rem;color:var(--text-primary)">{{ $actionLabels[$log->action] ?? $log->action }}</strong>
                        <span style="font-size:.68rem;color:var(--text-muted)">{{ $log->user?->name ?? 'Sistema' }}</span>
                    </div>
                    <span style="{{ $statusStyles[$log->status] ?? 'background:#f1f5f9;color:#334155' }};justify-self:start;border-radius:999px;padding:.16rem .5rem;font-size:.68rem;font-weight:800">
                        {{ $statusLabels[$log->status] ?? ucfirst($log->status) }}
                    </span>
                    <span class="charla-activity-summary">
                        {{ $log->summary ?: 'Accion registrada' }}
                    </span>
                    <time title="{{ optional($log->created_at)->format('d/m/Y H:i') }}" style="font-size:.68rem;color:var(--text-muted);white-space:nowrap">
                        {{ optional($log->created_at)->diffForHumans() }}
                    </time>
                </div>
            @endforeach
        </div>
    </details>
    @endif

    {{-- KPIs --}}
    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-item">
            <div class="stat-icon" style="background:rgba(59,130,246,.12);color:#3b82f6">
                <i class="bi bi-files"></i>
            </div>
            <div>
                <div class="stat-value">{{ number_format($total) }}</div>
                <div class="stat-label">{{ $esHistorico ? 'Registros Kizeo' : 'Creadas en el período' }}</div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#22c55e">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="stat-value" style="color:#15803d">{{ number_format($completadas) }}</div>
                <div class="stat-label">Completadas</div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon" style="background:rgba(249,115,22,.12);color:#f97316">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div>
                <div class="stat-value" style="color:#ea580c">{{ number_format($pendientes) }}</div>
                <div class="stat-label">Pendientes totales</div>
            </div>
        </div>
        <div class="stat-item">
            @php
                $tasaColor = '#7c3aed';
            @endphp
            <div class="stat-icon" style="background:rgba(139,92,246,.12);color:#8b5cf6">
                <i class="bi bi-percent"></i>
            </div>
            <div>
                <div class="stat-value" style="color:{{ $tasaColor }}">{{ $tasa }}%</div>
                <div class="stat-label">Tasa de finalización</div>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon" style="background:rgba(239,68,68,.12);color:#ef4444">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-value">{{ $promDias }}d</div>
                <div class="stat-label">Prom. Días Pendiente</div>
            </div>
        </div>
    </div>

    <div class="charla-cd-leaders">
        @foreach([
            ['titulo' => 'CD con más charlas ejecutadas', 'cantidad' => $maxCompletadasCD, 'centros' => $cdMasCompletadas, 'clase' => 'completed', 'estado' => 'completadas'],
            ['titulo' => 'CD con más charlas pendientes', 'cantidad' => $maxPendientesCD, 'centros' => $cdMasPendientes, 'clase' => 'pending', 'estado' => 'pendientes'],
        ] as $lider)
        <div class="glass-card charla-cd-card {{ $lider['clase'] }}">
            <h3>{{ $lider['titulo'] }}</h3>
            @if($lider['centros']->isNotEmpty())
                <strong class="charla-cd-count">{{ number_format($lider['cantidad']) }} <small>{{ $lider['estado'] }}{{ $lider['centros']->count() > 1 ? ' por CD' : '' }}</small></strong>
                <p class="charla-cd-names">{{ $lider['centros']->pluck('lugar')->implode(' · ') }}</p>
                @if($lider['centros']->count() > 1)
                    <small>Empate entre {{ $lider['centros']->count() }} centros.</small>
                @endif
            @else
                <p class="charla-cd-names">Sin charlas {{ $lider['estado'] }} con CD informado para estos filtros.</p>
            @endif
        </div>
        @endforeach
    </div>
    <p class="charla-cd-note">Según el lugar de capacitación informado en Kizeo y los filtros activos. Ejecutadas = terminadas + registradas. Se consideran todos los centros, no solo el Top 10. {{ number_format($sinLugar) }} registros sin CD informado quedan fuera de estos indicadores.</p>

    <p style="font-size:.78rem;color:var(--text-muted);margin:0 0 1rem">Terminadas y registradas cuentan como completadas. Recuperadas y transferidas siguen pendientes. Los rankings muestran grupos limitados y pueden no sumar el total del período.</p>

    {{-- Gráficos fila 1: Tendencia + Distribución estatus --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem">
        <div class="glass-card" style="padding:1rem 1.25rem">
            <h3 class="chart-title"><i class="bi bi-graph-up"></i> {{ $esHistorico ? 'Registros por semana Kizeo' : 'Asignaciones por semana de creación' }}</h3>
            <div style="position:relative;height:280px">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="glass-card" style="padding:1rem 1.25rem">
            <h3 class="chart-title"><i class="bi bi-pie-chart-fill"></i> Estatus Kizeo</h3>
            <div style="position:relative;height:280px;display:flex;align-items:center;justify-content:center">
                <canvas id="donutChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Gráficos fila 2: Asignadores + Destinatarios --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem">
        {{-- Quién crea/asigna --}}
        <div class="glass-card" style="padding:1rem 1.25rem">
            <h3 class="chart-title"><i class="bi bi-send-fill" style="color:#8b5cf6"></i> Asignadores / registradores · Top 10</h3>
            @if($topAsignadores->isEmpty())
                <div style="text-align:center;color:var(--text-muted);padding:2rem">
                    <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:.3rem"></i>
                    Sin registros en el período
                </div>
            @else
            <div class="glass-table-container" style="max-height:320px;overflow-y:auto">
                <table class="glass-table" style="font-size:.8rem">
                    <thead>
                        <tr>
                            <th style="text-align:left">Asignador / registrador</th>
                            <th style="text-align:center;width:70px">Total</th>
                            <th style="text-align:center;width:70px">Completadas</th>
                            <th style="text-align:center;width:70px">Pendientes</th>
                            <th style="text-align:center;width:60px">Tasa</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($topAsignadores as $a)
                        @php $aTasa = $a->total_asignadas > 0 ? round(($a->completadas / $a->total_asignadas) * 100) : 0; @endphp
                        <tr>
                            <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $a->usuario }}">{{ $a->usuario }}</td>
                            <td style="text-align:center;font-weight:600">{{ $a->total_asignadas }}</td>
                            <td style="text-align:center;color:#15803d">{{ $a->completadas }}</td>
                            <td style="text-align:center;color:#ea580c">{{ $a->pendientes }}</td>
                            <td style="text-align:center">
                                <span style="font-size:.72rem;padding:2px 6px;border-radius:4px;font-weight:600;
                                    background:rgba(139,92,246,.12);color:#7c3aed">{{ $aTasa }}%</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- A quién se le asigna --}}
        <div class="glass-card" style="padding:1rem 1.25rem">
            <h3 class="chart-title"><i class="bi bi-person-check-fill" style="color:#f97316"></i> Destinatario actual en Kizeo · Top 10 por pendientes</h3>
            <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:.75rem">Coincide con la columna Destinatario de Kizeo. Los registros completados cuyo destinatario quedó vacío siguen incluidos en el total general. La tasa corresponde a este grupo filtrado, no al historial completo de la persona.</p>
            @if($porDestinatario->isEmpty())
                <div style="text-align:center;color:var(--text-muted);padding:2rem">
                    <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:.3rem"></i>
                    Sin transferencias en el período
                </div>
            @else
            <div class="glass-table-container" style="max-height:320px;overflow-y:auto">
                <table class="glass-table" style="font-size:.8rem">
                    <thead>
                        <tr>
                            <th style="text-align:left">Destinatario</th>
                            <th style="text-align:center;width:65px">Registros</th>
                            <th style="text-align:center;width:65px">Completadas</th>
                            <th style="text-align:center;width:65px" title="Descargadas al dispositivo, en progreso">Recuperadas (pend.)</th>
                            <th style="text-align:center;width:65px" title="Aún no descargadas">Transferidas (pend.)</th>
                            <th style="text-align:center;width:55px">Tasa</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($porDestinatario as $d)
                        @php $dTasa = $d->total_recibidas > 0 ? round(($d->completadas / $d->total_recibidas) * 100) : 0; @endphp
                        <tr>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $d->destinatario }}">{{ $d->destinatario }}</td>
                            <td style="text-align:center;font-weight:600">{{ $d->total_recibidas }}</td>
                            <td style="text-align:center;color:#15803d">{{ $d->completadas }}</td>
                            <td style="text-align:center;color:#d97706">{{ $d->recuperadas }}</td>
                            <td style="text-align:center;color:#ea580c">{{ $d->sin_descargar }}</td>
                            <td style="text-align:center">
                                <span style="font-size:.72rem;padding:2px 6px;border-radius:4px;font-weight:600;
                                    background:rgba(139,92,246,.12);color:#7c3aed">{{ $dTasa }}%</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Gráficos fila 3: Cumplimiento por Usuario + Por Lugar --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem">
        <div class="glass-card" style="padding:1rem 1.25rem">
            <h3 class="chart-title"><i class="bi bi-people-fill"></i> Asignadores / registradores · Top 15 por pendientes</h3>
            <div style="position:relative;height:{{ max(250, count($porUsuario) * 32) }}px">
                <canvas id="userChart"></canvas>
            </div>
        </div>

        <div class="glass-card" style="padding:1rem 1.25rem">
            <h3 class="chart-title"><i class="bi bi-geo-alt-fill" style="color:#0ea5e9"></i> Lugar de la capacitación · Top 10</h3>
            <p style="font-size:.75rem;color:var(--text-muted)">Solo registros con lugar informado.</p>
            @if($porLugar->isEmpty())
                <div style="text-align:center;color:var(--text-muted);padding:2rem">
                    <i class="bi bi-geo-alt" style="font-size:1.5rem;display:block;margin-bottom:.3rem"></i>
                    Sin datos de lugar en el período
                </div>
            @else
            <div style="position:relative;height:{{ max(250, count($porLugar) * 32) }}px">
                <canvas id="lugarChart"></canvas>
            </div>
            @endif
        </div>
    </div>

    {{-- Top pendientes --}}
    <div class="glass-card" style="padding:1rem 1.25rem;margin-bottom:1.5rem">
        <h3 class="chart-title"><i class="bi bi-exclamation-triangle-fill" style="color:#f97316"></i> Pendientes con mayor antigüedad · Top 10 responsables</h3>
        <div class="glass-table-container">
            <table class="glass-table" style="font-size:.8rem">
                <thead>
                    <tr>
                        <th style="text-align:left">Responsable</th>
                        <th style="text-align:center;width:100px">Pend. / Transf.</th>
                        <th style="text-align:center;width:140px">Más Antigua</th>
                        <th style="text-align:center;width:80px">Días Máx.</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($topPendientes as $tp)
                    @php
                        $diasMax = $tp->dias_max ?? 0;
                        $diasClass = $diasMax > 14 ? 'color:#dc2626;font-weight:700' : ($diasMax > 7 ? 'color:#d97706;font-weight:600' : '');
                    @endphp
                    <tr>
                        <td>{{ $tp->responsable ?? 'Desconocido' }}</td>
                        <td style="text-align:center">
                            <span class="badge" style="font-size:.72rem;background:rgba(249,115,22,.12);color:#ea580c">{{ $tp->cantidad }}</span>
                        </td>
                        <td style="text-align:center;font-size:.75rem;color:var(--text-muted)">
                            {{ $tp->mas_antigua ? \Carbon\Carbon::parse($tp->mas_antigua)->format('d/m/Y') : '-' }}
                        </td>
                        <td style="text-align:center;{{ $diasClass }}">{{ $diasMax }}d</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem">
                            <i class="bi bi-check-circle-fill" style="font-size:1.5rem;color:#22c55e;display:block;margin-bottom:.3rem"></i>
                            Sin pendientes — ¡Todo al día!
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabla de registros detalle --}}
    <div class="glass-card" style="padding:1rem 1.25rem;margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
            <h3 class="chart-title" style="margin:0">
                <i class="bi bi-list-check"></i> {{ $esHistorico ? 'Histórico de registros Kizeo' : 'Detalle de asignaciones' }}
                <span class="badge" style="font-size:.65rem;margin-left:.3rem;vertical-align:middle;background:rgba(59,130,246,.12);color:#3b82f6">{{ $registrosList->total() }}</span>
            </h3>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>ID Kizeo</th>
                        <th>Título</th>
                        <th>Asignador / registrador</th>
                        <th>Destinatario</th>
                        <th>Lugar / CD</th>
                        <th style="text-align:center">Estatus</th>
                        <th style="text-align:center">Fecha Creación</th>
                        <th style="text-align:center">Registro Kizeo</th>
                        <th style="text-align:center">Fecha Asignación</th>
                        <th style="text-align:center">Fecha Respuesta</th>
                        <th style="text-align:center">Días</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($registrosList as $item)
                    @php
                        $refDate = $item->fecha_asignacion ?? $item->fecha_creacion;
                        $dias = ($item->estado !== 'completado' && $refDate) ? (int) $refDate->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;
                        $diasStyle = $dias !== null ? ($dias > 14 ? 'color:#dc2626;font-weight:700' : ($dias > 7 ? 'color:#d97706;font-weight:600' : 'color:var(--text-muted)')) : '';
                    @endphp
                    <tr>
                        <td style="font-size:.78rem">{{ $item->kizeo_data_id }}</td>
                        <td style="font-size:.82rem;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $item->titulo_actividad }}">
                            {{ $item->titulo_actividad ?: '—' }}
                        </td>
                        <td style="font-size:.82rem;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $item->asignado_por }}">
                            {{ $item->asignado_por ?? '-' }}
                        </td>
                        <td style="font-size:.82rem;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $item->asignado_a }}">
                            {{ $item->asignado_a ?? '—' }}
                        </td>
                        <td style="font-size:.8rem;color:var(--text-muted);max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $item->lugar }}">
                            {{ $item->lugar ?: '—' }}
                        </td>
                        <td style="text-align:center">
                            @if($item->estatus_kizeo === 'registrado')
                                <span style="font-size:.7rem;padding:2px 8px;border-radius:4px;font-weight:600;background:rgba(34,197,94,.12);color:#15803d">✓ Registrado</span>
                            @elseif($item->estatus_kizeo === 'terminado')
                                <span style="font-size:.7rem;padding:2px 8px;border-radius:4px;font-weight:600;background:rgba(15,118,110,.12);color:#0f766e">✓ Terminado</span>
                            @elseif($item->estatus_kizeo === 'transferido')
                                <span style="font-size:.7rem;padding:2px 8px;border-radius:4px;font-weight:600;background:rgba(249,115,22,.12);color:#ea580c">⟳ Transferido · pendiente</span>
                            @elseif($item->estatus_kizeo === 'recuperado')
                                <span style="font-size:.7rem;padding:2px 8px;border-radius:4px;font-weight:600;background:rgba(245,158,11,.12);color:#b45309">↓ Recuperado · pendiente</span>
                            @else
                                <span style="font-size:.7rem;padding:2px 8px;border-radius:4px;font-weight:600;background:rgba(249,115,22,.12);color:#ea580c">{{ ucfirst($item->estado) }}</span>
                            @endif
                        </td>
                        <td style="text-align:center;font-size:.78rem;color:var(--text-muted)">
                            {{ $item->fecha_creacion?->format('d/m/Y H:i') ?? '-' }}
                        </td>
                        <td style="text-align:center;font-size:.78rem;color:var(--text-muted)">
                            {{ $item->fecha_registro_kizeo?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td style="text-align:center;font-size:.78rem;color:var(--text-muted)">
                            {{ $item->fecha_asignacion?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td style="text-align:center;font-size:.78rem;{{ $item->fecha_respuesta ? 'color:#15803d' : 'color:var(--text-muted)' }}">
                            {{ $item->fecha_respuesta?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td style="text-align:center;{{ $diasStyle }}">
                            {{ $dias !== null ? $dias . 'd' : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center;color:var(--text-muted);padding:2rem">
                            No hay registros en el período seleccionado.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($registrosList->hasPages())
        <div style="margin-top:1rem;display:flex;justify-content:center">
            {{ $registrosList->links() }}
        </div>
        @endif
    </div>

</div>

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    if (typeof Chart === 'undefined') {
        document.querySelectorAll('canvas').forEach(function(canvas) {
            const holder = canvas.parentElement;
            if (!holder) return;
            canvas.remove();
            holder.innerHTML = '<div class="chart-fallback">No se pudo cargar el motor de gráficos. Actualice la página o revise el build de assets.</div>';
        });
        return;
    }

    const isDark = document.documentElement.classList.contains('dark') ||
                   document.body.classList.contains('dark-mode');
    const gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    Chart.defaults.color = textColor;
    Chart.defaults.font.size = 11;
    Chart.defaults.font.family = "'Segoe UI','Helvetica Neue',sans-serif";

    // === 1. Tendencia Semanal ===
    const trendData = @json($tendencia);
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.label),
            datasets: [
                {
                    label: 'Completadas',
                    data: trendData.map(d => d.completadas),
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,.1)',
                    fill: true, tension: .3, borderWidth: 2,
                    pointRadius: 4, pointBackgroundColor: '#22c55e'
                },
                {
                    label: 'Pendientes',
                    data: trendData.map(d => d.pendientes),
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249,115,22,.1)',
                    fill: true, tension: .3, borderWidth: 2,
                    pointRadius: 4, pointBackgroundColor: '#f97316'
                },
                {
                    label: 'Tasa %',
                    data: trendData.map(d => d.tasa),
                    borderColor: '#8b5cf6',
                    borderDash: [5, 3], borderWidth: 2,
                    pointRadius: 3, pointBackgroundColor: '#8b5cf6',
                    yAxisID: 'y1', fill: false, tension: .3
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.yAxisID === 'y1' ? `Tasa: ${ctx.raw}%` : `${ctx.dataset.label}: ${ctx.raw}`
                    }
                }
            },
            scales: {
                y:  { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } },
                y1: { position: 'right', beginAtZero: true, max: 100, grid: { display: false },
                      ticks: { callback: v => v + '%' } },
                x:  { grid: { display: false } }
            }
        }
    });

    // === 2. Doughnut Estatus Kizeo ===
    const dist = @json($distribucion);
    const statusLabels = {
        registrado: 'Registrado', transferido: 'Transferido · pendiente',
        recuperado: 'Recuperado · pendiente', terminado: 'Terminado', pendiente: 'Pendiente'
    };
    const statusColors = {
        terminado: '#0f766e', registrado: '#22c55e',
        recuperado: '#f59e0b', transferido: '#f97316', pendiente: '#f97316'
    };
    const distKeys = ['terminado', 'registrado', 'recuperado', 'transferido', 'pendiente'].filter(k => Number(dist[k]) > 0);

    new Chart(document.getElementById('donutChart'), {
        type: 'doughnut',
        data: {
            labels: distKeys.map(k => statusLabels[k] || k),
            datasets: [{
                data: distKeys.map(k => Number(dist[k])),
                backgroundColor: distKeys.map(k => statusColors[k] || '#6b7280'),
                borderWidth: 0, hoverOffset: 8
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 10 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                            const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${ctx.raw} (${pct}%)`;
                        }
                    }
                }
            }
        },
        plugins: [{
            id: 'centerText',
            afterDraw(chart) {
                const { ctx, width, height } = chart;
                const total = chart.data.datasets[0].data.reduce((a,b) => a+b, 0);
                const comp = Number(dist.registrado || 0) + Number(dist.terminado || 0);
                const pct = total > 0 ? Math.round((comp / total) * 1000) / 10 : 0;
                ctx.save();
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.font = 'bold 26px Segoe UI';
                ctx.fillStyle = isDark ? '#c4b5fd' : '#7c3aed';
                ctx.fillText(pct + '%', width / 2, height / 2 - 6);
                ctx.font = '10px Segoe UI';
                ctx.fillStyle = textColor;
                ctx.fillText('Finalización', width / 2, height / 2 + 14);
                ctx.restore();
            }
        }]
    });

    // === 3. Cumplimiento por Usuario (horizontal bar) ===
    const userData = @json($porUsuario);
    new Chart(document.getElementById('userChart'), {
        type: 'bar',
        data: {
            labels: userData.map(d => {
                const n = d.usuario || 'Desconocido';
                return n.length > 25 ? n.substring(0, 22) + '...' : n;
            }),
            datasets: [
                { label: 'Completadas', data: userData.map(d => d.completadas), backgroundColor: '#22c55e', borderRadius: 4, barPercentage: .7 },
                { label: 'Pendientes', data: userData.map(d => d.pendientes), backgroundColor: '#f97316', borderRadius: 4, barPercentage: .7 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } } },
            scales: {
                x: { stacked: true, beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } },
                y: { stacked: true, grid: { display: false } }
            }
        }
    });

    // === 4. Por Lugar (horizontal bar) ===
    const lugarEl = document.getElementById('lugarChart');
    if (lugarEl) {
        const lugarData = @json($porLugar);
        new Chart(lugarEl, {
            type: 'bar',
            data: {
                labels: lugarData.map(d => {
                    const n = d.lugar || 'Sin lugar';
                    return n.length > 25 ? n.substring(0, 22) + '...' : n;
                }),
                datasets: [
                    { label: 'Completadas', data: lugarData.map(d => d.completadas), backgroundColor: '#22c55e', borderRadius: 4, barPercentage: .7 },
                    { label: 'Pendientes', data: lugarData.map(d => d.pendientes), backgroundColor: '#f97316', borderRadius: 4, barPercentage: .7 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } } },
                scales: {
                    x: { stacked: true, beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } },
                    y: { stacked: true, grid: { display: false } }
                }
            }
        });
    }

    // Sync button loading
    const syncForm = document.getElementById('sync-form');
    if (syncForm) {
        syncForm.addEventListener('submit', function() {
            const btn = document.getElementById('sync-btn');
            const icon = document.getElementById('sync-icon');
            btn.disabled = true;
            btn.style.opacity = '.6';
            icon.classList.add('spin-animation');
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin-animation"></i> Sincronizando...';
        });
    }
});
</script>
@endpush

@push('styles')
<style>
.charla-dashboard .chart-title {
    font-size:.82rem;color:var(--text-muted);text-transform:uppercase;
    letter-spacing:.06em;font-weight:700;margin-bottom:.75rem;
}

.charla-header-actions {
    display: flex;
    gap: .5rem;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.charla-active-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .4rem;
    margin: -.25rem 0 1rem;
}

.charla-active-filters-label {
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--text-muted);
}

.charla-filter-chip {
    max-width: 100%;
    border: 1px solid rgba(59,130,246,.22);
    background: rgba(59,130,246,.08);
    color: #1d4ed8;
    border-radius: 999px;
    padding: .16rem .55rem;
    font-size: .7rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.charla-activity-list {
    display: grid;
    gap: .45rem;
}

.charla-activity-row {
    display: grid;
    grid-template-columns: minmax(120px, 150px) 90px minmax(0, 1fr) auto;
    gap: .75rem;
    align-items: center;
    border-top: 1px solid rgba(148,163,184,.22);
    padding: .55rem 0;
}

.charla-activity-summary {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: .74rem;
    color: var(--text-muted);
}

.chart-fallback {
    display: flex;
    height: 100%;
    min-height: 160px;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 1rem;
    border-radius: 10px;
    border: 1px dashed rgba(128,128,128,.25);
    color: var(--text-muted);
    font-size: .8rem;
}

.spin-animation { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* Pagination fix */
.page-container nav[role="navigation"] { font-size:.82rem; }
.page-container nav[role="navigation"] svg { width:1rem;height:1rem; }
.page-container nav .relative.inline-flex { display:inline-flex;gap:.15rem; }

@media (max-width: 900px) {
    .page-container > div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }

    .charla-header-actions {
        width: 100%;
        justify-content: stretch;
    }

    .charla-header-actions > a,
    .charla-header-actions > form,
    .charla-header-actions button {
        width: 100%;
    }

    .charla-activity-row {
        grid-template-columns: 1fr auto;
        align-items: start;
    }

    .charla-activity-row time {
        grid-column: 1 / -1;
        justify-self: start;
    }

    .charla-activity-summary {
        grid-column: 1 / -1;
        white-space: normal;
    }
}
.charla-dashboard { --charla-navy:#172f50; --charla-teal:#0f766e; }
.charla-dashboard .charla-hero {
    padding:1.65rem; margin-bottom:1.2rem; border-radius:18px;
    background:linear-gradient(115deg,#142c4b,#204c69); color:#fff;
    box-shadow:0 8px 24px rgba(20,44,75,.12); gap:1.25rem; flex-wrap:wrap;
}
.charla-eyebrow { display:block; font-size:.65rem; font-weight:800; letter-spacing:.14em; color:#9ddbd5; margin-bottom:.6rem; }
.charla-dashboard .charla-hero .page-heading { color:#fff; font-size:1.5rem; letter-spacing:-.035em; }
.charla-dashboard .charla-hero .page-heading i { color:#fb923c !important; }
.charla-dashboard .charla-hero .page-subheading,
.charla-dashboard .charla-hero .page-subheading span { color:#c7d8e7 !important; line-height:1.6; }
.charla-dashboard .charla-hero .btn-secondary { background:rgba(255,255,255,.1) !important; border:1px solid rgba(255,255,255,.25) !important; color:#fff !important; box-shadow:none; }
.charla-dashboard .charla-hero .btn-premium { background:#fff; color:#173653; box-shadow:none; border:1px solid #fff; }
.charla-dashboard .charla-hero button:hover,.charla-dashboard .charla-hero a:hover { filter:brightness(1.12); }
.charla-dashboard .glass-card {
    background:var(--card-bg,#fff); border:1px solid var(--border-color,#e2e8f0);
    border-radius:14px; box-shadow:0 3px 12px rgba(15,23,42,.035); backdrop-filter:none;
}
.charla-dashboard .charla-filters {
    display:grid; grid-template-columns:minmax(240px,1.4fr) repeat(3,minmax(135px,1fr)) minmax(170px,1.2fr) auto;
    gap:.85rem; padding:1.15rem; background:var(--card-bg,#fff);
    border:1px solid var(--border-color,#e2e8f0); border-radius:14px; margin-bottom:.65rem;
}
.charla-dashboard .charla-filters .filter-group { min-width:0; width:auto; }
.charla-dashboard .charla-filters label { font-size:.72rem; font-weight:700; color:var(--text-muted); margin-bottom:.4rem; }
.charla-dashboard .charla-filters .form-input { width:100%; min-height:42px; font-size:.8rem; border-radius:8px; box-shadow:none; }
.charla-dashboard .charla-filters .btn-secondary { min-height:42px; background:var(--charla-navy); color:#fff; border-radius:8px; }
.charla-dashboard .charla-sync-status { padding:.75rem 1rem !important; font-size:.8rem; box-shadow:none; }
.charla-dashboard .charla-period-comparison { background:linear-gradient(105deg,var(--card-bg),var(--bg-color)); box-shadow:none; }
.charla-dashboard .charla-audit { padding:0; margin:0 0 1.15rem; overflow:hidden; }
.charla-audit summary { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.9rem 1.1rem; cursor:pointer; list-style:none; font-size:.8rem; font-weight:700; color:var(--text-primary); }
.charla-audit summary::-webkit-details-marker { display:none; }
.charla-audit summary:hover { background:var(--bg-color); }
.charla-audit summary:focus-visible { outline:3px solid #38bdf8; outline-offset:-3px; }
.charla-audit summary .bi-shield-check { color:var(--charla-teal); font-size:1rem; }
.charla-audit-count { background:var(--bg-color); color:var(--text-muted); padding:.1rem .45rem; border-radius:6px; font-size:.68rem; }
.charla-audit-toggle { display:flex; align-items:center; gap:.65rem; color:var(--text-muted); font-size:.72rem; white-space:nowrap; }
.charla-audit .audit-hide { display:none; }
.charla-audit[open] .audit-hide { display:inline; }
.charla-audit[open] .audit-show { display:none; }
.charla-audit[open] .bi-chevron-down { transform:rotate(180deg); }
.charla-audit .charla-activity-list { padding:0 1.1rem .75rem; max-height:340px; overflow:auto; }
.charla-dashboard .stats-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:1rem; }
.charla-dashboard .stat-item {
    display:flex; align-items:center; gap:.9rem; padding:1.3rem 1rem;
    background:var(--card-bg); border:1px solid var(--border-color); border-top:3px solid #64748b;
    border-radius:12px; box-shadow:0 3px 12px rgba(15,23,42,.035); min-width:0;
}
.charla-dashboard .stat-item:nth-child(1) { border-top-color:#2563eb; }
.charla-dashboard .stat-item:nth-child(2) { border-top-color:#22c55e; }
.charla-dashboard .stat-item:nth-child(3) { border-top-color:#ea580c; }
.charla-dashboard .stat-item:nth-child(4) { border-top-color:#8b5cf6; }
.charla-dashboard .stat-icon { width:42px; height:42px; border-radius:11px; flex-shrink:0; font-size:1.15rem; }
.charla-dashboard .stat-value { font-size:1.85rem; font-weight:800; letter-spacing:-.045em; line-height:1.15; font-variant-numeric:tabular-nums; }
.charla-dashboard .stat-label { color:var(--text-muted); font-size:.72rem; line-height:1.4; margin-top:.35rem; }
.charla-dashboard .chart-title { color:var(--text-primary); font-size:.8rem; letter-spacing:.025em; padding-bottom:.8rem; border-bottom:1px solid var(--border-color); }
.charla-dashboard .chart-title i { color:var(--charla-teal); margin-right:.3rem; }
.charla-dashboard .glass-table thead th { background:var(--bg-color); font-size:.66rem; letter-spacing:.04em; color:var(--text-muted); padding:.8rem .65rem; }
.charla-dashboard .glass-table tbody td { padding:.8rem .65rem; border-bottom:1px solid var(--border-color); }
.charla-dashboard .glass-table tbody tr:hover { background:var(--bg-color); }
.charla-dashboard .glass-table-container { border-radius:8px; }
body.dark-mode .charla-dashboard .stat-value { color:#e2e8f0 !important; }
body.dark-mode .charla-dashboard .charla-filter-chip { color:#93c5fd; }
@media(max-width:1400px) {
    .charla-dashboard .charla-filters { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .charla-dashboard .stats-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
}
@media(max-width:1100px) {
    .charla-dashboard > div[style*="grid-template-columns"] { grid-template-columns:minmax(0,1fr) !important; }
    .charla-dashboard .charla-hero { padding:1.25rem; }
    .charla-dashboard .charla-header-actions { display:flex; flex-wrap:wrap; justify-content:flex-start; }
    .charla-dashboard .charla-header-actions > a,.charla-dashboard .charla-header-actions > form { width:auto; flex:1 1 160px; }
    .charla-dashboard .charla-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .charla-dashboard .stats-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media(max-width:540px) {
    .charla-dashboard .charla-filters { grid-template-columns:1fr; }
    .charla-dashboard .stat-item { padding:1rem .7rem; gap:.6rem; }
    .charla-dashboard .stat-icon { width:32px; height:32px; font-size:1rem; }
    .charla-dashboard .stat-value { font-size:1.5rem; }
    .charla-dashboard .charla-hero .page-heading { font-size:1.25rem; }
    .charla-audit-toggle .audit-show,.charla-audit-toggle .audit-hide { display:none; }
}
</style>
@endpush
@endsection

@push('styles')
<style>
.charla-cd-leaders { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-bottom:.5rem; }
.charla-cd-card { padding:1rem 1.25rem;border-top:3px solid #22c55e;min-width:0; }
.charla-cd-card.pending { border-top-color:#f97316; }
.charla-cd-card h3 { font-size:.85rem;font-weight:700;margin:0 0 .65rem;color:var(--text-muted); }
.charla-cd-count { font-size:1.8rem;color:#15803d; }
.charla-cd-card.pending .charla-cd-count { color:#ea580c; }
.charla-cd-count small { font-size:.8rem;font-weight:500; }
.charla-cd-names { font-weight:600;overflow-wrap:anywhere;margin:.4rem 0;color:var(--text-primary); }
.charla-cd-card > small,.charla-cd-note { font-size:.75rem;color:var(--text-muted); }
.charla-cd-note { margin-bottom:1.5rem; }
body.dark-mode .charla-cd-count { color:#4ade80; }
body.dark-mode .charla-cd-card.pending .charla-cd-count { color:#fb923c; }
@media(max-width:700px) { .charla-cd-leaders { grid-template-columns:1fr; } }
</style>
@endpush
