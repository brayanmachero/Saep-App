@extends('layouts.app')

@section('title', 'Entregas de Bodega')

@push('styles')
<style>
    .warehouse-dashboard{max-width:1540px;margin:0 auto;container-type:inline-size}.warehouse-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}.warehouse-heading{min-width:0}.warehouse-heading h2{margin:0;color:var(--text-primary);font-size:1.55rem;letter-spacing:0}.warehouse-heading p{margin:.32rem 0 0;color:var(--text-muted);font-size:.84rem;line-height:1.4}.warehouse-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.warehouse-actions form{margin:0}.warehouse-actions .btn-premium,.warehouse-actions .btn-secondary{white-space:nowrap}.warehouse-filter,.warehouse-panel,.warehouse-kpi{padding:1rem}.warehouse-filter{margin-bottom:1rem}.warehouse-filter-grid{display:grid;grid-template-columns:repeat(7,minmax(124px,1fr));gap:.62rem;align-items:end}.warehouse-filter label{display:block;margin-bottom:.28rem;color:var(--text-muted);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.035em}.warehouse-filter select,.warehouse-filter input{width:100%;min-height:38px;padding:.45rem .58rem;color:var(--text-primary);background:var(--input-bg,var(--card-bg,#fff));border:1px solid var(--border-color,#d9e0ea);border-radius:6px;font-size:.8rem}.warehouse-filter-actions{display:flex;align-items:center;gap:.45rem;min-width:0}.warehouse-filter-actions .btn-premium{display:inline-flex;flex:1;justify-content:center;white-space:nowrap}.warehouse-clear-filter{display:inline-flex;flex:0 0 38px;align-items:center;justify-content:center;text-decoration:none}.warehouse-kpis{display:grid;grid-template-columns:repeat(6,minmax(136px,1fr));gap:.7rem;margin-bottom:.8rem}.warehouse-kpi{min-height:96px;border-left:4px solid #8b5cf6}.warehouse-kpi.orange{border-left-color:#ff6b35}.warehouse-kpi.green{border-left-color:#16a34a}.warehouse-kpi.blue{border-left-color:#2563eb}.warehouse-kpi.red{border-left-color:#dc2626}.warehouse-kpi .label{color:var(--text-muted);font-size:.69rem;font-weight:800;text-transform:uppercase;letter-spacing:.035em}.warehouse-kpi .value{margin-top:.3rem;color:var(--text-primary);font-size:1.62rem;line-height:1.05;font-weight:800;font-variant-numeric:tabular-nums}.warehouse-kpi .hint{margin-top:.27rem;color:var(--text-muted);font-size:.7rem;line-height:1.25}.warehouse-visual-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:.8rem;margin-bottom:.8rem}.warehouse-panel{min-width:0}.warehouse-panel h3{display:flex;align-items:center;gap:.45rem;margin:0;color:var(--text-primary);font-size:.91rem}.warehouse-panel h3 i{color:var(--accent-color)}.warehouse-panel-subtitle{margin:.3rem 0 .8rem;color:var(--text-muted);font-size:.72rem;line-height:1.35}.warehouse-span-7{grid-column:span 7}.warehouse-span-5{grid-column:span 5}.warehouse-span-4{grid-column:span 4}.warehouse-span-8{grid-column:span 8}.warehouse-bars{display:grid;gap:.55rem}.warehouse-bar-row{display:grid;grid-template-columns:minmax(108px,1.45fr) minmax(72px,2fr) 34px;gap:.45rem;align-items:center}.warehouse-bar-label{overflow:hidden;color:var(--text-primary);font-size:.74rem;text-overflow:ellipsis;white-space:nowrap}.warehouse-item-ranking{grid-template-columns:minmax(128px,1.55fr) minmax(56px,1fr) 28px;min-height:1.7rem}.warehouse-item-ranking .warehouse-bar-label{line-height:1.16;overflow:visible;text-overflow:clip;white-space:normal}.warehouse-bar-track{height:9px;overflow:hidden;border-radius:3px;background:rgba(148,163,184,.2)}.warehouse-bar{display:block;height:100%;background:#8b5cf6}.warehouse-bar.orange{background:#ff6b35}.warehouse-bar.green{background:#16a34a}.warehouse-bar.blue{background:#2563eb}.warehouse-count{color:var(--text-muted);font-size:.74rem;text-align:right;font-variant-numeric:tabular-nums}.warehouse-panel-footer{margin:.8rem 0 0;color:var(--text-muted);font-size:.69rem;line-height:1.35}.warehouse-donut-layout{display:grid;grid-template-columns:142px minmax(0,1fr);align-items:center;justify-content:center;gap:1rem;min-height:166px}.warehouse-donut{position:relative;width:136px;height:136px;border-radius:50%;background:var(--chart-data,#e2e8f0);justify-self:center}.warehouse-donut::after{position:absolute;inset:26px;border:1px solid var(--border-color,#d9e0ea);border-radius:50%;background:var(--card-bg,#fff);content:''}.warehouse-donut-center{position:absolute;z-index:1;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-primary);font-size:1.28rem;font-weight:800;font-variant-numeric:tabular-nums}.warehouse-donut-center small{margin-top:.08rem;color:var(--text-muted);font-size:.62rem;font-weight:800;text-transform:uppercase}.warehouse-donut-legend{display:grid;gap:.38rem;min-width:0}.warehouse-donut-line{display:grid;grid-template-columns:8px minmax(0,1fr) auto;gap:.38rem;align-items:center;color:var(--text-primary);font-size:.73rem}.warehouse-swatch{width:8px;height:8px;border-radius:50%}.warehouse-donut-line span:nth-child(2){overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.warehouse-donut-line span:last-child{color:var(--text-muted);font-variant-numeric:tabular-nums}.warehouse-combo-chart{position:relative;min-height:250px}.warehouse-combo-chart canvas{display:block;width:100%!important;height:250px!important}.warehouse-chart-note{margin:.52rem 0 0;color:var(--text-muted);font-size:.69rem;line-height:1.35}.warehouse-table-wrap{overflow-x:auto}.warehouse-table{width:100%;min-width:850px;border-collapse:collapse}.warehouse-relationship-table{min-width:600px}.warehouse-table th{padding:.52rem .6rem;color:var(--text-muted);font-size:.68rem;text-align:left;text-transform:uppercase;letter-spacing:.035em;white-space:nowrap;border-bottom:1px solid var(--border-color,#d9e0ea)}.warehouse-table td{padding:.62rem .6rem;color:var(--text-primary);font-size:.77rem;vertical-align:top;border-bottom:1px solid var(--border-color,#d9e0ea)}.warehouse-table tbody tr{transition:background .15s}.warehouse-table tbody tr:hover{background:rgba(139,92,246,.045)}.warehouse-table tbody tr:last-child td{border-bottom:0}.warehouse-muted{color:var(--text-muted);font-size:.7rem;line-height:1.35}.warehouse-badge{display:inline-flex;align-items:center;border-radius:999px;padding:.16rem .43rem;background:#ede9fe;color:#5b21b6;font-size:.66rem;font-weight:800;white-space:nowrap}.warehouse-item-summary{display:grid;gap:.22rem;max-width:350px}.warehouse-item-summary > div{display:flex;gap:.28rem;align-items:center;min-width:0}.warehouse-more-items{color:var(--text-muted);font-size:.69rem;font-weight:700}.warehouse-row-actions{display:flex;justify-content:flex-end;gap:.35rem}.warehouse-icon-button{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;padding:0;border:1px solid var(--border-color,#d9e0ea);border-radius:6px;background:var(--card-bg,#fff);color:var(--text-primary);cursor:pointer;text-decoration:none}.warehouse-icon-button:hover{border-color:var(--accent-color);color:var(--accent-color);background:rgba(139,92,246,.06)}.warehouse-empty{padding:2.15rem 1rem;color:var(--text-muted);text-align:center}.warehouse-modal-backdrop{position:fixed;z-index:1200;inset:0;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.56)}.warehouse-modal-backdrop.is-open{display:flex}.warehouse-modal{width:min(760px,100%);max-height:min(780px,calc(100vh - 2rem));overflow:hidden;border:1px solid var(--border-color,#d9e0ea);border-radius:8px;background:var(--card-bg,#fff);box-shadow:0 24px 60px rgba(15,23,42,.28)}.warehouse-modal.document{width:min(1080px,100%)}.warehouse-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:.88rem 1rem;border-bottom:1px solid var(--border-color,#d9e0ea)}.warehouse-modal-head h3{margin:0;color:var(--text-primary);font-size:1rem}.warehouse-modal-head p{margin:.18rem 0 0;color:var(--text-muted);font-size:.73rem}.warehouse-modal-body{max-height:calc(100vh - 150px);overflow:auto;padding:1rem}.warehouse-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;margin-bottom:1rem}.warehouse-detail-field{padding:.65rem .7rem;border:1px solid var(--border-color,#d9e0ea);border-radius:6px}.warehouse-detail-field span{display:block;color:var(--text-muted);font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.035em}.warehouse-detail-field strong{display:block;margin-top:.2rem;color:var(--text-primary);font-size:.8rem;line-height:1.3;overflow-wrap:anywhere}.warehouse-detail-table{width:100%;border-collapse:collapse}.warehouse-detail-table th,.warehouse-detail-table td{padding:.55rem .45rem;text-align:left;border-bottom:1px solid var(--border-color,#d9e0ea);font-size:.78rem}.warehouse-detail-table th{color:var(--text-muted);font-size:.68rem;text-transform:uppercase}.warehouse-detail-table td:last-child,.warehouse-detail-table th:last-child{text-align:right;font-variant-numeric:tabular-nums}.warehouse-document-frame{display:block;width:100%;height:min(72vh,720px);border:0;background:#f1f5f9}@container(max-width:1180px){.warehouse-header{align-items:stretch}.warehouse-actions{justify-content:flex-start}.warehouse-filter-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.warehouse-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.warehouse-span-7{grid-column:span 12}.warehouse-span-5,.warehouse-span-4{grid-column:span 6}.warehouse-span-8{grid-column:span 12}}@container(max-width:720px){.warehouse-header{flex-direction:column}.warehouse-filter-grid,.warehouse-kpis{grid-template-columns:1fr 1fr}.warehouse-filter-actions{grid-column:span 2}.warehouse-kpi{min-height:88px}.warehouse-span-5,.warehouse-span-4,.warehouse-span-8{grid-column:span 12}.warehouse-donut-layout{grid-template-columns:122px minmax(0,1fr);gap:.7rem}.warehouse-donut{width:116px;height:116px}.warehouse-donut::after{inset:22px}.warehouse-detail-grid{grid-template-columns:1fr 1fr}.warehouse-actions{width:100%}.warehouse-actions > *{flex:1}.warehouse-actions .btn-premium,.warehouse-actions .btn-secondary{justify-content:center;width:100%}.warehouse-combo-chart{min-height:224px}.warehouse-combo-chart canvas{height:224px!important}}@container(max-width:460px){.warehouse-filter-grid,.warehouse-kpis,.warehouse-detail-grid{grid-template-columns:1fr}.warehouse-filter-actions{grid-column:span 1}.warehouse-donut-layout{grid-template-columns:1fr}.warehouse-donut-legend{width:100%}}
</style>
@endpush

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
    $articleCursor = 0; $articleStops = [];
    foreach (($analytics['articulos'] ?? []) as $count) { $end = $articleCursor + (($count / $articleTotal) * 100); $articleStops[] = $palette[count($articleStops) % count($palette)] . " {$articleCursor}% {$end}%"; $articleCursor = $end; }
    $sizeCursor = 0; $sizeStops = [];
    foreach (($analytics['tallas'] ?? []) as $count) { $end = $sizeCursor + (($count / $sizeTotal) * 100); $sizeStops[] = $palette[count($sizeStops) % count($palette)] . " {$sizeCursor}% {$end}%"; $sizeCursor = $end; }
    $recordData = $recent->map(fn ($record) => [
        'reference' => '#' . ($record->kizeo_record_number ?: $record->kizeo_data_id),
        'date' => $record->fecha_pedido?->format('d/m/Y') ?: 'Sin fecha',
        'person' => $record->nombre ?: 'Sin identificar',
        'rut' => $record->rut ?: 'Sin RUT',
        'center' => $record->centro ?: 'Sin centro',
        'registeredBy' => $record->registrado_por ?: 'Kizeo',
        'lines' => (int) $record->lineas_count,
        'units' => (int) $record->unidades_total,
        'documentUrl' => route('entregas-bodega-dashboard.document', $record),
        'items' => $record->items->map(fn ($item) => ['article' => $item->articulo ?: 'Sin articulo', 'size' => $item->talla ?: 'Sin talla', 'quantity' => (int) $item->cantidad])->values(),
    ])->values();
@endphp

<div class="page-container warehouse-dashboard">
    <div class="warehouse-header">
        <div class="warehouse-heading">
            <h2><i class="bi bi-box-seam-fill" style="color:var(--accent-color)"></i> Entregas de Bodega</h2>
            <p>Control de Entrega Bodega desde Kizeo.
                @if($syncInfo)<span title="Ultima sincronizacion local"><i class="bi bi-database-check" style="color:#16a34a"></i> {{ number_format($syncInfo['total']) }} entregas · actualizado {{ \Carbon\Carbon::parse($syncInfo['last_sync'])->diffForHumans() }}</span>
                @else <span><i class="bi bi-cloud-arrow-down"></i> Aun no se han sincronizado datos.</span>@endif
            </p>
        </div>
        <div class="warehouse-actions">
            @if($hasData)<a href="{{ route('entregas-bodega-dashboard.export', request()->query()) }}" class="btn-secondary" title="Descargar las entregas e items EPP filtrados en Excel"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</a>@endif
            @if(auth()->user()->tieneAcceso('entregas_bodega_dashboard', 'puede_editar'))<form method="POST" action="{{ route('entregas-bodega-dashboard.sync') }}" onsubmit="return confirm('Se consultaran nuevamente las entregas de Kizeo. ¿Continuar?')">@csrf<button class="btn-premium" style="font-size:.8rem" title="Actualiza la informacion desde Kizeo"><i class="bi bi-arrow-clockwise"></i> Sincronizar Kizeo</button></form>@endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

    <section class="glass-card warehouse-filter" aria-label="Filtros del dashboard">
        <form method="GET" action="{{ route('entregas-bodega-dashboard.index') }}" class="warehouse-filter-grid">
            @foreach(['centro' => ['Centro de costo', 'centros'], 'trabajador' => ['Persona', 'trabajadores'], 'articulo' => ['Articulo EPP', 'articulos'], 'talla' => ['Talla', 'tallas']] as $field => [$label, $optionKey])
                <div><label for="warehouse-{{ $field }}">{{ $label }}</label><select id="warehouse-{{ $field }}" name="{{ $field }}"><option value="">Todos</option>@foreach($options[$optionKey] ?? [] as $option)<option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ $option }}</option>@endforeach</select></div>
            @endforeach
            <div><label for="warehouse-desde">Desde</label><input id="warehouse-desde" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}"></div>
            <div><label for="warehouse-hasta">Hasta</label><input id="warehouse-hasta" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}"></div>
            <div class="warehouse-filter-actions"><button type="submit" class="btn-premium" title="Aplicar filtros"><i class="bi bi-funnel-fill"></i> Aplicar</button><a href="{{ route('entregas-bodega-dashboard.index', ['todo' => 1]) }}" class="btn-secondary warehouse-clear-filter" title="Quitar filtros y ver el historial" aria-label="Quitar filtros y ver el historial"><i class="bi bi-x-lg"></i></a></div>
        </form>
    </section>

    @if(! $hasData)
        <section class="glass-card warehouse-empty"><i class="bi bi-cloud-arrow-down" style="font-size:1.7rem;color:var(--accent-color)"></i><p style="margin:.7rem 0 .25rem;font-weight:700;color:var(--text-primary)">El dashboard esta listo para recibir entregas.</p><p style="margin:0">Un usuario autorizado puede iniciar la sincronizacion desde Kizeo.</p></section>
    @else
        <section class="warehouse-kpis" aria-label="Indicadores principales">
            <article class="glass-card warehouse-kpi"><div class="label">Entregas</div><div class="value">{{ number_format($analytics['total']) }}</div><div class="hint">En el periodo filtrado</div></article>
            <article class="glass-card warehouse-kpi orange"><div class="label">Unidades EPP</div><div class="value">{{ number_format($analytics['unidades']) }}</div><div class="hint">Suma de cantidades entregadas</div></article>
            <article class="glass-card warehouse-kpi blue"><div class="label">Lineas de detalle</div><div class="value">{{ number_format($analytics['lineas']) }}</div><div class="hint">Articulo, talla y cantidad</div></article>
            <article class="glass-card warehouse-kpi green"><div class="label">Personas</div><div class="value">{{ number_format($analytics['personas']) }}</div><div class="hint">Con entrega registrada</div></article>
            <article class="glass-card warehouse-kpi red"><div class="label">Centros activos</div><div class="value">{{ number_format($analytics['centros_activos']) }}</div><div class="hint">Con movimiento de bodega</div></article>
            <article class="glass-card warehouse-kpi"><div class="label">Promedio</div><div class="value">{{ number_format($analytics['promedio_unidades'], 1) }}</div><div class="hint">Unidades por entrega</div></article>
        </section>

        <section class="warehouse-visual-grid" aria-label="Analisis de entregas">
            <article class="glass-card warehouse-panel warehouse-span-7"><h3><i class="bi bi-bar-chart-line-fill"></i> Evolucion diaria</h3><p class="warehouse-panel-subtitle">Entregas por dia y unidades EPP asociadas al mismo periodo.</p>
                @if(!empty($analytics['by_day']))
                <div class="warehouse-combo-chart"><canvas id="warehouse-daily-combo-chart" role="img" aria-label="Grafico combinado de entregas y unidades EPP por dia"></canvas></div><p class="warehouse-chart-note">Barras: entregas. Linea: unidades EPP. Cada punto corresponde a un dia con movimiento.</p>
                @else <div class="warehouse-empty">No hay registros para el periodo seleccionado.</div>@endif
            </article>
            <article class="glass-card warehouse-panel warehouse-span-5"><h3><i class="bi bi-pie-chart-fill"></i> Articulos mas entregados</h3><p class="warehouse-panel-subtitle">Participacion sobre las unidades EPP entregadas.</p><div class="warehouse-donut-layout"><div class="warehouse-donut" style="--chart-data:{{ $articleStops ? 'conic-gradient(' . implode(', ', $articleStops) . ')' : '#e2e8f0' }}"><div class="warehouse-donut-center">{{ number_format($analytics['unidades']) }}<small>unidades</small></div></div><div class="warehouse-donut-legend">@forelse($analytics['articulos'] as $label => $count)<div class="warehouse-donut-line" title="{{ $label }}: {{ $count }} unidades"><span class="warehouse-swatch" style="background:{{ $palette[$loop->index % count($palette)] }}"></span><span>{{ $label }}</span><span>{{ $count }}</span></div>@empty<div class="warehouse-muted">Sin articulos informados.</div>@endforelse</div></div></article>
        </section>

        <section class="warehouse-visual-grid" aria-label="Distribuciones y rankings">
            <article class="glass-card warehouse-panel warehouse-span-4"><h3><i class="bi bi-buildings-fill"></i> Entregas por centro</h3><p class="warehouse-panel-subtitle">Cantidad de entregas en cada centro.</p><div class="warehouse-bars">@forelse($analytics['centros'] as $label => $count)<div class="warehouse-bar-row" title="{{ $label }}: {{ $count }} entregas"><div class="warehouse-bar-label">{{ $label }}</div><div class="warehouse-bar-track"><span class="warehouse-bar blue" style="width:{{ ($count / $maxCenter) * 100 }}%"></span></div><div class="warehouse-count">{{ $count }}</div></div>@empty<div class="warehouse-empty">Sin centros para mostrar.</div>@endforelse</div></article>
            <article class="glass-card warehouse-panel warehouse-span-4"><h3><i class="bi bi-person-check-fill"></i> Personas con mas unidades</h3><p class="warehouse-panel-subtitle">Ranking de unidades EPP asignadas.</p><div class="warehouse-bars">@forelse($analytics['personas_top'] as $person)<div class="warehouse-bar-row" title="{{ $person['nombre'] }}: {{ $person['unidades'] }} unidades en {{ $person['entregas'] }} entrega(s)"><div class="warehouse-bar-label">{{ $person['nombre'] }}</div><div class="warehouse-bar-track"><span class="warehouse-bar green" style="width:{{ ($person['unidades'] / $maxPeople) * 100 }}%"></span></div><div class="warehouse-count">{{ $person['unidades'] }}</div></div>@empty<div class="warehouse-empty">Sin personas identificadas.</div>@endforelse</div></article>
            <article class="glass-card warehouse-panel warehouse-span-4"><h3><i class="bi bi-rulers"></i> Distribucion por talla</h3><p class="warehouse-panel-subtitle">Unidades segun talla registrada.</p><div class="warehouse-donut-layout"><div class="warehouse-donut" style="width:120px;height:120px;--chart-data:{{ $sizeStops ? 'conic-gradient(' . implode(', ', $sizeStops) . ')' : '#e2e8f0' }}"><div class="warehouse-donut-center" style="font-size:1.12rem">{{ number_format($sizeTotal) }}<small>unid.</small></div></div><div class="warehouse-donut-legend">@forelse($analytics['tallas'] as $label => $count)<div class="warehouse-donut-line"><span class="warehouse-swatch" style="background:{{ $palette[$loop->index % count($palette)] }}"></span><span>{{ $label }}</span><span>{{ $count }}</span></div>@empty<div class="warehouse-muted">Sin tallas informadas.</div>@endforelse</div></div></article>
        </section>

        <section class="warehouse-visual-grid" aria-label="Relaciones y articulos">
            <article class="glass-card warehouse-panel warehouse-span-8"><h3><i class="bi bi-diagram-3-fill"></i> Relacion persona y centro</h3><p class="warehouse-panel-subtitle">Cruce de la persona receptora con el centro informado en la entrega.</p><div class="warehouse-table-wrap"><table class="warehouse-table warehouse-relationship-table"><thead><tr><th>Persona</th><th>Centro</th><th>Entregas</th><th>Unidades</th></tr></thead><tbody>@forelse($analytics['relaciones'] as $relation)<tr><td><strong>{{ $relation['nombre'] }}</strong></td><td>{{ $relation['centro'] }}</td><td>{{ $relation['entregas'] }}</td><td><span class="warehouse-badge">{{ number_format($relation['unidades']) }} unid.</span></td></tr>@empty<tr><td colspan="4" class="warehouse-empty">Sin relaciones para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></article>
            <article class="glass-card warehouse-panel warehouse-span-4"><h3><i class="bi bi-boxes"></i> Top de articulos</h3><p class="warehouse-panel-subtitle">Las seis referencias con mas unidades entregadas.</p><div class="warehouse-bars">@forelse(collect($analytics['articulos'] ?? [])->take(6) as $label => $count)<div class="warehouse-bar-row warehouse-item-ranking" title="{{ $label }}: {{ $count }} unidades"><div class="warehouse-bar-label">{{ $label }}</div><div class="warehouse-bar-track"><span class="warehouse-bar orange" style="width:{{ ($count / $maxArticle) * 100 }}%"></span></div><div class="warehouse-count">{{ $count }}</div></div>@empty<div class="warehouse-empty">Sin articulos para mostrar.</div>@endforelse</div>@if(count($analytics['articulos'] ?? []) > 6)<p class="warehouse-panel-footer">El listado completo de articulos y tallas esta disponible en Exportar Excel.</p>@endif</article>
        </section>

        <section class="glass-card warehouse-panel"><h3><i class="bi bi-table"></i> Entregas recientes del periodo</h3><p class="warehouse-panel-subtitle">Abre el detalle para revisar todos los items o el PDF generado por Kizeo.</p><div class="warehouse-table-wrap"><table class="warehouse-table"><thead><tr><th>Fecha</th><th>Persona</th><th>Centro</th><th>Resumen EPP</th><th>Unidades</th><th>Registrado por</th><th aria-label="Acciones"></th></tr></thead><tbody>@forelse($recent as $record)<tr><td>{{ $record->fecha_pedido?->format('d/m/Y') ?? 'Sin fecha' }}<div class="warehouse-muted">#{{ $record->kizeo_record_number ?? $record->kizeo_data_id }}</div></td><td><strong>{{ $record->nombre ?: 'Sin identificar' }}</strong><div class="warehouse-muted">{{ $record->rut }}</div></td><td>{{ $record->centro ?: 'Sin centro' }}</td><td><div class="warehouse-item-summary">@foreach($record->items->take(2) as $item)<div><span class="warehouse-badge">{{ $item->cantidad }} x {{ $item->articulo }}</span><span class="warehouse-muted">{{ $item->talla ?: 'Sin talla' }}</span></div>@endforeach @if($record->items->count() > 2)<span class="warehouse-more-items">+{{ $record->items->count() - 2 }} item(s) en detalle</span>@endif</div></td><td><strong>{{ number_format($record->unidades_total) }}</strong><div class="warehouse-muted">{{ $record->lineas_count }} linea(s)</div></td><td>{{ $record->registrado_por ?: 'Kizeo' }}</td><td><div class="warehouse-row-actions"><button type="button" class="warehouse-icon-button" title="Ver detalle de entrega" aria-label="Ver detalle de entrega" onclick="openWarehouseDetail({{ $loop->index }})"><i class="bi bi-eye"></i></button><button type="button" class="warehouse-icon-button" title="Ver PDF generado por Kizeo" aria-label="Ver PDF generado por Kizeo" onclick="openWarehouseDocument({{ $loop->index }})"><i class="bi bi-file-earmark-pdf"></i></button></div></td></tr>@empty<tr><td colspan="7" class="warehouse-empty">No hay entregas para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></section>
    @endif
