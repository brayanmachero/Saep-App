<?php

namespace Tests\Feature;

use App\Http\Controllers\ContratacionController;
use App\Models\PostulanteContratacion;
use App\Services\OneDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ContratacionRepostulacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_portal_creates_a_reapplication_for_the_same_rut_with_another_google_account(): void
    {
        Storage::fake('local');
        Mail::fake();

        $previous = PostulanteContratacion::create([
            'nombre' => 'Juan Guillermo Sandoval Guerra',
            'rut' => '18.527.794-1',
            'email' => 'correo-anterior@example.test',
            'google_id' => 'google-anterior',
            'google_name' => 'Juan Sandoval',
            'estado' => 'aprobado',
            'consentimiento_datos' => true,
            'es_vigente' => true,
        ]);

        $oneDrive = Mockery::mock(OneDriveService::class);
        // Si el respaldo histórico no puede subirse, la ficha principal no se
        // reemplaza: la operación se detiene para no perder la versión previa.
        $oneDrive->shouldReceive('isConfigured')->once()->andReturn(false);
        $this->app->instance(OneDriveService::class, $oneDrive);

        $response = $this->withSession([
            'contratacion_google_user' => [
                'id' => 'google-nuevo',
                'email' => 'correo-nuevo@example.test',
                'name' => 'Juan Sandoval',
                'avatar' => null,
            ],
        ])->post(route('contratacion-publico.store'), [
            'nombre' => 'Juan Guillermo Sandoval Guerra',
            'rut' => '18.527.794-1',
            'consentimiento_datos' => '1',
            'carnet_frontal' => UploadedFile::fake()->create('carnet-frontal.pdf', 10, 'application/pdf'),
            'carnet_reverso' => UploadedFile::fake()->create('carnet-reverso.pdf', 10, 'application/pdf'),
            'certificado_afp' => UploadedFile::fake()->create('afp.pdf', 10, 'application/pdf'),
            'certificado_fonasa' => UploadedFile::fake()->create('fonasa.pdf', 10, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $repostulacion = PostulanteContratacion::where('google_id', 'google-nuevo')->firstOrFail();
        $this->assertSame($previous->id, $repostulacion->postulacion_anterior_id);
        $this->assertTrue($repostulacion->es_repostulacion);
        $this->assertTrue($repostulacion->es_vigente);
        $this->assertSame('18.527.794-1', $repostulacion->rut);
        $this->assertSame('aprobado', $previous->fresh()->estado);
        $this->assertFalse($previous->fresh()->es_vigente);
    }

    public function test_approving_a_reapplication_makes_only_that_version_current(): void
    {
        $previous = PostulanteContratacion::create([
            'nombre' => 'Postulante Version Anterior',
            'rut' => '18.527.794-1',
            'email' => 'anterior@example.test',
            'google_id' => 'google-anterior',
            'estado' => 'aprobado',
            'consentimiento_datos' => true,
            'es_vigente' => true,
        ]);
        $repostulacion = PostulanteContratacion::create([
            'nombre' => 'Postulante Version Nueva',
            'rut' => '18.527.794-1',
            'email' => 'nuevo@example.test',
            'google_id' => 'google-nuevo',
            'estado' => 'en_revision',
            'consentimiento_datos' => true,
            'postulacion_anterior_id' => $previous->id,
            'es_vigente' => false,
        ]);

        $oneDrive = Mockery::mock(OneDriveService::class);
        $oneDrive->shouldReceive('isConfigured')->once()->andReturn(false);
        $this->app->instance(OneDriveService::class, $oneDrive);

        app(ContratacionController::class)->update(
            Request::create('/contratacion/'.$repostulacion->id, 'PATCH', ['estado' => 'aprobado']),
            $repostulacion,
        );

        $this->assertFalse($previous->fresh()->es_vigente);
        $this->assertTrue($repostulacion->fresh()->es_vigente);
        $this->assertSame('aprobado', $repostulacion->fresh()->estado);
    }
}
