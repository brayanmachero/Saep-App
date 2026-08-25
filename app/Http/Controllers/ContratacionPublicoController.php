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
use App\Support\PrivacyPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class ContratacionPublicoController extends Controller
{
    private const DOCUMENT_MAX_KB = 102400; // 100 MB
    private const DOCUMENT_MAX_MB = 100;
    private const HEIC_EXTENSIONS = ['heic', 'heif'];
    private const PDF_MEMORY_LIMIT = '1024M';
    private const PRIVACY_VERSION = 'contratacion-v2026-06-17';
    private const PRIVACY_TEXT = 'Autorizo a SAEP el tratamiento de mis datos personales y documentos de postulacion exclusivamente para fines de reclutamiento, seleccion, contratacion, verificacion documental, comunicacion con RRHH y archivo del proceso, incluyendo la generacion y almacenamiento de una ficha PDF consolidada en SharePoint.';
    private const TEMP_UPLOAD_SESSION_KEY = 'contratacion_temp_uploads';
    private const TEMP_UPLOAD_DIR = 'contratacion_tmp';
    private const TEMP_UPLOAD_TTL_SECONDS = 28800; // 8 horas
    private const DOCUMENT_FIELDS = [
        'carnet_frontal',
        'carnet_reverso',
        'certificado_afp',
        'certificado_fonasa',
        'licencia_conducir_frontal',
        'licencia_conducir_reverso',
    ];

    // ─── Paso 1: Landing ────────────────────────────────────────
    public function inicio()
    {
        if (Session::has('contratacion_google_user')) {
            return redirect()->route('contratacion-publico.formulario');
        }
        return view('contratacion.publico.inicio');
    }

    // ─── Paso 2: Redirigir a Google ──────────────────────────────
    public function redirectGoogle()
    {
        return Socialite::driver('google')
            ->redirectUrl(route('contratacion-publico.callback'))
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    // ─── Paso 3: Callback Google ─────────────────────────────────
    public function callbackGoogle()
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('contratacion-publico.callback'))
                ->user();

            Session::put('contratacion_google_user', [
                'id'     => $googleUser->getId(),
                'email'  => $googleUser->getEmail(),
                'name'   => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            return redirect()->route('contratacion-publico.formulario');
        } catch (\Exception $e) {
            return redirect()->route('contratacion-publico.inicio')
                ->with('error', 'No se pudo verificar tu cuenta de Google. Intenta nuevamente.');
        }
    }

    // ─── Paso 4: Formulario ──────────────────────────────────────
    public function formulario()
    {
        $googleUser = Session::get('contratacion_google_user');
        if (!$googleUser) {
            return redirect()->route('contratacion-publico.inicio')
                ->with('error', 'Debes iniciar sesión con Google para continuar.');
        }

        // Si ya tiene postulación, llevar a edición
        $postulante = PostulanteContratacion::where('google_id', $googleUser['id'])->first();
        $this->purgeExpiredTempUploads();
        $tempUploadsByField = $this->tempUploadsByField($googleUser['id']);

        return view('contratacion.publico.formulario', compact('googleUser', 'postulante', 'tempUploadsByField'));
    }

    public function preuploadDocumento(Request $request)
    {
        $googleUser = Session::get('contratacion_google_user');
        if (!$googleUser) {
            return response()->json(['message' => 'Sesión expirada. Inicia sesión con Google nuevamente.'], 401);
        }

        $this->purgeExpiredTempUploads();

        $request->validate([
            'campo'     => 'required|string|in:' . implode(',', self::DOCUMENT_FIELDS),
            'documento' => $this->documentRule(true),
        ], [
            'campo.in'            => 'El tipo de documento no es válido.',
            'documento.required'  => 'Selecciona un archivo para subir.',
            'documento.max'       => 'El archivo no puede superar los ' . self::DOCUMENT_MAX_MB . ' MB.',
        ]);

        $campo = (string) $request->input('campo');
        $preparedDocument = $this->prepareDocumentForStorage($request->file('documento'), $campo);

        try {
            $file = $preparedDocument['file'];
            $ext = strtolower($file->getClientOriginalExtension());
            $token = Str::random(48);
            $folder = self::TEMP_UPLOAD_DIR . '/' . hash('sha256', $googleUser['id'] . '|' . Session::getId());
            $filename = $campo . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $ext;
            $path = $file->storeAs($folder, $filename, 'local');

            if (!$path) {
                return response()->json(['message' => 'No se pudo guardar el documento temporal. Intenta nuevamente.'], 422);
            }

            $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);
            $uploads[$token] = [
                'token'         => $token,
                'campo'         => $campo,
                'google_id'     => $googleUser['id'],
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'extension'     => $ext,
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_at'   => now()->timestamp,
            ];
            Session::put(self::TEMP_UPLOAD_SESSION_KEY, $uploads);
            $this->discardTempUploadForField($campo, $googleUser['id'], '', $token);

            return response()->json([
                'ok'                  => true,
                'token'               => $token,
                'campo'               => $campo,
                'original_name'       => $file->getClientOriginalName(),
                'size'                => $file->getSize(),
                'size_label'          => $this->formatBytes((int) $file->getSize()),
                'converted_from_heic' => $preparedDocument['converted_from_heic'],
            ]);
        } finally {
            $this->cleanupPreparedDocument($preparedDocument);
        }
    }

    public function descartarPreuploadDocumento(Request $request)
    {
        $googleUser = Session::get('contratacion_google_user');
        if (!$googleUser) {
            return response()->json(['message' => 'Sesión expirada. Inicia sesión con Google nuevamente.'], 401);
        }

        $request->validate([
            'campo' => 'required|string|in:' . implode(',', self::DOCUMENT_FIELDS),
            'token' => 'nullable|string|max:120',
        ]);

        $removed = $this->discardTempUploadForField(
            (string) $request->input('campo'),
            $googleUser['id'],
            (string) $request->input('token', '')
        );

        return response()->json(['ok' => true, 'removed' => $removed]);
    }

    public function registrarErrorDocumento(Request $request)
    {
        $googleUser = Session::get('contratacion_google_user');
        if (!$googleUser) {
            return response()->json(['message' => 'Sesión expirada. Inicia sesión con Google nuevamente.'], 401);
        }

        $data = $request->validate([
            'campo'             => 'nullable|string|max:80',
            'fase'              => 'required|string|max:80',
            'mensaje'           => 'nullable|string|max:500',
            'archivo_nombre'    => 'nullable|string|max:255',
            'archivo_tamano'    => 'nullable|integer|min:0',
            'archivo_tipo'      => 'nullable|string|max:120',
            'http_status'       => 'nullable|integer|min:0|max:599',
            'xhr_response'      => 'nullable|string|max:1000',
            'navigator_online'  => 'nullable|boolean',
            'user_agent_cliente' => 'nullable|string|max:500',
        ]);

        Log::warning('Contratacion publico: error frontend upload documento', [
            'google_id_hash'    => hash('sha256', (string) ($googleUser['id'] ?? '')),
            'email_hash'        => hash('sha256', (string) ($googleUser['email'] ?? '')),
            'campo'             => $data['campo'] ?? null,
            'fase'              => $data['fase'],
            'mensaje'           => $data['mensaje'] ?? null,
            'archivo_nombre'    => isset($data['archivo_nombre']) ? Str::limit($data['archivo_nombre'], 120, '') : null,
            'archivo_tamano'    => $data['archivo_tamano'] ?? null,
            'archivo_tipo'      => $data['archivo_tipo'] ?? null,
            'http_status'       => $data['http_status'] ?? null,
            'xhr_response'      => isset($data['xhr_response']) ? Str::limit($data['xhr_response'], 500, '') : null,
            'navigator_online'  => $data['navigator_online'] ?? null,
            'ip'                => $request->ip(),
            'user_agent'        => Str::limit((string) $request->userAgent(), 500, ''),
            'user_agent_cliente' => $data['user_agent_cliente'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─── Paso 5: Guardar / actualizar postulación ────────────────
    public function store(Request $request)
    {
        $googleUser = Session::get('contratacion_google_user');
        if (!$googleUser) {
            return redirect()->route('contratacion-publico.inicio')
                ->with('error', 'Sesión expirada. Inicia sesión con Google nuevamente.');
        }

        // Buscar postulación existente ANTES de validar para aplicar reglas condicionales
        $postulante = PostulanteContratacion::where('google_id', $googleUser['id'])->first();
        $esNuevo    = !$postulante;
        $this->purgeExpiredTempUploads();
        $tempUploads = $this->validTempUploadsFromRequest($request, $googleUser['id']);

        // Documentos obligatorios: required si no existe registro previo O si aún no han sido subidos
        $requeridosObligatorios = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa'];
        $docRules = [];
        foreach ($requeridosObligatorios as $campo) {
            $yaTiene = $postulante && !empty($postulante->$campo);
            $tieneTemporal = isset($tempUploads[$campo]);
            $docRules[$campo] = $this->documentRule(!$yaTiene && !$tieneTemporal);
        }

        // Licencia: opcional, pero si se tiene/sube uno de los dos el otro se vuelve obligatorio
        $tendraLicF = ($postulante && !empty($postulante->licencia_conducir_frontal)) || $request->hasFile('licencia_conducir_frontal') || isset($tempUploads['licencia_conducir_frontal']);
        $tendraLicR = ($postulante && !empty($postulante->licencia_conducir_reverso)) || $request->hasFile('licencia_conducir_reverso') || isset($tempUploads['licencia_conducir_reverso']);
        $docRules['licencia_conducir_frontal'] = $this->documentRule($tendraLicR && !$tendraLicF);
        $docRules['licencia_conducir_reverso'] = $this->documentRule($tendraLicF && !$tendraLicR);

        $request->validate(array_merge([
            'nombre' => 'required|string|max:200',
            'rut'    => ['required', 'string', 'max:20', function ($attr, $val, $fail) {
                if (!PostulanteContratacion::validarRut($val)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'consentimiento_datos' => 'accepted',
            'uploaded_documents' => 'nullable|array',
            'uploaded_documents.*' => 'nullable|string|max:120',
        ], $docRules), [
            'carnet_frontal.required'              => 'El Carnet de Identidad (Frontal) es obligatorio.',
            'carnet_reverso.required'              => 'El Carnet de Identidad (Reverso) es obligatorio.',
            'certificado_afp.required'             => 'El Certificado AFP es obligatorio.',
            'certificado_fonasa.required'          => 'El Certificado FONASA es obligatorio.',
            'licencia_conducir_frontal.required'   => 'Debes subir el frontal de la Licencia de Conducir (ya tienes el reverso).',
            'licencia_conducir_reverso.required'   => 'Debes subir el reverso de la Licencia de Conducir (ya tienes el frontal).',
            'consentimiento_datos.accepted'        => 'Debes autorizar el tratamiento de tus datos personales para enviar la postulación.',
            '*.max'                                => 'El archivo no puede superar los ' . self::DOCUMENT_MAX_MB . ' MB.',
        ]);

        $rutLimpio = preg_replace('/[^0-9kK]/', '', strtoupper($request->rut));
        $rutFormateado = PostulanteContratacion::formatearRut($rutLimpio);
        $rutCarpeta    = strtolower(preg_replace('/\./', '', $rutLimpio));

        // El RUT identifica a la persona, no a su cuenta Google. Si vuelve a
        // postular con otro correo, se crea una versión nueva y aislada del
        // expediente anterior para que RRHH pueda revisar ambos historiales.
        $postulacionAnterior = $esNuevo
            ? PostulanteContratacion::query()
                ->where('rut', $rutFormateado)
                ->orderByDesc('es_vigente')
                ->latest('id')
                ->first()
            : null;
        $esRepostulacion = $postulacionAnterior !== null;

        if (!$esNuevo && $rutFormateado !== $postulante->rut) {
            throw ValidationException::withMessages([
                'rut' => 'El RUT de una postulación existente no puede modificarse desde el portal público.',
            ]);
        }

        // En registro NUEVO se aceptan nombre/rut del request.
        // En re-postulación se preservan los datos originales del primer envío
        // (no permitimos al postulante cambiar su identidad por la UI).
        if ($esNuevo) {
            $datos = [
                'nombre'        => $request->nombre,
                'rut'           => $rutFormateado,
                'email'         => $googleUser['email'],
                'google_id'     => $googleUser['id'],
                'google_name'   => $googleUser['name'],
                'google_avatar' => $googleUser['avatar'],
                'postulacion_anterior_id' => $postulacionAnterior?->id,
                // Hasta que RRHH revise una repostulación, la documentación
                // vigente continúa siendo la versión anterior.
                'es_vigente' => ! $esRepostulacion,
            ];
        } else {
            $datos = [
                'email'         => $googleUser['email'],
                'google_name'   => $googleUser['name'],
                'google_avatar' => $googleUser['avatar'],
            ];
            $rutCarpeta = strtolower(preg_replace('/\./', '', preg_replace('/[^0-9kK]/', '', strtoupper($postulante->rut))));
        }
        $datos = array_merge($datos, $this->consentPayload($request));

        // Subir documentos que lleguen en este request (disco PRIVADO: storage/app/private)
        $rutasAnteriores = [];
        foreach (self::DOCUMENT_FIELDS as $campo) {
            if ($request->hasFile($campo)) {
                if ($postulante && $postulante->$campo) {
                    $rutasAnteriores[] = $postulante->$campo;
                }
                $path = $this->storeDocument($request, $campo, $rutCarpeta);
                $datos[$campo] = $path;
            } elseif (isset($tempUploads[$campo])) {
                if ($postulante && $postulante->$campo) {
                    $rutasAnteriores[] = $postulante->$campo;
                }
                $datos[$campo] = $this->moveTempDocument($tempUploads[$campo], $rutCarpeta);
            }
        }

        if ($esNuevo) {
            $postulante = PostulanteContratacion::create($datos);
        } else {
            $postulante->update($datos);
        }
        $this->deleteOldDocuments($rutasAnteriores, array_intersect_key($datos, array_flip(self::DOCUMENT_FIELDS)));

        RegistroTratamientoDatos::registrar(
            $esNuevo ? ($esRepostulacion ? 'repostulacion_publica_creada' : 'postulacion_publica_creada') : 'postulacion_publica_actualizada',
            'postulantes_contratacion',
            $postulante->id,
            'personal',
            ($esRepostulacion ? "Repostulación pública {$postulante->folio} vinculada a {$postulacionAnterior->folio}" : "Postulación pública {$postulante->folio}")
                . ' recibida con consentimiento v' . PrivacyPolicy::VERSION
        );

        // Enviar emails solo en la primera postulación
        if ($esNuevo) {
            // Acuse al postulante
            try {
                Mail::to($postulante->email)->send(new ContratacionAcuseReciboMail($postulante));
            } catch (\Throwable $e) {
                Log::warning('Contratacion: no se pudo enviar acuse recibo', ['email' => $postulante->email, 'folio' => $postulante->folio, 'error' => $e->getMessage()]);
            }

            // Notificación a destinatarios configurados
            $this->notificarRrhh($postulante);
        }

        // Refrescar desde BD para garantizar que subirASharePoint tenga TODOS los documentos
        // (incluyendo los que ya existían y no se actualizaron en este request)
        $postulante->refresh();

        // Subir documentos + ficha PDF a SharePoint (no crítico)
        try {
            // La ficha vigente anterior queda congelada como versión histórica
            // antes de recibir la nueva. El contenido del PDF no se modifica.
            if ($esNuevo && $esRepostulacion && $postulacionAnterior) {
                $this->subirASharePoint($postulacionAnterior->fresh(), 'historial');
            }
            $this->subirASharePoint($postulante);
        } catch (\Throwable $e) {
            Log::warning('SharePoint contratacion upload falló (no crítico): ' . $e->getMessage());
        }

        $this->clearTempUploadsForGoogle($googleUser['id']);

        return redirect(URL::temporarySignedRoute(
            'contratacion-publico.confirmacion',
            now()->addDays(7),
            ['folio' => $postulante->folio]
        ));
    }

    // ─── Paso 6: Confirmación ────────────────────────────────────
    public function confirmacion(string $folio)
    {
        $googleUser = Session::get('contratacion_google_user');
        if (!$googleUser) {
            return redirect()->route('contratacion-publico.inicio')
                ->with('error', 'Debes iniciar sesión con Google para revisar tu confirmación.');
        }

        $postulante = PostulanteContratacion::where('folio', $folio)
            ->where('google_id', $googleUser['id'])
            ->firstOrFail();

        return view('contratacion.publico.confirmacion', compact('postulante'));
    }

    // ─── Logout ──────────────────────────────────────────────────
    public function logout()
    {
        Session::forget('contratacion_google_user');
        return redirect()->route('contratacion-publico.inicio')
            ->with('success', 'Sesión cerrada correctamente.');
    }

    // ─── Subida a SharePoint ─────────────────────────────────────
    private function subirASharePoint(PostulanteContratacion $postulante, ?string $destino = null): void
    {
        $graphConfig = config('services.microsoft_graph');
        $site        = $graphConfig['contratacion_site']   ?? 'RRH';
        $folder      = $graphConfig['contratacion_folder'] ?? 'Postulantes Documents';

        $oneDrive = app(OneDriveService::class);
        $destino ??= $postulante->es_repostulacion && ! $postulante->es_vigente ? 'historial' : 'vigente';

        // Registro inicial de sincronización (visible al admin)
        $intentoPrev = ContratacionSyncLog::where('postulante_id', $postulante->id)
            ->where('accion', ContratacionSyncLog::ACCION_SUBIDA_FICHA)
            ->max('intento');
        $syncLog = ContratacionSyncLog::create([
            'postulante_id'   => $postulante->id,
            'accion'          => ContratacionSyncLog::ACCION_SUBIDA_FICHA,
            'status'          => ContratacionSyncLog::STATUS_EN_PROCESO,
            'intento'         => (int) ($intentoPrev ?? 0) + 1,
            'sharepoint_site' => $site,
            'origen'          => $destino === 'historial' ? 'portal_publico_historial' : 'portal_publico_vigente',
            'started_at'      => now(),
        ]);

        if (!$oneDrive->isConfigured()) {
            $syncLog->update([
                'status'        => ContratacionSyncLog::STATUS_FALLIDO,
                'error_mensaje' => 'Microsoft Graph no configurado',
                'finished_at'   => now(),
            ]);
            return;
        }

        // Generar PDF consolidado y subirlo
        try {
            Log::info('SharePoint contratacion: construyendo array documentos', ['folio' => $postulante->folio]);

            $documentos   = [];
            $pdfDocRutas  = []; // rutas de documentos PDF a mergear después
            $camposLabels = [
                'carnet_frontal'            => 'Carnet de Identidad (Frontal)',
                'carnet_reverso'            => 'Carnet de Identidad (Reverso)',
                'certificado_afp'           => 'Certificado AFP',
                'certificado_fonasa'        => 'Certificado FONASA',
                'licencia_conducir_frontal' => 'Licencia de Conducir (Frontal)',
                'licencia_conducir_reverso' => 'Licencia de Conducir (Reverso)',
            ];
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
                    // PDF: lo marcamos para append con FPDI (no se muestra en la ficha DomPDF)
                    $documentos[]   = ['label' => $label, 'tipo' => 'pdf', 'data' => null, 'ext' => $ext];
                    $pdfDocRutas[]  = ['label' => $label, 'ruta' => $ruta];
                }
            }

            Log::info('SharePoint contratacion: generando PDF ficha', [
                'folio'     => $postulante->folio,
                'docs'      => count($documentos),
                'tipos'     => array_column($documentos, 'tipo'),
                'pdf_count' => count($pdfDocRutas),
            ]);

            // Asegurar directorio temporal escribible para DomPDF
            $tmpDir  = sys_get_temp_dir();
            $fontDir = storage_path('fonts');
            if (!is_dir($fontDir)) {
                @mkdir($fontDir, 0755, true);
            }

            // Aumentar límite de memoria
            $memPrev = ini_get('memory_limit');
            ini_set('memory_limit', self::PDF_MEMORY_LIMIT);

            // Paso A: DomPDF genera la ficha con datos personales + imágenes
            $fichaBytes = Pdf::loadView('pdf.contratacion_ficha', compact('postulante', 'documentos'))
                ->setPaper('a4', 'portrait')
                ->setOption('isRemoteEnabled', false)
                ->setOption('tempDir', $tmpDir)
                ->setOption('fontDir', $fontDir)
                ->setOption('fontCache', $tmpDir)
                ->output();

            Log::info('SharePoint contratacion: ficha DomPDF generada', [
                'folio' => $postulante->folio,
                'size'  => strlen($fichaBytes),
            ]);

            // Paso B: Merge con GS/FPDI si hay documentos PDF
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
                Log::info('SharePoint contratacion: PDF merge diagnóstico', [
                    'folio'     => $postulante->folio,
                    'gs_path'   => $gsPath ?: 'N/A',
                    'exec'      => function_exists('exec')      ? 'sí' : 'no',
                    'proc_open' => function_exists('proc_open') ? 'sí' : 'no',
                    'imagick'   => class_exists('Imagick')       ? 'sí' : 'no',
                    'docs'      => count($pdfDocRutas),
                ]);

                $fichaFinalizada = false;

                // ════════════════════════════════════════════════════════════════
                // Estrategia 1: GS merge directo de TODOS los PDFs (maneja PDF 1.5+)
                // ════════════════════════════════════════════════════════════════
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
                        Log::info('SharePoint contratacion: merge exitoso via GS pdfwrite', ['folio' => $postulante->folio]);
                    } else {
                        Log::warning('SharePoint contratacion: GS pdfwrite falló', [
                            'folio' => $postulante->folio,
                            'ret'   => $gsRet,
                            'out'   => implode("\n", array_slice($gsOut, 0, 10)),
                        ]);
                    }
                }

                // ════════════════════════════════════════════════════════════════
                // Estrategia 2: FPDI por documento + Imagick / GS-a-PNG fallback
                // ════════════════════════════════════════════════════════════════
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
                    } catch (\Throwable $ex) {
                        Log::warning('SharePoint contratacion: FPDI no pudo importar ficha', [
                            'folio' => $postulante->folio,
                            'error' => $ex->getMessage(),
                        ]);
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
                                Log::warning('SharePoint contratacion: FPDI falló', [
                                    'folio' => $postulante->folio,
                                    'label' => $docInfo['label'],
                                    'error' => $ex->getMessage(),
                                ]);
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
                                    Log::info('SharePoint contratacion: Imagick convirtió doc', [
                                        'folio' => $postulante->folio,
                                        'label' => $docInfo['label'],
                                    ]);
                                } catch (\Throwable $ex2) {
                                    Log::warning('SharePoint contratacion: Imagick falló', [
                                        'folio' => $postulante->folio,
                                        'label' => $docInfo['label'],
                                        'error' => $ex2->getMessage(),
                                    ]);
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
                                    Log::info('SharePoint contratacion: GS PNG convirtió doc', [
                                        'folio' => $postulante->folio,
                                        'label' => $docInfo['label'],
                                    ]);
                                } else {
                                    Log::warning('SharePoint contratacion: GS PNG falló', [
                                        'folio' => $postulante->folio,
                                        'label' => $docInfo['label'],
                                        'ret'   => $gsRet,
                                        'out'   => implode("\n", array_slice($gsOut, 0, 5)),
                                    ]);
                                }
                            }

                            if (!$docMerged) {
                                Log::warning('SharePoint contratacion: todos los métodos fallaron', [
                                    'folio' => $postulante->folio,
                                    'label' => $docInfo['label'],
                                ]);
                            }
                        }

                        $fichaBytes = $fpdi->Output('S');
                        Log::info('SharePoint contratacion: merge FPDI completado', [
                            'folio' => $postulante->folio,
                            'size'  => strlen($fichaBytes),
                        ]);
                    }
                }
            }

            // Limpiar archivos temporales
            foreach ($tempFiles as $tf) {
                @unlink($tf);
            }

            ini_set('memory_limit', $memPrev);

            Log::info('SharePoint contratacion: PDF final listo, subiendo a SharePoint', [
                'folio' => $postulante->folio,
                'size'  => strlen($fichaBytes),
            ]);

            $remotePath = $destino === 'historial'
                ? ContratacionSharePointPaths::historial($folder, $postulante)
                : ContratacionSharePointPaths::vigente($folder, $postulante);
            $fichaFilename = basename($remotePath);

            $ok = $oneDrive->uploadFileToSite(
                $site,
                $fichaBytes,
                $remotePath
            );

            $up = $oneDrive->lastUploadResult;
            $syncLog->update([
                'status'             => $ok ? ContratacionSyncLog::STATUS_EXITOSO : ContratacionSyncLog::STATUS_FALLIDO,
                'archivo_nombre'     => $fichaFilename,
                'archivo_tamano'     => strlen($fichaBytes),
                'sharepoint_path'    => $up['path'] ?? $remotePath,
                'sharepoint_item_id' => $up['item_id'] ?? null,
                'error_mensaje'      => $ok ? null : ($up['error'] ?? 'Subida falló'),
                'finished_at'        => now(),
            ]);

            Log::info('SharePoint contratacion: ficha PDF subida exitosamente', ['folio' => $postulante->folio]);
        } catch (\Throwable $e) {
            Log::error('SharePoint contratacion: fallo en generacion/subida de ficha PDF', [
                'folio'   => $postulante->folio,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
                'trace'   => substr($e->getTraceAsString(), 0, 2000),
            ]);
            $syncLog->update([
                'status'        => ContratacionSyncLog::STATUS_FALLIDO,
                'error_mensaje' => substr($e->getMessage(), 0, 1000),
                'finished_at'   => now(),
            ]);
        }
    }

    private function documentRule(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:' . self::DOCUMENT_MAX_KB,
            function (string $attribute, mixed $file, \Closure $fail): void {
                if (!$file instanceof UploadedFile || !$this->isSupportedDocument($file)) {
                    $fail('Solo se permiten archivos JPG, PNG, HEIC/HEIF o PDF.');
                }
            },
        ];
    }

    private function isSupportedDocument(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::HEIC_EXTENSIONS, true)) {
            return true;
        }

        return match ($extension) {
            'jpg', 'jpeg' => in_array($file->getMimeType(), ['image/jpeg', 'image/pjpeg'], true),
            'png'         => in_array($file->getMimeType(), ['image/png', 'image/x-png'], true),
            'pdf'         => in_array($file->getMimeType(), ['application/pdf', 'application/x-pdf'], true),
            default       => false,
        };
    }

    private function tempUploadsByField(string $googleId): array
    {
        $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);
        $byField = [];

        foreach ($uploads as $token => $upload) {
            if (($upload['google_id'] ?? null) !== $googleId) {
                continue;
            }

            $campo = $upload['campo'] ?? '';
            if (!in_array($campo, self::DOCUMENT_FIELDS, true)) {
                continue;
            }

            if (empty($upload['path']) || !Storage::disk('local')->exists($upload['path'])) {
                continue;
            }

            $upload['token'] = $token;
            $previous = $byField[$campo]['uploaded_at'] ?? 0;
            if (($upload['uploaded_at'] ?? 0) >= $previous) {
                $byField[$campo] = $upload;
            }
        }

        return $byField;
    }

    private function validTempUploadsFromRequest(Request $request, string $googleId): array
    {
        $requested = $request->input('uploaded_documents', []);
        if (!is_array($requested)) {
            return [];
        }

        $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);
        $valid = [];

        foreach (self::DOCUMENT_FIELDS as $campo) {
            $token = (string) ($requested[$campo] ?? '');
            if ($token === '' || empty($uploads[$token])) {
                continue;
            }

            $upload = $uploads[$token];
            if (($upload['google_id'] ?? null) !== $googleId || ($upload['campo'] ?? null) !== $campo) {
                continue;
            }

            if (empty($upload['path']) || !Storage::disk('local')->exists($upload['path'])) {
                continue;
            }

            $upload['token'] = $token;
            $valid[$campo] = $upload;
        }

        return $valid;
    }

    private function moveTempDocument(array $upload, string $rutCarpeta): string
    {
        $campo = $upload['campo'];
        $ext = strtolower($upload['extension'] ?? pathinfo((string) $upload['path'], PATHINFO_EXTENSION));
        $filename = $campo . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $ext;
        $dest = "contratacion/{$rutCarpeta}/{$filename}";

        if (empty($upload['path']) || !Storage::disk('local')->exists($upload['path'])) {
            throw ValidationException::withMessages([
                $campo => 'El documento temporal ya no está disponible. Vuelve a seleccionarlo.',
            ]);
        }

        Storage::disk('local')->makeDirectory("contratacion/{$rutCarpeta}");
        $moved = Storage::disk('local')->move($upload['path'], $dest);

        if (!$moved) {
            $copied = Storage::disk('local')->copy($upload['path'], $dest);
            if ($copied) {
                Storage::disk('local')->delete($upload['path']);
            }
            $moved = $copied;
        }

        if (!$moved) {
            throw ValidationException::withMessages([
                $campo => 'No se pudo confirmar el documento subido. Intenta nuevamente.',
            ]);
        }

        $this->forgetTempUploadToken((string) ($upload['token'] ?? ''), false);

        return $dest;
    }

    private function discardTempUploadForField(string $campo, string $googleId, string $token = '', string $exceptToken = ''): bool
    {
        $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);
        $removed = false;

        foreach ($uploads as $currentToken => $upload) {
            if ($exceptToken !== '' && $currentToken === $exceptToken) {
                continue;
            }

            if (($upload['google_id'] ?? null) !== $googleId || ($upload['campo'] ?? null) !== $campo) {
                continue;
            }

            if ($token !== '' && $currentToken !== $token) {
                continue;
            }

            if (!empty($upload['path'])) {
                Storage::disk('local')->delete($upload['path']);
            }
            unset($uploads[$currentToken]);
            $removed = true;
        }

        Session::put(self::TEMP_UPLOAD_SESSION_KEY, $uploads);

        return $removed;
    }

    private function clearTempUploadsForGoogle(string $googleId): void
    {
        $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);

        foreach ($uploads as $token => $upload) {
            if (($upload['google_id'] ?? null) !== $googleId) {
                continue;
            }

            if (!empty($upload['path'])) {
                Storage::disk('local')->delete($upload['path']);
            }
            unset($uploads[$token]);
        }

        Session::put(self::TEMP_UPLOAD_SESSION_KEY, $uploads);
    }

    private function purgeExpiredTempUploads(): void
    {
        $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);
        $now = now()->timestamp;

        foreach ($uploads as $token => $upload) {
            $uploadedAt = (int) ($upload['uploaded_at'] ?? 0);
            $expired = $uploadedAt <= 0 || ($now - $uploadedAt) > self::TEMP_UPLOAD_TTL_SECONDS;
            $missing = empty($upload['path']) || !Storage::disk('local')->exists($upload['path']);

            if ($expired || $missing) {
                if (!empty($upload['path'])) {
                    Storage::disk('local')->delete($upload['path']);
                }
                unset($uploads[$token]);
            }
        }

        Session::put(self::TEMP_UPLOAD_SESSION_KEY, $uploads);
    }

    private function forgetTempUploadToken(string $token, bool $deleteFile = true): void
    {
        if ($token === '') {
            return;
        }

        $uploads = Session::get(self::TEMP_UPLOAD_SESSION_KEY, []);
        if (!isset($uploads[$token])) {
            return;
        }

        if ($deleteFile && !empty($uploads[$token]['path'])) {
            Storage::disk('local')->delete($uploads[$token]['path']);
        }

        unset($uploads[$token]);
        Session::put(self::TEMP_UPLOAD_SESSION_KEY, $uploads);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
    }

    private function consentPayload(Request $request): array
    {
        $acceptedAt = now();

        return [
            'consentimiento_datos'        => true,
            'consentimiento_at'           => $acceptedAt,
            'consentimiento_aceptado_at'  => $acceptedAt,
            'consentimiento_version'      => PrivacyPolicy::VERSION,
            'consentimiento_texto'        => PrivacyPolicy::publicHiringConsentText(),
            'consentimiento_ip'           => $request->ip(),
            'consentimiento_user_agent'   => Str::limit((string) $request->userAgent(), 500, ''),
        ];
    }

    private function storeDocument(Request $request, string $campo, string $rutCarpeta): string
    {
        $preparedDocument = $this->prepareDocumentForStorage($request->file($campo), $campo);

        try {
            $file = $preparedDocument['file'];
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = $campo . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $ext;
            $path = $file->storeAs("contratacion/{$rutCarpeta}", $filename, 'local');

            if (!$path) {
                throw ValidationException::withMessages([
                    $campo => 'No se pudo guardar el documento. Intenta nuevamente.',
                ]);
            }

            return $path;
        } finally {
            $this->cleanupPreparedDocument($preparedDocument);
        }
    }

    /**
     * Converts HEIC/HEIF files before the PDF and SharePoint workflow.
     *
     * @return array{file: UploadedFile, temporary_path: ?string, converted_from_heic: bool}
     */
    private function prepareDocumentForStorage(UploadedFile $file, string $campo): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::HEIC_EXTENSIONS, true)) {
            return [
                'file'                => $file,
                'temporary_path'      => null,
                'converted_from_heic' => false,
            ];
        }

        if (!class_exists(\Imagick::class) || !in_array('HEIC', \Imagick::queryFormats('HEIC'), true)) {
            throw ValidationException::withMessages([
                $campo => 'No fue posible preparar la imagen HEIC. Intenta nuevamente o selecciona una imagen JPG, PNG o PDF.',
            ]);
        }

        $sourcePath = $file->getRealPath();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'saep_heic_');
        if ($sourcePath === false || $temporaryPath === false) {
            throw ValidationException::withMessages([
                $campo => 'No fue posible preparar la imagen HEIC. Intenta nuevamente.',
            ]);
        }

        $jpegPath = $temporaryPath . '.jpg';
        @unlink($temporaryPath);

        try {
            $image = new \Imagick();
            $image->readImage($sourcePath);
            $image->setFirstIterator();

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            }

            $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $image->setImageFormat('jpeg');
            $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality(88);
            $image->stripImage();
            $image->writeImage($jpegPath);
            $image->clear();
            $image->destroy();

            if (!is_file($jpegPath) || filesize($jpegPath) === 0) {
                throw new \RuntimeException('La conversión no generó un archivo JPG.');
            }

            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'documento';

            return [
                'file'                => new UploadedFile($jpegPath, $baseName . '.jpg', 'image/jpeg', null, true),
                'temporary_path'      => $jpegPath,
                'converted_from_heic' => true,
            ];
        } catch (\Throwable $exception) {
            @unlink($jpegPath);
            Log::warning('Contratacion publico: no se pudo convertir HEIC', [
                'campo'   => $campo,
                'archivo' => $file->getClientOriginalName(),
                'error'   => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $campo => 'No fue posible convertir la imagen HEIC. Intenta nuevamente o selecciona una imagen JPG, PNG o PDF.',
            ]);
        }
    }

    private function cleanupPreparedDocument(array $preparedDocument): void
    {
        if (!empty($preparedDocument['temporary_path'])) {
            @unlink($preparedDocument['temporary_path']);
        }
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

    // ─── Notificación RRHH ───────────────────────────────────────
    private function notificarRrhh(PostulanteContratacion $postulante): void
    {
        $destinatarios = Configuracion::get('contratacion_emails_notificacion', '');
        if (empty(trim($destinatarios))) return;

        $emails = array_filter(array_map('trim', explode(',', $destinatarios)));
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($email)->send(new ContratacionNuevoPostulanteMail($postulante));
                } catch (\Throwable $e) {
                    Log::warning('Contratacion: no se pudo notificar RRHH', ['email' => $email, 'folio' => $postulante->folio, 'error' => $e->getMessage()]);
                }
            }
        }
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
