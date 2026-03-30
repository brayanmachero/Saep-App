<?php

namespace App\Console\Commands;

use App\Models\SstActividad;
use Illuminate\Console\Command;

class SstRepararSeguimiento extends Command
{
    protected $signature = 'sst:reparar-seguimiento';
    protected $description = 'Crea registros de seguimiento faltantes a partir de la periodicidad de cada actividad';

    public function handle(): int
    {
        $actividades = SstActividad::whereNotNull('periodicidad')
            ->whereDoesntHave('seguimiento', fn ($q) => $q->where('programado', true))
            ->get();

        $this->info("Actividades sin seguimiento: {$actividades->count()}");

        $total = 0;
        foreach ($actividades as $act) {
            $meses = SstActividad::mesesProgramadosPorPeriodicidad($act->periodicidad);
            foreach ($meses as $mes) {
                $act->seguimiento()->updateOrCreate(
                    ['mes' => $mes],
                    ['programado' => true]
                );
                $total++;
            }

            // Auto-asignar fechas si faltan
            $anio = $act->categoria?->programa?->anio ?? date('Y');
            if (!$act->fecha_inicio && !empty($meses)) {
                $act->update(['fecha_inicio' => \Carbon\Carbon::create($anio, min($meses), 1)->toDateString()]);
            }
            if (!$act->fecha_fin && !empty($meses)) {
                $act->update(['fecha_fin' => \Carbon\Carbon::create($anio, max($meses))->endOfMonth()->toDateString()]);
            }

            $this->line("  ✓ {$act->nombre} → {$act->periodicidad} → " . count($meses) . " meses");
        }

        $this->info("Listo: {$total} registros de seguimiento creados.");
        return self::SUCCESS;
    }
}
