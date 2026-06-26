<?php

namespace Tests\Feature;

use App\Models\RegistroTratamientoDatos;
use App\Models\SolicitudArco;
use Tests\TestCase;

class ProteccionDatosPublicoTest extends TestCase
{
    private array $solicitudIds = [];

    protected function tearDown(): void
    {
        if ($this->solicitudIds !== []) {
            RegistroTratamientoDatos::where('tabla_afectada', 'solicitudes_arco')
                ->whereIn('registro_id', $this->solicitudIds)
                ->delete();

            SolicitudArco::whereIn('id', $this->solicitudIds)->delete();
        }

        parent::tearDown();
    }

    public function test_public_arco_request_creates_private_tracking_link(): void
    {
        $response = $this->post(route('proteccion-datos.publico.guardar'), [
            'titular_nombre' => 'Titular Publico Test',
            'titular_email' => 'titular.publico.feature@example.com',
            'titular_rut' => '12.345.678-5',
            'titular_telefono' => '+56911111111',
            'titular_contexto' => 'postulacion',
            'tipo' => 'bloqueo',
            'descripcion' => 'Solicitud publica de prueba para canal ARCO.',
            'datos_afectados' => 'Datos de postulacion',
            'causal_invocada' => 'Prueba de bloqueo temporal',
            'solicita_bloqueo_temporal' => '1',
            'acepta_tratamiento' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $solicitud = SolicitudArco::where('titular_email', 'titular.publico.feature@example.com')->firstOrFail();
        $this->solicitudIds[] = $solicitud->id;

        $this->assertSame('publico', $solicitud->canal_origen);
        $this->assertTrue($solicitud->bloqueo_temporal_activo);
        $this->assertNotNull($solicitud->consentimiento_aceptado_at);

        $location = $response->headers->get('Location');
        $this->assertNotEmpty($location);

        $token = basename($location);
        $this->assertTrue($solicitud->validarTokenPublico($token));

        $this->get($location)
            ->assertOk()
            ->assertSee($solicitud->numero_solicitud)
            ->assertSee('Pendiente')
            ->assertSee('bloqueo temporal activo');
    }

    public function test_public_arco_request_requires_consent(): void
    {
        $response = $this->post(route('proteccion-datos.publico.guardar'), [
            'titular_nombre' => 'Titular Sin Consentimiento',
            'titular_email' => 'titular.sin.consentimiento@example.com',
            'titular_contexto' => 'visitante',
            'tipo' => 'acceso',
            'descripcion' => 'Solicitud sin consentimiento.',
        ]);

        $response->assertSessionHasErrors('acepta_tratamiento');
    }
}
