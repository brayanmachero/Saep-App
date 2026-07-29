@extends('layouts.app')

@section('title', 'Entregas de Bodega')

@section('content')
@php
    $options = $analytics['filter_options'] ?? [];
    $recent = $analytics['recent'] ?? collect();
    $palette = ['#ff6b35', '#2563eb', '#16a34a', '#8b5cf6', '#d97706', '#0891b2', '#dc2626', '#64748b'];
    $maxCenter = max([1, ...array_values($analytics['centros'] ?? [])]);
    $maxArticle = max([1, ...array_values($analytics['articulos'] ?? [])]);
    $maxPeople = max([1, ...array_column($analytics['personas_top'] ?? [], 'unidades')]);
    $articleTotal = max(1, array_sum($analytics['articulos'] ?? []));
    $sizeTotal = max(1, array_sum($analytics['tallas'] ?? []));
    $articleCursor = 0;
    $articleStops = [];
    foreach (($analytics['articulos'] ?? []) as $count) {
        $end = $articleCursor + (($count / $articleTotal) * 100);
        $articleStops[] = $palette[count($articleStops) % count($palette)] . " {$articleCursor}% {$end}%";
        $articleCursor = $end;
    }
    $sizeCursor = 0;
    $sizeStops = [];
    foreach (($analytics['tallas'] ?? []) as $count) {
        $end = $sizeCursor + (($count / $sizeTotal) * 100);
        $sizeStops[] = $palette[count($sizeStops) % count($palette)] . " {$sizeCursor}% {$end}%";
        $sizeCursor = $end;
    }
@endphp

