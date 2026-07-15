<?php

namespace App\Console\Commands;

use App\Mail\KanbanResumenVencimientoMail;
use App\Models\KanbanTarea;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KanbanAlertasVencimiento extends Command
{
    protected $signature = 'kanban:alertas-vencimiento';
    protected $description = 'Envia un resumen consolidado por usuario para tareas Kanban proximas a vencer o vencidas';

    public function handle(): int
    {
        $hoy = now()->startOfDay();

        // Tareas con vencimiento entre hoy y +3 dias, o hasta 1 dia vencidas.
        $tareas = KanbanTarea::with(['tablero', 'columna', 'asignado', 'asignados'])
            ->where('archivada', false)
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [$hoy->copy()->subDay(), $hoy->copy()->addDays(3)])
            ->where(function ($q) {
                $q->whereNotNull('asignado_a')
                    ->orWhereHas('asignados');
            })
            ->whereHas('tablero', fn ($q) => $q->where('activo', true))
            ->whereHas('columna', function ($q) {
                // Excluir tareas en la última columna (completadas)
                $q->whereRaw('kanban_columnas.orden < (SELECT MAX(c2.orden) FROM kanban_columnas c2 WHERE c2.tablero_id = kanban_columnas.tablero_id)');
            })
            ->get();

        $pendientesPorUsuario = $this->agruparTareasPorUsuario($tareas, $hoy);
        $enviados = 0;

        foreach ($pendientesPorUsuario as $userId => $items) {
            $usuario = $items->first()['usuario'] ?? null;

            if (!$usuario?->email) {
                continue;
            }
            try {
                Mail::to($usuario->email)
                    ->send(new KanbanResumenVencimientoMail($usuario, $items));
                $enviados++;
            } catch (\Throwable $e) {
                Log::error('Kanban resumen vencimiento: error enviando email', [
                    'user_id' => $userId,
                    'email' => $usuario->email,
                    'tareas' => $items->count(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Resumenes enviados: {$enviados} usuario(s), {$tareas->count()} tarea(s) evaluadas.");
        return self::SUCCESS;
    }

    private function agruparTareasPorUsuario(Collection $tareas, \Carbon\CarbonInterface $hoy): Collection
    {
        $pendientes = collect();

        foreach ($tareas as $tarea) {
            $usuarios = $tarea->asignados;

            if ($tarea->asignado) {
                $usuarios = $usuarios->push($tarea->asignado);
            }

            $usuarios = $usuarios
                ->filter(fn (User $usuario) => $usuario->email)
                ->unique('id')
                ->values();

            foreach ($usuarios as $usuario) {
                $pendientes->push([
                    'usuario' => $usuario,
                    'tarea' => $tarea,
                    'dias_restantes' => (int) $hoy->diffInDays($tarea->fecha_vencimiento, false),
                ]);
            }
        }

        return $pendientes
            ->groupBy(fn ($item) => $item['usuario']->id)
            ->map(fn (Collection $items) => $items->sortBy(fn ($item) => sprintf(
                '%s|%012d|%012d',
                $item['tarea']->tablero?->nombre ?? '',
                $item['tarea']->fecha_vencimiento?->timestamp ?? 0,
                $item['tarea']->id
            ))->values());
    }
}
