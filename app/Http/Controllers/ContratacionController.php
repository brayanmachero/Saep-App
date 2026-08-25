<?php

namespace App\Http\Controllers;

use App\Mail\ContratacionAcuseReciboMail;
use App\Mail\ContratacionNuevoPostulanteMail;
use App\Models\Configuracion;
use App\Models\ContratacionSyncLog;
use App\Models\PostulanteContratacion;
use App\Models\RegistroTratamientoDatos;
use App\Services\OneDriveService;
use App\Support\ContratacionSharePointPaths;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use setasign\Fpdi\Fpdi;
use ZipArchive;

class ContratacionController extends Controller
{
    private const DOCUMENT_MAX_KB = 102400; // 100 MB
    private const DOCUMENT_MAX_MB = 100;
    private const PDF_MEMORY_LIMIT = '1024M';
    private const PRIVACY_VERSION = 'contratacion-manual-v2026-06-17';
    private const PRIVACY_TEXT = 'RRHH declara que el postulante autorizo el tratamiento de sus datos personales y documentos exclusivamente para fines de reclutamiento, seleccion, contratacion, verificacion documental, comunicacion con RRHH y archivo del proceso, incluyendo la generacion y almacenamiento de una ficha PDF consolidada en SharePoint.';
    private const DOCUMENT_FIELDS = [
        'carnet_frontal',
        'carnet_reverso',
        'certificado_afp',
        'certificado_fonasa',
        'licencia_conducir_frontal',
        'licencia_conducir_reverso',
    ];

    // ─── Listado ─────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = PostulanteContratacion::query()->with(['ultimoSync', 'postulacionAnterior']);

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
        $postulante->load('postulacionAnterior');
        $versiones = PostulanteContratacion::query()
            ->where('rut', $postulante->rut)
            ->orderByDesc('es_vigente')
            ->latest('created_at')
            ->get();

