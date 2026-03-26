<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index()
    {
        $cargos = Cargo::orderBy('nombre')->get();
        return view('cargos.index', compact('cargos'));
    }

    public function create()
    {
        return view('cargos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string|max:100|unique:cargos,codigo',
            'nombre' => 'required|string|max:200',
        ]);
        Cargo::create($request->only(['codigo','nombre']));
        return redirect()->route('cargos.index')->with('success', 'Cargo creado.');
    }

    public function show(Cargo $cargo)
    {
        return redirect()->route('cargos.index');
    }

    public function edit(Cargo $cargo)
    {
        return view('cargos.edit', compact('cargo'));
    }

    public function update(Request $request, Cargo $cargo)
    {
        $request->validate([
            'codigo' => 'required|string|max:100|unique:cargos,codigo,'.$cargo->id,
            'nombre' => 'required|string|max:200',
        ]);
        $cargo->update($request->only(['codigo','nombre','activo']));
        return redirect()->route('cargos.index')->with('success', 'Cargo actualizado.');
    }

    public function destroy(Cargo $cargo)
    {
        $cargo->update(['activo' => false]);
        return redirect()->route('cargos.index')->with('success', 'Cargo desactivado.');
    }
}
