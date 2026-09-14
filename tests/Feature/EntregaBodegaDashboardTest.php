<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioEntregaKizeoLinea;
use App\Models\InventarioMovimiento;
use App\Models\InventarioProducto;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Services\EntregaBodegaAnalyticsService;
use App\Services\EntregaBodegaExcelExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntregaBodegaDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach ([
            'inventario_entrega_kizeo_lineas', 'inventario_entrega_kizeo_aplicaciones', 'inventario_movimientos',
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
