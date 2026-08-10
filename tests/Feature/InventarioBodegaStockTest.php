<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
use App\Models\InventarioConteo;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Models\Rol;
use App\Models\User;
use App\Services\InventarioStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            'inventario_ingreso_items', 'inventario_ingresos', 'inventario_variantes',
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

    public function test_catalog_explains_how_to_load_stock_after_import(): void
    {
        [$user] = $this->inventoryContext();

        $this->withoutMiddleware(\App\Http\Middleware\VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'catalogo']))
            ->assertOk()
            ->assertSee('El catalogo parte en cero.')
            ->assertSee('Cargar desde compra')
            ->assertSee('Cargar desde conteo');
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
