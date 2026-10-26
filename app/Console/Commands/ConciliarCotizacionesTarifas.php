<?php

namespace App\Console\Commands;

use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Services\CotizacionTarifaService;
use Illuminate\Console\Command;

class ConciliarCotizacionesTarifas extends Command
{
    protected $signature = 'comercial:conciliar-cotizaciones {--aplicar : Enlazar históricos exactos y reflejar cotizaciones nativas}';
    protected $description = 'Conciliación idempotente de cotizador y sábana; no aprueba ni recalcula precios';

    public function handle(CotizacionTarifaService $service): int
    {
        $this->info($this->option('aplicar') ? 'Aplicando conciliación' : 'Simulación sin cambios');
        foreach (Cotizacion::withTrashed()->orderBy('id')->lazyById(100) as $cotizacion) {
            $this->line($cotizacion->id.' | '.$cotizacion->numero.' | '.$service->sincronizar($cotizacion, (bool) $this->option('aplicar')));
        }
        return self::SUCCESS;
    }
}
