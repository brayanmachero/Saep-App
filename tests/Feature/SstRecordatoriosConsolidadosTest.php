<?php

namespace Tests\Feature;

use App\Mail\SstResumenActividadesMail;
use App\Models\Modulo;
use App\Models\ProgramaSst;
use App\Models\Rol;
use App\Models\SstActividad;
use App\Models\SstCategoria;
use App\Models\SstNotificacionLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SstRecordatoriosConsolidadosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => config('app.key') ?: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);

        Modulo::firstOrCreate(
            ['slug' => 'carta_gantt'],
            [
                'nombre' => 'Carta Gantt',
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

    public function test_daily_sst_alerts_are_consolidated_per_recipient(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:00:00'));
        Mail::fake();

        $responsable = $this->createUser('responsable-gantt-' . uniqid() . '@saep.local');
        $jefe = $this->createUser('jefe-gantt-' . uniqid() . '@saep.local');

        $programa = ProgramaSst::create([
            'anio' => 2026,
            'titulo' => 'Programa SST Consolidado',
            'estado' => 'ACTIVO',
            'responsable_id' => $jefe->id,
            'creado_por' => $jefe->id,
        ]);

        $categoria = SstCategoria::create([
            'programa_id' => $programa->id,
            'nombre' => 'Operaciones',
            'orden' => 1,
        ]);

        $vencida = $this->createActividad($categoria, $responsable, 'Actividad vencida', '2026-07-10');
        $porVencer = $this->createActividad($categoria, $responsable, 'Actividad por vencer', '2026-07-18');

        Artisan::call('sst:enviar-recordatorios');

        Mail::assertSent(SstResumenActividadesMail::class, function (SstResumenActividadesMail $mail) use ($responsable) {
            return $mail->email === $responsable->email
                && $mail->items->count() === 2
                && $mail->items->pluck('actividad.nombre')->contains('Actividad vencida')
                && $mail->items->pluck('actividad.nombre')->contains('Actividad por vencer');
        });

        $this->assertSame(2, SstNotificacionLog::where('email', $responsable->email)->count());
        $this->assertDatabaseHas('sst_notificacion_log', [
            'actividad_id' => $vencida->id,
            'email' => $responsable->email,
            'tipo' => 'vencida',
            'rol_destinatario' => 'responsable',
        ]);
        $this->assertDatabaseHas('sst_notificacion_log', [
            'actividad_id' => $porVencer->id,
            'email' => $responsable->email,
            'tipo' => 'vencimiento',
            'rol_destinatario' => 'responsable',
        ]);
    }

    private function createUser(string $email): User
    {
        $role = Rol::create([
            'codigo' => 'GANTT_MAIL_QA_' . strtoupper(substr(md5($email), 0, 8)),
            'nombre' => 'Gantt Mail QA',
        ]);

        return User::create([
            'name' => 'Usuario Gantt Mail',
            'email' => $email,
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);
    }

    private function createActividad(SstCategoria $categoria, User $responsable, string $nombre, string $fechaFin): SstActividad
    {
        return SstActividad::create([
            'categoria_id' => $categoria->id,
            'nombre' => $nombre,
            'responsable' => $responsable->nombre_completo,
            'responsable_id' => $responsable->id,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => $fechaFin,
            'prioridad' => 'MEDIA',
            'estado' => 'PENDIENTE',
            'periodicidad' => null,
            'cantidad_programada' => 1,
            'orden' => 1,
        ]);
    }
}
