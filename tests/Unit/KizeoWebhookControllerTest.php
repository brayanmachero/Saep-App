<?php

namespace Tests\Unit;

use App\Http\Controllers\KizeoWebhookController;
use App\Services\KizeoAutomationService;
use App\Services\KizeoService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class KizeoWebhookControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_legacy_webhook_field_extractor_prefers_reference_text(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'getKizeoFieldValue');
        $method->setAccessible(true);

        $value = $method->invoke($controller, [
            'nombre_trabajador_observado' => [
                'value' => null,
                'text' => 'Maria Isabel Barraza Rodriguez',
                'forced' => false,
            ],
        ], 'nombre_trabajador_observado');

        $this->assertSame('Maria Isabel Barraza Rodriguez', $value);
    }

    public function test_legacy_webhook_filename_includes_kizeo_record_id(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'uniqueKizeoFilename');
        $method->setAccessible(true);

        $filename = $method->invoke(
            $controller,
            '2026-07-20',
            'METODOLOGIA 5S Y LAS 3R',
            'Marcelo Gonzalez',
            '276504121'
        );

        $this->assertSame(
            '2026-07-20 - METODOLOGIA 5S Y LAS 3R (Marcelo Gonzalez) - Registro 276504121.pdf',
            $filename
        );
    }

    private function controller(): KizeoWebhookController
    {
        return new KizeoWebhookController(
            Mockery::mock(KizeoService::class),
            Mockery::mock(KizeoAutomationService::class)
        );
    }
}
