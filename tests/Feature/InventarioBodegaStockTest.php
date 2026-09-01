<?php

namespace Tests\Feature;

use App\Http\Controllers\InventarioBodegaController;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\Configuracion;
use App\Models\EntregaBodega;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioConteo;
use App\Models\InventarioCoordinador;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioImportacionMovimiento;
use App\Models\InventarioIngreso;
use App\Models\InventarioKizeoCatalogItem;
use App\Models\InventarioMovimiento;
use App\Models\InventarioProducto;
use App\Models\InventarioProveedor;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Models\Rol;
use App\Models\User;
use App\Services\EntregaBodegaSyncService;
use App\Services\InventarioKizeoCatalogSyncService;
use App\Services\InventarioOperationalMasterService;
use App\Services\InventarioStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            'inventario_conteo_lineas', 'inventario_conteos', 'inventario_importacion_movimientos', 'inventario_movimientos',
            'inventario_centros_costo', 'inventario_coordinadores',
            'inventario_historial_costos', 'inventario_ingreso_items', 'inventario_ingresos', 'inventario_variantes',
            'inventario_kizeo_catalog_items', 'inventario_productos', 'inventario_proveedores', 'inventario_ubicaciones',
            'entrega_bodega_items', 'entregas_bodega', 'configuraciones',
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

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_07_120000_create_inventario_bodega_tables.php';
        $migration->up();
        $receiptReversalMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_13_150000_add_reversal_fields_to_inventario_ingresos.php';
        $receiptReversalMigration->up();
        $operationalMastersMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_13_190000_add_operational_masters_to_inventario.php';
        $operationalMastersMigration->up();
        $referenceCostMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_14_150000_add_reference_cost_history_to_inventario.php';
        $referenceCostMigration->up();
        $kizeoCatalogMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_18_170000_create_inventario_kizeo_catalog_items_table.php';
        $kizeoCatalogMigration->up();
        $movementImportMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_18_200000_create_inventario_importacion_movimientos_table.php';
        $movementImportMigration->up();
        Cache::flush();

        Schema::create('entregas_bodega', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 32)->unique();
            $table->string('kizeo_form_id', 50)->nullable();
            $table->string('origen_formulario', 120)->nullable();
            $table->string('tipo_operacion', 120)->nullable();
            $table->string('flujo_inventario', 20)->default('SALIDA');
            $table->string('estado_fuente', 40)->default('ACTIVA');
            $table->string('alerta_fuente', 500)->nullable();
            $table->timestamp('fuente_ausente_desde')->nullable();
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

        $kizeoMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_07_123000_create_inventario_entrega_kizeo_tables.php';
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

    public function test_open_stocktake_can_be_recreated_with_consolidated_balance_and_original_cutoff(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-RECREATE-1',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-01',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 8, 'costo_unitario' => null]], $user);

        $source = $service->createStocktake([
            'ubicacion_id' => $origin->id,
            'fecha_corte' => '2026-08-28',
            'observacion' => 'Conteo previo',
            'incluir_sin_stock' => false,
        ], $user);
        $sourceLine = $source->lineas()->firstOrFail();
        $service->saveStocktake($source, [$sourceLine->id => ['cantidad_fisica' => 9, 'observacion' => 'Validado en terreno']]);

        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-RECREATE-2',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-02',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 2, 'costo_unitario' => null]], $user);

        $variant->update(['activo' => false]);
        $replacement = $service->recreateStocktake($source, $user);
        $replacementLine = $replacement->lineas()->firstOrFail();

        $this->assertSame('REEMPLAZADO', $source->fresh()->estado);
        $this->assertFalse($source->fresh()->puedeEliminarse());
        $this->assertSame('EN_REVISION', $replacement->fresh()->estado);
        $this->assertSame('2026-08-28', $replacement->fresh()->fecha_corte->toDateString());
        $this->assertStringStartsWith('CNT-20260828-', $replacement->codigo);
        $this->assertSame(10.0, (float) $replacementLine->cantidad_sistema);
        $this->assertSame(9.0, (float) $replacementLine->cantidad_fisica);
        $this->assertSame('Validado en terreno', $replacementLine->observacion);
    }

    public function test_draft_stocktake_can_be_deleted_without_touching_kardex(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-DEL-1',
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
        $service->saveStocktake($conteo, [$line->id => ['cantidad_fisica' => 6, 'observacion' => 'Prueba']]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->delete(route('inventario-bodega.conteos.destroy', $conteo))
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'conteos']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('inventario_conteos', ['id' => $conteo->id]);
        $this->assertDatabaseMissing('inventario_conteo_lineas', ['conteo_id' => $conteo->id]);
        $this->assertSame(8.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseMissing('inventario_movimientos', [
            'referencia_tipo' => InventarioConteo::class,
            'referencia_id' => $conteo->id,
        ]);
    }

    public function test_approved_stocktake_cannot_be_deleted(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-DEL-2',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-01',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 8, 'costo_unitario' => null]], $user);

        $conteo = $service->createStocktake([
            'ubicacion_id' => $origin->id,
            'fecha_corte' => '2026-08-05',
            'observacion' => 'Conteo real',
            'incluir_sin_stock' => false,
        ], $user);
        $line = $conteo->lineas()->firstOrFail();
        $service->saveStocktake($conteo, [$line->id => ['cantidad_fisica' => 6, 'observacion' => 'Diferencia']]);
        $conteo = InventarioConteo::query()->with('lineas')->findOrFail($conteo->id);
        $service->approveStocktake($conteo, $user);

        try {
            $service->deleteStocktake($conteo->fresh());
            $this->fail('Un conteo aprobado no debe eliminarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('conteo', $exception->errors());
        }

        $this->assertDatabaseHas('inventario_conteos', ['id' => $conteo->id, 'estado' => 'APROBADO']);
        $this->assertSame(6.0, $service->stockActual($origin->id, $variant->id));
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

    public function test_kizeo_batch_application_always_uses_sede_central_and_only_exact_variant_matches(): void
    {
        [$user, $central, , $variant] = $this->inventoryContext();
        $central->update([
            'codigo' => InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE,
            'nombre' => 'Sede Central SAEP',
        ]);
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $central->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-KIZEO-LOTE',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-12',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 7, 'costo_unitario' => null]], $user);

        $first = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-batch-central-1',
            'kizeo_record_number' => 801,
            'nombre' => 'Primera persona',
            'fecha_pedido' => '2026-08-12',
        ]);
        $first->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 2]);
        $second = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-batch-central-2',
            'kizeo_record_number' => 802,
            'nombre' => 'Segunda persona',
            'fecha_pedido' => '2026-08-12',
        ]);
        $second->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 3]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.entregas-kizeo.aplicar-masivo'), ['entregas' => [$first->id, $second->id]])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertSessionHas('success', '2 salida(s) fueron descontadas desde Sede Central SAEP y quedaron trazables en Kardex.');

        $this->assertSame(2.0, $service->stockActual($central->id, $variant->id));
        $this->assertDatabaseCount('inventario_entrega_kizeo_aplicaciones', 2);
        $this->assertDatabaseHas('inventario_entrega_kizeo_aplicaciones', [
            'entrega_bodega_id' => $first->id,
            'ubicacion_id' => $central->id,
            'estado' => 'APLICADA',
        ]);

        $unmatched = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-batch-no-match',
            'kizeo_record_number' => 803,
            'nombre' => 'Relación pendiente',
            'fecha_pedido' => '2026-08-12',
        ]);
        $unmatched->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'L', 'cantidad' => 1]);
        try {
            $service->suggestedKizeoLineMappings($unmatched->load('items'));
            $this->fail('Una talla sin relación exacta no debe entrar a la aplicación masiva.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('entrega', $exception->errors());
        }

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo', 'kizeo_periodo' => 'todo']))
            ->assertOk()
            ->assertSee('Aplicación masiva desde Sede Central SAEP')
            ->assertSee('inventory-kizeo-batch-form', false);
    }

    public function test_kizeo_batch_application_maps_na_to_the_standard_variant_of_the_same_product(): void
    {
        [$user, $central] = $this->inventoryContext();
        $central->update([
            'codigo' => InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE,
            'nombre' => 'Sede Central SAEP',
        ]);
        $service = app(InventarioStockService::class);
        $product = $service->createProduct([
            'nombre' => 'Cuello Polar Azul RAC',
            'tipo' => 'EPP',
            'categoria' => 'Ropa de trabajo',
            'subcategoria' => null,
            'unidad_medida' => 'Unidad',
            'stock_minimo' => 0,
            'tallas' => 'ESTANDAR',
            'activo' => true,
        ], $user);
        $variant = InventarioVariante::query()->where('producto_id', $product->id)->firstOrFail();
        $service->registerReceipt([
            'ubicacion_id' => $central->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-KIZEO-NA',
            'fecha_documento' => null,
            'fecha_recepcion' => '2026-08-12',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 3, 'costo_unitario' => null]], $user);

        $delivery = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-batch-na-standard',
            'kizeo_record_number' => 804,
            'nombre' => 'Persona sin talla',
            'fecha_pedido' => '2026-08-12',
        ]);
        $item = $delivery->items()->create([
            'linea' => 1,
            'articulo' => 'Cuello Polar Azul RAC',
            'talla' => 'NA',
            'cantidad' => 1,
        ]);

        $mappings = $service->suggestedKizeoLineMappings($delivery->load('items'));
        $this->assertSame($variant->id, $mappings[$item->id]['variante_id']);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo', 'kizeo_periodo' => 'todo']))
            ->assertOk()
            ->assertSee('name="entregas[]" value="'.$delivery->id.'" data-kizeo-batch-checkbox', false);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.entregas-kizeo.aplicar-masivo'), ['entregas' => [$delivery->id]])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertSessionHas('success', '1 salida(s) fueron descontadas desde Sede Central SAEP y quedaron trazables en Kardex.');

        $this->assertSame(2.0, $service->stockActual($central->id, $variant->id));
    }

    public function test_epp_roster_import_starts_at_zero_and_can_be_loaded_later(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'epp-roster-').'.xlsx';
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([
            ['Tipo', 'Categoria', 'Sub Categoria', 'Item', 'Formato'],
            ['Epp', 'Botas de Agua', 'Botas RAC', 'Botas de agua Negra RAC T-39', 'Unidad'],
            ['Epp', 'Botas de Agua', 'Botas RAC', 'Botas de agua Negra RAC T-40', 'Unidad'],
            ['Epp', 'Guantes', 'Proteccion', 'Guante nitrilo T-NA', 'Par'],
        ]);
        (new Xlsx($sheet->getParent()))->save($path);

        try {
            $file = new UploadedFile($path, 'Epp.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $result = app(InventarioStockService::class)->importProducts($file, $user);
            $repeat = app(InventarioStockService::class)->importProducts($file, $user);
        } finally {
            @unlink($path);
        }

        $boots = InventarioProducto::query()->where('nombre', 'Botas de agua Negra RAC')->firstOrFail();
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

    public function test_receipt_and_movement_history_are_searchable_paginated_and_include_previous_dates(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $provider = InventarioProveedor::create(['nombre' => 'Proveedor Archivo', 'activo' => true]);
        $yesterday = now()->subDay();

        $oldReceipt = $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => $provider->id,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-HIST-001',
            'fecha_documento' => $yesterday->toDateString(),
            'fecha_recepcion' => $yesterday->toDateString(),
            'observacion' => 'Recepción que debe permanecer en el historial.',
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 1,
            'costo_unitario' => 1000,
        ]], $user);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-ACTUAL-001',
            'fecha_documento' => now()->toDateString(),
            'fecha_recepcion' => now()->toDateString(),
            'observacion' => 'Recepción actual.',
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 1,
            'costo_unitario' => 1200,
        ]], $user);

        $receiptResponse = $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'ingresos']));
        $receiptResponse->assertOk()->assertSee('Historial de ingresos');
        $this->assertSame(2, $receiptResponse->viewData('ingresos')->total());

        $filteredReceipts = $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'ingresos', 'ingreso_buscar' => 'Proveedor Archivo']));
        $this->assertSame(1, $filteredReceipts->viewData('ingresos')->total());
        $this->assertSame($oldReceipt->id, $filteredReceipts->viewData('ingresos')->first()->id);

        $oldMovement = InventarioMovimiento::create([
            'codigo' => 'MOV-HISTORICO',
            'tipo' => 'AJUSTE_POSITIVO',
            'origen' => 'MANUAL',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => 1,
            'destinatario_nombre' => 'Destino Archivo',
            'ocurrido_en' => $yesterday,
            'registrado_por' => $user->id,
            'registrado_por_nombre' => $user->name,
        ]);
        foreach (range(1, 21) as $index) {
            InventarioMovimiento::create([
                'codigo' => 'MOV-ACTUAL-'.$index,
                'tipo' => 'AJUSTE_POSITIVO',
                'origen' => 'MANUAL',
                'ubicacion_id' => $origin->id,
                'producto_id' => $variant->producto_id,
                'variante_id' => $variant->id,
                'cantidad' => 1,
                'ocurrido_en' => now()->addSeconds($index),
                'registrado_por' => $user->id,
                'registrado_por_nombre' => $user->name,
            ]);
        }

        $movementResponse = $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'movimientos', 'movimiento_buscar' => 'Destino Archivo']));
        $movementResponse->assertOk()->assertSee('Kardex histórico');
        $this->assertSame(1, $movementResponse->viewData('movements')->total());
        $this->assertSame($oldMovement->id, $movementResponse->viewData('movements')->first()->id);
        $this->assertSame(25, $movementResponse->viewData('movements')->perPage());
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
        $provider = InventarioProveedor::create(['nombre' => 'Proveedor de lentes', 'activo' => true]);
        $availableProvider = InventarioProveedor::create(['nombre' => 'Proveedor disponible sin ingresos', 'activo' => true]);
        $inactiveProvider = InventarioProveedor::create(['nombre' => 'Proveedor inactivo', 'activo' => false]);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => $provider->id,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-RESUMEN-1',
            'fecha_documento' => now()->toDateString(),
            'fecha_recepcion' => now()->toDateString(),
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

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'resumen']))
            ->assertOk()
            ->assertSee('Proveedor de lentes')
            ->assertSee('Proveedor disponible sin ingresos')
            ->assertDontSee('Proveedor inactivo');

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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
            $rows = IOFactory::load($path)->getActiveSheet()->rangeToArray('A1:K3', null, true, true, false);
        } finally {
            @unlink($path);
        }
        $this->assertSame('LENTE-SEG', $rows[1][0]);
        $this->assertNull($rows[2][0]);
    }

    public function test_summary_operational_traceability_excludes_movements_before_its_start_date(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $this->ensureConfiguracionesTable();
        Configuracion::create([
            'clave' => 'inventario_resumen_trazabilidad_desde',
            'valor' => now()->toDateString(),
            'tipo' => 'DATE',
            'categoria' => 'inventario',
            'descripcion' => 'Inicio de prueba',
            'editable' => false,
        ]);

        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-TRAZABILIDAD',
            'fecha_documento' => now()->toDateString(),
            'fecha_recepcion' => now()->toDateString(),
            'observacion' => 'Ingreso operacional',
        ], [[
            'variante_id' => $variant->id,
            'cantidad' => 10,
            'costo_unitario' => null,
        ]], $user);
        $service->registerManualMovement([
            'tipo' => 'ENTREGA_EPP',
            'ubicacion_id' => $origin->id,
            'variante_id' => $variant->id,
            'cantidad' => 3,
            'ocurrido_en' => now()->format('Y-m-d H:i:s'),
            'destinatario_nombre' => 'Persona de prueba',
            'destinatario_rut' => null,
            'centro_costo' => null,
            'documento_tipo' => null,
            'documento_numero' => null,
            'costo_unitario' => null,
            'observacion' => 'Salida operacional',
        ], $user);
        InventarioMovimiento::create([
            'codigo' => 'MOV-HISTORICO-TRAZA',
            'tipo' => 'STOCK_INICIAL',
            'origen' => 'IMPORTACION',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => 99,
            'ocurrido_en' => now()->subDay()->endOfDay(),
            'registrado_por' => $user->id,
            'registrado_por_nombre' => $user->name,
        ]);

        $request = Request::create('/inventario-bodega', 'GET', ['vista' => 'resumen']);
        $request->setUserResolver(fn () => $user);
        $data = (new InventarioBodegaController($service))->index($request)->getData();

        $this->assertSame(10.0, $data['summaryAnalytics']['entries']);
        $this->assertSame(3.0, $data['summaryAnalytics']['exits']);
        $this->assertSame(7.0, $data['summaryAnalytics']['net']);
        $this->assertSame(2, $data['summaryAnalytics']['movements']);
        $this->assertSame(now()->toDateString(), $data['summaryAnalytics']['daily'][0]['date']);
        $this->assertSame(1, $data['summaryAnalytics']['catalog_total']);
        $this->assertSame(1, $data['summaryAnalytics']['catalog_active']);
        $this->assertSame(0, $data['summaryAnalytics']['catalog_inactive']);

        $yesterdayRequest = Request::create('/inventario-bodega', 'GET', [
            'vista' => 'resumen',
            'resumen_periodo' => 'ayer',
        ]);
        $yesterdayRequest->setUserResolver(fn () => $user);
        $yesterday = (new InventarioBodegaController($service))->index($yesterdayRequest)->getData();
        $this->assertSame('ayer', $yesterday['summaryPeriod']['period']);
        $this->assertFalse($yesterday['summaryPeriod']['has_data_range']);
        $this->assertSame(0, $yesterday['summaryAnalytics']['movements']);
        $this->assertEmpty($yesterday['movements']);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'resumen']))
            ->assertOk()
            ->assertSee('Trazabilidad operativa')
            ->assertSee('Flujo diario')
            ->assertSee('Estado del catálogo')
            ->assertSee('Por tipo de movimiento');
    }

    public function test_stock_detail_loads_product_variants_balances_and_stock_ledger(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $otherVariant = InventarioVariante::create([
            'producto_id' => $variant->producto_id,
            'codigo' => 'CASCO-PRUEBA-L',
            'talla' => 'L',
            'stock_minimo' => 2,
            'activo' => true,
        ]);
        $criticalVariant = InventarioVariante::create([
            'producto_id' => $variant->producto_id,
            'codigo' => 'CASCO-PRUEBA-S',
            'talla' => 'S',
            'stock_minimo' => 12,
            'activo' => true,
        ]);
        InventarioMovimiento::create([
            'codigo' => 'MOV-DETALLE-1',
            'tipo' => 'STOCK_INICIAL',
            'origen' => 'PRUEBA',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => 6,
            'ocurrido_en' => now(),
            'registrado_por' => $user->id,
            'registrado_por_nombre' => $user->name,
        ]);
        InventarioMovimiento::create([
            'codigo' => 'MOV-DETALLE-2',
            'tipo' => 'STOCK_INICIAL',
            'origen' => 'PRUEBA',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $criticalVariant->id,
            'cantidad' => 3,
            'ocurrido_en' => now(),
            'registrado_por' => $user->id,
            'registrado_por_nombre' => 'Usuario de otra talla',
        ]);
        InventarioMovimiento::create([
            'codigo' => 'MOV-DETALLE-KIZEO',
            'tipo' => 'ENTREGA_EPP',
            'origen' => 'KIZEO_EPP',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => -1,
            'documento_numero' => 'KZ-DETALLE-1',
            'ocurrido_en' => now(),
            'registrado_por_nombre' => 'Kizeo automático',
        ]);

        $request = Request::create('/inventario-bodega/stock/'.$variant->id.'/detalle', 'GET', [
            'ubicacion_id' => $origin->id,
        ]);
        $detail = (new InventarioBodegaController(app(InventarioStockService::class)))->stockDetail($request, $variant);
        $ledger = $detail->getData()['movements']->values();
        $html = $detail->render();

        $this->assertSame([6.0, -1.0], $ledger->map(fn ($movement) => (float) $movement->cantidad)->all());
        $this->assertSame([6.0, 5.0], $ledger->map(fn ($movement) => (float) $movement->saldo_resultante)->all());
        $this->assertStringContainsString('Detalle de stock', $html);
        $this->assertStringContainsString('Cartola de stock', $html);
        $this->assertStringContainsString('Saldo actual: 5', $html);
        $this->assertStringContainsString('Bodega Principal', $html);
        $this->assertStringContainsString($variant->talla, $html);
        $this->assertStringContainsString($otherVariant->talla, $html);
        $this->assertStringContainsString($criticalVariant->talla, $html);
        $this->assertStringContainsString('Sin stock', $html);
        $this->assertStringContainsString('Crítico', $html);
        $this->assertStringContainsString($user->name, $html);
        $this->assertStringContainsString('Kizeo', $html);
        $this->assertStringContainsString('KZ-DETALLE-1', $html);
        $this->assertStringNotContainsString('Usuario de otra talla', $html);
        $this->assertStringNotContainsString('La variante seleccionada no tiene stock.', $html);
    }

    public function test_operational_rebase_replaces_legacy_history_and_preserves_current_kizeo_and_receipts(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $this->ensureConfiguracionesTable();
        Storage::fake('local');

        $legacyReceipt = $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-LEGACY',
            'fecha_documento' => '2026-08-23',
            'fecha_recepcion' => '2026-08-23',
            'observacion' => 'Ingreso anterior al inicio oficial.',
        ], [['variante_id' => $variant->id, 'cantidad' => 5, 'costo_unitario' => null]], $user);
        DB::table('inventario_ingresos')->where('id', $legacyReceipt->id)
            ->update(['created_at' => '2026-08-23 16:00:00', 'updated_at' => '2026-08-23 16:00:00']);
        DB::table('inventario_movimientos')
            ->where('referencia_tipo', InventarioIngreso::class)
            ->where('referencia_id', $legacyReceipt->id)
            ->update(['ocurrido_en' => '2026-08-23 16:00:00', 'created_at' => '2026-08-23 16:00:00', 'updated_at' => '2026-08-23 16:00:00']);
        $legacyStocktake = InventarioConteo::create([
            'codigo' => 'CNT-LEGACY',
            'ubicacion_id' => $origin->id,
            'fecha_corte' => '2026-08-21',
            'estado' => 'BORRADOR',
            'observacion' => 'Conteo anterior al inicio oficial.',
            'creado_por' => $user->id,
        ]);
        InventarioMovimiento::create([
            'codigo' => 'MOV-LEGACY-STOCK',
            'tipo' => 'STOCK_INICIAL',
            'origen' => 'IMPORTACION_CATALOGO',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => 2,
            'ocurrido_en' => '2026-08-14 15:00:00',
            'created_at' => '2026-08-14 15:00:00',
            'updated_at' => '2026-08-14 15:00:00',
        ]);
        $importMovement = InventarioMovimiento::create([
            'codigo' => 'MOV-NOMINA-AJUSTE',
            'tipo' => 'AJUSTE_POSITIVO',
            'origen' => 'IMPORTACION_CATALOGO',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => 3,
            'ocurrido_en' => '2026-08-24 12:22:31',
            'registrado_por' => $user->id,
            'registrado_por_nombre' => $user->name,
            'created_at' => '2026-08-24 12:22:31',
            'updated_at' => '2026-08-24 12:22:31',
        ]);
        DB::table('inventario_movimientos')->where('id', $importMovement->id)
            ->update(['created_at' => '2026-08-24 12:22:31', 'updated_at' => '2026-08-24 12:22:31']);
        $this->assertDatabaseHas('inventario_movimientos', ['id' => $importMovement->id, 'created_at' => '2026-08-24 12:22:31']);
        $kizeoMovement = InventarioMovimiento::create([
            'codigo' => 'MOV-KIZEO-VIGENTE',
            'tipo' => 'ENTREGA_EPP',
            'origen' => 'KIZEO_EPP',
            'ubicacion_id' => $origin->id,
            'producto_id' => $variant->producto_id,
            'variante_id' => $variant->id,
            'cantidad' => -2,
            'documento_numero' => 'KZ-100',
            'ocurrido_en' => '2026-08-24 15:00:00',
            'created_at' => '2026-08-24 15:00:00',
            'updated_at' => '2026-08-24 15:00:00',
        ]);
        DB::table('inventario_movimientos')->where('id', $kizeoMovement->id)
            ->update(['created_at' => '2026-08-24 15:00:00', 'updated_at' => '2026-08-24 15:00:00']);
        $todayReceipt = $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-HOY',
            'fecha_documento' => '2026-08-24',
            'fecha_recepcion' => '2026-08-24',
            'observacion' => 'Ingreso del inicio oficial.',
        ], [['variante_id' => $variant->id, 'cantidad' => 3, 'costo_unitario' => null]], $user);
        DB::table('inventario_ingresos')->where('id', $todayReceipt->id)
            ->update(['created_at' => '2026-08-24 16:04:16', 'updated_at' => '2026-08-24 16:04:16']);
        DB::table('inventario_movimientos')
            ->where('referencia_tipo', InventarioIngreso::class)
            ->where('referencia_id', $todayReceipt->id)
            ->update(['ocurrido_en' => '2026-08-24 00:00:00', 'created_at' => '2026-08-24 16:04:16', 'updated_at' => '2026-08-24 16:04:16']);

        $exitCode = Artisan::call('inventario:reiniciar-trazabilidad', [
            '--fecha' => '2026-08-24',
            '--importado-desde' => '2026-08-24 12:22:31',
            '--importado-hasta' => '2026-08-24 12:22:34',
            '--aplicar' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseMissing('inventario_ingresos', ['id' => $legacyReceipt->id]);
        $this->assertDatabaseMissing('inventario_conteos', ['id' => $legacyStocktake->id]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'STOCK_INICIAL',
            'origen' => 'NOMINA_INICIAL',
            'documento_numero' => 'NOMINA-STOCK-20260824',
            'variante_id' => $variant->id,
            'cantidad' => 10,
            'ocurrido_en' => '2026-08-24 00:00:00',
        ]);
        $this->assertDatabaseHas('inventario_movimientos', ['id' => $kizeoMovement->id, 'origen' => 'KIZEO_EPP']);
        $this->assertDatabaseHas('inventario_movimientos', [
            'referencia_tipo' => InventarioIngreso::class,
            'referencia_id' => $todayReceipt->id,
            'ocurrido_en' => '2026-08-24 16:04:16',
        ]);
        $this->assertSame(11.0, $service->stockActual($origin->id, $variant->id));
    }

    public function test_kizeo_queue_filters_deliveries_by_the_article_reported_in_kizeo(): void
    {
        [$user, , , $variant] = $this->inventoryContextWithCentralStock(5);
        $otherProduct = app(InventarioStockService::class)->createProduct([
            'codigo' => 'GUANTE-TERMICO',
            'nombre' => 'Guante térmico',
            'tipo' => 'EPP',
            'categoria' => 'Protección',
            'unidad_medida' => 'Unidad',
            'stock_minimo' => 0,
            'tallas' => 'L',
            'activo' => true,
        ], $user);
        $otherVariant = $otherProduct->variantes()->firstOrFail();

        $matching = $this->newKizeoDelivery('kizeo-filter-article-matching', $variant, 1, now());
        $matching->update(['nombre' => 'Entrega casco por código']);
        $matching->items()->update(['articulo' => $variant->producto->codigo]);
        $other = $this->newKizeoDelivery('kizeo-filter-article-other', $otherVariant, 1, now());
        $other->update(['nombre' => 'Entrega de guante']);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', [
                'vista' => 'kizeo',
                'kizeo_articulo' => $variant->producto->codigo,
            ]))
            ->assertOk()
            ->assertSee('Artículo entregado')
            ->assertSee('Entrega casco por código')
            ->assertDontSee('Entrega de guante')
            ->assertSee('1 entrega sincronizada en SAEP');
    }

    public function test_kizeo_view_summarizes_applied_articles_for_the_selected_period(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContextWithCentralStock(5);
        $delivery = $this->newKizeoDelivery('kizeo-applied-summary', $variant, 2, now());
        $item = $delivery->items->firstOrFail();

        app(InventarioStockService::class)->applyKizeoDelivery(
            $delivery,
            $origin->id,
            [$item->id => ['variante_id' => $variant->id]],
            $user,
        );

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertOk()
            ->assertSee('Artículos descontados en Kizeo')
            ->assertSee($variant->producto->nombre)
            ->assertSee('unidades entregadas')
            ->assertSee('Comprobantes');
    }

    public function test_catalog_import_sets_stock_by_location_without_confusing_it_with_stock_minimum(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'catalog-stock-').'.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Stock_Critico', 'Ubicacion_Codigo', 'Stock_Inicial'];
        $row = ['PARKA-AZUL', 'Parka termica azul', 'EPP', 'Ropa', 'Parkas', 'Unidad', 'M', 5, $origin->codigo, 30];

        try {
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $file = new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $service = app(InventarioStockService::class);
            $result = $service->importProducts($file, $user);
            $repeat = $service->importProducts($file, $user);

            $row[9] = 18;
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $adjusted = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);
        } finally {
            @unlink($path);
        }

        $product = InventarioProducto::query()->where('codigo', 'PARKA-AZUL')->firstOrFail();
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

    public function test_catalog_import_generates_codes_and_applies_status_per_variant(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $origin->update([
            'codigo' => InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE,
            'nombre' => 'Sede Central SAEP',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'catalog-status-').'.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia', 'Stock_Critico', 'Ubicacion_Codigo', 'Stock_Inicial', 'Estado'];
        $rows = [
            ['', 'Botín nuevo de prueba', 'EPP', 'Calzado', 'Botines', 'Unidad', '35', null, 2, '', 4, 'Inhabilitado'],
            ['', 'Botín nuevo de prueba', 'EPP', 'Calzado', 'Botines', 'Unidad', '36', null, 2, 'Todas las ubicaciones', 3, 'Activo'],
            ['', 'Casco nuevo inhabilitado', 'EPP', 'Cascos', 'Seguridad', 'Unidad', 'ESTANDAR', null, 1, 'Todas las ubicaciones', null, 'Inhabilitado'],
        ];

        try {
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray([$headers, ...$rows]);
            (new Xlsx($sheet->getParent()))->save($path);
            $result = app(InventarioStockService::class)->importProducts(
                new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                $user,
            );
        } finally {
            @unlink($path);
        }

        $botin = InventarioProducto::query()->where('nombre', 'Botín nuevo de prueba')->firstOrFail();
        $casco = InventarioProducto::query()->where('nombre', 'Casco nuevo inhabilitado')->firstOrFail();
        $botin35 = $botin->variantes()->where('talla', '35')->firstOrFail();
        $botin36 = $botin->variantes()->where('talla', '36')->firstOrFail();
        $cascoVariant = $casco->variantes()->where('talla', 'ESTANDAR')->firstOrFail();

        $this->assertSame(2, $result['created']);
        $this->assertSame(3, $result['variantsCreated']);
        $this->assertSame(2, $result['stocksSet']);
        $this->assertSame(2, $result['variantsInactive']);
        $this->assertSame(2, $result['centralStockRows']);
        $this->assertNotSame('', $botin->codigo);
        $this->assertSame($botin->codigo.'-35', $botin35->codigo);
        $this->assertFalse((bool) $botin35->activo);
        $this->assertTrue((bool) $botin36->activo);
        $this->assertTrue((bool) $botin->fresh()->activo);
        $this->assertFalse((bool) $cascoVariant->activo);
        $this->assertFalse((bool) $casco->fresh()->activo);
        $this->assertSame(4.0, app(InventarioStockService::class)->stockActual($origin->id, $botin35->id));
        $this->assertSame(3.0, app(InventarioStockService::class)->stockActual($origin->id, $botin36->id));
    }

    public function test_catalog_template_includes_status_and_documents_automatic_codes(): void
    {
        $response = (new InventarioBodegaController(app(InventarioStockService::class)))->productTemplate();
        $path = $response->getFile()->getPathname();

        try {
            $spreadsheet = IOFactory::load($path);
            $headers = $spreadsheet->getSheetByName('Productos')->rangeToArray('A1:L1', null, true, true, false)[0];
            $instructions = $spreadsheet->getSheetByName('Instrucciones')->rangeToArray('A1:A9', null, true, true, false);
        } finally {
            @unlink($path);
        }

        $this->assertSame(['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia', 'Stock_Critico', 'Ubicacion_Codigo', 'Stock_Inicial', 'Estado'], $headers);
        $this->assertStringContainsString('Codigo es opcional', $instructions[4][0]);
        $this->assertStringContainsString('Inactivo/Inhabilitado', $instructions[3][0]);
        $this->assertStringContainsString('SAEP-CENTRAL', $instructions[5][0]);
    }

    public function test_catalog_import_and_export_preserve_accents_and_enye(): void
    {
        [$user, $origin] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'catalog-unicode-').'.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Stock_Critico', 'Ubicacion_Codigo', 'Stock_Inicial', 'Estado'];
        $row = ['EPP-NANDU', 'Botín Ñandú Ágil', 'EPP', 'Calzado', 'Botines', 'Unidad', '42', 1, $origin->codigo, 3, 'Activo'];

        try {
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            app(InventarioStockService::class)->importProducts(
                new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                $user,
            );
        } finally {
            @unlink($path);
        }

        $product = InventarioProducto::query()->where('codigo', 'EPP-NANDU')->firstOrFail();
        $this->assertSame('Botín Ñandú Ágil', $product->nombre);

        $response = (new InventarioBodegaController(app(InventarioStockService::class)))->exportBalances(Request::create('/inventario-bodega/exportar', 'GET', [
            'ubicacion_id' => $origin->id,
            'buscar' => 'EPP-NANDU',
        ]));
        $exportPath = $response->getFile()->getPathname();

        try {
            $rows = IOFactory::load($exportPath)->getActiveSheet()->toArray(null, true, true, false);
        } finally {
            @unlink($exportPath);
        }

        $this->assertSame('Botín Ñandú Ágil', $rows[1][1]);
    }

    public function test_catalog_import_preserves_reference_cost_history_by_size(): void
    {
        [$user] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'catalog-cost-').'.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia'];
        $row = ['GUANTE-COSTO', 'Guante con costo', 'EPP', 'Guantes', 'Nitrilo', 'Unidad', 'M', 1250];

        try {
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $service = app(InventarioStockService::class);
            $first = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);

            $row[7] = 1475;
            $sheet = (new Spreadsheet)->getActiveSheet();
            $sheet->fromArray([$headers, $row]);
            (new Xlsx($sheet->getParent()))->save($path);
            $second = $service->importProducts(new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $user);

            $row[7] = 0;
            $sheet = (new Spreadsheet)->getActiveSheet();
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
        $path = tempnam(sys_get_temp_dir(), 'catalog-price-format-').'.xlsx';
        $headers = ['Codigo', 'Producto', 'Tipo', 'Categoria', 'Subcategoria', 'Formato', 'Talla', 'Costo_Referencia'];
        $row = ['BOTIN-PRECIO', 'Botín con precio', 'EPP', 'Calzado', 'Botines', 'Unidad', '40', 41590];

        try {
            $sheet = (new Spreadsheet)->getActiveSheet();
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

        $response = $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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
        $this->assertStringContainsString('ubicacion_id='.$origin->id, $location);
        $this->assertStringContainsString('producto_editar='.$variant->producto_id, $location);
        $this->assertStringContainsString('productos_pagina=2', $location);
        $this->assertStringContainsString('producto_buscar=casco', $location);
    }

    public function test_operational_masters_import_and_fill_a_manual_movement_from_center_and_coordinator(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $path = tempnam(sys_get_temp_dir(), 'inventory-masters-').'.xlsx';
        $spreadsheet = new Spreadsheet;
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

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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

        $mastersResponse = $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
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
        $path = tempnam(sys_get_temp_dir(), 'receipt-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Referencia_Ingreso', 'Ubicacion_Codigo', 'Proveedor', 'Proveedor_Rut', 'Tipo_Documento', 'Numero_Documento', 'Fecha_Documento', 'Fecha_Recepcion', 'Codigo_Producto', 'Talla', 'Cantidad', 'Costo_Unitario', 'Observacion'],
            ['COMPRA-AGOSTO-01', $origin->codigo, 'Proveedor importado', '76543210-1', 'FACTURA', 'F-IMPORT-1', '10/08/2026', '11/08/2026', $variant->producto->codigo, 'M', '4', 41590, 'Carga desde planilla'],
        ]);
        $sheet->getStyle('L2')->getNumberFormat()->setFormatCode('#,##0');
        (new Xlsx($spreadsheet))->save($path);

        try {
            $file = new UploadedFile($path, 'ingresos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $result = app(InventarioStockService::class)->importReceipts($file, $user);
        } finally {
            @unlink($path);
        }

        $service = app(InventarioStockService::class);
        $this->assertSame(1, $result['receipts']);
        $this->assertSame(1, $result['lines']);
        $this->assertSame(4.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_proveedores', ['rut' => '76543210-1', 'nombre' => 'Proveedor importado']);
        $this->assertDatabaseHas('inventario_ingresos', ['numero_documento' => 'F-IMPORT-1']);
        $this->assertDatabaseHas('inventario_movimientos', ['variante_id' => $variant->id, 'costo_unitario' => 41590]);

        $receipt = InventarioIngreso::query()->where('numero_documento', 'F-IMPORT-1')->firstOrFail();
        $service->reverseReceipt($receipt, 'Ingreso importado de prueba.', $user);

        $this->assertSame(0.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_ingresos', ['id' => $receipt->id, 'motivo_reversion' => 'Ingreso importado de prueba.']);
    }

    public function test_csv_upload_validation_accepts_a_browser_plain_text_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt-import-').'.csv';
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

    public function test_movement_import_updates_stock_avoids_duplicates_and_can_be_reversed(): void
    {
        [$user, $origin, $destination, $variant] = $this->inventoryContext();
        $service = app(InventarioStockService::class);
        $service->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => 'F-IMPORT-MOV-1',
            'fecha_documento' => '2026-08-18',
            'fecha_recepcion' => '2026-08-18',
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => 10, 'costo_unitario' => null]], $user);

        $coordinator = InventarioCoordinador::create([
            'nombre' => 'Coordinadora importada',
            'nombre_normalizado' => 'coordinadora importada',
            'rut' => '11111111-1',
            'activo' => true,
        ]);
        $costCenter = InventarioCentroCosto::create([
            'numero_maestro' => 108,
            'nombre' => 'Centro importado',
            'nombre_normalizado' => 'centro importado',
            'coordinador_id' => $coordinator->id,
            'activo' => true,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'movement-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Referencia_Movimiento', 'Tipo', 'Ubicacion_Origen_Codigo', 'Ubicacion_Destino_Codigo', 'Codigo_Producto', 'Talla', 'Cantidad', 'Fecha_Hora', 'Centro_Costo', 'Coordinador', 'Destinatario', 'RUT_Destinatario', 'Tipo_Documento', 'Numero_Documento', 'Costo_Unitario', 'Observacion'],
            ['MOV-IMP-001', 'ENTREGA_EPP', $origin->codigo, '', $variant->producto->codigo, $variant->talla, 2, '18/08/2026 09:30', 108, '', '', '', 'ACTA', 'ACTA-001', '', 'Entrega importada'],
            ['MOV-IMP-002', 'TRASLADO', $origin->codigo, $destination->codigo, $variant->producto->codigo, $variant->talla, 3, '2026-08-18 10:00', '', '', '', '', 'GUIA_DESPACHO', 'GD-001', '', 'Traslado importado'],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        try {
            $file = new UploadedFile($path, 'movimientos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $result = $service->importManualMovements($file, $user);
            $repeat = $service->importManualMovements($file, $user);
        } finally {
            @unlink($path);
        }

        $this->assertSame(2, $result['movements']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $repeat['movements']);
        $this->assertSame(2, $repeat['skipped']);
        $this->assertSame(5.0, $service->stockActual($origin->id, $variant->id));
        $this->assertSame(3.0, $service->stockActual($destination->id, $variant->id));
        $this->assertSame(2, InventarioImportacionMovimiento::query()->count());
        $this->assertDatabaseHas('inventario_movimientos', [
            'tipo' => 'ENTREGA_EPP',
            'origen' => 'MANUAL',
            'centro_costo_id' => $costCenter->id,
            'coordinador_id' => $coordinator->id,
            'cantidad' => -2,
        ]);

        $imported = InventarioImportacionMovimiento::query()->with('movimiento')->get()->keyBy('referencia');
        $service->reverseManualMovement($imported->get('MOV-IMP-001')->movimiento, 'Salida importada de prueba.', $user);
        $this->assertSame(7.0, $service->stockActual($origin->id, $variant->id));

        $service->reverseManualMovement($imported->get('MOV-IMP-002')->movimiento, 'Traslado importado de prueba.', $user);
        $this->assertSame(10.0, $service->stockActual($origin->id, $variant->id));
        $this->assertSame(0.0, $service->stockActual($destination->id, $variant->id));
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
            $rows = IOFactory::load($path)->getActiveSheet()->rangeToArray('A1:L2', null, true, true, false);
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
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventario_bodega/index.blade.php');

        $this->assertStringContainsString('El catálogo puede incluir stock, costo y estado por talla.', $view);
        $this->assertStringContainsString('Costo_Referencia', $view);
        $this->assertStringContainsString('Inactivo/Inhabilitado', $view);
        $this->assertStringContainsString('Cargar desde compra', $view);
        $this->assertStringContainsString('Cargar desde conteo', $view);
        $this->assertStringContainsString('conteos.destroy', $view);
        $this->assertStringContainsString('Eliminar conteo de prueba', $view);
        $this->assertStringContainsString('Desglose por talla', $view);
        $this->assertStringContainsString('product-variant-editor', $view);
        $this->assertStringContainsString('data-variants=', $view);
        $this->assertStringContainsString('inventory-variant-card', $view);
        $this->assertStringContainsString('data-product-category-select', $view);
        $this->assertStringContainsString('data-product-subcategory-select', $view);
        $this->assertStringContainsString('Importar movimientos manuales', $view);
        $this->assertStringContainsString('Referencia_Movimiento', $view);
        $this->assertStringContainsString('Entregas Kizeo', $view);
        $this->assertStringContainsString('Ver o anular ingresos', $view);
        $this->assertStringContainsString('InventarioMovimiento::TIPOS_DOCUMENTO', $view);
        $this->assertStringContainsString('data-reference-cost', $view);
        $this->assertStringContainsString('prefillReceiptReferenceCost', $view);
        $this->assertStringContainsString('title="Se precarga el costo de referencia', $view);
    }

    public function test_kizeo_queue_is_collapsed_and_displays_whether_stock_was_discounted(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContext();
        $origin->update(['codigo' => InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE, 'nombre' => 'Sede Central SAEP']);
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

        $insufficient = EntregaBodega::create([
            'kizeo_data_id' => 'kizeo-insufficient-ui',
            'kizeo_record_number' => 704,
            'nombre' => 'Pendiente sin stock suficiente',
            'fecha_pedido' => '2026-08-10',
        ]);
        $insufficient->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 4]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo', 'kizeo_periodo' => 'todo']))
            ->assertOk()
            ->assertSee('No descontada')
            ->assertSee('Salida descontada')
            ->assertSee('Stock repuesto')
            ->assertSee('1 item')
            ->assertSee('Stock SAEP')
            ->assertSee('Disponible')
            ->assertSee('Insuficiente')
            ->assertSee('Ajustar stock')
            ->assertSee('variante_ajustar='.$variant->id, false)
            ->assertSee('data-kizeo-stock-select', false)
            ->assertSee('inventory-kizeo-central-stock', false)
            ->assertSee('data-inventory-search-select', false)
            ->assertSee('Buscar por codigo, articulo o talla', false)
            ->assertSee('Descuento automático desactivado')
            ->assertSee('No aplica el histórico')
            ->assertSee('<details class="inventory-delivery-card">', false)
            ->assertDontSee('<details class="inventory-delivery-card" open', false);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', [
                'vista' => 'catalogo',
                'ubicacion_id' => $origin->id,
                'producto_editar' => $variant->producto_id,
                'producto_buscar' => $variant->producto->codigo,
                'variante_ajustar' => $variant->id,
            ]))
            ->assertOk()
            ->assertSee('data-highlight-variant="'.$variant->id.'"', false);
    }

    public function test_article_selectors_and_long_inventory_lists_use_the_searchable_picker(): void
    {
        [$user] = $this->inventoryContext();

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'ingresos']))
            ->assertOk()
            ->assertSee('name="items[__INDEX__][variante_id]" class="form-select" required data-inventory-search-select', false)
            ->assertSee('function shouldUseSearchSelect(nativeSelect)', false)
            ->assertSee('return selectableOptions(nativeSelect).length > 5;', false)
            ->assertSee("root.querySelectorAll('select.form-select')", false);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'movimientos']))
            ->assertOk()
            ->assertSee('name="variante_id" class="form-select" required data-inventory-search-select', false);
    }

    public function test_kizeo_queue_defaults_to_today_and_allows_period_and_custom_range_filters(): void
    {
        [$user, , , $variant] = $this->inventoryContextWithCentralStock(5);
        $today = now()->startOfDay();
        $todayDelivery = $this->newKizeoDelivery('kizeo-filter-today', $variant, 1, now());
        $todayDelivery->update([
            'nombre' => 'Entrega sincronizada hoy',
            'fecha_pedido' => $today->toDateString(),
            'synced_at' => now(),
        ]);
        $yesterdayDelivery = $this->newKizeoDelivery('kizeo-filter-yesterday', $variant, 1, now()->subDay());
        $yesterdayDelivery->update([
            'nombre' => 'Entrega sincronizada ayer',
            'fecha_pedido' => $today->copy()->subDay()->toDateString(),
        ]);
        $previousDelivery = $this->newKizeoDelivery('kizeo-filter-before-yesterday', $variant, 1, now()->subDays(2));
        $previousDelivery->update([
            'nombre' => 'Entrega sincronizada antes de ayer',
            'fecha_pedido' => $today->copy()->subDays(2)->toDateString(),
        ]);
        $reviewDelivery = $this->newKizeoDelivery('kizeo-filter-review', $variant, 1, now()->subDays(3));
        $reviewDelivery->update([
            'nombre' => 'Entrega fuera del período que requiere revisión',
            'fecha_pedido' => $today->copy()->subDays(3)->toDateString(),
            'estado_fuente' => 'INCOMPLETA',
        ]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertOk()
            ->assertSee('Entrega sincronizada hoy')
            ->assertDontSee('Entrega sincronizada ayer')
            ->assertSee('Periodo')
            ->assertSee('Vigentes (1)')
            ->assertSee('<article class="inventory-kpi accent-red"><span>Requieren revision</span><strong>0</strong>', false)
            ->assertSee('Antes de ayer')
            ->assertSee('Todo el historial');

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo', 'kizeo_periodo' => 'ayer']))
            ->assertOk()
            ->assertSee('Entrega sincronizada ayer')
            ->assertSee('Vigentes (1)')
            ->assertDontSee('Entrega sincronizada hoy');

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', [
                'vista' => 'kizeo',
                'kizeo_periodo' => 'personalizado',
                'kizeo_desde' => $today->copy()->subDays(2)->toDateString(),
                'kizeo_hasta' => $today->copy()->subDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Entrega sincronizada ayer')
            ->assertSee('Entrega sincronizada antes de ayer')
            ->assertSee('Vigentes (2)')
            ->assertDontSee('Entrega sincronizada hoy');

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo', 'kizeo_periodo' => 'todo']))
            ->assertOk()
            ->assertSee('Entrega fuera del período que requiere revisión')
            ->assertSee('<article class="inventory-kpi accent-red"><span>Requieren revision</span><strong>1</strong>', false);
    }

    public function test_saep_catalog_sync_updates_and_creates_advanced_kizeo_list_items_without_deleting_orphans(): void
    {
        [$user] = $this->inventoryContext();
        $stock = app(InventarioStockService::class);
        $newProduct = $stock->createProduct([
            'nombre' => 'Guante de prueba',
            'tipo' => 'EPP',
            'categoria' => 'Guantes',
            'subcategoria' => 'Guante de prueba',
            'unidad_medida' => 'Unidad',
            'stock_minimo' => 0,
            'tallas' => 'ESTANDAR',
            'activo' => true,
        ], $user);
        $existingVariant = InventarioVariante::query()->where('talla', 'M')->firstOrFail();
        $newVariant = InventarioVariante::query()->where('producto_id', $newProduct->id)->firstOrFail();
        config(['services.kizeo.inventory_catalog_list_id' => '500434', 'services.kizeo.token' => 'kizeo-test-token']);

        $definition = [
            'properties_definition' => [
                'type' => ['id' => 'property-type', 'display_name' => 'Tipo'],
                'category' => ['id' => 'property-category', 'display_name' => 'Categoria'],
                'subcategory' => ['id' => 'property-subcategory', 'display_name' => 'Sub Categoria'],
                'format' => ['id' => 'property-format', 'display_name' => 'Formato'],
            ],
        ];
        $items = [[
            'id' => 'remote-casco-m',
            'label' => 'Casco de seguridad T-M',
            'properties' => [
                'property-type' => 'EPP',
                'property-category' => 'Proteccion',
                'property-subcategory' => 'Casco de seguridad',
                'property-format' => 'Caja',
            ],
        ], [
            'id' => 'remote-legacy',
            'label' => 'Producto que ya no está en SAEP T-NA',
            'properties' => [],
        ]];

        $listItemReads = 0;
        Http::fake(function (HttpRequest $request) use ($definition, $items, &$listItemReads) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/definition')) {
                return Http::response($definition);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/items')) {
                $listItemReads++;

                return Http::response($listItemReads === 1 ? $items : array_merge($items, [[
                    'id' => 'remote-guante-standard',
                    'label' => 'Guante de prueba T-NA',
                    'properties' => [],
                ]]));
            }
            if ($request->method() === 'PATCH' && str_ends_with($request->url(), '/remote-casco-m')) {
                return Http::response(['id' => 'remote-casco-m']);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/items')) {
                return Http::response(['status' => 'ok'], 201);
            }

            return Http::response(['message' => 'Ruta de prueba no esperada'], 404);
        });

        $summary = app(InventarioKizeoCatalogSyncService::class)->synchronize();

        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(0, $summary['unchanged']);
        $this->assertSame(0, $summary['removed']);
        $this->assertSame([], $summary['errors']);
        $this->assertSame(['Producto que ya no está en SAEP T-NA'], $summary['orphans']);
        $this->assertDatabaseHas('inventario_kizeo_catalog_items', ['variante_id' => $existingVariant->id, 'kizeo_item_id' => 'remote-casco-m']);
        $this->assertDatabaseHas('inventario_kizeo_catalog_items', ['variante_id' => $newVariant->id, 'kizeo_item_id' => 'remote-guante-standard']);
        Http::assertSent(function (HttpRequest $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/lists/500434/items')
                && ($request['items'][0]['label'] ?? null) === 'Guante de prueba T-NA';
        });
        Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'DELETE');
    }

    public function test_catalog_exposes_the_manual_kizeo_sync_button_to_inventory_editors(): void
    {
        [$user] = $this->inventoryContext();
        config(['services.kizeo.inventory_catalog_list_id' => '500434']);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'catalogo']))
            ->assertOk()
            ->assertSee('SAEP publica el catálogo en Kizeo')
            ->assertSee('EPP AVANZADA (500434)')
            ->assertSee('Sincronizar ahora')
            ->assertSee('se quitarán los productos inhabilitados')
            ->assertSee('Producto vigente')
            ->assertSee(route('inventario-bodega.catalogo.kizeo.sincronizar'), false);
    }

    public function test_inactive_products_remain_in_catalog_but_leave_new_movement_options(): void
    {
        [$user] = $this->inventoryContext();
        $variant = InventarioVariante::query()->where('talla', 'M')->firstOrFail();
        $variant->producto->update(['activo' => false]);
        $this->assertDatabaseHas('inventario_productos', ['id' => $variant->producto_id, 'activo' => false]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'catalogo', 'producto_estado' => 'inactivos']))
            ->assertOk()
            ->assertSee('Casco de seguridad')
            ->assertSee('Inactivo');

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'movimientos']))
            ->assertOk()
            ->assertSee('Necesitas ubicaciones y articulos activos para registrar movimientos.')
            ->assertDontSee('Casco de seguridad');
    }

    public function test_product_management_can_find_and_reactivate_inactive_products_outside_the_visible_table_filter(): void
    {
        [$user] = $this->inventoryContext();
        $variant = InventarioVariante::query()->where('talla', 'M')->firstOrFail();
        $product = $variant->producto;
        $variant->update(['activo' => false]);
        $product->update(['activo' => false]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'catalogo', 'producto_estado' => 'activos']))
            ->assertOk()
            ->assertSee('Busca y selecciona productos activos o inactivos')
            ->assertSee($product->codigo.' - Casco de seguridad · Inactivo')
            ->assertSee('data-active="0"', false)
            ->assertSee('data-variant-status-action-base', false)
            ->assertSee('Talla vigente');

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->from(route('inventario-bodega.index', ['vista' => 'catalogo']))
            ->patch(route('inventario-bodega.variantes.estado.update', $variant), [
                'activo' => '1',
            ])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'catalogo', 'producto_editar' => $product->id]));

        $this->assertDatabaseHas('inventario_productos', ['id' => $product->id, 'activo' => true]);
        $this->assertDatabaseHas('inventario_variantes', ['id' => $variant->id, 'activo' => true]);
    }

    public function test_variant_status_is_independent_and_product_updates_do_not_reactivate_it(): void
    {
        [$user, , , $variant] = $this->inventoryContext();
        $product = $variant->producto;
        $otherVariant = InventarioVariante::query()->create([
            'producto_id' => $product->id,
            'codigo' => $product->codigo.'-L',
            'talla' => 'L',
            'activo' => true,
        ]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->patch(route('inventario-bodega.variantes.estado.update', $variant), ['activo' => '0'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventario_variantes', ['id' => $variant->id, 'activo' => false]);
        $this->assertDatabaseHas('inventario_variantes', ['id' => $otherVariant->id, 'activo' => true]);
        $this->assertDatabaseHas('inventario_productos', ['id' => $product->id, 'activo' => true]);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->put(route('inventario-bodega.productos.update', $product), [
                'nombre' => $product->nombre,
                'tipo' => $product->tipo,
                'categoria' => $product->categoria,
                'subcategoria' => $product->subcategoria,
                'unidad_medida' => $product->unidad_medida,
                'stock_minimo' => $product->stock_minimo,
                'tallas' => 'M, L',
                'activo' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventario_variantes', ['id' => $variant->id, 'activo' => false]);
        $this->assertDatabaseHas('inventario_variantes', ['id' => $otherVariant->id, 'activo' => true]);
    }

    public function test_inactive_saep_products_are_removed_from_kizeo_and_can_be_republished(): void
    {
        [$user] = $this->inventoryContext();
        $variant = InventarioVariante::query()->where('talla', 'M')->firstOrFail();
        $product = $variant->producto;
        InventarioKizeoCatalogItem::query()->create([
            'variante_id' => $variant->id,
            'kizeo_list_id' => '500434',
            'kizeo_item_id' => 'remote-casco-m',
            'source_hash' => 'hash-casco',
            'sincronizado_en' => now(),
        ]);
        $product->update(['activo' => false]);
        config(['services.kizeo.inventory_catalog_list_id' => '500434', 'services.kizeo.token' => 'kizeo-test-token']);

        $definition = [
            'properties_definition' => [
                'type' => ['id' => 'property-type', 'display_name' => 'Tipo'],
                'category' => ['id' => 'property-category', 'display_name' => 'Categoria'],
                'subcategory' => ['id' => 'property-subcategory', 'display_name' => 'Sub Categoria'],
                'format' => ['id' => 'property-format', 'display_name' => 'Formato'],
            ],
        ];
        $items = [[
            'id' => 'remote-casco-m',
            'label' => 'Casco de seguridad T-M',
            'properties' => [
                'property-type' => 'EPP',
                'property-category' => 'Proteccion',
                'property-subcategory' => 'Casco de seguridad',
                'property-format' => 'Unidad',
            ],
        ]];

        $deleted = false;
        Http::fake(function (HttpRequest $request) use ($definition, $items, &$deleted) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/definition')) {
                return Http::response($definition);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/items')) {
                return Http::response($deleted ? [] : $items);
            }
            if ($request->method() === 'DELETE' && str_ends_with($request->url(), '/remote-casco-m')) {
                $deleted = true;

                return Http::response(['status' => 'ok']);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/items')) {
                return Http::response(['id' => 'remote-casco-m-new'], 201);
            }

            return Http::response(['message' => 'Ruta de prueba no esperada'], 404);
        });

        $removed = app(InventarioKizeoCatalogSyncService::class)->synchronize();

        $this->assertSame(0, $removed['created']);
        $this->assertSame(1, $removed['removed']);
        $this->assertSame([], $removed['errors']);
        $this->assertDatabaseMissing('inventario_kizeo_catalog_items', ['variante_id' => $variant->id]);
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/lists/500434/items/remote-casco-m'));

        $product->update(['activo' => true]);
        Cache::flush();
        $republished = app(InventarioKizeoCatalogSyncService::class)->synchronize();

        $this->assertSame(1, $republished['created']);
        $this->assertSame(0, $republished['removed']);
        $this->assertSame([], $republished['errors']);
        $this->assertDatabaseHas('inventario_kizeo_catalog_items', [
            'variante_id' => $variant->id,
            'kizeo_item_id' => 'remote-casco-m-new',
        ]);
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

    public function test_kizeo_auto_apply_stays_off_and_does_not_discount_new_deliveries(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContextWithCentralStock(6);
        $service = app(InventarioStockService::class);
        $delivery = $this->newKizeoDelivery('kizeo-auto-off', $variant, 2, now());

        $this->assertNull($service->tryAutoApplyNewKizeoDelivery($delivery->load('items'), true));
        $this->assertSame(6.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseCount('inventario_entrega_kizeo_aplicaciones', 0);
        $this->assertFalse($service->kizeoAutoApplyEnabled());
    }

    public function test_kizeo_auto_apply_discounts_only_new_deliveries_after_the_switch_is_on(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContextWithCentralStock(8);
        $service = app(InventarioStockService::class);
        $this->ensureConfiguracionesTable();
        $service->setKizeoAutoApply(true, $user);

        $historical = $this->newKizeoDelivery('kizeo-auto-old', $variant, 2, now()->subDay());
        $this->assertNull($service->tryAutoApplyNewKizeoDelivery($historical->load('items'), true));
        $this->assertSame(8.0, $service->stockActual($origin->id, $variant->id));

        $fresh = $this->newKizeoDelivery('kizeo-auto-new', $variant, 3, now()->addMinute());
        $application = $service->tryAutoApplyNewKizeoDelivery($fresh->load('items'), true);

        $this->assertNotNull($application);
        $this->assertSame('APLICADA', $application->estado);
        $this->assertNull($application->aplicada_por);
        $this->assertSame(5.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseHas('inventario_movimientos', [
            'origen' => 'KIZEO_EPP',
            'cantidad' => -3,
            'registrado_por_nombre' => 'Kizeo automático',
        ]);
        $this->assertNull($service->tryAutoApplyNewKizeoDelivery($fresh->fresh(['items', 'inventarioAplicacion']), false));
    }

    public function test_kizeo_auto_apply_leaves_unmatched_or_insufficient_new_deliveries_pending(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContextWithCentralStock(1);
        $service = app(InventarioStockService::class);
        $this->ensureConfiguracionesTable();
        $service->setKizeoAutoApply(true, $user);

        $unmatched = $this->newKizeoDelivery('kizeo-auto-unmatched', $variant, 1, now()->addMinute(), 'L');
        $this->assertNull($service->tryAutoApplyNewKizeoDelivery($unmatched->load('items'), true));

        $insufficient = $this->newKizeoDelivery('kizeo-auto-stock', $variant, 4, now()->addMinute());
        $this->assertNull($service->tryAutoApplyNewKizeoDelivery($insufficient->load('items'), true));

        $this->assertSame(1.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseCount('inventario_entrega_kizeo_aplicaciones', 0);
    }

    public function test_kizeo_auto_apply_toggle_does_not_apply_the_existing_queue(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContextWithCentralStock(10);
        $pending = $this->newKizeoDelivery('kizeo-auto-queue', $variant, 2, now()->subDays(3));
        $this->ensureConfiguracionesTable();

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->post(route('inventario-bodega.entregas-kizeo.auto-aplicar'), ['activo' => 1])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertSessionHas('success');

        $this->assertTrue(app(InventarioStockService::class)->kizeoAutoApplyEnabled());
        $this->assertDatabaseCount('inventario_entrega_kizeo_aplicaciones', 0);
        $this->assertSame(10.0, app(InventarioStockService::class)->stockActual($origin->id, $variant->id));
        $this->assertNotNull($pending->fresh());

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertOk()
            ->assertSee('Descuento automático activo')
            ->assertSee('formulario histórico');
    }

    public function test_legacy_kizeo_form_cannot_discount_stock_and_stays_out_of_the_live_queue(): void
    {
        [$user, $origin, , $variant] = $this->inventoryContextWithCentralStock(5);
        $service = app(InventarioStockService::class);
        $legacy = EntregaBodega::create([
            'kizeo_form_id' => EntregaBodegaSyncService::LEGACY_FORM_ID,
            'kizeo_data_id' => 'kizeo-legacy-947762',
            'kizeo_record_number' => 947,
            'origen_formulario' => 'Control de Entrega Bodega',
            'flujo_inventario' => 'SALIDA',
            'estado_fuente' => 'ACTIVA',
            'nombre' => 'Entrega histórica',
            'fecha_pedido' => '2025-03-01',
        ]);
        $item = $legacy->items()->create(['linea' => 1, 'articulo' => 'Casco de seguridad', 'talla' => 'M', 'cantidad' => 2]);

        try {
            $service->applyKizeoDelivery($legacy->load('items'), $origin->id, [
                $item->id => ['variante_id' => $variant->id],
            ], $user);
            $this->fail('El formulario histórico no debe descontar stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('entrega', $exception->errors());
        }

        $this->assertSame(5.0, $service->stockActual($origin->id, $variant->id));
        $this->assertDatabaseCount('inventario_entrega_kizeo_aplicaciones', 0);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->from(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->post(route('inventario-bodega.entregas-kizeo.aplicar-masivo'), ['entregas' => [$legacy->id]])
            ->assertRedirect(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertSessionHas('warning');

        $this->assertSame(5.0, $service->stockActual($origin->id, $variant->id));

        $live = $this->newKizeoDelivery('kizeo-live-queue', $variant, 1, now());

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo']))
            ->assertOk()
            ->assertSee('Histórico Kizeo')
            ->assertSee($live->nombre)
            ->assertDontSee('>'.$legacy->nombre.'<', false);

        $this->withoutMiddleware(VerificarConsentimientoDatos::class)
            ->actingAs($user)
            ->get(route('inventario-bodega.index', ['vista' => 'kizeo', 'kizeo_origen' => 'historico', 'kizeo_periodo' => 'todo']))
            ->assertOk()
            ->assertSee($legacy->nombre)
            ->assertSee('Histórico · no descuenta')
            ->assertDontSee('Aplicar salida de stock');
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

    private function inventoryContextWithCentralStock(float $quantity): array
    {
        [$user, $origin, $destination, $variant] = $this->inventoryContext();
        $origin->update([
            'codigo' => InventarioStockService::KIZEO_ORIGIN_LOCATION_CODE,
            'nombre' => 'Sede Central SAEP',
        ]);
        app(InventarioStockService::class)->registerReceipt([
            'ubicacion_id' => $origin->id,
            'proveedor_id' => null,
            'tipo_documento' => 'GUIA_DESPACHO',
            'numero_documento' => 'GD-AUTO-'.uniqid(),
            'fecha_documento' => null,
            'fecha_recepcion' => now()->toDateString(),
            'observacion' => null,
        ], [['variante_id' => $variant->id, 'cantidad' => $quantity, 'costo_unitario' => null]], $user);

        return [$user, $origin, $destination, $variant];
    }

    private function newKizeoDelivery(string $dataId, InventarioVariante $variant, float $quantity, $createdAt, string $size = 'M'): EntregaBodega
    {
        $delivery = EntregaBodega::create([
            'kizeo_form_id' => '1195951',
            'kizeo_data_id' => $dataId,
            'kizeo_record_number' => random_int(9000, 9999),
            'origen_formulario' => 'Registro de Entrega Bodega',
            'flujo_inventario' => 'SALIDA',
            'estado_fuente' => 'ACTIVA',
            'nombre' => 'Trabajador automático',
            'fecha_pedido' => now()->toDateString(),
            'kizeo_created_at' => $createdAt,
            'kizeo_updated_at' => $createdAt,
        ]);
        $delivery->items()->create([
            'linea' => 1,
            'articulo' => $variant->producto->nombre,
            'talla' => $size,
            'cantidad' => $quantity,
        ]);

        return $delivery->load('items');
    }

    private function ensureConfiguracionesTable(): void
    {
        if (Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->string('tipo', 40)->nullable();
            $table->string('categoria', 80)->nullable();
            $table->string('descripcion', 500)->nullable();
            $table->boolean('editable')->default(true);
            $table->timestamps();
        });
    }
}
