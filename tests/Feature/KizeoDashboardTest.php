<?php

namespace Tests\Feature;

use App\Models\ConsentimientoDatos;
use App\Models\Rol;
use App\Models\User;
use App\Services\KizeoService;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KizeoDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_view_uses_local_chart_asset(): void
    {
        $this->actingAs($this->createSuperAdminUser())
            ->get(route('kizeo.dashboard'))
            ->assertOk()
            ->assertSee('vendor/bootstrap-icons/bootstrap-icons.css', false)
            ->assertSee('vendor/chartjs/chart.umd.js', false)
            ->assertSee('kizeo-chart-fallback', false)
            ->assertSee('renderAlertIcon', false)
            ->assertSee('Indicadores Operativos SST', false)
            ->assertSee('Días con actividad SST', false)
            ->assertDontSee('Cobertura del Periodo', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/bootstrap-icons', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_dashboard_api_normalizes_inverted_date_range(): void
    {
        $payload = $this->emptyDashboardPayload();

        $this->mock(KizeoService::class, function ($mock) use ($payload) {
            $mock->shouldReceive('getDashboardData')
                ->once()
                ->with('2026-06-01', '2026-06-30', false)
                ->andReturn($payload);
        });

        $this->actingAs($this->createSuperAdminUser())
            ->getJson(route('kizeo.api.dashboard', [
                'start_date' => '2026-06-30',
                'end_date' => '2026-06-01',
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_deep_data_api_enforces_minimum_limit_and_normalized_dates(): void
    {
        $this->mock(KizeoService::class, function ($mock) {
            $mock->shouldReceive('getDeepFormData')
                ->once()
                ->with('form-123', '2026-06-01', '2026-06-30', 1, false)
                ->andReturn([]);
        });

        $this->actingAs($this->createSuperAdminUser())
            ->getJson(route('kizeo.api.deep', [
                'formId' => 'form-123',
                'start_date' => '2026-06-30',
                'end_date' => '2026-06-01',
                'limit' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('records', []);
    }

    public function test_force_refresh_reloads_dashboard_form_metadata_cache(): void
    {
        Cache::flush();
        Config::set('services.kizeo.url', 'https://kizeo.test/rest/v3');
        Config::set('services.kizeo.token', 'test-token');

        $forms = [
            ['id' => 'insp-form', 'name' => 'PDR Inspección', 'class' => 'Prevención de Riesgos'],
        ];

        Http::fake([
            'https://kizeo.test/rest/v3/forms' => Http::sequence()
                ->push(['forms' => $forms])
                ->push(['forms' => $forms]),
            'https://kizeo.test/rest/v3/users' => Http::sequence()
                ->push(['data' => ['users' => [['id' => 'u1', 'first_name' => 'Ana', 'last_name' => 'Paz']]]])
                ->push(['data' => ['users' => [['id' => 'u1', 'first_name' => 'Ana', 'last_name' => 'Paz']]]]),
            'https://kizeo.test/rest/v3/forms/insp-form/data/all' => Http::sequence()
                ->push(['data' => [
                    [
                        'id' => 'old-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-01 08:00:00',
                        'update_time' => '2026-06-01 08:00:00',
                    ],
                ]])
                ->push(['data' => [
                [
                    'id' => 'old-1',
                    'user_id' => 'u1',
                    'create_time' => '2026-06-01 08:00:00',
                    'update_time' => '2026-06-01 08:00:00',
                ],
                [
                    'id' => 'new-1',
                    'user_id' => 'u1',
                    'create_time' => '2026-06-02 08:00:00',
                    'update_time' => '2026-06-02 08:00:00',
                ],
            ]]),
        ]);

        $service = new KizeoService();
        $first = $service->getDashboardData('2026-06-01', '2026-06-30', true);
        $this->assertSame(['2026-06-01' => 1], $first['dailyActivity']);

        $second = $service->getDashboardData('2026-06-01', '2026-06-30', true);

        $this->assertSame(2, $second['stats']['total']);
        $this->assertSame([
            '2026-06-01' => 1,
            '2026-06-02' => 1,
        ], $second['dailyActivity']);
    }

    public function test_force_refresh_reloads_deep_all_form_metadata_cache(): void
    {
        Cache::flush();
        Config::set('services.kizeo.url', 'https://kizeo.test/rest/v3');
        Config::set('services.kizeo.token', 'test-token');

        $forms = [
            ['id' => 'insp-form', 'name' => 'PDR Inspección', 'class' => 'Prevención de Riesgos'],
        ];

        Http::fake([
            'https://kizeo.test/rest/v3/forms' => Http::sequence()
                ->push(['forms' => $forms])
                ->push(['forms' => $forms]),
            'https://kizeo.test/rest/v3/users' => Http::sequence()
                ->push(['data' => ['users' => [['id' => 'u1', 'first_name' => 'Ana', 'last_name' => 'Paz']]]])
                ->push(['data' => ['users' => [['id' => 'u1', 'first_name' => 'Ana', 'last_name' => 'Paz']]]]),
            'https://kizeo.test/rest/v3/forms/insp-form/data/all' => Http::sequence()
                ->push(['data' => [
                    [
                        'id' => 'old-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-01 08:00:00',
                        'update_time' => '2026-06-01 08:00:00',
                    ],
                ]])
                ->push(['data' => [
                    [
                        'id' => 'new-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-02 08:00:00',
                        'update_time' => '2026-06-02 08:00:00',
                    ],
                ]]),
            'https://kizeo.test/rest/v3/forms/insp-form/data/old-1' => Http::response([
                'data' => [
                    'id' => 'old-1',
                    'user_id' => 'u1',
                    'create_time' => '2026-06-01 08:00:00',
                    'update_time' => '2026-06-01 08:00:00',
                    'fields' => [],
                ],
            ]),
            'https://kizeo.test/rest/v3/forms/insp-form/data/new-1' => Http::response([
                'data' => [
                    'id' => 'new-1',
                    'user_id' => 'u1',
                    'create_time' => '2026-06-02 08:00:00',
                    'update_time' => '2026-06-02 08:00:00',
                    'fields' => [],
                ],
            ]),
        ]);

        $service = new KizeoService();
        $first = $service->getAllDeepData('2026-06-01', '2026-06-30', true, 15);
        $this->assertSame('old-1', $first['records'][0]['id']);

        $second = $service->getAllDeepData('2026-06-01', '2026-06-30', true, 15);

        $this->assertSame('new-1', $second['records'][0]['id']);
        $this->assertCount(1, $second['records']);
    }

    public function test_historical_compliance_uses_selected_period_end_instead_of_today(): void
    {
        Cache::flush();
        Config::set('services.kizeo.url', 'https://kizeo.test/rest/v3');
        Config::set('services.kizeo.token', 'test-token');

        Http::fake([
            'https://kizeo.test/rest/v3/forms' => Http::response([
                'forms' => [
                    ['id' => 'inc-form', 'name' => 'PDR Incidente', 'class' => 'Prevencion de Riesgos'],
                    ['id' => 'insp-form', 'name' => 'PDR Inspeccion', 'class' => 'Prevencion de Riesgos'],
                ],
            ]),
            'https://kizeo.test/rest/v3/users' => Http::response([
                'data' => [
                    'users' => [
                        ['id' => 'u1', 'first_name' => 'Ana', 'last_name' => 'Paz'],
                    ],
                ],
            ]),
            'https://kizeo.test/rest/v3/forms/inc-form/data/all' => Http::response([
                'data' => [
                    [
                        'id' => 'inc-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-01-10 09:00:00',
                        'update_time' => '2026-01-10 09:00:00',
                    ],
                ],
            ]),
            'https://kizeo.test/rest/v3/forms/insp-form/data/all' => Http::response([
                'data' => [
                    [
                        'id' => 'insp-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-01-31 09:00:00',
                        'update_time' => '2026-01-31 09:00:00',
                    ],
                ],
            ]),
        ]);

        $data = (new KizeoService())->getDashboardData('2026-01-01', '2026-01-31', true);

        $this->assertSame(21, $data['compliance']['diasSinAccidente']);
        $this->assertFalse(collect($data['alerts'])->contains(
            fn (array $alert) => str_contains($alert['title'] ?? '', 'Auditor inactivo')
        ));
    }

    public function test_iso_timestamps_are_grouped_by_calendar_day_for_coverage(): void
    {
        Cache::flush();
        Config::set('services.kizeo.url', 'https://kizeo.test/rest/v3');
        Config::set('services.kizeo.token', 'test-token');

        Http::fake([
            'https://kizeo.test/rest/v3/forms' => Http::response([
                'forms' => [
                    ['id' => 'inc-form', 'name' => 'PDR Incidente', 'class' => 'Prevencion de Riesgos'],
                    ['id' => 'insp-form', 'name' => 'PDR Inspeccion', 'class' => 'Prevencion de Riesgos'],
                ],
            ]),
            'https://kizeo.test/rest/v3/users' => Http::response([
                'data' => [
                    'users' => [
                        ['id' => 'u1', 'first_name' => 'Ana', 'last_name' => 'Paz'],
                    ],
                ],
            ]),
            'https://kizeo.test/rest/v3/forms/inc-form/data/all' => Http::response([
                'data' => [
                    [
                        'id' => 'inc-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-25T15:55:07.000000Z',
                        'update_time' => '2026-06-25T15:55:07.000000Z',
                    ],
                ],
            ]),
            'https://kizeo.test/rest/v3/forms/insp-form/data/all' => Http::response([
                'data' => [
                    [
                        'id' => 'insp-1',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-26T08:00:00.000000Z',
                        'update_time' => '2026-06-26T08:00:00.000000Z',
                    ],
                    [
                        'id' => 'insp-2',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-26T10:15:00.000000Z',
                        'update_time' => '2026-06-26T10:15:00.000000Z',
                    ],
                    [
                        'id' => 'insp-3',
                        'user_id' => 'u1',
                        'create_time' => '2026-06-26T11:30:00.000000Z',
                        'update_time' => '2026-06-26T11:30:00.000000Z',
                    ],
                ],
            ]),
        ]);

        $data = (new KizeoService())->getDashboardData('2026-06-01', '2026-06-30', true);

        $this->assertSame([
            '2026-06-25' => 1,
            '2026-06-26' => 3,
        ], $data['dailyActivity']);
        $this->assertSame(2, $data['compliance']['activeDays']);
        $this->assertSame(30, $data['compliance']['totalDays']);
        $this->assertSame(6.7, $data['compliance']['coverageRate']);
        $this->assertSame('2026-06-25', $data['compliance']['lastIncident']);
    }

    private function emptyDashboardPayload(): array
    {
        return [
            'forms' => [],
            'stats' => [
                'total' => 0,
                'incidentes' => 0,
                'charlas' => 0,
                'inspecciones' => 0,
                'auditores' => 0,
            ],
            'formDistribution' => [],
            'dailyActivity' => [],
            'auditorsData' => [],
            'recentRecords' => [],
            'compliance' => [],
            'calendar' => [],
            'alerts' => [],
            'cached_at' => '2026-06-26 00:00:00',
        ];
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();

        $user = User::create([
            'name' => 'Super Admin Kizeo Test',
            'email' => 'kizeo-superadmin-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);

        ConsentimientoDatos::create([
            'user_id' => $user->id,
            'version_politica' => PrivacyPolicy::VERSION,
            'texto_aceptado' => PrivacyPolicy::internalConsentText(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'fecha_aceptacion' => now(),
            'vigente' => true,
        ]);

        return $user;
    }
}
