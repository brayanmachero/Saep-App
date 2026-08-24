@php
    $scope = $location?->nombre ?? 'Todas las ubicaciones';
    $totalStock = (float) $variants->sum(fn ($variant) => (float) $variant->stock_actual);
    $activeVariants = $variants->where('activo', true)->count();
    $selectedMinimum = (float) ($selectedVariant->stock_minimo ?? $product->stock_minimo);
    $selectedStock = (float) $selectedVariant->stock_actual;
    $selectedCriticalThreshold = $selectedMinimum > 0 ? min(3, max(1, (int) floor($selectedMinimum / 4))) : 0;
    $selectedOutOfStock = $selectedStock <= 0;
    $selectedCriticalStock = ! $selectedOutOfStock && $selectedMinimum > 0 && $selectedStock <= $selectedCriticalThreshold;
    $selectedNeedsReplenishment = ! $selectedOutOfStock && ! $selectedCriticalStock && $selectedMinimum > 0 && $selectedStock <= $selectedMinimum;
@endphp

<div class="inventory-detail-heading">
    <div>
        <span class="inventory-detail-kicker">Detalle de stock</span>
        <h2>{{ $product->nombre }}</h2>
        <p>{{ $product->codigo }} · {{ $scope }}</p>
    </div>
    <span class="inventory-status {{ $product->activo ? 'is-ok' : 'is-empty' }}">{{ $product->activo ? 'Activo' : 'Inactivo' }}</span>
</div>

<div class="inventory-detail-grid">
    <div><span>Tipo</span><strong>{{ $product->tipo ?: 'No informado' }}</strong></div>
    <div><span>Categoría</span><strong>{{ $product->categoria ?: 'No informada' }}</strong></div>
    <div><span>Subcategoría</span><strong>{{ $product->subcategoria ?: 'No informada' }}</strong></div>
    <div><span>Unidad</span><strong>{{ $product->unidad_medida ?: 'Unidad' }}</strong></div>
    <div><span>Stock total</span><strong>{{ rtrim(rtrim(number_format($totalStock, 3, ',', '.'), '0'), ',') }}</strong></div>
    <div><span>Variantes activas</span><strong>{{ $activeVariants }} de {{ $variants->count() }}</strong></div>
</div>

