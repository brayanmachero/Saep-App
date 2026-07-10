<?php

namespace Tests\Unit;

use App\Services\KizeoAutomationService;
use App\Services\KizeoService;
use App\Services\OneDriveService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class KizeoAutomationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_build_context_resolves_advanced_list_ids_to_visible_labels(): void
    {
        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('rawGet')
            ->once()
            ->with('forms/1156826', 20)
            ->andReturn([
                'form' => [
                    'fields' => [
                        'field_123' => [
                            'caption' => 'Centro de Distribucion',
                            'type' => 'select',
                            'list_id' => '483239',
                            'list_is_advanced' => true,
                        ],
                        'nombre_trabajador_observado' => [
                            'caption' => 'Nombre Trabajador Observado',
                            'type' => 'text',
                        ],
                    ],
                ],
            ]);

        $kizeo->shouldReceive('getListItems')
            ->once()
            ->with('483239', false)
            ->andReturn([
                ['id' => 'a1386ffb-fbe3-4158-afbe-987019a2e830', 'label' => 'CCU CENTRAL'],
            ]);

        $context = $this->buildContext($kizeo, [
            'fields' => [
                'field_123' => [
                    'type' => 'select',
                    'value' => 'a1386ffb-fbe3-4158-afbe-987019a2e830',
                    'valuesAsArray' => ['a1386ffb-fbe3-4158-afbe-987019a2e830'],
                ],
                'nombre_trabajador_observado' => [
                    'value' => null,
                    'text' => 'Maria Isabel Barraza Rodriguez',
                    'forced' => false,
                ],
            ],
            'create_time' => '2026-07-10 10:30:00',
            'form_name' => 'Obs. Conducta CCU',
            'record_number' => 'REQ-001',
        ]);

        $this->assertSame('CCU CENTRAL', $context['field_123']);
        $this->assertSame('CCU CENTRAL', $context['centro_de_distribucion']);
        $this->assertSame('a1386ffb-fbe3-4158-afbe-987019a2e830', $context['field_123_id']);
        $this->assertSame('a1386ffb-fbe3-4158-afbe-987019a2e830', $context['centro_de_distribucion_id']);
        $this->assertSame('Maria Isabel Barraza Rodriguez', $context['nombre_trabajador_observado']);
    }

    public function test_build_context_refreshes_advanced_list_cache_when_uuid_is_not_resolved(): void
    {
        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('rawGet')
            ->once()
            ->with('forms/1156826', 20)
            ->andReturn([
                'form' => [
                    'fields' => [
                        'centro_de_distribucion' => [
                            'caption' => 'Centro de Distribucion',
                            'type' => 'select',
                            'list_id' => '483239',
                            'list_is_advanced' => true,
                        ],
                    ],
                ],
            ]);

        $kizeo->shouldReceive('getListItems')
            ->once()
            ->with('483239', false)
            ->andReturn([]);

        $kizeo->shouldReceive('getListItems')
            ->once()
            ->with('483239', true)
            ->andReturn([
                ['id' => 'a1386ffb-fbe3-4158-afbe-987019a2e830', 'label' => 'CCU CENTRAL'],
            ]);

        $context = $this->buildContext($kizeo, [
            'fields' => [
                'centro_de_distribucion' => [
                    'type' => 'select',
                    'value' => 'a1386ffb-fbe3-4158-afbe-987019a2e830',
                    'valuesAsArray' => ['a1386ffb-fbe3-4158-afbe-987019a2e830'],
                ],
            ],
            'create_time' => '2026-07-10 10:30:00',
        ]);

        $this->assertSame('CCU CENTRAL', $context['centro_de_distribucion']);
    }

    private function buildContext(KizeoService $kizeo, array $record): array
    {
        $service = new KizeoAutomationService($kizeo, Mockery::mock(OneDriveService::class));
        $method = new ReflectionMethod($service, 'buildContext');
        $method->setAccessible(true);

        return $method->invoke($service, '1156826', '275857458', [], $record);
    }
}
