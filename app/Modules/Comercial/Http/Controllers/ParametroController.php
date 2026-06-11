<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\Parametro;
use App\Modules\Comercial\Models\ParametroAuditoria;
use App\Modules\Comercial\Services\IntegradorGobiernoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $uniformesNuevos = $request->input('uniformes_nuevos', []);
        $uniformesCreados = [];

        try {
            DB::transaction(function () use ($request, $parametros, $uniformesNuevos, &$uniformesCreados) {
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

                $uniformesCreados = $this->crearUniformes($uniformesNuevos, $request->input('origen', 'mantenedor'));
            });

            $totalUniformesCreados = count($uniformesCreados);
            $message = $totalUniformesCreados > 0
                ? 'Parámetros actualizados y '.$totalUniformesCreados.' uniforme'.($totalUniformesCreados === 1 ? '' : 's').' agregado'.($totalUniformesCreados === 1 ? '' : 's').' exitosamente.'
                : 'Parámetros actualizados exitosamente.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'uniformes' => $uniformesCreados,
                ]);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', 'Error: '.$e->getMessage());
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
        $this->validarValor($valor, $parametro);

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

    private function crearUniformes(array $uniformes, string $origen = 'mantenedor'): array
    {
        $creados = [];

        foreach ($uniformes as $uniforme) {
            $nombre = trim((string) ($uniforme['nombre'] ?? ''));
            $valor = $uniforme['valor'] ?? null;
            $valorTexto = trim((string) $valor);

            if ($nombre === '' && $valorTexto === '') {
                continue;
            }

            if ($nombre === '') {
                throw new \InvalidArgumentException('Cada uniforme nuevo debe tener nombre.');
            }

            if (mb_strlen($nombre) > 120) {
                throw new \InvalidArgumentException('El nombre del uniforme no puede superar 120 caracteres.');
            }

            if ($valorTexto === '') {
                throw new \InvalidArgumentException("Debe indicar precio para el uniforme '{$nombre}'.");
            }

            $existe = Parametro::where('categoria', 'UNIFORMES')
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->exists();

            if ($existe) {
                throw new \InvalidArgumentException("Ya existe un uniforme llamado '{$nombre}'. Edite su precio en la tarjeta existente.");
            }

            $clave = $this->generarClaveUniforme($nombre);
            $parametro = new Parametro([
                'clave' => $clave,
                'nombre' => $nombre,
                'descripcion' => 'Precio referencial uniforme.',
                'tipo' => 'decimal',
                'editable' => true,
                'categoria' => 'UNIFORMES',
                'version' => 1,
                'actualizado_por' => auth()->id(),
            ]);

            $valorNormalizado = $this->normalizarValor($valorTexto, $parametro);
            $this->validarValor($valorNormalizado, $parametro);

            $parametro->valor = (string) $valorNormalizado;
            $parametro->save();

            $parametro->auditorias()->create([
                'usuario_id' => auth()->id(),
                'clave' => $parametro->clave,
                'nombre' => $parametro->nombre,
                'categoria' => $parametro->categoria,
                'valor_anterior' => null,
                'valor_nuevo' => (string) $valorNormalizado,
                'origen' => $origen,
                'descripcion' => "Creación de uniforme {$parametro->nombre}",
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->header('User-Agent'),
            ]);

            $creados[] = [
                'id' => $parametro->id,
                'clave' => $parametro->clave,
                'nombre' => $parametro->nombre,
                'valor' => (float) $parametro->valor,
                'valor_visual' => $parametro->formatearValorVisual(),
            ];
        }

        return $creados;
    }

    private function generarClaveUniforme(string $nombre): string
    {
        $base = 'UNIFORME_'.Str::upper(Str::slug($nombre, '_'));
        if ($base === 'UNIFORME_') {
            throw new \InvalidArgumentException('El nombre del uniforme debe contener letras o números.');
        }

        $clave = $base;
        $sufijo = 2;
        while (Parametro::withTrashed()->where('clave', $clave)->exists()) {
            $clave = $base.'_'.$sufijo;
            $sufijo++;
        }

        return $clave;
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

    private function validarValor(mixed $valor, Parametro $parametro): void
    {
        match ($parametro->tipo) {
            'integer' => $this->validarEntero($valor),
            'decimal' => $this->validarDecimal($valor),
            'date' => $this->validarFecha($valor),
            default => null,
        };

        if (strtoupper($parametro->clave) === 'JORNADA_SEMANAL_SUB') {
            $this->validarRangoNumerico($valor, 1, 60, 'La jornada semanal debe estar entre 1 y 60 horas.');
        }
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

    private function validarRangoNumerico(mixed $valor, float $min, float $max, string $mensaje): void
    {
        if (! is_numeric($valor) || (float) $valor < $min || (float) $valor > $max) {
            throw new \InvalidArgumentException($mensaje);
        }
    }

    private function validarFecha(mixed $valor): void
    {
        if (! \Carbon\Carbon::canBeCreatedFromFormat((string) $valor, 'Y-m-d')) {
            throw new \InvalidArgumentException('Formato de fecha inválido. Use YYYY-MM-DD.');
        }
    }
}
