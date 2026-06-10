<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\Parametro;
use App\Modules\Comercial\Services\IntegradorGobiernoService;
use Illuminate\Http\Request;

class ParametroController
{
    public function index()
    {
        $parametrosPorCategoria = Parametro::editables()
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get()
            ->groupBy('categoria');

        $ultimaActualizacion = Parametro::with('actualizadoPor')
            ->latest('updated_at')
            ->first();

        return view('comercial::mantenedor.parametros', compact('parametrosPorCategoria', 'ultimaActualizacion'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'parametro_id' => 'required|exists:comercial_parametros,id',
            'valor' => 'required',
        ]);

        try {
            $parametro = Parametro::findOrFail($validated['parametro_id']);
            $this->actualizarParametro($parametro, $validated['valor']);

            return back()->with('success', "Parámetro '{$parametro->nombre}' actualizado.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error: '.$e->getMessage());
        }
    }

    public function batchUpdate(Request $request)
    {
        $parametros = $request->input('parametros', []);

        try {
            foreach ($parametros as $id => $payload) {
                $valor = is_array($payload) ? ($payload['valor'] ?? null) : $payload;
                if ($valor === null) {
                    continue;
                }

                $parametro = Parametro::findOrFail($id);
                if ($parametro->editable && (string) $valor !== (string) $parametro->valor) {
                    $this->actualizarParametro($parametro, $valor);
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Parámetros actualizados exitosamente.',
                ]);
            }

            return back()->with('success', 'Parámetros actualizados exitosamente.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Error: '.$e->getMessage());
        }
    }

    public function actualizarGobierno(IntegradorGobiernoService $integrador)
    {
        $resultados = $integrador->actualizarTodos();

        return response()->json([
            'success' => true,
            'message' => 'Proceso de actualización finalizado.',
            'resultados' => $resultados,
        ]);
    }

    private function actualizarParametro(Parametro $parametro, mixed $valor): void
    {
        if (! $parametro->editable) {
            throw new \InvalidArgumentException('Este parámetro no es editable.');
        }

        $this->validarValor($valor, $parametro->tipo);

        $parametro->valor_anterior = $parametro->valor;
        $parametro->valor = (string) $valor;
        $parametro->version += 1;
        $parametro->actualizado_por = auth()->id();
        $parametro->save();
    }

    private function validarValor(mixed $valor, string $tipo): void
    {
        match ($tipo) {
            'integer' => $this->validarEntero($valor),
            'decimal' => $this->validarDecimal($valor),
            'date' => $this->validarFecha($valor),
            default => null,
        };
    }

    private function validarEntero(mixed $valor): void
    {
        if (! is_numeric($valor) || (int) $valor != $valor) {
            throw new \InvalidArgumentException('Debe ser un número entero.');
        }
    }

    private function validarDecimal(mixed $valor): void
    {
        if (! is_numeric($valor)) {
            throw new \InvalidArgumentException('Debe ser un número decimal.');
        }
    }

    private function validarFecha(mixed $valor): void
    {
        if (! \Carbon\Carbon::canBeCreatedFromFormat((string) $valor, 'Y-m-d')) {
            throw new \InvalidArgumentException('Formato de fecha inválido. Use YYYY-MM-DD.');
        }
    }
}
