<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class RrhhWorkerSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $timeout = 600;
    public int $tries = 1;
    public function __construct(public readonly int $actor, public readonly string $runId)
    {
        $this->onConnection('dashboard_sync')->onQueue('dashboard-sync');
    }

    public function handle(): void
    {
        if (Cache::get('rrhh_worker_sync_reservation') !== $this->runId) return;
        $this->status('running', 'Consultando contratos y trabajadores en Talana…');
        try {
            $exit = Artisan::call('talana:sync-db', ['--padron-documental'=>true]);
            if ($exit !== 0) throw new \RuntimeException('La sincronización no se completó.');
            $this->status('success', 'Actualización completada. Pulsa Actualizar listado para ver los resultados.');
        } catch (\Throwable $e) {
            report($e);
            $this->status('error', 'No se completó la actualización. Se conserva el listado disponible; vuelve a intentarlo más tarde.');
        } finally { $this->releaseReservation(); }
    }

    public function failed(?\Throwable $exception): void
    {
        if (Cache::get('rrhh_worker_sync_reservation') !== $this->runId) return;
        $this->status('error', 'La actualización se interrumpió o superó el tiempo permitido. No se reintentó automáticamente.');
        $this->releaseReservation();
    }

    private function status(string $state, string $message): void
    {
        Cache::put('rrhh_worker_sync_status', ['state'=>$state,'message'=>$message,'actor'=>$this->actor,'run_id'=>$this->runId,'at'=>now()->toIso8601String()], now()->addDays(7));
    }

    private function releaseReservation(): void
    {
        if (Cache::get('rrhh_worker_sync_reservation') === $this->runId) Cache::forget('rrhh_worker_sync_reservation');
    }
}
