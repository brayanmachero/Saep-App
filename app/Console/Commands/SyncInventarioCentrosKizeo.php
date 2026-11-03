<?php

namespace App\Console\Commands;

use App\Services\InventarioKizeoCentroSyncService;
use App\Services\InventarioTalanaCentroSyncService;
use Illuminate\Console\Command;

class SyncInventarioCentrosKizeo extends Command
{
    protected $signature = 'inventario:sync-centros-kizeo {--dry-run}';

    protected $description = 'Publica centros en la lista avanzada conservando IDs Kizeo y verificando su lectura.';

    public function handle(InventarioKizeoCentroSyncService $service, InventarioTalanaCentroSyncService $talana): int
    {
        $this->line(json_encode(['talana' => $talana->synchronize((bool) $this->option('dry-run'))], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $result = $service->synchronize((bool) $this->option('dry-run'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
