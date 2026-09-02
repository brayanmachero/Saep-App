<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioEntregaKizeoLinea;
use App\Models\InventarioProducto;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Services\EntregaBodegaAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntregaBodegaDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_analytics_groups_deliveries_and_units_by_day(): void
    {
        EntregaBodega::query()->delete();

        $this->createDelivery('2026-07-20', 8);
        $this->createDelivery('2026-07-20', 12);
        $this->createDelivery('2026-07-22', 5);

        $analytics = (new EntregaBodegaAnalyticsService)->getFilteredAnalytics([
            'fecha_desde' => '2026-07-01',
            'fecha_hasta' => '2026-07-31',
        ]);

        $this->assertSame([
            ['label' => '2026-07-20', 'entregas' => 2, 'unidades' => 20, 'valor_referencial' => 0.0],
            ['label' => '2026-07-22', 'entregas' => 1, 'unidades' => 5, 'valor_referencial' => 0.0],
        ], $analytics['by_day']);
    }

    public function test_analytics_excludes_the_legacy_form_from_the_epp_dashboard(): void
    {
        EntregaBodega::query()->delete();

        $this->createDelivery('2026-09-01', 3, '1195951');
        $this->createDelivery('2026-09-01', 7, '947762');

        $analytics = (new EntregaBodegaAnalyticsService)->getFilteredAnalytics([
            'fecha_desde' => '2026-09-01',
            'fecha_hasta' => '2026-09-01',
        ]);

        $this->assertSame(1, $analytics['total']);
        $this->assertSame(3, $analytics['unidades']);
        $this->assertSame([
            ['label' => '2026-09-01', 'entregas' => 1, 'unidades' => 3, 'valor_referencial' => 0.0],
        ], $analytics['by_day']);
        $this->assertSame([], $analytics['filter_options']['articulos']);
    }

    public function test_analytics_values_linked_lines_and_unambiguous_exact_catalogue_matches(): void
    {
        EntregaBodega::query()->delete();

        $delivery = $this->createDelivery('2026-09-01', 2);
        $delivery->items()->create([
            'linea' => 1,
            'articulo' => 'Casco de prueba',
            'talla' => 'ESTANDAR',
            'cantidad' => 2,
        ]);

        $location = InventarioUbicacion::create([
            'codigo' => 'TEST-EPP-'.uniqid(),
            'nombre' => 'Ubicación de prueba EPP',
        ]);
        $product = InventarioProducto::create([
            'codigo' => 'TEST-EPP-'.uniqid(),
            'nombre' => 'Casco de prueba',
        ]);
        $variant = InventarioVariante::create([
            'producto_id' => $product->id,
            'talla' => 'ESTANDAR',
            'costo_referencia' => 12345,
        ]);
        $application = InventarioEntregaKizeoAplicacion::create([
            'entrega_bodega_id' => $delivery->id,
            'ubicacion_id' => $location->id,
            'estado' => 'APLICADA',
        ]);
        InventarioEntregaKizeoLinea::create([
            'aplicacion_id' => $application->id,
            'linea_fuente' => 1,
            'articulo_fuente' => 'Casco de prueba',
            'talla_fuente' => 'ESTANDAR',
            'cantidad_fuente' => 2,
            'producto_id' => $product->id,
            'variante_id' => $variant->id,
        ]);
        $historicalDelivery = $this->createDelivery('2026-09-01', 1);
        $historicalDelivery->items()->create([
            'linea' => 1,
            'articulo' => 'Casco de prueba',
            'talla' => 'ESTANDAR',
            'cantidad' => 1,
        ]);

        $analytics = (new EntregaBodegaAnalyticsService)->getFilteredAnalytics([
            'fecha_desde' => '2026-09-01',
            'fecha_hasta' => '2026-09-01',
        ]);

        $this->assertSame(3, $analytics['unidades_valorizadas']);
        $this->assertSame(0, $analytics['unidades_sin_precio']);
        $this->assertSame(37035.0, $analytics['valor_referencial']);
        $this->assertSame(12345.0, $analytics['precio_referencia_promedio']);
        $this->assertSame(['Casco de prueba' => 37035.0], $analytics['articulos_valor']);
    }

    private function createDelivery(string $date, int $units, string $formId = '1195951'): EntregaBodega
    {
        return EntregaBodega::create([
            'kizeo_data_id' => uniqid('delivery-', true),
            'kizeo_form_id' => $formId,
            'fecha_pedido' => $date,
            'centro' => 'LTS PENON EST',
            'nombre' => 'Trabajador de prueba',
            'unidades_total' => $units,
        ]);
    }
}
