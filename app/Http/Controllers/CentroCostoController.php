<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CentroCostoController extends Controller
{
    public function index()
    {
        $centros = CentroCosto::orderBy('nombre')->get();
        return view('centros_costo.index', compact('centros'));
    }

    public function create()
    {
        return view('centros_costo.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'razon_social' => 'required|in:NORMAL,TRANSITORIO',
        ]);
        $codigo = $this->generarCodigo($request->nombre);
        CentroCosto::create(['codigo' => $codigo] + $request->only(['nombre','razon_social']));
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo creado.');
    }

    public function show(CentroCosto $centrosCosto)
    {
        return redirect()->route('centros-costo.index');
    }

    public function edit(CentroCosto $centrosCosto)
    {
        return view('centros_costo.edit', ['centroCosto' => $centrosCosto]);
    }

    public function update(Request $request, CentroCosto $centrosCosto)
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'razon_social' => 'required|in:NORMAL,TRANSITORIO',
        ]);
        $codigo = $this->generarCodigo($request->nombre, $centrosCosto->id);
        $centrosCosto->update(['codigo' => $codigo] + $request->only(['nombre','razon_social','activo']));
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo actualizado.');
    }

    public function destroy(CentroCosto $centrosCosto)
    {
        $centrosCosto->update(['activo' => false]);
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo desactivado.');
    }

    private function generarCodigo(string $nombre, ?int $excludeId = null): string
    {
        $base = Str::upper(Str::slug($nombre, '_'));
        $codigo = Str::limit($base, 50, '');
        $suffix = 0;

        while (CentroCosto::where('codigo', $codigo)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $suffix++;
            $codigo = Str::limit($base, 47, '') . '_' . $suffix;
        }

        return $codigo;
    }
}
