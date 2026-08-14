@extends('layouts.app')
@section('title', 'Dashboard ejecutivo Carta Gantt')
@section('content')
@php
    $mesesCortos = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
    $resumen = $dashboard['resumen'];
    $serie = $dashboard['serie_mensual'];
    $maximoGrafico = max(1, (float) $serie->max('esperado_acumulado'), (float) $serie->max('real_acumulado'));
    $pasoX = $serie->count() > 1 ? 650 / ($serie->count() - 1) : 650;
    $puntosEsperado = $serie->map(function (array $fila, int $indice) use ($pasoX, $maximoGrafico) {
        return round(35 + ($indice * $pasoX), 1) . ',' . round(178 - (($fila['esperado_acumulado'] / $maximoGrafico) * 132), 1);
    })->join(' ');
    $puntosReal = $serie->map(function (array $fila, int $indice) use ($pasoX, $maximoGrafico) {
        return round(35 + ($indice * $pasoX), 1) . ',' . round(178 - (($fila['real_acumulado'] / $maximoGrafico) * 132), 1);
    })->join(' ');
    $estados = [
        'al_dia' => ['texto' => 'Al día', 'clase' => 'is-ok'],
        'en_riesgo' => ['texto' => 'En riesgo', 'clase' => 'is-risk'],
        'critico' => ['texto' => 'Crítico', 'clase' => 'is-critical'],
        'sin_meta' => ['texto' => 'Sin meta a la fecha', 'clase' => 'is-neutral'],
    ];
    $mesCritico = $dashboard['mes_critico'];
    $fechaCorte = $dashboard['fecha_corte'];
@endphp

