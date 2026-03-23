<?php

namespace App\Http\Controllers;

use App\Models\VisitaSst;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VisitaSstController extends Controller
{
    public function index()
    {
        $visitas = VisitaSst::with(['centroCosto', 'inspector'])
            ->orderByDesc('fecha_visita')
            ->paginate(20);
        return view('visitas_sst.index', compact('visitas'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('visitas_sst.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha_visita'   => 'required|date',
            'tipo_visita'    => 'required|string',
            'centro_costo_id'=> 'required|exists:centros_costo,id',
        ]);
        VisitaSst::create(array_merge(
            $request->except(['_token']),
            ['inspector_id' => $request->inspector_id ?? auth()->id()]
        ));
        return redirect()->route('visitas-sst.index')->with('success', 'Visita registrada correctamente.');
    }

    public function show(VisitaSst $visitasSst)
    {
        $visitaSst = $visitasSst->load(['centroCosto', 'inspector']);
        return view('visitas_sst.show', compact('visitaSst'));
    }

    public function edit(VisitaSst $visitasSst)
    {
        $visitaSst = $visitasSst;
        $centros   = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios  = User::orderBy('name')->get();
        return view('visitas_sst.edit', compact('visitaSst', 'centros', 'usuarios'));
    }

    public function update(Request $request, VisitaSst $visitasSst)
    {
        $request->validate([
            'fecha_visita'   => 'required|date',
            'tipo_visita'    => 'required|string',
            'centro_costo_id'=> 'required|exists:centros_costo,id',
        ]);
        $visitasSst->update($request->except(['_token', '_method']));
        return redirect()->route('visitas-sst.show', $visitasSst)->with('success', 'Visita actualizada.');
    }

    public function destroy(VisitaSst $visitasSst)
    {
        $visitasSst->delete();
        return redirect()->route('visitas-sst.index')->with('success', 'Visita eliminada.');
    }
}

