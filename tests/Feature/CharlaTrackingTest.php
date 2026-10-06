<?php

namespace Tests\Feature;

use App\Console\Commands\KizeoCharlaWeeklyReport;
use App\Jobs\DashboardSyncJob;
use App\Mail\CharlaTrackingReporteMail;
use App\Models\CharlaTrackingActionLog;
use App\Models\ConsentimientoDatos;
use App\Models\KizeoCharlaTracking;
use App\Models\Rol;
use App\Models\User;
use App\Services\KizeoService;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CharlaTrackingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_uses_local_chart_asset_and_carries_active_filters(): void
    {
        KizeoCharlaTracking::query()->delete();

        $this->createTracking('2026-06-10', 'completado', 'terminado', [
            'titulo_actividad' => 'Charla Centro Norte',
            'lugar' => 'Centro Norte',
        ]);

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('charla-tracking.index', [
                'desde' => '2026-06-01',
                'hasta' => '2026-06-30',
                'estado' => 'todos',
                'buscar' => 'Centro Norte',
            ]))
            ->assertOk()
            ->assertSee('vendor/chartjs/chart.umd.js', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false)
            ->assertSee('Filtros activos', false)
            ->assertSee('Desde: 2026-06-01', false)
            ->assertSee('Hasta: 2026-06-30', false)
            ->assertSee('Buscar: Centro Norte', false)
            ->assertSee('charla-tracking/email-preview?desde=2026-06-01', false)
            ->assertSee('name="buscar" value="Centro Norte"', false);
    }

    public function test_report_builder_respects_active_filters(): void
    {
        KizeoCharlaTracking::query()->delete();

        $this->createTracking('2026-06-10', 'completado', 'terminado', [
            'titulo_actividad' => 'Charla Centro Norte',
            'lugar' => 'Centro Norte',
            'asignado_a' => 'Trabajador Norte',
        ]);
        $this->createTracking('2026-06-12', 'pendiente', 'transferido', [
            'titulo_actividad' => 'Charla Centro Sur',
            'lugar' => 'Centro Sur',
            'asignado_a' => 'Trabajador Sur',
        ]);
        $this->createTracking('2026-05-10', 'completado', 'terminado', [
            'titulo_actividad' => 'Charla Centro Norte',
            'lugar' => 'Centro Norte',
        ]);

        $data = KizeoCharlaWeeklyReport::buildReportDataFromFilters([
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'buscar' => 'Centro Norte',
        ]);

        $this->assertSame(1, $data['stats']['total']);
        $this->assertSame(1, $data['stats']['completadas']);
        $this->assertSame(0, $data['stats']['transferidos']);
        $this->assertSame('Centro Norte', $data['filters']['buscar']);
        $this->assertSame('01/06/2026 al 30/06/2026', $data['periodo']);
    }

    public function test_historic_mode_uses_kizeo_registration_date_and_keeps_pending_assignments(): void
    {
        KizeoCharlaTracking::query()->delete();
        $this->createTracking('2026-08-20', 'completado', 'terminado', [
            'fecha_registro_kizeo' => Carbon::parse('2026-09-10 12:00:00'),
            'fecha_respuesta' => Carbon::parse('2026-09-10 12:00:00'),
        ]);
        $this->createTracking('2026-09-12', 'completado', 'registrado', [
            'fecha_registro_kizeo' => Carbon::parse('2026-09-15 12:00:00'),
            'fecha_respuesta' => Carbon::parse('2026-09-15 12:00:00'),
        ]);
        $this->createTracking('2026-09-20', 'transferido', 'transferido', [
            'fecha_registro_kizeo' => Carbon::parse('2026-09-20 12:00:00'),
            'fecha_respuesta' => null,
        ]);

        $historico = KizeoCharlaWeeklyReport::buildReportDataFromFilters([
            'desde' => '2026-09-01', 'hasta' => '2026-09-30', 'tipo_fecha' => 'registro',
        ]);
        $asignaciones = KizeoCharlaWeeklyReport::buildReportDataFromFilters([
            'desde' => '2026-09-01', 'hasta' => '2026-09-30', 'tipo_fecha' => 'creacion',
        ]);

        $this->assertSame(3, $historico['stats']['total']);
        $this->assertSame(2, $asignaciones['stats']['total']);
        $this->assertSame(1, $asignaciones['stats']['transferidos']);
        $this->assertSame(1, $historico['stats']['transferidos']);

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('charla-tracking.index', [
                'desde' => '2026-09-01', 'hasta' => '2026-09-30', 'tipo_fecha' => 'registro',
            ]))
            ->assertOk()
            ->assertSee('Histórico de registros Kizeo')
            ->assertSee('Registros Kizeo');
    }

    public function test_sync_rejects_incomplete_kizeo_batch_without_changing_existing_records(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        config()->set('services.kizeo.charla_form_id', '973784');
        KizeoCharlaTracking::query()->delete();
        $existing = $this->createTracking('2026-10-05', 'completado', 'registrado');

        $kizeo = \Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('rawPost')->once()->andReturn([
            'recordsFiltered' => 2,
            'data' => [['_id' => 'one', '_create_time' => '2026-10-06 10:00:00']],
        ]);
        $this->app->instance(KizeoService::class, $kizeo);

        $this->artisan('kizeo:sync-charla-tracking', ['--months' => 0])->assertExitCode(1);
        $this->assertDatabaseHas('kizeo_charla_tracking', ['id' => $existing->id]);
        Carbon::setTestNow();
    }

    public function test_sync_preserves_pending_state_when_history_has_registration_date(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        config()->set('services.kizeo.charla_form_id', '973784');
        KizeoCharlaTracking::query()->delete();

        $kizeo = \Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('rawPost')->once()->andReturn([
            'recordsFiltered' => 1,
            'data' => [[
                '_id' => 123, '_create_time' => '2026-10-06 10:00:00',
                '_answer_time' => '', '_user_name' => 'Persona de prueba',
            ]],
        ]);
        $kizeo->shouldReceive('rawGet')->once()->andReturn([
            'data' => [[
                'id' => 123, 'create_time' => '2026-10-06 10:00:00',
                'answer_time' => '2026-10-06 11:00:00', 'direction' => 'in',
            ]],
        ]);
        $this->app->instance(KizeoService::class, $kizeo);

        $this->artisan('kizeo:sync-charla-tracking', ['--months' => 0])->assertExitCode(0);
        $this->assertDatabaseHas('kizeo_charla_tracking', [
            'kizeo_data_id' => '123', 'estado' => 'pendiente',
            'fecha_respuesta' => null,
            'fecha_registro_kizeo' => '2026-10-06 11:00:00',
        ]);
        Carbon::setTestNow();
    }

    public function test_completed_transfer_does_not_reconstruct_current_kizeo_recipient(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        config()->set('services.kizeo.charla_form_id', '973784');
        $kizeo = \Mockery::mock(KizeoService::class);
        $kizeo->shouldReceive('rawPost')->once()->andReturn([
            'recordsFiltered' => 1,
            'data' => [[
                '_id' => 123, '_create_time' => '2026-10-06 10:00:00',
                '_answer_time' => '2026-10-06 11:00:00',
                '_user_name' => 'SMU Sop', '_user_id' => 'user-smu',
                '_recipient_name' => '', '_recipient_id' => null,
                'antecedentes' => 'ACTIVIDAD',
                'lugar_de_la_capacitacion' => 'Centro de prueba',
                'titulo_actividad' => 'Título real de prueba',
                '_history' => 'Transferido por Prevencion a SMU Sop el 2026-10-06 10:00:00',
            ]],
        ]);
        $kizeo->shouldReceive('rawGet')->once()->andReturn([
            'data' => [[
                'id' => 123, 'create_time' => '2026-10-06 10:00:00',
                'answer_time' => '2026-10-06 11:00:00',
            ]],
        ]);
        $this->app->instance(KizeoService::class, $kizeo);
        try {
            $this->artisan('kizeo:sync-charla-tracking', ['--months' => 0])->assertExitCode(0);
            $record = KizeoCharlaTracking::where('kizeo_data_id', '123')->firstOrFail();
            $this->assertSame('Centro de prueba', $record->lugar);
            $this->assertSame('Título real de prueba', $record->titulo_actividad);
            $this->assertNull($record->asignado_a);
            $this->assertNull($record->asignado_a_id);
            $this->assertSame('completado', $record->estado);
            $this->assertSame('SMU Sop', $record->metadata['destinatario_historico']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sync_is_queued_and_audited(): void
    {
        $user = $this->createSuperAdminUser();
        CharlaTrackingActionLog::query()->delete();
        Cache::forget(DashboardSyncJob::runningKey('charlas'));
        Queue::fake();

        $this->actingAs($user)
            ->post(route('charla-tracking.sync'))
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'segundo plano'))
            ->assertSessionMissing('error');

        Queue::assertPushed(DashboardSyncJob::class, function (DashboardSyncJob $job) use ($user) {
            return $job->key === 'charlas'
                && $job->command === 'kizeo:sync-charla-tracking'
                && $job->arguments === ['--months' => 6]
                && $job->requestedByUserId === $user->id
                && $job->auditChannel === 'charla'
                && $job->queue === DashboardSyncJob::QUEUE
                && $job->connection === DashboardSyncJob::CONNECTION;
        });

        $log = CharlaTrackingActionLog::query()->where('action', 'sync')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('queued', $log->status);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(DashboardSyncJob::QUEUE, $log->metadata['queue'] ?? null);
    }

    public function test_send_now_passes_active_filters_and_is_audited(): void
    {
        $user = $this->createSuperAdminUser();
        CharlaTrackingActionLog::query()->delete();

        Artisan::shouldReceive('call')
            ->once()
            ->with('kizeo:charla-weekly-report', [
                '--sync' => true,
                '--desde' => '2026-06-01',
                '--hasta' => '2026-06-30',
                '--tipo_fecha' => 'creacion',
                '--estado' => 'pendiente',
                '--buscar' => 'Centro Norte',
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Reporte OK');

        $this->actingAs($user)
            ->post(route('charla-tracking.send-report'), [
                'desde' => '2026-06-01',
                'hasta' => '2026-06-30',
                'estado' => 'pendiente',
                'buscar' => 'Centro Norte',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Reporte OK'));

        $log = CharlaTrackingActionLog::query()->where('action', 'report_send_now')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('pendiente', $log->filters['estado'] ?? null);
        $this->assertSame('Centro Norte', $log->filters['buscar'] ?? null);
    }

    public function test_weekly_report_command_uses_filters_and_audits_execution(): void
    {
        KizeoCharlaTracking::query()->delete();
        CharlaTrackingActionLog::query()->delete();
        Mail::fake();

        $this->createTracking('2026-06-10', 'completado', 'terminado', [
            'titulo_actividad' => 'Charla Centro Norte',
            'lugar' => 'Centro Norte',
        ]);
        $this->createTracking('2026-06-12', 'pendiente', 'transferido', [
            'titulo_actividad' => 'Charla Centro Sur',
            'lugar' => 'Centro Sur',
        ]);

        $this->artisan('kizeo:charla-weekly-report', [
            '--email' => 'destino@charlas.test',
            '--desde' => '2026-06-01',
            '--hasta' => '2026-06-30',
            '--buscar' => 'Centro Norte',
        ])->assertExitCode(0);

        Mail::assertSent(CharlaTrackingReporteMail::class, function (CharlaTrackingReporteMail $mail) {
            return ($mail->stats['total'] ?? 0) === 1
                && $mail->periodo === '01/06/2026 al 30/06/2026';
        });

        $log = CharlaTrackingActionLog::query()->where('action', 'report_scheduled_send')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('Centro Norte', $log->filters['buscar'] ?? null);
        $this->assertSame('destino@charlas.test', $log->metadata['sent'][0] ?? null);
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();

        $user = User::create([
            'name' => 'Super Admin Charlas Test',
            'email' => 'charlas-superadmin-' . uniqid() . '@saep.local',
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

    private function createTracking(
        string $date,
        string $estado,
        string $estatusKizeo,
        array $overrides = []
    ): KizeoCharlaTracking {
        $fecha = Carbon::parse($date);

        return KizeoCharlaTracking::create(array_merge([
            'kizeo_data_id' => 'charla-' . uniqid(),
            'kizeo_form_id' => 'form-charlas',
            'asignado_por' => 'Prevencionista Test',
            'asignado_por_id' => 'u-1',
            'asignado_a' => 'Trabajador Test',
            'asignado_a_id' => 'u-2',
            'titulo_actividad' => 'Charla SST Test',
            'lugar' => 'Centro Test',
            'estado' => $estado,
            'estatus_kizeo' => $estatusKizeo,
            'fecha_creacion' => $fecha,
            'fecha_registro_kizeo' => $fecha,
            'fecha_asignacion' => $fecha,
            'fecha_respuesta' => $estado === 'completado' ? $fecha->copy()->addHour() : null,
            'semana' => (int) $fecha->isoWeek(),
            'anio' => (int) $fecha->isoWeekYear(),
            'metadata' => [],
        ], $overrides));
    }
}
