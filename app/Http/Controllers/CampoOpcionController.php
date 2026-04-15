<?php

namespace App\Http\Controllers;

use App\Models\FormularioCampoOpcion;
use Illuminate\Http\Request;

class CampoOpcionController extends Controller
{
    /**
     * List options for a given form field.
     */
    public function index(int $formularioId, string $campoId, Request $request)
    {
        $query = FormularioCampoOpcion::where('formulario_id', $formularioId)
            ->where('campo_id', $campoId)
            ->orderBy('valor');

        if ($request->filled('q')) {
            $query->where('valor', 'like', '%' . $request->q . '%');
        }

        return response()->json(
            $query->limit(50)->pluck('valor')
        );
    }

    /**
     * Create a new option for a field.
     */
    public function store(int $formularioId, string $campoId, Request $request)
    {
        $request->validate([
            'valor' => ['required', 'string', 'max:500'],
        ]);

        $valor = trim($request->valor);

        $opcion = FormularioCampoOpcion::firstOrCreate(
            [
                'formulario_id' => $formularioId,
                'campo_id'      => $campoId,
                'valor'         => $valor,
            ],
            [
                'creado_por' => auth()->id(),
            ]
        );

        return response()->json([
            'valor'  => $opcion->valor,
            'nuevo'  => $opcion->wasRecentlyCreated,
        ]);
    }

    /**
     * Update an option value (admin).
     */
    public function update(Request $request, FormularioCampoOpcion $opcion)
    {
        $request->validate([
            'valor' => ['required', 'string', 'max:500'],
        ]);

        $nuevo = trim($request->valor);

        // Check for duplicates in the same field
        $existe = FormularioCampoOpcion::where('formulario_id', $opcion->formulario_id)
            ->where('campo_id', $opcion->campo_id)
            ->where('valor', $nuevo)
            ->where('id', '!=', $opcion->id)
            ->exists();

        if ($existe) {
            return back()->with('error', 'Ya existe una opción con ese valor.');
        }

        $opcion->update(['valor' => $nuevo]);

        return back()->with('success', 'Opción actualizada correctamente.');
    }

    /**
     * Delete an option (admin).
     */
    public function destroy(FormularioCampoOpcion $opcion)
    {
        $opcion->delete();

        return back()->with('success', 'Opción eliminada correctamente.');
    }
}
