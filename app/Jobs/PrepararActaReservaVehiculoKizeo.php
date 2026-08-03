<?php

namespace App\Jobs;

use App\Models\ReservaVehiculo;
use App\Services\ReservaVehiculoKizeoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepararActaReservaVehiculoKizeo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $reservaId) {}

    public function handle(ReservaVehiculoKizeoService $kizeo): void
    {
        $reserva = ReservaVehiculo::query()
            ->with(['vehiculo', 'user'])
            ->find($this->reservaId);

        if (! $reserva || $reserva->estado !== 'CONFIRMADA' || $reserva->kizeo_pushed_at || $reserva->kizeo_data_id) {
            return;
        }

        $kizeo->prepararActa($reserva, null);
    }
}
