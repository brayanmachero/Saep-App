<?php

namespace App\Console\Commands;

use App\Modules\Comercial\Services\SabanaTarifasService;
use Illuminate\Console\Command;

class ImportarSabanaTarifas extends Command
{
    protected $signature = 'comercial:importar-sabana {archivo : Lote JSON normalizado y revisable} {--aplicar : Registrar referencias pendientes sin activar vigencia}';

    protected $description = 'Registra fuentes y versiones de tarifas comerciales sin modificar cotizaciones ni aprobar tarifas';

    public function handle(SabanaTarifasService $service): int
    {
        try {
            $lote = json_decode(file_get_contents($this->argument('archivo')), true, 512, JSON_THROW_ON_ERROR);
            $service->validar($lote);
            if (! $this->option('aplicar')) {
                $this->info('Simulación: '.count($lote['fuentes']).' fuentes, '.count($lote['tarifas']).' referencias. Sin escritura.');

                return self::SUCCESS;
            }
            $this->info(json_encode($service->importar($lote), JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
