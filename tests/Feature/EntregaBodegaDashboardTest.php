<?php

namespace Tests\Feature;

use App\Models\EntregaBodega;
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
            ['label' => '2026-07-20', 'entregas' => 2, 'unidades' => 20],
            ['label' => '2026-07-22', 'entregas' => 1, 'unidades' => 5],
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
            ['label' => '2026-09-01', 'entregas' => 1, 'unidades' => 3],
        ], $analytics['by_day']);
        $this->assertSame([], $analytics['filter_options']['articulos']);
    }

    private function createDelivery(string $date, int $units, string $formId = '1195951'): void
    {
        EntregaBodega::create([
            'kizeo_data_id' => uniqid('delivery-', true),
            'kizeo_form_id' => $formId,
            'fecha_pedido' => $date,
            'centro' => 'LTS PENON EST',
            'nombre' => 'Trabajador de prueba',
            'unidades_total' => $units,
        ]);
    }
}
