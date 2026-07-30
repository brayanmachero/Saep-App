<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\Modulo;
use App\Models\ProgramaSst;
use App\Models\Rol;
use App\Models\SstReprogramacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CartaGanttTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => config('app.key') ?: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);

        $this->withoutMiddleware([
            VerificarConsentimientoDatos::class,
            ForcePasswordChange::class,
        ]);

        Modulo::updateOrCreate(
            ['slug' => 'carta_gantt'],
            [
                'nombre' => 'Carta Gantt',
                'descripcion' => 'Programas anuales SST',
                'icono' => 'bi-bar-chart-steps',
                'grupo' => 'Prevencion SST',
                'orden' => 22,
                'activo' => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_view_only_user_cannot_create_categories_by_posting_directly(): void
    {
        $programa = $this->createPrograma();
        $user = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => false,
            'puede_eliminar' => false,
        ]);

        $this->actingAs($user)
            ->post(route('carta-gantt.categorias.store', $programa), [
                'nombre' => 'Categoria no autorizada',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('sst_categorias', [
            'programa_id' => $programa->id,
            'nombre' => 'Categoria no autorizada',
        ]);
    }

    public function test_program_can_be_duplicated_as_a_draft_without_operational_progress(): void
    {
        $creator = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);
        $assigned = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => false,
            'puede_eliminar' => false,
        ]);
        $programa = $this->createPrograma($creator);
        $programa->asignados()->sync([$assigned->id]);
        $categoria = $programa->categorias()->create(['nombre' => 'Operaciones', 'orden' => 1]);
        $actividad = $categoria->actividades()->create([
            'nombre' => 'Actividad de plantilla',
            'descripcion' => 'Descripción que debe copiarse',
            'responsable' => $assigned->nombre_completo,
            'responsable_id' => $assigned->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'prioridad' => 'ALTA',
            'estado' => 'COMPLETADA',
            'periodicidad' => 'MENSUAL',
            'cantidad_programada' => 3,
            'orden' => 1,
        ]);
        $actividad->seguimiento()->create([
            'mes' => 7,
            'programado' => true,
            'realizado' => true,
            'observacion' => 'Avance original',
            'actualizado_por' => $creator->id,
            'fecha_actualizacion' => now(),
            'cantidad_realizada' => 3,
        ]);
        $actividad->planesAccion()->create([
            'accion' => 'Plan reutilizable',
            'responsable' => 'Responsable QA',
            'fecha_compromiso' => '2026-08-15',
            'estado' => 'COMPLETADO',
            'observacion' => 'Cierre original',
            'creado_por' => $creator->id,
        ]);
        $actividad->comentarios()->create(['user_id' => $creator->id, 'comentario' => 'Comentario original']);
        SstReprogramacion::create([
            'actividad_id' => $actividad->id,
            'mes_original' => 6,
            'mes_nuevo' => 7,
            'motivo' => 'Motivo original',
            'reprogramado_por' => $creator->id,
        ]);

        $response = $this->actingAs($creator)
            ->post(route('carta-gantt.duplicate', $programa), ['nombre' => 'Programa SST duplicado QA']);

        $copy = ProgramaSst::where('titulo', 'Programa SST duplicado QA')->firstOrFail();
        $response->assertRedirect(route('carta-gantt.edit', $copy));
        $this->assertSame('BORRADOR', $copy->estado);
        $this->assertSame($programa->anio, $copy->anio);
        $this->assertSame($programa->descripcion, $copy->descripcion);
        $this->assertSame($programa->responsable_id, $copy->responsable_id);
        $this->assertTrue($copy->asignados()->whereKey($assigned->id)->exists());

        $copyActivity = $copy->categorias()->firstOrFail()->actividades()->firstOrFail();
        $this->assertSame('Actividad de plantilla', $copyActivity->nombre);
        $this->assertSame('PENDIENTE', $copyActivity->estado);
        $this->assertSame($assigned->id, $copyActivity->responsable_id);
        $this->assertSame(3, $copyActivity->cantidad_programada);

        $copyTracking = $copyActivity->seguimiento()->firstOrFail();
        $this->assertTrue($copyTracking->programado);
        $this->assertFalse($copyTracking->realizado);
        $this->assertSame(0, $copyTracking->cantidad_realizada);
        $this->assertNull($copyTracking->observacion);
        $this->assertNull($copyTracking->actualizado_por);

        $copyPlan = $copyActivity->planesAccion()->firstOrFail();
        $this->assertSame('Plan reutilizable', $copyPlan->accion);
        $this->assertSame('PENDIENTE', $copyPlan->estado);
        $this->assertNull($copyPlan->observacion);
        $this->assertSame($creator->id, $copyPlan->creado_por);
        $this->assertSame(0, $copyActivity->comentarios()->count());
        $this->assertSame(0, $copyActivity->reprogramaciones()->count());
        $this->assertDatabaseHas('sst_actividad_logs', ['programa_id' => $copy->id, 'accion' => 'programa_duplicado']);
        $this->assertDatabaseHas('sst_actividad_logs', ['programa_id' => $programa->id, 'accion' => 'programa_duplicado_como_plantilla']);
    }

    public function test_reprogramming_rejects_month_with_existing_progress(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));

        $user = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => true,
            'puede_eliminar' => false,
        ]);
        $programa = $this->createPrograma($user);
        $categoria = $programa->categorias()->create(['nombre' => 'Seguimiento', 'orden' => 1]);
        $actividad = $categoria->actividades()->create([
            'nombre' => 'Actividad con avance parcial',
            'responsable' => $user->nombre_completo,
            'responsable_id' => $user->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'prioridad' => 'MEDIA',
            'estado' => 'EN_PROGRESO',
            'periodicidad' => 'MENSUAL',
            'cantidad_programada' => 4,
            'orden' => 1,
        ]);
        $seguimiento = $actividad->seguimiento()->create([
            'mes' => 6,
            'programado' => true,
            'realizado' => false,
            'cantidad_realizada' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('carta-gantt.actividades.reprogramar', $actividad), [
                'mes_original' => 6,
                'mes_nuevo' => 8,
                'motivo' => 'Prueba de bloqueo',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('mes_original');

        $seguimiento->refresh();

        $this->assertTrue($seguimiento->programado);
        $this->assertFalse($seguimiento->realizado);
        $this->assertSame(2, $seguimiento->cantidad_realizada);
        $this->assertDatabaseMissing('sst_reprogramaciones', [
            'actividad_id' => $actividad->id,
            'mes_original' => 6,
            'mes_nuevo' => 8,
        ]);
    }

    public function test_responsible_without_edit_permission_does_not_see_reprogram_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));

        $creator = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => false,
        ]);
        $responsable = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => false,
            'puede_eliminar' => false,
        ]);

        $programa = $this->createPrograma($creator);
        $categoria = $programa->categorias()->create(['nombre' => 'Reprogramacion visible', 'orden' => 1]);
        $actividad = $categoria->actividades()->create([
            'nombre' => 'Actividad vencida asignada',
            'responsable' => $responsable->nombre_completo,
            'responsable_id' => $responsable->id,
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'prioridad' => 'MEDIA',
            'estado' => 'PENDIENTE',
            'periodicidad' => 'UNICA',
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);
        $actividad->seguimiento()->create([
            'mes' => 6,
            'programado' => true,
            'realizado' => false,
            'cantidad_realizada' => 0,
        ]);

        $this->actingAs($responsable)
            ->get(route('carta-gantt.show', $programa))
            ->assertOk()
            ->assertDontSee('openReprogramar(' . $actividad->id . ',', false);

        $this->actingAs($creator)
            ->get(route('carta-gantt.show', $programa))
            ->assertOk()
            ->assertSee('openReprogramar(' . $actividad->id . ',', false);
    }

    public function test_editor_who_is_not_program_creator_cannot_update_activity_structure_directly(): void
    {
        $creator = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => false,
        ]);
        $otherEditor = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => true,
            'puede_eliminar' => false,
        ]);

        $programa = $this->createPrograma($creator);
        $categoria = $programa->categorias()->create(['nombre' => 'Estructura', 'orden' => 1]);
        $actividad = $categoria->actividades()->create([
            'nombre' => 'Actividad protegida',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'prioridad' => 'MEDIA',
            'estado' => 'PENDIENTE',
            'periodicidad' => 'UNICA',
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);

        $payload = [
            'nombre' => 'Actividad modificada por externo',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'prioridad' => 'ALTA',
            'estado' => 'EN_PROGRESO',
            'periodicidad' => 'MENSUAL',
            'cantidad_programada' => 2,
            'has_meses_prog' => '1',
            'meses_prog' => [1, 2],
        ];

        $this->actingAs($otherEditor)
            ->put(route('carta-gantt.actividades.update', $actividad), $payload)
            ->assertForbidden();

        $this->assertDatabaseHas('sst_actividades', [
            'id' => $actividad->id,
            'nombre' => 'Actividad protegida',
            'prioridad' => 'MEDIA',
        ]);

        $this->actingAs($creator)
            ->put(route('carta-gantt.actividades.update', $actividad), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('sst_actividades', [
            'id' => $actividad->id,
            'nombre' => 'Actividad modificada por externo',
            'prioridad' => 'ALTA',
        ]);
    }

    public function test_editor_who_is_not_program_creator_cannot_manage_plan_actions_directly(): void
    {
        $creator = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);
        $otherEditor = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);

        $programa = $this->createPrograma($creator);
        $categoria = $programa->categorias()->create(['nombre' => 'Planes', 'orden' => 1]);
        $actividad = $categoria->actividades()->create([
            'nombre' => 'Actividad con planes',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'prioridad' => 'MEDIA',
            'estado' => 'PENDIENTE',
            'periodicidad' => 'UNICA',
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);
        $plan = $actividad->planesAccion()->create([
            'accion' => 'Plan original',
            'responsable' => 'Responsable QA',
            'estado' => 'PENDIENTE',
            'creado_por' => $creator->id,
        ]);

        $this->actingAs($otherEditor)
            ->post(route('carta-gantt.plan-accion.store', $actividad), [
                'accion' => 'Plan no autorizado',
            ])
            ->assertForbidden();

        $this->actingAs($otherEditor)
            ->patch(route('carta-gantt.plan-accion.update', $plan), [
                'estado' => 'COMPLETADO',
                'observacion' => 'Cambio externo',
            ])
            ->assertForbidden();

        $this->actingAs($otherEditor)
            ->delete(route('carta-gantt.plan-accion.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseMissing('sst_plan_accion', [
            'actividad_id' => $actividad->id,
            'accion' => 'Plan no autorizado',
        ]);
        $this->assertDatabaseHas('sst_plan_accion', [
            'id' => $plan->id,
            'estado' => 'PENDIENTE',
        ]);

        $this->actingAs($creator)
            ->patch(route('carta-gantt.plan-accion.update', $plan), [
                'estado' => 'COMPLETADO',
                'observacion' => 'Cierre validado',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sst_plan_accion', [
            'id' => $plan->id,
            'estado' => 'COMPLETADO',
        ]);
    }

    public function test_editor_who_is_not_program_creator_cannot_reprogram_activity_directly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));

        $creator = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => false,
        ]);
        $otherEditor = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => false,
            'puede_editar' => true,
            'puede_eliminar' => false,
        ]);

        $programa = $this->createPrograma($creator);
        $categoria = $programa->categorias()->create(['nombre' => 'Reprogramacion backend', 'orden' => 1]);
        $actividad = $categoria->actividades()->create([
            'nombre' => 'Actividad vencida protegida',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'prioridad' => 'MEDIA',
            'estado' => 'PENDIENTE',
            'periodicidad' => 'UNICA',
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);
        $seguimiento = $actividad->seguimiento()->create([
            'mes' => 6,
            'programado' => true,
            'realizado' => false,
            'cantidad_realizada' => 0,
        ]);

        $payload = [
            'mes_original' => 6,
            'mes_nuevo' => 8,
            'motivo' => 'Prueba de permiso',
        ];

        $this->actingAs($otherEditor)
            ->post(route('carta-gantt.actividades.reprogramar', $actividad), $payload)
            ->assertForbidden();

        $seguimiento->refresh();
        $this->assertTrue($seguimiento->programado);
        $this->assertDatabaseMissing('sst_reprogramaciones', [
            'actividad_id' => $actividad->id,
            'mes_original' => 6,
            'mes_nuevo' => 8,
        ]);

        $this->actingAs($creator)
            ->post(route('carta-gantt.actividades.reprogramar', $actividad), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('sst_reprogramaciones', [
            'actividad_id' => $actividad->id,
            'mes_original' => 6,
            'mes_nuevo' => 8,
        ]);
    }

    public function test_csv_import_rejects_invalid_dates_without_creating_rows(): void
    {
        $user = $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => false,
            'puede_eliminar' => false,
        ]);
        $programa = $this->createPrograma($user);
        $csv = implode("\n", [
            'categoria;nombre;fecha_inicio;fecha_fin',
            'Capacitaciones QA;Actividad fecha mala;99/99/2026;2026-12-31',
        ]);
        $file = UploadedFile::fake()->createWithContent('actividades.csv', $csv);

        $this->actingAs($user)
            ->post(route('carta-gantt.importar', $programa), [
                'archivo' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sst_categorias', [
            'programa_id' => $programa->id,
            'nombre' => 'Capacitaciones QA',
        ]);
        $this->assertDatabaseMissing('sst_actividades', [
            'nombre' => 'Actividad fecha mala',
        ]);
    }

    public function test_cancelled_activities_are_not_counted_as_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));

        $programa = $this->createPrograma();
        $categoria = $programa->categorias()->create(['nombre' => 'Canceladas', 'orden' => 1]);
        $categoria->actividades()->create([
            'nombre' => 'Actividad cancelada vencida',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
            'prioridad' => 'BAJA',
            'estado' => 'CANCELADA',
            'periodicidad' => 'UNICA',
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);

        $this->assertSame(0, $programa->actividades_vencidas);
    }

    private function createPrograma(?User $user = null): ProgramaSst
    {
        $user ??= $this->createCartaGanttUser([
            'puede_ver' => true,
            'puede_crear' => true,
            'puede_editar' => true,
            'puede_eliminar' => true,
        ]);

        return ProgramaSst::create([
            'anio' => 2026,
            'titulo' => 'Programa SST QA ' . uniqid(),
            'descripcion' => 'Programa creado por test',
            'estado' => 'ACTIVO',
            'responsable_id' => $user->id,
            'creado_por' => $user->id,
        ]);
    }

    private function createCartaGanttUser(array $permissions): User
    {
        $role = Rol::create([
            'codigo' => 'CARTA_GANTT_QA_' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Carta Gantt QA',
        ]);
        $modulo = Modulo::where('slug', 'carta_gantt')->firstOrFail();

        $role->modulos()->syncWithoutDetaching([
            $modulo->id => [
                'puede_ver' => $permissions['puede_ver'] ?? false,
                'puede_crear' => $permissions['puede_crear'] ?? false,
                'puede_editar' => $permissions['puede_editar'] ?? false,
                'puede_eliminar' => $permissions['puede_eliminar'] ?? false,
            ],
        ]);

        return User::create([
            'name' => 'Carta Gantt QA',
            'email' => 'carta-gantt-qa-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);
    }
}
