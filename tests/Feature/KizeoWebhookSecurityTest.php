<?php

namespace Tests\Feature;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class KizeoWebhookSecurityTest extends TestCase
{
    private array $webhookLogIds = [];

    protected function tearDown(): void
    {
        if ($this->webhookLogIds !== []) {
            WebhookLog::whereIn('id', $this->webhookLogIds)->delete();
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
}
