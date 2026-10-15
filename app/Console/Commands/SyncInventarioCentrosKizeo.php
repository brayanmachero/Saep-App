<?php

namespace App\Console\Commands;

use App\Services\InventarioKizeoCentroSyncService;
use Illuminate\Console\Command;

class SyncInventarioCentrosKizeo extends Command
{
    protected $signature = 'inventario:sync-centros-kizeo {--dry-run}';

    protected $description = 'Publica centros en la lista avanzada conservando IDs Kizeo y verificando su lectura.';

    public function handle(InventarioKizeoCentroSyncService $service): int
    {
        $result = $service->synchronize((bool) $this->option('dry-run'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
