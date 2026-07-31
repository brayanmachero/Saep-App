<?php

namespace App\Console\Commands;

use App\Services\ReservaVehiculoService;
use Illuminate\Console\Command;

class ProcesarReservasVehiculos extends Command
{
    protected $signature = 'vehiculos:procesar-reservas';

    protected $description = 'Envia recordatorios y procesa reservas de vehiculos vencidas';

    public function handle(ReservaVehiculoService $reservas): int
    {
        $resultado = $reservas->procesarNotificaciones();
        $this->info('Recordatorios: '.$resultado['recordatorios'].'. Vencidas: '.$resultado['vencidas'].'.');

        return self::SUCCESS;
    }
}
