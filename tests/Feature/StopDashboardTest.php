<?php

namespace Tests\Feature;

use App\Models\ConsentimientoDatos;
use App\Models\Configuracion;
use App\Models\Rol;
use App\Models\StopActionLog;
use App\Models\StopObservacion;
use App\Models\User;
use App\Mail\StopReporteMail;
use App\Console\Commands\StopWeeklyReport;
use App\Services\StopAnalyticsService;
use App\Services\StopExcelExport;
use App\Support\PrivacyPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

class StopDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_failure_is_reported_as_error_flash(): void
    {
        $user = $this->createSuperAdminUser();
        StopActionLog::query()->delete();

        Artisan::shouldReceive('call')
            ->once()
            ->with('stop:sync-sheets', ['--force' => true])
            ->andThrow(new RuntimeException('fallo de prueba'));

        $this->actingAs($user)
            ->post(route('stop-dashboard.sync'))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'fallo de prueba'))
            ->assertSessionMissing('success');

        $log = StopActionLog::query()->where('action', 'sync')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('fallo de prueba', $log->metadata['error'] ?? null);
    }

    public function test_checklist_analytics_respect_active_filters(): void
    {
        StopObservacion::query()->delete();

        StopObservacion::create([
            'gdrive_file_id' => 'archivo-a',
            'fecha_tarjeta' => '2026-06-10',
            'empresa_observado' => 'SAEP',
            'clasificacion' => 'Negativa',
            'checklist_data' => [
                ['cat' => 'EPP', 'q' => 'Uso de casco', 'val' => 'CUMPLE'],
            ],
        ]);

        StopObservacion::create([
            'gdrive_file_id' => 'archivo-b',
            'fecha_tarjeta' => '2026-06-10',
            'empresa_observado' => 'OTRA',
            'clasificacion' => 'Negativa',
            'checklist_data' => [
                ['cat' => 'EPP', 'q' => 'Uso de casco', 'val' => 'NO CUMPLE'],
            ],
        ]);

        $result = (new StopAnalyticsService())->getChecklistAnalytics([
            'empresa_observado' => 'SAEP',
        ]);

        $this->assertSame(1, $result['categories']['EPP']['total']);
        $this->assertSame(1, $result['categories']['EPP']['cumple']);
        $this->assertSame(0, $result['categories']['EPP']['no_cumple']);
    }

    public function test_sync_info_uses_global_total_and_latest_source_file(): void
    {
        StopObservacion::query()->delete();

        StopObservacion::create([
            'gdrive_file_id' => 'archivo-antiguo',
            'fecha_tarjeta' => '2026-06-10',
            'updated_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        StopObservacion::create([
            'gdrive_file_id' => 'archivo-reciente',
            'fecha_tarjeta' => '2026-06-11',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $syncInfo = (new StopAnalyticsService())->getSyncInfo();

        $this->assertSame(2, (int) $syncInfo['total']);
        $this->assertSame('archivo-reciente', $syncInfo['fileId']);
    }

    public function test_dashboard_uses_local_chart_asset(): void
    {
        StopObservacion::query()->delete();

        StopObservacion::create([
            'gdrive_file_id' => 'archivo-local',
            'fecha_tarjeta' => now()->toDateString(),
            'empresa_observado' => 'SAEP',
            'empresa_observador' => 'SAEP',
            'centro' => 'Centro Test',
            'clasificacion' => 'Positiva',
            'tipo_observacion' => 'Felicitación',
            'checklist_data' => [
                ['cat' => 'EPP', 'q' => 'Uso de casco', 'val' => 'CUMPLE'],
            ],
        ]);

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('stop-dashboard'))
            ->assertOk()
            ->assertSee('vendor/chartjs/chart.umd.js', false)
            ->assertSee('Descargar Excel', false)
            ->assertSee('Filtros activos', false)
            ->assertSee('Observado: SAEP', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_dashboard_shows_recent_stop_activity(): void
    {
        StopObservacion::query()->delete();

        $user = $this->createSuperAdminUser();

        StopObservacion::create([
            'gdrive_file_id' => 'archivo-local',
            'fecha_tarjeta' => now()->toDateString(),
            'empresa_observado' => 'SAEP',
            'empresa_observador' => 'SAEP',
            'centro' => 'Centro Test',
            'clasificacion' => 'Positiva',
            'tipo_observacion' => 'Felicitacion',
        ]);

        StopActionLog::record([
            'user_id' => $user->id,
            'action' => 'sync',
            'status' => 'success',
            'summary' => 'Prueba auditoria STOP',
        ]);

        $this->actingAs($user)
            ->get(route('stop-dashboard'))
            ->assertOk()
            ->assertSee('Actividad reciente STOP', false)
            ->assertSee('Sincronizacion', false)
            ->assertSee('Correcto', false)
            ->assertSee('Prueba auditoria STOP', false);
    }

    public function test_dashboard_and_reports_apply_worker_search_filter(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva', 'Centro Norte', 'Valentin Hernandez');
        $this->createStopObservation('2026-05-10', 'SAEP', 'Negativa', 'Centro Norte', 'Maria Soto');

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('stop-dashboard', [
                'empresa_observado' => 'SAEP',
                'mes' => '2026-05',
                'trabajador' => 'Valentin',
            ]))
            ->assertOk()
            ->assertSee('Trabajador: Valentin', false)
            ->assertSee('>1</h2>', false);

        $data = StopWeeklyReport::buildReportDataFromFilters([
            'empresa_observado' => 'SAEP',
            'mes' => '2026-05',
            'trabajador' => 'Valentin',
        ]);

        $this->assertSame(1, $data['analytics']['totalRows'] ?? null);
        $this->assertSame('Valentin', $data['filters']['trabajador'] ?? null);
    }

    public function test_month_comparison_respects_company_year_month_and_ytd_cutoff(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2026-01-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2026-05-11', 'SAEP', 'Negativa');
        $this->createStopObservation('2026-06-01', 'SAEP', 'Negativa');
        $this->createStopObservation('2026-05-12', 'OTRA', 'Positiva');
        $this->createStopObservation('2025-01-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2025-05-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2025-05-11', 'SAEP', 'Positiva');
        $this->createStopObservation('2025-06-01', 'SAEP', 'Negativa');

        $comparison = (new StopAnalyticsService())->buildComparison([
            'empresa_observado' => 'SAEP',
            'mes' => '2026-05',
        ]);

        $this->assertSame(2026, $comparison['currentYear']);
        $this->assertSame(2025, $comparison['prevYear']['year']);
        $this->assertSame(3, $comparison['ytd']['total']);
        $this->assertSame(1, $comparison['ytd']['pos']);
        $this->assertSame(2, $comparison['ytd']['neg']);
        $this->assertSame(2, $comparison['prevYear']['sameMonthTotal']);
        $this->assertSame(3, $comparison['prevYear']['ytdTotal']);
        $this->assertArrayNotHasKey('2026-06', $comparison['ytd']['byMonth']);
        $this->assertArrayNotHasKey('2025-06', $comparison['prevYear']['byMonth']);
    }

    public function test_year_comparison_uses_selected_year_instead_of_current_calendar_year(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2025-02-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2025-11-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2024-02-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2026-02-10', 'SAEP', 'Negativa');

        $comparison = (new StopAnalyticsService())->buildComparison([
            'empresa_observado' => 'SAEP',
            'anio' => '2025',
        ]);

        $this->assertSame(2025, $comparison['currentYear']);
        $this->assertSame(2024, $comparison['prevYear']['year']);
        $this->assertSame(2, $comparison['ytd']['total']);
        $this->assertSame(1, $comparison['prevYear']['sameMonthTotal']);
        $this->assertSame(1, $comparison['prevYear']['ytdTotal']);
        $this->assertArrayNotHasKey('2026-02', $comparison['ytd']['byMonth']);
    }

    public function test_date_range_comparison_uses_exact_shifted_previous_period_and_ytd_until_end_date(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2026-01-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2026-05-11', 'SAEP', 'Negativa');
        $this->createStopObservation('2026-05-12', 'SAEP', 'Negativa');
        $this->createStopObservation('2025-01-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2025-05-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2025-05-11', 'SAEP', 'Negativa');
        $this->createStopObservation('2025-05-12', 'SAEP', 'Positiva');

        $comparison = (new StopAnalyticsService())->buildComparison([
            'empresa_observado' => 'SAEP',
            'fecha_desde' => '2026-05-10',
            'fecha_hasta' => '2026-05-11',
        ]);

        $this->assertSame(2026, $comparison['currentYear']);
        $this->assertSame(2025, $comparison['prevYear']['year']);
        $this->assertSame(3, $comparison['ytd']['total']);
        $this->assertSame(2, $comparison['prevYear']['sameMonthTotal']);
        $this->assertSame(3, $comparison['prevYear']['ytdTotal']);
        $this->assertSame(2, $comparison['prevYear']['sameMonthNeg']);
    }

    public function test_dashboard_comparison_labels_follow_active_month_filter(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2025-05-10', 'SAEP', 'Negativa');

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('stop-dashboard', [
                'empresa_observado' => 'SAEP',
                'mes' => '2026-05',
            ]))
            ->assertOk()
            ->assertSee('Mismo Periodo 2025', false)
            ->assertSee('Acum. 2026', false)
            ->assertSee('Tendencia Mensual', false)
            ->assertSee('2026 vs 2025', false)
            ->assertSee('Var. Total Periodo', false)
            ->assertSee('Mes: 2026-05', false);
    }

    public function test_dashboard_year_trend_includes_previous_year_ytd_months_and_chart(): void
    {
        StopObservacion::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

        try {
            $this->createStopObservation('2026-01-10', 'SAEP', 'Positiva');
            $this->createStopObservation('2026-06-10', 'SAEP', 'Negativa');
            $this->createStopObservation('2025-01-10', 'SAEP', 'Negativa');
            $this->createStopObservation('2025-05-10', 'SAEP', 'Positiva');
            $this->createStopObservation('2025-06-10', 'SAEP', 'Positiva');

            $this->actingAs($this->createSuperAdminUser())
                ->get(route('stop-dashboard'))
                ->assertOk()
                ->assertSee('yearComparisonChart', false)
                ->assertSee('Evolutivo acumulado hasta Jun', false)
                ->assertSee('data-comparison-month="01" data-current-total="1" data-prev-total="1"', false)
                ->assertSee('data-comparison-month="05" data-current-total="0" data-prev-total="1"', false)
                ->assertSee('data-comparison-month="06" data-current-total="1" data-prev-total="1"', false)
                ->assertSee("label: '2025 Total'", false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stop_report_email_year_trend_keeps_cutoff_months(): void
    {
        StopObservacion::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

        try {
            $this->seedStopYearTrendData();

            $data = StopWeeklyReport::buildReportDataFromFilters([
                'empresa_observado' => 'SAEP',
            ]);

            $html = (new StopReporteMail(
                analytics: $data['analytics'],
                periodo: $data['periodo'],
                mesLabel: $data['mesLabel'],
                frecuencia: 'Semanal',
                comparison: $data['comparison'],
                evalDetail: $data['evalDetail'],
            ))->render();

            $this->assertStringContainsString('Tendencia Mensual', $html);
            $this->assertStringContainsString('2026 vs 2025', $html);
            $this->assertStringContainsString('>Feb</td>', $html);
            $this->assertStringContainsString('>Mar</td>', $html);
            $this->assertStringContainsString('>Abr</td>', $html);
            $this->assertStringContainsString('>May</td>', $html);
            $this->assertStringContainsString('2025 Total', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stop_report_excel_year_trend_keeps_cutoff_months(): void
    {
        StopObservacion::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

        $path = null;

        try {
            $this->seedStopYearTrendData();

            $data = StopWeeklyReport::buildReportDataFromFilters([
                'empresa_observado' => 'SAEP',
            ]);

            $path = (new StopExcelExport())->generate(
                $data['analytics'],
                $data['periodo'],
                'Semanal',
                $data['comparison'],
                $data['evalDetail'],
            );

            $sheet = IOFactory::load($path)->getSheetByName('Comparativa');
            $this->assertNotNull($sheet);

            $rows = $sheet->rangeToArray('A1:G120', null, true, true, true);
            $monthRows = collect($rows)
                ->filter(fn ($row) => in_array($row['A'], ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio'], true))
                ->keyBy('A');

            $this->assertSame(6, $monthRows->count());
            $this->assertSame(1, (int) $monthRows['Enero']['B']);
            $this->assertSame(1, (int) $monthRows['Enero']['E']);
            $this->assertSame(0, (int) $monthRows['Mayo']['B']);
            $this->assertSame(1, (int) $monthRows['Mayo']['E']);
            $this->assertSame(1, (int) $monthRows['Junio']['B']);
            $this->assertSame(1, (int) $monthRows['Junio']['E']);
        } finally {
            if ($path && file_exists($path)) {
                @unlink($path);
            }
            Carbon::setTestNow();
        }
    }

    public function test_stop_weekly_report_command_uses_filtered_report_builder(): void
    {
        StopObservacion::query()->delete();
        Mail::fake();

        $this->createStopObservation('2026-06-10', 'SAEP', 'Negativa', 'Centro Norte');
        $this->createStopObservation('2026-06-11', 'OTRA', 'Positiva', 'Centro Norte');

        $this->artisan('stop:weekly-report', [
            '--email' => 'destino@stop.test',
            '--mes' => '2026-06',
            '--empresa' => 'SAEP',
        ])->assertExitCode(0);

        Mail::assertSent(StopReporteMail::class, function (StopReporteMail $mail) {
            return ($mail->analytics['totalRows'] ?? 0) === 1
                && $mail->periodo === 'junio 2026'
                && ($mail->comparison['currentYear'] ?? null) === 2026;
        });

        $log = StopActionLog::query()->where('action', 'report_scheduled_send')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertNull($log->user_id);
        $this->assertSame('SAEP', $log->filters['empresa_observado'] ?? null);
        $this->assertSame('destino@stop.test', $log->metadata['sent'][0] ?? null);
    }

    public function test_report_preview_preserves_active_filters_and_uses_filtered_data(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva', 'Centro Norte');
        $this->createStopObservation('2026-05-10', 'OTRA', 'Negativa', 'Centro Norte');
        $this->createStopObservation('2026-05-15', 'SAEP', 'Negativa', 'Centro Sur');

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('stop-dashboard.reporte.preview', [
                'empresa_observado' => 'SAEP',
                'centro' => 'Centro Norte',
                'fecha_desde' => '2026-05-10',
                'fecha_hasta' => '2026-05-11',
            ]))
            ->assertOk()
            ->assertSee('1 tarjetas', false)
            ->assertSee('10/05/2026 — 11/05/2026', false)
            ->assertSee('name="empresa_observado"', false)
            ->assertSee('value="SAEP"', false)
            ->assertSee('name="centro"', false)
            ->assertSee('value="Centro Norte"', false)
            ->assertSee('name="fecha_desde"', false)
            ->assertSee('value="2026-05-10"', false);
    }

    public function test_excel_download_uses_full_active_filters_and_is_audited(): void
    {
        StopObservacion::query()->delete();

        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva', 'Centro Norte');
        $this->createStopObservation('2026-05-10', 'OTRA', 'Negativa', 'Centro Norte');
        $this->createStopObservation('2026-05-15', 'SAEP', 'Negativa', 'Centro Sur');

        $this->actingAs($this->createSuperAdminUser())
            ->get(route('stop-dashboard.reporte.excel', [
                'empresa_observado' => 'SAEP',
                'centro' => 'Centro Norte',
                'fecha_desde' => '2026-05-10',
                'fecha_hasta' => '2026-05-11',
            ]))
            ->assertDownload('Reporte_STOP_CCU_10-05-2026-11-05-2026.xlsx');

        $log = StopActionLog::query()->where('action', 'report_excel_download')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('Centro Norte', $log->filters['centro'] ?? null);
        $this->assertSame(1, $log->metadata['total_rows'] ?? null);
    }

    public function test_test_report_uses_full_active_filters_when_sending_mail(): void
    {
        StopObservacion::query()->delete();
        Mail::fake();

        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva', 'Centro Norte');
        $this->createStopObservation('2026-05-10', 'OTRA', 'Negativa', 'Centro Norte');
        $this->createStopObservation('2026-05-15', 'SAEP', 'Negativa', 'Centro Sur');

        $this->actingAs($this->createSuperAdminUser())
            ->post(route('stop-dashboard.reporte.test-send'), [
                'email' => 'destino@stop.test',
                'frecuencia' => 'Semanal',
                'empresa_observado' => 'SAEP',
                'centro' => 'Centro Norte',
                'fecha_desde' => '2026-05-10',
                'fecha_hasta' => '2026-05-11',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(StopReporteMail::class, function (StopReporteMail $mail) {
            return ($mail->analytics['totalRows'] ?? 0) === 1
                && $mail->periodo === '10/05/2026 — 11/05/2026'
                && ($mail->comparison['currentYear'] ?? null) === 2026;
        });

        $log = StopActionLog::query()->where('action', 'report_test_send')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('Centro Norte', $log->filters['centro'] ?? null);
        $this->assertSame('destino@stop.test', $log->metadata['recipient'] ?? null);
    }

    public function test_send_now_uses_full_active_filters_for_configured_recipients(): void
    {
        StopObservacion::query()->delete();
        Mail::fake();

        Configuracion::updateOrCreate(
            ['clave' => 'stop_report_destinatarios'],
            [
                'valor' => 'destino@stop.test',
                'tipo' => 'TEXT',
                'categoria' => 'reportes',
                'descripcion' => 'Destinatarios STOP test',
                'editable' => true,
            ]
        );

        $this->createStopObservation('2026-05-10', 'SAEP', 'Positiva', 'Centro Norte');
        $this->createStopObservation('2026-05-10', 'OTRA', 'Negativa', 'Centro Norte');
        $this->createStopObservation('2026-05-15', 'SAEP', 'Negativa', 'Centro Sur');

        $this->actingAs($this->createSuperAdminUser())
            ->post(route('stop-dashboard.reporte.send-now'), [
                'frecuencia' => 'semanal',
                'empresa_observado' => 'SAEP',
                'centro' => 'Centro Norte',
                'fecha_desde' => '2026-05-10',
                'fecha_hasta' => '2026-05-11',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(StopReporteMail::class, function (StopReporteMail $mail) {
            return ($mail->analytics['totalRows'] ?? 0) === 1
                && $mail->periodo === '10/05/2026 — 11/05/2026'
                && ($mail->comparison['currentYear'] ?? null) === 2026;
        });

        $log = StopActionLog::query()->where('action', 'report_send_now')->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('Centro Norte', $log->filters['centro'] ?? null);
        $this->assertSame(1, $log->metadata['recipients_count'] ?? null);
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::where('codigo', 'SUPER_ADMIN')->firstOrFail();

        $user = User::create([
            'name' => 'Super Admin STOP Test',
            'email' => 'stop-superadmin-' . uniqid() . '@saep.local',
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

    private function seedStopYearTrendData(): void
    {
        $this->createStopObservation('2026-01-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2026-06-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2025-01-10', 'SAEP', 'Negativa');
        $this->createStopObservation('2025-05-10', 'SAEP', 'Positiva');
        $this->createStopObservation('2025-06-10', 'SAEP', 'Positiva');
    }

    private function createStopObservation(
        string $date,
        string $company,
        string $classification,
        string $center = 'Centro Test',
        string $workerName = 'Trabajador Test',
    ): StopObservacion
    {
        return StopObservacion::create([
            'gdrive_file_id' => 'archivo-comparativa',
            'fecha_tarjeta' => $date,
            'empresa_observado' => $company,
            'empresa_observador' => 'SAEP',
            'centro' => $center,
            'clasificacion' => $classification,
            'tipo_observacion' => 'EPP',
            'nombre_observado' => $workerName,
            'checklist_data' => [
                ['cat' => 'EPP', 'q' => 'Uso de casco', 'val' => 'CUMPLE'],
            ],
        ]);
    }
}
