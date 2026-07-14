<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\Modulo;
use App\Models\ProgramaSst;
use App\Models\Rol;
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
