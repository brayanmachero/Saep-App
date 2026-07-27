<?php

namespace App\Jobs;

use App\Models\CharlaTrackingActionLog;
use App\Models\StopActionLog;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DashboardSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CONNECTION = 'dashboard_sync';
    public const QUEUE = 'dashboard-sync';

    /** Must stay below the dedicated worker timeout and retry window. */
    public int $timeout = 840;

    /** A failed manual sync is visible to the requester and must not cascade. */
    public int $tries = 1;

    public function __construct(
        public readonly string $key,
        public readonly string $command,
        public readonly array $arguments = [],
        public readonly ?int $requestedByUserId = null,
        public readonly ?string $auditChannel = null,
    ) {
        $this->onConnection(self::CONNECTION);
        $this->onQueue(self::QUEUE);
    }

    public static function dispatchOnce(
        string $key,
        string $command,
        array $arguments = [],
        ?int $requestedByUserId = null,
        ?string $auditChannel = null,
    ): bool {
        if (! Cache::add(self::runningKey($key), true, now()->addMinutes(20))) {
            return false;
        }

        try {
            static::dispatch($key, $command, $arguments, $requestedByUserId, $auditChannel);

            return true;
        } catch (Throwable $e) {
            Cache::forget(self::runningKey($key));

            throw $e;
        }
    }

    public function handle(): void
    {
        Cache::put(self::runningKey($this->key), true, now()->addMinutes(20));
        Cache::put(self::statusKey($this->key), [
            'state' => 'running',
            'started_at' => now()->toDateTimeString(),
        ], now()->addDay());

        $exitCode = Artisan::call($this->command, $this->arguments);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'La sincronización terminó con un error.');
        }

        if ($this->key === 'stop') {
            app(GoogleDriveService::class)->clearCache();
        }

        Cache::put(self::statusKey($this->key), [
            'state' => 'success',
            'finished_at' => now()->toDateTimeString(),
            'output' => Str::limit($output, 2000, ''),
        ], now()->addDay());
        Cache::forget(self::runningKey($this->key));

        $this->recordAudit('success', $this->successSummary(), [
            'command' => $this->command,
            'exit_code' => $exitCode,
            'output' => Str::limit($output, 2000, ''),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Cache::put(self::statusKey($this->key), [
            'state' => 'failed',
            'finished_at' => now()->toDateTimeString(),
            'error' => Str::limit($exception->getMessage(), 1000, ''),
        ], now()->addDay());
        Cache::forget(self::runningKey($this->key));

        Log::error('Dashboard manual sync failed', [
            'key' => $this->key,
            'command' => $this->command,
            'error' => $exception->getMessage(),
        ]);

        $this->recordAudit('failed', $this->failureSummary(), [
            'command' => $this->command,
            'error' => Str::limit($exception->getMessage(), 2000, ''),
        ]);
    }

    public static function runningKey(string $key): string
    {
        return "dashboard_sync:{$key}:running";
    }

    private static function statusKey(string $key): string
    {
        return "dashboard_sync:{$key}:status";
    }

    private function recordAudit(string $status, string $summary, array $metadata): void
    {
        if (! $this->requestedByUserId || ! $this->auditChannel) {
            return;
        }

        $attrs = [
            'user_id' => $this->requestedByUserId,
            'action' => 'sync',
            'status' => $status,
            'summary' => $summary,
            'filters' => [],
            'metadata' => $metadata,
        ];

        match ($this->auditChannel) {
            'stop' => StopActionLog::record($attrs),
            'charla' => CharlaTrackingActionLog::record($attrs),
            default => null,
        };
    }

    private function successSummary(): string
    {
        return match ($this->key) {
            'stop' => 'Sincronización manual de datos STOP completada.',
            'charlas' => 'Sincronización manual de charlas completada.',
            'observaciones-ccu' => 'Sincronización de Observaciones CCU completada.',
            'inspecciones-pdr' => 'Sincronización de Inspecciones Preventivas PDR completada.',
            default => 'Sincronización manual completada.',
        };
    }

    private function failureSummary(): string
    {
        return match ($this->key) {
            'stop' => 'Error durante sincronización manual de datos STOP.',
            'charlas' => 'Error durante sincronización manual de charlas.',
            'observaciones-ccu' => 'Error durante sincronización de Observaciones CCU.',
            'inspecciones-pdr' => 'Error durante sincronización de Inspecciones Preventivas PDR.',
            default => 'Error durante sincronización manual.',
        };
    }
}
