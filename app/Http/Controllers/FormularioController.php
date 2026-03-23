<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Formulario;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormularioController extends Controller
{
    public function index(Request $request)
    {
        $query = Formulario::with(['departamento', 'creador']);

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%$q%")
                  ->orWhere('codigo', 'like', "%$q%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        $formularios = $query->latest()->paginate(15)->withQueryString();

        return view('formularios.index', compact('formularios'));
    }

    public function create()
    {
        $departamentos = Departamento::where('activo', true)->get();
        $roles = Rol::all();
        return view('formularios.create', compact('departamentos', 'roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'              => ['required', 'string', 'max:100', 'unique:formularios,codigo'],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string', 'max:1000'],
            'departamento_id'     => ['nullable', 'exists:departamentos,id'],
            'requiere_aprobacion' => ['boolean'],
            'aprobador_rol_id'    => ['nullable', 'exists:roles,id'],
            'genera_pdf'          => ['boolean'],
            'schema_json'         => ['required', 'json'],
        ]);

        Formulario::create([
            'codigo'              => strtoupper($request->codigo),
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'departamento_id'     => $request->departamento_id,
            'schema_json'         => $request->schema_json,
            'version'             => 1,
            'activo'              => true,
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'aprobador_rol_id'    => $request->aprobador_rol_id,
            'genera_pdf'          => $request->boolean('genera_pdf'),
            'creado_por'          => auth()->id(),
        ]);

        return redirect()->route('formularios.index')
            ->with('success', 'Formulario creado correctamente.');
    }

    public function show(Formulario $formulario)
    {
        $formulario->load('departamento', 'creador', 'aprobadorRol');

        $schema = json_decode($formulario->schema_json ?? '[]', true);

        $stats = [
            'total'      => $formulario->respuestas()->count(),
            'pendientes' => $formulario->respuestas()->where('estado', 'Pendiente')->count(),
            'aprobadas'  => $formulario->respuestas()->where('estado', 'Aprobado')->count(),
            'rechazadas' => $formulario->respuestas()->where('estado', 'Rechazado')->count(),
            'borradores' => $formulario->respuestas()->where('estado', 'Borrador')->count(),
        ];

        return view('formularios.show', compact('formulario', 'schema', 'stats'));
    }

    public function edit(Formulario $formulario)
    {
        $departamentos = Departamento::where('activo', true)->get();
        $roles = Rol::all();
        return view('formularios.edit', compact('formulario', 'departamentos', 'roles'));
    }

    public function update(Request $request, Formulario $formulario)
    {
        $request->validate([
            'codigo'              => ['required', 'string', 'max:100', Rule::unique('formularios')->ignore($formulario->id)],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string', 'max:1000'],
            'departamento_id'     => ['nullable', 'exists:departamentos,id'],
            'requiere_aprobacion' => ['boolean'],
            'aprobador_rol_id'    => ['nullable', 'exists:roles,id'],
            'genera_pdf'          => ['boolean'],
            'activo'              => ['boolean'],
            'schema_json'         => ['required', 'json'],
        ]);

        // Incrementar versión si el schema cambió
        $nueva_version = $formulario->version;
        if ($formulario->schema_json !== $request->schema_json) {
            $nueva_version++;
        }

        $formulario->update([
            'codigo'              => strtoupper($request->codigo),
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'departamento_id'     => $request->departamento_id,
            'schema_json'         => $request->schema_json,
            'version'             => $nueva_version,
            'activo'              => $request->boolean('activo'),
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'aprobador_rol_id'    => $request->aprobador_rol_id,
            'genera_pdf'          => $request->boolean('genera_pdf'),
        ]);

        return redirect()->route('formularios.index')
            ->with('success', "Formulario actualizado a versión {$nueva_version}.");
    }

    public function destroy(Formulario $formulario)
    {
        if ($formulario->respuestas()->exists()) {
            $formulario->update(['activo' => false]);
            return redirect()->route('formularios.index')
                ->with('success', 'Formulario desactivado (tiene respuestas asociadas).');
        }

        $formulario->delete();
        return redirect()->route('formularios.index')
            ->with('success', 'Formulario eliminado correctamente.');
    }
}
