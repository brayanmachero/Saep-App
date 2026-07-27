<?php

namespace Tests\Unit;

use App\Mail\RespuestaFormularioMail;
use App\Models\Formulario;
use App\Models\Respuesta;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RespuestaFormularioMailTest extends TestCase
{
    public function test_large_form_attachments_are_omitted_before_resend_limit_is_reached(): void
    {
        Config::set('mail.response_attachment_max_bytes', 10);
        Storage::fake('public');

        Storage::disk('public')->put('respuestas/adjuntos/1/pequeno.pdf', '1234');
        Storage::disk('public')->put('respuestas/adjuntos/1/grande.pdf', '12345678');

        $formulario = new Formulario([
            'nombre' => 'Formulario de prueba',
            'schema_json' => json_encode([
                ['id' => 'adjuntos', 'type' => 'file'],
            ]),
        ]);

        $respuesta = new Respuesta([
            'datos_json' => json_encode([
                'adjuntos' => [
                    ['path' => 'respuestas/adjuntos/1/pequeno.pdf', 'name' => 'pequeno.pdf', 'mime' => 'application/pdf'],
                    ['path' => 'respuestas/adjuntos/1/grande.pdf', 'name' => 'grande.pdf', 'mime' => 'application/pdf'],
                ],
            ]),
        ]);
        $respuesta->setRelation('formulario', $formulario);

        $mail = new RespuestaFormularioMail($respuesta);

        $this->assertSame([
            ['name' => 'grande.pdf', 'size' => 8],
        ], $mail->adjuntosOmitidos);
        $this->assertCount(1, $mail->attachments());
    }
}
