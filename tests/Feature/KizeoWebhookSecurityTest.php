<?php

namespace Tests\Feature;

use App\Models\WebhookLog;
use App\Models\EntregaBodega;
use App\Services\EntregaBodegaSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class KizeoWebhookSecurityTest extends TestCase
{
    private array $webhookLogIds = [];
    private bool $createdWebhookLogTable = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('origen', 60)->nullable();
                $table->string('form_id', 80)->nullable();
                $table->string('data_id', 80)->nullable();
                $table->string('tipo', 120)->nullable();
                $table->string('estado', 30)->nullable();
                $table->text('resumen')->nullable();
                $table->string('archivo')->nullable();
                $table->text('sharepoint_path')->nullable();
                $table->boolean('email_enviado')->default(false);
                $table->json('destinatarios')->nullable();
                $table->json('metadata')->nullable();
                $table->text('error_message')->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamps();
            });
            $this->createdWebhookLogTable = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->webhookLogIds !== []) {
            WebhookLog::whereIn('id', $this->webhookLogIds)->delete();
        }

        Mockery::close();

        if ($this->createdWebhookLogTable) {
            Schema::drop('webhook_logs');
        }

        parent::tearDown();
    }

    public function test_kizeo_webhook_fails_closed_when_secret_is_required_but_not_configured(): void
    {
        Config::set('services.kizeo.webhook_require_secret', true);
        Config::set('services.kizeo.webhook_secret', null);

        $this->postJson(route('kizeo.webhook'), [
            'form_id' => 'form-test',
            'data_id' => 'data-test',
        ])->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'message' => 'Webhook secret not configured',
            ]);
    }

    public function test_kizeo_webhook_rejects_missing_or_invalid_secret(): void
    {
        Config::set('services.kizeo.webhook_require_secret', true);
        Config::set('services.kizeo.webhook_secret', 'expected-secret');

        $this->postJson(route('kizeo.webhook'), [
            'form_id' => 'form-test',
            'data_id' => 'data-test',
        ])->assertForbidden();

        $this->postJson(route('kizeo.webhook'), [
            'form_id' => 'form-test',
            'data_id' => 'data-test',
        ], [
            'X-Webhook-Secret' => 'wrong-secret',
        ])->assertForbidden();
    }

    public function test_kizeo_webhook_accepts_valid_secret_and_ignores_incomplete_payload(): void
    {
        Config::set('services.kizeo.webhook_require_secret', true);
        Config::set('services.kizeo.webhook_secret', 'expected-secret');

        $response = $this->postJson(route('kizeo.webhook'), [], [
            'X-Webhook-Secret' => 'expected-secret',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'ignored',
                'message' => 'Sin form_id o data_id',
            ]);

        $log = WebhookLog::where('origen', 'kizeo')
            ->where('tipo', 'sin_identificar')
            ->latest('id')
            ->firstOrFail();

        $this->webhookLogIds[] = $log->id;
    }

    public function test_inventory_forms_are_synchronized_immediately_from_the_webhook(): void
    {
        Config::set('services.kizeo.webhook_require_secret', true);
        Config::set('services.kizeo.webhook_secret', 'expected-secret');

        $sync = Mockery::mock(EntregaBodegaSyncService::class);
        $sync->shouldReceive('supportsForm')->once()->with('1196386')->andReturnTrue();
        $sync->shouldReceive('syncSourceRecord')->once()
            ->withArgs(function (string $formId, string $dataId, array $data): bool {
                return $formId === '1196386'
                    && $dataId === 'webhook-record'
                    && ($data['update_answer_time'] ?? null) === '2026-08-17T12:00:00-04:00';
            })
            ->andReturn(new EntregaBodega([
                'id' => 999,
                'estado_fuente' => 'ACTIVA',
                'flujo_inventario' => 'SALIDA',
            ]));
        $this->app->instance(EntregaBodegaSyncService::class, $sync);

        $this->postJson(route('kizeo.webhook'), [
            'eventType' => 'finished',
            'data' => [
                'form_id' => '1196386',
                'id' => 'webhook-record',
                'update_answer_time' => '2026-08-17T12:00:00-04:00',
            ],
        ], [
            'X-Webhook-Secret' => 'expected-secret',
        ])->assertOk()
            ->assertJson([
                'status' => 'success',
                'estado_fuente' => 'ACTIVA',
            ]);

        $log = WebhookLog::where('origen', 'kizeo')
            ->where('form_id', '1196386')
            ->where('data_id', 'webhook-record')
            ->where('tipo', 'inventario_bodega_finished')
            ->latest('id')
            ->firstOrFail();

        $this->webhookLogIds[] = $log->id;
    }
}
