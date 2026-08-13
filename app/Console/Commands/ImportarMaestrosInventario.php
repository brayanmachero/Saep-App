<?php

namespace App\Console\Commands;

use App\Services\InventarioOperationalMasterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarMaestrosInventario extends Command
{
    protected $signature = 'inventario:importar-maestros {archivo : Ruta absoluta o relativa del libro Excel} {--dry-run : Valida e informa el resultado sin guardar}';

    protected $description = 'Importa los maestros Maestro_CC y Maestro_Coordinador del libro de Inventario.';

    public function handle(InventarioOperationalMasterService $masters): int
    {
        $path = (string) $this->argument('archivo');
        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            DB::beginTransaction();
        }

        try {
            $result = $masters->import($path);
            if ($this->option('dry-run')) {
                DB::rollBack();
            }
        } catch (\Throwable $exception) {
            if ($this->option('dry-run') && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $exception;
        }

        $this->table(
            ['Coordinadores creados', 'Actualizados', 'Centros creados', 'Actualizados', 'Sin relación'],
            [[
                $result['coordinadoresCreados'],
                $result['coordinadoresActualizados'],
                $result['centrosCreados'],
                $result['centrosActualizados'],
                count($result['coordinadoresSinRelacion']),
            ]],
        );
        if ($result['coordinadoresSinRelacion'] !== []) {
            $this->warn('Coordinadores conservados como texto de origen, sin relación: ' . implode(', ', $result['coordinadoresSinRelacion']));
        }
        if ($this->option('dry-run')) {
            $this->info('Validación finalizada: no se guardaron cambios.');
        }

        return self::SUCCESS;
    }
}
