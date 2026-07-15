<?php

namespace Tests\Feature;

use App\Models\ConsentimientoDatos;
use App\Models\Modulo;
use App\Models\ProgramaSst;
use App\Models\Rol;
use App\Models\SstActividad;
use App\Models\SstActividadComentario;
use App\Models\SstCategoria;
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

        $categoria = SstCategoria::create([
            'programa_id' => $visible->id,
            'nombre' => 'Operación mensual',
            'orden' => 1,
        ]);
        $pendienteMes = $this->createActividad($categoria, 'Pendiente visible en listado');
        $pendienteMes->seguimiento()->create(['mes' => (int) date('n'), 'programado' => true]);

        $response = $this->actingAs($assigned)
            ->get(route('carta-gantt.index'));

        $response->assertOk()
            ->assertSee('Programa visible asignado')
            ->assertSee('Mi rol: Equipo asignado')
            ->assertSee('Mes actual: 1')
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
        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $seguimiento->id,
            'user_id' => $assigned->id,
            'accion' => 'seguimiento_actualizado',
        ]);
        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $reprogramable->id,
            'user_id' => $assigned->id,
            'accion' => 'actividad_reprogramada',
        ]);
        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $seguimiento->id,
            'user_id' => $assigned->id,
            'accion' => 'actividad_actualizada',
        ]);

        $this->actingAs($assigned)
            ->post(route('carta-gantt.comentarios.store', $seguimiento), [
                'comentario' => 'Se deja comentario operativo desde el equipo asignado.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividad_comentarios', [
            'actividad_id' => $seguimiento->id,
            'user_id' => $assigned->id,
            'comentario' => 'Se deja comentario operativo desde el equipo asignado.',
        ]);
        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $seguimiento->id,
            'user_id' => $assigned->id,
            'accion' => 'comentario_creado',
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

        $this->actingAs($outsider)
            ->post(route('carta-gantt.comentarios.store', $actividad), [
                'comentario' => 'Intento externo de comentario.',
            ])
            ->assertForbidden();
    }

    public function test_assigned_user_can_delete_own_comment_but_not_other_comment(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true]);
        $otherAssigned = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true]);
        $programa = $this->createPrograma($creator, 'Programa comentarios asignados');
        $programa->asignados()->sync([$assigned->id, $otherAssigned->id]);
        $categoria = SstCategoria::create([
            'programa_id' => $programa->id,
            'nombre' => 'Comentarios',
            'orden' => 1,
        ]);
        $actividad = $this->createActividad($categoria, 'Actividad con comentarios');

        $propio = SstActividadComentario::create([
            'actividad_id' => $actividad->id,
            'user_id' => $assigned->id,
            'comentario' => 'Comentario propio.',
        ]);
        $ajeno = SstActividadComentario::create([
            'actividad_id' => $actividad->id,
            'user_id' => $otherAssigned->id,
            'comentario' => 'Comentario de otro usuario.',
        ]);

        $this->actingAs($assigned)
            ->delete(route('carta-gantt.comentarios.destroy', $ajeno))
            ->assertForbidden();

        $this->actingAs($assigned)
            ->delete(route('carta-gantt.comentarios.destroy', $propio))
            ->assertRedirect();

        $this->assertDatabaseMissing('sst_actividad_comentarios', [
            'id' => $propio->id,
        ]);
        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $actividad->id,
            'user_id' => $assigned->id,
            'accion' => 'comentario_eliminado',
        ]);
    }

    public function test_assigned_user_plan_actions_are_logged(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true, 'puede_eliminar' => true]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true, 'puede_editar' => true, 'puede_eliminar' => true]);
        $programa = $this->createPrograma($creator, 'Programa planes trazables');
        $programa->asignados()->sync([$assigned->id]);
        $categoria = SstCategoria::create([
            'programa_id' => $programa->id,
            'nombre' => 'Planes',
            'orden' => 1,
        ]);
        $actividad = $this->createActividad($categoria, 'Actividad con plan');

        $this->actingAs($assigned)
            ->post(route('carta-gantt.plan-accion.store', $actividad), [
                'accion' => 'Revisar evidencia pendiente',
                'responsable' => 'Equipo asignado',
                'fecha_compromiso' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $actividad->id,
            'user_id' => $assigned->id,
            'accion' => 'plan_creado',
        ]);
    }

    public function test_assigned_view_only_user_can_operate_assigned_gantt_with_audit_log(): void
    {
        $creator = $this->createCartaGanttUser(['puede_ver' => true, 'puede_crear' => true, 'puede_editar' => true]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true]);
        $programa = $this->createPrograma($creator, 'Programa operativo asignado');
        $programa->asignados()->sync([$assigned->id]);
        $categoria = SstCategoria::create([
            'programa_id' => $programa->id,
            'nombre' => 'Operacion asignada',
            'orden' => 1,
        ]);

        $seguimiento = $this->createActividad($categoria, 'Avance sin permiso editar');
        $seguimiento->seguimiento()->create(['mes' => 1, 'programado' => true]);

        $reprogramable = $this->createActividad($categoria, 'Reprogramacion sin permiso editar');
        $reprogramable->seguimiento()->create(['mes' => 1, 'programado' => true]);

        $this->actingAs($assigned)
            ->patchJson(route('carta-gantt.seguimiento.update', $seguimiento), ['mes' => 1])
            ->assertOk();

        $this->actingAs($assigned)
            ->post(route('carta-gantt.comentarios.store', $seguimiento), [
                'comentario' => 'Comentario desde usuario asignado con solo lectura global.',
            ])
            ->assertRedirect();

        $this->actingAs($assigned)
            ->put(route('carta-gantt.actividades.update', $seguimiento), [
                'nombre' => 'Avance actualizado por asignado',
                'prioridad' => 'ALTA',
                'estado' => 'EN_PROGRESO',
                'cantidad_programada' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($assigned)
            ->post(route('carta-gantt.actividades.reprogramar', $reprogramable), [
                'mes_original' => 1,
                'mes_nuevo' => max(2, (int) date('n')),
                'motivo' => 'Reprogramacion autorizada por asignacion directa.',
            ])
            ->assertRedirect();

        foreach (['seguimiento_actualizado', 'comentario_creado', 'actividad_actualizada'] as $accion) {
            $this->assertDatabaseHas('sst_actividad_logs', [
                'actividad_id' => $seguimiento->id,
                'user_id' => $assigned->id,
                'accion' => $accion,
            ]);
        }

        $this->assertDatabaseHas('sst_actividad_logs', [
            'actividad_id' => $reprogramable->id,
            'user_id' => $assigned->id,
            'accion' => 'actividad_reprogramada',
        ]);

        $this->actingAs($assigned)
            ->get(route('carta-gantt.show', $programa))
            ->assertOk()
            ->assertSee('Modo equipo asignado')
            ->assertSee('Puedes actualizar avances')
            ->assertSee('Comentarios')
            ->assertSee('Comentario desde usuario asignado con solo lectura global.')
            ->assertSee('Bitácora de cambios')
            ->assertSee('Editar actividad: Avance actualizado por asignado')
            ->assertSee('Acciones rápidas')
            ->assertSee('Ir a actividad')
            ->assertSee('Comentar')
            ->assertSee('Filtro rápido')
            ->assertSee('Mi alcance')
            ->assertSee('Pendientes mes')
            ->assertSee('No hay actividades para este filtro.')
            ->assertDontSee('Editar datos generales del programa')
            ->assertDontSee('Importar CSV')
            ->assertDontSee(route('carta-gantt.importar', $programa), false)
            ->assertDontSee(route('carta-gantt.actividades.store', $categoria), false);
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

    public function test_program_admin_actions_are_logged_and_visible_to_admin(): void
    {
        $creator = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);
        $assigned = $this->createCartaGanttUser(['puede_ver' => true]);
        $programa = $this->createPrograma($creator, 'Programa trazabilidad general');

        $this->actingAs($creator)
            ->put(route('carta-gantt.update', $programa), [
                'nombre' => 'Programa trazabilidad editado',
                'anio' => $programa->anio,
                'estado' => 'ACTIVO',
                'responsable_id' => $assigned->id,
                'asignados' => [$assigned->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividad_logs', [
            'programa_id' => $programa->id,
            'actividad_id' => null,
            'user_id' => $creator->id,
            'accion' => 'programa_actualizado',
        ]);

        $this->actingAs($creator)
            ->post(route('carta-gantt.categorias.store', $programa), [
                'nombre' => 'Categoria trazable',
                'orden' => 1,
            ])
            ->assertRedirect();

        $categoria = SstCategoria::where('programa_id', $programa->id)
            ->where('nombre', 'Categoria trazable')
            ->firstOrFail();

        $this->assertDatabaseHas('sst_actividad_logs', [
            'programa_id' => $programa->id,
            'actividad_id' => null,
            'user_id' => $creator->id,
            'accion' => 'categoria_creada',
        ]);

        $this->actingAs($creator)
            ->delete(route('carta-gantt.categorias.destroy', $categoria))
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividad_logs', [
            'programa_id' => $programa->id,
            'actividad_id' => null,
            'user_id' => $creator->id,
            'accion' => 'categoria_eliminada',
        ]);

        $this->actingAs($creator)
            ->delete(route('carta-gantt.destroy', $programa))
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividad_logs', [
            'programa_id' => $programa->id,
            'actividad_id' => null,
            'user_id' => $creator->id,
            'accion' => 'programa_cerrado',
        ]);

        $this->actingAs($creator)
            ->get(route('carta-gantt.show', $programa))
            ->assertOk()
            ->assertSee('Bitácora del programa')
            ->assertSee('programa actualizado')
            ->assertSee('categoria creada')
            ->assertSee('categoria eliminada')
            ->assertSee('programa cerrado');
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
            'anio' => (int) date('Y'),
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
