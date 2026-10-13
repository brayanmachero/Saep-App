<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\TarifaFuente;
use App\Modules\Comercial\Models\TarifaReferencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SabanaTarifasService
{
    public function importar(array $lote): array
    {
        $this->validar($lote);

        return DB::transaction(function () use ($lote) {
            return $this->registrarLote($lote);
        });
    }

    public function validar(array $lote): void
    {
        $reglas = [
            'fuentes' => 'required|array', 'tarifas' => 'present|array',
            'fuentes.*.clave' => 'required|string|max:1000',
            'fuentes.*.archivo' => 'required|string|max:1000',
            'fuentes.*.hash_sha256' => 'required|regex:/^[a-f0-9]{64}$/',
            'fuentes.*.estado' => 'required|in:pendiente,parcial,leida,excluida',
            'tarifas.*.fuente_clave' => 'required|string|max:1000',
            'tarifas.*.cliente' => 'required|string|max:255',
            'tarifas.*.cargo' => 'required|string|max:255',
            'tarifas.*.precio' => 'nullable|numeric|min:0|max:999999999999',
            'tarifas.*.hoja' => 'required|string|max:255',
            'tarifas.*.celda' => 'required|string|max:255',
            'tarifas.*.fecha_referencia' => 'nullable|date_format:Y-m-d',
            'tarifas.*.conceptos' => 'nullable|array',
            'tarifas.*.centro' => 'nullable|string|max:255',
            'tarifas.*.categoria' => 'nullable|string|max:255',
            'tarifas.*.modalidad' => 'nullable|string|max:255',
            'tarifas.*.unidad' => 'nullable|string|max:255',
            'tarifas.*.variante' => 'nullable|string|max:255',
            'tarifas.*.moneda' => 'nullable|in:CLP,UF,USD',
            'tarifas.*.periodo' => 'nullable|string|max:255',
        ];
        Validator::make($lote, array_intersect_key($reglas, array_flip(['fuentes', 'tarifas'])))->validate();
        // Validate each record separately: expanding wildcard rules over all nested
        // Excel concepts makes large batches needlessly quadratic.
        foreach (['fuentes', 'tarifas'] as $seccion) {
            $reglasFila = [];
            foreach ($reglas as $campo => $regla) {
                if (str_starts_with($campo, $seccion.'.*.')) {
                    $reglasFila[substr($campo, strlen($seccion) + 3)] = $regla;
                }
            }
            foreach ($lote[$seccion] as $fila) {
                Validator::make($fila, $reglasFila)->validate();
            }
        }
    }

    private function registrarLote(array $lote): array
    {
        $normalizar = fn ($valor) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $valor)));
        $centros = CentroCosto::with('cliente')->get();
        $fuentes = [];
        foreach ($lote['fuentes'] as $fila) {
            $fuentes[$fila['clave']] = TarifaFuente::updateOrCreate(
                ['identidad' => hash('sha256', $fila['clave'].'|'.$fila['hash_sha256'])],
                collect($fila)->only(['archivo', 'carpeta', 'hash_sha256', 'hojas', 'estado', 'observaciones'])->all()
            );
        }
        $creadas = 0;
        foreach ($lote['tarifas'] as $fila) {
            $fuente = $fuentes[$fila['fuente_clave']] ?? null;
            if (! $fuente || $fuente->estado === 'excluida') {
                throw new \InvalidArgumentException('Fuente no disponible: '.$fila['fuente_clave']);
            }
            $datos = collect($fila)->only(['cliente', 'centro', 'cargo', 'categoria', 'modalidad', 'unidad', 'variante', 'moneda', 'precio', 'periodo', 'fecha_referencia', 'hoja', 'celda', 'conceptos', 'observaciones'])->all();
            $datos['moneda'] ??= 'CLP';
            if ($datos['cliente'] === 'Por homologar' && ! empty($datos['centro'])) {
                $coincidencias = $centros->filter(fn ($centro) => $normalizar($centro->nombre) === $normalizar($datos['centro']) ||
                    $normalizar($centro->codigo) === $normalizar($datos['centro'])
                );
                if ($coincidencias->count() === 1 && $coincidencias->first()->cliente) {
                    $datos['cliente'] = $coincidencias->first()->cliente->nombre_comercial ?: $coincidencias->first()->cliente->nombre;
                    $datos['observaciones'] = ($datos['observaciones'] ?? '').' Cliente homologado por coincidencia exacta y única del centro comercial.';
                }
            }
            $tarifa = TarifaReferencia::firstOrCreate([
                'identidad' => hash('sha256', $fuente->identidad.'|'.$fila['hoja'].'|'.$fila['celda']),
            ], $datos + ['fuente_id' => $fuente->id, 'grupo' => TarifaReferencia::grupoPara($datos), 'estado' => 'pendiente']);
            $creadas += (int) $tarifa->wasRecentlyCreated;
        }

        return ['fuentes' => count($fuentes), 'nuevas' => $creadas, 'existentes' => count($lote['tarifas']) - $creadas];
    }

    public function consulta(array $filtros)
    {
        $query = TarifaReferencia::with('fuente');
        foreach (['cliente', 'centro', 'cargo', 'categoria', 'modalidad', 'unidad'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->where($campo, $filtros[$campo]);
            }
        }
        if (! empty($filtros['q'])) {
            $q = '%'.$filtros['q'].'%';
            $query->where(fn ($sub) => $sub->where('cliente', 'like', $q)->orWhere('centro', 'like', $q)
                ->orWhere('cargo', 'like', $q)->orWhere('categoria', 'like', $q)
                ->orWhereHas('fuente', fn ($fuente) => $fuente->where('archivo', 'like', $q)));
        }
        if (! empty($filtros['anio'])) {
            $query->whereYear('fecha_referencia', $filtros['anio']);
        }
        $vista = $filtros['vista'] ?? 'ultimas';
        if ($vista === 'vigentes') {
            $fecha = $filtros['fecha'] ?? now()->toDateString();
            $query->where('estado', 'confirmada')->whereDate('vigente_desde', '<=', $fecha)
                ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha))
                ->whereNotExists(function ($sub) use ($fecha) {
                    $sub->selectRaw('1')->from('comercial_tarifas_referencia as siguiente')
                        ->whereColumn('siguiente.grupo', 'comercial_tarifas_referencia.grupo')
                        ->where('siguiente.estado', 'confirmada')->whereDate('siguiente.vigente_desde', '<=', $fecha)
                        ->where(fn ($q) => $q->whereColumn('siguiente.vigente_desde', '>', 'comercial_tarifas_referencia.vigente_desde')
                            ->orWhere(fn ($q) => $q->whereColumn('siguiente.vigente_desde', 'comercial_tarifas_referencia.vigente_desde')
                                ->whereColumn('siguiente.id', '>', 'comercial_tarifas_referencia.id')));
                });
        } elseif ($vista === 'ultimas') {
            $query->where('estado', '!=', 'descartada')->whereNotExists(function ($sub) use ($filtros) {
                $sub->selectRaw('1')->from('comercial_tarifas_referencia as posterior')
                    ->whereColumn('posterior.grupo', 'comercial_tarifas_referencia.grupo')
                    ->where('posterior.estado', '!=', 'descartada')
                    ->whereColumn('posterior.fecha_referencia', '>', 'comercial_tarifas_referencia.fecha_referencia');
                if (! empty($filtros['anio'])) {
                    $sub->whereYear('posterior.fecha_referencia', $filtros['anio']);
                }
            });
        } elseif ($vista === 'pendientes') {
            $query->where('estado', 'pendiente');
        } elseif ($vista === 'referencias') {
            $query->where('estado', '!=', 'descartada');
        }

        return $query->orderBy('cliente')->orderBy('centro')->orderBy('cargo')->orderByDesc('fecha_referencia')->orderByDesc('id');
    }
}
