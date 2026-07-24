@extends('layouts.app')

@section('title', 'Inspecciones PDR')

@section('content')
@php
    $options = $analytics['filter_options'] ?? [];
    $recent = $analytics['recent'] ?? collect();
    $maxCenter = max([1, ...array_values($analytics['centros'] ?? [])]);
    $maxArea = max([1, ...array_values($analytics['areas'] ?? [])]);
    $maxFrequency = max([1, ...array_values($analytics['frecuencias'] ?? [])]);
    $maxInspector = max([1, ...array_values($analytics['inspectores'] ?? [])]);
    $objectives = $analytics['objetivos'] ?? [];
    $objectiveTotal = max(1, array_sum($objectives));
    $palette = ['#ff6b35', '#2563eb', '#8b5cf6', '#16a34a', '#d97706', '#64748b'];
    $cursor = 0;
    $stops = [];
    foreach ($objectives as $label => $count) {
        $end = $cursor + (($count / $objectiveTotal) * 100);
        $color = $palette[count($stops) % count($palette)];
        $stops[] = "{$color} {$cursor}% {$end}%";
        $cursor = $end;
    }
    $objectiveGradient = $stops ? 'conic-gradient(' . implode(', ', $stops) . ')' : '#e2e8f0';
@endphp

<style>
    .insp-dashboard { max-width:1540px; margin:0 auto; }
    .insp-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem; }
    .insp-header h2 { margin:0; color:var(--text-primary); font-size:1.5rem; }
    .insp-header p { margin:.3rem 0 0; color:var(--text-muted); font-size:.84rem; }
    .insp-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
    .insp-filter { padding:1rem; margin-bottom:1rem; }
    .insp-filter-grid { display:grid; grid-template-columns:repeat(9,minmax(120px,1fr)); gap:.65rem; align-items:end; }
    .insp-filter label { display:block; margin-bottom:.25rem; color:var(--text-muted); font-size:.71rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
    .insp-filter select,.insp-filter input { width:100%; min-height:38px; padding:.45rem .55rem; color:var(--text-primary); background:var(--input-bg,var(--card-bg,#fff)); border:1px solid var(--border-color,#d9e0ea); border-radius:6px; font-size:.8rem; }
    .insp-kpis { display:grid; grid-template-columns:repeat(6,minmax(140px,1fr)); gap:.75rem; margin-bottom:1rem; }
    .insp-kpi { padding:1rem; min-height:104px; border-left:4px solid #8b5cf6; }
    .insp-kpi.orange { border-left-color:#ff6b35; }.insp-kpi.red { border-left-color:#dc2626; }.insp-kpi.green { border-left-color:#16a34a; }.insp-kpi.blue { border-left-color:#2563eb; }
    .insp-kpi .label { color:var(--text-muted); font-size:.71rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }.insp-kpi .value { color:var(--text-primary); margin-top:.35rem; font-size:1.65rem; line-height:1.1; font-weight:800; }.insp-kpi .hint { margin-top:.25rem; color:var(--text-muted); font-size:.72rem; }
    .insp-grid { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(340px,.75fr); gap:1rem; margin-bottom:1rem; }.insp-grid-three { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; margin-bottom:1rem; }
    .insp-panel { min-width:0; padding:1rem; }.insp-panel h3 { display:flex; gap:.45rem; align-items:center; margin:0 0:.85rem; color:var(--text-primary); font-size:.91rem; }.insp-panel h3 i { color:var(--accent-color); }
    .insp-bars { display:grid; gap:.62rem; }.insp-bar-row { display:grid; grid-template-columns:minmax(104px,1.5fr) minmax(80px,2fr) 34px; gap:.5rem; align-items:center; }.insp-bar-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-primary); font-size:.75rem; }.insp-bar-track { height:10px; overflow:hidden; border-radius:3px; background:rgba(148,163,184,.2); }.insp-bar { display:block; height:100%; background:#8b5cf6; }.insp-bar.orange { background:#ff6b35; }.insp-bar.green { background:#16a34a; }.insp-bar.blue { background:#2563eb; }.insp-count { color:var(--text-muted); font-size:.75rem; text-align:right; font-variant-numeric:tabular-nums; }
    .insp-trend { display:grid; grid-template-columns:repeat(auto-fit,minmax(72px,1fr)); gap:.5rem; align-items:end; min-height:166px; }.insp-trend-item { min-width:0; text-align:center; }.insp-trend-bars { display:flex; height:108px; gap:3px; justify-content:center; align-items:flex-end; border-bottom:1px solid var(--border-color,#d9e0ea); }.insp-trend-bar { width:16px; min-height:3px; border-radius:3px 3px 0 0; }.insp-trend-label { margin-top:.35rem; color:var(--text-muted); font-size:.66rem; white-space:nowrap; }.insp-legend { display:flex; gap:.8rem; margin-top:.7rem; color:var(--text-muted); font-size:.72rem; }.insp-legend span::before { content:''; display:inline-block; width:9px; height:9px; margin-right:.3rem; border-radius:2px; }.insp-legend .i::before { background:#8b5cf6; }.insp-legend .c::before { background:#ff6b35; }.insp-legend .m::before { background:#16a34a; }
    .insp-donut-layout { display:flex; justify-content:center; align-items:center; min-height:175px; gap:1.2rem; flex-wrap:wrap; }.insp-donut { position:relative; width:142px; height:142px; flex:0 0 auto; border-radius:50%; background:var(--objective-gradient); }.insp-donut::after { content:''; position:absolute; inset:27px; border:1px solid var(--border-color,#d9e0ea); border-radius:50%; background:var(--card-bg,#fff); }.insp-donut-center { position:absolute; z-index:1; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; color:var(--text-primary); font-size:1.4rem; font-weight:800; }.insp-donut-center small { margin-top:.1rem; color:var(--text-muted); font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }.insp-donut-legend { display:grid; min-width:155px; gap:.45rem; }.insp-donut-line { display:grid; grid-template-columns:9px minmax(0,1fr) auto; align-items:center; gap:.4rem; color:var(--text-primary); font-size:.75rem; }.insp-swatch { width:9px; height:9px; border-radius:50%; }.insp-donut-line span:last-child { color:var(--text-muted); font-variant-numeric:tabular-nums; }
    .insp-table-wrap { overflow-x:auto; }.insp-table { width:100%; min-width:950px; border-collapse:collapse; }.insp-table th { padding:.55rem .6rem; color:var(--text-muted); font-size:.7rem; text-align:left; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; border-bottom:1px solid var(--border-color,#d9e0ea); }.insp-table td { padding:.65rem .6rem; vertical-align:top; color:var(--text-primary); font-size:.78rem; border-bottom:1px solid var(--border-color,#d9e0ea); }.insp-table tbody tr:last-child td { border-bottom:0; }.insp-muted { color:var(--text-muted); font-size:.72rem; }.insp-badge { display:inline-flex; border-radius:999px; padding:.18rem .48rem; background:#ede9fe; color:#5b21b6; font-size:.68rem; font-weight:800; white-space:nowrap; }.insp-empty { padding:2.2rem 1rem; color:var(--text-muted); text-align:center; }
    @media(max-width:1240px) { .insp-filter-grid { grid-template-columns:repeat(4,minmax(150px,1fr)); }.insp-kpis { grid-template-columns:repeat(3,1fr); }.insp-grid,.insp-grid-three { grid-template-columns:1fr; } }
    @media(max-width:720px) { .insp-header { flex-direction:column; }.insp-actions { width:100%; }.insp-filter-grid,.insp-kpis { grid-template-columns:1fr 1fr; }.insp-kpi { min-height:92px; } }
</style>

<div class="page-container insp-dashboard">
    <div class="insp-header">
        <div>
            <h2><i class="bi bi-clipboard2-check-fill" style="color:var(--accent-color)"></i> PDR Inspección Preventiva</h2>
            <p>
                Indicadores del formulario Kizeo: condiciones, medidas correctivas y evidencias.
                @if($syncInfo)<span title="Última sincronización local"><i class="bi bi-database-check" style="color:#16a34a"></i> {{ number_format($syncInfo['total']) }} inspecciones · actualizado {{ \Carbon\Carbon::parse($syncInfo['last_sync'])->diffForHumans() }}</span>
                @else <span><i class="bi bi-cloud-arrow-down"></i> Aún no se han sincronizado datos.</span>@endif
            </p>
        </div>
        <div class="insp-actions">
            <a href="{{ route('pdr-inspecciones-dashboard.excel', $filters) }}" class="btn-secondary" style="font-size:.8rem;text-decoration:none" title="Descarga resumen y detalle de las inspecciones"><i class="bi bi-file-earmark-excel"></i> Descargar Excel</a>
            <a href="{{ route('pdr-inspecciones-dashboard.email-preview', $filters) }}" target="_blank" class="btn-secondary" style="font-size:.8rem;text-decoration:none" title="Revisa el correo antes de enviarlo"><i class="bi bi-envelope-open"></i> Vista previa</a>
            <form method="POST" action="{{ route('pdr-inspecciones-dashboard.email-self') }}" onsubmit="return confirm('Se enviará el reporte filtrado y su Excel adjunto a tu correo de usuario. ¿Continuar?')">@csrf @foreach($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button class="btn-secondary" style="font-size:.8rem" title="Envía el reporte a tu correo"><i class="bi bi-send-fill"></i> Enviar a mi correo</button></form>
            <a href="{{ route('pdr-inspecciones-dashboard.index', ['todo' => 1]) }}" class="btn-secondary" style="font-size:.8rem;text-decoration:none" title="Muestra el historial completo"><i class="bi bi-calendar3"></i> Ver historial</a>
            @if(auth()->user()->tieneAcceso('pdr_inspecciones_dashboard', 'puede_editar'))<form method="POST" action="{{ route('pdr-inspecciones-dashboard.sync') }}" onsubmit="return confirm('Se consultarán nuevamente las respuestas de Kizeo. ¿Continuar?')">@csrf<button class="btn-premium" style="font-size:.8rem" title="Actualiza desde Kizeo"><i class="bi bi-arrow-clockwise"></i> Sincronizar Kizeo</button></form>@endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

    <section class="glass-card insp-filter" aria-label="Filtros del dashboard">
        <form method="GET" action="{{ route('pdr-inspecciones-dashboard.index') }}" class="insp-filter-grid">
            @foreach(['centro' => ['Centro', 'centros'], 'objetivo' => ['Objetivo', 'objetivos'], 'inspector_nombre' => ['Inspector', 'inspectores'], 'responsable_area' => ['Responsable área', 'responsables'], 'frecuencia' => ['Frecuencia', 'frecuencias'], 'verificacion' => ['Verificación', 'verificaciones']] as $field => [$label, $optionKey])
                <div><label for="insp-{{ $field }}">{{ $label }}</label><select id="insp-{{ $field }}" name="{{ $field }}"><option value="">Todos{{ $field === 'centro' ? ' los centros' : '' }}</option>@foreach($options[$optionKey] ?? [] as $option)<option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ $option }}</option>@endforeach</select></div>
            @endforeach
            <div><label for="insp-desde">Desde</label><input id="insp-desde" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}"></div>
            <div><label for="insp-hasta">Hasta</label><input id="insp-hasta" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}"></div>
            <div style="display:flex;gap:.45rem"><button type="submit" class="btn-premium" style="font-size:.8rem;flex:1"><i class="bi bi-funnel-fill"></i> Aplicar</button><a href="{{ route('pdr-inspecciones-dashboard.index', ['todo' => 1]) }}" class="btn-secondary" style="display:inline-flex;align-items:center;text-decoration:none" title="Quitar filtros"><i class="bi bi-x-lg"></i></a></div>
        </form>
    </section>

    @if(!$hasData)
        <section class="glass-card insp-empty"><i class="bi bi-cloud-arrow-down" style="font-size:1.7rem;color:var(--accent-color)"></i><p style="margin:.7rem 0 .25rem;font-weight:700;color:var(--text-primary)">El dashboard está listo para recibir datos.</p><p style="margin:0">Un usuario con permiso de edición debe sincronizar el formulario desde Kizeo.</p></section>
    @else
        <section class="insp-kpis" aria-label="Indicadores principales">
            <article class="glass-card insp-kpi"><div class="label">Inspecciones</div><div class="value">{{ number_format($analytics['total']) }}</div><div class="hint">En el período filtrado</div></article>
            <article class="glass-card insp-kpi orange"><div class="label">Condiciones</div><div class="value">{{ number_format($analytics['condiciones']) }}</div><div class="hint">Reportadas en inspecciones</div></article>
            <article class="glass-card insp-kpi red"><div class="label">Medidas</div><div class="value">{{ number_format($analytics['medidas']) }}</div><div class="hint">Correctivas / preventivas</div></article>
            <article class="glass-card insp-kpi green"><div class="label">Acción inmediata</div><div class="value">{{ number_format($analytics['inmediatas']) }}</div><div class="hint">{{ number_format($analytics['porcentaje_inmediata'], 1) }}% de las medidas</div></article>
            <article class="glass-card insp-kpi blue"><div class="label">Evidencias</div><div class="value">{{ number_format($analytics['evidencias']) }}</div><div class="hint">Fotografías informadas</div></article>
            <article class="glass-card insp-kpi"><div class="label">Centros activos</div><div class="value">{{ number_format($analytics['centros_activos']) }}</div><div class="hint">Con inspecciones</div></article>
        </section>

        <section class="insp-grid">
            <article class="glass-card insp-panel"><h3><i class="bi bi-bar-chart-line-fill"></i> Tendencia mensual</h3>
                @if(!empty($analytics['by_month'])) @php $maxMonth = max(array_map(fn($month) => max($month['inspecciones'], $month['condiciones'], $month['medidas']), $analytics['by_month'])) ?: 1; @endphp
                    <div class="insp-trend">@foreach($analytics['by_month'] as $month)<div class="insp-trend-item" title="{{ $month['label'] }}: {{ $month['inspecciones'] }} inspecciones, {{ $month['condiciones'] }} condiciones y {{ $month['medidas'] }} medidas"><div class="insp-trend-bars"><span class="insp-trend-bar" style="height:{{ max(3, ($month['inspecciones'] / $maxMonth) * 100) }}%;background:#8b5cf6"></span><span class="insp-trend-bar" style="height:{{ max(3, ($month['condiciones'] / $maxMonth) * 100) }}%;background:#ff6b35"></span><span class="insp-trend-bar" style="height:{{ max(3, ($month['medidas'] / $maxMonth) * 100) }}%;background:#16a34a"></span></div><div class="insp-trend-label">{{ \Carbon\Carbon::createFromFormat('Y-m', $month['label'])->translatedFormat('M y') }}</div></div>@endforeach</div><div class="insp-legend"><span class="i">Inspecciones</span><span class="c">Condiciones</span><span class="m">Medidas</span></div>
                @else <div class="insp-empty">No hay registros para el período seleccionado.</div>@endif
            </article>
            <article class="glass-card insp-panel"><h3><i class="bi bi-bullseye"></i> Objetivo de inspección</h3><div class="insp-donut-layout"><div class="insp-donut" style="--objective-gradient:{{ $objectiveGradient }}"><div class="insp-donut-center">{{ number_format($analytics['total']) }}<small>registros</small></div></div><div class="insp-donut-legend">@forelse($objectives as $label => $count)<div class="insp-donut-line"><span class="insp-swatch" style="background:{{ $palette[$loop->index % count($palette)] }}"></span><span title="{{ $label }}">{{ $label }}</span><span>{{ $count }}</span></div>@empty<div class="insp-muted">Sin objetivo informado.</div>@endforelse</div></div></article>
        </section>

        <section class="insp-grid-three">
            <article class="glass-card insp-panel"><h3><i class="bi bi-buildings-fill"></i> Por centro</h3><div class="insp-bars">@forelse($analytics['centros'] as $label => $count)<div class="insp-bar-row" title="{{ $label }}: {{ $count }} inspecciones"><div class="insp-bar-label">{{ $label }}</div><div class="insp-bar-track"><span class="insp-bar" style="width:{{ ($count / $maxCenter) * 100 }}%"></span></div><div class="insp-count">{{ $count }}</div></div>@empty<div class="insp-empty">Sin centros para mostrar.</div>@endforelse</div></article>
            <article class="glass-card insp-panel"><h3><i class="bi bi-clock-history"></i> Frecuencia de medidas</h3><div class="insp-bars">@forelse($analytics['frecuencias'] as $label => $count)<div class="insp-bar-row" title="{{ $label }}: {{ $count }} medidas"><div class="insp-bar-label">{{ $label }}</div><div class="insp-bar-track"><span class="insp-bar orange" style="width:{{ ($count / $maxFrequency) * 100 }}%"></span></div><div class="insp-count">{{ $count }}</div></div>@empty<div class="insp-empty">Sin frecuencias informadas.</div>@endforelse</div></article>
            <article class="glass-card insp-panel"><h3><i class="bi bi-person-check-fill"></i> Inspectores con más registros</h3><div class="insp-bars">@forelse($analytics['inspectores'] as $label => $count)<div class="insp-bar-row" title="{{ $label }}: {{ $count }} inspecciones"><div class="insp-bar-label">{{ $label }}</div><div class="insp-bar-track"><span class="insp-bar green" style="width:{{ ($count / $maxInspector) * 100 }}%"></span></div><div class="insp-count">{{ $count }}</div></div>@empty<div class="insp-empty">Sin inspectores informados.</div>@endforelse</div></article>
        </section>

        <section class="insp-grid">
            <article class="glass-card insp-panel"><h3><i class="bi bi-signpost-split-fill"></i> Áreas inspeccionadas</h3><div class="insp-bars">@forelse($analytics['areas'] as $label => $count)<div class="insp-bar-row" title="{{ $label }}: {{ $count }} inspecciones"><div class="insp-bar-label">{{ $label }}</div><div class="insp-bar-track"><span class="insp-bar blue" style="width:{{ ($count / $maxArea) * 100 }}%"></span></div><div class="insp-count">{{ $count }}</div></div>@empty<div class="insp-empty">Sin áreas informadas.</div>@endforelse</div></article>
            <article class="glass-card insp-panel"><h3><i class="bi bi-check2-circle"></i> Verificación de medidas</h3><div class="insp-bars">@forelse($analytics['verificaciones'] as $label => $count)<div class="insp-bar-row" title="{{ $label }}: {{ $count }} medidas"><div class="insp-bar-label">{{ $label }}</div><div class="insp-bar-track"><span class="insp-bar green" style="width:{{ ($count / max([1, ...array_values($analytics['verificaciones'] ?? [])])) * 100 }}%"></span></div><div class="insp-count">{{ $count }}</div></div>@empty<div class="insp-empty">Sin verificación informada.</div>@endforelse</div></article>
        </section>

        <section class="glass-card insp-panel"><h3><i class="bi bi-table"></i> Inspecciones del período</h3><div class="insp-table-wrap"><table class="insp-table"><thead><tr><th>Fecha</th><th>Centro / área</th><th>Objetivo</th><th>Inspector</th><th>Condiciones</th><th>Evidencias</th><th>Medidas y seguimiento</th></tr></thead><tbody>@forelse($recent as $record)@php($frecuenciasRegistro = collect(explode('|', trim((string) $record->frecuencias_text, '|')))->filter()->unique()->implode(', '))<tr><td>{{ $record->fecha_inspeccion?->format('d/m/Y') ?? 'Sin fecha' }}<div class="insp-muted">{{ $record->hora_inspeccion }}</div></td><td><strong>{{ $record->centro ?: 'Sin centro' }}</strong><div class="insp-muted">{{ $record->area_inspeccionada ?: 'Sin área' }}</div></td><td><span class="insp-badge">{{ $record->objetivo ?: 'Sin objetivo' }}</span></td><td><strong>{{ $record->inspector_nombre ?: 'Sin identificar' }}</strong><div class="insp-muted">{{ $record->inspector_cargo }}</div></td><td><strong>{{ $record->condiciones_count }}</strong><div class="insp-muted" title="{{ $record->condiciones_resumen }}">{{ \Illuminate\Support\Str::limit($record->condiciones_resumen, 90) }}</div></td><td><strong>{{ $record->evidencias_count }}</strong><div class="insp-muted">fotografías</div></td><td><strong>{{ $record->medidas_count }}</strong><div class="insp-muted">{{ $frecuenciasRegistro ?: 'Sin frecuencia' }} · {{ $record->responsable_medida ?: 'Sin responsable' }}</div></td></tr>@empty<tr><td colspan="7" class="insp-empty">No hay inspecciones para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></section>
    @endif
</div>
@endsection
