<?php

namespace App\Console\Commands;

use App\Services\EntregaBodegaSyncService;
use App\Services\KizeoService;
use Illuminate\Console\Command;

class SyncEntregasBodega extends Command
{
    protected $signature = 'kizeo:sync-entregas-bodega
                            {--limit=250 : Máximo de respuestas a consultar por ejecución}
                            {--force : Vuelve a consultar también respuestas ya sincronizadas}';

    protected $description = 'Sincroniza las entregas de bodega desde el formulario Kizeo Control de Entrega Bodega.';

    public function handle(KizeoService $kizeo): int
    {
        $summary = (new EntregaBodegaSyncService($kizeo))
            ->sync(max(1, (int) $this->option('limit')), (bool) $this->option('force'));

        $this->info(sprintf(
            'Entregas de bodega: %d creadas, %d actualizadas, %d con error. Fuente: %d.',
            $summary['created'], $summary['updated'], $summary['failed'], $summary['total_source'],
        ));

        if ($summary['remaining'] > 0) {
            $this->warn("Quedan {$summary['remaining']} registros pendientes para una próxima ejecución.");
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
