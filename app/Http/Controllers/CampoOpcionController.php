<?php

namespace App\Http\Controllers;

use App\Models\FormularioCampoOpcion;
use App\Models\Respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * If the target value already exists, merges both options.
     * Also updates all existing responses that reference the old value.
     */
    public function update(Request $request, FormularioCampoOpcion $opcion)
    {
        $request->validate([
            'valor' => ['required', 'string', 'max:500'],
        ]);

        $nuevo = trim($request->valor);
        $antiguo = $opcion->valor;

        if ($nuevo === $antiguo) {
            return back();
        }

        $merged = false;

        DB::transaction(function () use ($opcion, $antiguo, $nuevo, &$merged) {
            // Update all responses that have the old value in this field
            $respuestas = Respuesta::where('formulario_id', $opcion->formulario_id)
                ->whereNotNull('datos_json')
                ->get();

            foreach ($respuestas as $resp) {
                $datos = json_decode($resp->datos_json, true);
                if (is_array($datos) && isset($datos[$opcion->campo_id]) && $datos[$opcion->campo_id] === $antiguo) {
                    $datos[$opcion->campo_id] = $nuevo;
                    $resp->update(['datos_json' => json_encode($datos, JSON_UNESCAPED_UNICODE)]);
                }
            }

            // Check if the target value already exists as an option (merge case)
            $existente = FormularioCampoOpcion::where('formulario_id', $opcion->formulario_id)
                ->where('campo_id', $opcion->campo_id)
                ->where('valor', $nuevo)
                ->where('id', '!=', $opcion->id)
                ->first();

            if ($existente) {
                // Merge: delete the old option since target already exists
                $opcion->delete();
                $merged = true;
            } else {
                // Simple rename
                $opcion->update(['valor' => $nuevo]);
            }
        });

        $msg = "\"$antiguo\" → \"$nuevo\"" . ($merged ? ' (fusionada)' : '');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'merged' => $merged, 'valor' => $nuevo, 'message' => $msg]);
        }

        return back()->with('success', "Opción actualizada: $msg");
    }

    /**
     * Delete an option (admin).
     */
    public function destroy(Request $request, FormularioCampoOpcion $opcion)
    {
        $opcion->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Opción eliminada correctamente.');
    }
}
