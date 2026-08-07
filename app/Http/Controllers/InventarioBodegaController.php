<?php

namespace App\Http\Controllers;

use App\Models\EntregaBodega;
use App\Models\InventarioConteo;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioMovimiento;
use App\Models\InventarioProducto;
use App\Models\InventarioProveedor;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Services\InventarioStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventarioBodegaController extends Controller
{
    public function __construct(private readonly InventarioStockService $stock)
    {
    }

    public function index(Request $request)
    {
        $view = in_array($request->input('vista'), ['resumen', 'ingresos', 'movimientos', 'conteos', 'kizeo', 'catalogo'], true)
            ? $request->input('vista')
            : 'resumen';
        $selectedLocation = $request->integer('ubicacion_id') ?: null;
        $search = trim((string) $request->input('buscar'));
        $balances = $this->stock->balances($selectedLocation);
        if ($search !== '') {
            $needle = Str::lower($search);
            $balances = $balances->filter(fn (InventarioVariante $variant) => str_contains(Str::lower($variant->producto->nombre . ' ' . $variant->producto->codigo . ' ' . $variant->talla), $needle));
        }

        $critical = $balances->filter(function (InventarioVariante $variant) {
            $minimum = $variant->stock_minimo ?? $variant->producto->stock_minimo;

            return (float) $minimum > 0 && (float) $variant->stock_actual <= (float) $minimum;
        })->values();

        $movements = InventarioMovimiento::query()
            ->with(['producto', 'variante', 'ubicacion'])
            ->when($selectedLocation, fn ($query) => $query->where('ubicacion_id', $selectedLocation))
            ->latest('ocurrido_en')
            ->limit(20)
            ->get();

        $productSearch = trim((string) $request->input('producto_buscar'));
        $products = InventarioProducto::query()
            ->with('variantes')
            ->when($productSearch !== '', fn ($query) => $query->where(function ($products) use ($productSearch) {
                $products->where('nombre', 'like', '%' . $productSearch . '%')
                    ->orWhere('codigo', 'like', '%' . $productSearch . '%')
                    ->orWhere('categoria', 'like', '%' . $productSearch . '%');
            }))
            ->orderBy('nombre')
            ->paginate(30, ['*'], 'productos_pagina')
            ->withQueryString();

        $variantOptions = InventarioVariante::query()
            ->with('producto')
            ->where('activo', true)
            ->whereHas('producto', fn ($query) => $query->where('activo', true))
            ->orderBy('talla')
            ->get();

        $kizeoDeliveries = $view === 'kizeo'
            ? EntregaBodega::query()
                ->with([
                    'items',
                    'inventarioAplicacion.ubicacion',
                    'inventarioAplicacion.lineas.variante.producto',
                ])
                ->orderByDesc('fecha_pedido')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
            : collect();
        $kizeoSuggestions = [];
        foreach ($kizeoDeliveries as $delivery) {
            $kizeoSuggestions[$delivery->id] = $this->stock->suggestedKizeoVariants($delivery, $variantOptions);
        }
        $kizeoApplications = InventarioEntregaKizeoAplicacion::query()
            ->with('entrega')
            ->get();
        $kizeoStats = [
            'pending' => EntregaBodega::query()->whereDoesntHave('inventarioAplicacion')->count(),
            'applied' => $kizeoApplications->where('estado', 'APLICADA')->count(),
            'review' => $kizeoApplications->filter(fn (InventarioEntregaKizeoAplicacion $application) => $application->estado === 'APLICADA'
                && $application->entrega?->kizeo_updated_at
                && (! $application->fuente_actualizada_en || $application->entrega->kizeo_updated_at->gt($application->fuente_actualizada_en)))->count(),
        ];

        return view('inventario_bodega.index', [
            'vista' => $view,
            'selectedLocation' => $selectedLocation,
            'search' => $search,
            'productSearch' => $productSearch,
            'locations' => InventarioUbicacion::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            'activeLocations' => InventarioUbicacion::query()->where('activo', true)->orderBy('nombre')->get(),
            'providers' => InventarioProveedor::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            'variantOptions' => $variantOptions,
            'balances' => $balances,
            'critical' => $critical,
            'movements' => $movements,
            'products' => $products,
            'ingresos' => \App\Models\InventarioIngreso::query()->with(['ubicacion', 'proveedor', 'items'])->latest('fecha_recepcion')->latest('id')->limit(15)->get(),
            'conteos' => InventarioConteo::query()->with('ubicacion')->withCount('lineas')->latest('fecha_corte')->latest('id')->limit(15)->get(),
            'kizeoDeliveries' => $kizeoDeliveries,
            'kizeoSuggestions' => $kizeoSuggestions,
            'kizeoStats' => $kizeoStats,
            'canCreate' => $request->user()->tieneAcceso('inventario_bodega', 'puede_crear'),
            'canEdit' => $request->user()->tieneAcceso('inventario_bodega', 'puede_editar'),
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
        $data = $this->productData($request, false);
        $this->stock->updateProduct($producto, $data);

        return back()->with('success', 'Producto actualizado. Las variantes existentes y su historial se conservan.');
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'proveedor_id' => ['nullable', 'exists:inventario_proveedores,id'],
            'tipo_documento' => ['required', Rule::in(array_keys(\App\Models\InventarioIngreso::TIPOS_DOCUMENTO))],
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

    public function storeMovement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['ENTREGA_EPP', 'DESPACHO_CENTRO', 'TRASLADO', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'STOCK_INICIAL'])],
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'ubicacion_destino_id' => ['nullable', 'required_if:tipo,TRASLADO', 'exists:inventario_ubicaciones,id'],
            'variante_id' => ['required', 'exists:inventario_variantes,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'ocurrido_en' => ['required', 'date'],
            'destinatario_nombre' => ['nullable', 'string', 'max:200'],
            'destinatario_rut' => ['nullable', 'string', 'max:30'],
            'centro_costo' => ['nullable', 'string', 'max:180'],
            'documento_tipo' => ['nullable', 'string', 'max:40'],
            'documento_numero' => ['nullable', 'string', 'max:100'],
            'costo_unitario' => ['nullable', 'numeric', 'gte:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $this->stock->registerManualMovement($data, $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'movimientos'])
            ->with('success', 'Movimiento registrado. El saldo fue actualizado sin editar movimientos anteriores.');
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
        $conteo->setRelation('lineas', $conteo->lineas->sortBy(fn ($line) => $line->producto->nombre . ' ' . $line->variante->talla));

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

    public function applyKizeoDelivery(Request $request, EntregaBodega $entrega): RedirectResponse
    {
        $data = $request->validate([
            'ubicacion_id' => ['required', 'exists:inventario_ubicaciones,id'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.variante_id' => ['required', 'exists:inventario_variantes,id'],
        ]);
        $application = $this->stock->applyKizeoDelivery($entrega->load('items'), (int) $data['ubicacion_id'], $data['lineas'], $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'kizeo'])
            ->with('success', "Entrega Kizeo aplicada desde {$application->ubicacion->nombre}. El descuento quedo vinculado al comprobante original.");
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
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);
        $result = $this->stock->importProducts($request->file('archivo'), $request->user());

        return redirect()->route('inventario-bodega.index', ['vista' => 'catalogo'])
            ->with('success', "Importacion finalizada: {$result['created']} productos creados, {$result['updated']} actualizados, {$result['variantsCreated']} variantes creadas y {$result['skipped']} filas omitidas. No se registraron movimientos de stock.");
    }

    public function productTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Stock_Critico'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D0B64']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1');
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'B' ? 34 : 20);
        }

        $path = storage_path('app/plantilla_catalogo_inventario_' . now()->format('YmdHis') . '.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'plantilla_catalogo_inventario.xlsx')->deleteFileAfterSend(true);
    }

    public function exportBalances(Request $request)
    {
        $locationId = $request->integer('ubicacion_id') ?: null;
        $balances = $this->stock->balances($locationId);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock actual');
        $headers = ['Codigo producto', 'Producto', 'Talla o variante', 'Tipo', 'Categoria', 'Stock minimo', 'Stock actual'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2D0B64']],
        ]);
        $row = 2;
        foreach ($balances as $variant) {
            $sheet->fromArray([
                $variant->producto->codigo,
                $variant->producto->nombre,
                $variant->talla,
                $variant->producto->tipo,
                $variant->producto->categoria,
                $variant->stock_minimo ?? $variant->producto->stock_minimo,
                $variant->stock_actual,
            ], null, "A{$row}");
            $row++;
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G1');
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['B', 'E'], true) ? 30 : 18);
        }

        $path = storage_path('app/stock_inventario_' . now()->format('Ymd_His') . '.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'stock_inventario.xlsx')->deleteFileAfterSend(true);
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
        $data['activo'] = $request->boolean('activo');

        return $data;
    }
}
