<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\PostulanteContratacion;
use App\Services\OneDriveService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use setasign\Fpdi\Fpdi;
use ZipArchive;

class ContratacionController extends Controller
{
    // ─── Middleware: solo admin ───────────────────────────────────
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || auth()->user()->rol !== 'admin') {
                abort(403, 'Acceso no autorizado.');
            }
            return $next($request);
        });
    }

    // ─── Listado ─────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = PostulanteContratacion::query();

        if ($request->filled('buscar')) {
            $b = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where(function ($q) use ($b) {
                $q->where('folio', 'like', "%{$b}%")
                  ->orWhere('nombre', 'like', "%{$b}%")
                  ->orWhere('rut', 'like', "%{$b}%")
                  ->orWhere('email', 'like', "%{$b}%");
            });
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $postulantes = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $stats = [
            'total'       => PostulanteContratacion::count(),
            'pendiente'   => PostulanteContratacion::where('estado', 'pendiente')->count(),
            'en_revision' => PostulanteContratacion::where('estado', 'en_revision')->count(),
            'aprobado'    => PostulanteContratacion::where('estado', 'aprobado')->count(),
            'rechazado'   => PostulanteContratacion::where('estado', 'rechazado')->count(),
        ];

        return view('contratacion.admin.index', compact('postulantes', 'stats'));
    }

    // ─── Detalle ─────────────────────────────────────────────────
    public function show(PostulanteContratacion $postulante)
    {
        return view('contratacion.admin.show', compact('postulante'));
    }

    // ─── Actualizar estado ────────────────────────────────────────
    public function update(Request $request, PostulanteContratacion $postulante)
    {
        $request->validate([
            'estado'        => 'required|in:pendiente,en_revision,aprobado,rechazado',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $postulante->update([
            'estado'        => $request->estado,
            'observaciones' => $request->observaciones,
        ]);

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    // ─── Descargar un documento ───────────────────────────────────
    public function descargarDocumento(PostulanteContratacion $postulante, string $campo)
    {
        $camposPermitidos = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir'];
        if (!in_array($campo, $camposPermitidos)) {
            abort(404);
        }

        $ruta = $postulante->$campo;
        if (!$ruta || !Storage::disk('public')->exists($ruta)) {
            abort(404, 'Documento no encontrado.');
        }

        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombreDescarga = $postulante->folio . '_' . $campo . '.' . $extension;

        return response()->streamDownload(function () use ($ruta) {
            echo Storage::disk('public')->get($ruta);
        }, $nombreDescarga);
    }

    // ─── Descargar ZIP con todos los documentos ───────────────────
    public function descargarZip(PostulanteContratacion $postulante)
    {
        $docs = $postulante->documentosSubidos();
        if (empty($docs)) {
            return back()->with('error', 'Este postulante no tiene documentos subidos.');
        }

        $zipPath = sys_get_temp_dir() . '/' . $postulante->folio . '_documentos.zip';
        $zip     = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo generar el ZIP.');
        }

        foreach ($docs as $doc) {
            if (Storage::disk('public')->exists($doc['ruta'])) {
                $contenido = Storage::disk('public')->get($doc['ruta']);
                $ext       = pathinfo($doc['ruta'], PATHINFO_EXTENSION);
                $zip->addFromString($doc['campo'] . '.' . $ext, $contenido);
            }
        }
        $zip->close();

        return response()->download($zipPath, $postulante->folio . '_documentos.zip')
            ->deleteFileAfterSend(true);
    }

    // ─── Exportar Excel ──────────────────────────────────────────
    public function exportarExcel()
    {
        $postulantes = PostulanteContratacion::orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Postulantes');

        // Encabezados
        $headers = ['Folio', 'Nombre', 'RUT', 'Email', 'Estado', 'Carnet F.', 'Carnet R.', 'AFP', 'FONASA', 'Licencia', 'Fecha'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }

        // Datos
        foreach ($postulantes as $idx => $p) {
            $row = $idx + 2;
            $sheet->setCellValueByColumnAndRow(1,  $row, $p->folio);
            $sheet->setCellValueByColumnAndRow(2,  $row, $p->nombre);
            $sheet->setCellValueByColumnAndRow(3,  $row, $p->rut);
            $sheet->setCellValueByColumnAndRow(4,  $row, $p->email);
            $sheet->setCellValueByColumnAndRow(5,  $row, $p->estado_label);
            $sheet->setCellValueByColumnAndRow(6,  $row, $p->carnet_frontal     ? 'Sí' : 'No');
            $sheet->setCellValueByColumnAndRow(7,  $row, $p->carnet_reverso     ? 'Sí' : 'No');
            $sheet->setCellValueByColumnAndRow(8,  $row, $p->certificado_afp    ? 'Sí' : 'No');
            $sheet->setCellValueByColumnAndRow(9,  $row, $p->certificado_fonasa ? 'Sí' : 'No');
            $sheet->setCellValueByColumnAndRow(10, $row, $p->licencia_conducir  ? 'Sí' : 'No');
            $sheet->setCellValueByColumnAndRow(11, $row, $p->created_at->format('d/m/Y H:i'));
        }

        // Autowidth
        foreach (range(1, 11) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer  = new Xlsx($spreadsheet);
        $tmpFile = sys_get_temp_dir() . '/contratacion_' . date('Ymd_His') . '.xlsx';
        $writer->save($tmpFile);

        return response()->download($tmpFile, 'Postulantes_Contratacion_' . date('Ymd') . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    // ─── Configuración (emails notificación) ─────────────────────
    public function configuracion()
    {
        $emails = Configuracion::get('contratacion_emails_notificacion', '');
        return view('contratacion.admin.configuracion', compact('emails'));
    }

    public function guardarConfiguracion(Request $request)
    {
        $request->validate([
            'emails' => 'nullable|string|max:1000',
        ]);

        // Validar cada email individualmente
        if ($request->filled('emails')) {
            $lista = array_map('trim', explode(',', $request->emails));
            foreach ($lista as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return back()->withErrors(['emails' => "El correo '{$email}' no es válido."])->withInput();
                }
            }
            Configuracion::set('contratacion_emails_notificacion', implode(', ', $lista));
        } else {
            Configuracion::set('contratacion_emails_notificacion', '');
        }

        return back()->with('success', 'Configuración guardada.');
    }

    // ─── Ficha PDF del postulante ─────────────────────────────────
    public function fichaPdf(PostulanteContratacion $postulante)
    {
        $fichaBytes = $this->generarFichaBytes($postulante);
        $filename   = $postulante->folio . '_ficha.pdf';

        return response($fichaBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─── Re-sincronizar ficha en SharePoint ───────────────────────
    public function resincronizarSharePoint(PostulanteContratacion $postulante)
    {
        $oneDrive = app(OneDriveService::class);
        if (!$oneDrive->isConfigured()) {
            return back()->with('error', 'Microsoft Graph no está configurado.');
        }

        try {
            $graphConfig = config('services.microsoft_graph');
            $site        = $graphConfig['contratacion_site']   ?? 'RRH';
            $folder      = $graphConfig['contratacion_folder'] ?? 'Postulantes Documents';
            $carpeta     = $postulante->rut . ' - ' . $postulante->nombre;

            // Re-subir documentos originales
            $camposDocs = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir'];
            foreach ($camposDocs as $campo) {
                if (empty($postulante->$campo)) continue;
                if (!Storage::disk('public')->exists($postulante->$campo)) continue;

                $ext     = strtolower(pathinfo($postulante->$campo, PATHINFO_EXTENSION));
                $mime    = match($ext) {
                    'png'         => 'image/png',
                    'gif'         => 'image/gif',
                    'webp'        => 'image/webp',
                    'jpg','jpeg'  => 'image/jpeg',
                    default       => 'application/pdf',
                };
                $content = Storage::disk('public')->get($postulante->$campo);
                $oneDrive->uploadFileToSite($site, $content, "{$folder}/{$carpeta}/{$campo}.{$ext}", $mime);
            }

            // Re-generar y subir ficha PDF
            $fichaBytes = $this->generarFichaBytes($postulante);
            $oneDrive->uploadFileToSite(
                $site,
                $fichaBytes,
                "{$folder}/{$carpeta}/{$postulante->folio}_ficha.pdf"
            );

            Log::info('Contratacion admin: resincronizacion SharePoint completada', ['folio' => $postulante->folio]);
            return back()->with('success', 'Ficha y documentos sincronizados en SharePoint correctamente.');
        } catch (\Throwable $e) {
            Log::error('Contratacion admin: fallo resincronizacion SharePoint', [
                'folio' => $postulante->folio,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Error al sincronizar con SharePoint: ' . $e->getMessage());
        }
    }

    // ─── Generar bytes de la ficha PDF (DomPDF + FPDI merge) ─────
    private function generarFichaBytes(PostulanteContratacion $postulante): string
    {
        $camposLabels = [
            'carnet_frontal'     => 'Carnet de Identidad (Frontal)',
            'carnet_reverso'     => 'Carnet de Identidad (Reverso)',
            'certificado_afp'    => 'Certificado AFP',
            'certificado_fonasa' => 'Certificado FONASA',
            'licencia_conducir'  => 'Licencia de Conducir',
        ];

        $documentos  = [];
        $pdfDocRutas = [];

        foreach ($camposLabels as $campo => $label) {
            if (empty($postulante->$campo)) continue;

            $ruta = $postulante->$campo;
            $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

            if (!Storage::disk('public')->exists($ruta)) {
                $documentos[] = ['label' => $label, 'tipo' => 'ausente', 'data' => null, 'ext' => $ext];
                continue;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $contenido    = Storage::disk('public')->get($ruta);
                $mime         = match($ext) {
                    'png'  => 'image/png',
                    'gif'  => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };
                $documentos[] = [
                    'label' => $label,
                    'tipo'  => 'imagen',
                    'data'  => 'data:' . $mime . ';base64,' . base64_encode($contenido),
                    'ext'   => $ext,
                ];
            } else {
                $documentos[]  = ['label' => $label, 'tipo' => 'pdf', 'data' => null, 'ext' => $ext];
                $pdfDocRutas[] = ['label' => $label, 'ruta' => $ruta];
            }
        }

        $tmpDir  = sys_get_temp_dir();
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $memPrev    = ini_get('memory_limit');
        ini_set('memory_limit', '384M');

        $fichaBytes = Pdf::loadView('pdf.contratacion_ficha', compact('postulante', 'documentos'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('tempDir', $tmpDir)
            ->setOption('fontDir', $fontDir)
            ->setOption('fontCache', $tmpDir)
            ->output();

        $tempFiles = [];

        if (!empty($pdfDocRutas)) {
            $tempFicha   = tempnam($tmpDir, 'ficha_') . '.pdf';
            file_put_contents($tempFicha, $fichaBytes);
            $tempFiles[] = $tempFicha;

            $fpdi = new Fpdi();

            try {
                $n = $fpdi->setSourceFile($tempFicha);
                for ($i = 1; $i <= $n; $i++) {
                    $tpl = $fpdi->importPage($i);
                    [$w, $h] = array_values($fpdi->getTemplateSize($tpl));
                    $fpdi->AddPage($h > $w ? 'P' : 'L', [$w, $h]);
                    $fpdi->useTemplate($tpl, 0, 0, $w, $h, true);
                }
            } catch (\Throwable) {
                $fpdi = null;
            }

            if ($fpdi !== null) {
                foreach ($pdfDocRutas as $docInfo) {
                    $pdfBytes    = Storage::disk('public')->get($docInfo['ruta']);
                    $tempDoc     = tempnam($tmpDir, 'doc_') . '.pdf';
                    file_put_contents($tempDoc, $pdfBytes);
                    $tempFiles[] = $tempDoc;

                    try {
                        $n = $fpdi->setSourceFile($tempDoc);
                        for ($i = 1; $i <= $n; $i++) {
                            $tpl = $fpdi->importPage($i);
                            [$w, $h] = array_values($fpdi->getTemplateSize($tpl));
                            $fpdi->AddPage($h > $w ? 'P' : 'L', [$w, $h]);
                            $fpdi->useTemplate($tpl, 0, 0, $w, $h, true);
                        }
                    } catch (\Throwable) {
                        // Omitir este documento si FPDI no puede leerlo
                    }
                }

                $fichaBytes = $fpdi->Output('S');
            }

            foreach ($tempFiles as $tf) {
                @unlink($tf);
            }
        }

        ini_set('memory_limit', $memPrev);

        return $fichaBytes;
    }
}
