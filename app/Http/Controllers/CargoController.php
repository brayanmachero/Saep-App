<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'nombre' => 'required|string|max:200',
        ]);

        $codigo = $this->generarCodigo($request->nombre);

        Cargo::create(['codigo' => $codigo, 'nombre' => $request->nombre]);
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
            'nombre' => 'required|string|max:200',
        ]);

        $codigo = $this->generarCodigo($request->nombre, $cargo->id);

        $cargo->update(['codigo' => $codigo, 'nombre' => $request->nombre, 'activo' => $request->boolean('activo')]);
        return redirect()->route('cargos.index')->with('success', 'Cargo actualizado.');
    }

    public function destroy(Cargo $cargo)
    {
        $cargo->update(['activo' => false]);
        return redirect()->route('cargos.index')->with('success', 'Cargo desactivado.');
    }

    private function generarCodigo(string $nombre, ?int $excludeId = null): string
    {
        $base = Str::upper(Str::slug($nombre, '_'));
        $codigo = Str::limit($base, 50, '');
        $suffix = 0;

        while (Cargo::where('codigo', $codigo)->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $suffix++;
            $codigo = Str::limit($base, 47, '') . '_' . $suffix;
        }

        return $codigo;
    }
}
