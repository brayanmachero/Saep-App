<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerificarConsentimientoDatos;
use App\Models\Formulario;
use App\Models\Modulo;
use App\Models\Respuesta;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RespuestaExportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerificarConsentimientoDatos::class,
            ForcePasswordChange::class,
        ]);

        Modulo::updateOrCreate(
            ['slug' => 'formularios'],
            [
                'nombre' => 'Formularios',
                'descripcion' => 'Gestion de formularios',
                'icono' => 'bi-ui-checks-grid',
                'grupo' => 'Formularios',
                'orden' => 10,
                'activo' => true,
            ]
        );
    }

    public function test_respuestas_export_handles_nested_array_values(): void
    {
        $user = $this->createSuperAdminUser();

        $formulario = Formulario::create([
            'codigo' => 'FORM-EXPORT-QA-' . uniqid(),
            'nombre' => 'Formulario Export QA',
            'schema_json' => json_encode([
                ['id' => 'archivo', 'label' => 'Archivo adjunto', 'type' => 'file'],
                ['id' => 'checks', 'label' => 'Checks', 'type' => 'checkbox'],
                ['id' => 'metadata', 'label' => 'Metadata', 'type' => 'text'],
            ]),
            'version' => 1,
            'activo' => true,
            'requiere_aprobacion' => false,
            'genera_pdf' => false,
            'fuente_trabajadores' => 'talana',
            'creado_por' => $user->id,
        ]);

        Respuesta::create([
            'formulario_id' => $formulario->id,
            'version_form' => 1,
            'usuario_id' => $user->id,
            'estado' => 'Aprobado',
            'datos_json' => json_encode([
                'archivo' => [
                    ['name' => 'foto-uno.jpg', 'path' => 'respuestas/adjuntos/foto-uno.jpg'],
                    ['name' => 'foto-dos.jpg', 'path' => 'respuestas/adjuntos/foto-dos.jpg'],
                ],
                'checks' => [
                    'Check simple',
                    ['label' => 'Check compuesto', 'value' => 'Detalle'],
                ],
                'metadata' => [
                    'turno' => 'A',
                    'extra' => ['jefe' => 'Juan Perez', 'validado' => true],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)
            ->get(route('respuestas.exportar', ['formulario_id' => $formulario->id]));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'respuestas-export-') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('foto-uno.jpg, foto-dos.jpg', $sheet->getCell('G2')->getValue());
        $this->assertSame('Check simple, label: Check compuesto, value: Detalle', $sheet->getCell('H2')->getValue());
        $this->assertSame('turno: A, extra: jefe: Juan Perez, validado: Si', $sheet->getCell('I2')->getValue());

        @unlink($path);
    }

    private function createSuperAdminUser(): User
    {
        $role = Rol::firstOrCreate(
            ['codigo' => 'SUPER_ADMIN'],
            ['nombre' => 'Super Admin']
        );

        return User::create([
            'name' => 'Export QA Admin',
            'email' => 'export-qa-' . uniqid() . '@saep.local',
            'rol_id' => $role->id,
            'password' => Hash::make('Saep2026!'),
            'activo' => true,
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
            'must_change_password' => false,
        ]);
    }
}
