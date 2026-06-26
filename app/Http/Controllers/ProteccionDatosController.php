<?php

namespace App\Http\Controllers;

use App\Models\ConsentimientoDatos;
use App\Models\RegistroTratamientoDatos;
use App\Models\SolicitudArco;
use App\Models\User;
use App\Services\DatosPersonalesSupresionService;
use App\Support\PrivacyPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProteccionDatosController extends Controller
{
    // ─── Política de Privacidad (pública) ───

    public function politicaPrivacidad()
    {
        return view('proteccion-datos.politica-privacidad');
    }

    // ─── Canal ARCO público (visitantes, postulantes y denunciantes sin cuenta) ───

    public function crearSolicitudPublica()
    {
        return view('proteccion-datos.publico-solicitud');
    }

    public function guardarSolicitudPublica(Request $request)
    {
        $request->validate([
            'titular_nombre' => 'required|string|max:255',
            'titular_email' => 'required|email|max:255',
            'titular_rut' => 'nullable|string|max:30',
            'titular_telefono' => 'nullable|string|max:50',
            'titular_contexto' => 'required|in:postulacion,ley_karin,trabajador,proveedor,visitante,otro',
            'tipo' => 'required|in:acceso,rectificacion,supresion,oposicion,portabilidad,bloqueo',
            'descripcion' => 'required|string|max:2000',
            'datos_afectados' => 'nullable|string|max:1000',
            'causal_invocada' => 'required_if:tipo,supresion,oposicion,bloqueo|nullable|string|max:200',
            'antecedentes' => 'nullable|string|max:2000',
            'solicita_bloqueo_temporal' => 'nullable|boolean',
            'acepta_tratamiento' => 'accepted',
        ]);

        $token = SolicitudArco::generarTokenPublico();
        $bloqueoSolicitado = $request->boolean('solicita_bloqueo_temporal') || $request->tipo === 'bloqueo';

        $solicitud = SolicitudArco::create([
            'numero_solicitud' => SolicitudArco::generarNumero(),
            'user_id' => null,
            'canal_origen' => 'publico',
            'titular_nombre' => $request->titular_nombre,
            'titular_email' => strtolower($request->titular_email),
            'titular_rut' => $request->titular_rut,
            'titular_telefono' => $request->titular_telefono,
            'titular_contexto' => $request->titular_contexto,
            'token_hash' => hash('sha256', $token),
            'token_expires_at' => now()->addDays(90),
            'tipo' => $request->tipo,
            'descripcion' => $request->descripcion,
            'datos_afectados' => $request->datos_afectados,
            'causal_invocada' => $request->causal_invocada,
            'antecedentes' => $request->antecedentes,
            'solicita_bloqueo_temporal' => $bloqueoSolicitado,
            'bloqueo_temporal_activo' => $bloqueoSolicitado,
            'bloqueo_temporal_at' => $bloqueoSolicitado ? now() : null,
            'bloqueo_temporal_motivo' => $bloqueoSolicitado ? 'Solicitado por titular externo al crear la solicitud.' : null,
            'estado' => 'pendiente',
            'fecha_solicitud' => now(),
            'fecha_vencimiento' => now()->addDays(30),
            'consentimiento_version' => PrivacyPolicy::VERSION,
            'consentimiento_texto' => PrivacyPolicy::publicArcoConsentText(),
            'consentimiento_aceptado_at' => now(),
            'consentimiento_ip' => $request->ip(),
            'consentimiento_user_agent' => $request->userAgent(),
        ]);

        RegistroTratamientoDatos::registrar(
            'solicitud_arco_publica',
            'solicitudes_arco',
            $solicitud->id,
            'personal',
            "Solicitud ARCO pública {$solicitud->numero_solicitud} creada por canal {$solicitud->titular_contexto}"
        );

        return redirect()->route('proteccion-datos.publico.ver', [
            'numero' => $solicitud->numero_solicitud,
            'token' => $token,
        ])->with('success', 'Solicitud recibida. Guarde este enlace privado para consultar el estado.');
    }

    public function verSolicitudPublica(string $numero, string $token)
    {
        $solicitud = SolicitudArco::where('numero_solicitud', $numero)->firstOrFail();

        if (!$solicitud->validarTokenPublico($token)) {
            abort(403);
        }

        return view('proteccion-datos.publico-ver-solicitud', compact('solicitud', 'token'));
    }

    // ─── Portal ARCO (usuario autenticado) ───

    public function index()
    {
        $user = Auth::user();
        $solicitudes = SolicitudArco::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        $consentimiento = $user->consentimientoDatosVigente()->first();

        return view('proteccion-datos.index', compact('solicitudes', 'consentimiento'));
    }

    public function crearSolicitud()
    {
        return view('proteccion-datos.crear-solicitud');
    }

    public function guardarSolicitud(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:acceso,rectificacion,supresion,oposicion,portabilidad,bloqueo',
            'descripcion' => 'required|string|max:2000',
            'datos_afectados' => 'nullable|string|max:1000',
            'causal_invocada' => 'required_if:tipo,supresion,oposicion,bloqueo|nullable|string|max:200',
            'antecedentes' => 'nullable|string|max:2000',
            'solicita_bloqueo_temporal' => 'nullable|boolean',
        ]);

        $bloqueoSolicitado = $request->boolean('solicita_bloqueo_temporal') || $request->tipo === 'bloqueo';

        $solicitud = SolicitudArco::create([
            'numero_solicitud' => SolicitudArco::generarNumero(),
            'user_id' => Auth::id(),
            'canal_origen' => 'interno',
            'tipo' => $request->tipo,
            'descripcion' => $request->descripcion,
            'datos_afectados' => $request->datos_afectados,
            'causal_invocada' => $request->causal_invocada,
            'antecedentes' => $request->antecedentes,
            'solicita_bloqueo_temporal' => $bloqueoSolicitado,
            'bloqueo_temporal_activo' => $bloqueoSolicitado,
            'bloqueo_temporal_at' => $bloqueoSolicitado ? now() : null,
            'bloqueo_temporal_motivo' => $bloqueoSolicitado ? 'Solicitado por el titular al crear la solicitud.' : null,
            'estado' => 'pendiente',
            'fecha_solicitud' => now(),
            'fecha_vencimiento' => now()->addDays(30),
        ]);

        RegistroTratamientoDatos::registrar(
            'solicitud_arco',
            'solicitudes_arco',
            $solicitud->id,
            'personal',
            "Solicitud ARCO tipo '{$solicitud->nombre_tipo}' creada: {$solicitud->numero_solicitud}"
        );

        return redirect()->route('proteccion-datos.index')
            ->with('success', "Solicitud {$solicitud->numero_solicitud} creada exitosamente. Será procesada en un plazo máximo de 30 días corridos.");
    }

    public function verSolicitud(SolicitudArco $solicitud)
    {
        if ($solicitud->user_id !== Auth::id() && !in_array(Auth::user()->rol->codigo, ['SUPER_ADMIN', 'PREVENCIONISTA'])) {
            abort(403);
        }

        return view('proteccion-datos.ver-solicitud', compact('solicitud'));
    }

    // ─── Exportar datos personales (Portabilidad) ───

    public function exportarDatos()
    {
        $user = Auth::user()->load(['departamento', 'rol', 'cargo', 'centroCosto']);

        $datos = [
            'informacion_personal' => [
                'nombre' => $user->name,
                'apellido_paterno' => $user->apellido_paterno,
                'apellido_materno' => $user->apellido_materno,
                'email' => $user->email,
                'rut' => $user->rut,
                'telefono' => $user->telefono,
                'fecha_nacimiento' => $user->fecha_nacimiento,
                'nacionalidad' => $user->nacionalidad,
                'sexo' => $user->sexo,
                'estado_civil' => $user->estado_civil,
                'fecha_ingreso' => $user->fecha_ingreso,
            ],
            'informacion_laboral' => [
                'departamento' => $user->departamento?->nombre,
                'cargo' => $user->cargo?->nombre,
                'centro_costo' => $user->centroCosto?->nombre,
                'tipo_nomina' => $user->tipo_nomina,
                'razon_social' => $user->razon_social,
                'rol_sistema' => $user->rol?->nombre,
            ],
            'consentimientos' => $user->consentimientos()
                ->select('version_politica', 'fecha_aceptacion', 'fecha_revocacion', 'vigente')
                ->get()->toArray(),
            'solicitudes_arco' => $user->solicitudesArco()
                ->select('numero_solicitud', 'tipo', 'estado', 'fecha_solicitud', 'fecha_respuesta')
                ->get()->toArray(),
            'metadata' => [
                'fecha_exportacion' => now()->toIso8601String(),
                'responsable' => 'SAEP SpA',
                'base_legal' => 'Ley 21.719 - Derecho de portabilidad (Art. 9 bis)',
            ],
        ];

        RegistroTratamientoDatos::registrar(
            'exportacion',
            'users',
            $user->id,
            'personal',
            'Exportación de datos personales solicitada por el titular'
        );

        $filename = 'datos_personales_' . now()->format('Y-m-d_His') . '.json';

        return response()->json($datos, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─── Consentimiento ───

    public function aceptarPolitica(Request $request)
    {
        $user = Auth::user();

        // Invalidar consentimientos anteriores
        ConsentimientoDatos::where('user_id', $user->id)
            ->where('vigente', true)
            ->update(['vigente' => false]);

        ConsentimientoDatos::create([
            'user_id' => $user->id,
            'version_politica' => PrivacyPolicy::VERSION,
            'texto_aceptado' => PrivacyPolicy::internalConsentText(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fecha_aceptacion' => now(),
            'vigente' => true,
        ]);

        $user->update([
            'acepta_politica_datos' => true,
            'fecha_aceptacion_politica' => now(),
        ]);

        RegistroTratamientoDatos::registrar(
            'consentimiento',
            'consentimientos_datos',
            null,
            'personal',
            'Aceptación de política de datos personales v' . PrivacyPolicy::VERSION
        );

        return redirect()->route('dashboard')->with('success', 'Política de datos aceptada correctamente.');
    }

    public function revocarConsentimiento(Request $request)
    {
        $user = Auth::user();

        ConsentimientoDatos::where('user_id', $user->id)
            ->where('vigente', true)
            ->update([
                'vigente' => false,
                'fecha_revocacion' => now(),
            ]);

        $user->update([
            'acepta_politica_datos' => false,
            'fecha_aceptacion_politica' => null,
        ]);

        RegistroTratamientoDatos::registrar(
            'revocacion_consentimiento',
            'consentimientos_datos',
            null,
            'personal',
            'Revocación del consentimiento de tratamiento de datos'
        );

        return redirect()->route('proteccion-datos.index')
            ->with('info', 'Su consentimiento ha sido revocado. Algunos servicios podrían verse limitados.');
    }

    // ─── Administración (SUPER_ADMIN / PREVENCIONISTA) ───

    public function administrar(Request $request)
    {
        $query = SolicitudArco::with('user');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('canal')) {
            $query->where('canal_origen', $request->canal);
        }

        $solicitudes = $query->orderByDesc('created_at')->paginate(15);

        $stats = [
            'pendientes' => SolicitudArco::where('estado', 'pendiente')->count(),
            'en_revision' => SolicitudArco::where('estado', 'en_revision')->count(),
            'vencidas' => SolicitudArco::whereIn('estado', ['pendiente', 'en_revision'])
                ->where('fecha_vencimiento', '<', now())->count(),
            'total_mes' => SolicitudArco::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'publicas' => SolicitudArco::where('canal_origen', 'publico')->count(),
            'bloqueos_activos' => SolicitudArco::where('bloqueo_temporal_activo', true)
                ->whereIn('estado', ['pendiente', 'en_revision', 'aprobada'])->count(),
        ];

        return view('proteccion-datos.administrar', compact('solicitudes', 'stats'));
    }

    public function responderSolicitud(Request $request, SolicitudArco $solicitud)
    {
        $request->validate([
            'estado' => 'required|in:en_revision,aprobada,rechazada,completada',
            'respuesta' => 'required|string|max:2000',
            'motivo_rechazo' => 'required_if:estado,rechazada|nullable|string|max:1000',
        ]);

        if ($solicitud->tipo === 'supresion' && $request->estado === 'completada' && !$solicitud->fecha_ejecucion) {
            return back()->with('error', 'Una solicitud de supresión debe aprobarse y luego ejecutarse desde el flujo autorizado antes de marcarla como completada.');
        }

        $solicitud->update([
            'estado' => $request->estado,
            'respuesta' => $request->respuesta,
            'responsable_id' => Auth::id(),
            'fecha_respuesta' => now(),
            'motivo_rechazo' => $request->motivo_rechazo,
            'bloqueo_temporal_activo' => in_array($request->estado, ['rechazada', 'completada']) ? false : $solicitud->bloqueo_temporal_activo,
        ]);

        RegistroTratamientoDatos::registrar(
            'respuesta_arco',
            'solicitudes_arco',
            $solicitud->id,
            'personal',
            "Solicitud {$solicitud->numero_solicitud} actualizada a estado '{$solicitud->nombre_estado}'"
        );

        return redirect()->route('proteccion-datos.administrar')
            ->with('success', "Solicitud {$solicitud->numero_solicitud} actualizada correctamente.");
    }

    public function ejecutarSupresion(Request $request, SolicitudArco $solicitud, DatosPersonalesSupresionService $service)
    {
        $request->validate([
            'observacion_ejecucion' => 'nullable|string|max:2000',
        ]);

        if ($solicitud->tipo !== 'supresion') {
            abort(404);
        }

        if ($solicitud->estado !== 'aprobada') {
            return back()->with('error', 'La solicitud debe estar aprobada antes de ejecutar la supresión.');
        }

        if ($solicitud->fecha_ejecucion) {
            return back()->with('info', 'La supresión ya fue ejecutada para esta solicitud.');
        }

        $titular = $solicitud->user;
        if ($titular) {
            $resultado = $service->ejecutarParaUsuario($titular, $solicitud, Auth::user());
        } elseif ($solicitud->esPublica()) {
            $resultado = $service->ejecutarParaSolicitudPublica($solicitud, Auth::user());
        } else {
            return back()->with('error', 'No se encontró el titular asociado a la solicitud.');
        }
        $estadoEjecucion = empty($resultado['advertencias']) ? 'completada' : 'completada_con_advertencias';

        $solicitud->update([
            'estado' => 'completada',
            'respuesta' => trim(($solicitud->respuesta ? $solicitud->respuesta . "\n\n" : '') . 'Supresión ejecutada conforme a solicitud aprobada. Revise el resultado de ejecución y las advertencias registradas.'),
            'responsable_id' => Auth::id(),
            'fecha_respuesta' => now(),
            'fecha_ejecucion' => now(),
            'ejecutada_por' => Auth::id(),
            'estado_ejecucion' => $estadoEjecucion,
            'resultado_ejecucion' => $resultado,
            'observacion_ejecucion' => $request->observacion_ejecucion,
            'bloqueo_temporal_activo' => false,
        ]);

        RegistroTratamientoDatos::registrar(
            'supresion_autorizada',
            'solicitudes_arco',
            $solicitud->id,
            'personal',
            "Flujo de supresión autorizado completado para {$solicitud->numero_solicitud}",
            null,
            $resultado
        );

        return redirect()->route('proteccion-datos.ver-solicitud', $solicitud)
            ->with('success', 'Supresión ejecutada y registrada en auditoría.');
    }

    // ─── Registro de tratamiento (auditoría) ───

    public function registroTratamiento(Request $request)
    {
        $query = RegistroTratamientoDatos::with('user');

        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('created_at', '<=', $request->fecha_hasta . ' 23:59:59');
        }

        $registros = $query->orderByDesc('created_at')->paginate(20);

        return view('proteccion-datos.registro-tratamiento', compact('registros'));
    }

    public function matrizRetencion()
    {
        return view('proteccion-datos.matriz-retencion', [
            'matriz' => config('proteccion_datos.retention_matrix', []),
            'encargados' => config('proteccion_datos.external_processors', []),
        ]);
    }
}
