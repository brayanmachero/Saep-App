<?php

namespace App\Http\Controllers;

use App\Models\OpcionAccidenteSst;
use Illuminate\Http\Request;

class OpcionAccidenteSstController extends Controller
{
    /**
     * Vista de gestión del catálogo de opciones.
     */
    public function index(Request $request)
    {
        $tipo = $request->get('tipo', 'lesion');

        $opciones = OpcionAccidenteSst::where('tipo', $tipo)
            ->orderBy('nombre')
            ->get();

        $conteos = OpcionAccidenteSst::selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return view('accidentes_sst.opciones', compact('opciones', 'tipo', 'conteos'));
    }

    /**
     * Crear nueva opción (JSON).
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo'   => 'required|in:lesion,causa,medida',
            'nombre' => 'required|string|max:300',
        ]);

        $opcion = OpcionAccidenteSst::firstOrCreate(
            ['tipo' => $request->tipo, 'nombre' => trim($request->nombre)],
            ['activo' => true]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok'    => true,
                'nuevo' => $opcion->wasRecentlyCreated,
                'opcion' => $opcion,
            ]);
        }

        $msg = $opcion->wasRecentlyCreated ? 'Opción creada.' : 'La opción ya existe.';
        return back()->with('success', $msg);
    }

    /**
     * Actualizar nombre de opción (JSON).
     */
    public function update(Request $request, OpcionAccidenteSst $opcion)
    {
        $request->validate([
            'nombre' => 'required|string|max:300',
        ]);

        $nuevo = trim($request->nombre);

        // Si ya existe una con el mismo tipo+nombre, fusionar
        $existente = OpcionAccidenteSst::where('tipo', $opcion->tipo)
            ->where('nombre', $nuevo)
            ->where('id', '!=', $opcion->id)
            ->first();

        if ($existente) {
            // Mover pivots al existente y eliminar duplicado
            \DB::table('accidente_sst_opcion')
                ->where('opcion_id', $opcion->id)
                ->update(['opcion_id' => $existente->id]);
            $opcion->delete();
            $msg = "Fusionada con opción existente.";
        } else {
            $opcion->update(['nombre' => $nuevo]);
            $msg = "Opción actualizada.";
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Activar / desactivar opción.
     */
    public function toggleActivo(OpcionAccidenteSst $opcion)
    {
        $opcion->update(['activo' => !$opcion->activo]);

        return response()->json([
            'ok' => true,
            'activo' => $opcion->activo,
        ]);
    }

    /**
     * Eliminar opción.
     */
    public function destroy(Request $request, OpcionAccidenteSst $opcion)
    {
        $opcion->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Opción eliminada.');
    }

    /**
     * API: listar opciones activas por tipo (para selects dinámicos).
     */
    public function api(Request $request, string $tipo)
    {
        $query = OpcionAccidenteSst::where('tipo', $tipo)->where('activo', true)->orderBy('nombre');

        if ($request->filled('q')) {
            $query->where('nombre', 'like', '%' . $request->q . '%');
        }

        return response()->json($query->get(['id', 'nombre']));
    }
}
