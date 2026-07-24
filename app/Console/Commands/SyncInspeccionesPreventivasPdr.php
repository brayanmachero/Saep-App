<?php

namespace App\Console\Commands;

use App\Services\InspeccionPreventivaPdrSyncService;
use App\Services\KizeoService;
use Illuminate\Console\Command;

class SyncInspeccionesPreventivasPdr extends Command
{
    protected $signature = 'kizeo:sync-inspecciones-preventivas
                            {--limit=250 : Máximo de respuestas a consultar por ejecución}
                            {--force : Vuelve a consultar también respuestas ya sincronizadas}';

    protected $description = 'Sincroniza PDR Inspección Preventiva de Kizeo para su dashboard local.';

    public function handle(KizeoService $kizeo): int
    {
        $summary = (new InspeccionPreventivaPdrSyncService($kizeo))
            ->sync(max(1, (int) $this->option('limit')), (bool) $this->option('force'));

        $this->info(sprintf(
            'Inspecciones preventivas: %d creadas, %d actualizadas, %d con error. Fuente: %d.',
            $summary['created'], $summary['updated'], $summary['failed'], $summary['total_source'],
        ));
        if ($summary['remaining'] > 0) {
            $this->warn("Quedan {$summary['remaining']} registros pendientes para una próxima ejecución.");
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
