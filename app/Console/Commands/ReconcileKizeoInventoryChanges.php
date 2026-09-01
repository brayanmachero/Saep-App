<?php

namespace App\Console\Commands;

use App\Models\InventarioEntregaKizeoAplicacion;
use App\Services\InventarioStockService;
use Illuminate\Console\Command;

class ReconcileKizeoInventoryChanges extends Command
{
    protected $signature = 'inventario:conciliar-cambios-kizeo';

    protected $description = 'Conciliar cambios ya sincronizados de artículo, talla o cantidad desde Kizeo sin borrar su trazabilidad.';

    public function handle(InventarioStockService $stock): int
    {
        $reviewed = 0;
        $corrected = 0;
        $pending = 0;

        InventarioEntregaKizeoAplicacion::query()
            ->whereIn('estado', ['APLICADA', 'CORREGIDA'])
            ->with(['entrega.items', 'lineas'])
            ->orderBy('id')
            ->chunkById(100, function ($applications) use ($stock, &$reviewed, &$corrected, &$pending) {
                foreach ($applications as $application) {
                    $delivery = $application->entrega;
                    if (! $delivery) {
                        continue;
                    }

                    $acknowledgedAt = $application->fuente_corregida_en ?: $application->fuente_actualizada_en;
                    $hasNewSourceVersion = $delivery->kizeo_updated_at
                        && (! $acknowledgedAt || $delivery->kizeo_updated_at->gt($acknowledgedAt));
                    if (! $hasNewSourceVersion && ! filled($application->correccion_pendiente_motivo)) {
                        continue;
                    }

                    $reviewed++;
                    $result = $stock->tryAutoReconcileUpdatedKizeoDelivery($delivery);
                    if ($result?->estado === 'CORREGIDA') {
                        $corrected++;
                    } elseif ($result === null) {
                        $pending++;
                    }
                }
            });

        $this->info("Cambios Kizeo revisados: {$reviewed}; corregidos: {$corrected}; pendientes: {$pending}.");

        return self::SUCCESS;
    }
}