<style>
.gantt-exec-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem}
.gantt-exec-header-actions{display:flex;gap:.55rem;flex-wrap:wrap;justify-content:flex-end}
.gantt-exec-eyebrow{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:var(--primary-color);font-weight:800;margin-bottom:.4rem}
.gantt-exec-filter{display:grid;grid-template-columns:minmax(150px,.7fr) minmax(220px,1.5fr) minmax(220px,1.5fr) minmax(150px,.75fr) auto;gap:.7rem;align-items:end;margin-bottom:1rem}
.gantt-exec-filter .btn-premium{height:42px;justify-content:center}
.gantt-exec-note{display:flex;align-items:flex-start;gap:.65rem;border:1px solid rgba(79,70,229,.17);border-left:3px solid var(--primary-color);border-radius:10px;background:rgba(79,70,229,.045);padding:.75rem .9rem;margin-bottom:1.15rem;color:var(--text-color)}
.gantt-exec-note i{color:var(--primary-color);font-size:1rem;margin-top:.08rem}
.gantt-exec-note strong{font-size:.8rem;display:block;margin-bottom:.12rem}.gantt-exec-note span{font-size:.75rem;color:var(--text-muted);line-height:1.45}
.gantt-exec-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem;margin-bottom:1rem}
.gantt-exec-kpi{padding:1rem 1.05rem;min-height:122px;display:flex;flex-direction:column;justify-content:space-between;border-top:3px solid transparent}
.gantt-exec-kpi.is-primary{border-top-color:var(--primary-color)}.gantt-exec-kpi.is-muted{border-top-color:#94a3b8}.gantt-exec-kpi.is-risk{border-top-color:#d97706}.gantt-exec-kpi.is-critical{border-top-color:#c2410c}
.gantt-exec-kpi-label{font-size:.68rem;letter-spacing:.07em;text-transform:uppercase;font-weight:800;color:var(--text-muted)}
.gantt-exec-kpi-value{font-size:1.85rem;line-height:1;font-weight:800;letter-spacing:-.05em;color:var(--text-color);margin:.45rem 0}.gantt-exec-kpi-value.negative{color:#b45309}
.gantt-exec-kpi-sub{font-size:.74rem;color:var(--text-muted)}
.gantt-exec-grid{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(290px,.75fr);gap:1rem;margin-bottom:1rem}
.gantt-exec-card{padding:1.1rem}.gantt-exec-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.8rem}.gantt-exec-card-head h3{font-size:1rem;margin:0}.gantt-exec-card-head p{font-size:.76rem;color:var(--text-muted);margin:.22rem 0 0;line-height:1.4}
.gantt-exec-legend{display:flex;gap:.75rem;flex-wrap:wrap;font-size:.7rem;color:var(--text-muted);padding:.2rem 0 .35rem}.gantt-exec-legend span{display:inline-flex;align-items:center;gap:.35rem}.gantt-exec-legend i{width:18px;height:3px;border-radius:999px;display:block}.gantt-exec-legend .expected{background:#64748b}.gantt-exec-legend .real{background:var(--primary-color)}
.gantt-exec-chart{width:100%;height:auto;display:block}.gantt-exec-chart-grid{stroke:var(--surface-border);stroke-dasharray:3 4}.gantt-exec-chart-label{fill:var(--text-muted);font-size:10px}.gantt-exec-chart-expected{fill:none;stroke:#64748b;stroke-width:3;stroke-dasharray:6 5}.gantt-exec-chart-real{fill:none;stroke:var(--primary-color);stroke-width:4}.gantt-exec-chart-point{fill:var(--card-bg);stroke:var(--primary-color);stroke-width:3}
.gantt-exec-signals{display:grid;gap:.7rem}.gantt-exec-signal{padding:.78rem .85rem;border:1px solid var(--surface-border);border-radius:9px}.gantt-exec-signal small{display:block;text-transform:uppercase;letter-spacing:.06em;font-weight:800;font-size:.62rem;color:var(--text-muted);margin-bottom:.28rem}.gantt-exec-signal strong{display:block;font-size:.87rem;line-height:1.35}.gantt-exec-signal span{display:block;font-size:.73rem;line-height:1.4;color:var(--text-muted);margin-top:.18rem}
.gantt-exec-table-card{padding:1.1rem;margin-bottom:1rem}.gantt-exec-table-card:last-child{margin-bottom:0}.gantt-exec-table{min-width:900px}.gantt-exec-table th{white-space:nowrap}.gantt-exec-person{display:flex;flex-direction:column;gap:.16rem}.gantt-exec-person strong{font-size:.82rem}.gantt-exec-person span{font-size:.68rem;color:var(--text-muted)}
.gantt-exec-progress{display:flex;align-items:center;gap:.55rem;min-width:155px}.gantt-exec-progress-track{width:92px;height:7px;border-radius:999px;background:var(--surface-bg,#eef2f7);overflow:hidden}.gantt-exec-progress-fill{height:100%;border-radius:999px;background:var(--primary-color)}.gantt-exec-progress-fill.is-risk{background:#d97706}.gantt-exec-progress-fill.is-critical{background:#c2410c}.gantt-exec-progress b{font-size:.76rem;min-width:36px}
.gantt-exec-badge{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .5rem;font-size:.66rem;font-weight:800;white-space:nowrap}.gantt-exec-badge.is-ok{background:rgba(16,185,129,.11);color:#047857}.gantt-exec-badge.is-risk{background:rgba(245,158,11,.13);color:#a16207}.gantt-exec-badge.is-critical{background:rgba(194,65,12,.11);color:#c2410c}.gantt-exec-badge.is-neutral{background:rgba(100,116,139,.11);color:#475569}
.gantt-exec-breach{font-size:.78rem;font-weight:800;color:#b45309}.gantt-exec-muted{font-size:.72rem;color:var(--text-muted)}
.gantt-exec-empty{text-align:center;padding:2.8rem 1rem;color:var(--text-muted)}.gantt-exec-empty i{font-size:1.5rem;display:block;margin-bottom:.55rem;color:var(--primary-color)}
@media(max-width:1080px){.gantt-exec-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.gantt-exec-filter .btn-premium{width:100%}.gantt-exec-grid{grid-template-columns:1fr}.gantt-exec-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:680px){.gantt-exec-header{flex-direction:column}.gantt-exec-header-actions{justify-content:flex-start}.gantt-exec-filter,.gantt-exec-kpis{grid-template-columns:1fr}.gantt-exec-kpi{min-height:96px}.gantt-exec-card,.gantt-exec-table-card{padding:.9rem}.gantt-exec-note{padding:.7rem}.gantt-exec-chart{min-width:580px}.gantt-exec-chart-wrap{overflow-x:auto;padding-bottom:.25rem}}
</style>

<div class="page-container">
    <div class="gantt-exec-header">
        <div>
            <div class="gantt-exec-eyebrow">Coordinación SST</div>
            <h2 class="page-heading"><i class="bi bi-bar-chart-line" style="color:var(--primary-color)"></i> Dashboard ejecutivo Carta Gantt</h2>
            <p class="page-subheading">Cumplimiento real frente a la carga que debía estar ejecutada a la fecha.</p>
        </div>
        <div class="gantt-exec-header-actions">
            <a href="{{ route('carta-gantt.index') }}" class="btn-ghost"><i class="bi bi-calendar3"></i> Ver programas</a>
            <a href="{{ route('carta-gantt.mis-tareas') }}" class="btn-ghost"><i class="bi bi-list-task"></i> Mis tareas</a>
        </div>
    </div>

    @include('partials._alerts')

    <div class="glass-card" style="padding:1rem 1.05rem">
        <form method="GET" action="{{ route('carta-gantt.dashboard') }}" class="gantt-exec-filter">
            <div class="filter-group">
                <label>Año</label>
                <select class="form-input" name="anio">
                    @foreach($anios as $opcionAnio)
                        <option value="{{ $opcionAnio }}" @selected((int) $opcionAnio === (int) $anio)>{{ $opcionAnio }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Carta Gantt</label>
                <select class="form-input" name="programa_id">
                    <option value="">Todas las cartas del año</option>
                    @foreach($programasDisponibles as $programaDisponible)
                        <option value="{{ $programaDisponible->id }}" @selected(request('programa_id') == $programaDisponible->id)>
                            {{ $programaDisponible->codigo }} · {{ $programaDisponible->titulo }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Responsable de la Gantt</label>
                <select class="form-input" name="responsable_gantt_id">
                    <option value="">Todos los responsables</option>
                    @foreach($responsablesGantt as $responsable)
                        <option value="{{ $responsable->id }}" @selected(request('responsable_gantt_id') == $responsable->id)>{{ $responsable->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Estado</label>
                <select class="form-input" name="estado">
                    <option value="ACTIVO" @selected($estado === 'ACTIVO')>Activas</option>
                    <option value="BORRADOR" @selected($estado === 'BORRADOR')>Borradores</option>
                    <option value="CERRADO" @selected($estado === 'CERRADO')>Cerradas</option>
                    <option value="" @selected($estado === '')>Todos los estados</option>
                </select>
            </div>
            <button class="btn-premium" type="submit"><i class="bi bi-funnel"></i> Aplicar</button>
        </form>
    </div>

    <div class="gantt-exec-note">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Meta calculada desde el plan real</strong>
            <span>La meta no es fija: pondera cada actividad por su cantidad y por el mes en que fue programada. Los meses cerrados cuentan completos y {{ $fechaCorte->translatedFormat('F') }} se prorratea al día {{ $fechaCorte->day }}.</span>
        </div>
    </div>

    <div class="gantt-exec-kpis">
        <div class="glass-card gantt-exec-kpi is-muted">
            <span class="gantt-exec-kpi-label">Meta acumulada</span>
            <strong class="gantt-exec-kpi-value">{{ number_format($resumen['esperado_porcentaje'], 1, ',', '.') }}%</strong>
            <span class="gantt-exec-kpi-sub">{{ number_format($resumen['esperado'], 1, ',', '.') }} ejecuciones esperadas</span>
        </div>
        <div class="glass-card gantt-exec-kpi is-primary">
            <span class="gantt-exec-kpi-label">Avance real</span>
            <strong class="gantt-exec-kpi-value">{{ number_format($resumen['real_porcentaje'], 1, ',', '.') }}%</strong>
            <span class="gantt-exec-kpi-sub">{{ number_format($resumen['real'], 0, ',', '.') }} de {{ number_format($resumen['planificado'], 0, ',', '.') }} ejecuciones planificadas</span>
        </div>
        <div class="glass-card gantt-exec-kpi {{ $resumen['brecha_puntos'] < 0 ? 'is-risk' : 'is-primary' }}">
            <span class="gantt-exec-kpi-label">Brecha contra meta</span>
            <strong class="gantt-exec-kpi-value {{ $resumen['brecha_puntos'] < 0 ? 'negative' : '' }}">{{ $resumen['brecha_puntos'] > 0 ? '+' : '' }}{{ number_format($resumen['brecha_puntos'], 1, ',', '.') }} pp</strong>
            <span class="gantt-exec-kpi-sub">{{ number_format($resumen['brecha_unidades'], 1, ',', '.') }} ejecuciones pendientes al corte</span>
        </div>
        <div class="glass-card gantt-exec-kpi {{ $dashboard['responsables_en_riesgo'] > 0 ? 'is-critical' : 'is-primary' }}">
            <span class="gantt-exec-kpi-label">Focos de seguimiento</span>
            <strong class="gantt-exec-kpi-value">{{ $dashboard['responsables_en_riesgo'] }}</strong>
            <span class="gantt-exec-kpi-sub">Responsables bajo la meta · {{ $dashboard['programas_en_riesgo'] }} cartas con brecha</span>
        </div>
    </div>

    @if($resumen['planificado'] > 0)
    <div class="gantt-exec-grid">
        <section class="glass-card gantt-exec-card">
            <div class="gantt-exec-card-head">
                <div>
                    <h3>Avance acumulado del año</h3>
                    <p>Comparación entre carga esperada y ejecución registrada por mes.</p>
                </div>
                <span class="gantt-exec-badge {{ $estados[$resumen['estado']]['clase'] }}">{{ $estados[$resumen['estado']]['texto'] }}</span>
            </div>
            <div class="gantt-exec-legend"><span><i class="expected"></i>Meta acumulada</span><span><i class="real"></i>Avance acumulado</span></div>
            <div class="gantt-exec-chart-wrap">
                <svg class="gantt-exec-chart" viewBox="0 0 720 215" role="img" aria-label="Gráfico de avance real versus meta acumulada">
                    <line class="gantt-exec-chart-grid" x1="35" y1="46" x2="685" y2="46"></line>
                    <line class="gantt-exec-chart-grid" x1="35" y1="112" x2="685" y2="112"></line>
                    <line class="gantt-exec-chart-grid" x1="35" y1="178" x2="685" y2="178"></line>
                    <polyline class="gantt-exec-chart-expected" points="{{ $puntosEsperado }}"></polyline>
                    <polyline class="gantt-exec-chart-real" points="{{ $puntosReal }}"></polyline>
                    @foreach($serie as $indice => $fila)
                        @php $x = 35 + ($indice * $pasoX); $y = 178 - (($fila['real_acumulado'] / $maximoGrafico) * 132); @endphp
                        <circle class="gantt-exec-chart-point" cx="{{ $x }}" cy="{{ $y }}" r="3.4"></circle>
                        <text class="gantt-exec-chart-label" x="{{ $x }}" y="202" text-anchor="middle">{{ $mesesCortos[$fila['mes']] }}</text>
                    @endforeach
                </svg>
            </div>
        </section>

        <aside class="glass-card gantt-exec-card">
            <div class="gantt-exec-card-head"><div><h3>Lectura ejecutiva</h3><p>Señales para priorizar seguimiento.</p></div></div>
            <div class="gantt-exec-signals">
                <div class="gantt-exec-signal">
                    <small>Mes de mayor brecha</small>
                    <strong>{{ $mesCritico ? $mesesCortos[$mesCritico['mes']] . ' · faltan ' . number_format($mesCritico['brecha'], 1, ',', '.') . ' ejecuciones' : 'Sin meses exigibles aún' }}</strong>
                    <span>La carga esperada de ese mes no quedó cubierta por el avance registrado.</span>
                </div>
                <div class="gantt-exec-signal">
                    <small>Programa más comprometido</small>
                    @php $programaCritico = $dashboard['programas']->first(); @endphp
                    <strong>{{ $programaCritico['programa']?->titulo ?? 'Sin programas con carga planificada' }}</strong>
                    <span>
                        @if($programaCritico && $programaCritico['brecha_unidades'] > 0)
                            Brecha de {{ number_format($programaCritico['brecha_unidades'], 1, ',', '.') }} ejecuciones y {{ number_format(abs($programaCritico['brecha_puntos']), 1, ',', '.') }} pp bajo la meta.
                        @else
                            No registra brecha contra la meta a la fecha.
                        @endif
                    </span>
                </div>
                <div class="gantt-exec-signal">
                    <small>Responsable a priorizar</small>
                    @php $responsableCritico = $dashboard['responsables']->first(); @endphp
                    <strong>{{ $responsableCritico['responsable'] ?? 'Sin responsables con carga planificada' }}</strong>
                    <span>
                        @if($responsableCritico && $responsableCritico['brecha_unidades'] > 0)
                            Cumple {{ number_format($responsableCritico['cumplimiento'] ?? 0, 1, ',', '.') }}% de su meta exigible.
                        @else
                            No presenta desviación exigible al corte.
                        @endif
                    </span>
                </div>
            </div>
        </aside>
    </div>

    <section class="glass-card gantt-exec-table-card">
        <div class="gantt-exec-card-head">
            <div><h3>Resultado por responsable de actividad</h3><p>Detalle de cumplimiento individual, brecha y mes que requiere seguimiento.</p></div>
            <span class="gantt-exec-muted">{{ $dashboard['responsables']->count() }} responsable(s) con carga</span>
        </div>
        <div class="glass-table-container">
            <table class="glass-table gantt-exec-table">
                <thead><tr><th>Responsable</th><th>Cartas involucradas</th><th>Avance real</th><th>Meta a la fecha</th><th>Brecha</th><th>Mes crítico</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($dashboard['responsables'] as $fila)
                    @php $estadoFila = $estados[$fila['estado']]; @endphp
                    <tr>
                        <td><div class="gantt-exec-person"><strong>{{ $fila['responsable'] }}</strong><span>{{ $fila['responsable_id'] ? 'Responsable de actividad' : 'Requiere asignación' }}</span></div></td>
                        <td>{{ count($fila['programas'] ?? []) }}</td>
                        <td><div class="gantt-exec-progress"><div class="gantt-exec-progress-track"><div class="gantt-exec-progress-fill {{ $estadoFila['clase'] }}" style="width:{{ min(100, $fila['real_porcentaje']) }}%"></div></div><b>{{ number_format($fila['real_porcentaje'], 1, ',', '.') }}%</b></div></td>
                        <td>{{ number_format($fila['esperado_porcentaje'], 1, ',', '.') }}%</td>
                        <td><span class="gantt-exec-breach">{{ $fila['brecha_puntos'] > 0 ? '+' : '' }}{{ number_format($fila['brecha_puntos'], 1, ',', '.') }} pp</span><div class="gantt-exec-muted">Faltan {{ number_format($fila['brecha_unidades'], 1, ',', '.') }}</div></td>
                        <td>{{ $fila['mes_critico'] ? $mesesCortos[$fila['mes_critico']['mes']] . ' · ' . number_format($fila['mes_critico']['brecha'], 1, ',', '.') . ' pend.' : 'Sin meta a la fecha' }}</td>
                        <td><span class="gantt-exec-badge {{ $estadoFila['clase'] }}">{{ $estadoFila['texto'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="gantt-exec-empty"><i class="bi bi-clipboard-data"></i>No hay actividades programadas para los filtros seleccionados.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="glass-card gantt-exec-table-card">
        <div class="gantt-exec-card-head"><div><h3>Desempeño por Carta Gantt</h3><p>Permite identificar en qué programa y mes se concentra el incumplimiento.</p></div><span class="gantt-exec-muted">{{ $dashboard['programas']->count() }} carta(s) medibles</span></div>
        <div class="glass-table-container">
            <table class="glass-table gantt-exec-table">
                <thead><tr><th>Carta Gantt</th><th>Responsable</th><th>Avance real</th><th>Meta</th><th>Desviación</th><th>Mes de mayor baja</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($dashboard['programas'] as $fila)
                    @php $estadoFila = $estados[$fila['estado']]; $programa = $fila['programa']; @endphp
                    <tr>
                        <td><div class="gantt-exec-person"><strong>{{ $programa->titulo }}</strong><span>{{ $programa->codigo }} · {{ $programa->anio }}</span></div></td>
                        <td>{{ $programa->responsable?->nombre_completo ?? 'Sin responsable' }}</td>
                        <td><div class="gantt-exec-progress"><div class="gantt-exec-progress-track"><div class="gantt-exec-progress-fill {{ $estadoFila['clase'] }}" style="width:{{ min(100, $fila['real_porcentaje']) }}%"></div></div><b>{{ number_format($fila['real_porcentaje'], 1, ',', '.') }}%</b></div></td>
                        <td>{{ number_format($fila['esperado_porcentaje'], 1, ',', '.') }}%</td>
                        <td><span class="gantt-exec-breach">{{ $fila['brecha_puntos'] > 0 ? '+' : '' }}{{ number_format($fila['brecha_puntos'], 1, ',', '.') }} pp</span></td>
                        <td>{{ $fila['mes_critico'] ? $mesesCortos[$fila['mes_critico']['mes']] . ' · faltan ' . number_format($fila['mes_critico']['brecha'], 1, ',', '.') : 'Sin meta a la fecha' }}</td>
                        <td><span class="gantt-exec-badge {{ $estadoFila['clase'] }}">{{ $estadoFila['texto'] }}</span></td>
                        <td><a class="icon-btn" href="{{ route('carta-gantt.show', $programa) }}" title="Abrir Carta Gantt"><i class="bi bi-arrow-up-right"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="gantt-exec-empty"><i class="bi bi-bar-chart-line"></i>No hay Cartas Gantt con programación para estos filtros.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @else
    <section class="glass-card gantt-exec-card gantt-exec-empty"><i class="bi bi-calendar-x"></i><strong>No hay carga planificada para medir todavía.</strong><span>Selecciona otra Carta Gantt, estado o año; el dashboard se alimenta de los meses programados de cada actividad.</span></section>
    @endif
</div>
@endsection
