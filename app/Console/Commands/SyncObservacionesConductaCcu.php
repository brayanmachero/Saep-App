<?php

namespace App\Console\Commands;

use App\Services\KizeoService;
use App\Services\ObservacionConductaCcuSyncService;
use Illuminate\Console\Command;

class SyncObservacionesConductaCcu extends Command
{
    protected $signature = 'kizeo:sync-observaciones-ccu
                            {--limit=250 : Máximo de respuestas a consultar por ejecución}
                            {--force : Vuelve a consultar también respuestas ya sincronizadas}';

    protected $description = 'Sincroniza el formulario Kizeo Observaciones de conducta CCU para su dashboard local.';

    public function handle(KizeoService $kizeo): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $summary = (new ObservacionConductaCcuSyncService($kizeo))
            ->sync($limit, (bool) $this->option('force'));

        $this->info(sprintf(
            'Observaciones CCU: %d creadas, %d actualizadas, %d con error. Fuente: %d.',
            $summary['created'],
            $summary['updated'],
            $summary['failed'],
            $summary['total_source'],
        ));

        if ($summary['remaining'] > 0) {
            $this->warn("Quedan {$summary['remaining']} registros pendientes para una próxima ejecución.");
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