</div>

<div class="warehouse-modal-backdrop" id="warehouse-detail-modal" role="dialog" aria-modal="true" aria-labelledby="warehouse-detail-title" onclick="if(event.target===this)closeWarehouseModal('warehouse-detail-modal')"><section class="warehouse-modal"><header class="warehouse-modal-head"><div><h3 id="warehouse-detail-title"><i class="bi bi-box-seam"></i> Detalle de entrega</h3><p id="warehouse-detail-reference"></p></div><button type="button" class="warehouse-icon-button" title="Cerrar detalle" aria-label="Cerrar detalle" onclick="closeWarehouseModal('warehouse-detail-modal')"><i class="bi bi-x-lg"></i></button></header><div class="warehouse-modal-body"><div class="warehouse-detail-grid" id="warehouse-detail-fields"></div><table class="warehouse-detail-table"><thead><tr><th>Articulo EPP</th><th>Talla</th><th>Cantidad</th></tr></thead><tbody id="warehouse-detail-items"></tbody></table></div></section></div>
<div class="warehouse-modal-backdrop" id="warehouse-document-modal" role="dialog" aria-modal="true" aria-labelledby="warehouse-document-title" onclick="if(event.target===this)closeWarehouseDocument()"><section class="warehouse-modal document"><header class="warehouse-modal-head"><div><h3 id="warehouse-document-title"><i class="bi bi-file-earmark-pdf"></i> Documento Kizeo</h3><p id="warehouse-document-reference"></p></div><div class="warehouse-row-actions"><a id="warehouse-document-open" class="warehouse-icon-button" target="_blank" rel="noopener" title="Abrir PDF en una nueva pestaña" aria-label="Abrir PDF en una nueva pestaña"><i class="bi bi-box-arrow-up-right"></i></a><button type="button" class="warehouse-icon-button" title="Cerrar visor" aria-label="Cerrar visor" onclick="closeWarehouseDocument()"><i class="bi bi-x-lg"></i></button></div></header><iframe id="warehouse-document-frame" class="warehouse-document-frame" title="Documento generado por Kizeo"></iframe></section></div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script>
    const warehouseDailyTrend = @json($analytics['by_day'] ?? []);
    const warehouseRecords = @json($recordData);
    const warehouseFormatDate = (value, includeYear = false) => {
        const [year, month, day] = String(value).split('-').map(Number);
        return new Intl.DateTimeFormat('es-CL', includeYear
            ? { day: '2-digit', month: 'short', year: 'numeric' }
            : { day: '2-digit', month: 'short' }
        ).format(new Date(year, month - 1, day)).replace('.', '');
    };
    const warehouseTrendCanvas = document.getElementById('warehouse-daily-combo-chart');
    if (warehouseTrendCanvas && window.Chart && warehouseDailyTrend.length) {
        const pageStyles = getComputedStyle(document.documentElement);
        const muted = pageStyles.getPropertyValue('--text-muted').trim() || '#64748b';
        const border = pageStyles.getPropertyValue('--border-color').trim() || 'rgba(148, 163, 184, .35)';
        new Chart(warehouseTrendCanvas, {
            data: {
                labels: warehouseDailyTrend.map((point) => point.label),
                datasets: [
                    { type: 'bar', label: 'Entregas', data: warehouseDailyTrend.map((point) => point.entregas), yAxisID: 'deliveries', backgroundColor: '#8b5cf6', borderRadius: 4, borderSkipped: false, barPercentage: .68, categoryPercentage: .82 },
                    { type: 'line', label: 'Unidades EPP', data: warehouseDailyTrend.map((point) => point.unidades), yAxisID: 'units', borderColor: '#ff6b35', backgroundColor: '#ff6b35', borderWidth: 2.5, tension: .28, pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: '#fff', pointBorderWidth: 2, fill: false },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', align: 'start', labels: { color: muted, usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 11 } } },
                    tooltip: { callbacks: { title: (items) => `Fecha: ${warehouseFormatDate(items[0].label, true)}`, label: (item) => `${item.dataset.label}: ${Number(item.raw || 0).toLocaleString('es-CL')}` } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 10, callback: (_, index) => warehouseFormatDate(warehouseDailyTrend[index].label) } },
                    deliveries: { type: 'linear', position: 'left', beginAtZero: true, grid: { color: border }, ticks: { color: muted, precision: 0 }, title: { display: true, text: 'Entregas', color: muted, font: { size: 11, weight: '600' } } },
                    units: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: muted, precision: 0 }, title: { display: true, text: 'Unidades EPP', color: muted, font: { size: 11, weight: '600' } } },
                },
            },
        });
    }
    const warehouseText = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    function closeWarehouseModal(id) { document.getElementById(id)?.classList.remove('is-open'); }
    function openWarehouseDetail(index) {
        const record = warehouseRecords[index]; if (!record) return;
        document.getElementById('warehouse-detail-reference').textContent = `${record.reference} · ${record.date}`;
        document.getElementById('warehouse-detail-fields').innerHTML = [
            ['Persona', record.person], ['RUT', record.rut], ['Centro de costo', record.center], ['Registrado por', record.registeredBy], ['Lineas EPP', record.lines], ['Unidades totales', record.units]
        ].map(([label, value]) => `<div class="warehouse-detail-field"><span>${warehouseText(label)}</span><strong>${warehouseText(value)}</strong></div>`).join('');
        document.getElementById('warehouse-detail-items').innerHTML = record.items.length
            ? record.items.map((item) => `<tr><td>${warehouseText(item.article)}</td><td>${warehouseText(item.size)}</td><td>${warehouseText(item.quantity)}</td></tr>`).join('')
            : '<tr><td colspan="3" class="warehouse-muted">Kizeo no informo items para esta entrega.</td></tr>';
        document.getElementById('warehouse-detail-modal').classList.add('is-open');
    }
    function openWarehouseDocument(index) {
        const record = warehouseRecords[index]; if (!record) return;
        document.getElementById('warehouse-document-reference').textContent = `${record.reference} · ${record.person}`;
        document.getElementById('warehouse-document-open').href = record.documentUrl;
        document.getElementById('warehouse-document-frame').src = record.documentUrl;
        document.getElementById('warehouse-document-modal').classList.add('is-open');
    }
    function closeWarehouseDocument() { closeWarehouseModal('warehouse-document-modal'); document.getElementById('warehouse-document-frame').src = 'about:blank'; }
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeWarehouseModal('warehouse-detail-modal'); closeWarehouseDocument(); } });
</script>
@endpush
