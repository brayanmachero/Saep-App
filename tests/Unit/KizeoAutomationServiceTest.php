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

    public function test_build_context_resolves_advanced_list_id_from_result_when_value_is_empty(): void
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
            ->andReturn([
                ['id' => 'a1386ffb-fbf9-4d43-a662-d7264fb80280', 'label' => 'CCU RANCAGUA'],
            ]);

        $context = $this->buildContext($kizeo, [
            'fields' => [
                'centro_de_distribucion' => [
                    'type' => 'select',
                    'value' => null,
                    'result' => 'a1386ffb-fbf9-4d43-a662-d7264fb80280',
                ],
            ],
            'create_time' => '2026-07-15 09:34:00',
        ]);

        $this->assertSame('CCU RANCAGUA', $context['centro_de_distribucion']);
        $this->assertSame('a1386ffb-fbf9-4d43-a662-d7264fb80280', $context['centro_de_distribucion_id']);
    }

    public function test_build_context_handles_nested_advanced_list_values_without_failing_the_webhook(): void
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
            ->andReturn([
                ['id' => 'a1386ffb-fbfd-47e4-94b7-a99dfe5be53a', 'label' => 'CCU SANTIAGO SUR'],
            ]);

        $context = $this->buildContext($kizeo, [
            'fields' => [
                'centro_de_distribucion' => [
                    'type' => 'select',
                    'value' => 'a1386ffb-fbfd-47e4-94b7-a99dfe5be53a',
                    'valuesAsArray' => [
                        ['value' => ['id' => 'a1386ffb-fbfd-47e4-94b7-a99dfe5be53a']],
                    ],
                ],
            ],
            'create_time' => '2026-07-27 14:45:00',
        ]);

        $this->assertSame('CCU SANTIAGO SUR', $context['centro_de_distribucion']);
    }

    public function test_event_date_drives_investigation_folder_and_filename_context(): void
    {
        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('rawGet')
            ->once()
            ->with('forms/1200400', 20)
            ->andReturn([
                'form' => [
                    'fields' => [
                        'nombre_del_lesionado' => ['caption' => 'Nombre del lesionado', 'type' => 'text'],
                        'tipo_de_incidente' => ['caption' => 'Tipo de Incidente', 'type' => 'choice'],
                        'fecha_del_accidente' => ['caption' => 'Fecha del Evento', 'type' => 'datetime'],
                        'cd' => ['caption' => 'CD', 'type' => 'text'],
                    ],
                ],
            ]);

        $context = $this->buildContextForForm($kizeo, '1200400', [
            'fields' => [
                'nombre_del_lesionado' => ['value' => 'Ana Pérez'],
                'tipo_de_incidente' => ['value' => 'Trabajo'],
                'fecha_del_accidente' => ['value' => ['date' => '2026-08-29', 'hour' => '10:15']],
                'cd' => ['value' => 'CD Quilicura'],
            ],
            // Debe ignorarse para construir la ruta; es posterior al evento.
            'create_time' => '2026-09-07 09:30:00',
            'record_number' => 'INV-123',
        ]);

        $this->assertSame('2026-08-29', $context['fecha']);
        $this->assertSame('2026', $context['anio']);
        $this->assertSame('08', $context['mes']);
        $this->assertSame('Agosto', $context['mes_nombre']);
        $this->assertSame('Ana Pérez', $context['nombre_del_lesionado']);
        $this->assertSame('Trabajo', $context['tipo_de_incidente']);
        $this->assertSame('CD Quilicura', $context['cd']);
    }

    private function buildContext(KizeoService $kizeo, array $record): array
    {
        return $this->buildContextForForm($kizeo, '1156826', $record);
    }

    private function buildContextForForm(KizeoService $kizeo, string $formId, array $record): array
    {
        $service = new KizeoAutomationService($kizeo, Mockery::mock(OneDriveService::class));
        $method = new ReflectionMethod($service, 'buildContext');
        $method->setAccessible(true);

        return $method->invoke($service, $formId, '275857458', [], $record);
    }
}
