@extends('layouts.app')

@section('content')
@php
    $canCreate = auth()->user()->tieneAcceso('inventario_bodega', 'puede_crear');
    $canEdit = auth()->user()->tieneAcceso('inventario_bodega', 'puede_editar');
    $stockUnits = (float) $balances->sum('stock_actual');
    $availableVariants = $balances->filter(fn ($item) => (float) $item->stock_actual > 0)->count();
    $views = [
        'resumen' => ['Resumen', 'bi-grid-1x2-fill'],
        'ingresos' => ['Ingresos', 'bi-box-arrow-in-down'],
        'movimientos' => ['Movimientos', 'bi-arrow-left-right'],
        'conteos' => ['Conteos', 'bi-clipboard-check'],
        'kizeo' => ['Entregas Kizeo', 'bi-phone'],
        'catalogo' => ['Catalogo', 'bi-sliders'],
    ];
@endphp

<div class="inventory-page">
    <div class="inventory-heading">
        <div>
            <p class="inventory-kicker">Bodega SAEP</p>
            <h1>Inventario</h1>
            <p>Stock por ubicacion, movimientos trazables y conteos fisicos.</p>
        </div>
        <div class="inventory-header-actions">
            <a class="btn btn-light inventory-btn" href="{{ route('inventario-bodega.export', ['ubicacion_id' => $selectedLocation]) }}" title="Descargar el stock visible en Excel">
                <i class="bi bi-file-earmark-excel"></i><span>Exportar stock</span>
            </a>
            @if($canCreate)
                <a class="btn btn-primary inventory-btn" href="{{ route('inventario-bodega.index', ['vista' => 'ingresos']) }}#registrar-ingreso">
                    <i class="bi bi-plus-lg"></i><span>Registrar ingreso</span>
                </a>
                <a class="btn btn-light inventory-btn" href="{{ route('inventario-bodega.index', ['vista' => 'ingresos']) }}#importar-ingresos">
                    <i class="bi bi-upload"></i><span>Importar ingresos</span>
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success inventory-alert"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger inventory-alert">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div><strong>Revisa los datos ingresados.</strong><br>{{ $errors->first() }}</div>
        </div>
    @endif

    @if($activeLocations->isEmpty())
        <section class="inventory-onboarding">
            <div class="inventory-onboarding-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <h2>Primero registra las ubicaciones reales</h2>
                <p>El sistema no carga bodegas de ejemplo. Crea las tres bodegas fisicas y la zona de despacho con sus nombres oficiales antes de registrar stock.</p>
            </div>
            <a href="{{ route('inventario-bodega.index', ['vista' => 'catalogo']) }}#ubicaciones" class="btn btn-primary inventory-btn"><i class="bi bi-plus-lg"></i>Crear ubicaciones</a>
        </section>
    @endif

    <nav class="inventory-tabs" aria-label="Secciones de inventario">
        @foreach($views as $key => [$label, $icon])
            <a href="{{ route('inventario-bodega.index', ['vista' => $key, 'ubicacion_id' => $selectedLocation]) }}" class="inventory-tab {{ $vista === $key ? 'active' : '' }}">
                <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    @if($vista === 'resumen')
        <section class="inventory-section inventory-filter-section">
            <form method="GET" action="{{ route('inventario-bodega.index') }}" class="inventory-filter-grid">
                <input type="hidden" name="vista" value="resumen">
                <label>Ubicacion
                    <select name="ubicacion_id" class="form-select">
                        <option value="">Todas las ubicaciones</option>
                        @foreach($activeLocations as $location)
                            <option value="{{ $location->id }}" @selected($selectedLocation === $location->id)>{{ $location->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Buscar articulo
                    <input type="search" name="buscar" class="form-control" value="{{ $search }}" placeholder="Codigo, producto o talla">
                </label>
                <button class="btn btn-primary inventory-btn" type="submit"><i class="bi bi-funnel-fill"></i>Aplicar</button>
                @if($selectedLocation || $search !== '')
                    <a href="{{ route('inventario-bodega.index') }}" class="btn btn-light inventory-icon-btn" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                @endif
            </form>
        </section>

        <section class="inventory-kpis">
            <article class="inventory-kpi accent-purple"><span>Unidades disponibles</span><strong>{{ rtrim(rtrim(number_format($stockUnits, 3, ',', '.'), '0'), ',') }}</strong><small>Saldo del kardex</small></article>
            <article class="inventory-kpi accent-blue"><span>Articulos con stock</span><strong>{{ $availableVariants }}</strong><small>Variantes disponibles</small></article>
            <article class="inventory-kpi accent-red"><span>Stock critico</span><strong>{{ $critical->count() }}</strong><small>En o bajo el minimo</small></article>
            <article class="inventory-kpi accent-green"><span>Ubicaciones activas</span><strong>{{ $activeLocations->count() }}</strong><small>Bodegas y despacho</small></article>
        </section>

        <section class="inventory-section">
            <div class="inventory-section-title">
                <div><h2>Stock disponible</h2><p>Saldo resultante de todos los ingresos, salidas, traslados y ajustes aprobados.</p></div>
                <span class="inventory-count">{{ $balances->count() }} variantes</span>
            </div>
            <div class="inventory-table-wrap">
                <table class="inventory-table">
                    <thead><tr><th>Codigo</th><th>Articulo</th><th>Talla</th><th>Categoria</th><th>Minimo</th><th class="text-end">Stock actual</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse($balances as $variant)
                        @php $minimum = (float) ($variant->stock_minimo ?? $variant->producto->stock_minimo); $actual = (float) $variant->stock_actual; @endphp
                        <tr>
                            <td><span class="inventory-code">{{ $variant->producto->codigo }}</span></td>
                            <td><strong>{{ $variant->producto->nombre }}</strong><small>{{ $variant->producto->tipo ?: 'Sin tipo' }}</small></td>
                            <td>{{ $variant->talla }}</td>
                            <td>{{ $variant->producto->categoria ?: 'Sin categoria' }}</td>
                            <td>{{ rtrim(rtrim(number_format($minimum, 3, ',', '.'), '0'), ',') }}</td>
                            <td class="text-end"><strong>{{ rtrim(rtrim(number_format($actual, 3, ',', '.'), '0'), ',') }}</strong></td>
                            <td><span class="inventory-status {{ $minimum > 0 && $actual <= $minimum ? 'is-critical' : ($actual > 0 ? 'is-ok' : 'is-empty') }}">{{ $minimum > 0 && $actual <= $minimum ? 'Reponer' : ($actual > 0 ? 'Disponible' : 'Sin stock') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="inventory-empty">No hay articulos para el filtro seleccionado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="inventory-section">
            <div class="inventory-section-title"><div><h2>Ultimos movimientos</h2><p>Consulta rapida de lo que afecto el stock.</p></div><a href="{{ route('inventario-bodega.index', ['vista' => 'movimientos']) }}" class="inventory-link">Ver kardex<i class="bi bi-arrow-right"></i></a></div>
            <div class="inventory-table-wrap">
                <table class="inventory-table inventory-table-compact">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Articulo</th><th>Ubicacion</th><th>Referencia</th><th class="text-end">Cantidad</th></tr></thead>
                    <tbody>
                    @forelse($movements as $movement)
                        <tr><td>{{ optional($movement->ocurrido_en)->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', $movement->tipo) }}</td><td>{{ $movement->producto->nombre ?? '-' }}<small>{{ $movement->variante->talla ?? '' }}</small></td><td>{{ $movement->ubicacion->nombre ?? '-' }}</td><td>{{ $movement->documento_numero ?: ($movement->centro_costo ?: '-') }}</td><td class="text-end {{ $movement->cantidad < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->cantidad > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->cantidad, 3, ',', '.'), '0'), ',') }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="inventory-empty">Aun no hay movimientos registrados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    @elseif($vista === 'ingresos')
        <section class="inventory-workspace">
            <div class="inventory-section" id="registrar-ingreso">
                <div class="inventory-section-title"><div><h2>Registrar ingreso</h2><p>Compra respaldada por factura o guia de despacho. Al guardar, el saldo aumenta en la ubicacion indicada.</p></div></div>
                @if($canCreate)
                    <div class="inventory-inline-editor inventory-receipt-import" id="importar-ingresos">
                        <div><h3>Importar ingresos</h3><p>Carga varios comprobantes de una vez. Cada fila representa un articulo y una talla; las lineas con la misma referencia forman un solo ingreso.</p></div>
                        <form method="POST" action="{{ route('inventario-bodega.ingresos.importar') }}" enctype="multipart/form-data" class="inventory-import-receipt-form">@csrf
                            <a href="{{ route('inventario-bodega.ingresos.plantilla') }}" class="inventory-link"><i class="bi bi-download"></i>Descargar plantilla de ingresos</a>
                            <label>Archivo Excel o CSV<input name="archivo" type="file" accept=".xlsx,.xls,.csv" class="form-control" required></label>
                            <button class="btn btn-light inventory-btn" type="submit"><i class="bi bi-upload"></i>Importar ingresos</button>
                        </form>
                        <small class="inventory-import-hint">El archivo se valida completo antes de guardar. No se duplica un documento que ya está vigente en la misma ubicación.</small>
                    </div>
                @endif
                @if($activeLocations->isEmpty() || $variantOptions->isEmpty())
                    <div class="inventory-notice"><i class="bi bi-info-circle"></i>Necesitas al menos una ubicacion activa y un articulo activo antes de registrar un ingreso.</div>
                @else
                    <form method="POST" action="{{ route('inventario-bodega.ingresos.store') }}" class="inventory-form">
                        @csrf
                        <div class="inventory-form-grid three">
                            <label>Ubicacion de ingreso <select name="ubicacion_id" class="form-select" required><option value="">Selecciona una ubicacion</option>@foreach($activeLocations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach</select></label>
                            <label>Proveedor <select name="proveedor_id" class="form-select"><option value="">Sin proveedor registrado</option>@foreach($providers->where('activo', true) as $provider)<option value="{{ $provider->id }}">{{ $provider->nombre }}</option>@endforeach</select></label>
                            <label>Tipo de documento <select name="tipo_documento" class="form-select" required>@foreach(\App\Models\InventarioIngreso::TIPOS_DOCUMENTO as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                            <label>Nro. documento <input name="numero_documento" class="form-control" maxlength="100" placeholder="Factura o guia"></label>
                            <label>Fecha documento <input name="fecha_documento" type="date" class="form-control"></label>
                            <label>Fecha de recepcion <input name="fecha_recepcion" type="date" class="form-control" value="{{ now()->toDateString() }}" required></label>
                        </div>
                        <label class="inventory-wide-label">Observacion <input name="observacion" class="form-control" maxlength="500" placeholder="Compra, reposicion u otra referencia"></label>
                        <div class="inventory-line-header"><h3>Articulos recibidos</h3><button type="button" class="btn btn-light inventory-btn inventory-add-line" data-target="receipt-lines"><i class="bi bi-plus-lg"></i>Agregar articulo</button></div>
                        <div id="receipt-lines" class="inventory-lines"></div>
                        <template id="receipt-line-template"><div class="inventory-line"><label>Articulo<select name="items[__INDEX__][variante_id]" class="form-select" required data-inventory-search-select data-search-placeholder="Buscar por codigo, articulo o talla"><option value="">Selecciona articulo y talla</option>@foreach($variantOptions as $variant)<option value="{{ $variant->id }}">{{ $variant->producto->codigo }} - {{ $variant->producto->nombre }} · {{ $variant->talla }}</option>@endforeach</select></label><label>Cantidad<input name="items[__INDEX__][cantidad]" type="number" min="0.001" step="0.001" class="form-control" required></label><label>Costo unitario<input name="items[__INDEX__][costo_unitario]" type="number" min="0" step="0.01" class="form-control" placeholder="Opcional"></label><button type="button" class="btn btn-light inventory-icon-btn inventory-remove-line" title="Quitar articulo"><i class="bi bi-trash3"></i></button></div></template>
                        <div class="inventory-form-actions"><button type="submit" class="btn btn-primary inventory-btn"><i class="bi bi-check2-circle"></i>Guardar ingreso</button></div>
                    </form>
                @endif
            </div>
            <aside class="inventory-side-note"><i class="bi bi-shield-check"></i><strong>El historial no se borra.</strong><span>Si un ingreso se registró por error, anúlalo con un motivo. Se crean movimientos inversos y queda registrado quién realizó la acción.</span></aside>
        </section>
        <section class="inventory-section" id="ingresos-recientes">
            <div class="inventory-section-title"><div><h2>Ingresos recientes</h2><p>Compras y recepciones registradas desde Bodega. Para corregir uno de prueba, usa “Anular ingreso”; el historial y el reverso quedan registrados.</p></div></div>
            <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Codigo</th><th>Recepcion</th><th>Ubicacion</th><th>Documento</th><th>Proveedor</th><th>Estado</th><th class="text-end">Lineas</th><th>Acciones</th></tr></thead><tbody>@forelse($ingresos as $ingreso)<tr><td><span class="inventory-code">{{ $ingreso->codigo }}</span></td><td>{{ optional($ingreso->fecha_recepcion)->format('d/m/Y') }}</td><td>{{ $ingreso->ubicacion->nombre ?? '-' }}</td><td>{{ $ingreso->tipo_documento }} {{ $ingreso->numero_documento ?: '-' }}</td><td>{{ $ingreso->proveedor->nombre ?? 'Sin proveedor' }}</td><td>@if($ingreso->reversado_en)<span class="inventory-status is-empty">Anulado</span><small>Anulado {{ $ingreso->reversado_en->format('d/m/Y H:i') }}{{ $ingreso->reversadoPor ? ' por ' . $ingreso->reversadoPor->name : '' }}<br>{{ $ingreso->motivo_reversion }}</small>@else<span class="inventory-status is-ok">Vigente</span>@endif</td><td class="text-end">{{ $ingreso->items->count() }}</td><td>@if($canEdit && ! $ingreso->reversado_en)<details class="inventory-receipt-reverse"><summary class="btn btn-light inventory-btn inventory-receipt-reverse-trigger" title="Anular ingreso"><i class="bi bi-arrow-counterclockwise"></i>Anular ingreso</summary><form method="POST" action="{{ route('inventario-bodega.ingresos.revertir', $ingreso) }}" class="inventory-reverse-form">@csrf<label>Motivo de anulación<input name="motivo_reversion" class="form-control" minlength="5" maxlength="500" required placeholder="Ej.: ingreso de prueba"></label><label class="inventory-checkbox inventory-confirm-reverse"><input type="checkbox" required><span>Confirmo que se descontará el stock de estas líneas.</span></label><button type="submit" class="btn btn-light inventory-btn text-danger"><i class="bi bi-arrow-counterclockwise"></i>Confirmar anulación</button></form></details>@else<span class="inventory-muted">-</span>@endif</td></tr>@empty<tr><td colspan="8" class="inventory-empty">Aun no hay ingresos registrados.</td></tr>@endforelse</tbody></table></div>
        </section>

    @elseif($vista === 'movimientos')
        <section class="inventory-workspace">
            <div class="inventory-section">
                <div class="inventory-section-title"><div><h2>Registrar movimiento</h2><p>Usa entregas, despachos, traslados o ajustes. El sistema valida que nunca salga mas stock del disponible.</p></div></div>
                @if($canCreate)
                    <div class="inventory-inline-editor inventory-movement-receipt-actions">
                        <div><h3>¿Es una compra o una carga masiva?</h3><p>Los ingresos se registran en su propia pestaña para conservar el documento, proveedor y detalle. Ahí también puedes anular un ingreso de prueba con motivo y reverso trazable.</p></div>
                        <div class="inventory-import-actions">
                            <a href="{{ route('inventario-bodega.index', ['vista' => 'ingresos']) }}#importar-ingresos" class="btn btn-light inventory-btn"><i class="bi bi-upload"></i>Importar ingresos</a>
                            <a href="{{ route('inventario-bodega.index', ['vista' => 'ingresos']) }}#ingresos-recientes" class="btn btn-light inventory-btn"><i class="bi bi-arrow-counterclockwise"></i>Ver o anular ingresos</a>
                        </div>
                    </div>
                @endif
                @if($activeLocations->isEmpty() || $variantOptions->isEmpty())
                    <div class="inventory-notice"><i class="bi bi-info-circle"></i>Necesitas ubicaciones y articulos activos para registrar movimientos.</div>
                @else
                    <form method="POST" action="{{ route('inventario-bodega.movimientos.store') }}" class="inventory-form" id="movement-form">
                        @csrf
                        <div class="inventory-form-grid three">
                            <label>Tipo de movimiento<select name="tipo" class="form-select" id="movement-type" required><option value="ENTREGA_EPP">Entrega EPP</option><option value="DESPACHO_CENTRO">Despacho a centro</option><option value="TRASLADO">Traslado entre ubicaciones</option><option value="AJUSTE_POSITIVO">Ajuste positivo</option><option value="AJUSTE_NEGATIVO">Ajuste negativo</option><option value="STOCK_INICIAL">Carga de stock inicial</option></select></label>
                            <label>Ubicacion de origen<select name="ubicacion_id" class="form-select" required><option value="">Selecciona ubicacion</option>@foreach($activeLocations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach</select></label>
                            <label data-movement-destination hidden>Ubicacion de destino<select name="ubicacion_destino_id" class="form-select"><option value="">Selecciona ubicacion</option>@foreach($activeLocations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach</select></label>
                            <label>Articulo<select name="variante_id" class="form-select" required data-inventory-search-select data-search-placeholder="Buscar por codigo, articulo o talla"><option value="">Selecciona articulo y talla</option>@foreach($variantOptions as $variant)<option value="{{ $variant->id }}">{{ $variant->producto->codigo }} - {{ $variant->producto->nombre }} · {{ $variant->talla }}</option>@endforeach</select></label>
                            <label>Cantidad<input name="cantidad" type="number" min="0.001" step="0.001" class="form-control" required></label>
                            <label>Fecha y hora<input name="ocurrido_en" type="datetime-local" value="{{ now()->format('Y-m-d\\TH:i') }}" class="form-control" required></label>
                            <label>Persona o destinatario<input name="destinatario_nombre" class="form-control" maxlength="200" placeholder="Para entrega EPP"></label>
                            <label>RUT destinatario<input name="destinatario_rut" class="form-control" maxlength="30" placeholder="Sin puntos, con guion"></label>
                            <label>Centro de costo<select name="centro_costo" class="form-select" data-inventory-search-select data-search-placeholder="Buscar centro de costo"><option value="">Sin centro de costo</option>@foreach($costCenters as $costCenter)<option value="{{ $costCenter->codigo }}">{{ $costCenter->codigo }} · {{ $costCenter->nombre }}</option>@endforeach</select></label>
                            <label>Tipo documento<select name="documento_tipo" class="form-select"><option value="">Sin documento</option>@foreach(\App\Models\InventarioMovimiento::TIPOS_DOCUMENTO as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                            <label>Nro. documento<input name="documento_numero" class="form-control" maxlength="100" placeholder="Referencia externa"></label>
                            <label>Costo unitario<input name="costo_unitario" type="number" min="0" step="0.01" class="form-control" placeholder="Opcional"></label>
                        </div>
                        <label class="inventory-wide-label">Observacion<input name="observacion" class="form-control" maxlength="500" placeholder="Contexto del movimiento"></label>
                        <div class="inventory-form-actions"><button type="submit" class="btn btn-primary inventory-btn"><i class="bi bi-check2-circle"></i>Registrar movimiento</button></div>
                    </form>
                @endif
            </div>
            <aside class="inventory-side-note"><i class="bi bi-arrow-left-right"></i><strong>Traslado en dos pasos.</strong><span>Al trasladar, el sistema descuenta el origen y suma el destino como dos registros enlazados.</span></aside>
        </section>
        <section class="inventory-section">
            <div class="inventory-section-title"><div><h2>Kardex reciente</h2><p>Cada fila identifica que cambio, donde y quien lo registro.</p></div></div>
            <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Fecha</th><th>Tipo</th><th>Articulo</th><th>Ubicacion</th><th>Destino / CC</th><th>Registrado por</th><th class="text-end">Cantidad</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ optional($movement->ocurrido_en)->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', $movement->tipo) }}</td><td>{{ $movement->producto->nombre ?? '-' }}<small>{{ $movement->variante->talla ?? '' }}</small></td><td>{{ $movement->ubicacion->nombre ?? '-' }}</td><td>{{ $movement->destinatario_nombre ?: ($movement->centro_costo ?: '-') }}</td><td>{{ $movement->registrado_por_nombre ?: '-' }}</td><td class="text-end {{ $movement->cantidad < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->cantidad > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->cantidad, 3, ',', '.'), '0'), ',') }}</td></tr>@empty<tr><td colspan="7" class="inventory-empty">Aun no hay movimientos registrados.</td></tr>@endforelse</tbody></table></div>
        </section>

    @elseif($vista === 'conteos')
        <section class="inventory-workspace">
            <div class="inventory-section">
                <div class="inventory-section-title"><div><h2>Nuevo conteo fisico</h2><p>Genera una hoja de conteo por ubicacion. Al aprobar las diferencias, se transforman en ajustes trazables.</p></div></div>
                @if($activeLocations->isEmpty())
                    <div class="inventory-notice"><i class="bi bi-info-circle"></i>Crea una ubicacion antes de iniciar un conteo.</div>
                @else
                    <form method="POST" action="{{ route('inventario-bodega.conteos.store') }}" class="inventory-form">
                        @csrf
                        <div class="inventory-form-grid three">
                            <label>Ubicacion<select name="ubicacion_id" class="form-select" required><option value="">Selecciona ubicacion</option>@foreach($activeLocations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach</select></label>
                            <label>Fecha de corte<input name="fecha_corte" type="date" class="form-control" value="{{ now()->toDateString() }}" required></label>
                            <label class="inventory-checkbox"><input name="incluir_sin_stock" type="checkbox" value="1"><span>Incluir articulos sin stock</span></label>
                        </div>
                        <label class="inventory-wide-label">Observacion<input name="observacion" class="form-control" maxlength="500" placeholder="Conteo quincenal, motivo o responsable"></label>
                        <div class="inventory-form-actions"><button class="btn btn-primary inventory-btn" type="submit"><i class="bi bi-clipboard-plus"></i>Crear conteo</button></div>
                    </form>
                @endif
            </div>
            <aside class="inventory-side-note"><i class="bi bi-calendar2-check"></i><strong>Ritmo recomendado.</strong><span>Parte con un conteo quincenal. Cuando el proceso se estabilice, puedes pasar a una revision mensual.</span></aside>
        </section>
        <section class="inventory-section">
            <div class="inventory-section-title"><div><h2>Conteos registrados</h2><p>Los borradores se pueden completar; los aprobados ya dejaron su ajuste en el kardex.</p></div></div>
            <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Codigo</th><th>Ubicacion</th><th>Fecha corte</th><th>Estado</th><th>Lineas</th><th>Accion</th></tr></thead><tbody>@forelse($conteos as $conteo)<tr><td><span class="inventory-code">{{ $conteo->codigo }}</span></td><td>{{ $conteo->ubicacion->nombre ?? '-' }}</td><td>{{ optional($conteo->fecha_corte)->format('d/m/Y') }}</td><td><span class="inventory-status {{ $conteo->estado === 'APROBADO' ? 'is-ok' : ($conteo->estado === 'EN_REVISION' ? 'is-review' : 'is-empty') }}">{{ str_replace('_', ' ', $conteo->estado) }}</span></td><td>{{ $conteo->lineas_count ?? $conteo->lineas()->count() }}</td><td><a href="{{ route('inventario-bodega.conteos.show', $conteo) }}" class="btn btn-light inventory-icon-btn" title="Abrir conteo"><i class="bi bi-arrow-up-right-square"></i></a></td></tr>@empty<tr><td colspan="6" class="inventory-empty">Aun no hay conteos registrados.</td></tr>@endforelse</tbody></table></div>
        </section>

    @elseif($vista === 'kizeo')
        <section class="inventory-kizeo-intro">
            <div>
                <p class="inventory-kicker">Conciliacion operativa</p>
                <h2>Entregas de EPP desde Kizeo</h2>
                <p>Kizeo conserva la evidencia de la entrega. Bodega confirma la ubicacion de origen y el sistema descuenta el stock una sola vez.</p>
            </div>
            <a href="{{ route('entregas-bodega-dashboard.index') }}" class="btn btn-light inventory-btn"><i class="bi bi-box-seam"></i>Ver entregas Kizeo</a>
        </section>

        <section class="inventory-kpis inventory-kizeo-kpis">
            <article class="inventory-kpi accent-orange"><span>Pendientes de aplicar</span><strong>{{ $kizeoStats['pending'] }}</strong><small>Sin afectar el inventario</small></article>
            <article class="inventory-kpi accent-green"><span>Aplicadas</span><strong>{{ $kizeoStats['applied'] }}</strong><small>Con salida trazable</small></article>
            <article class="inventory-kpi accent-red"><span>Requieren revision</span><strong>{{ $kizeoStats['review'] }}</strong><small>Kizeo fue modificado despues</small></article>
            <article class="inventory-kpi accent-blue"><span>Regla de control</span><strong>1 vez</strong><small>Una entrega no se descuenta dos veces</small></article>
        </section>

        <section class="inventory-kizeo-notice"><i class="bi bi-shield-check"></i><div><strong>Antes de aplicar</strong><span>Verifica que la entrega este completa en Kizeo y selecciona la bodega real de salida. Si Kizeo se corrige luego, usa el reverso para reponer el stock y deja el motivo registrado.</span></div></section>

        <section class="inventory-kizeo-queue">
            @forelse($kizeoDeliveries as $delivery)
                @php
                    $application = $delivery->inventarioAplicacion;
                    $needsReview = $application && $application->estado === 'APLICADA' && $delivery->kizeo_updated_at && (! $application->fuente_actualizada_en || $delivery->kizeo_updated_at->gt($application->fuente_actualizada_en));
                    $deliverySuggestions = $kizeoSuggestions[$delivery->id] ?? [];
                    $deliveryItems = $delivery->items->where('cantidad', '>', 0);
                @endphp
                <details class="inventory-delivery-card{{ $needsReview ? ' needs-review' : '' }}">
                    <summary class="inventory-delivery-header" title="Abrir o cerrar el detalle de esta entrega">
                        <div class="inventory-delivery-identification">
                            <span class="inventory-code">KZ-{{ $delivery->kizeo_record_number ?: $delivery->kizeo_data_id }}</span>
                            <h3>{{ $delivery->nombre ?: 'Sin trabajador identificado' }}</h3>
                            <p>{{ $delivery->rut ?: 'Sin RUT' }} · {{ $delivery->centro ?: 'Sin centro informado' }} · {{ optional($delivery->fecha_pedido)->format('d/m/Y') ?: 'Sin fecha' }}</p>
                        </div>
                        <div class="inventory-delivery-status">
                            <span class="inventory-delivery-items"><i class="bi bi-list-check"></i>{{ $deliveryItems->count() }} {{ $deliveryItems->count() === 1 ? 'item' : 'items' }}</span>
                            @if(! $application)
                                <span class="inventory-status is-review" title="Esta entrega aun no genera una salida de stock.">No descontada</span>
                            @elseif($application->estado === 'REVERSADA')
                                <span class="inventory-status is-empty" title="La salida fue reversada y el stock ya fue repuesto.">Stock repuesto</span>
                            @else
                                <span class="inventory-status is-ok" title="Esta entrega ya descontó stock y no puede aplicarse nuevamente.">Salida descontada</span>
                                @if($needsReview)
                                    <span class="inventory-status is-critical" title="Kizeo fue modificado despues de descontar el stock.">Kizeo actualizado</span>
                                @endif
                            @endif
                            <i class="bi bi-chevron-down inventory-delivery-toggle" aria-hidden="true"></i>
                        </div>
                    </summary>

                    <div class="inventory-delivery-body">
                        <div class="inventory-delivery-body-actions"><a href="{{ route('entregas-bodega-dashboard.document', $delivery) }}" class="inventory-link" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i>Ver comprobante de Kizeo</a></div>
                        @if(! $application)
                            @if($activeLocations->isEmpty() || $variantOptions->isEmpty())
                                <div class="inventory-notice"><i class="bi bi-info-circle"></i>Debes tener ubicaciones y articulos activos antes de aplicar entregas Kizeo.</div>
                            @else
                                <form method="POST" action="{{ route('inventario-bodega.entregas-kizeo.aplicar', $delivery) }}" class="inventory-kizeo-form">
                                    @csrf
                                    <div class="inventory-kizeo-form-heading">
                                        <label>Ubicacion de salida<select name="ubicacion_id" class="form-select" required><option value="">Selecciona la bodega que entrego</option>@foreach($activeLocations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach</select></label>
                                        <p>Las cantidades vienen de Kizeo. Solo se elige la variante real del inventario cuando el nombre no coincide.</p>
                                    </div>
                                    <div class="inventory-table-wrap"><table class="inventory-table inventory-table-compact"><thead><tr><th>Articulo informado</th><th>Talla</th><th class="text-end">Cantidad</th><th>Relacion con inventario</th></tr></thead><tbody>
                                        @foreach($deliveryItems as $item)
                                            <tr><td><strong>{{ $item->articulo ?: 'Sin articulo' }}</strong></td><td>{{ $item->talla ?: 'Estandar' }}</td><td class="text-end">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', '.'), '0'), ',') }}</td><td><select name="lineas[{{ $item->id }}][variante_id]" class="form-select form-select-sm" required data-inventory-search-select data-search-placeholder="Buscar por codigo, articulo o talla"><option value="">Selecciona articulo y talla</option>@foreach($variantOptions as $variant)<option value="{{ $variant->id }}" @selected(($deliverySuggestions[$item->id] ?? null) === $variant->id)>{{ $variant->producto->codigo }} - {{ $variant->producto->nombre }} · {{ $variant->talla }}</option>@endforeach</select></td></tr>
                                        @endforeach
                                    </tbody></table></div>
                                    <div class="inventory-form-actions"><button class="btn btn-primary inventory-btn" type="submit"><i class="bi bi-box-arrow-up-right"></i>Aplicar salida de stock</button></div>
                                </form>
                            @endif
                        @else
                            <div class="inventory-delivery-summary">
                                <div><span>Ubicacion de salida</span><strong>{{ $application->ubicacion->nombre ?? '-' }}</strong></div>
                                <div><span>Aplicada</span><strong>{{ optional($application->aplicada_en)->format('d/m/Y H:i') ?: '-' }}</strong></div>
                                <div><span>Lineas descontadas</span><strong>{{ $application->lineas->count() }}</strong></div>
                                @if($application->estado === 'REVERSADA')<div><span>Reversada</span><strong>{{ optional($application->revertida_en)->format('d/m/Y H:i') ?: '-' }}</strong></div>@endif
                            </div>
                            @if($needsReview)
                                <div class="inventory-source-warning"><i class="bi bi-exclamation-triangle-fill"></i><span>Esta entrega fue modificada en Kizeo despues de aplicar el stock. Revisa el comprobante y reversa la salida si ya no corresponde.</span></div>
                            @endif
                            @if($application->estado === 'APLICADA' && $canEdit)
                                <details class="inventory-reverse-details"><summary><i class="bi bi-arrow-counterclockwise"></i>Corregir esta aplicacion</summary><form method="POST" action="{{ route('inventario-bodega.entregas-kizeo.revertir', $application) }}" class="inventory-reverse-form">@csrf<label>Motivo del reverso<input name="motivo_reversion" class="form-control" minlength="5" maxlength="500" required placeholder="Ej. entrega anulada o cantidades corregidas en Kizeo"></label><button type="submit" class="btn btn-light inventory-btn" onclick="return confirm('Se repondra el stock con movimientos nuevos. ¿Continuar?')"><i class="bi bi-arrow-counterclockwise"></i>Reversar salida</button></form></details>
                            @endif
                        @endif
                    </div>
                </details>
            @empty
                <section class="inventory-section"><div class="inventory-empty">Aun no hay entregas sincronizadas desde Kizeo. Usa el boton “Sincronizar Kizeo” en Entregas EPP y vuelve aqui para conciliarlas.</div></section>
            @endforelse
        </section>

    @else
        <section class="inventory-section" id="catalogo">
            <div class="inventory-section-title"><div><h2>Catalogo autogestionable</h2><p>Productos, ubicaciones y proveedores se administran aqui. Los movimientos historicos no se eliminan al desactivar un registro.</p></div></div>
            <div class="inventory-catalog-grid">
                <details class="inventory-details" open>
                    <summary><span><i class="bi bi-box-seam"></i>Productos y tallas</span><i class="bi bi-chevron-down"></i></summary>
                    <div class="inventory-details-body">
                        <div class="inventory-split-forms">
                            <form method="POST" action="{{ route('inventario-bodega.productos.store') }}" class="inventory-compact-form">@csrf
                                <h3>Agregar producto</h3>
                                <div class="inventory-form-grid two">
                                    <label>Codigo<input name="codigo" class="form-control" maxlength="80" placeholder="Automatico si se deja vacio" value="{{ old('codigo') }}"></label>
                                    <label>Producto<input name="nombre" class="form-control" maxlength="220" required value="{{ old('nombre') }}"></label>
                                    <label>Tipo<select name="tipo" class="form-select"><option value="">Selecciona un tipo</option>@foreach($productTypes as $type)<option value="{{ $type }}" @selected(old('tipo') === $type)>{{ $type }}</option>@endforeach</select></label>
                                    <label>Categoria<select name="categoria" class="form-select" data-product-category-select><option value="">Selecciona una categoria</option>@foreach($productCategories as $category)<option value="{{ $category }}" @selected(old('categoria') === $category)>{{ $category }}</option>@endforeach</select></label>
                                    <label>Subcategoria<select name="subcategoria" class="form-select" data-product-subcategory-select><option value="">Selecciona una subcategoria</option>@foreach($productSubcategories as $subcategory)<option value="{{ $subcategory['nombre'] }}" data-category="{{ $subcategory['categoria'] }}" @selected(old('subcategoria') === $subcategory['nombre'])>{{ $subcategory['nombre'] }}</option>@endforeach</select></label>
                                    <label>Unidad<select name="unidad_medida" class="form-select">@foreach($productUnits as $unit)<option value="{{ $unit }}" @selected(old('unidad_medida', 'Unidad') === $unit)>{{ $unit }}</option>@endforeach</select></label>
                                    <label>Stock minimo<input name="stock_minimo" type="number" min="0" step="0.001" class="form-control" value="{{ old('stock_minimo', 0) }}"></label>
                                    <label>Tallas o variantes<input name="tallas" class="form-control" maxlength="500" placeholder="S, M, L o ESTANDAR" value="{{ old('tallas') }}"></label>
                                </div><input type="hidden" name="activo" value="1"><button class="btn btn-primary inventory-btn" type="submit"><i class="bi bi-plus-lg"></i>Agregar producto</button>
                            </form>
                            <form method="POST" action="{{ route('inventario-bodega.productos.importar') }}" enctype="multipart/form-data" class="inventory-compact-form">@csrf
                                <h3>Importar catalogo</h3><p>Admite la plantilla SAEP y la lista EPP con columnas Tipo, Categoria, Sub Categoria, Item y Formato. Los sufijos T-39, T-M y T-NA se convierten en tallas o variantes.</p><div class="inventory-import-flow"><i class="bi bi-box-seam"></i><div><strong>El catalogo parte en cero.</strong><span>La importacion crea productos y tallas, sin saldo. Despues registra una compra o un conteo inicial por ubicacion para cargar existencias sin perder trazabilidad.</span></div></div><div class="inventory-import-actions"><a href="{{ route('inventario-bodega.index', ['vista' => 'ingresos']) }}" class="inventory-link"><i class="bi bi-box-arrow-in-down"></i>Cargar desde compra</a><a href="{{ route('inventario-bodega.index', ['vista' => 'conteos']) }}" class="inventory-link"><i class="bi bi-clipboard-check"></i>Cargar desde conteo</a></div><a href="{{ route('inventario-bodega.productos.plantilla') }}" class="inventory-link"><i class="bi bi-download"></i>Descargar plantilla</a><label>Archivo Excel o CSV<input name="archivo" type="file" accept=".xlsx,.xls,.csv" class="form-control" required></label><button class="btn btn-light inventory-btn" type="submit"><i class="bi bi-upload"></i>Importar productos</button>
                            </form>
                        </div>
                        <form method="GET" action="{{ route('inventario-bodega.index') }}" class="inventory-product-search"><input type="hidden" name="vista" value="catalogo"><input class="form-control" name="producto_buscar" value="{{ $productSearch }}" placeholder="Buscar por codigo, nombre o categoria"><button class="btn btn-light inventory-btn" type="submit"><i class="bi bi-search"></i>Buscar</button></form>
                        <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Codigo</th><th>Producto</th><th>Tipo</th><th>Tallas</th><th>Minimo</th><th>Estado</th></tr></thead><tbody>@forelse($products as $product)<tr><td><span class="inventory-code">{{ $product->codigo }}</span></td><td><strong>{{ $product->nombre }}</strong><small>{{ $product->categoria ?: 'Sin categoria' }}</small></td><td>{{ $product->tipo ?: '-' }}</td><td>{{ $product->variantes->pluck('talla')->join(', ') }}</td><td>{{ rtrim(rtrim(number_format((float) $product->stock_minimo, 3, ',', '.'), '0'), ',') }}</td><td><span class="inventory-status {{ $product->activo ? 'is-ok' : 'is-empty' }}">{{ $product->activo ? 'Activo' : 'Inactivo' }}</span></td></tr>@empty<tr><td colspan="6" class="inventory-empty">No hay productos registrados.</td></tr>@endforelse</tbody></table></div>
                        <div class="inventory-pagination">{{ $products->links() }}</div>
                        @if($canEdit && $products->isNotEmpty())
                            <section class="inventory-product-management">
                            <form method="POST" id="product-editor" data-action-base="{{ url('inventario-bodega/productos') }}" class="inventory-editor inventory-product-general">@csrf @method('PUT')
                                <div><h3>Gestionar producto y tallas</h3><p>Selecciona el producto una sola vez. Aquí actualizas sus datos generales y, abajo, el saldo de cada talla por separado.</p></div>
                                <label>Producto<select id="product-editor-select" class="form-select"><option value="">Selecciona un producto</option>@foreach($products as $product)@php($editorVariants = $product->variantes->sortBy('talla')->map(fn ($variant) => ['id' => $variant->id, 'talla' => $variant->talla ?: 'ESTANDAR', 'codigo' => $variant->codigo, 'stock_minimo' => (float) ($variant->stock_minimo ?? $product->stock_minimo), 'activo' => (bool) $variant->activo])->values())<option value="{{ $product->id }}" data-nombre="{{ $product->nombre }}" data-tipo="{{ $product->tipo }}" data-categoria="{{ $product->categoria }}" data-subcategoria="{{ $product->subcategoria }}" data-unidad_medida="{{ $product->unidad_medida }}" data-stock_minimo="{{ $product->stock_minimo }}" data-tallas="{{ $product->variantes->pluck('talla')->join(', ') }}" data-variants="{{ $editorVariants->toJson() }}" data-active="{{ $product->activo ? '1' : '0' }}" @selected($editingProductId === $product->id)>{{ $product->codigo }} - {{ $product->nombre }}</option>@endforeach</select></label>
                                <div class="inventory-form-grid two"><label>Nombre<input name="nombre" class="form-control" maxlength="220" required></label><label>Tipo<select name="tipo" class="form-select"><option value="">Selecciona un tipo</option>@foreach($productTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label><label>Categoria<select name="categoria" class="form-select" data-product-category-select><option value="">Selecciona una categoria</option>@foreach($productCategories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></label><label>Subcategoria<select name="subcategoria" class="form-select" data-product-subcategory-select><option value="">Selecciona una subcategoria</option>@foreach($productSubcategories as $subcategory)<option value="{{ $subcategory['nombre'] }}" data-category="{{ $subcategory['categoria'] }}">{{ $subcategory['nombre'] }}</option>@endforeach</select></label><label>Unidad<select name="unidad_medida" class="form-select">@foreach($productUnits as $unit)<option value="{{ $unit }}">{{ $unit }}</option>@endforeach</select></label><label>Stock minimo<input name="stock_minimo" type="number" min="0" step="0.001" class="form-control"></label><label>Tallas o variantes<input name="tallas" class="form-control" maxlength="500"></label><label class="inventory-checkbox"><input type="hidden" name="activo" value="0"><input name="activo" type="checkbox" value="1" checked><span>Producto activo</span></label></div>
                                <button class="btn btn-light inventory-btn" type="submit" disabled data-editor-submit><i class="bi bi-save2"></i>Guardar cambios</button>
                            </form>
                            @if($activeLocations->isNotEmpty())
                                <section id="product-variant-editor" class="inventory-variant-editor" data-action="{{ route('inventario-bodega.stock-talla.store') }}" data-csrf="{{ csrf_token() }}" data-stocks="{{ $variantStocksByLocation->toJson() }}" data-product-page="{{ $products->currentPage() }}" data-product-search="{{ $productSearch }}" hidden>
                                    <div class="inventory-variant-editor-heading">
                                        <div><h3>Desglose por talla</h3><p id="product-variant-editor-copy">Selecciona un producto y una ubicación para editar solo la talla que corresponda.</p></div>
                                        <label>Ubicación para los saldos<select id="product-variant-location" class="form-select"><option value="">Selecciona una ubicación</option>@foreach($activeLocations as $location)<option value="{{ $location->id }}" @selected($selectedLocation === $location->id)>{{ $location->nombre }}</option>@endforeach</select></label>
                                    </div>
                                    <div id="product-variant-editor-rows" class="inventory-variant-editor-rows"></div>
                                </section>
                            @endif
                            </section>
                        @endif
                    </div>
                </details>

                <details class="inventory-details" id="ubicaciones" @if($activeLocations->isEmpty()) open @endif>
                    <summary><span><i class="bi bi-geo-alt"></i>Ubicaciones de stock</span><i class="bi bi-chevron-down"></i></summary>
                    <div class="inventory-details-body inventory-split-forms">
                        <form method="POST" action="{{ route('inventario-bodega.ubicaciones.store') }}" class="inventory-compact-form">@csrf
                            <h3>Agregar ubicacion</h3><div class="inventory-form-grid two"><label>Codigo<input name="codigo" class="form-control" maxlength="40" placeholder="Ej. BOD-01" required></label><label>Nombre oficial<input name="nombre" class="form-control" maxlength="160" required></label><label>Tipo<select name="tipo" class="form-select" required>@foreach(\App\Models\InventarioUbicacion::TIPOS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label><label>Descripcion<input name="descripcion" class="form-control" maxlength="300" placeholder="Opcional"></label></div><input type="hidden" name="activo" value="1"><button class="btn btn-primary inventory-btn" type="submit"><i class="bi bi-plus-lg"></i>Guardar ubicacion</button>
                        </form>
                        <div class="inventory-list-panel"><h3>Ubicaciones actuales</h3>@forelse($locations as $location)<div class="inventory-list-row"><div><strong>{{ $location->nombre }}</strong><small>{{ $location->codigo }} · {{ $location->tipo }}</small></div><span class="inventory-status {{ $location->activo ? 'is-ok' : 'is-empty' }}">{{ $location->activo ? 'Activa' : 'Inactiva' }}</span></div>@empty<p class="inventory-empty-copy">Aun no hay ubicaciones. Registra las tres bodegas fisicas y la zona de despacho.</p>@endforelse
                            @if($canEdit && $locations->isNotEmpty())
                                <form method="POST" id="location-editor" data-action-base="{{ url('inventario-bodega/ubicaciones') }}" class="inventory-inline-editor">@csrf @method('PUT')<label>Editar ubicacion<select id="location-editor-select" class="form-select"><option value="">Selecciona una ubicacion</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-codigo="{{ $location->codigo }}" data-nombre="{{ $location->nombre }}" data-tipo="{{ $location->tipo }}" data-descripcion="{{ $location->descripcion }}" data-active="{{ $location->activo ? '1' : '0' }}">{{ $location->codigo }} - {{ $location->nombre }}</option>@endforeach</select></label><div class="inventory-form-grid two"><label>Codigo<input name="codigo" class="form-control" maxlength="40" required></label><label>Nombre<input name="nombre" class="form-control" maxlength="160" required></label><label>Tipo<select name="tipo" class="form-select">@foreach(\App\Models\InventarioUbicacion::TIPOS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label><label>Descripcion<input name="descripcion" class="form-control" maxlength="300"></label></div><label class="inventory-checkbox"><input type="hidden" name="activo" value="0"><input name="activo" type="checkbox" value="1" checked><span>Ubicacion activa</span></label><button class="btn btn-light inventory-btn" type="submit" disabled data-editor-submit><i class="bi bi-save2"></i>Guardar ubicacion</button></form>
                            @endif
                        </div>
                    </div>
                </details>

                <details class="inventory-details">
                    <summary><span><i class="bi bi-truck"></i>Proveedores</span><i class="bi bi-chevron-down"></i></summary>
                    <div class="inventory-details-body inventory-split-forms">
                        <form method="POST" action="{{ route('inventario-bodega.proveedores.store') }}" class="inventory-compact-form">@csrf
                            <h3>Agregar proveedor</h3><div class="inventory-form-grid two"><label>Nombre<input name="nombre" class="form-control" maxlength="180" required></label><label>RUT<input name="rut" class="form-control" maxlength="30" placeholder="Sin puntos, con guion"></label><label>Contacto<input name="contacto" class="form-control" maxlength="160"></label><label>Correo<input name="email" type="email" class="form-control" maxlength="180"></label><label>Telefono<input name="telefono" class="form-control" maxlength="50"></label><label>Observacion<input name="observacion" class="form-control" maxlength="500"></label></div><input type="hidden" name="activo" value="1"><button class="btn btn-primary inventory-btn" type="submit"><i class="bi bi-plus-lg"></i>Guardar proveedor</button>
                        </form>
                        <div class="inventory-list-panel"><h3>Proveedores actuales</h3>@forelse($providers as $provider)<div class="inventory-list-row"><div><strong>{{ $provider->nombre }}</strong><small>{{ $provider->rut ?: 'Sin RUT' }}{{ $provider->contacto ? ' · ' . $provider->contacto : '' }}</small></div><span class="inventory-status {{ $provider->activo ? 'is-ok' : 'is-empty' }}">{{ $provider->activo ? 'Activo' : 'Inactivo' }}</span></div>@empty<p class="inventory-empty-copy">Aun no hay proveedores registrados.</p>@endforelse
                            @if($canEdit && $providers->isNotEmpty())
                                <form method="POST" id="provider-editor" data-action-base="{{ url('inventario-bodega/proveedores') }}" class="inventory-inline-editor">@csrf @method('PUT')<label>Editar proveedor<select id="provider-editor-select" class="form-select"><option value="">Selecciona un proveedor</option>@foreach($providers as $provider)<option value="{{ $provider->id }}" data-nombre="{{ $provider->nombre }}" data-rut="{{ $provider->rut }}" data-contacto="{{ $provider->contacto }}" data-email="{{ $provider->email }}" data-telefono="{{ $provider->telefono }}" data-observacion="{{ $provider->observacion }}" data-active="{{ $provider->activo ? '1' : '0' }}">{{ $provider->nombre }}</option>@endforeach</select></label><div class="inventory-form-grid two"><label>Nombre<input name="nombre" class="form-control" maxlength="180" required></label><label>RUT<input name="rut" class="form-control" maxlength="30"></label><label>Contacto<input name="contacto" class="form-control" maxlength="160"></label><label>Correo<input name="email" type="email" class="form-control" maxlength="180"></label><label>Telefono<input name="telefono" class="form-control" maxlength="50"></label><label>Observacion<input name="observacion" class="form-control" maxlength="500"></label></div><label class="inventory-checkbox"><input type="hidden" name="activo" value="0"><input name="activo" type="checkbox" value="1" checked><span>Proveedor activo</span></label><button class="btn btn-light inventory-btn" type="submit" disabled data-editor-submit><i class="bi bi-save2"></i>Guardar proveedor</button></form>
                            @endif
                        </div>
                    </div>
                </details>
            </div>
        </section>
    @endif
</div>

<style>
    .inventory-page { container-type: inline-size; padding: .25rem 0 2rem; color: #17213a; }
    .inventory-heading, .inventory-section-title, .inventory-header-actions, .inventory-tabs, .inventory-line-header, .inventory-form-actions, .inventory-list-row { display:flex; align-items:center; }
    .inventory-heading { justify-content:space-between; gap:1rem; margin-bottom:1rem; }
    .inventory-kicker { margin:0 0 .2rem; color:#f0642c; font-size:.75rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .inventory-heading h1 { margin:0; font-size:1.48rem; font-weight:800; letter-spacing:0; }
    .inventory-heading p:not(.inventory-kicker) { color:#64748b; margin:.22rem 0 0; font-size:.86rem; }
    .inventory-header-actions { gap:.6rem; flex-wrap:wrap; justify-content:flex-end; }
    .inventory-btn { border-radius:.48rem; font-size:.86rem; font-weight:700; min-height:2.55rem; display:inline-flex; gap:.48rem; align-items:center; justify-content:center; white-space:nowrap; }
    .inventory-btn.btn-primary { color:#fff; background:#23085d; border-color:#23085d; box-shadow:0 .35rem .85rem rgba(35,8,93,.18); }
    .inventory-btn.btn-primary:hover,.inventory-btn.btn-primary:focus { color:#fff; background:#3a147c; border-color:#3a147c; }
    .inventory-icon-btn { width:2.55rem; height:2.55rem; border-radius:.48rem; display:inline-grid; place-items:center; padding:0; }
    .inventory-alert { display:flex; gap:.7rem; align-items:flex-start; border-radius:.55rem; margin-bottom:1rem; }
    .inventory-onboarding { display:flex; align-items:center; gap:1rem; padding:1rem 1.15rem; background:#fff8e8; border:1px solid #f6c86c; border-left:4px solid #f0642c; border-radius:.6rem; margin-bottom:1rem; }
    .inventory-onboarding-icon { width:2.55rem; height:2.55rem; border-radius:.5rem; display:grid; place-items:center; color:#9a4d0c; background:#fff; flex:0 0 auto; }
    .inventory-onboarding h2 { font-size:1rem; margin:0 0 .2rem; font-weight:800; }.inventory-onboarding p { margin:0; color:#6c5730; font-size:.87rem; }.inventory-onboarding .btn { margin-left:auto; }
    .inventory-tabs { gap:.3rem; overflow-x:auto; border-bottom:1px solid #dbe2ef; margin-bottom:1.2rem; scrollbar-width:thin; }
    .inventory-tab { display:inline-flex; gap:.45rem; align-items:center; padding:.72rem .8rem; color:#64748b; text-decoration:none; border-bottom:3px solid transparent; font-size:.84rem; font-weight:750; white-space:nowrap; }
    .inventory-tab:hover { color:#23085d; }.inventory-tab.active { color:#23085d; border-color:#ff6a31; }
    .inventory-section { background:#fff; border:1px solid #e0e6f0; border-radius:.65rem; box-shadow:0 .4rem 1.2rem rgba(29,41,67,.05); padding:.85rem; margin-bottom:.85rem; }
    .inventory-filter-section { padding:.72rem .85rem; }.inventory-filter-grid { display:grid; grid-template-columns:minmax(185px,.75fr) minmax(240px,1.25fr) auto auto; gap:.55rem; align-items:end; }.inventory-filter-grid label { margin:0; min-width:0; }
    .inventory-filter-grid label, .inventory-form label { color:#53627d; font-size:.72rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-filter-grid .form-control, .inventory-filter-grid .form-select, .inventory-form .form-control, .inventory-form .form-select { margin-top:.28rem; min-height:2.42rem; font-size:.88rem; letter-spacing:0; text-transform:none; }
    .inventory-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.65rem; margin-bottom:.85rem; }.inventory-kpi { border:1px solid #e1e6f1; background:#fff; border-radius:.65rem; padding:.72rem .85rem; border-left-width:4px; }.inventory-kpi span { display:block; color:#64748b; font-size:.69rem; font-weight:800; text-transform:uppercase; }.inventory-kpi strong { display:block; font-size:1.35rem; line-height:1.15; margin:.2rem 0; }.inventory-kpi small { color:#7a879f; font-size:.72rem; }.accent-purple { border-left-color:#8757ec; }.accent-blue { border-left-color:#2563eb; }.accent-red { border-left-color:#e33c42; }.accent-green { border-left-color:#1aa364; }.accent-orange { border-left-color:#f0642c; }
    .inventory-section-title { justify-content:space-between; gap:1rem; margin-bottom:.8rem; }.inventory-section-title h2 { font-size:1rem; margin:0 0 .18rem; font-weight:800; }.inventory-section-title p { color:#6b778d; font-size:.82rem; margin:0; }.inventory-count { color:#596780; font-size:.78rem; white-space:nowrap; }.inventory-link { color:#351178; text-decoration:none; font-weight:800; font-size:.82rem; display:inline-flex; align-items:center; gap:.4rem; white-space:nowrap; }
    .inventory-table-wrap { overflow:auto; }.inventory-table { width:100%; min-width:700px; border-collapse:collapse; font-size:.82rem; }.inventory-table th { color:#667085; font-size:.68rem; font-weight:800; letter-spacing:.045em; text-transform:uppercase; padding:.58rem .55rem; border-bottom:1px solid #dfe5ef; white-space:nowrap; }.inventory-table td { padding:.68rem .55rem; border-bottom:1px solid #edf0f5; vertical-align:middle; }.inventory-table tbody tr:last-child td { border-bottom:0; }.inventory-table td small { display:block; margin-top:.12rem; color:#748198; font-size:.73rem; }.inventory-table-compact td { padding:.5rem .55rem; }.inventory-code { color:#45308b; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.74rem; font-weight:750; }.inventory-status { display:inline-flex; padding:.24rem .5rem; border-radius:999px; font-size:.7rem; font-weight:800; white-space:nowrap; }.inventory-status.is-ok { background:#e7f8ef; color:#087245; }.inventory-status.is-critical { background:#fff0de; color:#b85d00; }.inventory-status.is-empty { background:#eef1f5; color:#637083; }.inventory-status.is-review { background:#eee8ff; color:#5632b5; }.inventory-empty { text-align:center; padding:2rem!important; color:#7a879b; }.inventory-empty-copy { color:#718096; font-size:.85rem; margin:0; }
    .inventory-workspace { display:grid; grid-template-columns:minmax(0,1fr) minmax(250px,290px); align-items:start; gap:1.15rem; margin-bottom:1.25rem; }.inventory-workspace .inventory-section { margin:0; }.inventory-side-note { align-self:start; display:grid; gap:.45rem; padding:1.1rem 1rem; background:#f5f3ff; border-left:3px solid #8757ec; border-radius:.55rem; color:#4a3b76; font-size:.82rem; }.inventory-side-note i { font-size:1.1rem; color:#5d37b5; }.inventory-side-note strong { color:#251352; }.inventory-side-note span { max-width:31ch; line-height:1.45; }
    .inventory-form { display:grid; gap:.85rem; }.inventory-form-grid { display:grid; gap:.75rem; min-width:0; }.inventory-form-grid.two { grid-template-columns:repeat(2,minmax(0,1fr)); }.inventory-form-grid.three { grid-template-columns:repeat(3,minmax(0,1fr)); }.inventory-form label,.inventory-editor > label,.inventory-inline-editor > label { min-width:0; max-width:100%; margin:0; }.inventory-form-grid .form-control,.inventory-form-grid .form-select,.inventory-editor > label .form-control,.inventory-editor > label .form-select,.inventory-inline-editor > label .form-control,.inventory-inline-editor > label .form-select { box-sizing:border-box; max-width:100%; width:100%; }.inventory-wide-label { display:block; }.inventory-checkbox { display:flex; gap:.55rem; align-items:center; padding-top:1.65rem; text-transform:none!important; letter-spacing:0!important; font-size:.84rem!important; }.inventory-checkbox input { width:1rem; height:1rem; }.inventory-line-header { justify-content:space-between; gap:.8rem; }.inventory-line-header h3,.inventory-compact-form h3,.inventory-list-panel h3 { font-size:.9rem; font-weight:800; margin:0; }.inventory-lines { display:grid; gap:.55rem; }.inventory-line { display:grid; grid-template-columns:minmax(230px,1fr) 125px 145px 2.55rem; gap:.55rem; align-items:end; padding:.65rem; background:#f8fafc; border:1px solid #e3e8f1; border-radius:.5rem; }.inventory-form-actions { justify-content:flex-end; border-top:1px solid #e8edf4; padding-top:.8rem; }.inventory-notice { display:flex; gap:.5rem; align-items:center; background:#fff8e8; border:1px solid #f7d48b; color:#80520e; border-radius:.45rem; padding:.75rem; font-size:.84rem; }
    .inventory-catalog-grid { display:grid; gap:.75rem; min-width:0; }.inventory-details { min-width:0; border:1px solid #e1e6ef; border-radius:.55rem; }.inventory-details summary { cursor:pointer; list-style:none; padding:.82rem .9rem; display:flex; justify-content:space-between; align-items:center; color:#25324b; font-weight:800; font-size:.9rem; }.inventory-details summary::-webkit-details-marker { display:none; }.inventory-details summary span { display:flex; align-items:center; gap:.55rem; }.inventory-details[open] summary { border-bottom:1px solid #e4e9f1; }.inventory-details[open] summary > .bi-chevron-down { transform:rotate(180deg); }.inventory-details-body { min-width:0; padding:.9rem; }.inventory-split-forms { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; min-width:0; }.inventory-compact-form { display:grid; gap:.75rem; min-width:0; padding:.85rem; background:#f8fafc; border:1px solid #e3e8f1; border-radius:.5rem; }.inventory-compact-form p { margin:0; font-size:.82rem; color:#68758b; line-height:1.45; }.inventory-import-flow { display:flex; gap:.55rem; align-items:flex-start; padding:.65rem .7rem; border:1px solid #d9d4ff; background:#f6f4ff; border-radius:.45rem; color:#4a347e; }.inventory-import-flow > i { color:#5a35ba; font-size:1rem; line-height:1.25; }.inventory-import-flow strong,.inventory-import-flow span { display:block; }.inventory-import-flow strong { color:#30215f; font-size:.78rem; }.inventory-import-flow span { margin-top:.12rem; font-size:.76rem; line-height:1.35; }.inventory-import-actions { display:flex; flex-wrap:wrap; gap:.7rem; }.inventory-list-panel { min-width:0; padding:.85rem; border:1px solid #e3e8f1; border-radius:.5rem; }.inventory-list-panel h3 { margin-bottom:.55rem; }.inventory-list-row { justify-content:space-between; gap:.75rem; padding:.62rem 0; border-bottom:1px solid #edf0f4; }.inventory-list-row:last-child { border-bottom:0; }.inventory-list-row strong { display:block; font-size:.84rem; }.inventory-list-row small { display:block; color:#748198; font-size:.76rem; margin-top:.13rem; }.inventory-product-search { display:flex; gap:.55rem; min-width:0; margin:1rem 0 .65rem; }.inventory-product-search .form-control { max-width:390px; }.inventory-pagination { margin-top:.75rem; }.inventory-editor,.inventory-inline-editor { display:grid; gap:.7rem; min-width:0; margin-top:1rem; padding:.85rem; background:#f5f3ff; border:1px solid #ded5ff; border-radius:.5rem; }.inventory-editor p { margin:.2rem 0 0; color:#665b84; font-size:.8rem; }.inventory-inline-editor { margin-top:.75rem; }.inventory-editor .inventory-btn,.inventory-inline-editor .inventory-btn { justify-self:end; }
    .inventory-kizeo-intro { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.1rem; background:#f2f6ff; border:1px solid #dbe5fb; border-left:4px solid #2563eb; border-radius:.65rem; margin-bottom:1rem; }.inventory-kizeo-intro h2 { font-size:1.05rem; margin:0 0 .22rem; font-weight:800; }.inventory-kizeo-intro p:not(.inventory-kicker) { color:#61708b; font-size:.83rem; margin:0; max-width:720px; }.inventory-kizeo-kpis { grid-template-columns:repeat(4,minmax(0,1fr)); }.inventory-kizeo-notice { display:flex; align-items:flex-start; gap:.65rem; padding:.8rem 1rem; background:#fff8e8; border:1px solid #f2d392; border-radius:.6rem; color:#71501a; margin-bottom:1rem; font-size:.82rem; }.inventory-kizeo-notice i { color:#d17913; margin-top:.05rem; }.inventory-kizeo-notice strong,.inventory-kizeo-notice span { display:block; }.inventory-kizeo-notice span { margin-top:.12rem; line-height:1.45; }.inventory-kizeo-queue { display:grid; gap:.85rem; }.inventory-delivery-card { background:#fff; border:1px solid #dfe6f0; border-radius:.65rem; box-shadow:0 .35rem 1rem rgba(29,41,67,.04); overflow:hidden; }.inventory-delivery-card.needs-review { border-color:#f0bf74; }.inventory-delivery-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; padding:.9rem 1rem; border-bottom:1px solid #e7ecf3; }.inventory-delivery-header h3 { margin:.25rem 0 .12rem; font-size:.95rem; font-weight:800; }.inventory-delivery-header p { color:#708099; font-size:.78rem; margin:0; }.inventory-delivery-status { display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap; gap:.55rem; }.inventory-kizeo-form { display:grid; gap:.75rem; padding:.9rem 1rem 1rem; }.inventory-kizeo-form-heading { display:flex; align-items:end; justify-content:space-between; gap:1rem; }.inventory-kizeo-form-heading label { display:grid; gap:.28rem; min-width:280px; color:#53627d; font-size:.72rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-kizeo-form-heading .form-select { min-height:2.42rem; font-size:.88rem; letter-spacing:0; text-transform:none; }.inventory-kizeo-form-heading p { color:#738097; font-size:.79rem; margin:0; max-width:460px; line-height:1.4; }.inventory-delivery-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; background:#e4eaf2; }.inventory-delivery-summary > div { padding:.7rem 1rem; background:#fbfcfe; }.inventory-delivery-summary span { display:block; color:#748198; font-size:.68rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-delivery-summary strong { display:block; color:#273450; font-size:.82rem; margin-top:.18rem; }.inventory-source-warning { display:flex; gap:.55rem; margin:.8rem 1rem 0; padding:.7rem .8rem; background:#fff4e5; border-left:3px solid #e88120; color:#875115; font-size:.8rem; line-height:1.4; }.inventory-reverse-details { margin:.75rem 1rem 1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:.5rem; }.inventory-reverse-details summary { cursor:pointer; display:flex; align-items:center; gap:.48rem; padding:.65rem .75rem; color:#4f3b78; font-size:.8rem; font-weight:800; }.inventory-reverse-form { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:.65rem; padding:.1rem .75rem .75rem; }.inventory-reverse-form label { display:grid; gap:.28rem; color:#53627d; font-size:.72rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-reverse-form .form-control { min-height:2.42rem; font-size:.88rem; letter-spacing:0; text-transform:none; }
    .inventory-delivery-card.has-open-search-select { position:relative; z-index:4; overflow:visible; }.inventory-search-select { position:relative; min-width:230px; }.inventory-search-select-native { position:absolute!important; width:1px!important; height:1px!important; margin:-1px!important; padding:0!important; overflow:hidden!important; clip:rect(0 0 0 0)!important; white-space:nowrap!important; border:0!important; }.inventory-search-select-trigger { width:100%; min-height:2.42rem; display:flex; align-items:flex-start; justify-content:space-between; gap:.65rem; padding:.48rem .65rem; color:#25334f; background:#fff; border:1px solid #cfd8e6; border-radius:.45rem; text-align:left; font-size:.82rem; line-height:1.35; }.inventory-search-select-trigger:hover { border-color:#9eadd0; }.inventory-search-select-trigger:focus-visible { outline:0; border-color:#7250ca; box-shadow:0 0 0 .16rem rgba(114,80,202,.12); }.inventory-search-select-trigger > span { min-width:0; overflow-wrap:anywhere; }.inventory-search-select-trigger > i { color:#5b6780; font-size:.9rem; flex:0 0 auto; margin-top:.15rem; }.inventory-search-select.is-invalid .inventory-search-select-trigger { border-color:#d63946; box-shadow:0 0 0 .16rem rgba(214,57,70,.12); }.inventory-search-select-menu { position:absolute; z-index:30; top:calc(100% + .3rem); right:0; width:max(100%, min(40rem, calc(100vw - 2rem))); padding:.55rem; background:#fff; border:1px solid #cfd8e6; border-radius:.55rem; box-shadow:0 .75rem 1.7rem rgba(28,39,63,.2); }.inventory-search-select-menu.is-portal { position:fixed; z-index:1055; max-height:calc(100vh - 2rem); }.inventory-search-select-menu[hidden] { display:none; }.inventory-search-select-search { width:100%; min-height:2.35rem; padding:.46rem .62rem; color:#25334f; background:#fbfcfe; border:1px solid #d5deeb; border-radius:.4rem; font-size:.84rem; }.inventory-search-select-search:focus { outline:0; border-color:#7250ca; box-shadow:0 0 0 .16rem rgba(114,80,202,.12); }.inventory-search-select-results { max-height:16rem; margin-top:.45rem; overflow:auto; overscroll-behavior:contain; }.inventory-search-select-option,.inventory-search-select-empty { width:100%; padding:.5rem .6rem; border:0; border-radius:.35rem; text-align:left; font-size:.8rem; line-height:1.35; }.inventory-search-select-option { color:#25334f; background:transparent; }.inventory-search-select-option:hover,.inventory-search-select-option:focus-visible { color:#23085d; background:#f3f0ff; outline:0; }.inventory-search-select-option.is-selected { color:#321170; background:#eee8ff; font-weight:800; }.inventory-search-select-option span { display:block; overflow-wrap:anywhere; }.inventory-search-select-empty { color:#718096; font-style:italic; }.inventory-search-select-help { display:block; margin:.38rem .1rem 0; color:#6d7a91; font-size:.7rem; line-height:1.35; }
    body.dark-mode .inventory-page { min-height:100%; background:#111827; color:#f8fafc; }.dark-mode .inventory-heading h1,.dark-mode .inventory-section-title h2,.dark-mode .inventory-kpi strong,.dark-mode .inventory-delivery-card h3,.dark-mode .inventory-delivery-summary strong,.dark-mode .inventory-details summary,.dark-mode .inventory-list-row strong,.dark-mode .inventory-import-flow strong { color:#f8fafc; }.dark-mode .inventory-heading p:not(.inventory-kicker),.dark-mode .inventory-section-title p,.dark-mode .inventory-count,.dark-mode .inventory-kpi span,.dark-mode .inventory-kpi small,.dark-mode .inventory-table th,.dark-mode .inventory-table td small,.dark-mode .inventory-empty,.dark-mode .inventory-empty-copy,.dark-mode .inventory-delivery-header p,.dark-mode .inventory-kizeo-form-heading p,.dark-mode .inventory-delivery-summary span { color:#aeb9cc; }.dark-mode .inventory-tabs { border-color:rgba(255,255,255,.14); }.dark-mode .inventory-tab { color:#aeb9cc; }.dark-mode .inventory-tab:hover,.dark-mode .inventory-tab.active,.dark-mode .inventory-link { color:#c4b5fd; }.dark-mode .inventory-section,.dark-mode .inventory-kpi,.dark-mode .inventory-delivery-card { background:#1f2937; border-color:#374151; box-shadow:0 .4rem 1.2rem rgba(0,0,0,.16); }.dark-mode .inventory-table th,.dark-mode .inventory-table td,.dark-mode .inventory-delivery-header { border-color:#374151; }.dark-mode .inventory-code { color:#c4b5fd; }.dark-mode .inventory-side-note,.dark-mode .inventory-editor,.dark-mode .inventory-inline-editor,.dark-mode .inventory-import-flow { background:rgba(135,87,236,.12); border-color:rgba(167,139,250,.25); color:#ddd6fe; }.dark-mode .inventory-side-note strong { color:#f8fafc; }.dark-mode .inventory-line,.dark-mode .inventory-compact-form,.dark-mode .inventory-reverse-details { background:rgba(255,255,255,.035); border-color:#374151; }.dark-mode .inventory-list-panel,.dark-mode .inventory-details { border-color:#374151; }.dark-mode .inventory-list-row { border-color:rgba(255,255,255,.09); }.dark-mode .inventory-kizeo-intro { background:#172554; border-color:#3b82f6; }.dark-mode .inventory-kizeo-intro h2 { color:#f8fafc; }.dark-mode .inventory-kizeo-intro p:not(.inventory-kicker) { color:#bfdbfe; }.dark-mode .inventory-delivery-summary { background:#374151; }.dark-mode .inventory-delivery-summary > div { background:rgba(255,255,255,.035); }.dark-mode .inventory-kizeo-notice,.dark-mode .inventory-notice { background:#443107; border-color:#a16207; color:#fde68a; }.dark-mode .inventory-source-warning { background:#542a0b; color:#fed7aa; }.dark-mode .inventory-status.is-empty { background:rgba(148,163,184,.16); color:#cbd5e1; }.dark-mode .inventory-status.is-review { background:rgba(139,92,246,.2); color:#ddd6fe; }.dark-mode .inventory-search-select-trigger,.dark-mode .inventory-search-select-menu,.dark-mode .inventory-search-select-search { color:#e5edf9; background:#111827; border-color:#475569; }.dark-mode .inventory-search-select-trigger > i { color:#aeb9cc; }.dark-mode .inventory-search-select-trigger:hover { border-color:#7484a0; }.dark-mode .inventory-search-select-option { color:#e5edf9; }.dark-mode .inventory-search-select-option:hover,.dark-mode .inventory-search-select-option:focus-visible { color:#fff; background:#312252; }.dark-mode .inventory-search-select-option.is-selected { color:#ede9fe; background:#3b2b62; }.dark-mode .inventory-search-select-empty,.dark-mode .inventory-search-select-help { color:#aeb9cc; }
    @container (max-width: 1050px) { .inventory-kpis,.inventory-kizeo-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }.inventory-workspace { grid-template-columns:1fr; }.inventory-side-note { display:flex; align-items:center; }.inventory-side-note strong { margin-right:.3rem; }.inventory-side-note span { flex:1; }.inventory-delivery-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @container (max-width: 760px) { .inventory-page { padding:1rem .85rem 5rem; }.inventory-heading { align-items:flex-start; flex-direction:column; gap:.8rem; }.inventory-header-actions { justify-content:flex-start; width:100%; }.inventory-header-actions .inventory-btn { flex:1; }.inventory-onboarding { align-items:flex-start; flex-wrap:wrap; }.inventory-onboarding .btn { margin-left:3.55rem; }.inventory-filter-grid { align-items:stretch; }.inventory-filter-grid .inventory-btn { flex:1; }.inventory-form-grid.two,.inventory-form-grid.three,.inventory-split-forms { grid-template-columns:1fr; }.inventory-line { grid-template-columns:1fr 1fr 2.55rem; }.inventory-line > label:first-child { grid-column:1/-1; }.inventory-line > label:nth-child(3) { grid-column:1/3; }.inventory-line > button { grid-column:3; grid-row:2; }.inventory-kpis,.inventory-kizeo-kpis { grid-template-columns:1fr 1fr; }.inventory-section { padding:.8rem; }.inventory-section-title { align-items:flex-start; }.inventory-side-note { align-items:flex-start; flex-wrap:wrap; }.inventory-side-note span { width:100%; }.inventory-product-search .form-control { max-width:none; }.inventory-tab { padding:.65rem .7rem; }.inventory-kizeo-intro,.inventory-delivery-header,.inventory-kizeo-form-heading { align-items:flex-start; flex-direction:column; }.inventory-kizeo-intro .btn { width:100%; }.inventory-delivery-status { justify-content:flex-start; }.inventory-kizeo-form-heading label { min-width:0; width:100%; }.inventory-reverse-form { grid-template-columns:1fr; }.inventory-reverse-form .inventory-btn { width:100%; }.inventory-delivery-summary { grid-template-columns:1fr 1fr; }.inventory-kizeo-form .inventory-table { min-width:0; }.inventory-kizeo-form .inventory-table thead { display:none; }.inventory-kizeo-form .inventory-table tbody { display:grid; gap:.65rem; }.inventory-kizeo-form .inventory-table tbody tr { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.3rem .65rem; padding:.7rem; border:1px solid #e1e6f1; border-radius:.5rem; background:#f8fafc; }.inventory-kizeo-form .inventory-table tbody td { display:block; padding:0; border:0; }.inventory-kizeo-form .inventory-table tbody td:first-child { grid-column:1/-1; }.inventory-kizeo-form .inventory-table tbody td:nth-child(2)::before { content:'Talla: '; color:#718096; font-weight:700; }.inventory-kizeo-form .inventory-table tbody td:nth-child(3)::before { content:'Cantidad: '; color:#718096; font-weight:700; }.inventory-kizeo-form .inventory-table tbody td:nth-child(4) { grid-column:1/-1; margin-top:.25rem; }.inventory-kizeo-form .inventory-table tbody td:nth-child(4)::before { content:'Relacion con inventario'; display:block; margin-bottom:.28rem; color:#53627d; font-size:.68rem; font-weight:800; letter-spacing:.035em; text-align:left; text-transform:uppercase; }.dark-mode .inventory-kizeo-form .inventory-table tbody tr { background:rgba(255,255,255,.035); border-color:#374151; }.dark-mode .inventory-kizeo-form .inventory-table tbody td:nth-child(2)::before,.dark-mode .inventory-kizeo-form .inventory-table tbody td:nth-child(3)::before,.dark-mode .inventory-kizeo-form .inventory-table tbody td:nth-child(4)::before { color:#aeb9cc; } }
    @container (max-width: 440px) { .inventory-kpis,.inventory-kizeo-kpis { grid-template-columns:1fr; }.inventory-header-actions { display:grid; grid-template-columns:1fr; }.inventory-onboarding .btn { margin-left:0; width:100%; }.inventory-filter-grid { display:grid; grid-template-columns:1fr 2.55rem; }.inventory-filter-grid label { grid-column:1/-1; }.inventory-filter-grid .inventory-btn { grid-column:1; }.inventory-filter-grid .inventory-icon-btn { grid-column:2; }.inventory-line { grid-template-columns:1fr 2.55rem; }.inventory-line > label:nth-child(2),.inventory-line > label:nth-child(3) { grid-column:1; }.inventory-line > button { grid-column:2; grid-row:3; }.inventory-form-actions .inventory-btn { width:100%; }.inventory-delivery-summary { grid-template-columns:1fr; } }

    .inventory-delivery-card > .inventory-delivery-header { cursor:pointer; list-style:none; border-bottom:0; }
    .inventory-delivery-card > .inventory-delivery-header::-webkit-details-marker { display:none; }
    .inventory-delivery-card[open] > .inventory-delivery-header { border-bottom:1px solid #e7ecf3; background:#fbfcfe; }
    .inventory-delivery-card > .inventory-delivery-header:focus-visible { outline:3px solid rgba(114,80,202,.3); outline-offset:-3px; }
    .inventory-delivery-identification { min-width:0; }
    .inventory-delivery-items { display:inline-flex; align-items:center; gap:.32rem; color:#64748b; font-size:.75rem; font-weight:750; white-space:nowrap; }
    .inventory-delivery-items i { color:#6677a1; }
    .inventory-delivery-toggle { display:inline-grid; place-items:center; width:1.8rem; height:1.8rem; color:#5d37b5; border:1px solid #d9e0ea; border-radius:.45rem; background:#fff; transition:transform .16s ease; flex:0 0 auto; }
    .inventory-delivery-card[open] .inventory-delivery-toggle { transform:rotate(180deg); }
    .inventory-delivery-body { min-width:0; }
    .inventory-delivery-body-actions { display:flex; justify-content:flex-end; padding:.48rem 1rem; border-bottom:1px solid #edf1f6; background:#fbfcfe; }
    .inventory-delivery-body-actions .inventory-link { font-size:.78rem; }
    .dark-mode .inventory-delivery-card[open] > .inventory-delivery-header,.dark-mode .inventory-delivery-body-actions { background:rgba(255,255,255,.025); border-color:#374151; }
    .dark-mode .inventory-delivery-items { color:#aeb9cc; }.dark-mode .inventory-delivery-toggle { color:#c4b5fd; background:#1f2937; border-color:#475569; }

    /* Compact, resilient inventory controls for desktop zoom and smaller work areas. */
    .inventory-page { box-sizing:border-box; width:100%; max-width:1680px; margin:0 auto; padding:.65rem clamp(.85rem,1.35vw,1.5rem) 2.25rem; }
    .inventory-page *, .inventory-page *::before, .inventory-page *::after { box-sizing:border-box; }
    .inventory-page .form-control, .inventory-page .form-select { width:100%; min-width:0; min-height:2.5rem; margin-top:.3rem; padding:.48rem .7rem; color:#273550; background:#fbfcfe; border:1px solid #d5deeb; border-radius:.5rem; box-shadow:none; font-size:.86rem; line-height:1.35; }
    .inventory-page .form-control::placeholder { color:#98a4b7; opacity:1; }
    .inventory-page .form-control:focus, .inventory-page .form-select:focus { color:#1e2b43; background:#fff; border-color:#7250ca; box-shadow:0 0 0 .16rem rgba(114,80,202,.12); }
    .inventory-page .inventory-btn { box-sizing:border-box; min-height:2.5rem; max-width:100%; padding:.54rem .84rem; border:1px solid transparent; text-decoration:none; line-height:1.2; }
    .inventory-page .inventory-btn.btn-light { color:#26334c; background:#fff; border-color:#d8e0ec; box-shadow:0 .12rem .32rem rgba(30,41,59,.06); }
    .inventory-page .inventory-btn.btn-light:hover { color:#23085d; background:#f8f6ff; border-color:#bfaef1; }
    .inventory-page .inventory-icon-btn { display:inline-grid; place-items:center; width:2.5rem; min-width:2.5rem; padding:0; }
    .inventory-heading { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:1rem 1.25rem; }
    .inventory-heading > :first-child, .inventory-header-actions, .inventory-onboarding > div, .inventory-section > *, .inventory-details-body > * { min-width:0; }
    .inventory-header-actions { gap:.6rem; }
    .inventory-onboarding { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.8rem 1rem; padding:1rem 1.1rem; }
    .inventory-onboarding .btn { margin-left:0; }
    .inventory-filter-section { padding:.9rem 1rem; }
    .inventory-filter-grid { grid-template-columns:minmax(195px,260px) minmax(260px,430px) auto auto; justify-content:start; gap:.7rem; }
    .inventory-filter-grid label { display:grid; gap:.08rem; }
    .inventory-filter-grid .form-control, .inventory-filter-grid .form-select { margin-top:.2rem; }
    .inventory-section { box-sizing:border-box; padding:1rem; }
    .inventory-details-body { padding:1rem; }
    .inventory-split-forms { gap:1rem; }
    .inventory-compact-form, .inventory-list-panel { padding:1rem; border-radius:.6rem; }
    .inventory-compact-form { gap:.8rem; }
    .inventory-product-search { display:grid; grid-template-columns:minmax(220px,360px) auto; align-items:end; justify-content:start; gap:.6rem; margin:1rem 0 .8rem; }
    .inventory-product-search .form-control { max-width:none; margin:0; }
    .inventory-editor, .inventory-inline-editor { padding:1rem; }
    .inventory-list-row { gap:1rem; }

    .dark-mode .inventory-page .form-control, .dark-mode .inventory-page .form-select { color:#e5edf9; background:#111827; border-color:#475569; }
    .dark-mode .inventory-page .form-control::placeholder { color:#8090a7; }
    .dark-mode .inventory-page .form-control:focus, .dark-mode .inventory-page .form-select:focus { color:#f8fafc; background:#0f172a; border-color:#a78bfa; box-shadow:0 0 0 .16rem rgba(167,139,250,.18); }
    .dark-mode .inventory-page .inventory-btn.btn-light { color:#e5edf9; background:#1f2937; border-color:#475569; box-shadow:none; }
    .dark-mode .inventory-page .inventory-btn.btn-light:hover { color:#fff; background:#293548; border-color:#a78bfa; }

    @container (max-width: 1280px) {
        .inventory-heading { grid-template-columns:1fr; align-items:start; }
        .inventory-header-actions { width:100%; justify-content:flex-start; }
    }
    @container (max-width: 1020px) {
        .inventory-onboarding { grid-template-columns:auto minmax(0,1fr); align-items:start; }
        .inventory-onboarding .btn { grid-column:2; justify-self:start; }
    }
    @container (max-width: 1050px) {
        .inventory-workspace { gap:.85rem; margin-bottom:1rem; }
        .inventory-side-note { align-self:auto; }
        .inventory-side-note span { max-width:none; }
    }
    @container (max-width: 820px) {
        .inventory-filter-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); }
        .inventory-filter-grid .inventory-btn { align-self:end; justify-self:start; width:auto; }
        .inventory-filter-grid .inventory-icon-btn { align-self:end; justify-self:start; }
    }
    @container (max-width: 700px) {
        .inventory-page { padding:.85rem .8rem 4rem; }
        .inventory-header-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .inventory-header-actions .inventory-btn { width:100%; }
        .inventory-onboarding { grid-template-columns:auto minmax(0,1fr); padding:.9rem; }
        .inventory-onboarding .btn { grid-column:1/-1; justify-self:stretch; width:100%; }
        .inventory-filter-section { padding:.8rem; }
        .inventory-filter-grid { grid-template-columns:minmax(0,1fr) 2.5rem; gap:.6rem; }
        .inventory-filter-grid label { grid-column:1/-1; }
        .inventory-filter-grid .inventory-btn { grid-column:1; width:100%; }
        .inventory-filter-grid .inventory-icon-btn { grid-column:2; }
        .inventory-product-search { grid-template-columns:minmax(0,1fr) auto; }
        .inventory-section, .inventory-details-body, .inventory-compact-form, .inventory-list-panel { padding:.85rem; }
        .inventory-search-select { min-width:0; }
        .inventory-search-select-menu { left:0; right:auto; width:min(40rem, calc(100vw - 2rem)); }
    }
    @container (max-width: 440px) {
        .inventory-header-actions { grid-template-columns:1fr; }
        .inventory-product-search { grid-template-columns:1fr; }
        .inventory-product-search .inventory-btn { width:100%; }
    }
    .inventory-receipt-import { margin:.85rem 0 1rem; background:#f6f4ff; }.inventory-receipt-import h3,.inventory-variant-stock-editor h3 { margin:0; font-size:.92rem; font-weight:800; }.inventory-receipt-import p,.inventory-variant-stock-editor p { margin:.2rem 0 0; color:#665b84; font-size:.8rem; line-height:1.45; }.inventory-import-receipt-form { display:grid; grid-template-columns:auto minmax(220px,1fr) auto; gap:.65rem; align-items:end; }.inventory-import-receipt-form label { display:grid; gap:.28rem; min-width:0; color:#53627d; font-size:.72rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-import-receipt-form .form-control { min-height:2.42rem; font-size:.88rem; letter-spacing:0; text-transform:none; }.inventory-import-hint { color:#68758b; font-size:.75rem; line-height:1.4; }.inventory-receipt-reverse { position:relative; min-width:2.45rem; }.inventory-receipt-reverse > summary { list-style:none; cursor:pointer; }.inventory-receipt-reverse > summary::-webkit-details-marker { display:none; }.inventory-receipt-reverse[open] { min-width:22rem; }.inventory-receipt-reverse[open] > summary { margin-bottom:.45rem; }.inventory-receipt-reverse .inventory-reverse-form { padding:.7rem; background:#fff8f8; border:1px solid #f2c8cc; border-radius:.5rem; grid-template-columns:1fr; }.inventory-receipt-reverse .inventory-confirm-reverse { align-items:flex-start; padding-top:0; color:#7d3440; font-size:.76rem!important; line-height:1.35; }.inventory-muted { color:#94a3b8; }.dark-mode .inventory-receipt-import,.dark-mode .inventory-receipt-reverse .inventory-reverse-form { background:rgba(255,255,255,.035); border-color:#374151; }.dark-mode .inventory-import-hint { color:#aeb9cc; }.dark-mode .inventory-receipt-import p,.dark-mode .inventory-variant-stock-editor p { color:#d8cff7; }
    @container (max-width: 760px) { .inventory-import-receipt-form { grid-template-columns:1fr; }.inventory-import-receipt-form .inventory-btn { width:100%; }.inventory-receipt-reverse[open] { min-width:min(22rem, calc(100vw - 3rem)); } }
    .inventory-product-management { display:grid; gap:.85rem; margin-top:1rem; padding:1rem; background:#f5f3ff; border:1px solid #ded5ff; border-radius:.6rem; }.inventory-product-management .inventory-product-general { margin:0; padding:0; background:transparent; border:0; }.inventory-product-management .inventory-variant-editor { margin:0; padding:1rem 0 0; background:transparent; border:0; border-top:1px solid #ded5ff; border-radius:0; }.inventory-variant-editor { display:grid; gap:.85rem; }.inventory-variant-editor-heading { display:grid; grid-template-columns:minmax(0,1fr) minmax(230px,340px); gap:1rem; align-items:end; }.inventory-variant-editor h3 { margin:0; color:#2e1a68; font-size:1rem; font-weight:800; }.inventory-variant-editor p { margin:.22rem 0 0; color:#665b84; font-size:.81rem; line-height:1.45; }.inventory-variant-editor-heading > label { display:grid; gap:.28rem; color:#53627d; font-size:.72rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-variant-editor-rows { display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:.7rem; }.inventory-variant-card { display:grid; gap:.72rem; padding:.85rem; background:#fff; border:1px solid #ded5ff; border-radius:.55rem; box-shadow:0 .18rem .65rem rgba(53,36,112,.06); }.inventory-variant-card.is-inactive { opacity:.72; }.inventory-variant-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:.65rem; }.inventory-variant-card-header strong { display:block; color:#21114f; font-size:.95rem; }.inventory-variant-card-header small { display:block; max-width:24ch; margin-top:.12rem; color:#718096; font-size:.7rem; overflow-wrap:anywhere; }.inventory-variant-card-stock { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; padding:.65rem; background:#f8fafc; border-radius:.45rem; }.inventory-variant-card-stock span { display:block; color:#748198; font-size:.68rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-variant-card-stock strong { display:block; margin-top:.12rem; color:#17213a; font-size:1.05rem; }.inventory-variant-card label { display:grid; gap:.28rem; color:#53627d; font-size:.72rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }.inventory-variant-card .inventory-btn { width:100%; }.dark-mode .inventory-product-management { background:rgba(135,87,236,.12); border-color:rgba(167,139,250,.25); }.dark-mode .inventory-product-management .inventory-variant-editor { border-color:#4c3e77; }.dark-mode .inventory-variant-card { background:#141b2b; border-color:#4c3e77; box-shadow:none; }.dark-mode .inventory-variant-card-header strong,.dark-mode .inventory-variant-card-stock strong { color:#f3f5fb; }.dark-mode .inventory-variant-card-stock { background:#111827; }.dark-mode .inventory-variant-editor h3 { color:#e7ddff; }.dark-mode .inventory-variant-editor p { color:#d8cff7; }
    @container (max-width: 700px) { .inventory-variant-editor-heading { grid-template-columns:1fr; }.inventory-variant-editor-rows { grid-template-columns:1fr; } }
    .inventory-movement-receipt-actions { grid-template-columns:minmax(0,1fr) auto; align-items:end; background:#f6f4ff; }.inventory-movement-receipt-actions h3 { margin:0; font-size:.92rem; font-weight:800; }.inventory-movement-receipt-actions p { margin:.22rem 0 0; color:#665b84; font-size:.8rem; line-height:1.45; }.inventory-movement-receipt-actions .inventory-import-actions { justify-content:flex-end; }.inventory-receipt-reverse-trigger { white-space:nowrap; }.dark-mode .inventory-movement-receipt-actions { background:rgba(135,87,236,.12); }.dark-mode .inventory-movement-receipt-actions p { color:#d8cff7; }
    @container (max-width: 760px) { .inventory-movement-receipt-actions { grid-template-columns:1fr; }.inventory-movement-receipt-actions .inventory-import-actions { justify-content:stretch; }.inventory-movement-receipt-actions .inventory-import-actions .inventory-btn { flex:1; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var lines = document.getElementById('receipt-lines');
    var template = document.getElementById('receipt-line-template');
    var addButton = document.querySelector('.inventory-add-line');
    var nextIndex = 0;
    function addLine() {
        if (!lines || !template) return;
        var fragment = template.content.cloneNode(true);
        fragment.firstElementChild.innerHTML = fragment.firstElementChild.innerHTML.replaceAll('__INDEX__', nextIndex++);
        lines.appendChild(fragment);
        setupSearchSelects(lines.lastElementChild);
    }
    if (addButton) { addButton.addEventListener('click', addLine); }
    document.addEventListener('click', function (event) { if (event.target.closest('.inventory-remove-line')) { event.target.closest('.inventory-line').remove(); } });
    var type = document.getElementById('movement-type');
    var destination = document.querySelector('[data-movement-destination]');
    function toggleDestination() { if (destination && type) { destination.hidden = type.value !== 'TRASLADO'; } }
    if (type) { type.addEventListener('change', toggleDestination); toggleDestination(); }
    function setupEditor(formId, selectId, fields) {
        var form = document.getElementById(formId);
        var select = document.getElementById(selectId);
        if (!form || !select) return;
        function syncEditor() {
            var option = select.options[select.selectedIndex];
            var submit = form.querySelector('[data-editor-submit]');
            if (!option.value) { form.action = ''; if (submit) submit.disabled = true; return; }
            form.action = form.dataset.actionBase + '/' + option.value;
            fields.forEach(function (field) {
                var input = form.querySelector('[name="' + field + '"]');
                if (input) input.value = option.dataset[field] || '';
            });
            var active = form.querySelector('input[name="activo"][type="checkbox"]');
            if (active) active.checked = option.dataset.active === '1';
            if (submit) submit.disabled = false;
        }
        select.addEventListener('change', syncEditor);
        return syncEditor;
    }
    var refreshProductEditor = setupEditor('product-editor', 'product-editor-select', ['nombre', 'tipo', 'categoria', 'subcategoria', 'unidad_medida', 'stock_minimo', 'tallas']);
    setupEditor('location-editor', 'location-editor-select', ['codigo', 'nombre', 'tipo', 'descripcion']);
    setupEditor('provider-editor', 'provider-editor-select', ['nombre', 'rut', 'contacto', 'email', 'telefono', 'observacion']);

    var productSelect = document.getElementById('product-editor-select');
    var productVariantPanel = document.getElementById('product-variant-editor');
    var productVariantRows = document.getElementById('product-variant-editor-rows');
    var productVariantLocation = document.getElementById('product-variant-location');
    var productVariantCopy = document.getElementById('product-variant-editor-copy');

    function inventoryElement(tag, className, text) {
        var element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }

    function inventoryHiddenInput(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    function inventoryLabel(text, input) {
        var label = inventoryElement('label', null, text);
        label.appendChild(input);
        return label;
    }

    function formatInventoryStock(value) {
        return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 3 }).format(Number(value || 0));
    }

    function selectedProductVariants() {
        if (!productSelect || !productSelect.value) return [];
        try { return JSON.parse(productSelect.options[productSelect.selectedIndex].dataset.variants || '[]'); }
        catch (error) { return []; }
    }

    function renderProductVariantCards() {
        if (!productSelect || !productVariantPanel || !productVariantRows) return;
        var option = productSelect.options[productSelect.selectedIndex];
        var variants = selectedProductVariants();
        if (!option || !option.value || !variants.length) {
            productVariantPanel.hidden = true;
            productVariantRows.replaceChildren();
            return;
        }

        var stocks = {};
        try { stocks = JSON.parse(productVariantPanel.dataset.stocks || '{}'); }
        catch (error) { stocks = {}; }
        var locationId = productVariantLocation ? productVariantLocation.value : '';
        var locationStocks = locationId ? (stocks[locationId] || {}) : {};
        productVariantPanel.hidden = false;
        productVariantCopy.textContent = locationId
            ? 'Cada tarjeta corresponde a una talla. El cambio queda registrado como ajuste en la ubicación seleccionada.'
            : 'Selecciona una ubicación para ver el saldo actual y habilitar el ajuste de cada talla.';
        productVariantRows.replaceChildren();

        variants.forEach(function (variant) {
            var stock = Number(locationStocks[String(variant.id)] || 0);
            var card = inventoryElement('form', 'inventory-variant-card' + (variant.activo ? '' : ' is-inactive'));
            var header = inventoryElement('div', 'inventory-variant-card-header');
            var title = inventoryElement('div');
            var titleText = variant.talla === 'ESTANDAR' ? 'Talla estándar' : 'Talla ' + variant.talla;
            title.append(inventoryElement('strong', null, titleText), inventoryElement('small', null, variant.codigo || 'Sin código de variante'));
            header.appendChild(title);
            header.appendChild(inventoryElement('span', 'inventory-status ' + (variant.activo ? 'is-ok' : 'is-empty'), variant.activo ? 'Activa' : 'Inactiva'));

            var stockSummary = inventoryElement('div', 'inventory-variant-card-stock');
            var current = inventoryElement('div');
            current.append(inventoryElement('span', null, 'Saldo actual'), inventoryElement('strong', null, locationId ? formatInventoryStock(stock) : '—'));
            var minimum = inventoryElement('div');
            minimum.append(inventoryElement('span', null, 'Stock mínimo'), inventoryElement('strong', null, formatInventoryStock(variant.stock_minimo)));
            stockSummary.append(current, minimum);

            var finalStock = document.createElement('input');
            finalStock.type = 'number';
            finalStock.name = 'stock_final';
            finalStock.className = 'form-control';
            finalStock.min = '0';
            finalStock.step = '0.001';
            finalStock.inputMode = 'decimal';
            finalStock.required = true;
            finalStock.placeholder = 'Saldo que debe quedar';
            finalStock.value = locationId ? String(stock) : '';
            finalStock.disabled = !locationId || !variant.activo;

            var reason = document.createElement('input');
            reason.type = 'text';
            reason.name = 'observacion';
            reason.className = 'form-control';
            reason.minLength = 5;
            reason.maxLength = 500;
            reason.required = true;
            reason.placeholder = 'Ej.: conteo físico';
            reason.disabled = !locationId || !variant.activo;

            var button = inventoryElement('button', 'btn btn-primary inventory-btn');
            button.type = 'submit';
            button.disabled = !locationId || !variant.activo;
            button.append(inventoryElement('i', 'bi bi-save2'), document.createTextNode('Guardar ' + titleText));

            card.method = 'POST';
            card.action = productVariantPanel.dataset.action;
            card.append(
                inventoryHiddenInput('_token', productVariantPanel.dataset.csrf),
                inventoryHiddenInput('ubicacion_id', locationId),
                inventoryHiddenInput('variante_id', variant.id),
                inventoryHiddenInput('productos_pagina', productVariantPanel.dataset.productPage || '1'),
                inventoryHiddenInput('producto_buscar', productVariantPanel.dataset.productSearch || ''),
                header,
                stockSummary,
                inventoryLabel('Nuevo saldo', finalStock),
                inventoryLabel('Motivo del ajuste', reason),
                button
            );
            productVariantRows.appendChild(card);
        });
    }

    if (productSelect) productSelect.addEventListener('change', renderProductVariantCards);
    if (productVariantLocation) productVariantLocation.addEventListener('change', renderProductVariantCards);
    function syncProductSubcategoryOptions(scope) {
        var category = scope.querySelector('[data-product-category-select]');
        var subcategory = scope.querySelector('[data-product-subcategory-select]');
        if (!category || !subcategory) return;
        Array.prototype.slice.call(subcategory.options, 1).forEach(function (option) {
            var isAvailable = !category.value || option.dataset.category === category.value;
            option.hidden = !isAvailable;
            option.disabled = !isAvailable;
        });
        if (subcategory.selectedOptions[0] && subcategory.selectedOptions[0].disabled) subcategory.value = '';
    }
    document.querySelectorAll('[data-product-category-select]').forEach(function (category) {
        var scope = category.closest('form');
        category.addEventListener('change', function () { syncProductSubcategoryOptions(scope); });
        syncProductSubcategoryOptions(scope);
    });
    if (productSelect) productSelect.addEventListener('change', function () { syncProductSubcategoryOptions(productSelect.closest('form')); });
    if (refreshProductEditor) refreshProductEditor();
    if (productSelect) syncProductSubcategoryOptions(productSelect.closest('form'));
    renderProductVariantCards();

    function normalizedSearch(value) {
        return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    }

    var searchableSelects = [];
    function selectableOptions(nativeSelect) {
        return Array.prototype.slice.call(nativeSelect.options).filter(function (option) {
            return option.value && !option.disabled;
        });
    }

    function shouldUseSearchSelect(nativeSelect) {
        if (nativeSelect.dataset.inventorySearchIgnore === 'true') return false;
        return selectableOptions(nativeSelect).length > 5;
    }

    function setupSearchSelects(container) {
        var root = container || document.querySelector('.inventory-page');
        if (!root) return;

        root.querySelectorAll('select.form-select').forEach(function (nativeSelect) {
            if (shouldUseSearchSelect(nativeSelect)) {
                setupSearchSelect(nativeSelect, searchableSelects.length);
            }
        });
    }

    function closeSearchSelect(component) {
        component.menu.hidden = true;
        component.wrapper.classList.remove('is-open');
        component.trigger.setAttribute('aria-expanded', 'false');
        var card = component.wrapper.closest('.inventory-delivery-card');
        if (card) card.classList.remove('has-open-search-select');
    }

    function closeAllSearchSelects(except) {
        searchableSelects.forEach(function (component) {
            if (component !== except) closeSearchSelect(component);
        });
    }

    function positionSearchSelectMenu(component) {
        if (component.menu.hidden) return;

        var triggerBounds = component.trigger.getBoundingClientRect();
        var viewportPadding = 16;
        var availableWidth = Math.max(1, window.innerWidth - (viewportPadding * 2));
        var menuWidth = Math.min(Math.max(triggerBounds.width, 280), Math.min(640, availableWidth));
        var menuLeft = Math.min(
            Math.max(viewportPadding, triggerBounds.right - menuWidth),
            window.innerWidth - menuWidth - viewportPadding
        );

        component.menu.style.width = menuWidth + 'px';
        component.menu.style.left = menuLeft + 'px';
        component.menu.style.right = 'auto';
        component.menu.style.top = (triggerBounds.bottom + 5) + 'px';

        var menuBounds = component.menu.getBoundingClientRect();
        if (menuBounds.bottom > window.innerHeight - viewportPadding && triggerBounds.top - menuBounds.height - 5 >= viewportPadding) {
            component.menu.style.top = Math.max(viewportPadding, triggerBounds.top - menuBounds.height - 5) + 'px';
        }
    }

    function setupSearchSelect(nativeSelect, index) {
        if (nativeSelect.dataset.inventorySearchReady === 'true') return;
        nativeSelect.dataset.inventorySearchReady = 'true';

        var originalRequired = nativeSelect.required;
        var wrapper = document.createElement('div');
        var trigger = document.createElement('button');
        var triggerLabel = document.createElement('span');
        var menu = document.createElement('div');
        var search = document.createElement('input');
        var results = document.createElement('div');
        var help = document.createElement('small');
        var component;

        wrapper.className = 'inventory-search-select';
        nativeSelect.parentNode.insertBefore(wrapper, nativeSelect);
        wrapper.appendChild(nativeSelect);
        nativeSelect.classList.add('inventory-search-select-native');
        nativeSelect.required = false;
        nativeSelect.tabIndex = -1;

        trigger.type = 'button';
        trigger.className = 'inventory-search-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        var searchPlaceholder = nativeSelect.dataset.searchPlaceholder || 'Buscar una opcion';
        var searchLabel = nativeSelect.dataset.searchLabel || 'opcion';
        trigger.setAttribute('aria-label', 'Buscar ' + searchLabel);
        trigger.appendChild(triggerLabel);
        var triggerIcon = document.createElement('i');
        triggerIcon.className = 'bi bi-search';
        trigger.setAttribute('title', 'Buscar ' + searchLabel);
        trigger.appendChild(triggerIcon);

        menu.className = 'inventory-search-select-menu';
        menu.hidden = true;
        menu.id = 'inventory-search-select-' + index;
        trigger.setAttribute('aria-controls', menu.id);
        search.type = 'search';
        search.className = 'inventory-search-select-search';
        search.autocomplete = 'off';
        search.placeholder = searchPlaceholder;
        search.setAttribute('aria-label', search.placeholder);
        results.className = 'inventory-search-select-results';
        results.setAttribute('role', 'listbox');
        help.className = 'inventory-search-select-help';
        help.textContent = nativeSelect.dataset.searchHelp || 'Escribe para filtrar las opciones.';
        menu.append(search, results, help);
        wrapper.appendChild(trigger);
        menu.classList.add('is-portal');
        document.body.appendChild(menu);

        component = { nativeSelect: nativeSelect, wrapper: wrapper, trigger: trigger, triggerLabel: triggerLabel, menu: menu, search: search, results: results, originalRequired: originalRequired };
        searchableSelects.push(component);

        function selectedOption() {
            return nativeSelect.options[nativeSelect.selectedIndex] || nativeSelect.options[0];
        }

        function syncSelectedOption() {
            var option = selectedOption();
            triggerLabel.textContent = option ? option.textContent.trim() : 'Selecciona una opcion';
            triggerLabel.title = triggerLabel.textContent;
            wrapper.classList.toggle('is-invalid', originalRequired && !nativeSelect.value);
        }

        function selectOption(option) {
            nativeSelect.value = option.value;
            nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            closeSearchSelect(component);
            trigger.focus();
        }

        function renderResults() {
            var query = normalizedSearch(search.value);
            var matches = selectableOptions(nativeSelect).filter(function (option) {
                return !query || normalizedSearch(option.textContent).indexOf(query) !== -1;
            }).slice(0, 60);
            results.replaceChildren();

            if (!matches.length) {
                var empty = document.createElement('div');
                empty.className = 'inventory-search-select-empty';
                empty.textContent = 'No hay opciones que coincidan.';
                results.appendChild(empty);
                return;
            }

            matches.forEach(function (option) {
                var result = document.createElement('button');
                var text = document.createElement('span');
                result.type = 'button';
                result.className = 'inventory-search-select-option' + (option.selected ? ' is-selected' : '');
                result.setAttribute('role', 'option');
                result.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                text.textContent = option.textContent.trim();
                result.appendChild(text);
                result.addEventListener('click', function () { selectOption(option); });
                results.appendChild(result);
            });
        }

        function openSearchSelect() {
            closeAllSearchSelects(component);
            wrapper.classList.add('is-open');
            var card = wrapper.closest('.inventory-delivery-card');
            if (card) card.classList.add('has-open-search-select');
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            renderResults();
            positionSearchSelectMenu(component);
            window.requestAnimationFrame(function () { search.focus(); });
        }

        trigger.addEventListener('click', function () {
            if (menu.hidden) openSearchSelect(); else closeSearchSelect(component);
        });
        trigger.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openSearchSelect();
            }
        });
        search.addEventListener('input', renderResults);
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSearchSelect(component);
                trigger.focus();
            }
            if (event.key === 'Enter') {
                var firstResult = results.querySelector('.inventory-search-select-option');
                if (firstResult) {
                    event.preventDefault();
                    firstResult.click();
                }
            }
        });
        nativeSelect.addEventListener('change', function () {
            syncSelectedOption();
            trigger.setAttribute('aria-expanded', 'false');
        });
        var form = nativeSelect.closest('form');
        if (form) {
            form.addEventListener('submit', function (event) {
                if (originalRequired && !nativeSelect.value) {
                    event.preventDefault();
                    wrapper.classList.add('is-invalid');
                    openSearchSelect();
                }
            });
        }
        syncSelectedOption();
    }

    setupSearchSelects();
    if (addButton) addLine();
    window.addEventListener('resize', function () {
        searchableSelects.forEach(positionSearchSelectMenu);
    });
    window.addEventListener('scroll', function () {
        searchableSelects.forEach(positionSearchSelectMenu);
    }, true);
    document.addEventListener('click', function (event) {
        searchableSelects.forEach(function (component) {
            if (!component.wrapper.contains(event.target) && !component.menu.contains(event.target)) closeSearchSelect(component);
        });
    });
});
</script>
@endsection
