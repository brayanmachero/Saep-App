<?php

namespace App\Http\Controllers;

use App\Models\Charla;
use App\Models\Respuesta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    /**
     * Generate PDF for a Respuesta (formulario response).
     */
    public function respuesta(Respuesta $respuesta)
    {
        $respuesta->load('formulario', 'usuario', 'aprobaciones.aprobador');
        $datos  = json_decode($respuesta->datos_json ?? '{}', true);
        $schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);

        $pdf = Pdf::loadView('pdf.respuesta', compact('respuesta', 'datos', 'schema'))
            ->setPaper('a4', 'portrait');

        $filename = 'solicitud-' . str_pad($respuesta->id, 5, '0', STR_PAD_LEFT) . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Generate attendance PDF for a Charla SST.
     */
    public function charla(Charla $charla)
    {
        $charla->load([
            'creador', 'supervisor', 'centroCosto',
            'relatores.usuario',
            'asistentes.usuario.rol',
        ]);

        $pdf = Pdf::loadView('pdf.charla', compact('charla'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'charla-sst-' . str_pad($charla->id, 5, '0', STR_PAD_LEFT) . '.pdf';
        return $pdf->download($filename);
    }
}
