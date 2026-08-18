<?php

namespace App\Console\Commands;

use App\Services\InventarioKizeoCatalogSyncService;
use Illuminate\Console\Command;

class SyncInventarioCatalogoKizeo extends Command
{
    protected $signature = 'kizeo:sync-catalogo-inventario
                            {--dry-run : Muestra los cambios sin escribir en Kizeo}
                            {--limit=80 : Máximo de altas o cambios por ejecución}';

    protected $description = 'Publica las variantes activas del catálogo SAEP en la lista avanzada EPP de Kizeo.';

    public function handle(InventarioKizeoCatalogSyncService $sync): int
    {
        $summary = $sync->synchronize((bool) $this->option('dry-run'), max(1, (int) $this->option('limit')));
        $mode = $summary['dryRun'] ? 'Vista previa' : 'Sincronización';

        $this->info("{$mode} Kizeo lista {$summary['listId']}: {$summary['created']} por crear, {$summary['updated']} por actualizar, {$summary['unchanged']} sin cambios.");
        if ($summary['deferred'] > 0) {
            $this->warn("{$summary['deferred']} cambio(s) quedaron para la siguiente ejecución por el límite operativo.");
        }
        if ($summary['orphans'] !== []) {
            $this->warn(count($summary['orphans']).' ítem(s) de Kizeo no existen como variante activa en SAEP y no fueron eliminados.');
        }
        foreach (array_slice($summary['errors'], 0, 10) as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
