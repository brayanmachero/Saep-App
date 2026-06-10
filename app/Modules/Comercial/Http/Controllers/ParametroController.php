<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\Parametro;
use App\Modules\Comercial\Models\ParametroAuditoria;
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

        $auditorias = ParametroAuditoria::with('usuario')
            ->latest('created_at')
            ->limit(80)
            ->get();

        return view('comercial::mantenedor.parametros', compact(
            'parametrosPorCategoria',
            'ultimaActualizacion',
            'auditorias',
        ));
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
                    $this->actualizarParametro($parametro, $valor, $request->input('origen', 'mantenedor'));
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

    private function actualizarParametro(Parametro $parametro, mixed $valor, string $origen = 'mantenedor'): void
    {
        if (! $parametro->editable) {
            throw new \InvalidArgumentException('Este parámetro no es editable.');
        }

        $valor = $this->normalizarValor($valor, $parametro);
        $this->validarValor($valor, $parametro->tipo);

        $valorAnterior = $parametro->valor;
        $parametro->valor_anterior = $parametro->valor;
        $parametro->valor = (string) $valor;
        $parametro->version += 1;
        $parametro->actualizado_por = auth()->id();
        $parametro->save();

        $parametro->auditorias()->create([
            'usuario_id' => auth()->id(),
            'clave' => $parametro->clave,
            'nombre' => $parametro->nombre,
            'categoria' => $parametro->categoria,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => (string) $valor,
            'origen' => $origen,
            'descripcion' => "Cambio de {$parametro->nombre}",
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->header('User-Agent'),
        ]);
    }

    private function normalizarValor(mixed $valor, Parametro $parametro): string
    {
        $valor = trim((string) $valor);
        $valor = str_replace(['$', '%', ' '], '', $valor);
        $valor = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';

        if ($valor === '') {
            return $valor;
        }

        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            return str_replace(',', '.', $valor);
        }

        if ($parametro->formato_visual === 'entero') {
            return str_replace('.', '', $valor);
        }

        if ($parametro->formato_visual === 'moneda') {
            $partes = explode('.', $valor);
            if (count($partes) > 2 || (count($partes) === 2 && strlen(end($partes)) === 3)) {
                return str_replace('.', '', $valor);
            }
        }

        return $valor;
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
