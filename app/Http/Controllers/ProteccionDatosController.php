<?php

namespace App\Http\Controllers;

use App\Models\ConsentimientoDatos;
use App\Models\RegistroTratamientoDatos;
use App\Models\SolicitudArco;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProteccionDatosController extends Controller
{
    // ─── Política de Privacidad (pública) ───

    public function politicaPrivacidad()
    {
        return view('proteccion-datos.politica-privacidad');
    }

    // ─── Portal ARCO (usuario autenticado) ───

    public function index()
    {
        $user = Auth::user();
        $solicitudes = SolicitudArco::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        $consentimiento = ConsentimientoDatos::where('user_id', $user->id)
            ->where('vigente', true)
            ->latest()
            ->first();

        return view('proteccion-datos.index', compact('solicitudes', 'consentimiento'));
    }

    public function crearSolicitud()
    {
        return view('proteccion-datos.crear-solicitud');
    }

    public function guardarSolicitud(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:acceso,rectificacion,supresion,oposicion,portabilidad',
            'descripcion' => 'required|string|max:2000',
            'datos_afectados' => 'nullable|string|max:1000',
        ]);

        $solicitud = SolicitudArco::create([
            'numero_solicitud' => SolicitudArco::generarNumero(),
            'user_id' => Auth::id(),
            'tipo' => $request->tipo,
            'descripcion' => $request->descripcion,
            'datos_afectados' => $request->datos_afectados,
            'estado' => 'pendiente',
            'fecha_solicitud' => now(),
            'fecha_vencimiento' => now()->addWeekdays(30),
        ]);

        RegistroTratamientoDatos::registrar(
            'solicitud_arco',
            'solicitudes_arco',
            $solicitud->id,
            'personal',
            "Solicitud ARCO tipo '{$solicitud->nombre_tipo}' creada: {$solicitud->numero_solicitud}"
        );

        return redirect()->route('proteccion-datos.index')
            ->with('success', "Solicitud {$solicitud->numero_solicitud} creada exitosamente. Será procesada en un plazo máximo de 30 días hábiles.");
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
            'version_politica' => '1.0',
            'texto_aceptado' => 'Acepto la política de tratamiento de datos personales de SAEP conforme a la Ley 21.719.',
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
            'Aceptación de política de datos personales v1.0'
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

        $solicitudes = $query->orderByDesc('created_at')->paginate(15);

        $stats = [
            'pendientes' => SolicitudArco::where('estado', 'pendiente')->count(),
            'en_revision' => SolicitudArco::where('estado', 'en_revision')->count(),
            'vencidas' => SolicitudArco::where('estado', 'pendiente')
                ->where('fecha_vencimiento', '<', now())->count(),
            'total_mes' => SolicitudArco::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
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

        $solicitud->update([
            'estado' => $request->estado,
            'respuesta' => $request->respuesta,
            'responsable_id' => Auth::id(),
            'fecha_respuesta' => now(),
            'motivo_rechazo' => $request->motivo_rechazo,
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
}
