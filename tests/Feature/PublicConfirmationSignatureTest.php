<?php

namespace Tests\Feature;

use App\Models\LeyKarin;
use App\Models\PostulanteContratacion;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicConfirmationSignatureTest extends TestCase
{
    private array $leyKarinIds = [];
    private array $postulanteIds = [];

    protected function tearDown(): void
    {
        if ($this->leyKarinIds !== []) {
            LeyKarin::withTrashed()->whereIn('id', $this->leyKarinIds)->forceDelete();
        }

        if ($this->postulanteIds !== []) {
            PostulanteContratacion::withTrashed()->whereIn('id', $this->postulanteIds)->forceDelete();
        }

        parent::tearDown();
    }

    public function test_public_ley_karin_confirmation_requires_signed_url(): void
    {
        $caso = LeyKarin::create([
            'tipo' => 'ACOSO_LABORAL',
            'denunciante_nombre' => 'Persona Denunciante Test',
            'denunciante_email' => 'denuncia-confirmacion-' . uniqid() . '@saep.local',
            'fecha_denuncia' => now()->toDateString(),
            'descripcion_hechos' => 'Relato de prueba para validar confirmacion firmada publica.',
            'canal' => 'FORMULARIO_WEB',
            'confidencial' => true,
            'consentimiento_datos' => true,
        ]);
        $this->leyKarinIds[] = $caso->id;

        $this->get(route('ley-karin-publico.confirmacion', $caso->folio))
            ->assertForbidden();

        $this->get(URL::temporarySignedRoute(
            'ley-karin-publico.confirmacion',
            now()->addDays(7),
            ['folio' => $caso->folio]
        ))->assertOk()
            ->assertSee($caso->folio);
    }

    public function test_public_hiring_confirmation_requires_signed_url(): void
    {
        $postulante = PostulanteContratacion::create([
            'nombre' => 'Postulante Confirmacion Test',
            'rut' => '12.345.678-5',
            'email' => 'postulacion-confirmacion-' . uniqid() . '@saep.local',
            'google_id' => 'google-' . uniqid(),
            'google_name' => 'Postulante Confirmacion Test',
            'estado' => 'pendiente',
            'consentimiento_datos' => true,
        ]);
        $this->postulanteIds[] = $postulante->id;

        $this->get(route('contratacion-publico.confirmacion', $postulante->folio))
            ->assertForbidden();

        $this->withSession([
            'contratacion_google_user' => [
                'id' => $postulante->google_id,
                'email' => $postulante->email,
                'name' => $postulante->google_name,
                'avatar' => null,
            ],
        ])->get(URL::temporarySignedRoute(
            'contratacion-publico.confirmacion',
            now()->addDays(7),
            ['folio' => $postulante->folio]
        ))->assertOk()
            ->assertSee($postulante->folio)
            ->assertSee($postulante->email);
    }
}
