<?php

namespace App\Console\Commands;

use App\Mail\SstActividadAlertaMail;
use App\Models\SstActividad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SstEnviarRecordatorios extends Command
{
    protected $signature = 'sst:enviar-recordatorios';
    protected $description = 'Envía alertas por email de actividades SST próximas a vencer y vencidas';

    public function handle(): int
    {
        $enviados = 0;

        // 1) Actividades que vencen en los próximos 7 días
        $proximas = SstActividad::with(['responsableUser', 'categoria.programa'])
            ->whereNotNull('fecha_fin')
            ->whereNotNull('responsable_id')
            ->where('fecha_fin', '>', now())
            ->where('fecha_fin', '<=', now()->addDays(7))
            ->whereNotIn('estado', ['COMPLETADA', 'CANCELADA'])
            ->get();

        foreach ($proximas as $act) {
            if ($act->responsableUser?->email) {
                try {
                    Mail::to($act->responsableUser->email)->send(
                        new SstActividadAlertaMail($act, 'vencimiento')
                    );
                    $enviados++;
                } catch (\Exception $e) {
                    Log::warning("SST Recordatorio: no se pudo enviar a {$act->responsableUser->email}: {$e->getMessage()}");
                }
            }
        }

        // 2) Actividades ya vencidas (venció ayer o antes, no completadas)
        $vencidas = SstActividad::with(['responsableUser', 'categoria.programa'])
            ->whereNotNull('fecha_fin')
            ->whereNotNull('responsable_id')
            ->where('fecha_fin', '<', now())
            ->where('fecha_fin', '>=', now()->subDays(3)) // solo las vencidas en los últimos 3 días
            ->whereNotIn('estado', ['COMPLETADA', 'CANCELADA'])
            ->get();

        foreach ($vencidas as $act) {
            if ($act->responsableUser?->email) {
                try {
                    Mail::to($act->responsableUser->email)->send(
                        new SstActividadAlertaMail($act, 'vencida')
                    );
                    $enviados++;
                } catch (\Exception $e) {
                    Log::warning("SST Recordatorio vencida: no se pudo enviar a {$act->responsableUser->email}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Recordatorios enviados: {$enviados} (próximas: {$proximas->count()}, vencidas: {$vencidas->count()})");
        Log::info("SST Recordatorios: {$enviados} emails enviados", [
            'proximas' => $proximas->count(),
            'vencidas' => $vencidas->count(),
        ]);

        return self::SUCCESS;
    }
}
