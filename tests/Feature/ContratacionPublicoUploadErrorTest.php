<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ContratacionPublicoUploadErrorTest extends TestCase
{
    public function test_public_upload_error_is_logged_for_google_session(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Contratacion publico: error frontend upload documento',
                Mockery::on(function (array $context) {
                    return $context['campo'] === 'carnet_frontal'
                        && $context['fase'] === 'network_error'
                        && $context['archivo_nombre'] === 'carnet.jpg'
                        && $context['archivo_tamano'] === 3349266
                        && $context['http_status'] === 0
                        && strlen($context['google_id_hash']) === 64
                        && strlen($context['email_hash']) === 64;
                })
            );

        $this->withSession([
            'contratacion_google_user' => [
                'id' => 'google-user-123',
                'email' => 'postulante@example.com',
                'name' => 'Postulante Prueba',
                'avatar' => null,
            ],
        ])->postJson(route('contratacion-publico.documento.error'), [
            'campo' => 'carnet_frontal',
            'fase' => 'network_error',
            'mensaje' => 'No se pudo conectar para subir el archivo.',
            'archivo_nombre' => 'carnet.jpg',
            'archivo_tamano' => 3349266,
            'archivo_tipo' => 'image/jpeg',
            'http_status' => 0,
            'navigator_online' => true,
            'user_agent_cliente' => 'Android Chrome',
        ])->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_public_upload_error_requires_google_session(): void
    {
        $this->postJson(route('contratacion-publico.documento.error'), [
            'fase' => 'network_error',
        ])->assertUnauthorized();
    }
}
