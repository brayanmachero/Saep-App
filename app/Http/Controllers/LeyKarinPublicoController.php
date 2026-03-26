<?php

namespace App\Http\Controllers;

use App\Mail\LeyKarinAcuseReciboMail;
use App\Mail\LeyKarinDenunciaMail;
use App\Models\ArchivoAdjunto;
use App\Models\CentroCosto;
use App\Models\LeyKarin;
use App\Models\LeyKarinLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;

class LeyKarinPublicoController extends Controller
{
    /**
     * Paso 1: Mostrar landing con botón "Iniciar sesión con Google"
     */
    public function inicio()
    {
        // Si ya tiene sesión de Google, ir directo al formulario
        if (Session::has('google_user')) {
            return redirect()->route('ley-karin-publico.formulario');
        }

        return view('ley_karin_publico.inicio');
    }

    /**
     * Paso 2: Redirigir a Google OAuth
     */
    public function redirectGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    /**
     * Paso 3: Callback de Google — guardar datos en sesión
     */
    public function callbackGoogle()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            Session::put('google_user', [
                'email'  => $googleUser->getEmail(),
                'name'   => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            return redirect()->route('ley-karin-publico.formulario');
        } catch (\Exception $e) {
            return redirect()->route('ley-karin-publico.inicio')
                ->with('error', 'No se pudo verificar tu cuenta de Google. Intenta nuevamente.');
        }
    }

    /**
     * Paso 4: Mostrar formulario de denuncia (requiere sesión Google)
     */
    public function formulario()
    {
        $googleUser = Session::get('google_user');

        if (!$googleUser) {
            return redirect()->route('ley-karin-publico.inicio')
                ->with('error', 'Debes iniciar sesión con Google para acceder al formulario.');
        }

        $centros = CentroCosto::where('activo', true)->orderBy('nombre')->get();

        return view('ley_karin_publico.formulario', compact('googleUser', 'centros'));
    }

    /**
     * Paso 5: Procesar y guardar la denuncia
     */
    public function store(Request $request)
    {
        $googleUser = Session::get('google_user');

        if (!$googleUser) {
            return redirect()->route('ley-karin-publico.inicio')
                ->with('error', 'Sesión expirada. Inicia sesión con Google nuevamente.');
        }

        $esAnonima = $request->boolean('anonima');

        $rules = [
            'tipo'               => 'required|string|in:' . implode(',', array_keys(LeyKarin::tiposMap())),
            'descripcion_hechos' => 'required|string|min:20|max:10000',
            'centro_costo_id'    => 'required|exists:centros_costo,id',
            'denunciado_nombre'  => 'nullable|string|max:200',
            'denunciado_cargo'   => 'nullable|string|max:200',
            'metodo_contacto'    => 'nullable|string|in:EMAIL,TELEFONO,NO_CONTACTAR',
            'latitud'            => 'nullable|numeric|between:-90,90',
            'longitud'           => 'nullable|numeric|between:-180,180',
            'consentimiento_datos' => 'accepted',
            'consentimiento_geolocalizacion' => 'nullable|boolean',
            'evidencias'         => 'nullable|array|max:5',
            'evidencias.*'       => 'file|max:10240|mimes:pdf,jpg,jpeg,png,gif,mp3,mp4,wav,doc,docx',
            'anonima'            => 'nullable|boolean',
        ];

        // Si NO es anónima, nombre es obligatorio
        if (!$esAnonima) {
            $rules['denunciante_nombre'] = 'required|string|max:200';
            $rules['denunciante_rut']    = 'nullable|string|max:20';
        }

        $data = $request->validate($rules);

        $caso = LeyKarin::create([
            'tipo'                => $data['tipo'],
            'descripcion_hechos'  => $data['descripcion_hechos'],
            'centro_costo_id'     => $data['centro_costo_id'],
            'denunciante_nombre'  => $esAnonima ? null : ($data['denunciante_nombre'] ?? null),
            'denunciante_rut'     => $esAnonima ? null : ($data['denunciante_rut'] ?? null),
            'denunciante_email'   => $googleUser['email'],  // SIEMPRE se guarda el email (anti-fraude)
            'denunciado_nombre'   => $data['denunciado_nombre'] ?? null,
            'denunciado_cargo'    => $data['denunciado_cargo'] ?? null,
            'denunciante_latitud' => $data['latitud'] ?? null,
            'denunciante_longitud' => $data['longitud'] ?? null,
            'metodo_contacto'     => $data['metodo_contacto'] ?? 'EMAIL',
            'fecha_denuncia'      => now()->toDateString(),
            'canal'               => 'FORMULARIO_WEB',
            'confidencial'        => true,
            'anonima'             => $esAnonima,
            'consentimiento_datos' => true,
            'consentimiento_geolocalizacion' => $request->boolean('consentimiento_geolocalizacion'),
        ]);

        // Subir evidencias
        if ($request->hasFile('evidencias')) {
            foreach ($request->file('evidencias') as $archivo) {
                $nombre = time() . '_' . $archivo->getClientOriginalName();
                $ruta = $archivo->storeAs('ley_karin/' . $caso->id, $nombre, 'public');

                ArchivoAdjunto::create([
                    'entidad_tipo'     => 'ley_karin',
                    'entidad_id'       => $caso->id,
                    'nombre_original'  => $archivo->getClientOriginalName(),
                    'nombre_archivo'   => $nombre,
                    'ruta'             => $ruta,
                    'mime_type'        => $archivo->getMimeType(),
                    'tamanio'          => $archivo->getSize(),
                    'campo_formulario' => 'evidencias',
                ]);
            }
        }

        // Audit log
        LeyKarinLog::registrar($caso->id, 'CREADA', 'Denuncia creada vía formulario web público' . ($esAnonima ? ' (anónima)' : ''), null);

        // Notificaciones
        $caso->load('centroCosto');
        $this->notificarAdmins($caso);

        // Acuse de recibo al denunciante
        try {
            Mail::to($googleUser['email'])->send(new LeyKarinAcuseReciboMail($caso));
        } catch (\Exception $e) {
            // No bloquear el flujo si falla el email
        }

        // Limpiar sesión de Google después de enviar
        Session::forget('google_user');

        return redirect()->route('ley-karin-publico.confirmacion', $caso->folio);
    }

    /**
     * Paso 6: Confirmación con folio
     */
    public function confirmacion(string $folio)
    {
        $caso = LeyKarin::where('folio', $folio)->firstOrFail();

        return view('ley_karin_publico.confirmacion', compact('caso'));
    }

    /**
     * Cerrar sesión de Google en el formulario público
     */
    public function logout()
    {
        Session::forget('google_user');
        return redirect()->route('ley-karin-publico.inicio');
    }

    private function notificarAdmins(LeyKarin $caso): void
    {
        $destinatarios = User::whereHas('rol', fn ($q) => $q->whereIn('nombre', ['SUPER_ADMIN', 'PREVENCIONISTA']))
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($destinatarios as $email) {
            Mail::to($email)->send(new LeyKarinDenunciaMail($caso));
        }
    }
}
