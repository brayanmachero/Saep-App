<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\CategoriaFormulario;
use App\Models\Departamento;
use App\Models\Formulario;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormularioController extends Controller
{
    public function index(Request $request)
    {
        $query = Formulario::with(['departamento', 'creador', 'categoria']);

        if ($request->filled('buscar')) {
            $q = str_replace(['%', '_'], ['\%', '\_'], $request->buscar);
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%$q%")
                  ->orWhere('codigo', 'like', "%$q%");
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        $formularios = $query->latest()->paginate(15)->withQueryString();
        $categorias  = CategoriaFormulario::where('activo', true)->orderBy('orden')->get();

        return view('formularios.index', compact('formularios', 'categorias'));
    }

    public function create()
    {
        $departamentos = Departamento::where('activo', true)->get();
        $roles      = Rol::all();
        $categorias = CategoriaFormulario::where('activo', true)->orderBy('orden')->get();

        // Auto-generate next code
        $ultimo = Formulario::where('codigo', 'like', 'FORM-%')->orderByDesc('id')->value('codigo');
        $num = 1;
        if ($ultimo && preg_match('/FORM-(\d+)/', $ultimo, $m)) {
            $num = intval($m[1]) + 1;
        }
        $nextCodigo = 'FORM-' . str_pad($num, 4, '0', STR_PAD_LEFT);

        return view('formularios.create', compact('departamentos', 'roles', 'categorias', 'nextCodigo'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string', 'max:1000'],
            'departamento_id'     => ['nullable', 'exists:departamentos,id'],
            'categoria_id'        => ['nullable', 'exists:categorias_formularios,id'],
            'requiere_aprobacion' => ['boolean'],
            'aprobador_rol_id'    => ['nullable', 'exists:roles,id'],
            'genera_pdf'          => ['boolean'],
            'fecha_inicio'        => ['nullable', 'date'],
            'fecha_fin'           => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'frecuencia'          => ['nullable', 'in:unica,diaria,semanal,quincenal,mensual'],
            'schema_json'         => ['required', 'json'],
        ]);

        // Auto-generate unique code
        $ultimo = Formulario::where('codigo', 'like', 'FORM-%')->orderByDesc('id')->value('codigo');
        $num = 1;
        if ($ultimo && preg_match('/FORM-(\d+)/', $ultimo, $m)) {
            $num = intval($m[1]) + 1;
        }
        $codigo = 'FORM-' . str_pad($num, 4, '0', STR_PAD_LEFT);

        $formulario = Formulario::create([
            'codigo'              => $codigo,
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'departamento_id'     => $request->departamento_id,
            'categoria_id'        => $request->categoria_id,
            'schema_json'         => $request->schema_json,
            'version'             => 1,
            'activo'              => true,
            'fecha_inicio'        => $request->fecha_inicio,
            'fecha_fin'           => $request->fecha_fin,
            'frecuencia'          => $request->frecuencia,
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'aprobador_rol_id'    => $request->aprobador_rol_id,
            'genera_pdf'          => $request->boolean('genera_pdf'),
            'creado_por'          => auth()->id(),
        ]);

        return redirect()->route('formularios.show', $formulario)
            ->with('success', 'Formulario creado correctamente.');
    }

    public function show(Formulario $formulario)
    {
        $formulario->load('departamento', 'creador', 'aprobadorRol', 'categoria', 'versiones.modificador');
        $formulario->load(['asignaciones' => function ($q) {
            $q->with('departamento', 'cargo');
        }]);

        $schema = json_decode($formulario->schema_json ?? '[]', true);

        $stats = [
            'total'      => $formulario->respuestas()->count(),
            'pendientes' => $formulario->respuestas()->where('estado', 'Pendiente')->count(),
            'aprobadas'  => $formulario->respuestas()->where('estado', 'Aprobado')->count(),
            'rechazadas' => $formulario->respuestas()->where('estado', 'Rechazado')->count(),
            'borradores' => $formulario->respuestas()->where('estado', 'Borrador')->count(),
        ];

        $asignados    = $formulario->asignaciones;
        $usuariosDisp = User::where('activo', true)
                            ->whereNotIn('id', $asignados->pluck('id'))
                            ->orderBy('name')
                            ->get();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos        = Cargo::where('activo', true)->orderBy('nombre')->get();
        $roles         = Rol::orderBy('nombre')->get();

        return view('formularios.show', compact('formulario', 'schema', 'stats', 'asignados', 'usuariosDisp', 'departamentos', 'cargos', 'roles'));
    }

    public function edit(Formulario $formulario)
    {
        $departamentos = Departamento::where('activo', true)->get();
        $roles      = Rol::all();
        $categorias = CategoriaFormulario::where('activo', true)->orderBy('orden')->get();
        return view('formularios.edit', compact('formulario', 'departamentos', 'roles', 'categorias'));
    }

    public function update(Request $request, Formulario $formulario)
    {
        $request->validate([
            'codigo'              => ['required', 'string', 'max:100', Rule::unique('formularios')->ignore($formulario->id)],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string', 'max:1000'],
            'departamento_id'     => ['nullable', 'exists:departamentos,id'],
            'categoria_id'        => ['nullable', 'exists:categorias_formularios,id'],
            'requiere_aprobacion' => ['boolean'],
            'aprobador_rol_id'    => ['nullable', 'exists:roles,id'],
            'genera_pdf'          => ['boolean'],
            'activo'              => ['boolean'],
            'fecha_inicio'        => ['nullable', 'date'],
            'fecha_fin'           => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'frecuencia'          => ['nullable', 'in:unica,diaria,semanal,quincenal,mensual'],
            'schema_json'         => ['required', 'json'],
        ]);

        // Incrementar versión si el schema cambió
        $nueva_version = $formulario->version;
        $schemaChanged = $formulario->schema_json !== $request->schema_json;
        if ($schemaChanged) {
            // Save current version as snapshot before overwriting
            \App\Models\FormularioVersion::create([
                'formulario_id' => $formulario->id,
                'version'       => $formulario->version,
                'schema_json'   => $formulario->schema_json,
                'modificado_por' => auth()->id(),
            ]);
            $nueva_version++;
        }

        $formulario->update([
            'codigo'              => strtoupper($request->codigo),
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'departamento_id'     => $request->departamento_id,
            'categoria_id'        => $request->categoria_id,
            'schema_json'         => $request->schema_json,
            'version'             => $nueva_version,
            'activo'              => $request->boolean('activo'),
            'fecha_inicio'        => $request->fecha_inicio,
            'fecha_fin'           => $request->fecha_fin,
            'frecuencia'          => $request->frecuencia,
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'aprobador_rol_id'    => $request->aprobador_rol_id,
            'genera_pdf'          => $request->boolean('genera_pdf'),
        ]);

        return redirect()->route('formularios.show', $formulario)
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

    /**
     * Asignar formulario a usuarios (individuales o por departamento).
     */
    public function asignar(Request $request, Formulario $formulario)
    {
        $request->validate([
            'modo'             => ['required', 'in:usuarios,departamento,cargo,rol,todos'],
            'user_ids'         => ['required_if:modo,usuarios', 'array'],
            'user_ids.*'       => ['exists:users,id'],
            'departamento_id'  => ['required_if:modo,departamento', 'exists:departamentos,id'],
            'cargo_id'         => ['required_if:modo,cargo', 'exists:cargos,id'],
            'rol_id'           => ['required_if:modo,rol', 'exists:roles,id'],
            'fecha_limite'     => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $userIds = [];

        switch ($request->modo) {
            case 'usuarios':
                $userIds = $request->user_ids;
                break;
            case 'departamento':
                $userIds = User::where('departamento_id', $request->departamento_id)
                               ->where('activo', true)->pluck('id')->toArray();
                break;
            case 'cargo':
                $userIds = User::where('cargo_id', $request->cargo_id)
                               ->where('activo', true)->pluck('id')->toArray();
                break;
            case 'rol':
                $userIds = User::where('rol_id', $request->rol_id)
                               ->where('activo', true)->pluck('id')->toArray();
                break;
            case 'todos':
                $userIds = User::where('activo', true)->pluck('id')->toArray();
                break;
        }

        $fecha = $request->fecha_limite;
        $added = 0;

        foreach ($userIds as $uid) {
            // Evitar duplicados para la misma fecha_limite
            $exists = \DB::table('formulario_usuario')
                ->where('formulario_id', $formulario->id)
                ->where('user_id', $uid)
                ->where('fecha_limite', $fecha)
                ->exists();

            if (!$exists) {
                $formulario->asignaciones()->attach($uid, [
                    'estado'       => 'Pendiente',
                    'fecha_limite' => $fecha,
                ]);
                $added++;
            }
        }

        return back()->with('success', "{$added} usuario(s) asignado(s) correctamente.");
    }

    /**
     * Quitar asignación de un usuario.
     */
    public function desasignar(Formulario $formulario, User $user)
    {
        \DB::table('formulario_usuario')
            ->where('formulario_id', $formulario->id)
            ->where('user_id', $user->id)
            ->where('estado', 'Pendiente')
            ->delete();

        return back()->with('success', "Asignación removida para {$user->name}.");
    }
}
