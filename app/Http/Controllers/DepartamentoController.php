<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller
{
    public function index()
    {
        $departamentos = Departamento::withCount('users')->latest()->paginate(20);
        return view('departamentos.index', compact('departamentos'));
    }

    public function create()
    {
        return view('departamentos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'      => ['required', 'string', 'max:50', 'unique:departamentos,codigo'],
            'nombre'      => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        Departamento::create([
            'codigo'      => strtoupper($request->codigo),
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo'      => true,
        ]);

        return redirect()->route('departamentos.index')
            ->with('success', 'Departamento creado correctamente.');
    }

    public function edit(Departamento $departamento)
    {
        return view('departamentos.edit', compact('departamento'));
    }

    public function update(Request $request, Departamento $departamento)
    {
        $request->validate([
            'codigo'      => ['required', 'string', 'max:50', Rule::unique('departamentos')->ignore($departamento->id)],
            'nombre'      => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo'      => ['boolean'],
        ]);

        $departamento->update([
            'codigo'      => strtoupper($request->codigo),
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo'      => $request->boolean('activo'),
        ]);

        return redirect()->route('departamentos.index')
            ->with('success', 'Departamento actualizado correctamente.');
    }

    public function destroy(Departamento $departamento)
    {
        if ($departamento->users()->exists()) {
            return back()->with('error', 'No se puede eliminar: tiene usuarios asociados.');
        }

        $departamento->delete();

        return redirect()->route('departamentos.index')
            ->with('success', 'Departamento eliminado correctamente.');
    }
}
