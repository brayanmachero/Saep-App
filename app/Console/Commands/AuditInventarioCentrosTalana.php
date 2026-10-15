<?php

namespace App\Console\Commands;

use App\Services\InventarioTalanaCentroAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditInventarioCentrosTalana extends Command
{
    protected $signature = 'inventario:conciliar-centros-talana';

    protected $description = 'Consulta Talana por empresa y compara código/modalidad; no modifica datos.';

    public function handle(InventarioTalanaCentroAuditService $service): int
    {
        $r = $service->audit();
        Storage::disk('local')->put('kizeo-centros/talana-'.now()->format('Ymd-His').'.json', json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