<section class="inventory-detail-lines">
    <div class="inventory-detail-section-title">
        <div><h3>Variantes y saldo</h3><small>{{ $scope }}</small></div>
        <strong>{{ $variants->count() }} variante(s)</strong>
    </div>
    <div class="inventory-detail-table-wrap">
        <table class="inventory-detail-table inventory-product-stock-table">
            <thead><tr><th>Talla</th><th class="text-end">Mínimo</th><th class="text-end">Stock</th><th>Estado</th></tr></thead>
            <tbody>
            @foreach($variants as $productVariant)
                @php
                    $minimum = (float) ($productVariant->stock_minimo ?? $product->stock_minimo);
                    $actual = (float) $productVariant->stock_actual;
                    $criticalThreshold = $minimum > 0 ? min(3, max(1, (int) floor($minimum / 4))) : 0;
                    $isOutOfStock = $actual <= 0;
                    $isCriticalStock = ! $isOutOfStock && $minimum > 0 && $actual <= $criticalThreshold;
                    $needsReplenishment = ! $isOutOfStock && ! $isCriticalStock && $minimum > 0 && $actual <= $minimum;
                    $status = ! $productVariant->activo ? 'Inactiva' : ($isOutOfStock ? 'Sin stock' : ($isCriticalStock ? 'Crítico' : ($needsReplenishment ? 'Reponer' : 'Disponible')));
                    $statusClass = ! $productVariant->activo ? 'is-empty' : ($isOutOfStock ? 'is-out-of-stock' : ($isCriticalStock ? 'is-stock-critical' : ($needsReplenishment ? 'is-critical' : 'is-ok')));
                @endphp
                <tr class="{{ $productVariant->is($selectedVariant) ? 'is-selected' : '' }}">
                    <td><strong>{{ $productVariant->talla ?: 'ESTANDAR' }}</strong>@if($productVariant->descripcion)<small>{{ $productVariant->descripcion }}</small>@endif</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($minimum, 3, ',', '.'), '0'), ',') }}</td>
                    <td class="text-end"><strong>{{ rtrim(rtrim(number_format($actual, 3, ',', '.'), '0'), ',') }}</strong></td>
                    <td><span class="inventory-status {{ $statusClass }}">{{ $status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($selectedOutOfStock || $selectedCriticalStock || $selectedNeedsReplenishment)
        <div class="inventory-detail-warning"><i class="bi bi-exclamation-triangle"></i><div><strong>{{ $selectedOutOfStock ? 'La variante seleccionada no tiene stock.' : ($selectedCriticalStock ? 'La variante seleccionada tiene stock crítico.' : 'La variante seleccionada requiere reposición.') }}</strong><span>Su saldo es {{ rtrim(rtrim(number_format($selectedStock, 3, ',', '.'), '0'), ',') }} y el mínimo definido es {{ rtrim(rtrim(number_format($selectedMinimum, 3, ',', '.'), '0'), ',') }}.</span></div></div>
    @endif
</section>

<section class="inventory-detail-lines">
    <div class="inventory-detail-section-title">
        <div><h3>Movimientos recientes</h3><small>Últimos {{ $movements->count() }} movimiento(s) en {{ $scope }}</small></div>
        <strong>Trazabilidad</strong>
    </div>
    <div class="inventory-detail-table-wrap">
        <table class="inventory-detail-table">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Origen</th><th>Usuario</th><th class="text-end">Cantidad</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                @php
                    $isReversed = $movement->reversos_count > 0;
                    $user = $movement->registrado_por_nombre ?: ($movement->registradoPor?->nombre_completo ?? $movement->registradoPor?->name ?? 'No disponible');
                    $isKizeo = in_array($movement->origen, ['KIZEO_EPP', 'REVERSO_KIZEO_EPP'], true);
                    $source = match ($movement->origen) {
                        'KIZEO_EPP' => 'Kizeo',
                        'REVERSO_KIZEO_EPP' => 'Reverso Kizeo',
                        'IMPORTACION_CATALOGO' => 'Importación de stock',
                        'INGRESO_BODEGA' => 'Ingreso de bodega',
                        'CONTEO_FISICO' => 'Conteo físico',
                        'AJUSTE_STOCK_TALLA' => 'Ajuste de stock',
                        'MANUAL' => 'Movimiento manual',
                        default => str_replace('_', ' ', strtolower((string) $movement->origen)),
                    };
                @endphp
                <tr>
                    <td>{{ optional($movement->ocurrido_en)->format('d/m/Y H:i') }}<small>{{ $movement->ubicacion?->nombre ?: 'Sin ubicación' }} · {{ $movement->variante?->talla ?: 'ESTANDAR' }}</small></td>
                    <td><span class="inventory-status {{ $isReversed ? 'is-empty' : ($movement->tipo === 'REVERSO' ? 'is-review' : 'is-ok') }}">{{ $isReversed ? 'Anulado' : (\App\Models\InventarioMovimiento::TIPOS[$movement->tipo] ?? str_replace('_', ' ', $movement->tipo)) }}</span></td>
                    <td><span class="inventory-status {{ $isKizeo ? 'is-review' : 'is-empty' }}">{{ $source }}</span>@if($isKizeo && $movement->documento_numero)<small>{{ $movement->documento_numero }}</small>@endif</td>
                    <td>{{ $user }}</td>
                    <td class="text-end {{ $movement->cantidad < 0 ? 'text-danger' : 'text-success' }}"><strong>{{ $movement->cantidad > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->cantidad, 3, ',', '.'), '0'), ',') }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="inventory-empty">No hay movimientos de este producto para la ubicación seleccionada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
