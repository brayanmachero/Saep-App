<?php

namespace App\Http\Controllers;

use App\Models\AccidenteSst;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccidenteSstController extends Controller
{
    public function index()
    {
        $accidentes = AccidenteSst::with(['centroCosto', 'trabajador'])
            ->orderByDesc('fecha_accidente')->paginate(20);
        return view('accidentes_sst.index', compact('accidentes'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('accidentes_sst.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo'            => 'required|string',
            'fecha_accidente' => 'required|date',
            'descripcion'     => 'required|string',
            'gravedad'        => 'required|string',
            'centro_costo_id' => 'required|exists:centros_costo,id',
        ]);
        AccidenteSst::create(array_merge(
            $request->except(['_token']),
            ['registrado_por' => auth()->id()]
        ));
        return redirect()->route('accidentes-sst.index')->with('success', 'Accidente registrado correctamente.');
    }

    public function show(AccidenteSst $accidentesSst)
    {
        $accidenteSst = $accidentesSst->load(['centroCosto', 'trabajador']);
        return view('accidentes_sst.show', compact('accidenteSst'));
    }

    public function edit(AccidenteSst $accidentesSst)
    {
        $accidenteSst = $accidentesSst;
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('accidentes_sst.edit', compact('accidenteSst', 'centros', 'usuarios'));
    }

    public function update(Request $request, AccidenteSst $accidentesSst)
    {
        $request->validate([
            'tipo'            => 'required|string',
            'fecha_accidente' => 'required|date',
            'descripcion'     => 'required|string',
            'gravedad'        => 'required|string',
            'centro_costo_id' => 'required|exists:centros_costo,id',
        ]);
        $accidentesSst->update($request->except(['_token', '_method']));
        return redirect()->route('accidentes-sst.show', $accidentesSst)->with('success', 'Accidente actualizado.');
    }

    public function destroy(AccidenteSst $accidentesSst)
    {
        $accidentesSst->delete();
        return redirect()->route('accidentes-sst.index')->with('success', 'Registro eliminado.');
    }
}
