<?php

namespace Tests\Feature;

use App\Mail\InspeccionPreventivaPdrReporteMail;
use App\Models\ConsentimientoDatos;
use App\Models\InspeccionPreventivaPdr;
use App\Models\Rol;
use App\Models\User;
use App\Services\InspeccionPreventivaPdrAnalyticsService;
use App\Services\InspeccionPreventivaPdrSyncService;
use App\Services\KizeoService;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class InspeccionPreventivaPdrDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_flattens_conditions_evidence_and_corrective_measures(): void
    {
        InspeccionPreventivaPdr::query()->delete();
        $kizeo = Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('getFormData')->once()->with('973787', false)->andReturn([
            ['id' => 'inspection-1', 'record_number' => '321', 'create_time' => '2026-07-21 08:00:00'],
        ]);
        $kizeo->shouldReceive('getRecord')->once()->with('973787', 'inspection-1')->andReturn($this->record());

        $summary = (new InspeccionPreventivaPdrSyncService($kizeo))->sync();
        $inspection = InspeccionPreventivaPdr::where('kizeo_data_id', 'inspection-1')->firstOrFail();

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame('CCU RENCA', $inspection->centro);
        $this->assertSame(2, $inspection->condiciones_count);
        $this->assertSame(3, $inspection->evidencias_count);
        $this->assertSame(2, $inspection->medidas_count);
        $this->assertSame('|Inmediata|Semanal|', $inspection->frecuencias_text);
        $this->assertSame('|Durante el día|Durante la semana|', $inspection->verificaciones_text);
    }

    public function test_analytics_filters_by_inspector_and_measure_frequency(): void
    {
        InspeccionPreventivaPdr::query()->delete();
        $this->createInspection('CCU RENCA', 'Flavio Liguen', 'Inmediata', '2026-07-08', 2, 2);
        $this->createInspection('CCU RENCA', 'Otro Inspector', 'Semanal', '2026-07-09', 1, 1);
        $this->createInspection('CCU MODELO', 'Flavio Liguen', 'Inmediata', '2026-07-10', 3, 1);

        $analytics = (new InspeccionPreventivaPdrAnalyticsService())->getFilteredAnalytics([
            'centro' => 'CCU RENCA', 'inspector_nombre' => 'Flavio Liguen', 'frecuencia' => 'Inmediata',
        ]);

        $this->assertSame(1, $analytics['total']);
        $this->assertSame(2, $analytics['condiciones']);
        $this->assertSame(2, $analytics['medidas']);
        $this->assertSame(2, $analytics['inmediatas']);
        $this->assertSame(['CCU RENCA' => 1], $analytics['centros']);
        $this->assertContains('Inmediata', $analytics['filter_options']['frecuencias']);
    }

    public function test_report_is_branded_and_current_user_receives_only_own_report(): void
    {
        InspeccionPreventivaPdr::query()->delete();
        $this->createInspection('CCU RENCA', 'Flavio Liguen', 'Inmediata', '2026-07-08', 2, 2);
        $analytics = new InspeccionPreventivaPdrAnalyticsService();
        $filters = ['fecha_desde' => '2026-07-01', 'fecha_hasta' => '2026-07-31'];
        $mail = new InspeccionPreventivaPdrReporteMail(
            $analytics->getFilteredAnalytics($filters), $analytics->getFilteredRecords($filters), $filters,
            'https://saep.bmachero.com/inspecciones-preventivas', 'Usuario de prueba', false,
        );
        $this->assertStringContainsString('Logo_Saep_email.png', $mail->render());
        $this->assertStringContainsString('Inspecciones Preventivas', $mail->render());

        $user = $this->createSuperAdminUser();
        Mail::fake();
        $this->actingAs($user)->post(route('pdr-inspecciones-dashboard.email-self'), $filters)
            ->assertRedirect()->assertSessionHas('success');
        Mail::assertSent(InspeccionPreventivaPdrReporteMail::class, fn ($sent) => $sent->hasTo($user->email) && $sent->analytics['total'] === 1);
    }

    private function record(): array
    {
        return [
            'id' => 'inspection-1', 'record_number' => '321', 'create_time' => '2026-07-21 08:00:00',
            'fields' => [
                'centro_de_distribucion' => ['value' => 'CCU RENCA'], 'fecha_' => ['value' => '2026-07-21'], 'hora_' => ['value' => '19:34'],
                'responsable_area_' => ['value' => 'Francisco Cabezas'], 'inspeccion_efectuada_por_' => ['value' => 'Flavio Liguen'],
                'cargo_' => ['value' => 'Prevencionista'], 'areas_inspeccionadas_' => ['value' => 'Retorno fletero'], 'objetivo_1' => ['value' => 'Insp. Planeada.'],
                'inspeccion1' => ['value' => [
                    ['descripcion_de_la_accion_o_co' => ['value' => 'Desorden en zona de retorno'], 'registro_fotografico_en_cas' => ['value' => 'photo-a,photo-b']],
                    ['descripcion_de_la_accion_o_co' => ['value' => 'Señalética deteriorada'], 'registro_fotografico_en_cas' => ['value' => 'photo-c']],
                ]],
                'medidas_correctivas_y_o_p1' => ['value' => [
                    ['medidas_correctivas_preventiv' => ['value' => 'Ordenar la zona'], 'frecuencia' => ['value' => 'Inmediata'], 'responsable_de_ejecucion' => ['value' => 'Francisco Cabezas'], 'verificacion' => ['value' => 'Durante el día']],
                    ['medidas_correctivas_preventiv' => ['value' => 'Revisar señalética'], 'frecuencia' => ['value' => 'Semanal'], 'responsable_de_ejecucion' => ['value' => 'Mantención'], 'verificacion' => ['value' => 'Durante la semana']],
                ]],
            ],
        ];
    }

    private function createInspection(string $center, string $inspector, string $frequency, string $date, int $conditions, int $measures): void
    {
        InspeccionPreventivaPdr::create([
            'kizeo_data_id' => uniqid('inspection-', true), 'fecha_inspeccion' => $date, 'centro' => $center,
            'inspector_nombre' => $inspector, 'area_inspeccionada' => 'Bodega picking', 'objetivo' => 'Insp. Planeada.',
            'condiciones_count' => $conditions, 'evidencias_count' => 1, 'medidas_count' => $measures,
            'frecuencias_text' => implode('', array_fill(0, $measures, '|' . $frequency)) . '|',
            'verificaciones_text' => implode('', array_fill(0, $measures, '|Durante el día')) . '|', 'synced_at' => now(),
        ]);
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::firstOrCreate(['codigo' => 'SUPER_ADMIN'], ['nombre' => 'Super Administrador', 'puede_crear_forms' => true, 'puede_aprobar' => true, 'puede_ver_dashboard' => true, 'puede_admin_usuarios' => true]);
        $user = User::create(['name' => 'Super Admin Inspecciones Test', 'email' => 'inspection-superadmin-' . uniqid() . '@saep.local', 'rol_id' => $role->id, 'password' => Hash::make('Saep2026!'), 'activo' => true, 'acepta_politica_datos' => true, 'fecha_aceptacion_politica' => now(), 'must_change_password' => false]);
        ConsentimientoDatos::create(['user_id' => $user->id, 'version_politica' => PrivacyPolicy::VERSION, 'texto_aceptado' => PrivacyPolicy::internalConsentText(), 'ip_address' => '127.0.0.1', 'user_agent' => 'Feature test', 'fecha_aceptacion' => now(), 'vigente' => true]);
        return $user;
    }
}
