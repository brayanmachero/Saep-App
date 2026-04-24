<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\PostulanteContratacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $campos = [
            'carnet_frontal'    => 'Carnet de Identidad (Frontal)',
            'carnet_reverso'    => 'Carnet de Identidad (Reverso)',
            'certificado_afp'   => 'Certificado AFP',
            'certificado_fonasa'=> 'Certificado FONASA',
            'licencia_conducir' => 'Licencia de Conducir',
        ];

        // Preparar documentos: imágenes como base64, PDFs como referencia
        $documentos = [];
        foreach ($campos as $campo => $label) {
            if (empty($postulante->$campo)) continue;

            $ruta = $postulante->$campo;
            $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

            if (!Storage::disk('public')->exists($ruta)) {
                $documentos[] = ['label' => $label, 'tipo' => 'ausente', 'data' => null, 'ext' => $ext];
                continue;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $contenido = Storage::disk('public')->get($ruta);
                $mime      = match($ext) {
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
                // Es PDF: no se puede embeber inline en dompdf, se indica con url
                $url = Storage::disk('public')->url($ruta);
                $documentos[] = ['label' => $label, 'tipo' => 'pdf', 'data' => $url, 'ext' => $ext];
            }
        }

        $pdf = Pdf::loadView('pdf.contratacion_ficha', compact('postulante', 'documentos'))
            ->setPaper('a4', 'portrait');

        $filename = $postulante->folio . '_ficha.pdf';
        return $pdf->download($filename);
    }
}
