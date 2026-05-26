<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TalanaSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Segundos hasta que el job expira si no fue procesado. */
    public int $timeout = 600;

    /** Sin reintentos: si falla, queda registrado y no repite en cascada. */
    public int $tries = 1;

    public function __construct(private readonly int $meses = 1) {}

    public function handle(): void
    {
        Cache::put('talana_sync_running', true, now()->addMinutes(15));
        Cache::put('talana_sync_started_at', now()->toDateTimeString(), now()->addHours(2));
        Cache::forget('talana_sync_error');

        try {
            Artisan::call('talana:sync-db', [
                '--meses' => $this->meses,
            ]);

            $output = Artisan::output();
            Cache::put('talana_sync_last_output', mb_substr($output, 0, 2000), now()->addHours(6));
            Cache::put('talana_sync_finished_at', now()->toDateTimeString(), now()->addHours(6));

        } catch (\Throwable $e) {
            Cache::put('talana_sync_error', $e->getMessage(), now()->addHours(2));
            Log::error('TalanaSyncJob falló', ['error' => $e->getMessage()]);
        } finally {
            Cache::forget('talana_sync_running');
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget('talana_sync_running');
        Cache::put('talana_sync_error', $e->getMessage(), now()->addHours(2));
        Log::error('TalanaSyncJob::failed', ['error' => $e->getMessage()]);
    }
}
