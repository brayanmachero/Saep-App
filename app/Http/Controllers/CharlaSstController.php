<?php

namespace App\Http\Controllers;

use App\Models\Charla;
use App\Models\CharlaAsistente;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CharlaSstController extends Controller
{
    public function index(Request $request)
    {
        $query = Charla::with('creador', 'supervisor')
            ->withCount('asistentes');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('buscar')) {
            $query->where('titulo', 'like', '%' . $request->buscar . '%');
        }

        $charlas = $query->orderBy('fecha_programada', 'desc')->paginate(15)->withQueryString();

        return view('charlas.index', compact('charlas'));
    }

    public function create()
    {
        $supervisores = User::whereHas('rol', fn($q) => $q->whereIn('nombre', ['SUPERVISOR', 'PREVENCIONISTA', 'SUPER_ADMIN']))
            ->where('activo', true)->orderBy('name')->get();
        $trabajadores = User::where('activo', true)->orderBy('name')->get();
        return view('charlas.create', compact('supervisores', 'trabajadores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo'            => ['required', 'string', 'max:300'],
            'tipo'              => ['required', 'in:CHARLA_5MIN,CAPACITACION,INDUCCION,CHARLA_ESPECIAL'],
            'lugar'             => ['nullable', 'string', 'max:300'],
            'fecha_programada'  => ['required', 'date'],
            'duracion_minutos'  => ['required', 'integer', 'min:1'],
            'supervisor_id'     => ['nullable', 'exists:users,id'],
            'contenido'         => ['nullable', 'string'],
            'asistentes'        => ['nullable', 'array'],
            'asistentes.*'      => ['exists:users,id'],
        ]);

        $charla = Charla::create([
            ...$validated,
            'creado_por' => auth()->id(),
            'estado'     => 'PROGRAMADA',
        ]);

        // Agregar asistentes
        foreach ($request->asistentes ?? [] as $userId) {
            CharlaAsistente::create([
                'charla_id'         => $charla->id,
                'usuario_id'        => $userId,
                'fecha_asignacion'  => now(),
            ]);
        }

        return redirect()->route('charlas.show', $charla)
            ->with('success', 'Charla SST creada correctamente.');
    }

    public function show(Charla $charla)
    {
        $charla->load('creador', 'supervisor', 'asistentes.usuario');
        return view('charlas.show', compact('charla'));
    }

    public function edit(Charla $charla)
    {
        $supervisores = User::whereHas('rol', fn($q) => $q->whereIn('nombre', ['SUPERVISOR', 'PREVENCIONISTA', 'SUPER_ADMIN']))
            ->where('activo', true)->orderBy('name')->get();
        $trabajadores = User::where('activo', true)->orderBy('name')->get();
        $asistentesIds = $charla->asistentes->pluck('usuario_id')->toArray();
        return view('charlas.edit', compact('charla', 'supervisores', 'trabajadores', 'asistentesIds'));
    }

    public function update(Request $request, Charla $charla)
    {
        $validated = $request->validate([
            'titulo'            => ['required', 'string', 'max:300'],
            'tipo'              => ['required', 'in:CHARLA_5MIN,CAPACITACION,INDUCCION,CHARLA_ESPECIAL'],
            'lugar'             => ['nullable', 'string', 'max:300'],
            'fecha_programada'  => ['required', 'date'],
            'duracion_minutos'  => ['required', 'integer', 'min:1'],
            'supervisor_id'     => ['nullable', 'exists:users,id'],
            'contenido'         => ['nullable', 'string'],
            'asistentes'        => ['nullable', 'array'],
            'asistentes.*'      => ['exists:users,id'],
        ]);

        $charla->update($validated);

        // Sync asistentes (don't remove existing signatures)
        $nuevos = collect($request->asistentes ?? []);
        $existentes = $charla->asistentes->pluck('usuario_id');

        // Add new
        foreach ($nuevos->diff($existentes) as $userId) {
            CharlaAsistente::create([
                'charla_id'        => $charla->id,
                'usuario_id'       => $userId,
                'fecha_asignacion' => now(),
            ]);
        }
        // Remove only unsigned ones
        CharlaAsistente::where('charla_id', $charla->id)
            ->whereIn('usuario_id', $existentes->diff($nuevos)->toArray())
            ->where('estado', 'PENDIENTE')
            ->delete();

        return redirect()->route('charlas.show', $charla)
            ->with('success', 'Charla actualizada correctamente.');
    }

    public function destroy(Charla $charla)
    {
        if ($charla->estado === 'COMPLETADA') {
            return back()->with('error', 'No se puede eliminar una charla completada.');
        }
        $charla->delete();
        return redirect()->route('charlas.index')->with('success', 'Charla eliminada.');
    }

    /**
     * Show the signature form for a worker to sign.
     */
    public function firmar(Charla $charla, CharlaAsistente $asistente)
    {
        if ($asistente->charla_id !== $charla->id) {
            abort(403);
        }
        if ($asistente->estado === 'FIRMADO') {
            return back()->with('error', 'Esta asistencia ya fue firmada.');
        }
        return view('charlas.firmar', compact('charla', 'asistente'));
    }

    /**
     * Store the digital signature.
     */
    public function guardarFirma(Request $request, Charla $charla, CharlaAsistente $asistente)
    {
        $request->validate([
            'firma_imagen' => ['required', 'string'],
        ]);

        // Accept only the authenticated user's own record
        if ($asistente->usuario_id !== auth()->id() && !in_array(auth()->user()->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA'])) {
            abort(403);
        }

        $asistente->update([
            'estado'         => 'FIRMADO',
            'firma_imagen'   => $request->firma_imagen,
            'fecha_firma'    => now(),
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'documento_hash' => hash('sha256', $charla->titulo . $asistente->usuario_id . now()),
        ]);

        // Mark charla as COMPLETADA if all signed
        if ($charla->asistentes()->where('estado', 'PENDIENTE')->count() === 0) {
            $charla->update(['estado' => 'COMPLETADA', 'fecha_dictado' => now()]);
        }

        return redirect()->route('charlas.show', $charla)
            ->with('success', 'Firma registrada correctamente.');
    }

    /**
     * Admin: change charla estado.
     */
    public function cambiarEstado(Request $request, Charla $charla)
    {
        $request->validate(['estado' => ['required', 'in:BORRADOR,PROGRAMADA,EN_CURSO,COMPLETADA,CANCELADA']]);
        $charla->update(['estado' => $request->estado]);
        return back()->with('success', 'Estado actualizado.');
    }
}
