<?php

namespace App\Console\Commands;

use App\Mail\KanbanVencimientoMail;
use App\Models\KanbanTarea;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KanbanAlertasVencimiento extends Command
{
    protected $signature = 'kanban:alertas-vencimiento';
    protected $description = 'Envía alertas por email para tareas próximas a vencer (1-3 días) o ya vencidas';

    public function handle(): int
    {
        $hoy = now()->startOfDay();

        // Tareas con vencimiento entre hoy y +3 días, o hasta 1 día vencidas
        $tareas = KanbanTarea::with(['tablero', 'columna', 'asignado'])
            ->where('archivada', false)
            ->whereNotNull('fecha_vencimiento')
            ->whereNotNull('asignado_a')
            ->whereBetween('fecha_vencimiento', [$hoy->copy()->subDay(), $hoy->copy()->addDays(3)])
            ->whereHas('tablero', fn ($q) => $q->where('activo', true))
            ->whereHas('columna', function ($q) {
                // Excluir tareas en la última columna (completadas)
                $q->whereRaw('kanban_columnas.orden < (SELECT MAX(c2.orden) FROM kanban_columnas c2 WHERE c2.tablero_id = kanban_columnas.tablero_id)');
            })
            ->get();

        $enviados = 0;

        foreach ($tareas as $tarea) {
            if (!$tarea->asignado?->email) continue;

            $dias = (int) $hoy->diffInDays($tarea->fecha_vencimiento, false);

            try {
                Mail::to($tarea->asignado->email)
                    ->send(new KanbanVencimientoMail($tarea, $dias));
                $enviados++;
            } catch (\Throwable $e) {
                Log::error('Kanban alerta vencimiento: error enviando email', [
                    'tarea_id' => $tarea->id,
                    'email'    => $tarea->asignado->email,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $this->info("Alertas enviadas: {$enviados} de {$tareas->count()} tareas.");
        return self::SUCCESS;
    }
}
