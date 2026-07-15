<?php

namespace Tests\Feature;

use App\Mail\KanbanResumenVencimientoMail;
use App\Models\KanbanColumna;
use App\Models\KanbanTablero;
use App\Models\KanbanTarea;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KanbanAlertasVencimientoTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_vencimiento_alerts_are_consolidated_by_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:15:00'));
        Mail::fake();

        $role = Rol::updateOrCreate(
            ['codigo' => 'KANBAN_ALERT_TEST'],
            ['nombre' => 'Kanban Alert Test']
        );

        $user = User::create([
            'name' => 'Asignado Kanban',
            'email' => 'kanban-alert-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
        ]);

        $tablero = KanbanTablero::create([
            'nombre' => 'Tablero prueba alertas',
            'activo' => true,
            'creado_por' => $user->id,
        ]);

        $pendiente = KanbanColumna::create([
            'tablero_id' => $tablero->id,
            'nombre' => 'Pendiente',
            'color' => '#f59e0b',
            'orden' => 1,
        ]);

        KanbanColumna::create([
            'tablero_id' => $tablero->id,
            'nombre' => 'Completado',
            'color' => '#10b981',
            'orden' => 5,
            'es_completada' => true,
        ]);

        $vencida = KanbanTarea::create([
            'tablero_id' => $tablero->id,
            'columna_id' => $pendiente->id,
            'titulo' => 'Tarea vencida',
            'prioridad' => 'ALTA',
            'asignado_a' => $user->id,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'orden' => 1,
        ]);

        $proxima = KanbanTarea::create([
            'tablero_id' => $tablero->id,
            'columna_id' => $pendiente->id,
            'titulo' => 'Tarea proxima',
            'prioridad' => 'MEDIA',
            'fecha_vencimiento' => now()->addDays(2)->toDateString(),
            'orden' => 2,
        ]);
        $proxima->asignados()->sync([$user->id]);

        Artisan::call('kanban:alertas-vencimiento');

        Mail::assertSent(KanbanResumenVencimientoMail::class, 1);
        Mail::assertSent(KanbanResumenVencimientoMail::class, function (KanbanResumenVencimientoMail $mail) use ($user, $vencida, $proxima) {
            return $mail->usuario->is($user)
                && $mail->items->count() === 2
                && $mail->items->pluck('tarea.id')->sort()->values()->all() === collect([$vencida->id, $proxima->id])->sort()->values()->all();
        });
    }
}
