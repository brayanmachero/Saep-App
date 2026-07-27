<?php

namespace Tests\Feature;

use App\Jobs\DashboardSyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardSyncJobTest extends TestCase
{
    public function test_job_runs_the_command_and_clears_its_running_lock(): void
    {
        Cache::forget(DashboardSyncJob::runningKey('observaciones-ccu'));

        Artisan::shouldReceive('call')
            ->once()
            ->with('kizeo:sync-observaciones-ccu', ['--force' => true])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('65 registros sincronizados');

        $job = new DashboardSyncJob(
            'observaciones-ccu',
            'kizeo:sync-observaciones-ccu',
            ['--force' => true],
        );

        $job->handle();

        $this->assertFalse(Cache::has(DashboardSyncJob::runningKey('observaciones-ccu')));
        $this->assertSame('success', Cache::get('dashboard_sync:observaciones-ccu:status')['state']);
    }
}
