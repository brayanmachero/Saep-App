<?php

namespace App\Http\Controllers;

use App\Models\ProgramaSst;
use App\Models\SstCategoria;
use App\Models\SstActividad;
use App\Models\SstSeguimiento;
use App\Models\SstPlanAccion;
use App\Models\CentroCosto;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CartaGanttController extends Controller
{
    public function index()
    {
        $programas = ProgramaSst::with('creador')->orderByDesc('anio')->get();
        return view('carta_gantt.index', compact('programas'));
    }

    public function create()
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.create', compact('centros', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'anio'  => 'required|integer|min:2020|max:2099',
            'nombre'=> 'required|string|max:300',
        ]);
        $programa = ProgramaSst::create([
            'anio'        => $request->anio,
            'titulo'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado'      => strtoupper($request->get('estado', 'BORRADOR')),
            'creado_por'  => auth()->id(),
        ]);
        return redirect()->route('carta-gantt.show', $programa)->with('success', 'Programa SST creado.');
    }

    public function show(ProgramaSst $cartaGantt)
    {
        $cartaGantt->load(['categorias.actividades.seguimiento', 'creador']);
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.show', compact('cartaGantt', 'usuarios'));
    }

    public function edit(ProgramaSst $cartaGantt)
    {
        $centros  = CentroCosto::where('activo', true)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();
        return view('carta_gantt.edit', compact('cartaGantt', 'centros', 'usuarios'));
    }

    public function update(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate(['nombre' => 'required|string|max:300']);
        $cartaGantt->update([
            'titulo'      => $request->nombre,
            'anio'        => $request->anio ?? $cartaGantt->anio,
            'descripcion' => $request->descripcion,
            'estado'      => strtoupper($request->get('estado', $cartaGantt->estado)),
        ]);
        return redirect()->route('carta-gantt.show', $cartaGantt)->with('success', 'Programa actualizado.');
    }

    public function destroy(ProgramaSst $cartaGantt)
    {
        $cartaGantt->update(['estado' => 'CERRADO']);
        return redirect()->route('carta-gantt.index')->with('success', 'Programa cerrado.');
    }

    // Gestión de categorías
    public function storeCategoria(Request $request, ProgramaSst $cartaGantt)
    {
        $request->validate(['nombre' => 'required|string|max:200']);
        $cartaGantt->categorias()->create([
            'nombre' => $request->nombre,
            'orden'  => $cartaGantt->categorias()->max('orden') + 1,
        ]);
        return back()->with('success', 'Categoría agregada.');
    }

    // Gestión de actividades
    public function storeActividad(Request $request, SstCategoria $categoria)
    {
        $request->validate([
            'nombre'      => 'required|string|max:300',
            'meses_prog'  => 'nullable|array',
            'meses_prog.*'=> 'integer|min:1|max:12',
        ]);
        $actividad = $categoria->actividades()->create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'responsable' => $request->responsable,
            'orden'       => $categoria->actividades()->max('orden') + 1,
        ]);
        // Crear seguimiento para meses programados
        foreach ($request->get('meses_prog', []) as $mes) {
            $actividad->seguimiento()->updateOrCreate(
                ['mes' => $mes],
                ['programado' => true]
            );
        }
        return back()->with('success', 'Actividad agregada.');
    }

    // Actualizar seguimiento (marcar realizado)
    public function updateSeguimiento(Request $request, SstActividad $actividad)
    {
        $request->validate([
            'mes'          => 'required|integer|min:1|max:12',
            'realizado'    => 'required|boolean',
            'observacion'  => 'nullable|string',
        ]);
        $actividad->seguimiento()->updateOrCreate(
            ['mes' => $request->mes],
            [
                'realizado'          => $request->realizado,
                'observacion'        => $request->observacion,
                'actualizado_por'    => auth()->id(),
                'fecha_actualizacion'=> now(),
            ]
        );
        return response()->json(['success' => true]);
    }
}
