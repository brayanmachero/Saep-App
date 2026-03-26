<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\CentroCosto;
use App\Models\Departamento;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['rol', 'departamento', 'cargo', 'centroCosto']);

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('rut', 'like', "%$q%")
                  ->orWhere('apellido_paterno', 'like', "%$q%")
                  ->orWhere('apellido_materno', 'like', "%$q%");
            });
        }

        if ($request->filled('rol_id')) {
            $query->where('rol_id', $request->rol_id);
        }

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
        }

        if ($request->filled('cargo_id')) {
            $query->where('cargo_id', $request->cargo_id);
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        $usuarios = $query->latest()->paginate(15)->withQueryString();
        $roles = Rol::all();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos = Cargo::where('activo', true)->get();

        return view('usuarios.index', compact('usuarios', 'roles', 'departamentos', 'cargos'));
    }

    public function create()
    {
        $roles = Rol::all();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos = Cargo::where('activo', true)->get();
        $centrosCosto = CentroCosto::where('activo', true)->get();
        return view('usuarios.create', compact('roles', 'departamentos', 'cargos', 'centrosCosto'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'apellido_paterno'  => ['nullable', 'string', 'max:200'],
            'apellido_materno'  => ['nullable', 'string', 'max:200'],
            'email'             => ['required', 'email', 'unique:users,email'],
            'rut'               => ['nullable', 'string', 'max:20'],
            'rol_id'            => ['required', 'exists:roles,id'],
            'departamento_id'   => ['nullable', 'exists:departamentos,id'],
            'cargo_id'          => ['nullable', 'exists:cargos,id'],
            'centro_costo_id'   => ['nullable', 'exists:centros_costo,id'],
            'tipo_nomina'       => ['nullable', 'in:NORMAL,TRANSITORIO'],
            'razon_social'      => ['nullable', 'string', 'max:300'],
            'fecha_nacimiento'  => ['nullable', 'date'],
            'nacionalidad'      => ['nullable', 'string', 'max:100'],
            'sexo'              => ['nullable', 'string', 'max:10'],
            'estado_civil'      => ['nullable', 'string', 'max:50'],
            'fecha_ingreso'     => ['nullable', 'date'],
            'telefono'          => ['nullable', 'string', 'max:50'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            'activo'            => ['boolean'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['activo'] = $request->boolean('activo', true);

        User::create($data);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $roles = Rol::all();
        $departamentos = Departamento::where('activo', true)->get();
        $cargos = Cargo::where('activo', true)->get();
        $centrosCosto = CentroCosto::where('activo', true)->get();
        return view('usuarios.edit', compact('usuario', 'roles', 'departamentos', 'cargos', 'centrosCosto'));
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'apellido_paterno'  => ['nullable', 'string', 'max:200'],
            'apellido_materno'  => ['nullable', 'string', 'max:200'],
            'email'             => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'rut'               => ['nullable', 'string', 'max:20'],
            'rol_id'            => ['required', 'exists:roles,id'],
            'departamento_id'   => ['nullable', 'exists:departamentos,id'],
            'cargo_id'          => ['nullable', 'exists:cargos,id'],
            'centro_costo_id'   => ['nullable', 'exists:centros_costo,id'],
            'tipo_nomina'       => ['nullable', 'in:NORMAL,TRANSITORIO'],
            'razon_social'      => ['nullable', 'string', 'max:300'],
            'fecha_nacimiento'  => ['nullable', 'date'],
            'nacionalidad'      => ['nullable', 'string', 'max:100'],
            'sexo'              => ['nullable', 'string', 'max:10'],
            'estado_civil'      => ['nullable', 'string', 'max:50'],
            'fecha_ingreso'     => ['nullable', 'date'],
            'telefono'          => ['nullable', 'string', 'max:50'],
            'activo'            => ['boolean'],
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = Hash::make($request->password);
        }

        $data['activo'] = $request->boolean('activo');

        $usuario->update($data);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $usuario->update(['activo' => false]);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario desactivado correctamente.');
    }
}
