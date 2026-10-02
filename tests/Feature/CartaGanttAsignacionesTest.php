<?php

namespace Tests\Feature;

use App\Models\Modulo;
use App\Models\ProgramaSst;
use App\Models\ConsentimientoDatos;
use App\Models\Rol;
use App\Models\SstActividad;
use App\Models\SstCategoria;
use App\Models\SstSeguimientoOcurrencia;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CartaGanttAsignacionesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assigned_user_only_sees_assigned_programs(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true]);

        $visible = $this->createPrograma($creator, 'Programa visible asignado');
        $hidden = $this->createPrograma($creator, 'Programa oculto no asignado');
        $visible->asignados()->sync([$assigned->id]);

        $this->actingAs($assigned)
            ->get(route('carta-gantt.index'))
            ->assertOk()
            ->assertSee('Programa visible asignado')
            ->assertDontSee('Programa oculto no asignado');

        $this->actingAs($assigned)
            ->get(route('carta-gantt.show', $visible))
            ->assertOk();

        $this->actingAs($assigned)
            ->get(route('carta-gantt.show', $hidden))
            ->assertForbidden();
    }

    public function test_assigned_editor_can_update_follow_up_and_reprogram_assigned_activity(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true]);
        $programa = $this->createPrograma($creator, 'Programa ejecución asignada');
        $programa->asignados()->sync([$assigned->id]);

        $categoria = SstCategoria::create([
            'programa_id' => $programa->id,
            'nombre' => 'Cumplimiento mensual',
            'orden' => 1,
        ]);

        $seguimiento = $this->createActividad($categoria, 'Seguimiento asignado');
        $seguimiento->seguimiento()->create(['mes' => 1, 'programado' => true]);

        $reprogramable = $this->createActividad($categoria, 'Actividad reprogramable');
        $reprogramable->seguimiento()->create(['mes' => 1, 'programado' => true]);

        $this->actingAs($assigned)
            ->patchJson(route('carta-gantt.seguimiento.update', $seguimiento), ['mes' => 1])
            ->assertOk()
            ->assertJson(['success' => true, 'realizado' => true]);

        $mesNuevo = max(2, (int) date('n'));

        $this->actingAs($assigned)
            ->post(route('carta-gantt.actividades.reprogramar', $reprogramable), [
                'mes_original' => 1,
                'mes_nuevo' => $mesNuevo,
                'motivo' => 'Se reprograma por disponibilidad del equipo.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sst_reprogramaciones', [
            'actividad_id' => $reprogramable->id,
            'mes_original' => 1,
            'mes_nuevo' => $mesNuevo,
            'reprogramado_por' => $assigned->id,
        ]);

        $this->actingAs($assigned)
            ->put(route('carta-gantt.actividades.update', $seguimiento), [
                'nombre' => 'Seguimiento asignado actualizado',
                'prioridad' => 'ALTA',
                'estado' => 'EN_PROGRESO',
                'cantidad_programada' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividades', [
            'id' => $seguimiento->id,
            'nombre' => 'Seguimiento asignado actualizado',
            'prioridad' => 'ALTA',
        ]);
    }

    public function test_unassigned_user_cannot_view_or_modify_program(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $outsider = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true]);
        $programa = $this->createPrograma($creator, 'Programa privado');
        $categoria = SstCategoria::create([
            'programa_id' => $programa->id,
            'nombre' => 'Privado',
            'orden' => 1,
        ]);
        $actividad = $this->createActividad($categoria, 'Actividad privada');

        $this->actingAs($outsider)
            ->get(route('carta-gantt.show', $programa))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->put(route('carta-gantt.actividades.update', $actividad), [
                'nombre' => 'Intento externo',
                'prioridad' => 'ALTA',
                'estado' => 'EN_PROGRESO',
                'cantidad_programada' => 1,
            ])
            ->assertForbidden();
    }

    public function test_coordinator_role_can_view_all_programs(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $coordinator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true], 'COORDINADOR');

        $first = $this->createPrograma($creator, 'Programa global A');
        $second = $this->createPrograma($creator, 'Programa global B');

        $this->actingAs($coordinator)
            ->get(route('carta-gantt.index'))
            ->assertOk()
            ->assertSee('Programa global A')
            ->assertSee('Programa global B');

        $this->actingAs($coordinator)
            ->get(route('carta-gantt.show', $first))
            ->assertOk();

        $this->actingAs($coordinator)
            ->get(route('carta-gantt.show', $second))
            ->assertOk();
    }

    public function test_assigned_user_cannot_update_program_assignment(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true]);
        $programa = $this->createPrograma($creator, 'Programa asignado sin administración');
        $programa->asignados()->sync([$assigned->id]);

        $this->actingAs($assigned)
            ->put(route('carta-gantt.update', $programa), [
                'nombre' => 'Cambio no autorizado',
                'anio' => $programa->anio,
                'estado' => 'ACTIVO',
                'responsable_id' => $assigned->id,
                'asignados' => [$assigned->id],
            ])
            ->assertForbidden();
    }

    public function test_daily_and_weekly_activities_generate_calendar_occurrences(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $programa = $this->createPrograma($creator, 'Programa mensual octubre');
        $categoria = SstCategoria::create(['programa_id' => $programa->id, 'nombre' => 'Control', 'orden' => 1]);

        $semanal = SstActividad::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Control semanal',
            'periodicidad' => 'SEMANAL',
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-31',
            'cantidad_programada' => 1,
        ]);
        $diaria = SstActividad::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Control diario',
            'periodicidad' => 'DIARIA',
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-04',
            'cantidad_programada' => 1,
        ]);

        $semanal->sincronizarOcurrencias();
        $diaria->sincronizarOcurrencias();

        $this->assertSame(5, SstSeguimientoOcurrencia::where('actividad_id', $semanal->id)->where('programado', true)->count());
        $this->assertDatabaseHas('sst_seguimiento_ocurrencias', [
            'actividad_id' => $semanal->id,
            'fecha_programada' => '2026-10-01',
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-04',
            'programado' => true,
        ]);
        $this->assertDatabaseHas('sst_seguimiento_ocurrencias', [
            'actividad_id' => $semanal->id,
            'fecha_programada' => '2026-10-26',
            'fecha_inicio' => '2026-10-26',
            'fecha_fin' => '2026-10-31',
            'programado' => true,
        ]);
        $this->assertSame(4, SstSeguimientoOcurrencia::where('actividad_id', $diaria->id)->where('programado', true)->count());
    }

    public function test_toggling_a_daily_occurrence_only_updates_that_date_and_its_monthly_summary(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $programa = $this->createPrograma($creator, 'Programa diario granular');
        $categoria = SstCategoria::create(['programa_id' => $programa->id, 'nombre' => 'Control', 'orden' => 1]);
        $actividad = SstActividad::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Checklist diario',
            'periodicidad' => 'DIARIA',
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-04',
            'cantidad_programada' => 1,
        ]);
        $actividad->sincronizarOcurrencias();
        $primera = $actividad->ocurrencias()->whereDate('fecha_programada', '2026-10-01')->firstOrFail();
        $segunda = $actividad->ocurrencias()->whereDate('fecha_programada', '2026-10-02')->firstOrFail();

        $this->actingAs($creator)
            ->patchJson(route('carta-gantt.ocurrencias.toggle', [$actividad, $primera]))
            ->assertOk()
            ->assertJsonPath('ocurrencia.realizado', true)
            ->assertJsonPath('resumen.cantidad_realizada', 1)
            ->assertJsonPath('resumen.cantidad_programada', 4);

        $this->assertTrue($primera->fresh()->realizado);
        $this->assertFalse($segunda->fresh()->realizado);
        $this->assertDatabaseHas('sst_seguimiento', [
            'actividad_id' => $actividad->id,
            'mes' => 10,
            'cantidad_realizada' => 1,
            'realizado' => false,
        ]);
    }

    private function createCartaGanttUser(array $permissions, ?string $roleCode = null): User
    {
        $roleCode ??= 'GANTT_TEST_' . uniqid();
        $role = Rol::updateOrCreate(
            ['codigo' => $roleCode],
            ['nombre' => str_replace('_', ' ', $roleCode)]
        );

        $modulo = Modulo::firstOrCreate(
            ['slug' => 'carta_gantt'],
            [
                'nombre' => 'Carta Gantt',
                'icono' => 'bi-bar-chart-steps',
                'grupo' => 'Prevención SST',
                'orden' => 22,
                'activo' => true,
            ]
        );

        DB::table('rol_modulo')->updateOrInsert(
            ['rol_id' => $role->id, 'modulo_id' => $modulo->id],
            [
                'puede_ver' => (bool) ($permissions['puede_ver'] ?? false),
                'puede_crear' => (bool) ($permissions['puede_crear'] ?? false),
                'puede_editar' => (bool) ($permissions['puede_editar'] ?? false),
                'puede_eliminar' => (bool) ($permissions['puede_eliminar'] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user = User::create([
            'name' => 'Usuario Gantt ' . uniqid(),
            'email' => 'gantt-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);

        ConsentimientoDatos::create([
            'user_id' => $user->id,
            'version_politica' => 'test',
            'texto_aceptado' => 'Consentimiento interno para prueba automatizada.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'fecha_aceptacion' => now(),
            'vigente' => true,
        ]);

        return $user;
    }

    private function createPrograma(User $creator, string $title): ProgramaSst
    {
        return ProgramaSst::create([
            'anio' => 2026,
            'titulo' => $title,
            'estado' => 'ACTIVO',
            'creado_por' => $creator->id,
        ]);
    }

    private function createActividad(SstCategoria $categoria, string $name): SstActividad
    {
        return SstActividad::create([
            'categoria_id' => $categoria->id,
            'nombre' => $name,
            'prioridad' => 'MEDIA',
            'estado' => 'PENDIENTE',
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);
    }
}
