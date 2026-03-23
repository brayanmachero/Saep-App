<?php

namespace App\Http\Controllers;

use App\Mail\RespuestaAprobadaMail;
use App\Mail\RespuestaCreadaMail;
use App\Models\Formulario;
use App\Models\Respuesta;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RespuestaController extends Controller
{
    public function index(Request $request)
    {
        $query = Respuesta::with(['formulario', 'usuario.departamento']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('formulario_id')) {
            $query->where('formulario_id', $request->formulario_id);
        }

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->whereHas('usuario', fn($u) => $u->where('name', 'like', "%$q%"));
        }

        $respuestas = $query->latest()->paginate(15)->withQueryString();
        $formularios = Formulario::where('activo', true)->get();

        return view('respuestas.index', compact('respuestas', 'formularios'));
    }

    public function create(Request $request)
    {
        $formularios = Formulario::where('activo', true)->get();
        $formulario = $request->formulario_id
            ? Formulario::findOrFail($request->formulario_id)
            : null;

        return view('respuestas.create', compact('formularios', 'formulario'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'formulario_id' => ['required', 'exists:formularios,id'],
            'datos_json'    => ['required', 'json'],
            'estado'        => ['in:Borrador,Pendiente'],
        ]);

        $formulario = Formulario::findOrFail($request->formulario_id);

        $respuesta = Respuesta::create([
            'formulario_id'   => $formulario->id,
            'version_form'    => $formulario->version,
            'usuario_id'      => auth()->id(),
            'departamento_id' => auth()->user()->departamento_id,
            'estado'          => $request->input('estado', $formulario->requiere_aprobacion ? 'Pendiente' : 'Aprobado'),
            'datos_json'      => $request->datos_json,
        ]);

        // Notify approvers when submitted (not draft)
        if ($respuesta->estado === 'Pendiente' && $formulario->requiere_aprobacion && $formulario->aprobador_rol_id) {
            $aprobadores = User::where('rol_id', $formulario->aprobador_rol_id)->where('activo', true)->get();
            foreach ($aprobadores as $ap) {
                Mail::to($ap->email)->send(new RespuestaCreadaMail($respuesta));
            }
        }

        return redirect()->route('respuestas.show', $respuesta)
            ->with('success', 'Solicitud enviada correctamente.');
    }

    public function show(Respuesta $respuesta)
    {
        $respuesta->load(['formulario', 'usuario.departamento', 'aprobaciones.aprobador']);
        $schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);
        $datos  = json_decode($respuesta->datos_json ?? '{}', true);

        return view('respuestas.show', compact('respuesta', 'schema', 'datos'));
    }

    public function edit(Respuesta $respuesta)
    {
        abort_if($respuesta->estado !== 'Borrador', 403, 'Solo puedes editar borradores.');

        $schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);
        $datos  = json_decode($respuesta->datos_json ?? '{}', true);

        return view('respuestas.edit', compact('respuesta', 'schema', 'datos'));
    }

    public function update(Request $request, Respuesta $respuesta)
    {
        abort_if($respuesta->estado !== 'Borrador', 403);

        $request->validate([
            'datos_json' => ['required', 'json'],
            'estado'     => ['in:Borrador,Pendiente'],
        ]);

        $respuesta->update([
            'datos_json' => $request->datos_json,
            'estado'     => $request->input('estado', 'Pendiente'),
        ]);

        return redirect()->route('respuestas.show', $respuesta)
            ->with('success', 'Solicitud actualizada.');
    }

    public function destroy(Respuesta $respuesta)
    {
        abort_if($respuesta->estado !== 'Borrador', 403, 'Solo puedes eliminar borradores.');
        $respuesta->delete();
        return redirect()->route('respuestas.index')
            ->with('success', 'Solicitud eliminada.');
    }

    public function cambiarEstado(Request $request, Respuesta $respuesta)
    {
        $request->validate([
            'estado'     => ['required', 'in:Aprobado,Rechazado,Revisión,Pendiente'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ]);

        $respuesta->update([
            'estado'            => $request->estado,
            'fecha_resolucion'  => in_array($request->estado, ['Aprobado', 'Rechazado'])
                ? now() : $respuesta->fecha_resolucion,
        ]);

        // Registro de aprobación
        $respuesta->aprobaciones()->create([
            'aprobador_id' => auth()->id(),
            'accion'       => $request->estado,
            'comentario'   => $request->comentario,
            'fecha'        => now(),
        ]);

        // Notify requester when approved or rejected
        if (in_array($request->estado, ['Aprobado', 'Rechazado']) && $respuesta->usuario?->email) {
            Mail::to($respuesta->usuario->email)->send(new RespuestaAprobadaMail($respuesta->fresh(['formulario', 'aprobaciones.aprobador'])));
        }

        return back()->with('success', "Solicitud marcada como {$request->estado}.");
    }
}