<style>
    .warehouse-dashboard{max-width:1540px;margin:0 auto}.warehouse-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}.warehouse-header h2{margin:0;color:var(--text-primary);font-size:1.5rem}.warehouse-header p{margin:.3rem 0 0;color:var(--text-muted);font-size:.84rem}.warehouse-actions{display:flex;gap:.5rem;flex-wrap:wrap}.warehouse-filter,.warehouse-panel,.warehouse-kpi{padding:1rem}.warehouse-filter{margin-bottom:1rem}.warehouse-filter-grid{display:grid;grid-template-columns:repeat(7,minmax(125px,1fr));gap:.65rem;align-items:end}.warehouse-filter label{display:block;margin-bottom:.25rem;color:var(--text-muted);font-size:.71rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}.warehouse-filter select,.warehouse-filter input{width:100%;min-height:38px;padding:.45rem .55rem;color:var(--text-primary);background:var(--input-bg,var(--card-bg,#fff));border:1px solid var(--border-color,#d9e0ea);border-radius:6px;font-size:.8rem}.warehouse-kpis{display:grid;grid-template-columns:repeat(6,minmax(135px,1fr));gap:.75rem;margin-bottom:1rem}.warehouse-kpi{min-height:102px;border-left:4px solid #8b5cf6}.warehouse-kpi.orange{border-left-color:#ff6b35}.warehouse-kpi.green{border-left-color:#16a34a}.warehouse-kpi.blue{border-left-color:#2563eb}.warehouse-kpi.red{border-left-color:#dc2626}.warehouse-kpi .label{color:var(--text-muted);font-size:.71rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}.warehouse-kpi .value{margin-top:.35rem;color:var(--text-primary);font-size:1.65rem;line-height:1.1;font-weight:800}.warehouse-kpi .hint{margin-top:.25rem;color:var(--text-muted);font-size:.72rem}.warehouse-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(340px,.8fr);gap:1rem;margin-bottom:1rem}.warehouse-grid-three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1rem}.warehouse-panel{min-width:0}.warehouse-panel h3{display:flex;align-items:center;gap:.45rem;margin:0 0:.85rem;color:var(--text-primary);font-size:.91rem}.warehouse-panel h3 i{color:var(--accent-color)}.warehouse-bars{display:grid;gap:.62rem}.warehouse-bar-row{display:grid;grid-template-columns:minmax(112px,1.5fr) minmax(75px,2fr) 38px;gap:.5rem;align-items:center}.warehouse-bar-label{overflow:hidden;color:var(--text-primary);font-size:.75rem;text-overflow:ellipsis;white-space:nowrap}.warehouse-bar-track{height:10px;overflow:hidden;border-radius:3px;background:rgba(148,163,184,.2)}.warehouse-bar{display:block;height:100%;background:#8b5cf6}.warehouse-bar.orange{background:#ff6b35}.warehouse-bar.green{background:#16a34a}.warehouse-bar.blue{background:#2563eb}.warehouse-count{color:var(--text-muted);font-size:.75rem;text-align:right;font-variant-numeric:tabular-nums}.warehouse-donut-layout{display:flex;justify-content:center;align-items:center;gap:1.1rem;min-height:170px;flex-wrap:wrap}.warehouse-donut{position:relative;flex:0 0 auto;width:136px;height:136px;border-radius:50%;background:var(--chart-data,#e2e8f0)}.warehouse-donut::after{position:absolute;inset:26px;border:1px solid var(--border-color,#d9e0ea);border-radius:50%;background:var(--card-bg,#fff);content:''}.warehouse-donut-center{position:absolute;z-index:1;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-primary);font-size:1.35rem;font-weight:800}.warehouse-donut-center small{margin-top:.1rem;color:var(--text-muted);font-size:.65rem;font-weight:700;text-transform:uppercase}.warehouse-donut-legend{display:grid;min-width:145px;gap:.43rem}.warehouse-donut-line{display:grid;grid-template-columns:9px minmax(0,1fr) auto;gap:.4rem;align-items:center;color:var(--text-primary);font-size:.74rem}.warehouse-swatch{width:9px;height:9px;border-radius:50%}.warehouse-donut-line span:nth-child(2){overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.warehouse-donut-line span:last-child{color:var(--text-muted);font-variant-numeric:tabular-nums}.warehouse-trend{display:grid;grid-template-columns:repeat(auto-fit,minmax(70px,1fr));gap:.5rem;align-items:end;min-height:165px}.warehouse-trend-item{min-width:0;text-align:center}.warehouse-trend-bars{display:flex;align-items:flex-end;justify-content:center;gap:3px;height:108px;border-bottom:1px solid var(--border-color,#d9e0ea)}.warehouse-trend-bar{width:17px;min-height:3px;border-radius:3px 3px 0 0}.warehouse-trend-label{margin-top:.35rem;color:var(--text-muted);font-size:.66rem;white-space:nowrap}.warehouse-legend{display:flex;gap:.8rem;margin-top:.7rem;color:var(--text-muted);font-size:.72rem}.warehouse-legend span::before{display:inline-block;width:9px;height:9px;margin-right:.3rem;border-radius:2px;content:''}.warehouse-legend .deliveries::before{background:#8b5cf6}.warehouse-legend .units::before{background:#ff6b35}.warehouse-table-wrap{overflow-x:auto}.warehouse-table{width:100%;min-width:920px;border-collapse:collapse}.warehouse-table th{padding:.55rem .6rem;color:var(--text-muted);font-size:.7rem;text-align:left;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;border-bottom:1px solid var(--border-color,#d9e0ea)}.warehouse-table td{padding:.65rem .6rem;color:var(--text-primary);font-size:.78rem;vertical-align:top;border-bottom:1px solid var(--border-color,#d9e0ea)}.warehouse-table tbody tr:last-child td{border-bottom:0}.warehouse-muted{color:var(--text-muted);font-size:.72rem}.warehouse-badge{display:inline-flex;border-radius:999px;padding:.18rem .48rem;background:#ede9fe;color:#5b21b6;font-size:.68rem;font-weight:800;white-space:nowrap}.warehouse-empty{padding:2.2rem 1rem;color:var(--text-muted);text-align:center}@media(max-width:1240px){.warehouse-filter-grid{grid-template-columns:repeat(3,minmax(150px,1fr))}.warehouse-kpis{grid-template-columns:repeat(3,1fr)}.warehouse-grid,.warehouse-grid-three{grid-template-columns:1fr}}@media(max-width:720px){.warehouse-header{flex-direction:column}.warehouse-filter-grid,.warehouse-kpis{grid-template-columns:1fr 1fr}.warehouse-kpi{min-height:92px}}
</style>

<div class="page-container warehouse-dashboard">
    <div class="warehouse-header">
        <div>
            <h2><i class="bi bi-box-seam-fill" style="color:var(--accent-color)"></i> Entregas de Bodega</h2>
            <p>Indicadores del formulario Kizeo <strong>Control de Entrega Bodega</strong>.
                @if($syncInfo)<span title="Última sincronización local"><i class="bi bi-database-check" style="color:#16a34a"></i> {{ number_format($syncInfo['total']) }} entregas · actualizado {{ \Carbon\Carbon::parse($syncInfo['last_sync'])->diffForHumans() }}</span>
                @else <span><i class="bi bi-cloud-arrow-down"></i> Aún no se han sincronizado datos.</span>@endif
            </p>
        </div>
        @if(auth()->user()->tieneAcceso('entregas_bodega_dashboard', 'puede_editar'))
        <div class="warehouse-actions"><form method="POST" action="{{ route('entregas-bodega-dashboard.sync') }}" onsubmit="return confirm('Se consultarán nuevamente las entregas de Kizeo. ¿Continuar?')">@csrf<button class="btn-premium" style="font-size:.8rem" title="Actualiza la información desde Kizeo"><i class="bi bi-arrow-clockwise"></i> Sincronizar Kizeo</button></form></div>
        @endif
    </div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

    <section class="glass-card warehouse-filter" aria-label="Filtros del dashboard">
        <form method="GET" action="{{ route('entregas-bodega-dashboard.index') }}" class="warehouse-filter-grid">
            @foreach(['centro' => ['Centro de costo', 'centros'], 'trabajador' => ['Persona', 'trabajadores'], 'articulo' => ['Artículo EPP', 'articulos'], 'talla' => ['Talla', 'tallas']] as $field => [$label, $optionKey])
                <div><label for="warehouse-{{ $field }}">{{ $label }}</label><select id="warehouse-{{ $field }}" name="{{ $field }}"><option value="">Todos</option>@foreach($options[$optionKey] ?? [] as $option)<option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ $option }}</option>@endforeach</select></div>
            @endforeach
            <div><label for="warehouse-desde">Desde</label><input id="warehouse-desde" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}"></div>
            <div><label for="warehouse-hasta">Hasta</label><input id="warehouse-hasta" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}"></div>
            <div style="display:flex;gap:.45rem"><button type="submit" class="btn-premium" style="font-size:.8rem;flex:1" title="Aplicar filtros"><i class="bi bi-funnel-fill"></i> Aplicar</button><a href="{{ route('entregas-bodega-dashboard.index', ['todo' => 1]) }}" class="btn-secondary" style="display:inline-flex;align-items:center;text-decoration:none" title="Quitar filtros y ver historial"><i class="bi bi-x-lg"></i></a></div>
        </form>
    </section>

    @if(! $hasData)
        <section class="glass-card warehouse-empty"><i class="bi bi-cloud-arrow-down" style="font-size:1.7rem;color:var(--accent-color)"></i><p style="margin:.7rem 0 .25rem;font-weight:700;color:var(--text-primary)">El dashboard está listo para recibir entregas.</p><p style="margin:0">Un usuario autorizado puede iniciar la sincronización desde Kizeo.</p></section>
    @else
        <section class="warehouse-kpis" aria-label="Indicadores principales">
            <article class="glass-card warehouse-kpi"><div class="label">Entregas</div><div class="value">{{ number_format($analytics['total']) }}</div><div class="hint">En el período filtrado</div></article>
            <article class="glass-card warehouse-kpi orange"><div class="label">Unidades EPP</div><div class="value">{{ number_format($analytics['unidades']) }}</div><div class="hint">Suma de cantidades entregadas</div></article>
            <article class="glass-card warehouse-kpi blue"><div class="label">Líneas de detalle</div><div class="value">{{ number_format($analytics['lineas']) }}</div><div class="hint">Artículo, talla y cantidad</div></article>
            <article class="glass-card warehouse-kpi green"><div class="label">Personas</div><div class="value">{{ number_format($analytics['personas']) }}</div><div class="hint">Con entrega registrada</div></article>
            <article class="glass-card warehouse-kpi red"><div class="label">Centros activos</div><div class="value">{{ number_format($analytics['centros_activos']) }}</div><div class="hint">Con movimiento de bodega</div></article>
            <article class="glass-card warehouse-kpi"><div class="label">Promedio</div><div class="value">{{ number_format($analytics['promedio_unidades'], 1) }}</div><div class="hint">Unidades por entrega</div></article>
        </section>

        <section class="warehouse-grid">
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-bar-chart-line-fill"></i> Evolución mensual</h3>
                @if(!empty($analytics['by_month'])) @php $maxMonth = max(array_map(fn($month) => max($month['entregas'], $month['unidades']), $analytics['by_month'])) ?: 1; @endphp
                <div class="warehouse-trend">@foreach($analytics['by_month'] as $month)<div class="warehouse-trend-item" title="{{ $month['label'] }}: {{ $month['entregas'] }} entregas y {{ $month['unidades'] }} unidades"><div class="warehouse-trend-bars"><span class="warehouse-trend-bar" style="height:{{ max(3, ($month['entregas'] / $maxMonth) * 100) }}%;background:#8b5cf6"></span><span class="warehouse-trend-bar" style="height:{{ max(3, ($month['unidades'] / $maxMonth) * 100) }}%;background:#ff6b35"></span></div><div class="warehouse-trend-label">{{ \Carbon\Carbon::createFromFormat('Y-m', $month['label'])->translatedFormat('M y') }}</div></div>@endforeach</div><div class="warehouse-legend"><span class="deliveries">Entregas</span><span class="units">Unidades</span></div>
                @else <div class="warehouse-empty">No hay registros para el período seleccionado.</div>@endif
            </article>
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-pie-chart-fill"></i> Artículos más entregados</h3><div class="warehouse-donut-layout"><div class="warehouse-donut" style="--chart-data:{{ $articleStops ? 'conic-gradient(' . implode(', ', $articleStops) . ')' : '#e2e8f0' }}"><div class="warehouse-donut-center">{{ number_format($analytics['unidades']) }}<small>unidades</small></div></div><div class="warehouse-donut-legend">@forelse($analytics['articulos'] as $label => $count)<div class="warehouse-donut-line" title="{{ $label }}: {{ $count }} unidades"><span class="warehouse-swatch" style="background:{{ $palette[$loop->index % count($palette)] }}"></span><span>{{ $label }}</span><span>{{ $count }}</span></div>@empty<div class="warehouse-muted">Sin artículos informados.</div>@endforelse</div></div></article>
        </section>

        <section class="warehouse-grid-three">
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-buildings-fill"></i> Entregas por centro</h3><div class="warehouse-bars">@forelse($analytics['centros'] as $label => $count)<div class="warehouse-bar-row" title="{{ $label }}: {{ $count }} entregas"><div class="warehouse-bar-label">{{ $label }}</div><div class="warehouse-bar-track"><span class="warehouse-bar" style="width:{{ ($count / $maxCenter) * 100 }}%"></span></div><div class="warehouse-count">{{ $count }}</div></div>@empty<div class="warehouse-empty">Sin centros para mostrar.</div>@endforelse</div></article>
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-person-check-fill"></i> Personas con más unidades</h3><div class="warehouse-bars">@forelse($analytics['personas_top'] as $person)<div class="warehouse-bar-row" title="{{ $person['nombre'] }}: {{ $person['unidades'] }} unidades en {{ $person['entregas'] }} entrega(s)"><div class="warehouse-bar-label">{{ $person['nombre'] }}</div><div class="warehouse-bar-track"><span class="warehouse-bar green" style="width:{{ ($person['unidades'] / $maxPeople) * 100 }}%"></span></div><div class="warehouse-count">{{ $person['unidades'] }}</div></div>@empty<div class="warehouse-empty">Sin personas identificadas.</div>@endforelse</div></article>
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-rulers"></i> Distribución por talla</h3><div class="warehouse-donut-layout"><div class="warehouse-donut" style="width:120px;height:120px;--chart-data:{{ $sizeStops ? 'conic-gradient(' . implode(', ', $sizeStops) . ')' : '#e2e8f0' }}"><div class="warehouse-donut-center" style="font-size:1.15rem">{{ number_format($sizeTotal) }}<small>unid.</small></div></div><div class="warehouse-donut-legend">@forelse($analytics['tallas'] as $label => $count)<div class="warehouse-donut-line"><span class="warehouse-swatch" style="background:{{ $palette[$loop->index % count($palette)] }}"></span><span>{{ $label }}</span><span>{{ $count }}</span></div>@empty<div class="warehouse-muted">Sin tallas informadas.</div>@endforelse</div></div></article>
        </section>

        <section class="warehouse-grid">
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-diagram-3-fill"></i> Relación persona y centro</h3><div class="warehouse-table-wrap"><table class="warehouse-table"><thead><tr><th>Persona</th><th>Centro</th><th>Entregas</th><th>Unidades</th></tr></thead><tbody>@forelse($analytics['relaciones'] as $relation)<tr><td><strong>{{ $relation['nombre'] }}</strong></td><td>{{ $relation['centro'] }}</td><td>{{ $relation['entregas'] }}</td><td><span class="warehouse-badge">{{ number_format($relation['unidades']) }} unid.</span></td></tr>@empty<tr><td colspan="4" class="warehouse-empty">Sin relaciones para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></article>
            <article class="glass-card warehouse-panel"><h3><i class="bi bi-boxes"></i> Top de artículos</h3><div class="warehouse-bars">@forelse($analytics['articulos'] as $label => $count)<div class="warehouse-bar-row" title="{{ $label }}: {{ $count }} unidades"><div class="warehouse-bar-label">{{ $label }}</div><div class="warehouse-bar-track"><span class="warehouse-bar orange" style="width:{{ ($count / $maxArticle) * 100 }}%"></span></div><div class="warehouse-count">{{ $count }}</div></div>@empty<div class="warehouse-empty">Sin artículos para mostrar.</div>@endforelse</div></article>
        </section>

        <section class="glass-card warehouse-panel"><h3><i class="bi bi-table"></i> Entregas recientes del período</h3><div class="warehouse-table-wrap"><table class="warehouse-table"><thead><tr><th>Fecha</th><th>Persona</th><th>Centro</th><th>Detalle EPP</th><th>Unidades</th><th>Registrado por</th></tr></thead><tbody>@forelse($recent as $record)<tr><td>{{ $record->fecha_pedido?->format('d/m/Y') ?? 'Sin fecha' }}<div class="warehouse-muted">#{{ $record->kizeo_record_number ?? $record->kizeo_data_id }}</div></td><td><strong>{{ $record->nombre ?: 'Sin identificar' }}</strong><div class="warehouse-muted">{{ $record->rut }}</div></td><td>{{ $record->centro ?: 'Sin centro' }}</td><td>@forelse($record->items as $item)<div><span class="warehouse-badge">{{ $item->cantidad }} × {{ $item->articulo }}</span> <span class="warehouse-muted">{{ $item->talla ?: 'Sin talla' }}</span></div>@empty<span class="warehouse-muted">Sin detalle de artículos</span>@endforelse</td><td><strong>{{ number_format($record->unidades_total) }}</strong><div class="warehouse-muted">{{ $record->lineas_count }} línea(s)</div></td><td>{{ $record->registrado_por ?: 'Kizeo' }}</td></tr>@empty<tr><td colspan="6" class="warehouse-empty">No hay entregas para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></section>
    @endif
</div>
@endsection
