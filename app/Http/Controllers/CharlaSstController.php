<?php

namespace App\Http\Controllers;

use App\Models\Charla;
use App\Models\CharlaAsistente;
use App\Models\CharlaRelator;
use App\Models\CentroCosto;
use App\Models\User;
use Illuminate\Http\Request;

class CharlaSstController extends Controller
{
    public function index(Request $request)
    {
        $query = Charla::with('creador', 'supervisor', 'centroCosto')
            ->withCount([
                'asistentes',
                'asistentes as firmados_count' => fn($q) => $q->where('estado', 'FIRMADO'),
            ]);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('buscar')) {
            $query->where('titulo', 'like', '%' . $request->buscar . '%');
        }

        $charlas = $query->orderBy('fecha_programada', 'desc')->paginate(20)->withQueryString();

        $stats = [
            'total'       => Charla::count(),
            'programadas' => Charla::where('estado', 'PROGRAMADA')->count(),
            'en_curso'    => Charla::where('estado', 'EN_CURSO')->count(),
            'completadas' => Charla::where('estado', 'COMPLETADA')->count(),
            'asistentes'  => CharlaAsistente::count(),
            'firmados'    => CharlaAsistente::where('estado', 'FIRMADO')->count(),
        ];

        return view('charlas.index', compact('charlas', 'stats'));
    }

    public function create()
    {
        $centros      = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios     = User::where('activo', true)->orderBy('name')->get();
        $supervisores = $usuarios->filter(
            fn($u) => in_array($u->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA', 'SUPERVISOR', 'ADMIN'])
        )->values();
        return view('charlas.create', compact('centros', 'usuarios', 'supervisores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo'                  => ['required', 'string', 'max:300'],
            'tipo'                    => ['required', 'in:CHARLA_5MIN,CAPACITACION,INDUCCION,CHARLA_ESPECIAL'],
            'lugar'                   => ['nullable', 'string', 'max:300'],
            'fecha_programada'        => ['required', 'date'],
            'duracion_minutos'        => ['required', 'integer', 'min:1', 'max:480'],
            'supervisor_id'           => ['nullable', 'exists:users,id'],
            'centro_costo_id'         => ['nullable', 'exists:centros_costo,id'],
            'contenido'               => ['nullable', 'string'],
            'asistentes'              => ['nullable', 'array'],
            'asistentes.*'            => ['exists:users,id'],
            'relatores'               => ['nullable', 'array'],
            'relatores.*.usuario_id'  => ['required', 'exists:users,id'],
            'relatores.*.rol'         => ['required', 'in:RELATOR,SUPERVISOR_CPHS,INSTRUCTOR'],
        ]);

        $charla = Charla::create([
            'titulo'           => $request->titulo,
            'contenido'        => $request->contenido,
            'tipo'             => $request->tipo,
            'lugar'            => $request->lugar,
            'fecha_programada' => $request->fecha_programada,
            'duracion_minutos' => $request->duracion_minutos,
            'supervisor_id'    => $request->supervisor_id ?: null,
            'centro_costo_id'  => $request->centro_costo_id ?: null,
            'creado_por'       => auth()->id(),
            'estado'           => 'PROGRAMADA',
            'activo'           => true,
        ]);

        foreach ($request->input('relatores', []) as $rel) {
            CharlaRelator::create([
                'charla_id'   => $charla->id,
                'usuario_id'  => $rel['usuario_id'],
                'rol_relator' => $rel['rol'],
            ]);
        }

        foreach ($request->input('asistentes', []) as $userId) {
            CharlaAsistente::create([
                'charla_id'        => $charla->id,
                'usuario_id'       => $userId,
                'fecha_asignacion' => now(),
            ]);
        }

        return redirect()->route('charlas.show', $charla)
            ->with('success', 'Charla creada correctamente.');
    }

    public function show(Charla $charla)
    {
        $charla->load([
            'creador', 'supervisor', 'centroCosto',
            'relatores.usuario',
            'asistentes.usuario.rol',
        ]);
        return view('charlas.show', compact('charla'));
    }

    public function edit(Charla $charla)
    {
        $charla->load('relatores', 'asistentes');
        $centros      = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios     = User::where('activo', true)->orderBy('name')->get();
        $supervisores = $usuarios->filter(
            fn($u) => in_array($u->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA', 'SUPERVISOR', 'ADMIN'])
        )->values();
        $asistentesIds = $charla->asistentes->pluck('usuario_id')->toArray();
        return view('charlas.edit', compact('charla', 'centros', 'usuarios', 'supervisores', 'asistentesIds'));
    }

    public function update(Request $request, Charla $charla)
    {
        if (in_array($charla->estado, ['COMPLETADA', 'CANCELADA'])) {
            return back()->with('error', 'No se puede editar una charla completada o cancelada.');
        }

        $request->validate([
            'titulo'                  => ['required', 'string', 'max:300'],
            'tipo'                    => ['required', 'in:CHARLA_5MIN,CAPACITACION,INDUCCION,CHARLA_ESPECIAL'],
            'lugar'                   => ['nullable', 'string', 'max:300'],
            'fecha_programada'        => ['required', 'date'],
            'duracion_minutos'        => ['required', 'integer', 'min:1', 'max:480'],
            'supervisor_id'           => ['nullable', 'exists:users,id'],
            'centro_costo_id'         => ['nullable', 'exists:centros_costo,id'],
            'contenido'               => ['nullable', 'string'],
            'asistentes'              => ['nullable', 'array'],
            'asistentes.*'            => ['exists:users,id'],
            'relatores'               => ['nullable', 'array'],
            'relatores.*.usuario_id'  => ['required', 'exists:users,id'],
            'relatores.*.rol'         => ['required', 'in:RELATOR,SUPERVISOR_CPHS,INSTRUCTOR'],
        ]);

        $charla->update([
            'titulo'           => $request->titulo,
            'contenido'        => $request->contenido,
            'tipo'             => $request->tipo,
            'lugar'            => $request->lugar,
            'fecha_programada' => $request->fecha_programada,
            'duracion_minutos' => $request->duracion_minutos,
            'supervisor_id'    => $request->supervisor_id ?: null,
            'centro_costo_id'  => $request->centro_costo_id ?: null,
        ]);

        // Sync asistentes (keep signed, add new)
        $nuevos     = collect($request->input('asistentes', []));
        $existentes = $charla->asistentes->pluck('usuario_id');
        foreach ($nuevos->diff($existentes) as $userId) {
            CharlaAsistente::create([
                'charla_id'        => $charla->id,
                'usuario_id'       => $userId,
                'fecha_asignacion' => now(),
            ]);
        }
        CharlaAsistente::where('charla_id', $charla->id)
            ->whereIn('usuario_id', $existentes->diff($nuevos)->toArray())
            ->where('estado', 'PENDIENTE')->delete();

        // Sync relatores
        $nuevosRel = collect($request->input('relatores', []));
        $nuevoIds  = $nuevosRel->pluck('usuario_id');
        CharlaRelator::where('charla_id', $charla->id)
            ->whereNotIn('usuario_id', $nuevoIds->toArray())
            ->where('estado', 'PENDIENTE')->delete();
        $existentesRel = $charla->relatores->pluck('usuario_id');
        foreach ($nuevosRel as $rel) {
            if (!$existentesRel->contains($rel['usuario_id'])) {
                CharlaRelator::create([
                    'charla_id'   => $charla->id,
                    'usuario_id'  => $rel['usuario_id'],
                    'rol_relator' => $rel['rol'],
                ]);
            }
        }

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
     * Firma de asistente (trabajador).
     */
    public function firmar(Charla $charla, CharlaAsistente $asistente)
    {
        if ($asistente->charla_id !== $charla->id) abort(403);
        if ($asistente->estado === 'FIRMADO') {
            return redirect()->route('charlas.show', $charla)
                ->with('info', 'Esta asistencia ya fue firmada.');
        }
        $authUser = auth()->user();
        if ($asistente->usuario_id !== $authUser->id
            && !in_array($authUser->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA'])) {
            abort(403, 'Solo puedes firmar tu propia asistencia.');
        }
        $charla->load('supervisor', 'centroCosto');
        $asistente->load('usuario');
        return view('charlas.firmar', compact('charla', 'asistente'));
    }

    public function guardarFirma(Request $request, Charla $charla, CharlaAsistente $asistente)
    {
        $request->validate(['firma_imagen' => ['required', 'string']]);
        if ($asistente->charla_id !== $charla->id) abort(403);
        if ($asistente->estado === 'FIRMADO') return back()->with('error', 'Ya fue firmada.');
        $authUser = auth()->user();
        if ($asistente->usuario_id !== $authUser->id
            && !in_array($authUser->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA'])) {
            abort(403);
        }

        $hash = hash('sha256', implode('|', [
            $charla->titulo, $charla->contenido ?? '',
            $asistente->usuario_id, $request->firma_imagen, now()->toIso8601String(),
        ]));

        $asistente->update([
            'estado'         => 'FIRMADO',
            'firma_imagen'   => $request->firma_imagen,
            'fecha_firma'    => now(),
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'geolatitud'     => $request->input('geo_latitud'),
            'geolongitud'    => $request->input('geo_longitud'),
            'documento_hash' => $hash,
        ]);

        if ($charla->estado === 'PROGRAMADA') {
            $charla->update(['estado' => 'EN_CURSO']);
        }
        if ($charla->asistentes()->where('estado', 'PENDIENTE')->count() === 0) {
            $charla->update(['estado' => 'COMPLETADA', 'fecha_dictado' => now()]);
        }

        return redirect()->route('charlas.show', $charla)
            ->with('success', 'Firma registrada. Hash: ' . substr($hash, 0, 8) . '...');
    }

    /**
     * Firma de relator.
     */
    public function firmarRelator(Charla $charla, CharlaRelator $relator)
    {
        if ($relator->charla_id !== $charla->id) abort(403);
        if ($relator->estado === 'FIRMADO') {
            return redirect()->route('charlas.show', $charla)->with('info', 'Ya firmaste como relator.');
        }
        $authUser = auth()->user();
        if ($relator->usuario_id !== $authUser->id
            && !in_array($authUser->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA'])) {
            abort(403, 'Solo el relator puede firmar su secciÃ³n.');
        }
        $charla->load('supervisor', 'centroCosto');
        $relator->load('usuario');
        return view('charlas.firmar_relator', compact('charla', 'relator'));
    }

    public function guardarFirmaRelator(Request $request, Charla $charla, CharlaRelator $relator)
    {
        $request->validate(['firma_imagen' => ['required', 'string']]);
        if ($relator->charla_id !== $charla->id) abort(403);
        $authUser = auth()->user();
        if ($relator->usuario_id !== $authUser->id
            && !in_array($authUser->rol->nombre ?? '', ['SUPER_ADMIN', 'PREVENCIONISTA'])) {
            abort(403);
        }

        $hash = hash('sha256', implode('|', [
            $charla->titulo, $relator->usuario_id, $relator->rol_relator,
            $request->firma_imagen, now()->toIso8601String(),
        ]));

        $relator->update([
            'estado'         => 'FIRMADO',
            'firma_imagen'   => $request->firma_imagen,
            'fecha_firma'    => now(),
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'geolatitud'     => $request->input('geo_latitud'),
            'geolongitud'    => $request->input('geo_longitud'),
            'documento_hash' => $hash,
        ]);

        return redirect()->route('charlas.show', $charla)
            ->with('success', 'Firma de relator registrada.');
    }

    public function cambiarEstado(Request $request, Charla $charla)
    {
        $request->validate(['estado' => ['required', 'in:BORRADOR,PROGRAMADA,EN_CURSO,COMPLETADA,CANCELADA']]);
        $update = ['estado' => $request->estado];
        if ($request->estado === 'EN_CURSO' && !$charla->fecha_dictado) {
            $update['fecha_dictado'] = now();
        }
        $charla->update($update);
        return back()->with('success', 'Estado actualizado a ' . $request->estado);
    }
}
