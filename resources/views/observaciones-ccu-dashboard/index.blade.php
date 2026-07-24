@extends('layouts.app')

@section('title', 'Observaciones CCU')

@section('content')
@php
    $options = $analytics['filter_options'] ?? [];
    $recent = $analytics['recent'] ?? collect();
    $maxCenter = max([1, ...array_values($analytics['centros'] ?? [])]);
    $maxType = max([1, ...array_values($analytics['tipos'] ?? [])]);
    $maxMeasure = max([1, ...array_values($analytics['medidas'] ?? [])]);
    $maxCargo = max([1, ...array_values($analytics['cargos'] ?? [])]);
    $maxAntiguedad = max([1, ...array_values($analytics['antiguedades'] ?? [])]);
    $maxWorker = max([1, ...array_values($analytics['top_trabajadores_negativos'] ?? [])]);
    $maxObserver = max([1, ...array_values($analytics['top_observadores'] ?? [])]);
@endphp

<style>
    .ccu-dashboard { max-width: 1540px; margin: 0 auto; }
    .ccu-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem; }
    .ccu-header h2 { margin:0; font-size:1.5rem; color:var(--text-primary); }
    .ccu-header p { margin:.3rem 0 0; color:var(--text-muted); font-size:.84rem; }
    .ccu-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
    .ccu-filter-panel { padding:1rem; margin-bottom:1rem; }
    .ccu-filter-grid { display:grid; grid-template-columns:repeat(6, minmax(130px, 1fr)); gap:.7rem; align-items:end; }
    .ccu-filter-grid label { display:block; margin-bottom:.25rem; color:var(--text-muted); font-size:.71rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
    .ccu-filter-grid select, .ccu-filter-grid input { width:100%; min-height:38px; border:1px solid var(--border-color, #d9e0ea); border-radius:6px; background:var(--input-bg, var(--card-bg, #fff)); color:var(--text-primary); padding:.45rem .55rem; font-size:.8rem; }
    .ccu-kpis { display:grid; grid-template-columns:repeat(6, minmax(145px, 1fr)); gap:.75rem; margin-bottom:1rem; }
    .ccu-kpi { padding:1rem; min-height:104px; border-left:4px solid #8b5cf6; }
    .ccu-kpi.positive { border-left-color:#16a34a; }
    .ccu-kpi.negative { border-left-color:#dc2626; }
    .ccu-kpi.warning { border-left-color:#d97706; }
    .ccu-kpi.review { border-left-color:#64748b; }
    .ccu-kpi .label { color:var(--text-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
    .ccu-kpi .value { color:var(--text-primary); font-size:1.65rem; line-height:1.1; font-weight:800; margin-top:.35rem; }
    .ccu-kpi .hint { color:var(--text-muted); font-size:.72rem; margin-top:.25rem; }
    .ccu-grid { display:grid; grid-template-columns:minmax(0, 1.25fr) minmax(340px, .75fr); gap:1rem; margin-bottom:1rem; }
    .ccu-grid-three { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:1rem; margin-bottom:1rem; }
    .ccu-panel { padding:1rem; min-width:0; }
    .ccu-panel h3 { font-size:.91rem; color:var(--text-primary); margin:0 0:.85rem; display:flex; align-items:center; gap:.45rem; }
    .ccu-panel h3 i { color:var(--accent-color); }
    .ccu-bars { display:grid; gap:.6rem; }
    .ccu-bar-row { display:grid; grid-template-columns:minmax(110px, 1.5fr) minmax(100px, 2fr) 32px; gap:.55rem; align-items:center; }
    .ccu-bar-label { font-size:.75rem; color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ccu-bar-track { display:flex; height:10px; overflow:hidden; border-radius:3px; background:rgba(148,163,184,.2); }
    .ccu-bar-positive { background:#22c55e; }
    .ccu-bar-negative { background:#ef4444; }
    .ccu-bar-neutral { background:#8b5cf6; }
    .ccu-bar-review { background:#64748b; }
    .ccu-bar-count { color:var(--text-muted); font-size:.75rem; text-align:right; font-variant-numeric:tabular-nums; }
    .ccu-trend { display:grid; grid-template-columns:repeat(auto-fit, minmax(72px, 1fr)); gap:.5rem; align-items:end; min-height:165px; padding-top:.25rem; }
    .ccu-trend-item { min-width:0; text-align:center; }
    .ccu-trend-bars { height:108px; display:flex; gap:3px; justify-content:center; align-items:flex-end; border-bottom:1px solid var(--border-color, #d9e0ea); }
    .ccu-trend-bar { width:15px; min-height:3px; border-radius:3px 3px 0 0; }
    .ccu-trend-label { color:var(--text-muted); font-size:.66rem; margin-top:.35rem; white-space:nowrap; }
    .ccu-legend { display:flex; gap:.8rem; margin-top:.7rem; color:var(--text-muted); font-size:.72rem; }
    .ccu-legend span::before { content:''; width:9px; height:9px; border-radius:2px; display:inline-block; margin-right:.3rem; }
    .ccu-legend .positive::before { background:#22c55e; }
    .ccu-legend .negative::before { background:#ef4444; }
    .ccu-legend .review::before { background:#64748b; }
    .ccu-table-wrap { overflow-x:auto; }
    .ccu-table { width:100%; border-collapse:collapse; min-width:800px; }
    .ccu-table th { padding:.55rem .6rem; color:var(--text-muted); font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; text-align:left; border-bottom:1px solid var(--border-color, #d9e0ea); white-space:nowrap; }
    .ccu-table td { padding:.65rem .6rem; color:var(--text-primary); font-size:.78rem; vertical-align:top; border-bottom:1px solid var(--border-color, #d9e0ea); }
    .ccu-table tbody tr:last-child td { border-bottom:0; }
    .ccu-muted { color:var(--text-muted); font-size:.72rem; }
    .ccu-badge { display:inline-flex; align-items:center; border-radius:999px; padding:.18rem .48rem; font-size:.68rem; font-weight:800; white-space:nowrap; }
    .ccu-badge.positive { background:#dcfce7; color:#166534; }
    .ccu-badge.negative { background:#fee2e2; color:#b91c1c; }
    .ccu-badge.review { background:#e2e8f0; color:#334155; }
    .ccu-empty { padding:2.2rem 1rem; color:var(--text-muted); text-align:center; }
    @media (max-width: 1180px) { .ccu-filter-grid { grid-template-columns:repeat(3, minmax(160px, 1fr)); } .ccu-kpis { grid-template-columns:repeat(3, 1fr); } .ccu-grid, .ccu-grid-three { grid-template-columns:1fr; } }
    @media (max-width: 720px) { .ccu-header { flex-direction:column; } .ccu-actions { width:100%; } .ccu-filter-grid, .ccu-kpis { grid-template-columns:1fr 1fr; } .ccu-kpi { min-height:92px; } .ccu-bar-row { grid-template-columns:minmax(90px, 1.2fr) minmax(80px, 1fr) 28px; } }
</style>

<div class="page-container ccu-dashboard">
    <div class="ccu-header">
        <div>
            <h2><i class="bi bi-clipboard2-pulse-fill" style="color:var(--accent-color)"></i> Observaciones de Conducta CCU</h2>
            <p>
                Indicadores del formulario Kizeo, con centros oficiales CCU.
                @if($syncInfo)
                    <span title="Última sincronización local"><i class="bi bi-database-check" style="color:#16a34a"></i> {{ number_format($syncInfo['total']) }} registros · actualizado {{ \Carbon\Carbon::parse($syncInfo['last_sync'])->diffForHumans() }}</span>
                @else
                    <span><i class="bi bi-cloud-arrow-down"></i> Aún no se han sincronizado datos.</span>
                @endif
            </p>
        </div>
        <div class="ccu-actions">
            <a href="{{ route('pdr-ccu-dashboard.excel', $filters) }}" class="btn-secondary" style="font-size:.8rem;text-decoration:none" title="Descarga el resumen y los registros del periodo filtrado">
                <i class="bi bi-file-earmark-excel"></i> Descargar Excel
            </a>
            <a href="{{ route('pdr-ccu-dashboard.email-preview', $filters) }}" target="_blank" class="btn-secondary" style="font-size:.8rem;text-decoration:none" title="Revisa el correo antes de enviarlo">
                <i class="bi bi-envelope-open"></i> Vista previa
            </a>
            <form method="POST" action="{{ route('pdr-ccu-dashboard.email-self') }}" onsubmit="return confirm('Se enviará el reporte filtrado y su Excel adjunto a tu correo de usuario. ¿Continuar?')">
                @csrf
                @foreach($filters as $filterKey => $filterValue)
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endforeach
                <button type="submit" class="btn-secondary" style="font-size:.8rem" title="Envía el reporte al correo de tu sesión">
                    <i class="bi bi-send-fill"></i> Enviar a mi correo
                </button>
            </form>
            <a href="{{ route('pdr-ccu-dashboard.index', ['todo' => 1]) }}" class="btn-secondary" style="font-size:.8rem;text-decoration:none" title="Quita el periodo por defecto y muestra todo el historial">
                <i class="bi bi-calendar3"></i> Ver historial
            </a>
            @if(auth()->user()->tieneAcceso('pdr_ccu_dashboard', 'puede_editar'))
                <form method="POST" action="{{ route('pdr-ccu-dashboard.sync') }}" onsubmit="return confirm('Se consultarán nuevamente las respuestas de Kizeo. ¿Continuar?')">
                    @csrf
                    <button type="submit" class="btn-premium" style="font-size:.8rem" title="Actualiza el dashboard desde Kizeo">
                        <i class="bi bi-arrow-clockwise"></i> Sincronizar Kizeo
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <section class="glass-card ccu-filter-panel" aria-label="Filtros del dashboard">
        <form method="GET" action="{{ route('pdr-ccu-dashboard.index') }}" class="ccu-filter-grid">
            <div>
                <label for="ccu-centro">Centro</label>
                <select id="ccu-centro" name="centro">
                    <option value="">Todos los centros</option>
                    @foreach($options['centros'] ?? [] as $option)
                        <option value="{{ $option }}" @selected(($filters['centro'] ?? '') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ccu-clasificacion">Resultado</label>
                <select id="ccu-clasificacion" name="clasificacion">
                    <option value="">Todos los resultados</option>
                    <option value="Positiva" @selected(($filters['clasificacion'] ?? '') === 'Positiva')>Positivas</option>
                    <option value="Negativa" @selected(($filters['clasificacion'] ?? '') === 'Negativa')>Negativas</option>
                    <option value="Por revisar" @selected(($filters['clasificacion'] ?? '') === 'Por revisar')>Por revisar</option>
                </select>
            </div>
            <div>
                <label for="ccu-observador">Observador</label>
                <select id="ccu-observador" name="observador_nombre">
                    <option value="">Todos los observadores</option>
                    @foreach($options['observadores'] ?? [] as $option)
                        <option value="{{ $option }}" @selected(($filters['observador_nombre'] ?? '') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ccu-desde">Desde</label>
                <input id="ccu-desde" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}">
            </div>
            <div>
                <label for="ccu-hasta">Hasta</label>
                <input id="ccu-hasta" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}">
            </div>
            <div style="display:flex;gap:.45rem">
                <button type="submit" class="btn-premium" style="font-size:.8rem;flex:1"><i class="bi bi-funnel-fill"></i> Aplicar</button>
                <a href="{{ route('pdr-ccu-dashboard.index', ['todo' => 1]) }}" class="btn-secondary" style="font-size:.8rem;text-decoration:none;display:inline-flex;align-items:center" title="Quitar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </section>

    @if(!$hasData)
        <section class="glass-card ccu-empty">
            <i class="bi bi-cloud-arrow-down" style="font-size:1.7rem;color:var(--accent-color)"></i>
            <p style="margin:.7rem 0 .25rem;font-weight:700;color:var(--text-primary)">El dashboard está listo para recibir datos.</p>
            <p style="margin:0">Un usuario con permiso de edición debe sincronizar el formulario desde Kizeo.</p>
        </section>
    @else
        <section class="ccu-kpis" aria-label="Indicadores principales">
            <article class="glass-card ccu-kpi"><div class="label">Observaciones</div><div class="value">{{ number_format($analytics['total']) }}</div><div class="hint">En el periodo filtrado</div></article>
            <article class="glass-card ccu-kpi positive"><div class="label">Conductas seguras</div><div class="value">{{ number_format($analytics['positivas']) }}</div><div class="hint">Observaciones positivas</div></article>
            <article class="glass-card ccu-kpi negative"><div class="label">Hallazgos</div><div class="value">{{ number_format($analytics['negativas']) }}</div><div class="hint">Requieren seguimiento</div></article>
            <article class="glass-card ccu-kpi review"><div class="label">Por revisar</div><div class="value">{{ number_format($analytics['por_revisar']) }}</div><div class="hint">Selección múltiple en Kizeo</div></article>
            <article class="glass-card ccu-kpi warning"><div class="label">Resultado positivo</div><div class="value">{{ number_format($analytics['porcentaje_positivo'], 1) }}%</div><div class="hint">Del total observado</div></article>
            <article class="glass-card ccu-kpi"><div class="label">Centros activos</div><div class="value">{{ number_format($analytics['centros_activos']) }}</div><div class="hint">Con registros</div></article>
        </section>

        <section class="ccu-grid">
            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-bar-chart-line-fill"></i> Tendencia mensual</h3>
                @if(!empty($analytics['by_month']))
                    @php $maxMonth = max(array_map(fn ($month) => max($month['positivas'], $month['negativas'], $month['por_revisar']), $analytics['by_month'])) ?: 1; @endphp
                    <div class="ccu-trend">
                        @foreach($analytics['by_month'] as $month)
                            <div class="ccu-trend-item" title="{{ $month['label'] }}: {{ $month['positivas'] }} positivas, {{ $month['negativas'] }} negativas y {{ $month['por_revisar'] }} por revisar">
                                <div class="ccu-trend-bars">
                                    <span class="ccu-trend-bar ccu-bar-positive" style="height:{{ max(3, ($month['positivas'] / $maxMonth) * 100) }}%"></span>
                                    <span class="ccu-trend-bar ccu-bar-negative" style="height:{{ max(3, ($month['negativas'] / $maxMonth) * 100) }}%"></span>
                                    <span class="ccu-trend-bar ccu-bar-review" style="height:{{ max(3, ($month['por_revisar'] / $maxMonth) * 100) }}%"></span>
                                </div>
                                <div class="ccu-trend-label">{{ \Carbon\Carbon::createFromFormat('Y-m', $month['label'])->translatedFormat('M y') }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="ccu-legend"><span class="positive">Positivas</span><span class="negative">Negativas</span><span class="review">Por revisar</span></div>
                @else
                    <div class="ccu-empty">No hay registros para el periodo seleccionado.</div>
                @endif
            </article>

            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-buildings-fill"></i> Por centro</h3>
                <div class="ccu-bars">
                    @forelse($analytics['centros'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} observaciones">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-neutral" style="width:{{ ($count / $maxCenter) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">Sin centros para mostrar.</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="ccu-grid-three">
            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-person-workspace"></i> Por cargo observado</h3>
                <div class="ccu-bars">
                    @forelse($analytics['cargos'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} observaciones">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-neutral" style="width:{{ ($count / $maxCargo) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">Sin cargos informados.</div>
                    @endforelse
                </div>
            </article>

            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-hourglass-split"></i> Antigüedad en el cargo</h3>
                <div class="ccu-bars">
                    @forelse($analytics['antiguedades'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} observaciones">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-neutral" style="width:{{ ($count / $maxAntiguedad) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">Sin antigüedad informada.</div>
                    @endforelse
                </div>
            </article>

            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-arrow-repeat"></i> Medidas de control</h3>
                <div class="ccu-bars">
                    @forelse($analytics['medidas'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} registros no positivos">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-negative" style="width:{{ ($count / $maxMeasure) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">No hay medidas asociadas a hallazgos o registros por revisar.</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="ccu-grid">
            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-exclamation-diamond-fill"></i> Conductas observadas</h3>
                <div class="ccu-bars">
                    @forelse($analytics['tipos'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} observaciones">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-neutral" style="width:{{ ($count / $maxType) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">Sin conductas para mostrar.</div>
                    @endforelse
                </div>
            </article>

            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-people-fill"></i> Trabajadores con más hallazgos</h3>
                <div class="ccu-bars">
                    @forelse($analytics['top_trabajadores_negativos'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} hallazgos negativos">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-negative" style="width:{{ ($count / $maxWorker) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">No hay hallazgos negativos para mostrar.</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="ccu-grid">
            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-person-check-fill"></i> Top observadores</h3>
                <div class="ccu-bars">
                    @forelse($analytics['top_observadores'] as $label => $count)
                        <div class="ccu-bar-row" title="{{ $label }}: {{ $count }} observaciones registradas">
                            <div class="ccu-bar-label">{{ $label }}</div>
                            <div class="ccu-bar-track"><span class="ccu-bar-positive" style="width:{{ ($count / $maxObserver) * 100 }}%"></span></div>
                            <div class="ccu-bar-count">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="ccu-empty">No hay observadores para mostrar.</div>
                    @endforelse
                </div>
            </article>

            <article class="glass-card ccu-panel">
                <h3><i class="bi bi-info-circle-fill"></i> Lectura del resultado</h3>
                <p class="ccu-muted" style="font-size:.8rem;line-height:1.6;margin:0">
                    <strong style="color:#166534">Positiva:</strong> la selección contiene solo conductas seguras.
                    <br><strong style="color:#b91c1c">Negativa:</strong> contiene una conducta de riesgo o incumplimiento.
                    <br><strong style="color:#334155">Por revisar:</strong> Kizeo entregó una selección múltiple con ambos tipos; se mantiene separada para no alterar los indicadores.
                </p>
            </article>
        </section>

        <section class="glass-card ccu-panel" style="margin-bottom:1rem">
            <h3><i class="bi bi-clock-history"></i> Registros recientes</h3>
            <div class="ccu-table-wrap">
                <table class="ccu-table">
                    <thead><tr><th>Fecha</th><th>Centro</th><th>Trabajador observado</th><th>Conducta</th><th>Resultado</th><th>Medida</th><th>Observador</th></tr></thead>
                    <tbody>
                        @forelse($recent as $record)
                            <tr>
                                <td>{{ $record->fecha_observacion?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                                <td>{{ $record->centro ?: 'Sin centro' }}</td>
                                <td><strong>{{ $record->trabajador_nombre ?: 'Sin identificar' }}</strong><br><span class="ccu-muted">{{ $record->trabajador_cargo }}</span></td>
                                <td title="{{ $record->tipo_observacion }}">{{ \Illuminate\Support\Str::limit($record->tipo_observacion, 72) }}</td>
                                <td><span class="ccu-badge {{ $record->clasificacion === 'Negativa' ? 'negative' : ($record->clasificacion === 'Positiva' ? 'positive' : 'review') }}">{{ $record->clasificacion }}</span></td>
                                <td>{{ $record->medida_control ?: 'Sin medida' }}</td>
                                <td>{{ $record->observador_nombre ?: 'Sin identificar' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ccu-empty">No hay registros para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
