<?php

namespace Tests\Unit;

use App\Models\DescargaContenedor;
use App\Models\DescargaContenedorParticipante;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DescargaContenedorWorkflowTest extends TestCase
{
    public function test_tariff_blocker_explains_that_payment_is_completed_from_the_fact_tariff(): void
    {
        $descarga = new DescargaContenedor([
            'estado' => 'borrador',
            'fecha' => '2026-07-22',
            'contenedor' => 'MRKU0666691',
            'bodega' => 'Campos de Chile',
            'fact_codigo' => 'CNT001',
            'requiere_revision_tarifa' => false,
        ]);
        $descarga->setRelation('participantes', new Collection([
            new DescargaContenedorParticipante(['porcentaje_participacion' => 100]),
        ]));

        $action = $descarga->validationNextAction();

        $this->assertSame('Completar tarifa FACT', $action['label']);
        $this->assertSame('#tarifa_picker', $action['anchor']);
    }
}
