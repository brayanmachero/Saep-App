<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            'codigo' => 'required|string|max:100|unique:centros_costo,codigo',
            'nombre' => 'required|string|max:200',
            'razon_social' => 'required|in:NORMAL,TRANSITORIO',
        ]);
        CentroCosto::create($request->only(['codigo','nombre','razon_social']));
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo creado.');
    }

    public function show(CentroCosto $centrosCosto)
    {
        return redirect()->route('centros-costo.index');
    }

    public function edit(CentroCosto $centrosCosto)
    {
        return view('centros_costo.edit', ['centro' => $centrosCosto]);
    }

    public function update(Request $request, CentroCosto $centrosCosto)
    {
        $request->validate([
            'codigo' => 'required|string|max:100|unique:centros_costo,codigo,'.$centrosCosto->id,
            'nombre' => 'required|string|max:200',
            'razon_social' => 'required|in:NORMAL,TRANSITORIO',
        ]);
        $centrosCosto->update($request->only(['codigo','nombre','razon_social','activo']));
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo actualizado.');
    }

    public function destroy(CentroCosto $centrosCosto)
    {
        $centrosCosto->update(['activo' => false]);
        return redirect()->route('centros-costo.index')->with('success', 'Centro de costo desactivado.');
    }
}