        return view('contratacion.admin.show', compact('postulante', 'versiones'));
    }

    // ─── Ingreso manual (form) ────────────────────────────────────
    public function create()
    {
        return view('contratacion.admin.create');
    }

    // ─── Ingreso manual (guardar) ─────────────────────────────────
    public function storeManual(Request $request)
    {
        $documentRules = array_fill_keys(self::DOCUMENT_FIELDS, $this->documentRule(false));

        $request->validate(array_merge([
            'nombre'             => 'required|string|max:200',
            'rut'                => ['required', 'string', 'max:20', function ($attr, $val, $fail) {
                if (!PostulanteContratacion::validarRut($val)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'email'              => 'required|email|max:200',
            'consentimiento_datos' => 'accepted',
        ], $documentRules), [
            'consentimiento_datos.accepted' => 'Debes confirmar que el postulante autorizó el tratamiento de sus datos personales.',
            '*.mimes'                       => 'Solo se permiten archivos JPG, PNG o PDF.',
            '*.max'                         => 'El archivo no puede superar los ' . self::DOCUMENT_MAX_MB . ' MB.',
        ]);

        $rutLimpio     = preg_replace('/[^0-9kK]/', '', strtoupper($request->rut));
        $rutFormateado = PostulanteContratacion::formatearRut($rutLimpio);
        $rutCarpeta    = strtolower(preg_replace('/\./', '', $rutLimpio));

        if (PostulanteContratacion::where('rut', $rutFormateado)->exists()) {
            throw ValidationException::withMessages([
                'rut' => 'Este RUT ya tiene una postulación registrada.',
            ]);
        }

        $datos = array_merge([
            'nombre' => $request->nombre,
            'rut'    => $rutFormateado,
            'email'  => $request->email,
        ], $this->manualConsentPayload($request));

        foreach (self::DOCUMENT_FIELDS as $campo) {
            if ($request->hasFile($campo)) {
                $datos[$campo] = $this->storeDocument($request, $campo, $rutCarpeta);
            }
        }

        $postulante = PostulanteContratacion::create($datos);

        RegistroTratamientoDatos::registrar(
            'postulacion_manual_creada',
            'postulantes_contratacion',
            $postulante->id,
            'personal',
            "Postulante {$postulante->folio} creado manualmente por RRHH"
        );

        // Acuse al postulante
        try {
            Mail::to($postulante->email)->send(new ContratacionAcuseReciboMail($postulante));
        } catch (\Exception $e) {
            Log::warning('Contratacion manual: no se pudo enviar acuse recibo', ['email' => $postulante->email, 'folio' => $postulante->folio, 'error' => $e->getMessage()]);
        }

        // Notificación RRHH
        $this->notificarRrhh($postulante);

        // Subir ficha consolidada a SharePoint (no crítico)
        try {
            $this->subirFichaSharePoint($postulante);
        } catch (\Throwable $e) {
            Log::warning('Contratacion manual: SharePoint upload falló: ' . $e->getMessage());
        }

        return redirect()->route('contratacion.show', $postulante)
            ->with('success', "Postulante {$postulante->folio} ingresado correctamente.");
    }

    // ─── Helper: notificar RRHH ───────────────────────────────────
    private function notificarRrhh(PostulanteContratacion $postulante): void
    {
        $destinatarios = Configuracion::get('contratacion_emails_notificacion', '');
        if (empty(trim($destinatarios))) return;

        $emails = array_filter(array_map('trim', explode(',', $destinatarios)));
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($email)->send(new ContratacionNuevoPostulanteMail($postulante));
                } catch (\Exception $e) {
                    Log::warning('Contratacion: no se pudo notificar RRHH', ['email' => $email, 'folio' => $postulante->folio, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    // ─── Helper: subir ficha PDF a SharePoint ─────────────────────
    private function subirFichaSharePoint(PostulanteContratacion $postulante, ?string $destino = null): bool
    {
        $oneDrive = app(OneDriveService::class);

        $graphConfig = config('services.microsoft_graph');
        $site        = $graphConfig['contratacion_site']   ?? 'RRH';
        $folder      = $graphConfig['contratacion_folder'] ?? 'Postulantes Documents';
        $destino ??= $postulante->es_repostulacion && ! $postulante->es_vigente ? 'historial' : 'vigente';

        $intentoPrev = ContratacionSyncLog::where('postulante_id', $postulante->id)
            ->where('accion', ContratacionSyncLog::ACCION_SUBIDA_FICHA)
            ->max('intento');
        $syncLog = ContratacionSyncLog::create([
            'postulante_id'   => $postulante->id,
            'accion'          => ContratacionSyncLog::ACCION_SUBIDA_FICHA,
            'status'          => ContratacionSyncLog::STATUS_EN_PROCESO,
            'intento'         => (int) ($intentoPrev ?? 0) + 1,
            'sharepoint_site' => $site,
            'origen'          => $destino === 'historial' ? 'admin_historial' : 'admin_vigente',
            'started_at'      => now(),
        ]);

        if (!$oneDrive->isConfigured()) {
            $syncLog->update([
                'status'        => ContratacionSyncLog::STATUS_FALLIDO,
                'error_mensaje' => 'Microsoft Graph no configurado',
                'finished_at'   => now(),
            ]);
            return false;
        }

        try {
            $fichaBytes = $this->generarFichaBytes($postulante);
            $remotePath = $destino === 'historial'
                ? ContratacionSharePointPaths::historial($folder, $postulante)
                : ContratacionSharePointPaths::vigente($folder, $postulante);
            $filename = basename($remotePath);
            $ok = $oneDrive->uploadFileToSite(
                $site,
                $fichaBytes,
                $remotePath
            );
            $up = $oneDrive->lastUploadResult;
            $syncLog->update([
                'status'             => $ok ? ContratacionSyncLog::STATUS_EXITOSO : ContratacionSyncLog::STATUS_FALLIDO,
                'archivo_nombre'     => $filename,
                'archivo_tamano'     => strlen($fichaBytes),
                'sharepoint_path'    => $up['path'] ?? $remotePath,
                'sharepoint_item_id' => $up['item_id'] ?? null,
                'error_mensaje'      => $ok ? null : ($up['error'] ?? 'Subida falló'),
                'finished_at'        => now(),
            ]);
            return $ok;
        } catch (\Throwable $e) {
            Log::error('Contratacion manual: fallo subida ficha SharePoint', ['folio' => $postulante->folio, 'error' => $e->getMessage()]);
            $syncLog->update([
                'status'        => ContratacionSyncLog::STATUS_FALLIDO,
                'error_mensaje' => substr($e->getMessage(), 0, 1000),
                'finished_at'   => now(),
            ]);
            return false;
        }
    }

    // ─── Actualizar estado ────────────────────────────────────────
    public function update(Request $request, PostulanteContratacion $postulante)
    {
        $request->validate([
            'estado'        => 'required|in:pendiente,en_revision,aprobado,rechazado',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $publicarComoVigente = $postulante->es_repostulacion
            && ! $postulante->es_vigente
            && $request->estado === 'aprobado';

        DB::transaction(function () use ($postulante, $request, $publicarComoVigente): void {
            if ($publicarComoVigente) {
                PostulanteContratacion::query()
                    ->where('rut', $postulante->rut)
                    ->whereKeyNot($postulante->id)
                    ->update(['es_vigente' => false]);
            }

            $postulante->update([
                'estado'        => $request->estado,
                'observaciones' => $request->observaciones,
                'es_vigente'    => $publicarComoVigente ? true : $postulante->es_vigente,
            ]);
        });

        if ($publicarComoVigente) {
            try {
                // Se reutiliza la misma generación de ficha consolidada; solo
                // se publica la versión aprobada en la carpeta vigente.
                if (! $this->subirFichaSharePoint($postulante->fresh(), 'vigente')) {
                    return back()->with('warning', 'Estado actualizado, pero no se pudo publicar la ficha vigente en SharePoint. Usa “Sincronizar SharePoint” para reintentar.');
                }
            } catch (\Throwable $e) {
                Log::warning('Contratacion: no se pudo publicar repostulación vigente', [
                    'folio' => $postulante->folio,
                    'error' => $e->getMessage(),
                ]);

                return back()->with('warning', 'Estado actualizado, pero no se pudo publicar la ficha vigente en SharePoint. Usa “Sincronizar SharePoint” para reintentar.');
            }
        }

        return back()->with('success', $publicarComoVigente
            ? 'Repostulación aprobada y publicada como documentación vigente.'
            : 'Estado actualizado correctamente.');
    }

    // ─── Actualizar documentos desde admin ───────────────────────
    public function updateDocumentos(Request $request, PostulanteContratacion $postulante)
    {
        $request->validate(array_fill_keys(self::DOCUMENT_FIELDS, $this->documentRule(false)), [
            '*.mimes' => 'Solo se permiten archivos JPG, PNG o PDF.',
            '*.max'   => 'El archivo no puede superar los ' . self::DOCUMENT_MAX_MB . ' MB.',
        ]);

        $rutLimpio  = preg_replace('/[^0-9kK]/', '', strtoupper($postulante->rut));
        $rutCarpeta = strtolower(preg_replace('/\./', '', $rutLimpio));

        $actualizado = false;
        $rutasAnteriores = [];
        foreach (self::DOCUMENT_FIELDS as $campo) {
            if ($request->hasFile($campo)) {
                if ($postulante->$campo) {
                    $rutasAnteriores[] = $postulante->$campo;
                }
                $postulante->$campo = $this->storeDocument($request, $campo, $rutCarpeta);
                $actualizado = true;
            }
        }

        if (!$actualizado) {
            return back()->with('error', 'No se seleccionó ningún documento para subir.');
        }

        $postulante->save();
        $this->deleteOldDocuments($rutasAnteriores, $postulante->only(self::DOCUMENT_FIELDS));

        // Re-sincronizar ficha en SharePoint
        try {
            $this->subirFichaSharePoint($postulante);
        } catch (\Throwable $e) {
            Log::warning('Contratacion admin: SharePoint upload tras actualizar docs falló: ' . $e->getMessage());
        }

        return back()->with('success', 'Documentos actualizados y ficha PDF sincronizada en SharePoint.');
    }

    private function documentRule(bool $required): string
    {
        return ($required ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png,pdf|max:' . self::DOCUMENT_MAX_KB;
    }

    private function manualConsentPayload(Request $request): array
    {
        return [
            'consentimiento_datos'      => true,
            'consentimiento_at'         => now(),
            'consentimiento_version'    => self::PRIVACY_VERSION,
            'consentimiento_texto'      => self::PRIVACY_TEXT,
            'consentimiento_ip'         => $request->ip(),
            'consentimiento_user_agent' => Str::limit('Ingreso manual RRHH por usuario ' . auth()->id() . ' - ' . (string) $request->userAgent(), 500, ''),
        ];
    }

    private function storeDocument(Request $request, string $campo, string $rutCarpeta): string
    {
        $file = $request->file($campo);
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = $campo . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $ext;
        $path = $file->storeAs("contratacion/{$rutCarpeta}", $filename, 'local');

        if (!$path) {
            throw ValidationException::withMessages([
                $campo => 'No se pudo guardar el documento. Intenta nuevamente.',
            ]);
        }

        return $path;
    }

    private function deleteOldDocuments(array $oldPaths, array $newPaths): void
    {
        $newPaths = array_filter($newPaths);
        foreach (array_unique(array_filter($oldPaths)) as $oldPath) {
            if (!in_array($oldPath, $newPaths, true)) {
                Storage::disk('local')->delete($oldPath);
            }
        }
    }

    // ─── Descargar un documento ───────────────────────────────────
    public function descargarDocumento(PostulanteContratacion $postulante, string $campo)
    {
        $camposPermitidos = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir_frontal', 'licencia_conducir_reverso'];
        if (!in_array($campo, $camposPermitidos)) {
            abort(404);
        }

        $ruta = $postulante->$campo;
        if (!$ruta || !Storage::disk('local')->exists($ruta)) {
            abort(404, 'Documento no encontrado.');
        }

        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombreDescarga = $postulante->folio . '_' . $campo . '.' . $extension;

        return response()->streamDownload(function () use ($ruta) {
            echo Storage::disk('local')->get($ruta);
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
            if (Storage::disk('local')->exists($doc['ruta'])) {
                $contenido = Storage::disk('local')->get($doc['ruta']);
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
        $headers = ['Folio', 'Nombre', 'RUT', 'Email', 'Estado', 'Carnet F.', 'Carnet R.', 'AFP', 'FONASA', 'Lic. Frontal', 'Lic. Reverso', 'Fecha'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        // Datos
        foreach ($postulantes as $idx => $p) {
            $row = $idx + 2;
            $sheet->setCellValue([1,  $row], $p->folio);
            $sheet->setCellValue([2,  $row], $p->nombre);
            $sheet->setCellValue([3,  $row], $p->rut);
            $sheet->setCellValue([4,  $row], $p->email);
            $sheet->setCellValue([5,  $row], $p->estado_label);
            $sheet->setCellValue([6,  $row], $p->carnet_frontal              ? 'Sí' : 'No');
            $sheet->setCellValue([7,  $row], $p->carnet_reverso              ? 'Sí' : 'No');
            $sheet->setCellValue([8,  $row], $p->certificado_afp             ? 'Sí' : 'No');
            $sheet->setCellValue([9,  $row], $p->certificado_fonasa          ? 'Sí' : 'No');
            $sheet->setCellValue([10, $row], $p->licencia_conducir_frontal   ? 'Sí' : 'No');
            $sheet->setCellValue([11, $row], $p->licencia_conducir_reverso   ? 'Sí' : 'No');
            $sheet->setCellValue([12, $row], $p->created_at->format('d/m/Y H:i'));
        }

        // Autowidth
        foreach (range(1, 12) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer  = new Xlsx($spreadsheet);
        $tmpFile = sys_get_temp_dir() . '/contratacion_' . date('Ymd_His') . '.xlsx';
        $writer->save($tmpFile);

        return response()->download($tmpFile, 'Postulantes_Contratacion_' . date('Ymd') . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    // ─── Eliminar postulante (permanente) ────────────────────────
    public function destroy(PostulanteContratacion $postulante)
    {
        if (!auth()->user()->tieneAcceso('contratacion', 'puede_eliminar')) {
            abort(403, 'No tienes permiso para eliminar registros.');
        }

        // Eliminar archivos del storage (disco privado)
        $campos = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir_frontal', 'licencia_conducir_reverso'];
        foreach ($campos as $campo) {
            if (!empty($postulante->$campo)) {
                Storage::disk('local')->delete($postulante->$campo);
            }
        }

        $folio = $postulante->folio;
        $postulante->delete();

        Log::info('Contratacion: postulante eliminado', [
            'folio'      => $folio,
            'deleted_by' => auth()->id(),
        ]);

        RegistroTratamientoDatos::registrar(
            'eliminacion',
            'postulantes_contratacion',
            $postulante->id,
            'personal',
            "Postulante {$folio} eliminado por usuario autorizado"
        );

        return redirect()->route('contratacion.index')
            ->with('success', "Registro {$folio} eliminado permanentemente.");
    }

    // ─── Configuración (emails notificación) ─────────────────────
    public function configuracion()
    {
        $emails = Configuracion::get('contratacion_emails_notificacion', '');
        $cierreEmails = Configuracion::get('contratacion_cierre_diario_emails', 'mmejias@saep.cl, bmachero@saep.cl');

        return view('contratacion.admin.configuracion', compact('emails', 'cierreEmails'));
    }

    public function guardarConfiguracion(Request $request)
    {
        $request->validate([
            'emails' => 'nullable|string|max:1000',
            'cierre_emails' => 'nullable|string|max:1000',
        ]);

        // Validar cada email individualmente
        if ($request->filled('emails')) {
            $lista = array_map('trim', explode(',', $request->emails));
            foreach ($lista as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return back()->withErrors(['emails' => "El correo '{$email}' no es válido."])->withInput();
                }
            }
            Configuracion::updateOrCreate(
                ['clave' => 'contratacion_emails_notificacion'],
                [
                    'valor' => implode(', ', $lista),
                    'tipo' => 'TEXT',
                    'categoria' => 'contratacion',
                    'descripcion' => 'Correos que reciben aviso inmediato por cada nueva postulacion.',
                    'editable' => true,
                ]
            );
        } else {
            Configuracion::updateOrCreate(
                ['clave' => 'contratacion_emails_notificacion'],
                [
                    'valor' => '',
                    'tipo' => 'TEXT',
                    'categoria' => 'contratacion',
                    'descripcion' => 'Correos que reciben aviso inmediato por cada nueva postulacion.',
                    'editable' => true,
                ]
            );
        }

        if ($request->filled('cierre_emails')) {
            $listaCierre = array_map('trim', explode(',', $request->cierre_emails));
            foreach ($listaCierre as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return back()->withErrors(['cierre_emails' => "El correo '{$email}' no es válido."])->withInput();
                }
            }
            Configuracion::updateOrCreate(
                ['clave' => 'contratacion_cierre_diario_emails'],
                [
                    'valor' => implode(', ', $listaCierre),
                    'tipo' => 'TEXT',
                    'categoria' => 'contratacion',
                    'descripcion' => 'Destinatarios del cierre diario de postulantes RRHH.',
                    'editable' => true,
                ]
            );
        } else {
            Configuracion::updateOrCreate(
                ['clave' => 'contratacion_cierre_diario_emails'],
                [
                    'valor' => '',
                    'tipo' => 'TEXT',
                    'categoria' => 'contratacion',
                    'descripcion' => 'Destinatarios del cierre diario de postulantes RRHH.',
                    'editable' => true,
                ]
            );
        }

        return back()->with('success', 'Configuración guardada.');
    }

    // ─── Ficha PDF del postulante ─────────────────────────────────
    public function fichaPdf(PostulanteContratacion $postulante)
    {
        $fichaBytes = $this->generarFichaBytes($postulante);
        $filename   = $this->fichaFilename($postulante);

        return response($fichaBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─── Re-sincronizar ficha en SharePoint ───────────────────────
    public function resincronizarSharePoint(PostulanteContratacion $postulante)
    {
        $oneDrive = app(OneDriveService::class);

        $graphConfig = config('services.microsoft_graph');
        $site        = $graphConfig['contratacion_site']   ?? 'RRH';
        $folder      = $graphConfig['contratacion_folder'] ?? 'Postulantes Documents';
        $destino     = $postulante->es_repostulacion && ! $postulante->es_vigente ? 'historial' : 'vigente';

        $intentoPrev = ContratacionSyncLog::where('postulante_id', $postulante->id)
            ->where('accion', ContratacionSyncLog::ACCION_RESINCRONIZACION)
            ->max('intento');
        $syncLog = ContratacionSyncLog::create([
            'postulante_id'   => $postulante->id,
            'accion'          => ContratacionSyncLog::ACCION_RESINCRONIZACION,
            'status'          => ContratacionSyncLog::STATUS_EN_PROCESO,
            'intento'         => (int) ($intentoPrev ?? 0) + 1,
            'sharepoint_site' => $site,
            'origen'          => 'manual_admin',
            'started_at'      => now(),
        ]);

        if (!$oneDrive->isConfigured()) {
            $syncLog->update([
                'status'        => ContratacionSyncLog::STATUS_FALLIDO,
                'error_mensaje' => 'Microsoft Graph no configurado',
                'finished_at'   => now(),
            ]);
            return back()->with('error', 'Microsoft Graph no está configurado.');
        }

        try {
            $fichaBytes = $this->generarFichaBytes($postulante);
            $remotePath = $destino === 'historial'
                ? ContratacionSharePointPaths::historial($folder, $postulante)
                : ContratacionSharePointPaths::vigente($folder, $postulante);
            $filename = basename($remotePath);
            $ok = $oneDrive->uploadFileToSite(
                $site,
                $fichaBytes,
                $remotePath
            );
            $up = $oneDrive->lastUploadResult;
            $syncLog->update([
                'status'             => $ok ? ContratacionSyncLog::STATUS_EXITOSO : ContratacionSyncLog::STATUS_FALLIDO,
                'archivo_nombre'     => $filename,
                'archivo_tamano'     => strlen($fichaBytes),
                'sharepoint_path'    => $up['path'] ?? $remotePath,
                'sharepoint_item_id' => $up['item_id'] ?? null,
                'error_mensaje'      => $ok ? null : ($up['error'] ?? 'Subida falló'),
                'finished_at'        => now(),
            ]);

            if (!$ok) {
                return back()->with('error', 'No se pudo subir a SharePoint: ' . ($up['error'] ?? 'error desconocido'));
            }

            Log::info('Contratacion admin: resincronizacion SharePoint completada', ['folio' => $postulante->folio]);
            return back()->with('success', 'Ficha consolidada sincronizada en SharePoint correctamente.');
        } catch (\Throwable $e) {
            Log::error('Contratacion admin: fallo resincronizacion SharePoint', [
                'folio' => $postulante->folio,
                'error' => $e->getMessage(),
            ]);
            $syncLog->update([
                'status'        => ContratacionSyncLog::STATUS_FALLIDO,
                'error_mensaje' => substr($e->getMessage(), 0, 1000),
                'finished_at'   => now(),
            ]);
            return back()->with('error', 'Error al sincronizar con SharePoint: ' . $e->getMessage());
        }
    }

    // ─── Generar bytes de la ficha PDF (DomPDF + FPDI merge) ─────
    private function generarFichaBytes(PostulanteContratacion $postulante): string
    {
        $camposLabels = [
            'carnet_frontal'            => 'Carnet de Identidad (Frontal)',
            'carnet_reverso'            => 'Carnet de Identidad (Reverso)',
            'certificado_afp'           => 'Certificado AFP',
            'certificado_fonasa'        => 'Certificado FONASA',
            'licencia_conducir_frontal' => 'Licencia de Conducir (Frontal)',
            'licencia_conducir_reverso' => 'Licencia de Conducir (Reverso)',
        ];

        $documentos  = [];
        $pdfDocRutas = [];

        foreach ($camposLabels as $campo => $label) {
            if (empty($postulante->$campo)) continue;

            $ruta = $postulante->$campo;
            $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

            if (!Storage::disk('local')->exists($ruta)) {
                $documentos[] = ['label' => $label, 'tipo' => 'ausente', 'data' => null, 'ext' => $ext];
                continue;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $documentos[] = [
                    'label' => $label,
                    'tipo'  => 'imagen',
                    'data'  => $this->imageDataUriForPdf($ruta, $ext),
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
        ini_set('memory_limit', self::PDF_MEMORY_LIMIT);

        $fichaBytes = Pdf::loadView('pdf.contratacion_ficha', compact('postulante', 'documentos'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('tempDir', $tmpDir)
            ->setOption('fontDir', $fontDir)
            ->setOption('fontCache', $tmpDir)
            ->output();

        $tempFiles = [];

        if (!empty($pdfDocRutas)) {
            // ── Guardar ficha como archivo temporal ──────────────────────────
            $tempFicha = tempnam($tmpDir, 'ficha_') . '.pdf';
            file_put_contents($tempFicha, $fichaBytes);
            $tempFiles[] = $tempFicha;

            // ── Descargar todos los PDFs a archivos temporales ───────────────
            $docTempPaths = []; // label => path
            foreach ($pdfDocRutas as $docInfo) {
                $pdfBytes = Storage::disk('local')->get($docInfo['ruta']);
                $tempDoc  = tempnam($tmpDir, 'doc_') . '.pdf';
                file_put_contents($tempDoc, $pdfBytes ?? '');
                $tempFiles[]                     = $tempDoc;
                $docTempPaths[$docInfo['label']] = $tempDoc;
            }

            $gsPath = $this->gsPath();
            Log::info('PDF merge: diagnóstico', [
                'gs_path'   => $gsPath ?: 'N/A',
                'exec'      => function_exists('exec')      ? 'sí' : 'no',
                'proc_open' => function_exists('proc_open') ? 'sí' : 'no',
                'imagick'   => class_exists('Imagick')       ? 'sí' : 'no',
                'docs'      => count($pdfDocRutas),
            ]);

            $fichaFinalizada = false;

            // ══════════════════════════════════════════════════════════════════
            // Estrategia 1: GS merge directo de TODOS los PDFs (maneja PDF 1.5+)
            // ══════════════════════════════════════════════════════════════════
            if ($gsPath) {
                $tempMerged = tempnam($tmpDir, 'merged_') . '.pdf';
                @unlink($tempMerged);
                $tempFiles[] = $tempMerged;

                $allInputs = array_merge([$tempFicha], array_values($docTempPaths));
                $inputArgs = implode(' ', array_map('escapeshellarg', $allInputs));
                // -dSAFER: input no confiable (PDFs del público). NUNCA usar -dNOSAFER aquí.
                $cmd = sprintf(
                    '%s -dBATCH -dNOPAUSE -dSAFER -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s 2>&1',
                    escapeshellarg($gsPath),
                    escapeshellarg($tempMerged),
                    $inputArgs
                );
                $gsOut = []; $gsRet = -1;
                $this->runShell($cmd, $gsOut, $gsRet);

                if ($gsRet === 0 && file_exists($tempMerged) && filesize($tempMerged) > 1000) {
                    $fichaBytes      = file_get_contents($tempMerged);
                    $fichaFinalizada = true;
                    Log::info('PDF merge: exitoso via GS pdfwrite directo');
                } else {
                    Log::warning('PDF merge: GS pdfwrite falló', [
                        'ret' => $gsRet,
                        'out' => implode("\n", array_slice($gsOut, 0, 10)),
                    ]);
                }
            }

            // ══════════════════════════════════════════════════════════════════
            // Estrategia 2: FPDI por documento + Imagick / GS-a-PNG fallback
            // ══════════════════════════════════════════════════════════════════
            if (!$fichaFinalizada) {
                $fpdi = null;
                try {
                    $fpdi = new Fpdi();
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
                        $tempDoc   = $docTempPaths[$docInfo['label']];
                        $docMerged = false;

                        // 2a: FPDI directo
                        try {
                            $n = $fpdi->setSourceFile($tempDoc);
                            for ($i = 1; $i <= $n; $i++) {
                                $tpl = $fpdi->importPage($i);
                                [$w, $h] = array_values($fpdi->getTemplateSize($tpl));
                                $fpdi->AddPage($h > $w ? 'P' : 'L', [$w, $h]);
                                $fpdi->useTemplate($tpl, 0, 0, $w, $h, true);
                            }
                            $docMerged = true;
                        } catch (\Throwable $ex) {
                            Log::warning('FPDI falló: ' . $docInfo['label'] . ' — ' . $ex->getMessage());
                        }

                        // 2b: Imagick
                        if (!$docMerged && class_exists('Imagick')) {
                            try {
                                $imagick = new \Imagick();
                                $imagick->setResolution(150, 150);
                                $imagick->readImage($tempDoc);
                                foreach ($imagick as $pageImg) {
                                    $pageImg->setImageFormat('png');
                                    $pageImg->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                                    $imgW    = $pageImg->getImageWidth();
                                    $imgH    = $pageImg->getImageHeight();
                                    $pxMm    = 25.4 / 150;
                                    $wMm     = round($imgW * $pxMm, 2);
                                    $hMm     = round($imgH * $pxMm, 2);
                                    $tempPng = tempnam($tmpDir, 'pg_') . '.png';
                                    file_put_contents($tempPng, $pageImg->getImageBlob());
                                    $tempFiles[] = $tempPng;
                                    $fpdi->AddPage($hMm > $wMm ? 'P' : 'L', [$wMm, $hMm]);
                                    $fpdi->Image($tempPng, 0, 0, $wMm, $hMm, 'PNG');
                                }
                                $imagick->destroy();
                                $docMerged = true;
                                Log::info('Imagick: doc convertido: ' . $docInfo['label']);
                            } catch (\Throwable $ex2) {
                                Log::warning('Imagick falló: ' . $docInfo['label'] . ' — ' . $ex2->getMessage());
                            }
                        }

                        // 2c: GS a PNG
                        if (!$docMerged && $gsPath) {
                            $pngDir = $tmpDir . '/gs_' . uniqid();
                            @mkdir($pngDir, 0755);
                            $cmd = sprintf(
                                '%s -dBATCH -dNOPAUSE -dSAFER -sDEVICE=png16m -r150 -sOutputFile=%s %s 2>&1',
                                escapeshellarg($gsPath),
                                escapeshellarg($pngDir . '/page_%04d.png'),
                                escapeshellarg($tempDoc)
                            );
                            $gsOut = []; $gsRet = -1;
                            $this->runShell($cmd, $gsOut, $gsRet);
                            if ($gsRet === 0) {
                                $pngPages = glob($pngDir . '/page_*.png') ?: [];
                                natsort($pngPages);
                                foreach ($pngPages as $pngFile) {
                                    [$imgW, $imgH] = @getimagesize($pngFile) ?: [595, 842];
                                    $pxMm = 25.4 / 150;
                                    $wMm  = round($imgW * $pxMm, 2);
                                    $hMm  = round($imgH * $pxMm, 2);
                                    $tempFiles[] = $pngFile;
                                    $fpdi->AddPage($hMm > $wMm ? 'P' : 'L', [$wMm, $hMm]);
                                    $fpdi->Image($pngFile, 0, 0, $wMm, $hMm, 'PNG');
                                }
                                $docMerged = true;
                                Log::info('GS PNG: doc convertido: ' . $docInfo['label']);
                            } else {
                                Log::warning('GS PNG falló: ' . $docInfo['label'], [
                                    'ret' => $gsRet,
                                    'out' => implode("\n", array_slice($gsOut, 0, 5)),
                                ]);
                            }
                        }

                        if (!$docMerged) {
                            Log::warning('PDF merge: todos los métodos fallaron para: ' . $docInfo['label']);
                        }
                    }
                    $fichaBytes = $fpdi->Output('S');
                }
            }
        }

        foreach ($tempFiles as $tf) {
            @unlink($tf);
        }

        ini_set('memory_limit', $memPrev);

        return $fichaBytes;
    }

    // ─── Helper: nombre estándar del PDF consolidado ─────────────────────────
    private function fichaFilename(PostulanteContratacion $postulante): string
    {
        $seq = (int) substr(strrchr($postulante->folio, '-'), 1);
        $num = str_pad($seq, 3, '0', STR_PAD_LEFT);
        return $postulante->rut . ' - FICHA ' . $num . ' - ' . $postulante->nombre . '.pdf';
    }

    private function imageDataUriForPdf(string $ruta, string $ext): string
    {
        $contenido = Storage::disk('local')->get($ruta);
        $mime = match($ext) {
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        if (strlen($contenido) <= 5 * 1024 * 1024 || !function_exists('imagecreatefromstring')) {
            return 'data:' . $mime . ';base64,' . base64_encode($contenido);
        }

        try {
            $src = @imagecreatefromstring($contenido);
            if (!$src) {
                return 'data:' . $mime . ';base64,' . base64_encode($contenido);
            }

            $width = imagesx($src);
            $height = imagesy($src);
            $maxWidth = 1600;
            $maxHeight = 2200;
            $scale = min($maxWidth / max($width, 1), $maxHeight / max($height, 1), 1);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $dst = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            imagejpeg($dst, null, 82);
            $reduced = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);

            if ($reduced && strlen($reduced) < strlen($contenido)) {
                return 'data:image/jpeg;base64,' . base64_encode($reduced);
            }
        } catch (\Throwable $e) {
            Log::warning('Contratacion: no se pudo reducir imagen para PDF', ['ruta' => $ruta, 'error' => $e->getMessage()]);
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }

    // ─── Helper: encontrar ruta de Ghostscript ────────────────────────────────
    private function gsPath(): string
    {
        // Primero buscar directamente (no requiere exec/shell_exec)
        foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/bin/gs'] as $p) {
            if (@is_executable($p)) {
                return $p;
            }
        }
        // Fallback via shell
        if (function_exists('shell_exec')) {
            $p = trim((string)(@shell_exec('which gs 2>/dev/null') ?? ''));
            if ($p && @is_executable($p)) {
                return $p;
            }
        }
        if (function_exists('exec')) {
            $out = []; $ret = -1;
            @exec('which gs 2>/dev/null', $out, $ret);
            if ($ret === 0 && !empty($out[0])) {
                $p = trim($out[0]);
                if (@is_executable($p)) {
                    return $p;
                }
            }
        }
        return '';
    }

    // ─── Helper: ejecutar comando en shell con múltiples fallbacks ────────────
    private function runShell(string $cmd, array &$output, int &$retVal): bool
    {
        if (function_exists('exec')) {
            exec($cmd, $output, $retVal);
            return true;
        }
        if (function_exists('proc_open')) {
            $desc = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($cmd, $desc, $pipes);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $retVal = proc_close($proc);
                $output = array_values(array_filter(explode("\n", trim($stdout . "\n" . $stderr))));
                return true;
            }
        }
        if (function_exists('passthru')) {
            ob_start();
            passthru($cmd, $retVal);
            $output = array_values(array_filter(explode("\n", ob_get_clean())));
            return true;
        }
        $retVal = -1;
        return false;
    }
}
