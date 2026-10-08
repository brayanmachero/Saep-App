<?php

namespace Tests\Feature;

use App\Http\Controllers\CartaGanttController;
use App\Models\ProgramaSst;
use App\Models\SstActividad;
use App\Models\User;
use App\Services\CartaGanttPdfReportData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CartaGanttMonthlyProgressTest extends TestCase
{
    private SstActividad $activity;
    private User $editor;
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config(['database.default' => 'gantt_monthly_test', 'database.connections.gantt_monthly_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('centros_costo', fn (Blueprint $t) => $t->id());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_03_23_193042_create_carta_gantt_tables.php',
            '2026_03_24_000001_add_columns_to_carta_gantt_tables.php',
            '2026_04_10_160039_add_cantidad_to_carta_gantt_tables.php',
            '2026_07_14_000001_create_programa_sst_asignados_table.php',
            '2026_07_15_000002_create_sst_actividad_logs_table.php',
            '2026_10_02_150000_add_gantt_view_and_occurrence_tracking.php',
            '2026_10_02_180000_create_sst_seguimiento_semanas.php',
            '2026_10_08_000001_add_permitir_exceder_meta_to_sst_actividades.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('users', fn (Blueprint $t) => $t->softDeletes());
        DB::table('roles')->insert(['id' => 1, 'codigo' => 'QA', 'nombre' => 'QA']);
        DB::table('users')->insert(['id' => 1, 'rol_id' => 1, 'name' => 'Editor', 'email' => 'qa@example.test', 'password' => 'unused']);
        $this->editor = Mockery::mock(User::class)->makePartial();
        $this->editor->id = 1;
        $this->editor->shouldReceive('tieneAcceso')->andReturn(true);
        Auth::setUser($this->editor);
        $program = ProgramaSst::create(['anio' => 2026, 'titulo' => 'Plan QA', 'responsable_id' => 1]);
        $category = $program->categorias()->create(['nombre' => 'Inspecciones']);
        $this->activity = $category->actividades()->create([
            'nombre' => 'Inspección mensual', 'periodicidad' => 'MENSUAL', 'cantidad_programada' => 1,
            'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31',
        ]);
        $this->activity->seguimiento()->create(['mes' => 10, 'programado' => true, 'cantidad_realizada' => 0]);
    }

    protected function tearDown(): void
    {
        DB::purge('gantt_monthly_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function record(int $direction = 1, string $week = '2026-10-05')
    {
        $request = Request::create('/', 'POST', ['mes' => 10, 'semana_inicio' => $week, 'direccion' => $direction]);
        $request->setUserResolver(fn () => $this->editor);
        return app(CartaGanttController::class)->updateSeguimientoSemana($request, $this->activity->fresh());
    }

    private function edit(int $target, bool $allow): void
    {
        $request = Request::create('/', 'PUT', [
            'nombre' => $this->activity->nombre, 'periodicidad' => 'MENSUAL',
            'cantidad_programada' => $target, 'permitir_exceder_meta' => $allow ? '1' : '0',
            'estado' => 'COMPLETADA', 'has_meses_prog' => 1, 'meses_prog' => [10],
        ]);
        $request->setUserResolver(fn () => $this->editor);
        app(CartaGanttController::class)->updateActividad($request, $this->activity->fresh());
    }

    public function test_increasing_completed_target_preserves_one_execution_and_accepts_second(): void
    {
        $this->assertSame(200, $this->record()->status());
        $this->edit(2, false);
        $activity = $this->activity->fresh();
        $this->assertSame('EN_PROGRESO', $activity->estado);
        $this->assertSame(1, $activity->seguimiento_por_mes[10]['cantidad_realizada']);
        $this->assertFalse($activity->seguimiento_por_mes[10]['realizado']);
        $this->assertSame(200, $this->record(1, '2026-10-12')->status());
        $this->assertSame(2, $this->activity->fresh()->seguimiento_por_mes[10]['cantidad_realizada']);
        $this->assertSame('COMPLETADA', $this->activity->fresh()->estado);
        $this->assertSame(422, $this->record()->status());
    }

    public function test_stale_completed_flag_never_fabricates_an_extra_execution(): void
    {
        $this->activity->update(['cantidad_programada' => 2]);
        $this->activity->seguimiento()->update(['cantidad_realizada' => 1, 'realizado' => true]);
        $this->assertFalse($this->activity->fresh()->seguimiento_por_mes[10]['realizado']);
        $this->assertSame(200, $this->record()->status());
        $this->assertSame(2, $this->activity->fresh()->seguimiento_por_mes[10]['cantidad_realizada']);
    }

    public function test_overachievement_reports_150_and_200_percent_and_preserves_history_when_disabled(): void
    {
        $this->edit(2, true);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(200, $this->record()->status());
        }
        $program = $this->activity->fresh()->categoria->programa;
        $this->assertSame(150, $program->porcentaje_realizado);
        $report = (new CartaGanttPdfReportData)->build($program, 'mensual', 10);
        $this->assertSame(3, $report['totalRealizado']);
        $this->assertSame(150, $report['pct']);
        $this->record(1, '2026-10-12');
        $this->assertSame(200, $program->porcentaje_realizado);
        $this->edit(2, false);
        $this->assertSame(422, $this->record()->status());
        $this->assertSame(4, $this->activity->fresh()->seguimiento_por_mes[10]['cantidad_realizada']);
        $this->assertSame(200, $this->record(-1)->status());
        $this->assertSame(3, $this->activity->fresh()->seguimiento_por_mes[10]['cantidad_realizada']);
    }

    public function test_single_target_can_exceed_and_remove_one_at_a_time_even_after_disabling(): void
    {
        $this->edit(1, true);
        $this->record();
        $this->record();
        $this->assertSame(2, $this->activity->fresh()->seguimiento_por_mes[10]['cantidad_realizada']);
        $this->edit(1, false);
        $this->assertSame(422, $this->record()->status());
        $this->record(-1);
        $this->assertSame(1, $this->activity->fresh()->seguimiento_por_mes[10]['cantidad_realizada']);
    }

    public function test_unplanned_month_and_unauthorized_user_cannot_record(): void
    {
        $this->assertSame(422, $this->record(1, '2026-11-01')->status());
        $this->activity->seguimiento()->update(['programado' => false]);
        $this->assertSame(422, $this->record()->status());
        Auth::forgetUser();
        $this->assertSame(403, $this->record()->status());
    }
}
