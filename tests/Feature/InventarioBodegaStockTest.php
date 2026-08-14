<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
use App\Http\Controllers\InventarioBodegaController;
use App\Models\InventarioConteo;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioCoordinador;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioMovimiento;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Models\Rol;
use App\Models\User;
use App\Services\InventarioStockService;
use App\Services\InventarioOperationalMasterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InventarioBodegaStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach ([
            'inventario_entrega_kizeo_lineas', 'inventario_entrega_kizeo_aplicaciones',
            'inventario_conteo_lineas', 'inventario_conteos', 'inventario_movimientos',
            'inventario_historial_costos', 'inventario_ingreso_items', 'inventario_ingresos', 'inventario_variantes',
            'inventario_productos', 'inventario_proveedores', 'inventario_ubicaciones',
            'entrega_bodega_items', 'entregas_bodega',
            'rol_modulo', 'modulos', 'users', 'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('email', 200)->unique();
            $table->foreignId('rol_id')->constrained('roles');
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('icono')->nullable();
            $table->string('grupo')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('rol_modulo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->boolean('puede_ver')->default(false);
            $table->boolean('puede_crear')->default(false);
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_eliminar')->default(false);
            $table->timestamps();
        });

        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_07_120000_create_inventario_bodega_tables.php';
        $migration->up();
        $receiptReversalMigration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_13_150000_add_reversal_fields_to_inventario_ingresos.php';
        $receiptReversalMigration->up();
        $operationalMastersMigration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_13_190000_add_operational_masters_to_inventario.php';
        $operationalMastersMigration->up();
        $referenceCostMigration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_14_150000_add_reference_cost_history_to_inventario.php';
        $referenceCostMigration->up();

        Schema::create('entregas_bodega', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 32)->unique();
            $table->unsignedInteger('kizeo_record_number')->nullable();
            $table->timestamp('kizeo_created_at')->nullable();
            $table->timestamp('kizeo_updated_at')->nullable();
            $table->string('registrado_por', 200)->nullable();
            $table->string('centro', 180)->nullable();
            $table->string('rut', 30)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->date('fecha_pedido')->nullable();
            $table->unsignedSmallInteger('lineas_count')->default(0);
            $table->unsignedInteger('unidades_total')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('entrega_bodega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_bodega_id')->constrained('entregas_bodega')->cascadeOnDelete();
            $table->unsignedSmallInteger('linea');
            $table->string('articulo', 200)->nullable();
            $table->string('talla', 80)->nullable();
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['entrega_bodega_id', 'linea']);
        });

        $kizeoMigration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_07_123000_create_inventario_entrega_kizeo_tables.php';
        $kizeoMigration->up();
    }

    public function test_receipt_and_transfer_update_stock_without_losing_traceability(): void
    {
        [$user, $origin, $destination, $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);

        $receipt = $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-100',
            'fecha_documento' => '2026-08-01',
            'fecha_recepcion' => '2026-08-01',
            'observacion' => 'Ingreso de prueba',
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 10,
            'costo_unitario' => 1500,
        ]], $user);

        $service->registerManualMovement([
            'tipo' => 'TRASLADO',
            'ubicacion_id' => $origin->id,
            'ubicacion_destino_id' => $destination->id,
            'variante_id' => $variant->id,
            'cantidad' => 4,
            'ocurrido_en' => '2026-08-02 09:00:00',
            'destinatario_nombre' => null,
            'destinatario_rut' => null,
            'centro_costo' => null,
            'documento_tipo' => 'GUIA',
            'documento_numero' => 'G-20',
            'costo_unitario' => null,
            'observacion' => 'Traslado controlado',
        ], $user);

        $this->assertSame(6.0, $service->stockActual($origin->id, $variant->id));
        $this->assertSame(4.0, $service->stockActual($destination->id, $variant->id));
        $this->assertDatabaseHas('inventario_ingresos', ['id' => $receipt->id, 'numero_documento' => 'F-100']);
        $this->assertDatabaseCount('inventario_movimientos', 3);

        $this->expectException(ValidationException::class);
        $service->registerManualMovement([
            'tipo' => 'ENTREGA_EPP',
            'ubicacion_id' => $destination->id,
            'variante_id' => $variant->id,
            'cantidad' => 5,
            'ocurrido_en' => '2026-08-02 10:00:00',
            'destinatario_nombre' => 'Persona de prueba',
            'destinatario_rut' => null,
            'centro_costo' => null,
            'documento_tipo' => null,
            'documento_numero' => null,
            'costo_unitario' => null,
            'observacion' => null,
        ], $user);
    }

    public function test_approved_stocktake_registers_only_the_adjustment_difference(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-1',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-01',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 8, 'costo_unitario' => null]], $user);

        $conteo = $service->createStocktake([
            'ubicacion_id' => $origin->id,
            'fecha_corte' => '2026-08-05',
            'observacion' => 'Conteo de prueba',
            'incluir_sin_stock' => false,
        ], $user);
        $line = $conteo->lineas()->firstOrFail();
        $service->saveStocktake($conteo, [$line->id => ['cantidad_fisica' => 6, 'observacion' => 'Dos unidades menos']]);
        $conteo = InventarioConteo::query()->with('lineas')->findOrFail($conteo->id);
        $service->approveStocktake($conteo, $user);

        $this->assertSame('APROBADO', $conteo->fresh()->estado);
        $this->assertSame(6.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'AJUSTE_NEGATIVO',
            'referencia_id' => $conteo->id,
            'cantidad' => -2,
        ]);
    }

    public function test_kizeo_delivery_is_applied_once_and_reversed_without_erasing_history(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-55',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-01',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 10, 'costo_unitario' => null]], $user);

        $delivery = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-delivery-1',
            'kizeo_record_number' => 501,
            'kizeo_created_at' => '2026-08-02 09:00:00',
            'kizeo_updated_at' => '2026-08-02 09:15:00',
            'centro' => 'Centro prueba',
            'rut' => '11111111-1',
            'nombre' => 'Trabajador de prueba',
            'fecha_pedido' => '2026-08-02',
            'lineas_count' => 1,
            'unidades_total' => 2,
        ]);
        $item = $delivery->items()->create([
            'linea' => 1,
            'articulo' => 'Casco de seguridad',
            'talla' => 'M',
            'cantidad' => 2,
        ]);

        $suggestions = $service->suggestedKizeoVariants($delivery->load('items'), collect([$variant->load('producto')]));
        $this->assertSame($variant->id, $suggestions[$item->id]);

        $application = $service->applyKizeoDelivery($delivery->load('items'), $origin->id, [
            $item->id => ['variante_id' => $variant->id],
        ], $user);

        $this->assertSame('APLICADA', $application->estado);
        $this->assertSame(8.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', ['tipo' => 'ENTREGA_EPP', 'origen' => 'KIZEO_EPP', 'cantidad' => -2]);

        $this->expectException(ValidationException::class);
        $service->applyKizeoDelivery($delivery->load('items'), $origin->id, [
            $item->id => ['variante_id' => $variant->id],
        ], $user);
    }

    public function test_kizeo_delivery_reversal_replenishes_stock_with_a_new_movement(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-90',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-01',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 5, 'costo_unitario' => null]], $user);
        $delivery = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-delivery-2',
            'nombre' => 'Trabajador de prueba',
            'fecha_pedido' => '2026-08-02',
        ]);
        $item = $delivery->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 2]);
        $application = $service->applyKizeoDelivery($delivery->load('items'), $origin->id, [$item->id => ['variante_id' => $variant->id]], $user);

        $service->reverseKizeoDelivery(InventarioEntregaKizeoAplicacion::query()->findOrFail($application->id), 'Entrega anulada antes de salida.', $user);

        $this->assertSame('REVERSADA', $application->fresh()->estado);
        $this->assertSame(5.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', ['tipo' => 'REVERSO', 'origen' => 'REVERSO_KIZEO_EPP', 'cantidad' => 2]);
    }

    public function test_epp_roster_import_starts_at_zero_and_can_be_loaded_later(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'epp-roster-') . '.xlsx';
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([
            ['Tipo', 'Categoria', 'Sub Categoria', 'Item', 'Formato'],
            ['Epp', 'Botas de Agua', 'Botas RAC', 'Botas de agua Negra RAC T-39', 'Unidad'],
            ['Epp', 'Botas de Agua', 'Botas RAC', 'Botas de agua Negra RAC T-40', 'Unidad'],
            ['Epp', 'Guantes', 'Proteccion', 'Guante nitrilo T-NA', 'Par'],
        ]);
        (new Xlsx($sheet->getParent()))->save($path);

        try {
            $file = new \Illuminate\Http\UploadedFile($path, 'Epp.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $result = app(InventarioStockService::class)->importProducts($file, $user);
            $repeat = app(InventarioStockService::class)->importProducts($file, $user);
        } finally {
            @unlink($path);
        }

        $boots = \App\Models\InventarioProducto::query()->where('nombre', 'Botas de agua Negra RAC')->firstOrFail();
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(3, $result['variantsCreated']);
        $this->assertSame(0, $repeat['created']);
        $this->assertSame(2, $repeat['updated']);
        $this->assertSame(0, $repeat['variantsCreated']);
        $this->assertSame(['39', '40'], $boots->variantes()->pluck('talla')->all());
        $this->assertDatabaseHas('inventario_productos', ['nombre' => 'Guante nitrilo', 'subcategoria' => 'Proteccion']);
        $service = app(InventarioStockService::class);
        $variant = $boots->variantes()->firstOrFail();
        $this->assertSame(0.0, $service->stockActual($origin->id, $variant->id));

        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'OTRO',
            'numero_documento' => 'CONTEO-01',
            'fecha_documento' => '2026-08-10',
            'fecha_recepcion' => '2026-08-10',
            'observacion' => 'Carga posterior al catalogo.',
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 12,
            'costo_unitario' => null,
        ]], $user);

        $this->assertSame(12.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'INGRESO_COMPRA',
            'origen' => 'INGRESO_BODEGA',
            'variante_id' => $variant->id,
            'cantidad' => 12,
        ]);
    }

    public function test_summary_filters_balances_kpis_and_recent_movements_by_catalog_supplier_and_stock_status(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $product = $service->createProduct([
            'codigo' => 'LENTE-SEG',
            'nombre' => 'Lente de seguridad',
            'tipo' => 'EPP',
            'categoria' => 'Proteccion visual',
            'subcategoria' => 'Lentes',
            'unidad_medida' => 'Unidad',
            'stock_minimo' => 5,
            'tallas' => 'ESTANDAR',
            'activo' => true,
        ], $user);
        $variant = $product->variantes()->firstOrFail();
        $provider = \App\Models\InventarioProveedor::create(['nombre' => 'Proveedor de lentes', 'activo' => true]);
        $availableProvider = \App\Models\InventarioProveedor::create(['nombre' => 'Proveedor disponible sin ingresos', 'activo' => true]);
        $inactiveProvider = \App\Models\InventarioProveedor::create(['nombre' => 'Proveedor inactivo', 'activo' => false]);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => $provider->id,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-RESUMEN-1',
            'fecha_documento' => '2026-08-13',
            'fecha_recepcion' => '2026-08-13',
            'observacion' => null,
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 2,
            'costo_unitario' => null,
        ]], $user);

        $request = Request::create('/inventario-bodega', 'GET', [
            'vista' => 'resumen',
            'categoria' => 'Proteccion visual',
            'subcategoria' => 'Lentes',
            'proveedor_id' => $provider->id,
            'estado_stock' => 'critico',
        ]);
        $request->setUserResolver(fn () => $user);
        $view = (new InventarioBodegaController($service))->index($request);
        $data = $view->getData();

        $this->assertTrue($data['summaryFilters']['applied']);
        $this->assertSame('critico', $data['summaryFilters']['stock_status']);
        $this->assertCount(1, $data['balances']);
        $this->assertSame($variant->id, $data['balances']->first()->id);
        $this->assertCount(1, $data['critical']);
        $this->assertCount(1, $data['movements']);
        $this->assertSame($variant->id, $data['movements']->first()->variante_id);
        $this->assertTrue($data['summaryProviders']->contains('id', $provider->id));
        $this->assertTrue($data['summaryProviders']->contains('id', $availableProvider->id));
        $this->assertFalse($data['summaryProviders']->contains('id', $inactiveProvider->id));

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'resumen']))
            ->assertOk()
            ->assertSee('Proveedor de lentes')
            ->assertSee('Proveedor disponible sin ingresos')
            ->assertDontSee('Proveedor inactivo');

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'ingresos']))
            ->assertOk()
            ->assertSee('Proveedor de lentes')
            ->assertSee('Proveedor disponible sin ingresos')
            ->assertDontSee('Proveedor inactivo');

        $export = (new InventarioBodegaController($service))->exportBalances(Request::create('/inventario-bodega/exportar', 'GET', [
            'categoria' => 'Proteccion visual',
            'subcategoria' => 'Lentes',
            'proveedor_id' => $provider->id,
            'estado_stock' => 'critico',
        ]));
        $path = $export->getFile()->getPathname();
        try {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->rangeToArray('A1:K3', null, true, true, false);
        } finally {
            @unlink($path);
        }
        $this->assertSame('LENTE-SEG', $rows[1][0]);
        $this->assertNull($rows[2][0]);
    }

    public function test_catalog_import_sets_stock_by_location_without_confusing_it_with_stock_minimum(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'catalog-stock-') . '.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Stock_Critico', 'Ubicacion_Codigo', 'Stock_Inicial'];
        $row = ['PARKA-AZUL', 'Parka termica azul', 'EPP', 'Ropa', 'Parkas', 'Unidad', 'M', 5, $origin->codigo, 30];

        try {
            $sheet = (new Spreadsheet())->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $file = new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $service = app(InventarioStockService::class);
            $result = $service->importProducts($file, $user);
            $repeat = $service->importProducts($file, $user);

            $row[9] = 18;
            $sheet = (new Spreadsheet())->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $adjusted = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);
        } finally {
            @unlink($path);
        }

        $product = \App\Models\InventarioProducto::query()->where('codigo', 'PARKA-AZUL')->firstOrFail();
        $variant = $product->variantes()->where('talla', 'M')->firstOrFail();

        $this->assertSame(1, $result['stocksSet']);
        $this->assertSame(0, $repeat['stocksSet']);
        $this->assertSame(1, $adjusted['stocksSet']);
        $this->assertSame(5.0, (float) $product->stock_minimo);
        $this->assertSame(18.0, app(InventarioStockService::class)->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'STOCK_INICIAL',
            'origen' => 'IMPORTACION_CATALOGO',
            'variante_id' => $variant->id,
            'cantidad' => 30,
        ]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'AJUSTE_NEGATIVO',
            'origen' => 'IMPORTACION_CATALOGO',
            'variante_id' => $variant->id,
            'cantidad' => -12,
        ]);
    }

    public function test_catalog_import_preserves_reference_cost_history_by_size(): void
    {
        [$user] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'catalog-cost-') . '.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia'];
        $row = ['GUANTE-COSTO', 'Guante con costo', 'EPP', 'Guantes', 'Nitrilo', 'Unidad', 'M', 1250];

        try {
            $sheet = (new Spreadsheet())->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $service = app(InventarioStockService::class);
            $first = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);

            $row[7] = 1475;
            $sheet = (new Spreadsheet())->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $second = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);

            $row[7] = 0;
            $sheet = (new Spreadsheet())->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $unknown = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);
        } finally {
            @unlink($path);
        }

        $variant = InventarioVariante::query()
            ->whereHas('producto', fn ($products) => $products->where('codigo', 'GUANTE-COSTO'))
            ->where('talla', 'M')
            ->firstOrFail();

        $this->assertSame(1, $first['costsUpdated']);
        $this->assertSame(1, $second['costsUpdated']);
        $this->assertSame(0, $unknown['costsUpdated']);
        $this->assertSame(1475.0, (float) $variant->costo_referencia);
        $this->assertDatabaseCount('inventario_historial_costos', 2);
        $this->assertDatabaseHas('inventario_historial_costos', [
            'variante_id' => $variant->id,
            'costo_unitario' => 1475,
            'origen' => 'IMPORTACION_CATALOGO',
        ]);
    }

    public function test_catalog_import_keeps_excel_prices_with_thousands_separator(): void
    {
        [$user] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'catalog-price-format-') . '.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia'];
        $row = ['BOTIN-PRECIO', 'Botín con precio', 'EPP', 'Calzado', 'Botines', 'Unidad', '40', 41590];

        try {
            $sheet = (new Spreadsheet())->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            $sheet->getStyle('H2')->getNumberFormat()->setFormatCode('#,##0');
            $this->assertSame('41,590', $sheet->toArray(null, true, true, false)[1][7]);
            (new Xlsx($sheet->getParent()))->save($path);

            app(InventarioStockService::class)->importProducts(
                new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                $user,
            );
        } finally {
            @unlink($path);
        }

        $variant = InventarioVariante::query()
            ->whereHas('producto', fn ($products) => $products->where('codigo', 'BOTIN-PRECIO'))
            ->where('talla', '40')
            ->firstOrFail();

        $this->assertSame(41590.0, (float) $variant->costo_referencia);
        $this->assertDatabaseHas('inventario_historial_costos', [
            'variante_id' => $variant->id,
            'costo_unitario' => 41590,
            'origen' => 'IMPORTACION_CATALOGO',
        ]);
    }

    public function test_a_receipt_is_annulled_with_inverse_movements_and_audit_data(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $receipt = $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-ANULAR-1',
            'fecha_documento' => '2026-08-11',
            'fecha_recepcion' => '2026-08-11',
            'observacion' => 'Ingreso de prueba que no debía existir.',
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 6,
            'costo_unitario' => 1200,
        ]], $user);

        $service->reverseReceipt($receipt, 'Ingreso de prueba registrado por error.', $user);

        $this->assertSame(0.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_ingresos', [
            'id' => $receipt->id,
            'reversado_por' => $user->id,
            'motivo_reversion' => 'Ingreso de prueba registrado por error.',
        ]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'REVERSO',
            'origen' => 'REVERSO_INGRESO_BODEGA',
            'referencia_id' => $receipt->id,
            'cantidad' => -6,
        ]);

        $this->expectException(ValidationException::class);
        $service->reverseReceipt($receipt->fresh(), 'No se puede anular dos veces.', $user);
    }

    public function test_stock_can_be_set_for_one_size_without_touching_other_sizes(): void
    {
        [$user, $origin, , $medium] = $this->inventoryContext();
        $large = InventarioVariante::create([
            'producto_id' => $medium->producto_id,
            'codigo' => 'CASCO-SEGURIDAD-L',
            'talla' => 'L',
            'activo' => true,
        ]);
        $service = app(InventarioStockService::class);

        $service->setVariantStock([
            'ubicacion_id' => $origin->id,
            'variante_id' => $medium->id,
            'stock_final' => 7,
            'observacion' => 'Conteo físico de talla M.',
        ], $user);

        $this->assertSame(7.0, $service->stockActual($origin->id, $medium->id));
        $this->assertSame(0.0, $service->stockActual($origin->id, $large->id));
        $this->assertDatabaseHas('inventario_movimientos', [
            'origen' => 'AJUSTE_STOCK_TALLA',
            'variante_id' => $medium->id,
            'cantidad' => 7,
        ]);
    }

    public function test_stock_adjustment_returns_to_the_same_product_and_catalog_context(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();

        $response = $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.stock-talla.store'), [
                'ubicacion_id' => $origin->id,
                'variante_id' => $variant->id,
                'stock_final' => 6,
                'observacion' => 'Conteo de talla M en la bodega.',
                'productos_pagina' => 2,
                'producto_buscar' => 'casco',
            ]);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('vista=catalogo', $location);
        $this->assertStringContainsString('ubicacion_id=' . $origin->id, $location);
        $this->assertStringContainsString('producto_editar=' . $variant->producto_id, $location);
        $this->assertStringContainsString('productos_pagina=2', $location);
        $this->assertStringContainsString('producto_buscar=casco', $location);
    }

    public function test_operational_masters_import_and_fill_a_manual_movement_from_center_and_coordinator(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'inventory-masters-') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $coordinators = $spreadsheet->getActiveSheet();
        $coordinators->setTitle('Maestro_Coordinador');
        $coordinators->fromArray([
            ['N°', 'RUT', 'Nombre Completo', 'Cargo', 'Correo', 'Tlf', 'Jefe de operaciones'],
            [1, '11111111-1', 'Andrea Torres', 'Coordinadora de Operaciones', 'andrea@saep.cl', '912345678', 'Jefa Uno'],
            [2, '22222222-2', 'Bruno Pérez', 'Supervisor', 'bruno@saep.cl', '923456789', 'Jefa Dos'],
        ]);
        $centers = $spreadsheet->createSheet();
        $centers->setTitle('Maestro_CC');
        $centers->fromArray([
            ['N', 'CENTRO DE COSTOS', 'TIPO', 'COMUNA', 'DIRECCION', 'JEFE DE OPERACIONES', 'COORDINADOR', 'CARGO', 'CORREO', 'TLF'],
            [1, 'Centro Norte', 'EST', 'Recoleta', 'Av. Norte 123', 'Jefa Uno', 'Andrea Torres', 'Coordinadora de Operaciones', 'andrea@saep.cl', '912345678'],
            [2, 'Centro Sin Maestro', 'SUB', 'Maipú', 'Av. Sur 456', 'Jefa Dos', 'Nombre no incluido', '#N/A', '#N/A', '#N/A'],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = app(InventarioOperationalMasterService::class)->import($path);
            $repeat = app(InventarioOperationalMasterService::class)->import($path);
        } finally {
            @unlink($path);
        }

        $andrea = InventarioCoordinador::query()->where('nombre', 'Andrea Torres')->firstOrFail();
        $center = InventarioCentroCosto::query()->where('nombre', 'Centro Norte')->firstOrFail();
        $unlinked = InventarioCentroCosto::query()->where('nombre', 'Centro Sin Maestro')->firstOrFail();
        $this->assertSame(2, $result['coordinadoresCreados']);
        $this->assertSame(2, $result['centrosCreados']);
        $this->assertSame(0, $repeat['coordinadoresCreados']);
        $this->assertSame(2, $repeat['coordinadoresActualizados']);
        $this->assertSame(0, $repeat['centrosCreados']);
        $this->assertSame(2, $repeat['centrosActualizados']);
        $this->assertSame($andrea->id, $center->coordinador_id);
        $this->assertNull($unlinked->coordinador_id);
        $this->assertSame('Nombre no incluido', $unlinked->coordinador_nombre_origen);

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.movimientos.store'), [
                'tipo' => 'AJUSTE_POSITIVO',
                'ubicacion_id' => $origin->id,
                'variante_id' => $variant->id,
                'cantidad' => 2,
                'ocurrido_en' => '2026-08-13 15:30:00',
                'centro_costo_id' => $center->id,
                'documento_tipo' => 'ACTA',
            ])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'movimientos']));

        $this->assertDatabaseHas('inventario_movimientos', [
            'variante_id' => $variant->id,
            'centro_costo_id' => $center->id,
            'coordinador_id' => $andrea->id,
            'centro_costo' => 'Centro Norte',
            'destinatario_nombre' => 'Andrea Torres',
            'destinatario_rut' => '11111111-1',
        ]);
    }

    public function test_operational_master_tab_lists_and_edits_centers_and_coordinators_without_deleting_history(): void
    {
        [$user] = $this->inventoryContext();
        $coordinator = InventarioCoordinador::create([
            'nombre' => 'Andrea Operaciones',
            'nombre_normalizado' => 'andrea operaciones',
            'rut' => '11111111-1',
            'cargo' => 'Coordinadora',
            'activo' => true,
        ]);
        $center = InventarioCentroCosto::create([
            'numero_maestro' => 17,
            'nombre' => 'Centro Bodega Norte',
            'nombre_normalizado' => 'centro bodega norte',
            'comuna' => 'Recoleta',
            'coordinador_id' => $coordinator->id,
            'coordinador_nombre_origen' => $coordinator->nombre,
            'activo' => true,
        ]);

        $mastersResponse = $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'maestros']));

        $mastersResponse
            ->assertOk()
            ->assertSee('Maestros operativos')
            ->assertSee('Centros de costo')
            ->assertSee('Coordinadores')
            ->assertSee('<details class="inventory-details inventory-master-details" open>', false)
            ->assertSee('<details class="inventory-details inventory-master-details">', false)
            ->assertSee('Centro Bodega Norte')
            ->assertSee('Andrea Operaciones');

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->put(route('inventario-bodega.maestros.coordinadores.update', $coordinator), [
                'nombre' => 'Andrea Operaciones Actualizada',
                'rut' => '11111111-1',
                'cargo' => 'Jefa de Operaciones',
                'correo' => 'andrea.actualizada@saep.cl',
                'activo' => 0,
            ])
            ->assertRedirect();

        $coordinator = $coordinator->fresh();
        $this->assertSame('Andrea Operaciones Actualizada', $coordinator->nombre);
        $this->assertFalse($coordinator->activo);
        $this->assertSame($coordinator->id, $center->fresh()->coordinador_id);

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->put(route('inventario-bodega.maestros.centros.update', $center), [
                'numero_maestro' => 17,
                'nombre' => 'Centro Bodega Norte Actualizado',
                'comuna' => 'Quilicura',
                'coordinador_id' => $coordinator->id,
                'activo' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventario_centros_costo', [
            'id' => $center->id,
            'nombre' => 'Centro Bodega Norte Actualizado',
            'coordinador_id' => $coordinator->id,
            'activo' => false,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.maestros.coordinadores.store'), [
                'nombre' => 'Bruno Nuevo',
                'cargo' => 'Supervisor',
                'activo' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventario_coordinadores', [
            'nombre' => 'Bruno Nuevo',
            'nombre_normalizado' => 'bruno nuevo',
            'activo' => true,
        ]);
    }

    public function test_manual_movement_accepts_a_document_type_from_the_catalog(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.movimientos.store'), [
                'tipo' => 'AJUSTE_POSITIVO',
                'ubicacion_id' => $origin->id,
                'variante_id' => $variant->id,
                'cantidad' => 2,
                'ocurrido_en' => '2026-08-13 10:30:00',
                'documento_tipo' => 'ACTA',
                'documento_numero' => 'ACTA-QA-01',
            ])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'movimientos']));

        $this->assertDatabaseHas('inventario_movimientos', [
            'variante_id' => $variant->id,
            'documento_tipo' => 'ACTA',
            'documento_numero' => 'ACTA-QA-01',
            'cantidad' => 2,
        ]);
    }

    public function test_receipt_import_groups_lines_and_updates_stock(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $variant->load('producto');
        $path = tempnam(sys_get_temp_dir(), 'receipt-import-') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Referencia_Ingreso', 'Ubicacion_Codigo', 'Proveedor', 'Proveedor_Rut', 'Tipo_Documento', 'Numero_Documento', 'Fecha_Documento', 'Fecha_Recepcion', 'Codigo_Producto', 'Talla', 'Cantidad', 'Costo_Unitario', 'Observacion'],
            ['COMPRA-AGOSTO-01', $origin->codigo, 'Proveedor importado', '76543210-1', 'FACTURA', 'F-IMPORT-1', '10/08/2026', '11/08/2026', $variant->producto->codigo, 'M', '4', 41590, 'Carga desde planilla'],
        ]);
        $sheet->getStyle('L2')->getNumberFormat()->setFormatCode('#,##0');
        (new Xlsx($spreadsheet))->save($path);

        try {
            $file = new \Illuminate\Http\UploadedFile($path, 'ingresos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $result = app(InventarioStockService::class)->importReceipts($file, $user);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['receipts']);
        $this->assertSame(1, $result['lines']);
        $this->assertSame(4.0, app(InventarioStockService::class)->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_proveedores', ['rut' => '76543210-1', 'nombre' => 'Proveedor importado']);
        $this->assertDatabaseHas('inventario_ingresos', ['numero_documento' => 'F-IMPORT-1']);
        $this->assertDatabaseHas('inventario_movimientos', ['variante_id' => $variant->id, 'costo_unitario' => 41590]);
    }

    public function test_csv_upload_validation_accepts_a_browser_plain_text_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt-import-') . '.csv';
        file_put_contents($path, "Referencia_Ingreso,Ubicacion_Codigo\nQA-1,BOD-1\n");

        try {
            $file = new UploadedFile($path, 'ingresos.csv', 'text/plain', null, true);
            $validator = Validator::make(['archivo' => $file], [
                'archivo' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
            ]);
            $fails = $validator->fails();
            $errors = $validator->errors()->toJson();
        } finally {
            @unlink($path);
        }

        $this->assertFalse($fails, $errors);
    }

    public function test_stock_export_uses_the_catalog_fields_and_current_balance(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-EXPORT-1',
            'fecha_documento' => '2026-08-12',
            'fecha_recepcion' => '2026-08-12',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 3, 'costo_unitario' => 1750]], $user);

        $variant->refresh();
        $this->assertSame(1750.0, (float) $variant->costo_referencia);
        $this->assertDatabaseHas('inventario_historial_costos', [
            'variante_id' => $variant->id,
            'costo_unitario' => 1750,
            'origen' => 'INGRESO_BODEGA',
        ]);

        $response = (new InventarioBodegaController($service))->exportBalances(Request::create('/inventario-bodega/exportar', 'GET', [
            'ubicacion_id' => $origin->id,
        ]));
        $path = $response->getFile()->getPathname();

        try {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->rangeToArray('A1:L2', null, true, true, false);
        } finally {
            @unlink($path);
        }

        $this->assertSame(['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia', 'Stock_Critico', 'Stock_Actual', 'Ubicacion', 'Estado'], $rows[0]);
        $this->assertSame(1750.0, (float) $rows[1][7]);
        $this->assertSame(3.0, (float) $rows[1][9]);
        $this->assertSame($origin->nombre, $rows[1][10]);
    }

    public function test_catalog_explains_how_to_load_stock_after_import(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/inventario_bodega/index.blade.php');

        $this->assertStringContainsString('El catálogo puede incluir stock y costo de referencia.', $view);
        $this->assertStringContainsString('Costo_Referencia', $view);
        $this->assertStringContainsString('Cargar desde compra', $view);
        $this->assertStringContainsString('Cargar desde conteo', $view);
        $this->assertStringContainsString('Desglose por talla', $view);
        $this->assertStringContainsString('product-variant-editor', $view);
        $this->assertStringContainsString('data-variants=', $view);
        $this->assertStringContainsString('inventory-variant-card', $view);
        $this->assertStringContainsString('data-product-category-select', $view);
        $this->assertStringContainsString('data-product-subcategory-select', $view);
        $this->assertStringContainsString('¿Es una compra o una carga masiva?', $view);
        $this->assertStringContainsString('Ver o anular ingresos', $view);
        $this->assertStringContainsString('InventarioMovimiento::TIPOS_DOCUMENTO', $view);
    }

    public function test_kizeo_queue_is_collapsed_and_displays_whether_stock_was_discounted(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-COLA-1',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-10',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 4, 'costo_unitario' => null]], $user);

        $pending = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-pending-ui',
            'kizeo_record_number' => 701,
            'nombre' => 'Pendiente de stock',
            'fecha_pedido' => '2026-08-10',
        ]);
        $pending->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 1]);

        $applied = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-applied-ui',
            'kizeo_record_number' => 702,
            'nombre' => 'Salida ya aplicada',
            'fecha_pedido' => '2026-08-10',
        ]);
        $item = $applied->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 1]);
        $service->applyKizeoDelivery($applied->load('items'), $origin->id, [
            $item->id => ['variante_id' => $variant->id],
        ], $user);

        $reversed = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-reversed-ui',
            'kizeo_record_number' => 703,
            'nombre' => 'Salida con stock repuesto',
            'fecha_pedido' => '2026-08-10',
        ]);
        $reversedItem = $reversed->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 1]);
        $reversedApplication = $service->applyKizeoDelivery($reversed->load('items'), $origin->id, [
            $reversedItem->id => ['variante_id' => $variant->id],
        ], $user);
        $service->reverseKizeoDelivery($reversedApplication, 'Prueba de salida revertida.', $user);

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertOk()
            ->assertSee('No descontada')
            ->assertSee('Salida descontada')
            ->assertSee('Stock repuesto')
            ->assertSee('1 item')
            ->assertSee('data-inventory-search-select', false)
            ->assertSee('Buscar por codigo, articulo o talla', false)
            ->assertSee('<details class="inventory-delivery-card">', false)
            ->assertDontSee('<details class="inventory-delivery-card" open', false);
    }

    public function test_article_selectors_and_long_inventory_lists_use_the_searchable_picker(): void
    {
        [$user] = $this->inventoryContext();

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'ingresos']))
            ->assertOk()
            ->assertSee('name="items[__INDEX__][variante_id]" class="form-select" required data-inventory-search-select', false)
            ->assertSee('function shouldUseSearchSelect(nativeSelect)', false)
            ->assertSee('return selectableOptions(nativeSelect).length > 5;', false)
            ->assertSee("root.querySelectorAll('select.form-select')", false);

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'movimientos']))
            ->assertOk()
            ->assertSee('name="variante_id" class="form-select" required data-inventory-search-select', false);
    }

    public function test_manual_movement_and_transfer_are_reversed_without_deleting_history(): void
    {
        [$user, $origin, $destination, $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);

        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-REV-1',
            'fecha_documento' => '2026-08-01',
            'fecha_recepcion' => '2026-08-01',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 10, 'costo_unitario' => null]], $user);

        $service->registerManualMovement([
            'tipo' => 'ENTREGA_EPP',
            'ubicacion_id' => $origin->id,
            'ubicacion_destino_id' => null,
            'variante_id' => $variant->id,
            'cantidad' => 3,
            'ocurrido_en' => '2026-08-02 09:00:00',
            'destinatario_nombre' => 'Persona de prueba',
            'destinatario_rut' => null,
            'centro_costo' => null,
            'documento_tipo' => 'ACTA',
            'documento_numero' => 'A-1',
            'costo_unitario' => null,
            'observacion' => 'Entrega de prueba',
        ], $user);

        $manual = InventarioMovimiento::query()->where('tipo', 'ENTREGA_EPP')->firstOrFail();
        $service->reverseManualMovement($manual, 'La entrega fue registrada por error.', $user);

        $this->assertSame(10.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'REVERSO',
            'reverso_de_id' => $manual->id,
            'cantidad' => 3,
        ]);

        $service->registerManualMovement([
            'tipo' => 'TRASLADO',
            'ubicacion_id' => $origin->id,
            'ubicacion_destino_id' => $destination->id,
            'variante_id' => $variant->id,
            'cantidad' => 4,
            'ocurrido_en' => '2026-08-03 09:00:00',
            'destinatario_nombre' => null,
            'destinatario_rut' => null,
            'centro_costo' => null,
            'documento_tipo' => 'GUIA_DESPACHO',
            'documento_numero' => 'G-REV-1',
            'costo_unitario' => null,
            'observacion' => 'Traslado de prueba',
        ], $user);

        $transferOut = InventarioMovimiento::query()->where('tipo', 'TRASLADO_SALIDA')->latest('id')->firstOrFail();
        $service->reverseManualMovement($transferOut, 'El traslado fue registrado por error.', $user);

        $this->assertSame(10.0, $service->stockActual($origin->id, $variant->id));
        $this->assertSame(0.0, $service->stockActual($destination->id, $variant->id));
        $this->assertSame(2, InventarioMovimiento::query()
            ->where('tipo', 'REVERSO')
            ->where('origen', 'REVERSO_MOVIMIENTO_MANUAL')
            ->whereNotNull('grupo_traslado')
            ->count());

        try {
            $service->reverseManualMovement($transferOut, 'Intento de anulación repetido.', $user);
            $this->fail('La segunda anulación debía ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('movimiento', $exception->errors());
        }
    }

    private function inventoryContext(): array
    {
        $role = Rol::create(['codigo' => 'SUPER_ADMIN', 'nombre' => 'Super Admin']);
        $user = User::create([
            'name' => 'Usuario de inventario',
            'email' => 'inventario@example.test',
            'rol_id' => $role->id,
        ]);
        $origin = InventarioUbicacion::create(['codigo' => 'BOD-01', 'nombre' => 'Bodega Principal', 'tipo' => 'BODEGA', 'activo' => true]);
        $destination = InventarioUbicacion::create(['codigo' => 'DES-01', 'nombre' => 'Zona Despacho', 'tipo' => 'DESPACHO', 'activo' => true]);
        $product = app(InventarioStockService::class)->createProduct([
            'nombre' => 'Casco de seguridad',
            'tipo' => 'EPP',
            'categoria' => 'Proteccion',
            'subcategoria' => null,
            'unidad_medida' => 'Unidad',
            'stock_minimo' => 2,
            'tallas' => 'M',
            'activo' => true,
        ], $user);

        return [$user, $origin, $destination, InventarioVariante::query()->where('producto_id', $product->id)->firstOrFail()];
    }
}
