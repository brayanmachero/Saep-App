<?php

namespace App\Console\Commands;

use App\Models\KanbanActividadLog;
use App\Models\KanbanTarea;
use Illuminate\Console\Command;

class KanbanTareasRecurrentes extends Command
{
    protected $signature = 'kanban:tareas-recurrentes';
    protected $description = 'Crea nuevas instancias de tareas recurrentes según su programación';

    public function handle(): int
    {
        $hoy = now()->startOfDay();

        $tareasRecurrentes = KanbanTarea::with(['tablero', 'etiquetas'])
            ->whereNotNull('recurrencia')
            ->where('archivada', false)
            ->whereHas('tablero', fn ($q) => $q->where('activo', true))
            ->where(function ($q) use ($hoy) {
                $q->whereNull('recurrencia_hasta')
                  ->orWhere('recurrencia_hasta', '>=', $hoy);
            })
            ->get();

        $creadas = 0;

        foreach ($tareasRecurrentes as $tarea) {
            if (!$this->debeClonar($tarea, $hoy)) continue;

            $nuevaFechaInicio = $hoy->copy();
            $nuevaFechaVenc   = $this->calcularSiguienteVencimiento($tarea, $hoy);

            $nueva = $tarea->replicate(['archivada', 'orden']);
            $nueva->fecha_inicio      = $nuevaFechaInicio;
            $nueva->fecha_vencimiento = $nuevaFechaVenc;
            $nueva->tarea_origen_id   = $tarea->id;
            $nueva->recurrencia       = null; // La copia no es recurrente
            $nueva->recurrencia_hasta = null;
            $nueva->orden             = KanbanTarea::where('columna_id', $tarea->columna_id)->max('orden') + 1;
            $nueva->save();

            // Copiar etiquetas
            if ($tarea->etiquetas->isNotEmpty()) {
                $nueva->etiquetas()->sync($tarea->etiquetas->pluck('id'));
            }

            KanbanActividadLog::registrar($tarea->tablero_id, $nueva->id, 'created',
                "Tarea recurrente «{$nueva->titulo}» creada automáticamente");

            $creadas++;
        }

        $this->info("Tareas recurrentes creadas: {$creadas}");
        return self::SUCCESS;
    }

    private function debeClonar(KanbanTarea $tarea, $hoy): bool
    {
        // Buscar la última tarea creada desde esta origen
        $ultima = KanbanTarea::where('tarea_origen_id', $tarea->id)
            ->orderByDesc('created_at')
            ->first();

        $ultimaFecha = $ultima?->created_at?->startOfDay() ?? $tarea->created_at->startOfDay();

        return match ($tarea->recurrencia) {
            'diaria'    => $ultimaFecha->lt($hoy),
            'semanal'   => $ultimaFecha->diffInDays($hoy) >= 7,
            'quincenal' => $ultimaFecha->diffInDays($hoy) >= 15,
            'mensual'   => $ultimaFecha->diffInDays($hoy) >= 28,
            default     => false,
        };
    }

    private function calcularSiguienteVencimiento(KanbanTarea $tarea, $hoy)
    {
        if (!$tarea->fecha_vencimiento || !$tarea->fecha_inicio) {
            return match ($tarea->recurrencia) {
                'diaria'    => $hoy->copy()->addDay(),
                'semanal'   => $hoy->copy()->addWeek(),
                'quincenal' => $hoy->copy()->addDays(15),
                'mensual'   => $hoy->copy()->addMonth(),
                default     => $hoy->copy()->addWeek(),
            };
        }

        // Mantener la misma duración que la tarea original
        $duracion = $tarea->fecha_inicio->diffInDays($tarea->fecha_vencimiento);
        return $hoy->copy()->addDays($duracion);
    }
}
