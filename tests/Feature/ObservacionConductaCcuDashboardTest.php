<?php

namespace Tests\Feature;

use App\Mail\ObservacionConductaCcuReporteMail;
use App\Models\ConsentimientoDatos;
use App\Models\ObservacionConductaCcu;
use App\Models\Rol;
use App\Models\User;
use App\Services\KizeoService;
use App\Services\ObservacionConductaCcuAnalyticsService;
use App\Services\ObservacionConductaCcuSyncService;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ObservacionConductaCcuDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_sync_resolves_ccu_center_and_classifies_all_observation_types_as_negative(): void
    {
        ObservacionConductaCcu::query()->delete();

        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')
            ->once()
            ->with('1156826', false)
            ->andReturn([
                ['id' => 'ccu-siempre', 'record_number' => '1', 'create_time' => '2026-07-20 08:00:00'],
                ['id' => 'ccu-nunca', 'record_number' => '2', 'create_time' => '2026-07-20 09:00:00'],
                ['id' => 'ccu-mixto', 'record_number' => '3', 'create_time' => '2026-07-20 10:00:00'],
            ]);
        $kizeo->shouldReceive('getListItems')
            ->once()
            ->with('483239')
            ->andReturn([
                ['id' => 'center-id', 'label' => 'CCU CENTRAL'],
            ]);
        $kizeo->shouldReceive('getRecord')
            ->once()
            ->with('1156826', 'ccu-siempre')
            ->andReturn($this->record('ccu-siempre', '01.SIEMPRE utilizar todos los EPP'));
        $kizeo->shouldReceive('getRecord')
            ->once()
            ->with('1156826', 'ccu-nunca')
            ->andReturn($this->record('ccu-nunca', '06. NUNCA girar con carga en altura'));
        $kizeo->shouldReceive('getRecord')
            ->once()
            ->with('1156826', 'ccu-mixto')
            ->andReturn($this->record('ccu-mixto', [
                '01.SIEMPRE utilizar todos los EPP',
                '06. NUNCA girar con carga en altura',
            ]));

        $summary = (new ObservacionConductaCcuSyncService($kizeo))->sync();

        $this->assertSame(3, $summary['created']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame('CCU CENTRAL', ObservacionConductaCcu::where('kizeo_data_id', 'ccu-siempre')->value('centro'));
        $this->assertSame('Turno A', ObservacionConductaCcu::where('kizeo_data_id', 'ccu-siempre')->value('turno'));
        $this->assertSame('Negativa', ObservacionConductaCcu::where('kizeo_data_id', 'ccu-siempre')->value('clasificacion'));
        $this->assertSame('Negativa', ObservacionConductaCcu::where('kizeo_data_id', 'ccu-nunca')->value('clasificacion'));
        $this->assertSame('Negativa', ObservacionConductaCcu::where('kizeo_data_id', 'ccu-mixto')->value('clasificacion'));
    }

    public function test_analytics_respects_center_and_classification_filters(): void
    {
        ObservacionConductaCcu::query()->delete();

        $this->createObservation('CCU CENTRAL', 'Negativa', '2026-07-05', 'Ana Trabajadora', 'Turno A');
        $this->createObservation('CCU CENTRAL', 'Negativa', '2026-07-06', 'Ana Trabajadora', 'Turno B');
        $this->createObservation('CCU RENCA', 'Negativa', '2026-07-06', 'Luis Trabajador', 'Turno A');

        $analytics = (new ObservacionConductaCcuAnalyticsService())->getFilteredAnalytics([
            'centro' => 'CCU CENTRAL',
            'clasificacion' => 'Negativa',
            'trabajador_nombre' => 'Ana Trabajadora',
            'turno' => 'Turno B',
        ]);

        $this->assertSame(1, $analytics['total']);
        $this->assertSame(0, $analytics['positivas']);
        $this->assertSame(1, $analytics['negativas']);
        $this->assertSame(['CCU CENTRAL' => 1], $analytics['centros']);
        $this->assertSame(['Turno B' => 1], $analytics['turnos']);
        $this->assertContains('Ana Trabajadora', $analytics['filter_options']['trabajadores']);
        $this->assertContains('Turno B', $analytics['filter_options']['turnos']);
    }

    public function test_report_email_is_branded_and_includes_filtered_detail(): void
    {
        ObservacionConductaCcu::query()->delete();
        $this->createObservation('CCU CENTRAL', 'Negativa', '2026-07-06');
        $analytics = new ObservacionConductaCcuAnalyticsService();
        $filters = ['fecha_desde' => '2026-07-01', 'fecha_hasta' => '2026-07-31'];

        $mail = new ObservacionConductaCcuReporteMail(
            analytics: $analytics->getFilteredAnalytics($filters),
            records: $analytics->getFilteredRecords($filters),
            filters: $filters,
            dashboardUrl: 'https://saep.bmachero.com/observaciones-ccu?fecha_desde=2026-07-01',
            recipientName: 'Usuario de prueba',
            attachExcel: false,
        );

        $html = $mail->render();

        $this->assertStringContainsString('Logo_Saep_email.png', $html);
        $this->assertStringContainsString('Reporte de observaciones CCU', $html);
        $this->assertStringContainsString('Reinducción inmediata (RI)', $html);
        $this->assertStringContainsString('Abrir dashboard con estos filtros', $html);
    }

    public function test_current_user_can_request_report_to_own_email_only(): void
    {
        ObservacionConductaCcu::query()->delete();
        $this->createObservation('CCU CENTRAL', 'Negativa', '2026-07-06');
        $user = $this->createSuperAdminUser();
        Mail::fake();

        $this->actingAs($user)
            ->post(route('pdr-ccu-dashboard.email-self'), [
                'fecha_desde' => '2026-07-01',
                'fecha_hasta' => '2026-07-31',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(ObservacionConductaCcuReporteMail::class, function (ObservacionConductaCcuReporteMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->analytics['total'] === 1
                && $mail->filters['fecha_desde'] === '2026-07-01';
        });
    }

    private function record(string $id, string|array $type): array
    {
        return [
            'id' => $id,
            'record_number' => '1',
            'create_time' => '2026-07-20 08:00:00',
            'answer_time' => '2026-07-20 08:00:00',
            'fields' => [
                'centro_de_distribucion' => ['value' => 'center-id'],
                'fecha' => ['value' => '2026-07-20'],
                'turno' => ['value' => 'Turno A'],
                'negativa_1' => [
                    'value' => is_array($type) ? implode(', ', $type) : $type,
                    'valuesAsArray' => is_array($type) ? $type : [$type],
                ],
                'nombre_del_observador' => ['value' => 'Observador de prueba'],
                'cargo' => ['value' => 'Prevencionista'],
                'nombre_del_trabajador' => ['value' => '11111111-1'],
                'nombre_trabajador_observado' => ['value' => 'Trabajador de prueba'],
                'cargo1' => ['value' => 'Operario'],
                'antiguedad_en_el_cargo' => ['value' => '4-12 meses'],
                'conducta_observada' => ['value' => 'Detalle de prueba'],
                'medida_de_control' => ['value' => 'RI'],
                'retroalimentacion' => ['value' => 'Retroalimentacion de prueba'],
            ],
        ];
    }

    private function createObservation(string $center, string $classification, string $date, string $worker = 'Trabajador de prueba', string $turno = 'Turno A'): void
    {
        ObservacionConductaCcu::create([
            'kizeo_data_id' => uniqid('ccu-', true),
            'fecha_observacion' => $date,
            'centro' => $center,
            'turno' => $turno,
            'observador_nombre' => 'Observador de prueba',
            'trabajador_nombre' => $worker,
            'tipo_observacion' => 'Regla de prueba',
            'clasificacion' => $classification,
            'medida_control' => $classification === 'Negativa' ? 'RI' : null,
            'synced_at' => now(),
        ]);
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::firstOrCreate(
            ['codigo' => 'SUPER_ADMIN'],
            [
                'nombre' => 'Super Administrador',
                'puede_crear_forms' => true,
                'puede_aprobar' => true,
                'puede_ver_dashboard' => true,
                'puede_admin_usuarios' => true,
            ]
        );
        $user = User::create([
            'name' => 'Super Admin CCU Test',
            'email' => 'ccu-superadmin-' . uniqid() . '@saep.local',
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
