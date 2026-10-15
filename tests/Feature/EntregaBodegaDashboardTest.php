<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioEntregaKizeoLinea;
use App\Models\InventarioMovimiento;
use App\Models\InventarioIngreso;
use App\Models\InventarioProducto;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Services\EntregaBodegaAnalyticsService;
use App\Services\EntregaBodegaExcelExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EntregaBodegaDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::disableForeignKeyConstraints();
        foreach ([
            'inventario_entrega_kizeo_lineas', 'inventario_entrega_kizeo_aplicaciones', 'inventario_movimientos', 'inventario_ingresos',
            'inventario_centros_costo', 'inventario_variantes', 'inventario_productos', 'inventario_ubicaciones',
            'entrega_bodega_items', 'entregas_bodega',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('entregas_bodega', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id')->unique();
            $table->string('kizeo_form_id')->nullable();
            $table->unsignedInteger('kizeo_record_number')->nullable();
            $table->string('centro')->nullable();
            $table->string('rut')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_pedido')->nullable();
            $table->string('flujo_inventario')->default('SALIDA');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventario_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('inventario_productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('inventario_variantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('talla')->nullable();
            $table->decimal('costo_referencia', 14, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('inventario_centros_costo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('inventario_entrega_kizeo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entrega_bodega_id');
            $table->unsignedBigInteger('ubicacion_id');
            $table->string('estado')->default('APLICADA');
            $table->timestamps();
        });
        Schema::create('inventario_entrega_kizeo_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aplicacion_id');
            $table->unsignedSmallInteger('linea_fuente');
            $table->string('articulo_fuente');
            $table->string('talla_fuente')->nullable();
            $table->decimal('cantidad_fuente', 14, 3);
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('variante_id');
            $table->unsignedBigInteger('movimiento_id')->nullable();
            $table->timestamps();
        });
        Schema::create('inventario_ingresos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('tipo_ingreso');
            $table->timestamp('reversado_en')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->timestamps();
        });
        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('tipo');
            $table->string('origen');
            $table->unsignedBigInteger('ubicacion_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('variante_id');
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 2)->nullable();
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('destinatario_nombre')->nullable();
            $table->string('destinatario_rut')->nullable();
            $table->string('centro_costo')->nullable();
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->timestamp('ocurrido_en');
            $table->string('registrado_por_nombre')->nullable();
            $table->timestamps();
        });
    }

    public function test_analytics_nets_kizeo_deliveries_and_returns_in_the_assigned_cost_center(): void
    {
        $center = InventarioCentroCosto::create(['nombre' => 'LTS QUILICURA']);
        $this->createAppliedMovement('SALIDA', -5, 'KIZEO_EPP', $center, '2026-09-02');
        $this->createAppliedMovement('ENTRADA', 2, 'KIZEO_EPP_DEVOLUCION', $center, '2026-09-02');

        $analytics = (new EntregaBodegaAnalyticsService)->getFilteredAnalytics([
            'fecha_desde' => '2026-09-02',
            'fecha_hasta' => '2026-09-02',
        ]);

        $this->assertSame(2, $analytics['total']);
        $this->assertSame(5.0, $analytics['entregadas']);
        $this->assertSame(2.0, $analytics['devueltas']);
        $this->assertSame(3.0, $analytics['netas']);
        $this->assertSame(500.0, $analytics['valor_entregado']);
        $this->assertSame(200.0, $analytics['valor_devuelto']);
        $this->assertSame(300.0, $analytics['valor_neto']);
        $this->assertSame([
            [
                'centro' => 'LTS QUILICURA',
                'comprobantes' => 2,
                'entregadas' => 5.0,
                'devueltas' => 2.0,
                'netas' => 3.0,
                'valor_neto' => 300.0,
            ],
        ], $analytics['centros']);

        $filters = ['fecha_desde' => '2026-09-02', 'fecha_hasta' => '2026-09-02'];
        $path = (new EntregaBodegaExcelExport)->generate(
            $analytics,
            (new EntregaBodegaAnalyticsService)->getFilteredRecords($filters),
            $filters,
        );
        $this->assertFileExists($path);
        $book = IOFactory::load($path);
        $this->assertSame(['Resumen neto', 'Operaciones EPP', 'Detalle EPP'], $book->getSheetNames());
        $this->assertSame('Unidades devueltas', $book->getSheetByName('Resumen neto')->getCell('C4')->getValue());
        $this->assertSame(3.0, $book->getSheetByName('Resumen neto')->getCell('D5')->getValue());
        $this->assertSame('Devuelto', $book->getSheetByName('Operaciones EPP')->getCell('H1')->getValue());
        $this->assertSame('Neto', $book->getSheetByName('Detalle EPP')->getCell('J1')->getValue());
        @unlink($path);
    }

    public function test_analytics_uses_return_corrections_to_reduce_the_returned_balance(): void
    {
        $center = InventarioCentroCosto::create(['nombre' => 'LTS PEÑÓN']);
        $application = $this->createAppliedMovement('ENTRADA', 3, 'KIZEO_EPP_DEVOLUCION', $center, '2026-09-03', 'CORREGIDA');
        $this->createCorrectionMovement($application, -1, $center, '2026-09-03');

        $analytics = (new EntregaBodegaAnalyticsService)->getFilteredAnalytics([
            'fecha_desde' => '2026-09-03',
            'fecha_hasta' => '2026-09-03',
        ]);

        $this->assertSame(0.0, $analytics['entregadas']);
        $this->assertSame(2.0, $analytics['devueltas']);
        $this->assertSame(-2.0, $analytics['netas']);
        $this->assertSame(-200.0, $analytics['valor_neto']);
        $this->assertSame(2.0, $analytics['centros'][0]['devueltas']);
    }

    public function test_analytics_excludes_legacy_unapplied_and_reversed_kizeo_records(): void
    {
        $center = InventarioCentroCosto::create(['nombre' => 'LTS CENTRAL']);
        $this->createAppliedMovement('SALIDA', -4, 'KIZEO_EPP', $center, '2026-09-04', 'REVERSADA');
        $this->createAppliedMovement('SALIDA', -7, 'KIZEO_EPP', $center, '2026-09-04', 'APLICADA', '947762');

        $analytics = (new EntregaBodegaAnalyticsService)->getFilteredAnalytics([
            'fecha_desde' => '2026-09-04',
            'fecha_hasta' => '2026-09-04',
        ]);

        $this->assertSame(0, $analytics['total']);
        $this->assertSame(0.0, $analytics['netas']);
        $this->assertSame([], $analytics['centros']);
    }

    public function test_inventory_returns_update_totals_filters_details_and_excel_without_kizeo_reference(): void
    {
        $center = InventarioCentroCosto::create(['nombre' => 'CENTRO DEVOLUCIONES']);
        $this->createAppliedMovement('SALIDA', -5, 'KIZEO_EPP', $center, '2026-10-13');
        $first = $this->createInventoryReturn($center, 2, '2026-10-13');
        $this->createInventoryReturn($center, 1, '2026-10-13');
        $filters = ['centro' => $center->nombre, 'fecha_desde' => '2026-10-13', 'fecha_hasta' => '2026-10-13'];
        $service = new EntregaBodegaAnalyticsService;
        $analytics = $service->getFilteredAnalytics($filters);
        $this->assertSame(3, $analytics['total']); // Receipt IDs can coincide with Kizeo application IDs.
        $this->assertSame(5.0, $analytics['entregadas']);
        $this->assertSame(3.0, $analytics['devueltas']);
        $this->assertSame(2.0, $analytics['netas']);
        $this->assertSame(300.0, $analytics['valor_devuelto']);
        $this->assertSame(200.0, $analytics['valor_neto']);
        $this->assertSame(3, $analytics['centros'][0]['comprobantes']);
        $returns = $service->getFilteredRecords($filters)->where('tipo', 'Devolución');
        $this->assertCount(2, $returns);
        $this->assertNull($returns->first()->entrega);
        $this->assertTrue($returns->pluck('referencia')->contains($first->ingreso->codigo));
        $this->assertSame(3.0, $service->getFilteredAnalytics($filters + ['articulo' => 'Guantes devolución', 'talla' => 'M'])['devueltas']);
        $this->assertSame(0.0, $service->getFilteredAnalytics($filters + ['trabajador' => 'Persona de prueba'])['devueltas']);
        $this->assertSame(0.0, $service->getFilteredAnalytics(['fecha_desde' => '2026-10-14'])['devueltas']);
        $path = (new EntregaBodegaExcelExport)->generate($analytics, $service->getFilteredRecords($filters), $filters);
        try {
            $book = IOFactory::load($path);
            $this->assertSame(3.0, $book->getSheetByName('Resumen neto')->getCell('C5')->getValue());
            $this->assertSame('Comprobante', $book->getSheetByName('Operaciones EPP')->getCell('A1')->getValue());
            $references = array_column($book->getSheetByName('Operaciones EPP')->toArray(), 0);
            $this->assertContains($first->ingreso->codigo, $references);
        } finally {
            @unlink($path);
        }
    }

    public function test_only_active_inventory_returns_count_and_can_populate_a_dashboard_without_kizeo(): void
    {
        $center = InventarioCentroCosto::create(['nombre' => 'CENTRO SOLO DEVOLUCIONES']);
        $this->createInventoryReturn($center, 2.5, '2026-10-13');
        $cancelled = $this->createInventoryReturn($center, 10, '2026-10-13');
        $cancelled->ingreso->update(['reversado_en' => '2026-10-13 16:00:00']);
        $reverse = $cancelled->replicate();
        $reverse->fill(['codigo' => 'REV-'.uniqid(), 'origen' => 'REVERSO_INGRESO_BODEGA', 'tipo' => 'REVERSO', 'cantidad' => -10])->save();
        $purchase = $this->createInventoryReturn($center, 100, '2026-10-13');
        $purchase->ingreso->update(['tipo_ingreso' => 'COMPRA']);
        $purchase->update(['origen' => 'INGRESO_BODEGA', 'tipo' => 'INGRESO_COMPRA']);
        $service = new EntregaBodegaAnalyticsService;
        $this->assertTrue($service->hasSyncedData());
        $analytics = $service->getFilteredAnalytics();
        $this->assertSame(1, $analytics['total']);
        $this->assertSame(2.5, $analytics['devueltas']);
        $this->assertSame(-2.5, $analytics['netas']);
        $this->assertSame(250.0, $analytics['valor_devuelto']);
        $this->assertContains($center->nombre, $analytics['filter_options']['centros']);
        $this->assertSame([], $analytics['filter_options']['trabajadores']);
        $this->assertSame(0, $analytics['personas']);
    }

    public function test_summary_exports_all_centers_including_zero_and_inactive_without_article_breakdown(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $center = InventarioCentroCosto::create(['nombre' => sprintf('Centro %02d', $i), 'activo' => $i !== 13]);
            if ($i <= 11) {
                $this->createInventoryReturn($center, 1, '2026-10-15');
            }
        }
        $service = new EntregaBodegaAnalyticsService;
        $analytics = $service->getFilteredAnalytics();
        $this->assertCount(10, $analytics['centros']);
        $this->assertCount(13, $analytics['centros_exportacion']);
        $path = (new EntregaBodegaExcelExport)->generate($analytics, $service->getFilteredRecords(), []);
        try {
            $sheet = IOFactory::load($path)->getSheetByName('Resumen neto');
            $rows = $sheet->rangeToArray('A11:H23', null, true, false);
            $this->assertSame('Centro 13', $rows[12][0]);
            $this->assertEquals(0, $rows[12][5]);
            $this->assertEquals(-1100, array_sum(array_column($rows, 5)));
            $this->assertEquals(-209, array_sum(array_column($rows, 6)));
            $this->assertEquals(-1309, array_sum(array_column($rows, 7)));
            $this->assertSame(23, $sheet->getHighestDataRow());
        } finally {
            @unlink($path);
        }
    }

    private function createInventoryReturn(InventarioCentroCosto $center, float $quantity, string $date): InventarioMovimiento
    {
        $location = InventarioUbicacion::firstOrCreate(['nombre' => 'Sede Central SAEP'], ['codigo' => 'CENTRAL']);
        $product = InventarioProducto::firstOrCreate(['nombre' => 'Guantes devolución']);
        $variant = InventarioVariante::firstOrCreate(['producto_id' => $product->id, 'talla' => 'M'], ['costo_referencia' => 100]);
        $receipt = InventarioIngreso::create(['codigo' => 'ING-'.uniqid(), 'tipo_ingreso' => 'DEVOLUCION_EPP', 'fecha_recepcion' => $date]);
        return InventarioMovimiento::create([
            'codigo' => 'MOV-'.uniqid(), 'tipo' => 'DEVOLUCION_EPP', 'origen' => 'DEVOLUCION_BODEGA',
            'ubicacion_id' => $location->id, 'producto_id' => $product->id, 'variante_id' => $variant->id,
            'cantidad' => $quantity, 'costo_unitario' => 100,
            'referencia_tipo' => InventarioIngreso::class, 'referencia_id' => $receipt->id,
            'centro_costo_id' => $center->id, 'centro_costo' => $center->nombre, 'ocurrido_en' => $date,
        ]);
    }

    private function createAppliedMovement(
        string $flow,
        float $quantity,
        string $origin,
        InventarioCentroCosto $center,
        string $date,
        string $state = 'APLICADA',
        string $formId = '1195951',
    ): InventarioEntregaKizeoAplicacion {
        $suffix = uniqid();
        $location = InventarioUbicacion::firstOrCreate(['nombre' => 'Sede Central SAEP'], ['codigo' => 'CENTRAL']);
        $product = InventarioProducto::create(['codigo' => 'CASCO-'.$suffix, 'nombre' => 'Casco de prueba']);
        $variant = InventarioVariante::create(['producto_id' => $product->id, 'talla' => 'M', 'costo_referencia' => 100]);
        $delivery = EntregaBodega::create([
            'kizeo_data_id' => 'kz-'.$suffix,
            'kizeo_form_id' => $formId,
            'kizeo_record_number' => random_int(10000, 99999),
            'centro' => $center->nombre,
            'rut' => '11.111.111-1',
            'nombre' => 'Persona de prueba',
            'fecha_pedido' => $date,
            'flujo_inventario' => $flow,
        ]);
        $application = InventarioEntregaKizeoAplicacion::create([
            'entrega_bodega_id' => $delivery->id,
            'ubicacion_id' => $location->id,
            'estado' => $state,
        ]);
        $line = InventarioEntregaKizeoLinea::create([
            'aplicacion_id' => $application->id,
            'linea_fuente' => 1,
            'articulo_fuente' => 'Casco de prueba',
            'talla_fuente' => 'M',
            'cantidad_fuente' => abs($quantity),
            'producto_id' => $product->id,
            'variante_id' => $variant->id,
        ]);
        $movement = InventarioMovimiento::create([
            'codigo' => 'MOV-'.$suffix,
            'tipo' => $flow === 'ENTRADA' ? 'DEVOLUCION_EPP' : 'ENTREGA_EPP',
            'origen' => $origin,
            'ubicacion_id' => $location->id,
            'producto_id' => $product->id,
            'variante_id' => $variant->id,
            'cantidad' => $quantity,
            'referencia_tipo' => InventarioEntregaKizeoLinea::class,
            'referencia_id' => $line->id,
            'destinatario_nombre' => 'Persona de prueba',
            'destinatario_rut' => '11.111.111-1',
            'centro_costo' => $center->nombre,
            'centro_costo_id' => $center->id,
            'ocurrido_en' => $date.' 12:00:00',
            'registrado_por_nombre' => 'Kizeo automático',
        ]);
        $line->update(['movimiento_id' => $movement->id]);

        return $application;
    }

    private function createCorrectionMovement(InventarioEntregaKizeoAplicacion $application, float $quantity, InventarioCentroCosto $center, string $date): void
    {
        $line = $application->lineas()->firstOrFail();
        InventarioMovimiento::create([
            'codigo' => 'COR-'.uniqid(),
            'tipo' => 'REVERSO',
            'origen' => 'CORRECCION_KIZEO_EPP',
            'ubicacion_id' => $application->ubicacion_id,
            'producto_id' => $line->producto_id,
            'variante_id' => $line->variante_id,
            'cantidad' => $quantity,
            'referencia_tipo' => InventarioEntregaKizeoAplicacion::class,
            'referencia_id' => $application->id,
            'centro_costo' => $center->nombre,
            'centro_costo_id' => $center->id,
            'ocurrido_en' => $date.' 14:00:00',
            'registrado_por_nombre' => 'Kizeo automático',
        ]);
    }
}
