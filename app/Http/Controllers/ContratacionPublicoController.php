<?php

namespace App\Http\Controllers;

use App\Mail\ContratacionAcuseReciboMail;
use App\Mail\ContratacionNuevoPostulanteMail;
use App\Models\Configuracion;
use App\Models\MailLog;
use App\Models\PostulanteContratacion;
use App\Models\RegistroTratamientoDatos;
use App\Services\OneDriveService;
use App\Support\PrivacyPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class ContratacionPublicoController extends Controller
{
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
                ->stateless()
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

        return view('contratacion.publico.formulario', compact('googleUser', 'postulante'));
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

        // Documentos obligatorios: required si no existe registro previo O si aún no han sido subidos
        $requeridosObligatorios = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa'];
        $docRules = [];
        foreach ($requeridosObligatorios as $campo) {
            $yaTiene = $postulante && !empty($postulante->$campo);
            $docRules[$campo] = ($yaTiene ? 'nullable' : 'required') . '|file|mimes:jpg,jpeg,png,pdf|max:5120';
        }

        // Licencia: opcional, pero si se tiene/sube uno de los dos el otro se vuelve obligatorio
        $tendraLicF = ($postulante && !empty($postulante->licencia_conducir_frontal)) || $request->hasFile('licencia_conducir_frontal');
        $tendraLicR = ($postulante && !empty($postulante->licencia_conducir_reverso)) || $request->hasFile('licencia_conducir_reverso');
        $docRules['licencia_conducir_frontal'] = ($tendraLicR && !$tendraLicF ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png,pdf|max:5120';
        $docRules['licencia_conducir_reverso'] = ($tendraLicF && !$tendraLicR ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png,pdf|max:5120';

        $request->validate(array_merge([
            'nombre' => 'required|string|max:200',
            'rut'    => ['required', 'string', 'max:20', function ($attr, $val, $fail) {
                if (!PostulanteContratacion::validarRut($val)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'consentimiento_datos' => 'accepted',
        ], $docRules), [
            'carnet_frontal.required'              => 'El Carnet de Identidad (Frontal) es obligatorio.',
            'carnet_reverso.required'              => 'El Carnet de Identidad (Reverso) es obligatorio.',
            'certificado_afp.required'             => 'El Certificado AFP es obligatorio.',
            'certificado_fonasa.required'          => 'El Certificado FONASA es obligatorio.',
            'licencia_conducir_frontal.required'   => 'Debes subir el frontal de la Licencia de Conducir (ya tienes el reverso).',
            'licencia_conducir_reverso.required'   => 'Debes subir el reverso de la Licencia de Conducir (ya tienes el frontal).',
            'consentimiento_datos.accepted'        => 'Debes aceptar el tratamiento de datos personales para enviar la postulación.',
            '*.mimes'                              => 'Solo se permiten archivos JPG, PNG o PDF.',
            '*.max'                                => 'El archivo no puede superar los 5 MB.',
        ]);

        $rutLimpio = preg_replace('/[^0-9kK]/', '', strtoupper($request->rut));
        $rutFormateado = PostulanteContratacion::formatearRut($rutLimpio);
        $rutCarpeta    = strtolower(preg_replace('/\./', '', $rutLimpio));

        $datos = [
            'nombre'      => $request->nombre,
            'rut'         => $rutFormateado,
            'email'       => $googleUser['email'],
            'google_id'   => $googleUser['id'],
            'google_name' => $googleUser['name'],
            'google_avatar'=> $googleUser['avatar'],
            'consentimiento_datos' => true,
            'consentimiento_version' => PrivacyPolicy::VERSION,
            'consentimiento_texto' => PrivacyPolicy::publicHiringConsentText(),
            'consentimiento_aceptado_at' => now(),
            'consentimiento_ip' => $request->ip(),
            'consentimiento_user_agent' => $request->userAgent(),
        ];

        // Subir documentos que lleguen en este request
        $camposDocs = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir_frontal', 'licencia_conducir_reverso'];
        foreach ($camposDocs as $campo) {
            if ($request->hasFile($campo)) {
                // Borrar el anterior si existe
                if ($postulante && $postulante->$campo) {
                    Storage::disk('public')->delete($postulante->$campo);
                }
                $ext  = $request->file($campo)->getClientOriginalExtension();
                $path = $request->file($campo)->storeAs(
                    "contratacion/{$rutCarpeta}",
                    "{$campo}.{$ext}",
                    'public'
                );
                $datos[$campo] = $path;
            }
        }

        if ($esNuevo) {
            $postulante = PostulanteContratacion::create($datos);
        } else {
            $postulante->update($datos);
        }

        RegistroTratamientoDatos::registrar(
            $esNuevo ? 'postulacion_publica_creada' : 'postulacion_publica_actualizada',
            'postulantes_contratacion',
            $postulante->id,
            'personal',
            "Postulación pública {$postulante->folio} recibida con consentimiento v" . PrivacyPolicy::VERSION
        );

        // Enviar emails solo en la primera postulación
        if ($esNuevo) {
            // Acuse al postulante
            try {
                Mail::to($postulante->email)->send(new ContratacionAcuseReciboMail($postulante));
            } catch (\Throwable $e) {
                MailLog::recordFailed($postulante->email, 'Acuse recibo postulación - Folio ' . $postulante->folio, $e->getMessage(), 'ContratacionAcuseReciboMail');
                Log::error('Error enviando ContratacionAcuseReciboMail', ['email' => $postulante->email, 'folio' => $postulante->folio, 'error' => $e->getMessage()]);
            }

            // Notificación a destinatarios configurados
            $this->notificarRrhh($postulante);
        }

        // Refrescar desde BD para garantizar que subirASharePoint tenga TODOS los documentos
        // (incluyendo los que ya existían y no se actualizaron en este request)
        $postulante->refresh();

        // Subir documentos + ficha PDF a SharePoint (no crítico)
        try {
            $this->subirASharePoint($postulante);
        } catch (\Throwable $e) {
            Log::warning('SharePoint contratacion upload falló (no crítico): ' . $e->getMessage());
        }

        return redirect()->route('contratacion-publico.confirmacion', $postulante->folio);
    }

    // ─── Paso 6: Confirmación ────────────────────────────────────
    public function confirmacion(string $folio)
    {
        $postulante = PostulanteContratacion::where('folio', $folio)->firstOrFail();
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
    private function subirASharePoint(PostulanteContratacion $postulante): void
    {
        $graphConfig = config('services.microsoft_graph');
        $site        = $graphConfig['contratacion_site']   ?? 'RRH';
        $folder      = $graphConfig['contratacion_folder'] ?? 'Postulantes Documents';

        $oneDrive = app(OneDriveService::class);
        if (!$oneDrive->isConfigured()) {
            return;
        }

        // Carpeta del postulante: "{RUT} - {Nombre}"
        $carpeta = $postulante->rut . ' - ' . $postulante->nombre;

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
            ini_set('memory_limit', '384M');

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
                    $pdfBytes = Storage::disk('public')->get($docInfo['ruta']);
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
                    $cmd = sprintf(
                        '%s -dBATCH -dNOPAUSE -dNOSAFER -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s 2>&1',
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
                                    '%s -dBATCH -dNOPAUSE -dNOSAFER -sDEVICE=png16m -r150 -sOutputFile=%s %s 2>&1',
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

            $seq          = (int) substr(strrchr($postulante->folio, '-'), 1);
            $fichaNum     = str_pad($seq, 3, '0', STR_PAD_LEFT);
            $fichaFilename = $postulante->rut . ' - FICHA ' . $fichaNum . ' - ' . $postulante->nombre . '.pdf';

            $oneDrive->uploadFileToSite(
                $site,
                $fichaBytes,
                "{$folder}/{$carpeta}/{$fichaFilename}"
            );

            Log::info('SharePoint contratacion: ficha PDF subida exitosamente', ['folio' => $postulante->folio]);
        } catch (\Throwable $e) {
            Log::error('SharePoint contratacion: fallo en generacion/subida de ficha PDF', [
                'folio'   => $postulante->folio,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
                'trace'   => substr($e->getTraceAsString(), 0, 2000),
            ]);
        }
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
                    MailLog::recordFailed($email, 'Nuevo postulante - Folio ' . $postulante->folio, $e->getMessage(), 'ContratacionNuevoPostulanteMail');
                    Log::error('Error enviando ContratacionNuevoPostulanteMail', ['email' => $email, 'folio' => $postulante->folio, 'error' => $e->getMessage()]);
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
