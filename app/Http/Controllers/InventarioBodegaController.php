<?php

namespace App\Http\Controllers;

use App\Models\EntregaBodega;
use App\Models\Configuracion;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioConteo;
use App\Models\InventarioCoordinador;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioIngreso;
use App\Models\InventarioMovimiento;
use App\Models\InventarioProducto;
use App\Models\InventarioProveedor;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Modules\Comercial\Models\CentroCosto;
use App\Services\EntregaBodegaSyncService;
use App\Services\InventarioKizeoCatalogSyncService;
use App\Services\InventarioOperationalMasterService;
use App\Services\InventarioStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventarioBodegaController extends Controller
{
    private readonly InventarioOperationalMasterService $operationalMasters;

    private readonly InventarioKizeoCatalogSyncService $catalogSync;

    public function __construct(
        private readonly InventarioStockService $stock,
        ?InventarioOperationalMasterService $operationalMasters = null,
        ?InventarioKizeoCatalogSyncService $catalogSync = null,
    ) {
        $this->operationalMasters = $operationalMasters ?? app(InventarioOperationalMasterService::class);
        $this->catalogSync = $catalogSync ?? app(InventarioKizeoCatalogSyncService::class);
    }

    public function index(Request $request)
    {
        $view = in_array($request->input('vista'), ['resumen', 'ingresos', 'movimientos', 'conteos', 'kizeo', 'catalogo', 'maestros'], true)
            ? $request->input('vista')
            : 'resumen';
        $selectedLocation = $request->integer('ubicacion_id') ?: null;
        $summaryFilters = $this->summaryFilters($request);
        $receiptHistoryFilters = $this->receiptHistoryFilters($request);
        $movementHistoryFilters = $this->movementHistoryFilters($request);
        $search = $summaryFilters['search'];
        $balances = $this->filterSummaryBalances($this->stock->balances($selectedLocation), $summaryFilters);
        $summaryPeriod = $view === 'resumen'
            ? $this->summaryOperationalPeriod($request)
            : null;
        $operationalStart = $this->inventoryReportingStart();

        $critical = $balances->filter(function (InventarioVariante $variant) {
            $minimum = $variant->stock_minimo ?? $variant->producto->stock_minimo;

            return (float) $minimum > 0 && (float) $variant->stock_actual <= (float) $minimum;
        })->values();

        $movementQuery = InventarioMovimiento::query()
            ->with(['producto', 'variante', 'ubicacion', 'centroCosto', 'coordinador'])
            ->withCount('reversos');

        $movements = $view === 'movimientos'
            ? $this->applyMovementHistoryFilters($movementQuery, $movementHistoryFilters)
                ->latest('ocurrido_en')
                ->latest('id')
                ->paginate(25, ['*'], 'movimientos_pagina')
                ->withQueryString()
            : $movementQuery
                ->when($selectedLocation, fn ($query) => $query->where('ubicacion_id', $selectedLocation))
                ->when($view === 'resumen' && $summaryFilters['applied'], fn ($query) => $query->whereIn('variante_id', $balances->pluck('id')))
                ->when($summaryPeriod, fn (Builder $query) => $this->applySummaryOperationalPeriod($query, $summaryPeriod))
                ->latest('ocurrido_en')
                ->limit(20)
                ->get();

        $summaryAnalytics = $view === 'resumen'
            ? $this->summaryAnalytics($selectedLocation, $summaryFilters, $balances, $summaryPeriod)
            : null;

        $productSearch = trim((string) $request->input('producto_buscar'));
        $productStatus = in_array($request->input('producto_estado'), ['activos', 'inactivos'], true)
            ? $request->input('producto_estado')
            : '';
        $editingProductId = $request->integer('producto_editar') ?: null;
        $adjustingVariantId = $request->integer('variante_ajustar') ?: null;
        $products = InventarioProducto::query()
            ->with('variantes')
            ->when($productSearch !== '', fn ($query) => $query->where(function ($products) use ($productSearch) {
                $products->where('nombre', 'like', '%'.$productSearch.'%')
                    ->orWhere('codigo', 'like', '%'.$productSearch.'%')
                    ->orWhere('categoria', 'like', '%'.$productSearch.'%');
            }))
            ->when($productStatus !== '', fn ($query) => $query->where('activo', $productStatus === 'activos'))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(30, ['*'], 'productos_pagina')
            ->withQueryString();

        // El listado visible puede estar filtrado o paginado, pero el editor debe
        // permitir recuperar cualquier producto histórico para reactivarlo.
        $productEditorOptions = InventarioProducto::query()
            ->with('variantes')
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        $locations = InventarioUbicacion::query()->orderByDesc('activo')->orderBy('nombre')->get();
        $activeLocations = $locations->where('activo', true)->values();
        $variantOptions = InventarioVariante::query()
            ->with('producto')
            ->where('activo', true)
            ->whereHas('producto', fn ($query) => $query->where('activo', true))
            ->orderBy('talla')
            ->get();
        $variantStocksByLocation = InventarioMovimiento::query()
            ->selectRaw('ubicacion_id, variante_id, SUM(cantidad) as stock_actual')
            ->whereIn('ubicacion_id', $activeLocations->pluck('id'))
            ->groupBy('ubicacion_id', 'variante_id')
            ->get()
            ->groupBy('ubicacion_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [(string) $row->variante_id => (float) $row->stock_actual]));
        $catalogValues = InventarioProducto::query()
            ->get(['tipo', 'categoria', 'subcategoria', 'unidad_medida']);
        $productTypes = $catalogValues->pluck('tipo')->filter()->unique()->sort()->values();
        $productCategories = $catalogValues->pluck('categoria')->filter()->unique()->sort()->values();
        $productSubcategories = $catalogValues
            ->filter(fn (InventarioProducto $product) => filled($product->subcategoria))
            ->map(fn (InventarioProducto $product) => [
                'categoria' => (string) $product->categoria,
                'nombre' => (string) $product->subcategoria,
            ])
            ->unique(fn (array $subcategory) => Str::lower($subcategory['categoria'].'|'.$subcategory['nombre']))
            ->sortBy(fn (array $subcategory) => Str::lower($subcategory['categoria'].'|'.$subcategory['nombre']))
            ->values();
        $productUnits = $catalogValues->pluck('unidad_medida')->filter()->prepend('Unidad')->unique()->sort()->values();
        $inventoryCostCenters = Schema::hasTable('inventario_centros_costo')
            ? InventarioCentroCosto::query()->with('coordinador')->where('activo', true)->orderBy('nombre')->get()
            : collect();
        $coordinators = Schema::hasTable('inventario_coordinadores')
            ? InventarioCoordinador::query()->where('activo', true)->orderBy('nombre')->get()
            : collect();
        $legacyCostCenters = $inventoryCostCenters->isEmpty() && Schema::hasTable('comercial_centros_costo')
            ? CentroCosto::query()->activos()->whereNotNull('codigo')->where('codigo', '!=', '')->orderBy('nombre')->get(['id', 'codigo', 'nombre'])
            : collect();
        $masterSearch = trim((string) $request->input('maestro_buscar'));
        $masterStatus = in_array($request->input('maestro_estado'), ['activos', 'inactivos'], true)
            ? $request->input('maestro_estado')
            : '';
        $masterCenters = null;
        $masterCoordinators = null;
        $masterCoordinatorOptions = collect();
        $masterEditorKind = null;
        $masterEditRecord = null;
        $masterCreateKind = null;

        if ($view === 'maestros' && Schema::hasTable('inventario_centros_costo') && Schema::hasTable('inventario_coordinadores')) {
            $masterCenters = InventarioCentroCosto::query()
                ->with('coordinador')
                ->when($masterSearch !== '', function ($query) use ($masterSearch) {
                    $query->where(function ($centers) use ($masterSearch) {
                        $centers->where('nombre', 'like', '%'.$masterSearch.'%')
                            ->orWhere('numero_maestro', 'like', '%'.$masterSearch.'%')
                            ->orWhere('comuna', 'like', '%'.$masterSearch.'%')
                            ->orWhereHas('coordinador', fn ($coordinators) => $coordinators->where('nombre', 'like', '%'.$masterSearch.'%'));
                    });
                })
                ->when($masterStatus !== '', fn ($query) => $query->where('activo', $masterStatus === 'activos'))
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->paginate(20, ['*'], 'centros_pagina')
                ->withQueryString();
            $masterCoordinators = InventarioCoordinador::query()
                ->withCount('centrosCosto')
                ->when($masterSearch !== '', function ($query) use ($masterSearch) {
                    $query->where(function ($coordinators) use ($masterSearch) {
                        $coordinators->where('nombre', 'like', '%'.$masterSearch.'%')
                            ->orWhere('rut', 'like', '%'.$masterSearch.'%')
                            ->orWhere('correo', 'like', '%'.$masterSearch.'%')
                            ->orWhere('cargo', 'like', '%'.$masterSearch.'%');
                    });
                })
                ->when($masterStatus !== '', fn ($query) => $query->where('activo', $masterStatus === 'activos'))
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->paginate(20, ['*'], 'coordinadores_pagina')
                ->withQueryString();
            $masterCoordinatorOptions = InventarioCoordinador::query()
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get();

            $editorKind = $request->input('maestro_editar');
            if ($editorKind === 'centro' && $request->integer('maestro_id')) {
                $masterEditRecord = InventarioCentroCosto::query()->find($request->integer('maestro_id'));
                $masterEditorKind = $masterEditRecord ? 'centro' : null;
            } elseif ($editorKind === 'coordinador' && $request->integer('maestro_id')) {
                $masterEditRecord = InventarioCoordinador::query()->find($request->integer('maestro_id'));
                $masterEditorKind = $masterEditRecord ? 'coordinador' : null;
            } elseif (in_array($request->input('maestro_nuevo'), ['centro', 'coordinador'], true)) {
                $masterCreateKind = $request->input('maestro_nuevo');
                $masterEditorKind = $masterCreateKind;
            }
        }

        $kizeoQueue = in_array($request->input('kizeo_origen'), ['vigentes', 'historico'], true)
            ? $request->input('kizeo_origen')
            : 'vigentes';
        $kizeoPeriod = $this->kizeoDeliveryPeriod($request);
        $kizeoArticle = trim((string) $request->input('kizeo_articulo', ''));
        $kizeoChangeFilter = in_array($request->input('kizeo_cambios'), ['todos', 'cambiados', 'corregidos'], true)
            ? $request->input('kizeo_cambios')
            : 'todos';
        $kizeoPeriodQuery = $view === 'kizeo'
            ? $this->applyKizeoDeliveryPeriod(EntregaBodega::query(), $kizeoPeriod)
            : null;
        if ($kizeoPeriodQuery && $kizeoArticle !== '') {
            $kizeoPeriodQuery = $this->applyKizeoDeliveryArticleFilter($kizeoPeriodQuery, $kizeoArticle);
        }
        $kizeoApplications = collect();
        $applicationReviewIds = collect();
        $applicationCorrectedIds = collect();
        if ($view === 'kizeo') {
            $kizeoApplications = InventarioEntregaKizeoAplicacion::query()
                ->with('entrega')
                ->get();
            $applicationReviewIds = $kizeoApplications
                ->filter(function (InventarioEntregaKizeoAplicacion $application) {
                    if ($application->estado === 'REVERSADA' || ! $application->entrega) {
                        return false;
                    }

                    $acknowledgedAt = $application->fuente_corregida_en ?: $application->fuente_actualizada_en;

                    return filled($application->correccion_pendiente_motivo)
                        || ($application->entrega->kizeo_updated_at
                            && (! $acknowledgedAt || $application->entrega->kizeo_updated_at->gt($acknowledgedAt)));
                })
                ->pluck('entrega_bodega_id');
            $applicationCorrectedIds = $kizeoApplications
                ->where('estado', 'CORREGIDA')
                ->pluck('entrega_bodega_id');
        }
        $kizeoQueueCounts = ['vigentes' => 0, 'historico' => 0];
        if ($kizeoPeriodQuery) {
            $kizeoQueueCounts['vigentes'] = $this->currentKizeoDeliveryForms(clone $kizeoPeriodQuery)->count();
            $kizeoQueueCounts['historico'] = (clone $kizeoPeriodQuery)
                ->where('kizeo_form_id', EntregaBodegaSyncService::LEGACY_FORM_ID)
                ->count();
        }
        $kizeoDeliveries = $kizeoPeriodQuery
            ? (clone $kizeoPeriodQuery)
                ->with([
                    'items',
                    'inventarioAplicacion.ubicacion',
                    'inventarioAplicacion.lineas.variante.producto',
                ])
                ->when(
                    $kizeoQueue === 'historico',
                    fn ($query) => $query->where('kizeo_form_id', EntregaBodegaSyncService::LEGACY_FORM_ID),
                    fn ($query) => $this->currentKizeoDeliveryForms($query),
                )
                ->when($kizeoChangeFilter === 'cambiados', function (Builder $query) use ($applicationReviewIds) {
                    $query->where(function (Builder $query) use ($applicationReviewIds) {
                        $query->whereIn('entregas_bodega.id', $applicationReviewIds)
                            ->orWhereIn('estado_fuente', ['REQUIERE_REVISION', 'ELIMINADA_EN_KIZEO', 'INCOMPLETA']);
                    });
                })
                ->when($kizeoChangeFilter === 'corregidos', fn (Builder $query) => $query->whereIn('entregas_bodega.id', $applicationCorrectedIds))
                ->orderByDesc('fecha_pedido')
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'kizeo_pagina')
                ->withQueryString()
            : collect();
        $kizeoSuggestions = [];
        foreach ($kizeoDeliveries as $delivery) {
            $kizeoSuggestions[$delivery->id] = $this->stock->suggestedKizeoVariants($delivery, $variantOptions);
        }
        $centralKizeoLocation = $activeLocations->firstWhere('codigo', InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE);
        $kizeoCentralStockByVariant = $centralKizeoLocation
            ? $variantStocksByLocation
                ->get($centralKizeoLocation->id, collect())
                ->map(fn ($stock) => (float) $stock)
                ->all()
            : [];
        $kizeoBatchEligibleIds = [];
        if ($centralKizeoLocation) {
            foreach ($kizeoDeliveries as $delivery) {
                if ($delivery->inventarioAplicacion
                    || $delivery->flujo_inventario !== 'SALIDA'
                    || ($delivery->estado_fuente ?: 'ACTIVA') !== 'ACTIVA'
                    || EntregaBodegaSyncService::isHistoricalStockForm($delivery->kizeo_form_id)) {
                    continue;
                }

                try {
                    $this->stock->suggestedKizeoLineMappings($delivery, $variantOptions);
                    $kizeoBatchEligibleIds[] = $delivery->id;
                } catch (ValidationException) {
                    // Las relaciones no inequívocas se corrigen en la aplicación individual.
                }
            }
        }
        $kizeoStats = ['pending' => 0, 'historical' => 0, 'returns' => 0, 'applied' => 0, 'review' => 0];
        $kizeoDeliveredArticles = collect();
        $kizeoLastSyncedAt = null;
        if ($view === 'kizeo') {
            $kizeoStats = [
                'pending' => $this->currentKizeoDeliveryForms(clone $kizeoPeriodQuery)
                    ->where('flujo_inventario', 'SALIDA')
                    ->where('estado_fuente', 'ACTIVA')
                    ->whereDoesntHave('inventarioAplicacion')
                    ->count(),
                'historical' => (clone $kizeoPeriodQuery)
                    ->where('kizeo_form_id', EntregaBodegaSyncService::LEGACY_FORM_ID)
                    ->count(),
                'returns' => $this->currentKizeoDeliveryForms(clone $kizeoPeriodQuery)
                    ->where('flujo_inventario', 'ENTRADA')
                    ->where('estado_fuente', 'ACTIVA')
                    ->whereDoesntHave('inventarioAplicacion')
                    ->count(),
                'applied' => $this->currentKizeoDeliveryForms(clone $kizeoPeriodQuery)
                    ->whereHas('inventarioAplicacion', fn (Builder $query) => $query->whereIn('estado', ['APLICADA', 'CORREGIDA']))
                    ->count(),
                'review' => $this->currentKizeoDeliveryForms(clone $kizeoPeriodQuery)
                    ->where(function (Builder $query) use ($applicationReviewIds) {
                        $query->whereIn('estado_fuente', ['REQUIERE_REVISION', 'ELIMINADA_EN_KIZEO', 'INCOMPLETA'])
                            ->orWhereIn('id', $applicationReviewIds);
                    })
                    ->count(),
            ];
            $kizeoAppliedDeliveryIds = $this->currentKizeoDeliveryForms(clone $kizeoPeriodQuery)
                ->where('flujo_inventario', 'SALIDA')
                ->whereHas('inventarioAplicacion', fn (Builder $query) => $query->whereIn('estado', ['APLICADA', 'CORREGIDA']))
                ->select('entregas_bodega.id');
            $kizeoDeliveredArticles = DB::table('inventario_entrega_kizeo_lineas as lineas')
                ->join('inventario_entrega_kizeo_aplicaciones as aplicaciones', 'aplicaciones.id', '=', 'lineas.aplicacion_id')
                ->join('inventario_variantes as variantes', 'variantes.id', '=', 'lineas.variante_id')
                ->join('inventario_productos as productos', 'productos.id', '=', 'lineas.producto_id')
                ->whereIn('aplicaciones.estado', ['APLICADA', 'CORREGIDA'])
                ->whereIn('aplicaciones.entrega_bodega_id', $kizeoAppliedDeliveryIds->toBase())
                ->select('lineas.variante_id', 'productos.codigo as producto_codigo', 'productos.nombre as producto_nombre', 'variantes.talla')
                ->selectRaw('SUM(lineas.cantidad_fuente) as cantidad')
                ->selectRaw('COUNT(DISTINCT aplicaciones.entrega_bodega_id) as entregas')
                ->groupBy('lineas.variante_id', 'productos.codigo', 'productos.nombre', 'variantes.talla')
                ->orderByDesc('cantidad')
                ->orderBy('productos.nombre')
                ->get();
            $lastSync = EntregaBodega::query()->max('synced_at');
            $kizeoLastSyncedAt = $lastSync ? Carbon::parse($lastSync) : null;
        }

        return view('inventario_bodega.index', [
            'vista' => $view,
            'selectedLocation' => $selectedLocation,
            'search' => $search,
            'summaryFilters' => $summaryFilters,
            'summaryPeriod' => $summaryPeriod,
            'productSearch' => $productSearch,
            'productStatus' => $productStatus,
            'editingProductId' => $editingProductId,
            'adjustingVariantId' => $adjustingVariantId,
            'locations' => $locations,
            'activeLocations' => $activeLocations,
            'providers' => InventarioProveedor::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            'summaryProviders' => InventarioProveedor::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'variantOptions' => $variantOptions,
            'variantStocksByLocation' => $variantStocksByLocation,
            'productTypes' => $productTypes,
            'productCategories' => $productCategories,
            'productSubcategories' => $productSubcategories,
            'productUnits' => $productUnits,
            'inventoryCostCenters' => $inventoryCostCenters,
            'coordinators' => $coordinators,
            'legacyCostCenters' => $legacyCostCenters,
            'masterSearch' => $masterSearch,
            'masterStatus' => $masterStatus,
            'masterCenters' => $masterCenters,
            'masterCoordinators' => $masterCoordinators,
            'masterCoordinatorOptions' => $masterCoordinatorOptions,
            'masterEditorKind' => $masterEditorKind,
            'masterEditRecord' => $masterEditRecord,
            'masterCreateKind' => $masterCreateKind,
            'balances' => $balances,
            'critical' => $critical,
            'movements' => $movements,
            'summaryAnalytics' => $summaryAnalytics,
            'products' => $products,
            'productEditorOptions' => $productEditorOptions,
            'ingresos' => $view === 'ingresos'
                ? $this->applyReceiptHistoryFilters(
                    InventarioIngreso::query()
                        ->with(['ubicacion', 'proveedor', 'items.producto', 'items.variante', 'reversadoPor', 'registradoPor']),
                    $receiptHistoryFilters,
                )
                    ->latest('fecha_recepcion')
                    ->latest('id')
                    ->paginate(25, ['*'], 'ingresos_pagina')
                    ->withQueryString()
                : collect(),
            'receiptHistoryFilters' => $receiptHistoryFilters,
            'movementHistoryFilters' => $movementHistoryFilters,
            'conteos' => InventarioConteo::query()
                ->with('ubicacion')
                ->withCount('lineas')
                ->whereDate('fecha_corte', '>=', $operationalStart)
                ->latest('fecha_corte')
                ->latest('id')
                ->limit(15)
                ->get(),
            'kizeoDeliveries' => $kizeoDeliveries,
            'kizeoSuggestions' => $kizeoSuggestions,
            'kizeoStats' => $kizeoStats,
            'kizeoDeliveredArticles' => $kizeoDeliveredArticles,
            'centralKizeoLocation' => $centralKizeoLocation,
            'kizeoCentralStockByVariant' => $kizeoCentralStockByVariant,
            'kizeoBatchEligibleIds' => $kizeoBatchEligibleIds,
            'kizeoQueue' => $kizeoQueue,
            'kizeoQueueCounts' => $kizeoQueueCounts,
            'kizeoPeriod' => $kizeoPeriod,
            'kizeoArticle' => $kizeoArticle,
            'kizeoChangeFilter' => $kizeoChangeFilter,
            'kizeoLastSyncedAt' => $kizeoLastSyncedAt,
            'kizeoAutoApply' => $this->stock->kizeoAutoApplyState(),
            'kizeoCatalogListId' => config('services.kizeo.inventory_catalog_list_id'),
            'canCreate' => $request->user()->tieneAcceso('inventario_bodega', 'puede_crear'),
            'canEdit' => $request->user()->tieneAcceso('inventario_bodega', 'puede_editar'),
        ]);
    }

    /**
     * Devuelve el detalle consultable de una variante del resumen sin cargar
     * de antemano todos los movimientos de cada producto del catálogo.
     */
    public function stockDetail(Request $request, InventarioVariante $variante)
    {
        $data = $request->validate([
            'ubicacion_id' => ['nullable', 'integer', 'exists:inventario_ubicaciones,id'],
        ]);
        $locationId = $data['ubicacion_id'] ?? null;
        $variante->load('producto.variantes');

        abort_unless($variante->activo && $variante->producto?->activo, 404);

        $product = $variante->producto;
        $variants = $product->variantes;
        $stockByVariant = InventarioMovimiento::query()
            ->selectRaw('variante_id, SUM(cantidad) as stock_actual')
            ->whereIn('variante_id', $variants->pluck('id'))
            ->when($locationId, fn (Builder $query) => $query->where('ubicacion_id', $locationId))
            ->groupBy('variante_id')
            ->pluck('stock_actual', 'variante_id');

        $variants->each(function (InventarioVariante $productVariant) use ($stockByVariant): void {
            $productVariant->setAttribute('stock_actual', (float) ($stockByVariant[$productVariant->id] ?? 0));
        });
        $selectedVariant = $variants->firstWhere('id', $variante->id) ?? $variante;

        $movements = InventarioMovimiento::query()
            ->with(['ubicacion', 'variante', 'registradoPor'])
            ->withCount('reversos')
            // La ficha se abre desde una fila de stock, que representa una talla
            // concreta. El resumen superior conserva las demás tallas del producto,
            // pero su trazabilidad no debe mezclarse en el historial consultado.
            ->where('variante_id', $selectedVariant->id)
            ->when($locationId, fn (Builder $query) => $query->where('ubicacion_id', $locationId))
            ->orderBy('ocurrido_en')
            ->orderBy('id')
            ->get();

        // La cartola se construye desde el primer movimiento de la variante.
        // Cada fila conserva el saldo que quedó después de esa operación, sin
        // mezclar movimientos de otras tallas del mismo producto.
        $runningBalance = 0.0;
        $movements->each(function (InventarioMovimiento $movement) use (&$runningBalance): void {
            $runningBalance += (float) $movement->cantidad;
            $movement->setAttribute('saldo_resultante', $runningBalance);
        });

        return view('inventario_bodega.partials.stock-detail', [
            'product' => $product,
            'variants' => $variants,
            'selectedVariant' => $selectedVariant,
            'movements' => $movements,
            'location' => $locationId ? InventarioUbicacion::query()->find($locationId) : null,
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40', 'unique:inventario_ubicaciones,codigo'],
            'nombre' => ['required', 'string', 'max:160'],
            'tipo' => ['required', Rule::in(array_keys(InventarioUbicacion::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $data['activo'] = $request->boolean('activo');
        InventarioUbicacion::create($data);

        return back()->with('success', 'Ubicacion agregada. Ya esta disponible para recibir, mover y contar stock.');
    }

    public function updateLocation(Request $request, InventarioUbicacion $ubicacion): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40', Rule::unique('inventario_ubicaciones', 'codigo')->ignore($ubicacion->id)],
            'nombre' => ['required', 'string', 'max:160'],
            'tipo' => ['required', Rule::in(array_keys(InventarioUbicacion::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $data['activo'] = $request->boolean('activo');
        $ubicacion->update($data);

        return back()->with('success', 'Ubicacion actualizada. Su historial de movimientos se conserva.');
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $data = $this->providerData($request);
        InventarioProveedor::create($data);

        return back()->with('success', 'Proveedor agregado.');
    }

    public function updateProvider(Request $request, InventarioProveedor $proveedor): RedirectResponse
    {
        $data = $this->providerData($request, $proveedor);
        $proveedor->update($data);

        return back()->with('success', 'Proveedor actualizado.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $this->productData($request, true);
        $this->stock->createProduct($data, $request->user());

        return back()->with('success', 'Producto creado. Las tallas o variantes ya se pueden seleccionar en los movimientos.');
    }

    public function updateProduct(Request $request, InventarioProducto $producto): RedirectResponse
    {
        $wasActive = (bool) $producto->activo;
        $data = $this->productData($request, false);
        $this->stock->updateProduct($producto, $data);
        $isActive = (bool) $producto->fresh()->activo;
        $message = 'Producto actualizado. Las variantes existentes y su historial se conservan.';
        if ($wasActive && ! $isActive) {
            $message .= ' Quedó inhabilitado: sincroniza el catálogo con Kizeo para que deje de aparecer en entregas nuevas. Puedes reactivarlo más adelante.';
        } elseif (! $wasActive && $isActive) {
            $message .= ' Quedó vigente de nuevo: sincroniza el catálogo con Kizeo para que vuelva a las opciones de entrega.';
        }

        return back()->with('success', $message);
    }

    public function updateVariantStatus(Request $request, InventarioVariante $variante): RedirectResponse
    {
        $data = $request->validate([
            'activo' => ['nullable', 'boolean'],
        ]);
        $wasActive = (bool) $variante->activo;
        $wasProductActive = (bool) $variante->producto()->value('activo');
        $shouldBeActive = (bool) ($data['activo'] ?? false);

        $this->stock->setVariantActive($variante, $shouldBeActive);
        $variante->refresh()->load('producto');

        $message = $shouldBeActive
            ? "Talla {$variante->talla} reactivada. Su saldo e historial se conservan."
            : "Talla {$variante->talla} inhabilitada. Su saldo e historial se conservan.";

        if ($wasProductActive !== (bool) $variante->producto->activo) {
            $message .= $variante->producto->activo
                ? ' El producto también volvió a quedar vigente.'
                : ' Era la última talla vigente, por lo que el producto quedó inactivo.';
        } elseif ($wasActive === $shouldBeActive) {
            $message = "La talla {$variante->talla} ya estaba ".($shouldBeActive ? 'vigente.' : 'inhabilitada.');
        }

        return redirect()->route('inventario-bodega.index', $this->catalogEditorQuery($request, $variante->producto_id))
            ->with('success', $message.' Sincroniza con Kizeo para reflejar el cambio en entregas nuevas.');
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'proveedor_id' => ['nullable', 'exists:inventario_proveedores,id'],
            'tipo_documento' => ['required', Rule::in(array_keys(InventarioIngreso::TIPOS_DOCUMENTO))],
            'numero_documento' => ['nullable', 'string', 'max:100'],
            'fecha_documento' => ['nullable', 'date'],
            'fecha_recepcion' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variante_id' => ['required', 'exists:inventario_variantes,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.costo_unitario' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $ingreso = $this->stock->registerReceipt($data, $data['items'], $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'ingresos'])
            ->with('success', "Ingreso {$ingreso->codigo} registrado. El stock se actualizo en la ubicacion seleccionada.");
    }

    public function reverseReceipt(Request $request, InventarioIngreso $ingreso): RedirectResponse
    {
        $data = $request->validate([
            'motivo_reversion' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $this->stock->reverseReceipt($ingreso, $data['motivo_reversion'], $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'ingresos'])
            ->with('success', "Ingreso {$ingreso->codigo} anulado. El sistema creo movimientos inversos y conservo el historial.");
    }

    public function setVariantStock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'variante_id' => ['required', 'exists:inventario_variantes,id'],
            'stock_final' => ['required', 'numeric', 'gte:0'],
            'observacion' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $variant = InventarioVariante::query()->findOrFail($data['variante_id']);
        $stock = $this->stock->setVariantStock($data, $request->user());

        return redirect()->route('inventario-bodega.index', $this->catalogEditorQuery($request, $variant->producto_id, $data['ubicacion_id']))
            ->with('success', 'Saldo de la talla actualizado a '.rtrim(rtrim(number_format($stock, 3, ',', '.'), '0'), ',').'. El ajuste quedo registrado en el kardex.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $costCenterCodes = Schema::hasTable('comercial_centros_costo')
            ? CentroCosto::query()->activos()->whereNotNull('codigo')->where('codigo', '!=', '')->pluck('codigo')->all()
            : [];
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['ENTREGA_EPP', 'DESPACHO_CENTRO', 'TRASLADO', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'STOCK_INICIAL'])],
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'ubicacion_destino_id' => ['nullable', 'required_if:tipo,TRASLADO', 'exists:inventario_ubicaciones,id'],
            'variante_id' => ['required', 'exists:inventario_variantes,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'ocurrido_en' => ['required', 'date'],
            'destinatario_nombre' => ['nullable', 'string', 'max:200'],
            'destinatario_rut' => ['nullable', 'string', 'max:30'],
            'centro_costo_id' => ['nullable', Rule::exists('inventario_centros_costo', 'id')->where('activo', true)],
            'coordinador_id' => ['nullable', Rule::exists('inventario_coordinadores', 'id')->where('activo', true)],
            'centro_costo' => ['nullable', 'string', 'max:180', Rule::in($costCenterCodes)],
            'documento_tipo' => ['nullable', Rule::in(array_keys(InventarioMovimiento::TIPOS_DOCUMENTO))],
            'documento_numero' => ['nullable', 'string', 'max:100'],
            'costo_unitario' => ['nullable', 'numeric', 'gte:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $costCenter = isset($data['centro_costo_id'])
            ? InventarioCentroCosto::query()->with('coordinador')->find($data['centro_costo_id'])
            : null;
        if ($costCenter) {
            $data['centro_costo'] = $costCenter->nombre;
            if ($costCenter->coordinador_id) {
                $data['coordinador_id'] = $costCenter->coordinador_id;
            }
        }

        $coordinator = isset($data['coordinador_id'])
            ? InventarioCoordinador::query()->find($data['coordinador_id'])
            : null;
        if ($coordinator) {
            $data['destinatario_nombre'] = $data['destinatario_nombre'] ?? $coordinator->nombre;
            $data['destinatario_rut'] = $data['destinatario_rut'] ?? $coordinator->rut;
        }

        $this->stock->registerManualMovement($data, $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'movimientos'])
            ->with('success', 'Movimiento registrado. El saldo fue actualizado sin editar movimientos anteriores.');
    }

    public function reverseMovement(Request $request, InventarioMovimiento $movimiento): RedirectResponse
    {
        $data = $request->validate([
            'motivo_reversion' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->stock->reverseManualMovement($movimiento, $data['motivo_reversion'], $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'movimientos'])
            ->with('success', "Movimiento {$movimiento->codigo} anulado. El sistema creó el reverso y conservó el historial.");
    }

    public function storeStocktake(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'fecha_corte' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'incluir_sin_stock' => ['nullable', 'boolean'],
        ]);
        $data['incluir_sin_stock'] = $request->boolean('incluir_sin_stock');
        $conteo = $this->stock->createStocktake($data, $request->user());

        return redirect()->route('inventario-bodega.conteos.show', $conteo)
            ->with('success', 'Conteo creado. Registra las cantidades fisicas y luego envialo a revision.');
    }

    public function showStocktake(InventarioConteo $conteo)
    {
        $conteo->load(['ubicacion', 'lineas.producto', 'lineas.variante']);
        $conteo->setRelation('lineas', $conteo->lineas->sortBy(fn ($line) => $line->producto->nombre.' '.$line->variante->talla));

        return view('inventario_bodega.conteo', [
            'conteo' => $conteo,
            'canEdit' => request()->user()->tieneAcceso('inventario_bodega', 'puede_editar'),
        ]);
    }

    public function updateStocktake(Request $request, InventarioConteo $conteo): RedirectResponse
    {
        $data = $request->validate([
            'lineas' => ['required', 'array'],
            'lineas.*.cantidad_fisica' => ['nullable', 'numeric', 'gte:0'],
            'lineas.*.observacion' => ['nullable', 'string', 'max:300'],
        ]);
        $this->stock->saveStocktake($conteo, $data['lineas']);

        return back()->with('success', 'Conteo guardado. Cuando todas las lineas tengan cantidad, quedara listo para revision.');
    }

    public function approveStocktake(Request $request, InventarioConteo $conteo): RedirectResponse
    {
        $this->stock->approveStocktake($conteo->load('lineas'), $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'conteos'])
            ->with('success', "Conteo {$conteo->codigo} aprobado. Las diferencias se registraron como ajustes trazables.");
    }

    public function recreateStocktake(Request $request, InventarioConteo $conteo): RedirectResponse
    {
        $replacement = $this->stock->recreateStocktake($conteo, $request->user());

        return redirect()->route('inventario-bodega.conteos.show', $replacement)
            ->with('success', "Se recreó {$replacement->codigo} con el saldo consolidado de {$conteo->codigo}. Se mantuvo la fecha de corte y se copiaron los valores físicos ya registrados.");
    }

    public function destroyStocktake(InventarioConteo $conteo): RedirectResponse
    {
        $codigo = $conteo->codigo;
        $this->stock->deleteStocktake($conteo);

        return redirect()->route('inventario-bodega.index', ['vista' => 'conteos'])
            ->with('success', "Conteo {$codigo} eliminado. No se modificó el kardex.");
    }

    public function toggleKizeoAutoApply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);
        $enabled = (bool) $data['activo'];
        $this->stock->setKizeoAutoApply($enabled, $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'kizeo'])
            ->with('success', $enabled
                ? 'Descuento automático activado. Solo las entregas nuevas de Kizeo, posteriores a este momento, se descontarán de Sede Central. La cola histórica no se aplica.'
                : 'Descuento automático desactivado. Las nuevas entregas de Kizeo quedarán pendientes para aplicarlas a mano.');
    }

    public function applyKizeoDelivery(Request $request, EntregaBodega $entrega): RedirectResponse
    {
        $data = $request->validate([
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.variante_id' => ['required', 'exists:inventario_variantes,id'],
        ]);
        $origin = $this->stock->kizeoOriginLocation();
        $application = $this->stock->applyKizeoDelivery($entrega->load('items'), $origin->id, $data['lineas'], $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'kizeo'])
            ->with('success', "Entrega Kizeo aplicada desde {$application->ubicacion->nombre}. El descuento quedó vinculado al comprobante original.");
    }

    public function applyKizeoDeliveriesBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entregas' => ['required', 'array', 'min:1', 'max:40'],
            'entregas.*' => ['required', 'integer', 'distinct', 'exists:entregas_bodega,id'],
        ]);

        $deliveries = EntregaBodega::query()
            ->with(['items', 'inventarioAplicacion'])
            ->whereIn('id', $data['entregas'])
            ->get()
            ->keyBy('id');
        $applied = 0;
        $issues = [];

        foreach ($data['entregas'] as $deliveryId) {
            /** @var EntregaBodega|null $delivery */
            $delivery = $deliveries->get($deliveryId);
            if (! $delivery) {
                $issues[] = "Entrega #{$deliveryId}: ya no está disponible.";

                continue;
            }

            try {
                if (EntregaBodegaSyncService::isHistoricalStockForm($delivery->kizeo_form_id)) {
                    $issues[] = 'KZ-'.($delivery->kizeo_record_number ?: $delivery->kizeo_data_id).': formulario histórico, no se descuenta.';

                    continue;
                }

                $this->stock->applyKizeoDeliveryFromCentral($delivery, $request->user());
                $applied++;
            } catch (ValidationException $exception) {
                $reason = collect($exception->errors())->flatten()->first() ?: 'No se pudo aplicar.';
                $issues[] = 'KZ-'.($delivery->kizeo_record_number ?: $delivery->kizeo_data_id).': '.$reason;
            }
        }

        $response = redirect()->route('inventario-bodega.index', ['vista' => 'kizeo']);
        if ($applied > 0) {
            $response->with('success', "{$applied} salida(s) fueron descontadas desde Sede Central SAEP y quedaron trazables en Kardex.");
        }

        if ($issues !== []) {
            $response->with('warning', count($issues).' entrega(s) no se aplicaron. '.implode(' · ', array_slice($issues, 0, 3)));
        }

        return $response;
    }

    public function reverseKizeoDelivery(Request $request, InventarioEntregaKizeoAplicacion $aplicacion): RedirectResponse
    {
        $data = $request->validate([
            'motivo_reversion' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $this->stock->reverseKizeoDelivery($aplicacion, $data['motivo_reversion'], $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'kizeo'])
            ->with('success', 'La salida fue reversada. Se repuso el stock con movimientos nuevos y se conservo la trazabilidad.');
    }

    public function importProducts(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ]);
        $result = $this->stock->importProducts($request->file('archivo'), $request->user());

        $stockMessage = $result['stocksSet'] > 0
            ? " {$result['stocksSet']} saldo(s) por ubicacion fueron cargados o ajustados con movimientos trazables."
            : ' Las filas sin Ubicacion_Codigo y Stock_Inicial quedan con stock 0.';
        $costMessage = $result['costsUpdated'] > 0
            ? " {$result['costsUpdated']} costo(s) de referencia fueron actualizados y registrados en su historial."
            : '';
        $statusMessage = $result['variantsInactive'] > 0
            ? " {$result['variantsInactive']} talla(s) quedaron inhabilitadas segun la columna Estado."
            : '';
        $centralStockMessage = $result['centralStockRows'] > 0
            ? " {$result['centralStockRows']} saldo(s) sin ubicación específica fueron cargados en Sede Central SAEP."
            : '';

        return redirect()->route('inventario-bodega.index', ['vista' => 'catalogo'])
            ->with('success', "Importacion finalizada: {$result['created']} productos creados, {$result['updated']} actualizados, {$result['variantsCreated']} variantes creadas y {$result['skipped']} filas omitidas.".$stockMessage.$costMessage.$statusMessage.$centralStockMessage);
    }

    public function syncCatalogToKizeo(): RedirectResponse
    {
        try {
            $summary = $this->catalogSync->synchronize();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('inventario-bodega.index', ['vista' => 'catalogo'])
                ->with('error', 'No se pudo publicar el catálogo en Kizeo. El catálogo SAEP no fue modificado. Revisa la conexión o inténtalo nuevamente.');
        }

        $message = "Kizeo actualizado desde SAEP: {$summary['created']} creados, {$summary['updated']} actualizados, {$summary['removed']} inactivos quitados y {$summary['unchanged']} sin cambios.";
        $response = redirect()->route('inventario-bodega.index', ['vista' => 'catalogo'])->with('success', $message);
        $notices = [];
        if ($summary['deferred'] > 0) {
            $notices[] = "{$summary['deferred']} cambio(s) quedarán para la siguiente sincronización automática.";
        }
        if ($summary['orphans'] !== []) {
            $notices[] = count($summary['orphans']).' ítem(s) de Kizeo no están mapeados desde SAEP; se conservaron sin eliminar.';
        }
        if ($summary['errors'] !== []) {
            $notices[] = count($summary['errors']).' variante(s) no se publicaron. '.implode(' · ', array_slice($summary['errors'], 0, 2));
        }

        return $notices === [] ? $response : $response->with('warning', implode(' ', $notices));
    }

    public function importReceipts(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ]);
        $result = $this->stock->importReceipts($request->file('archivo'), $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'ingresos'])
            ->with('success', "Importacion de ingresos finalizada: {$result['receipts']} comprobantes y {$result['lines']} lineas registradas. El stock ya fue actualizado.");
    }

    public function importMovements(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ]);
        $result = $this->stock->importManualMovements($request->file('archivo'), $request->user());
        $message = "Importación de movimientos finalizada: {$result['movements']} fila(s) aplicada(s) y registrada(s) en Kardex.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} fila(s) ya habían sido importadas y se omitieron para no duplicar el stock.";
        }

        return redirect()->route('inventario-bodega.index', ['vista' => 'movimientos'])
            ->with('success', $message);
    }

    public function importOperationalMasters(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,xls', 'max:10240'],
        ]);
        $result = $this->operationalMasters->import($request->file('archivo'));
        $pending = $result['coordinadoresSinRelacion'] === []
            ? ''
            : ' Se conservaron sin vínculo automático: '.implode(', ', $result['coordinadoresSinRelacion']).'.';

        return redirect()->route('inventario-bodega.index', ['vista' => 'maestros'])
            ->with('success', "Maestros actualizados: {$result['centrosCreados']} centros creados, {$result['centrosActualizados']} actualizados, {$result['coordinadoresCreados']} coordinadores creados y {$result['coordinadoresActualizados']} actualizados.".$pending);
    }

    public function storeOperationalCoordinator(Request $request): RedirectResponse
    {
        InventarioCoordinador::create($this->operationalCoordinatorData($request));

        return back()->with('success', 'Coordinador agregado. Ya puede asignarse a un centro de costo y usarse como destinatario.');
    }

    public function updateOperationalCoordinator(Request $request, InventarioCoordinador $coordinador): RedirectResponse
    {
        $coordinador->update($this->operationalCoordinatorData($request, $coordinador));

        return back()->with('success', 'Coordinador actualizado. Sus movimientos históricos y relaciones se conservan.');
    }

    public function storeOperationalCostCenter(Request $request): RedirectResponse
    {
        InventarioCentroCosto::create($this->operationalCostCenterData($request));

        return back()->with('success', 'Centro de costo agregado. Ya queda disponible en Movimientos.');
    }

    public function updateOperationalCostCenter(Request $request, InventarioCentroCosto $centroCosto): RedirectResponse
    {
        $centroCosto->update($this->operationalCostCenterData($request, $centroCosto));

        return back()->with('success', 'Centro de costo actualizado. Sus movimientos históricos se conservan.');
    }

    public function productTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia', 'Stock_Critico', 'Ubicacion_Codigo', 'Stock_Inicial', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D0B64']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'B' ? 34 : 20);
        }
        $sheet->getStyle('H2:H5000')->getNumberFormat()->setFormatCode('#,##0.00');

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $instructions->fromArray([
            ['Plantilla de catalogo e inventario inicial'],
            ['Stock_Critico es el minimo que activa una alerta; no representa existencias.'],
            ['Costo_Referencia es el último costo conocido de esa talla. Ingresa solo el número (por ejemplo, 41590); Excel puede mostrarlo con separador de miles. Puedes usar Precio, Precio_Referencia, Costo o Costo_Unitario como alias. Cero o vacío significa sin información y no borra un costo ya registrado.'],
            ['Estado se aplica a cada talla: deja vacío o usa Activo/Habilitado para dejarla disponible; usa Inactivo/Inhabilitado para conservarla en catálogo, pero impedir que se seleccione en nuevos movimientos.'],
            ['Codigo es opcional para un producto nuevo: SAEP lo genera automáticamente desde el nombre. Para cada talla también genera su código propio.'],
            ['Para cargar existencias, informa Ubicacion_Codigo (codigo de una ubicacion activa) y Stock_Inicial. Si recibes “Todas las ubicaciones” o dejas la ubicación vacía con Stock_Inicial, SAEP carga ese saldo en SAEP-CENTRAL (Sede Central SAEP).'],
            ['Cada fila corresponde a un producto, talla y ubicacion. El Stock_Inicial fija el saldo de esa talla en esa ubicacion y deja un movimiento trazable.'],
            ['Si vuelves a importar la misma fila con el mismo saldo, no se duplica stock. Si cambias el saldo, se crea un ajuste trazable.'],
            ['Deja Stock_Inicial vacío si solo quieres cargar o actualizar el catálogo.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(120);
        $instructions->getStyle('A1')->getFont()->setBold(true);
        $instructions->getStyle('A1:A9')->getAlignment()->setWrapText(true);

        $path = storage_path('app/plantilla_catalogo_inventario_'.now()->format('YmdHis').'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'plantilla_catalogo_inventario.xlsx')->deleteFileAfterSend(true);
    }

    public function receiptTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ingresos');
        $headers = [
            'Referencia_Ingreso', 'Ubicacion_Codigo', 'Proveedor', 'Proveedor_Rut', 'Tipo_Documento',
            'Numero_Documento', 'Fecha_Documento', 'Fecha_Recepcion', 'Codigo_Producto', 'Talla',
            'Cantidad', 'Costo_Unitario', 'Observacion',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D0B64']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M1');
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['A', 'C', 'F', 'I', 'M'], true) ? 28 : 18);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $instructions->fromArray([
            ['Plantilla de importacion de ingresos'],
            ['Cada fila es un articulo y una talla. Agrupa las lineas de un mismo comprobante con la misma Referencia_Ingreso.'],
            ['Ubicacion_Codigo, Codigo_Producto y Talla deben existir y estar activos en el catalogo.'],
            ['Tipo_Documento: FACTURA, GUIA_DESPACHO u OTRO. Fechas: AAAA-MM-DD o DD/MM/AAAA.'],
            ['Proveedor y Proveedor_Rut son opcionales. Si el proveedor no existe, se crea al importar.'],
            ['La importacion rechaza documentos ya vigentes para evitar duplicar stock.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(115);
        $instructions->getStyle('A1')->getFont()->setBold(true);
        $instructions->getStyle('A1:A6')->getAlignment()->setWrapText(true);

        $path = storage_path('app/plantilla_ingresos_inventario_'.now()->format('YmdHis').'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'plantilla_ingresos_inventario.xlsx')->deleteFileAfterSend(true);
    }

    public function movementTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movimientos');
        $headers = [
            'Referencia_Movimiento', 'Tipo', 'Ubicacion_Origen_Codigo', 'Ubicacion_Destino_Codigo',
            'Codigo_Producto', 'Talla', 'Cantidad', 'Fecha_Hora', 'Centro_Costo', 'Coordinador',
            'Destinatario', 'RUT_Destinatario', 'Tipo_Documento', 'Numero_Documento', 'Costo_Unitario', 'Observacion',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D0B64']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:P1');
        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['A', 'C', 'D', 'E', 'I', 'J', 'K', 'N', 'P'], true) ? 28 : 18);
        }
        $sheet->getStyle('G2:G5000')->getNumberFormat()->setFormatCode('0.000');
        $sheet->getStyle('H2:H5000')->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
        $sheet->getStyle('O2:O5000')->getNumberFormat()->setFormatCode('#,##0.00');

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $instructions->fromArray([
            ['Plantilla de importación de movimientos manuales'],
            ['Cada fila es una operación lógica y Referencia_Movimiento debe ser única. Si vuelves a cargar la misma referencia, SAEP la omite para no duplicar el saldo.'],
            ['Tipo: ENTREGA_EPP, DESPACHO_CENTRO, TRASLADO, AJUSTE_POSITIVO, AJUSTE_NEGATIVO o STOCK_INICIAL.'],
            ['Ubicacion_Origen_Codigo, Codigo_Producto, Talla, Cantidad y Fecha_Hora son obligatorios. Fecha_Hora acepta AAAA-MM-DD HH:MM, DD/MM/AAAA HH:MM o fecha de Excel.'],
            ['Para TRASLADO debes indicar Ubicacion_Destino_Codigo. SAEP descontará el origen y registrará automáticamente la entrada en el destino.'],
            ['Centro_Costo acepta el número maestro o nombre del centro activo; Coordinador acepta RUT o nombre de la maestra. Si indicas un centro con coordinador asociado, se completa automáticamente.'],
            ['Las salidas de formularios Kizeo no se cargan aquí. Se aplican desde Entregas Kizeo para conservar el comprobante y evitar descuentos duplicados.'],
            ['Toda fila importada queda en Kardex como movimiento manual, con su referencia de importación, y puede anularse mediante reverso trazable.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(135);
        $instructions->getStyle('A1')->getFont()->setBold(true);
        $instructions->getStyle('A1:A8')->getAlignment()->setWrapText(true);

        $path = storage_path('app/plantilla_movimientos_inventario_'.now()->format('YmdHis').'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'plantilla_movimientos_inventario.xlsx')->deleteFileAfterSend(true);
    }

    public function exportBalances(Request $request)
    {
        $locationId = $request->integer('ubicacion_id') ?: null;
        $balances = $this->filterSummaryBalances($this->stock->balances($locationId), $this->summaryFilters($request));
        $locationName = $locationId
            ? InventarioUbicacion::query()->find($locationId)?->nombre
            : 'Todas las ubicaciones';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock actual');
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia', 'Stock_Critico', 'Stock_Actual', 'Ubicacion', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D0B64']],
        ]);
        $row = 2;
        foreach ($balances as $variant) {
            $sheet->fromArray([
                $variant->producto->codigo,
                $variant->producto->nombre,
                $variant->producto->tipo,
                $variant->producto->categoria,
                $variant->producto->subcategoria,
                $variant->producto->unidad_medida,
                $variant->talla,
                $variant->costo_referencia ?? 0,
                $variant->stock_minimo ?? $variant->producto->stock_minimo,
                $variant->stock_actual,
                $locationName,
                $variant->producto->activo && $variant->activo ? 'Activo' : 'Inactivo',
            ], null, "A{$row}");
            $row++;
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['B', 'D', 'E', 'K'], true) ? 30 : 18);
        }

        $path = storage_path('app/stock_inventario_'.now()->format('Ymd_His').'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'stock_inventario.xlsx')->deleteFileAfterSend(true);
    }

    private function kizeoDeliveryPeriod(Request $request): array
    {
        $period = (string) $request->input('kizeo_periodo', 'hoy');
        $allowedPeriods = [
            'hoy',
            'ayer',
            'antes_ayer',
            'semana',
            'mes_actual',
            'mes_anterior',
            'personalizado',
            'todo',
        ];
        $period = in_array($period, $allowedPeriods, true) ? $period : 'hoy';
        $today = now()->startOfDay();
        $from = $today->copy();
        $to = $today->copy();
        $label = 'Hoy';

        switch ($period) {
            case 'ayer':
                $from = $today->copy()->subDay();
                $to = $from->copy();
                $label = 'Ayer';
                break;
            case 'antes_ayer':
                $from = $today->copy()->subDays(2);
                $to = $from->copy();
                $label = 'Antes de ayer';
                break;
            case 'semana':
                $from = $today->copy()->startOfWeek();
                $to = $today->copy();
                $label = 'Esta semana';
                break;
            case 'mes_actual':
                $from = $today->copy()->startOfMonth();
                $to = $today->copy();
                $label = 'Este mes';
                break;
            case 'mes_anterior':
                $from = $today->copy()->subMonthNoOverflow()->startOfMonth();
                $to = $from->copy()->endOfMonth();
                $label = 'Mes anterior';
                break;
            case 'personalizado':
                $from = $this->kizeoDateInput($request->input('kizeo_desde')) ?? $today->copy();
                $to = $this->kizeoDateInput($request->input('kizeo_hasta')) ?? $from->copy();
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                $label = 'Periodo personalizado';
                break;
            case 'todo':
                $from = null;
                $to = null;
                $label = 'Todo el historial';
                break;
        }

        if ($from && $to && $period !== 'hoy' && $period !== 'personalizado') {
            $label .= ' · '.$from->format('d/m/Y').($from->isSameDay($to) ? '' : ' al '.$to->format('d/m/Y'));
        }
        if ($period === 'personalizado') {
            $label .= ' · '.$from->format('d/m/Y').($from->isSameDay($to) ? '' : ' al '.$to->format('d/m/Y'));
        }

        return compact('period', 'from', 'to', 'label');
    }

    /**
     * Restringe los comprobantes Kizeo al mismo período elegido en la interfaz.
     * Cuando una fuente histórica no trae fecha de pedido, se usa la fecha de
     * creación en Kizeo para no ocultarla ni contarla dos veces.
     *
     * @param  array{from: ?Carbon, to: ?Carbon}  $period
     */
    private function applyKizeoDeliveryPeriod(Builder $query, array $period): Builder
    {
        if (! $period['from'] || ! $period['to']) {
            return $query;
        }

        $fromDate = $period['from']->copy()->startOfDay();
        $toDate = $period['to']->copy()->endOfDay();

        return $query->where(function (Builder $deliveries) use ($fromDate, $toDate) {
            $deliveries->whereBetween('fecha_pedido', [$fromDate, $toDate])
                ->orWhere(function (Builder $withoutRequestDate) use ($fromDate, $toDate) {
                    $withoutRequestDate
                        ->whereNull('fecha_pedido')
                        ->whereBetween('kizeo_created_at', [$fromDate, $toDate]);
                });
        });
    }

    /** Limita la cola a comprobantes cuyo artículo informado coincide con la búsqueda. */
    private function applyKizeoDeliveryArticleFilter(Builder $query, string $article): Builder
    {
        $article = trim($article);

        if ($article === '') {
            return $query;
        }

        return $query->whereHas('items', function (Builder $items) use ($article) {
            $items->where('articulo', 'like', '%'.$article.'%');
        });
    }

    private function currentKizeoDeliveryForms(Builder $query): Builder
    {
        return $query->where(function (Builder $forms) {
            $forms->whereIn('kizeo_form_id', EntregaBodegaSyncService::currentFormIds())
                ->orWhereNull('kizeo_form_id')
                ->orWhere('kizeo_form_id', '');
        });
    }

    private function kizeoDateInput(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            return $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{search: string, from: ?string, to: ?string, status: string, location_id: ?int} */
    private function receiptHistoryFilters(Request $request): array
    {
        $from = $this->kizeoDateInput($request->input('ingreso_desde'));
        $to = $this->kizeoDateInput($request->input('ingreso_hasta'));

        return [
            'search' => trim((string) $request->input('ingreso_buscar', '')),
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'status' => in_array($request->input('ingreso_estado'), ['vigentes', 'anulados'], true)
                ? $request->input('ingreso_estado')
                : '',
            'location_id' => $request->integer('ingreso_ubicacion_id') ?: null,
        ];
    }

    /** @return array{search: string, from: ?string, to: ?string, type: string, location_id: ?int} */
    private function movementHistoryFilters(Request $request): array
    {
        $from = $this->kizeoDateInput($request->input('movimiento_desde'));
        $to = $this->kizeoDateInput($request->input('movimiento_hasta'));
        $type = (string) $request->input('movimiento_tipo', '');

        return [
            'search' => trim((string) $request->input('movimiento_buscar', '')),
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'type' => array_key_exists($type, InventarioMovimiento::TIPOS) ? $type : '',
            'location_id' => $request->integer('movimiento_ubicacion_id') ?: null,
        ];
    }

    /**
     * @param  array{search: string, from: ?string, to: ?string, status: string, location_id: ?int}  $filters
     */
    private function applyReceiptHistoryFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'], fn (Builder $receipts, string $from) => $receipts->whereDate('fecha_recepcion', '>=', $from))
            ->when($filters['to'], fn (Builder $receipts, string $to) => $receipts->whereDate('fecha_recepcion', '<=', $to))
            ->when($filters['status'] === 'vigentes', fn (Builder $receipts) => $receipts->whereNull('reversado_en'))
            ->when($filters['status'] === 'anulados', fn (Builder $receipts) => $receipts->whereNotNull('reversado_en'))
            ->when($filters['location_id'], fn (Builder $receipts, int $locationId) => $receipts->where('ubicacion_id', $locationId))
            ->when($filters['search'] !== '', function (Builder $receipts) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $receipts->where(function (Builder $matches) use ($term) {
                    $matches->where('codigo', 'like', $term)
                        ->orWhere('numero_documento', 'like', $term)
                        ->orWhere('tipo_documento', 'like', $term)
                        ->orWhere('observacion', 'like', $term)
                        ->orWhereHas('ubicacion', fn (Builder $locations) => $locations->where('nombre', 'like', $term)->orWhere('codigo', 'like', $term))
                        ->orWhereHas('proveedor', fn (Builder $providers) => $providers->where('nombre', 'like', $term)->orWhere('rut', 'like', $term))
                        ->orWhereHas('registradoPor', fn (Builder $users) => $users->where('name', 'like', $term))
                        ->orWhereHas('items.producto', fn (Builder $products) => $products->where('nombre', 'like', $term)->orWhere('codigo', 'like', $term))
                        ->orWhereHas('items.variante', fn (Builder $variants) => $variants->where('talla', 'like', $term)->orWhere('codigo', 'like', $term));
                });
            });
    }

    /**
     * @param  array{search: string, from: ?string, to: ?string, type: string, location_id: ?int}  $filters
     */
    private function applyMovementHistoryFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'], fn (Builder $movements, string $from) => $movements->whereDate('ocurrido_en', '>=', $from))
            ->when($filters['to'], fn (Builder $movements, string $to) => $movements->whereDate('ocurrido_en', '<=', $to))
            ->when($filters['type'] !== '', fn (Builder $movements) => $movements->where('tipo', $filters['type']))
            ->when($filters['location_id'], fn (Builder $movements, int $locationId) => $movements->where('ubicacion_id', $locationId))
            ->when($filters['search'] !== '', function (Builder $movements) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $movements->where(function (Builder $matches) use ($term) {
                    $matches->where('codigo', 'like', $term)
                        ->orWhere('documento_numero', 'like', $term)
                        ->orWhere('documento_tipo', 'like', $term)
                        ->orWhere('destinatario_nombre', 'like', $term)
                        ->orWhere('destinatario_rut', 'like', $term)
                        ->orWhere('centro_costo', 'like', $term)
                        ->orWhere('registrado_por_nombre', 'like', $term)
                        ->orWhere('observacion', 'like', $term)
                        ->orWhereHas('producto', fn (Builder $products) => $products->where('nombre', 'like', $term)->orWhere('codigo', 'like', $term))
                        ->orWhereHas('variante', fn (Builder $variants) => $variants->where('talla', 'like', $term)->orWhere('codigo', 'like', $term))
                        ->orWhereHas('ubicacion', fn (Builder $locations) => $locations->where('nombre', 'like', $term)->orWhere('codigo', 'like', $term))
                        ->orWhereHas('centroCosto', fn (Builder $centers) => $centers->where('nombre', 'like', $term)->orWhere('numero_maestro', 'like', $term))
                        ->orWhereHas('coordinador', fn (Builder $coordinators) => $coordinators->where('nombre', 'like', $term)->orWhere('rut', 'like', $term))
                        ->orWhereHas('registradoPor', fn (Builder $users) => $users->where('name', 'like', $term));
                });
            });
    }

    /**
     * Período de lectura para los gráficos operativos del resumen.
     * El catálogo y el saldo actual no se retrotraen: muestran el estado actual.
     *
     * @return array{period: string, label: string, from: Carbon, to: Carbon, requested_from: Carbon, requested_to: Carbon, tracking_start: Carbon, has_data_range: bool}
     */
    private function summaryOperationalPeriod(Request $request): array
    {
        $period = (string) $request->input('resumen_periodo', 'hoy');
        $allowedPeriods = ['hoy', 'ayer', 'semana', 'mes_actual', 'mes_anterior', 'personalizado', 'inicio'];
        $period = in_array($period, $allowedPeriods, true) ? $period : 'hoy';
        $today = now()->startOfDay();
        $trackingStart = $this->inventoryReportingStart();
        $requestedFrom = $today->copy();
        $requestedTo = $today->copy();
        $label = 'Hoy';

        switch ($period) {
            case 'ayer':
                $requestedFrom = $today->copy()->subDay();
                $requestedTo = $requestedFrom->copy();
                $label = 'Ayer';
                break;
            case 'semana':
                $requestedFrom = $today->copy()->startOfWeek();
                $label = 'Esta semana';
                break;
            case 'mes_actual':
                $requestedFrom = $today->copy()->startOfMonth();
                $label = 'Este mes';
                break;
            case 'mes_anterior':
                $requestedFrom = $today->copy()->subMonthNoOverflow()->startOfMonth();
                $requestedTo = $requestedFrom->copy()->endOfMonth();
                $label = 'Mes anterior';
                break;
            case 'personalizado':
                $requestedFrom = $this->kizeoDateInput($request->input('resumen_desde')) ?? $today->copy();
                $requestedTo = $this->kizeoDateInput($request->input('resumen_hasta')) ?? $requestedFrom->copy();
                if ($requestedFrom->gt($requestedTo)) {
                    [$requestedFrom, $requestedTo] = [$requestedTo, $requestedFrom];
                }
                $label = 'Período personalizado';
                break;
            case 'inicio':
                $requestedFrom = $trackingStart->copy();
                $label = 'Desde el inicio';
                break;
        }

        $now = now()->endOfDay();
        if ($requestedTo->gt($now)) {
            $requestedTo = $now;
        }
        $hasDataRange = $requestedTo->gte($trackingStart);
        $from = $requestedFrom->lt($trackingStart) ? $trackingStart->copy() : $requestedFrom->copy();

        if ($period !== 'hoy' && $period !== 'inicio') {
            $label .= ' · '.$requestedFrom->format('d/m/Y').($requestedFrom->isSameDay($requestedTo) ? '' : ' al '.$requestedTo->format('d/m/Y'));
        }

        return [
            'period' => $period,
            'label' => $label,
            'from' => $from->startOfDay(),
            'to' => $requestedTo->endOfDay(),
            'requested_from' => $requestedFrom->startOfDay(),
            'requested_to' => $requestedTo->endOfDay(),
            'tracking_start' => $trackingStart,
            'has_data_range' => $hasDataRange,
        ];
    }

    /** @param array{from: Carbon, to: Carbon, has_data_range: bool} $period */
    private function applySummaryOperationalPeriod(Builder $query, array $period): Builder
    {
        if (! $period['has_data_range']) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereBetween('ocurrido_en', [$period['from'], $period['to']]);
    }

    private function summaryFilters(Request $request): array
    {
        $stockStatus = trim((string) $request->input('estado_stock'));
        $stockStatus = in_array($stockStatus, ['critico', 'con_stock', 'sin_stock', 'sobre_minimo'], true)
            ? $stockStatus
            : null;
        $filters = [
            'search' => trim((string) $request->input('buscar')),
            'stock_status' => $stockStatus,
            'category' => trim((string) $request->input('categoria')) ?: null,
            'subcategory' => trim((string) $request->input('subcategoria')) ?: null,
            'provider_id' => $request->integer('proveedor_id') ?: null,
        ];
        $filters['applied'] = filled($filters['search'])
            || filled($filters['stock_status'])
            || filled($filters['category'])
            || filled($filters['subcategory'])
            || filled($filters['provider_id']);

        return $filters;
    }

    private function filterSummaryBalances(Collection $balances, array $filters): Collection
    {
        if ($filters['search'] !== '') {
            $needle = Str::lower($filters['search']);
            $balances = $balances->filter(fn (InventarioVariante $variant) => str_contains(
                Str::lower($variant->producto->nombre.' '.$variant->producto->codigo.' '.$variant->talla),
                $needle,
            ));
        }

        if ($filters['category']) {
            $category = Str::lower($filters['category']);
            $balances = $balances->filter(fn (InventarioVariante $variant) => Str::lower((string) $variant->producto->categoria) === $category);
        }

        if ($filters['subcategory']) {
            $subcategory = Str::lower($filters['subcategory']);
            $balances = $balances->filter(fn (InventarioVariante $variant) => Str::lower((string) $variant->producto->subcategoria) === $subcategory);
        }

        if ($filters['provider_id']) {
            $providedVariants = DB::table('inventario_ingreso_items')
                ->join('inventario_ingresos', 'inventario_ingresos.id', '=', 'inventario_ingreso_items.ingreso_id')
                ->where('inventario_ingresos.proveedor_id', $filters['provider_id'])
                ->whereNull('inventario_ingresos.reversado_en')
                ->pluck('inventario_ingreso_items.variante_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
            $balances = $balances->filter(fn (InventarioVariante $variant) => isset($providedVariants[$variant->id]));
        }

        if ($filters['stock_status']) {
            $balances = $balances->filter(function (InventarioVariante $variant) use ($filters) {
                $minimum = (float) ($variant->stock_minimo ?? $variant->producto->stock_minimo);
                $actual = (float) $variant->stock_actual;

                return match ($filters['stock_status']) {
                    'critico' => $minimum > 0 && $actual <= $minimum,
                    'con_stock' => $actual > 0,
                    'sin_stock' => $actual <= 0,
                    'sobre_minimo' => $actual > $minimum,
                    default => true,
                };
            });
        }

        return $balances->values();
    }

    /**
     * Resume la operación desde la fecha de inicio acordada para el tablero.
     * El saldo actual conserva todo el kardex, pero los gráficos no mezclan la
     * carga histórica previa con la operación que Bodega comienza a controlar.
     *
     * @param array{search: string, stock_status: ?string, category: ?string, subcategory: ?string, provider_id: ?int, applied: bool} $filters
     * @param array{from: Carbon, to: Carbon, has_data_range: bool} $period
     * @return array{start: Carbon, end: Carbon, entries: float, exits: float, net: float, movements: int, active_days: int, catalog_total: int, catalog_active: int, catalog_inactive: int, daily: array<int, array{date: string, entries: float, exits: float, net: float, movements: int}>, types: array<int, array{code: string, label: string, entries: float, exits: float, net: float}>}
     */
    private function summaryAnalytics(?int $selectedLocation, array $filters, Collection $balances, array $period): array
    {
        $start = $period['from']->copy();
        $end = $period['to']->copy();
        $variantIds = $balances->pluck('id')->filter()->values()->all();

        $query = InventarioMovimiento::query()
            ->when($selectedLocation, fn (Builder $query) => $query->where('ubicacion_id', $selectedLocation))
            ->when($filters['applied'], fn (Builder $query) => $query->whereIn('variante_id', $variantIds));
        $this->applySummaryOperationalPeriod($query, $period);

        $dailyRows = (clone $query)
            ->selectRaw('DATE(ocurrido_en) as fecha')
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN ABS(cantidad) ELSE 0 END) as salidas')
            ->selectRaw('SUM(cantidad) as neto')
            ->selectRaw('COUNT(*) as movimientos')
            ->groupBy(DB::raw('DATE(ocurrido_en)'))
            ->orderBy('fecha')
            ->get()
            ->keyBy('fecha');

        $daily = [];
        if ($period['has_data_range']) {
            for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
                $row = $dailyRows->get($date->toDateString());
                $daily[] = [
                    'date' => $date->toDateString(),
                    'entries' => (float) ($row->entradas ?? 0),
                    'exits' => (float) ($row->salidas ?? 0),
                    'net' => (float) ($row->neto ?? 0),
                    'movements' => (int) ($row->movimientos ?? 0),
                ];
            }
        }

        $typeRows = (clone $query)
            ->select('tipo')
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN ABS(cantidad) ELSE 0 END) as salidas')
            ->selectRaw('SUM(cantidad) as neto')
            ->groupBy('tipo')
            ->orderByRaw('SUM(ABS(cantidad)) DESC')
            ->limit(6)
            ->get();

        $types = $typeRows->map(fn ($row) => [
            'code' => (string) $row->tipo,
            'label' => InventarioMovimiento::TIPOS[$row->tipo] ?? Str::headline((string) $row->tipo),
            'entries' => (float) $row->entradas,
            'exits' => (float) $row->salidas,
            'net' => (float) $row->neto,
        ])->values()->all();

        $catalogStatus = InventarioVariante::query()
            ->join('inventario_productos as productos_catalogo', 'productos_catalogo.id', '=', 'inventario_variantes.producto_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN inventario_variantes.activo = 1 AND productos_catalogo.activo = 1 THEN 1 ELSE 0 END) as activos')
            ->first();
        $catalogTotal = (int) ($catalogStatus?->total ?? 0);
        $catalogActive = (int) ($catalogStatus?->activos ?? 0);

        return [
            'start' => $start,
            'end' => $end,
            'entries' => (float) collect($daily)->sum('entries'),
            'exits' => (float) collect($daily)->sum('exits'),
            'net' => (float) collect($daily)->sum('net'),
            'movements' => (int) collect($daily)->sum('movements'),
            'active_days' => collect($daily)->filter(fn (array $day) => $day['movements'] > 0)->count(),
            'catalog_total' => $catalogTotal,
            'catalog_active' => $catalogActive,
            'catalog_inactive' => max(0, $catalogTotal - $catalogActive),
            'daily' => $daily,
            'types' => $types,
        ];
    }

    private function inventoryReportingStart(): Carbon
    {
        $today = now()->startOfDay();

        if (! Schema::hasTable('configuraciones')) {
            return $today;
        }

        $value = trim((string) Configuracion::get('inventario_resumen_trazabilidad_desde', ''));
        $configuredStart = $this->kizeoDateInput($value);

        return $configuredStart && $configuredStart->lte($today)
            ? $configuredStart
            : $today;
    }

    private function operationalCoordinatorData(Request $request, ?InventarioCoordinador $coordinator = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'rut' => ['nullable', 'string', 'max:30', Rule::unique('inventario_coordinadores', 'rut')->ignore($coordinator?->id)],
            'cargo' => ['nullable', 'string', 'max:180'],
            'correo' => ['nullable', 'email', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'jefe_operaciones' => ['nullable', 'string', 'max:200'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $data['nombre'] = trim($data['nombre']);
        $data['nombre_normalizado'] = $this->normalizeOperationalMasterName($data['nombre']);

        if (InventarioCoordinador::query()
            ->where('nombre_normalizado', $data['nombre_normalizado'])
            ->when($coordinator, fn ($query) => $query->whereKeyNot($coordinator->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un coordinador con este nombre.',
            ]);
        }

        foreach (['rut', 'cargo', 'correo', 'telefono', 'jefe_operaciones'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }
        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    private function operationalCostCenterData(Request $request, ?InventarioCentroCosto $costCenter = null): array
    {
        $data = $request->validate([
            'numero_maestro' => ['nullable', 'integer', 'min:0', Rule::unique('inventario_centros_costo', 'numero_maestro')->ignore($costCenter?->id)],
            'nombre' => ['required', 'string', 'max:220'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'comuna' => ['nullable', 'string', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'jefe_operaciones' => ['nullable', 'string', 'max:200'],
            'coordinador_id' => ['nullable', 'exists:inventario_coordinadores,id'],
            'coordinador_nombre_origen' => ['nullable', 'string', 'max:200'],
            'cargo_contacto' => ['nullable', 'string', 'max:180'],
            'correo_contacto' => ['nullable', 'email', 'max:180'],
            'telefono_contacto' => ['nullable', 'string', 'max:50'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $data['nombre'] = trim($data['nombre']);
        $data['nombre_normalizado'] = $this->normalizeOperationalMasterName($data['nombre']);

        if (InventarioCentroCosto::query()
            ->where('nombre_normalizado', $data['nombre_normalizado'])
            ->when($costCenter, fn ($query) => $query->whereKeyNot($costCenter->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un centro de costo con este nombre.',
            ]);
        }

        foreach (['tipo', 'comuna', 'direccion', 'jefe_operaciones', 'coordinador_nombre_origen', 'cargo_contacto', 'correo_contacto', 'telefono_contacto'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }
        if ($data['coordinador_id'] ?? null) {
            $data['coordinador_nombre_origen'] = InventarioCoordinador::query()->find($data['coordinador_id'])?->nombre;
        }
        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    private function normalizeOperationalMasterName(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function providerData(Request $request, ?InventarioProveedor $provider = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:180'],
            'rut' => ['nullable', 'string', 'max:30', Rule::unique('inventario_proveedores', 'rut')->ignore($provider?->id)],
            'contacto' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    private function catalogEditorQuery(Request $request, int $productId, ?int $locationId = null): array
    {
        $catalogQuery = [
            'vista' => 'catalogo',
            'producto_editar' => $productId,
        ];

        if ($locationId ?? $request->integer('ubicacion_id')) {
            $catalogQuery['ubicacion_id'] = $locationId ?? $request->integer('ubicacion_id');
        }

        if ($request->filled('productos_pagina')) {
            $catalogQuery['productos_pagina'] = max(1, $request->integer('productos_pagina'));
        }

        if ($request->filled('producto_buscar')) {
            $catalogQuery['producto_buscar'] = trim((string) $request->input('producto_buscar'));
        }

        if (in_array($request->input('producto_estado'), ['activos', 'inactivos'], true)) {
            $catalogQuery['producto_estado'] = $request->input('producto_estado');
        }

        return $catalogQuery;
    }

    private function productData(Request $request, bool $withCode): array
    {
        $rules = [
            'nombre' => ['required', 'string', 'max:220'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'categoria' => ['nullable', 'string', 'max:120'],
            'subcategoria' => ['nullable', 'string', 'max:120'],
            'unidad_medida' => ['nullable', 'string', 'max:30'],
            'stock_minimo' => ['nullable', 'numeric', 'gte:0'],
            'tallas' => ['nullable', 'string', 'max:500'],
            'activo' => ['nullable', 'boolean'],
        ];
        if ($withCode) {
            $rules['codigo'] = ['nullable', 'string', 'max:80'];
        }
        $data = $request->validate($rules);
        $data['categoria'] = trim((string) ($data['categoria'] ?? '')) ?: null;
        $data['subcategoria'] = trim((string) ($data['subcategoria'] ?? '')) ?: null;

        if ($data['subcategoria'] && ! $data['categoria']) {
            throw ValidationException::withMessages([
                'subcategoria' => 'Selecciona primero una categoría.',
            ]);
        }

        if ($data['subcategoria'] && ! InventarioProducto::query()
            ->where('categoria', $data['categoria'])
            ->where('subcategoria', $data['subcategoria'])
            ->exists()) {
            throw ValidationException::withMessages([
                'subcategoria' => 'La subcategoría seleccionada no pertenece a la categoría indicada.',
            ]);
        }

        $data['activo'] = $request->boolean('activo');

        return $data;
    }
}
