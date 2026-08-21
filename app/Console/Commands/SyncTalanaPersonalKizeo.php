<?php

namespace App\Console\Commands;

use App\Services\TalanaKizeoPersonalSyncService;
use Illuminate\Console\Command;

class SyncTalanaPersonalKizeo extends Command
{
    protected $signature = 'kizeo:sync-personal-talana
                            {--dry-run : Muestra los cambios sin escribir en Kizeo}
                            {--limit=600 : Máximo de altas, cambios o bajas por ejecución}';

    protected $description = 'Publica el personal vigente de Talana en la lista avanzada Kizeo de trabajadores por CDD y quita a quienes ya no están vigentes.';

    public function handle(TalanaKizeoPersonalSyncService $sync): int
    {
        $summary = $sync->synchronize((bool) $this->option('dry-run'), max(1, (int) $this->option('limit')));
        $mode = $summary['dryRun'] ? 'Vista previa' : 'Sincronización';

        $this->info("{$mode} Kizeo lista {$summary['listId']}: {$summary['created']} por crear, {$summary['updated']} por actualizar, {$summary['removed']} no vigentes por quitar, {$summary['unchanged']} sin cambios.");
        if ($summary['deferred'] > 0) {
            $this->warn("{$summary['deferred']} cambio(s) quedaron para la siguiente ejecución por el límite operativo.");
        }
        if ($summary['orphans'] !== []) {
            $this->warn(count($summary['orphans']).' ítem(s) de Kizeo no están mapeados desde Talana y no fueron eliminados.');
        }
        foreach (array_slice($summary['errors'], 0, 10) as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
