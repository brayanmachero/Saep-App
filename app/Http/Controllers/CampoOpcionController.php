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
}
