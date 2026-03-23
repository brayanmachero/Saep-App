<?php

namespace App\Http\Controllers;

use App\Models\LeyKarin;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeyKarinController extends Controller
{
    public function index()
    {
        $casos = LeyKarin::with(['centroCosto','investigador'])
            ->orderByDesc('fecha_denuncia')->paginate(20);
        return view('ley_karin.index', compact('casos'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('ley_karin.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo_denuncia'      => 'required|string',
            'fecha_denuncia'     => 'required|date',
            'descripcion_hechos' => 'required|string',
            'centro_costo_id'    => 'required|exists:centros_costo,id',
        ]);
        LeyKarin::create($request->except(['_token']));
        return redirect()->route('ley-karin.index')->with('success', 'Caso registrado correctamente.');
    }

    public function show(LeyKarin $leyKarin)
    {
        $leyKarin->load(['centroCosto', 'investigador', 'denunciante']);
        return view('ley_karin.show', compact('leyKarin'));
    }

    public function edit(LeyKarin $leyKarin)
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('ley_karin.edit', compact('leyKarin', 'centros', 'usuarios'));
    }

    public function update(Request $request, LeyKarin $leyKarin)
    {
        $request->validate([
            'fecha_denuncia'     => 'required|date',
            'descripcion_hechos' => 'required|string',
            'centro_costo_id'    => 'required|exists:centros_costo,id',
        ]);
        $leyKarin->update($request->except(['_token', '_method']));
        return redirect()->route('ley-karin.show', $leyKarin)->with('success', 'Caso actualizado.');
    }

    public function destroy(LeyKarin $leyKarin)
    {
        $leyKarin->update(['estado' => 'ARCHIVADA']);
        return redirect()->route('ley-karin.index')->with('success', 'Caso archivado.');
    }
}
