<?php

namespace App\Http\Controllers;

use App\Mail\ContratacionAcuseReciboMail;
use App\Mail\ContratacionNuevoPostulanteMail;
use App\Models\Configuracion;
use App\Models\PostulanteContratacion;
use App\Services\OneDriveService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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

        $request->validate([
            'nombre' => 'required|string|max:200',
            'rut'    => ['required', 'string', 'max:20', function ($attr, $val, $fail) {
                if (!PostulanteContratacion::validarRut($val)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'carnet_frontal'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'carnet_reverso'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'certificado_afp'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'certificado_fonasa' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'licencia_conducir'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $rutLimpio = preg_replace('/[^0-9kK]/', '', strtoupper($request->rut));
        $rutFormateado = PostulanteContratacion::formatearRut($rutLimpio);
        $rutCarpeta    = strtolower(preg_replace('/\./', '', $rutLimpio));

        // Buscar postulación existente de este Google user
        $postulante = PostulanteContratacion::where('google_id', $googleUser['id'])->first();
        $esNuevo    = !$postulante;

        $datos = [
            'nombre'      => $request->nombre,
            'rut'         => $rutFormateado,
            'email'       => $googleUser['email'],
            'google_id'   => $googleUser['id'],
            'google_name' => $googleUser['name'],
            'google_avatar'=> $googleUser['avatar'],
        ];

        // Subir documentos que lleguen en este request
        $camposDocs = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir'];
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

        // Enviar emails solo en la primera postulación
        if ($esNuevo) {
            // Acuse al postulante
            try {
                Mail::to($postulante->email)->send(new ContratacionAcuseReciboMail($postulante));
            } catch (\Exception) {}

            // Notificación a destinatarios configurados
            $this->notificarRrhh($postulante);
        }

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

        // 1. Subir cada documento original desde Azure Blob
        $camposDocs = ['carnet_frontal', 'carnet_reverso', 'certificado_afp', 'certificado_fonasa', 'licencia_conducir'];
        foreach ($camposDocs as $campo) {
            if (empty($postulante->$campo)) continue;
            if (!Storage::disk('public')->exists($postulante->$campo)) continue;

            $ext     = strtolower(pathinfo($postulante->$campo, PATHINFO_EXTENSION));
            $mime    = match($ext) {
                'png'          => 'image/png',
                'gif'          => 'image/gif',
                'webp'         => 'image/webp',
                'jpg', 'jpeg'  => 'image/jpeg',
                default        => 'application/pdf',
            };
            $content = Storage::disk('public')->get($postulante->$campo);
            $oneDrive->uploadFileToSite($site, $content, "{$folder}/{$carpeta}/{$campo}.{$ext}", $mime);
        }

        // 2. Generar PDF consolidado y subirlo
        try {
            Log::info('SharePoint contratacion: construyendo array documentos', ['folio' => $postulante->folio]);

            $documentos   = [];
            $camposLabels = [
                'carnet_frontal'     => 'Carnet de Identidad (Frontal)',
                'carnet_reverso'     => 'Carnet de Identidad (Reverso)',
                'certificado_afp'    => 'Certificado AFP',
                'certificado_fonasa' => 'Certificado FONASA',
                'licencia_conducir'  => 'Licencia de Conducir',
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
                    // Para PDFs: construir URL pública directamente (Storage::url puede fallar en Azure)
                    $blobUrl = rtrim(config('filesystems.disks.public.url', env('AZURE_STORAGE_URL', '')), '/')
                        . '/' . ltrim($ruta, '/');
                    $documentos[] = ['label' => $label, 'tipo' => 'pdf', 'data' => $blobUrl, 'ext' => $ext];
                }
            }

            Log::info('SharePoint contratacion: generando PDF ficha', [
                'folio'  => $postulante->folio,
                'docs'   => count($documentos),
                'tipos'  => array_column($documentos, 'tipo'),
            ]);

            // Asegurar directorio temporal escribible para DomPDF
            $tmpDir = sys_get_temp_dir();
            $fontDir = storage_path('fonts');
            if (!is_dir($fontDir)) {
                @mkdir($fontDir, 0755, true);
            }

            // Aumentar límite de memoria para DomPDF
            $memPrev = ini_get('memory_limit');
            ini_set('memory_limit', '256M');

            $pdfContent = Pdf::loadView('pdf.contratacion_ficha', compact('postulante', 'documentos'))
                ->setPaper('a4', 'portrait')
                ->setOption('isRemoteEnabled', false)
                ->setOption('tempDir', $tmpDir)
                ->setOption('fontDir', $fontDir)
                ->setOption('fontCache', $tmpDir)
                ->output();

            ini_set('memory_limit', $memPrev);

            Log::info('SharePoint contratacion: PDF generado, subiendo a SharePoint', [
                'folio' => $postulante->folio,
                'size'  => strlen($pdfContent),
            ]);

            $oneDrive->uploadFileToSite(
                $site,
                $pdfContent,
                "{$folder}/{$carpeta}/{$postulante->folio}_ficha.pdf"
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
                } catch (\Exception) {}
            }
        }
    }
}
