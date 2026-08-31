<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InitializeSaepPlannerProjectTest extends TestCase
{
    public function test_it_previews_planner_initialization_without_writing(): void
    {
        config()->set('services.microsoft_graph.tenant_id', 'tenant-id');
        config()->set('services.microsoft_graph.client_id', 'client-id');
        config()->set('services.microsoft_graph.client_secret', 'client-secret');
        config()->set('planner.plan_id', 'plan-id');
        Cache::forget('msgraph_planner_access_token');

        Http::fake(function (Request $request) {
            return match (true) {
                str_contains($request->url(), 'oauth2/v2.0/token') => Http::response(['access_token' => 'token'], 200),
                str_ends_with($request->url(), '/planner/plans/plan-id') => Http::response(['id' => 'plan-id', 'title' => 'SAEP – Operación y Mejoras'], 200),
                str_ends_with($request->url(), '/planner/plans/plan-id/buckets') => Http::response([
                    'value' => [['id' => 'default-bucket', 'name' => 'Tareas', '@odata.etag' => 'bucket-etag']],
                ], 200),
                str_ends_with($request->url(), '/planner/plans/plan-id/tasks') => Http::response(['value' => []], 200),
                default => Http::response(['error' => ['message' => 'Unexpected request']], 500),
            };
        });

        $this->artisan('planner:initialize-saep-project --dry-run')
            ->expectsOutputToContain('Plan conectado: SAEP – Operación y Mejoras')
            ->expectsOutputToContain('Validación terminada. No se realizaron cambios en Planner.')
            ->assertSuccessful();

        Http::assertSentCount(4);
        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET' && ! str_contains($request->url(), 'oauth2/v2.0/token'));
    }
}
