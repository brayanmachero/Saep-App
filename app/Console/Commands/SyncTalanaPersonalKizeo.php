<?php

namespace App\Console\Commands;

use App\Services\TalanaKizeoPersonalSyncService;
use Illuminate\Console\Command;

class SyncTalanaPersonalKizeo extends Command
{
    protected $signature = 'kizeo:sync-personal-talana
                            {--dry-run : Muestra los cambios sin escribir en Kizeo}
                            {--reconcile : Elimina duplicados y RUT obsoletos después de una vista previa validada}
                            {--limit=250 : Máximo de altas, cambios o bajas por ejecución}';

    protected $description = 'Publica el personal vigente de Talana en la lista avanzada Kizeo de trabajadores por CDD y quita a quienes ya no están vigentes.';

    public function handle(TalanaKizeoPersonalSyncService $sync): int
    {
        $summary = $sync->synchronize(
            (bool) $this->option('dry-run'),
            max(1, (int) $this->option('limit')),
            (bool) $this->option('reconcile'),
        );
        $mode = $summary['dryRun'] ? 'Vista previa' : 'Sincronización';

        $this->info("{$mode} Kizeo lista {$summary['listId']}: {$summary['total']} personas Talana, {$summary['created']} por crear, {$summary['updated']} por actualizar, {$summary['removed']} por quitar, {$summary['unchanged']} sin cambios.");
        if ($summary['duplicates'] > 0) {
            $this->warn("{$summary['duplicates']} ítem(s) duplicados por RUT detectados.".($summary['reconcile'] ? ' Se retirarán sólo si la corrida termina sin errores.' : ' Usa --reconcile después de validar la vista previa.'));
        }
        if ($summary['stale'] > 0) {
            $this->warn("{$summary['stale']} RUT(s) obsoletos detectados en Kizeo.");
        }
        if ($summary['removalSafety']) {
            $this->warn($summary['removalSafety']);
        }
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
