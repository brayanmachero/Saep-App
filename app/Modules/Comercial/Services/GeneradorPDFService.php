<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\Cotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio para generar PDFs de cotizaciones
 *
 * Genera PDFs con branding SAEP
 * Incluye cálculos completos, detalles, y firma de autorización
 */
class GeneradorPDFService
{
    /**
     * Generar PDF para una cotización
     */
    public function generar(Cotizacion $cotizacion): \Barryvdh\DomPDF\PDF
    {
        $datos = $this->prepararDatos($cotizacion);

        $pdf = Pdf::loadView('comercial::reportes.cotizacion-pdf', $datos, [
            'title' => "Cotización {$cotizacion->numero}",
        ]);

        // Configurar opciones de PDF
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('margin-top', 15);
        $pdf->setOption('margin-right', 15);
        $pdf->setOption('margin-left', 15);
        $pdf->setOption('margin-bottom', 15);

        return $pdf;
    }

    /**
     * Guardar PDF en storage
     */
    public function guardarPDF(Cotizacion $cotizacion): string
    {
        $pdf = $this->generar($cotizacion);
        $filename = "cotizaciones/{$cotizacion->numero}.pdf";

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Guardar una copia final e inmutable para cotizaciones aprobadas o vigentes.
     */
    public function guardarPDFFinal(Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing(['cliente', 'centroCosto', 'modalidad', 'detalles', 'uniformes', 'usuario']);

        $contenido = $this->generar($cotizacion)->output();
        $filename = $this->rutaPDFFinal($cotizacion);

        if (! Storage::disk('local')->put($filename, $contenido)) {
            throw new \RuntimeException('No fue posible guardar el PDF final de la cotización.');
        }

        return [
            'path' => $filename,
            'hash' => hash('sha256', $contenido),
            'generado_at' => now(),
        ];
    }

    /**
     * Obtener el PDF que debe enviarse o descargarse. Si existe copia final, se usa esa.
     */
    public function contenidoPDF(Cotizacion $cotizacion): string
    {
        if ($this->existePDFFinal($cotizacion)) {
            return Storage::disk('local')->get($cotizacion->pdf_final_path);
        }

        return $this->generar($cotizacion)->output();
    }

    /**
     * Descargar PDF
     */
    public function descargar(Cotizacion $cotizacion)
    {
        if ($this->existePDFFinal($cotizacion)) {
            return Storage::disk('local')->download(
                $cotizacion->pdf_final_path,
                "cotizacion-{$cotizacion->numero}.pdf",
                ['Content-Type' => 'application/pdf']
            );
        }

        return $this->generar($cotizacion)->download("cotizacion-{$cotizacion->numero}.pdf");
    }

    /**
     * Preparar datos para la vista
     */
    private function prepararDatos(Cotizacion $cotizacion): array
    {
        $cotizacion->load(['cliente', 'centroCosto', 'modalidad', 'detalles', 'uniformes', 'usuario']);

        $datosCalculo = $cotizacion->datos_calculo ?? [];

        return [
            'cotizacion' => $cotizacion,
            'cliente' => $cotizacion->cliente,
            'centroCosto' => $cotizacion->centroCosto,
            'modalidad' => $cotizacion->modalidad,
            'detalles' => $cotizacion->detalles,
            'uniformes' => $cotizacion->uniformes,
            'usuario' => $cotizacion->usuario,
            'datos_calculo' => $datosCalculo,
            'fecha_emision' => now()->format('d/m/Y H:i'),
            'logo' => $this->obtenerLogo(),
            'empresa' => config('app.name'),
        ];
    }

    /**
     * Obtener logo en base64
     */
    private function obtenerLogo(): ?string
    {
        $logoCarpetas = [
            public_path('brand/wp/Logo_Saep.svg'),
            public_path('brand/wp/Logo-Saep_footer.svg'),
            public_path('images/saep-logo.png'),
            public_path('images/logo.png'),
            public_path('logo.png'),
        ];

        foreach ($logoCarpetas as $path) {
            if (file_exists($path)) {
                $data = file_get_contents($path);
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = $extension === 'svg' ? 'image/svg+xml' : 'image/png';

                return "data:{$mime};base64," . base64_encode($data);
            }
        }

        return null;
    }

    /**
     * Generar PDF con marca de agua "BORRADOR"
     */
    public function generarBorrador(Cotizacion $cotizacion): \Barryvdh\DomPDF\PDF
    {
        $datos = $this->prepararDatos($cotizacion);
        $datos['es_borrador'] = true;

        $pdf = Pdf::loadView('comercial::reportes.cotizacion-pdf', $datos, [
            'title' => "BORRADOR - Cotización {$cotizacion->numero}",
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);

        return $pdf;
    }

    /**
     * Exportar múltiples cotizaciones a ZIP
     */
    public function exportarMultiples(array $cotizaciones): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión ZIP de PHP no está disponible para exportar cotizaciones múltiples.');
        }

        $zipPath = storage_path('app/cotizaciones/cotizaciones-'.now()->format('Ymd-His').'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No fue posible crear el archivo ZIP de cotizaciones.');
        }

        foreach ($cotizaciones as $cotizacion) {
            if (! $cotizacion instanceof Cotizacion) {
                continue;
            }

            $nombre = preg_replace('/[^A-Za-z0-9._-]/', '-', $cotizacion->numero ?: 'cotizacion-'.$cotizacion->id);
            $zip->addFromString("{$nombre}.pdf", $this->generar($cotizacion)->output());
        }

        $zip->close();

        return $zipPath;
    }

    private function existePDFFinal(Cotizacion $cotizacion): bool
    {
        return is_string($cotizacion->pdf_final_path)
            && $cotizacion->pdf_final_path !== ''
            && Storage::disk('local')->exists($cotizacion->pdf_final_path);
    }

    private function rutaPDFFinal(Cotizacion $cotizacion): string
    {
        $numero = preg_replace('/[^A-Za-z0-9._-]/', '-', $cotizacion->numero ?: 'cotizacion-'.$cotizacion->id);
        $estado = preg_replace('/[^A-Za-z0-9._-]/', '-', $cotizacion->estado ?: 'final');

        return sprintf(
            'cotizaciones/finales/%s/%s-%s-%s.pdf',
            now()->format('Y'),
            $numero,
            $estado,
            now()->format('YmdHis')
        );
    }
}
