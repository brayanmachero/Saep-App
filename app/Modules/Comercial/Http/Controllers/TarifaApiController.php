<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Models\Configuracion;
use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarifaApiController
{
    private const COLUMNAS_TARIFA = [
        'cotizacion_id',
        'cotizacion_numero',
        'estado',
        'version',
        'titulo',
        'cargo',
        'modalidad',
        'modalidad_nombre',
        'cliente_id',
        'cliente_rut',
        'cliente_nombre',
        'cliente_nombre_comercial',
        'centro_costo_id',
        'centro_costo_codigo',
        'centro_costo_nombre',
        'fecha_cotizacion',
        'fecha_aprobacion',
        'fecha_vigencia',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'moneda',
        'sueldo_base',
        'bono_asistencia',
        'bono_compromiso',
        'otros_haberes',
        'gratificacion',
        'total_imponible',
        'total_no_imponible',
        'total_haberes',
        'imposiciones',
        'alcance_liquido',
        'renta_tributable',
        'impuesto_unico',
        'refprev',
        'sis',
        'mutual',
        'seguro_cesantia',
        'total_cotizaciones',
        'provision_vacaciones',
        'provision_indemnizaciones',
        'total_provisiones',
        'uniformes_total',
        'servicios_casino',
        'seguro_accidentes_personales',
        'otros_gastos',
        'otros_beneficios',
        'gastos_administracion',
        'total_gastos',
        'costo_bruto',
        'margen_porcentaje',
        'margen',
        'precio_venta',
        'hora_normal',
        'hora_normal_hhee',
        'hora_extra_50',
        'hora_extra_100',
        'jornada_semanal_hhee',
        'factor_normal_hhee',
        'actualizado_en',
    ];

    public function clientes(Request $request)
    {
        $this->validarToken($request);

        $query = Cliente::activos()
            ->select(['id', 'rut', 'nombre', 'nombre_comercial'])
            ->orderBy('nombre');

        if ($request->filled('q')) {
            $termino = trim((string) $request->query('q'));
            $query->where(function (Builder $builder) use ($termino) {
                $builder->where('rut', 'like', "%{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%")
                    ->orWhere('nombre_comercial', 'like', "%{$termino}%");
            });
        }

        return response()->json([
            'success' => true,
            'generated_at' => now()->toIso8601String(),
            'data' => $query->limit($this->limite($request))->get(),
        ]);
    }

    public function tarifasCotizadas(Request $request)
    {
        $this->validarToken($request);
        $this->validarFiltroCliente($request);

        $estados = $this->estadosSolicitados($request);
        $limite = $this->limite($request);

        $query = Cotizacion::with([
            'cliente',
            'centroCosto',
            'modalidad',
            'detalles',
            'uniformes',
        ])->whereIn('estado', $estados);

        $this->aplicarFiltros($query, $request);

        $cotizaciones = $query
            ->orderByRaw('COALESCE(fecha_vigencia, fecha_aprobacion, fecha_cotizacion, created_at) DESC')
            ->orderByDesc('id')
            ->limit($limite * 3)
            ->get()
            ->unique(fn (Cotizacion $cotizacion) => $this->claveTarifa($cotizacion))
            ->take($limite)
            ->values()
            ->map(fn (Cotizacion $cotizacion) => $this->filaTarifa($cotizacion));

        if (strtolower((string) $request->query('format')) === 'csv') {
            return $this->respuestaCsv($cotizaciones->all(), 'tarifas-cotizadas.csv');
        }

        return response()->json([
            'success' => true,
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'cliente_id' => $request->query('cliente_id'),
                'rut' => $request->query('rut'),
                'cliente' => $request->query('cliente'),
                'modalidad' => $request->query('modalidad'),
                'centro_costo_id' => $request->query('centro_costo_id'),
                'estados' => $estados,
            ],
            'count' => $cotizaciones->count(),
            'data' => $cotizaciones,
        ]);
    }

    private function validarToken(Request $request): void
    {
        if (! config('comercial.api.enabled', true)) {
            $this->errorJson(404, 'API comercial deshabilitada.');
        }

        $esperado = (string) (config('comercial.api.token') ?: Configuracion::get('comercial_api_token', ''));
        if ($esperado === '') {
            $this->errorJson(503, 'API comercial no configurada.');
        }

        $recibido = $request->bearerToken()
            ?: $request->header('X-SAEP-API-KEY')
            ?: (config('comercial.api.allow_query_token', false) ? ($request->query('api_key') ?: $request->query('token')) : null);

        if (! is_string($recibido) || ! hash_equals($esperado, $recibido)) {
            $this->errorJson(401, 'Token API invalido.');
        }
    }

    private function validarFiltroCliente(Request $request): void
    {
        if ($request->filled('cliente_id') || $request->filled('rut') || $request->filled('cliente')) {
            return;
        }

        $this->errorJson(422, 'Debe indicar cliente_id, rut o cliente para consultar tarifas.');
    }

    private function errorJson(int $status, string $mensaje): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $mensaje,
        ], $status));
    }

    private function aplicarFiltros(Builder $query, Request $request): void
    {
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('rut')) {
            $rut = trim((string) $request->query('rut'));
            $rutNormalizado = str_replace(['.', ' '], '', $rut);

            $query->whereHas('cliente', function (Builder $cliente) use ($rut, $rutNormalizado) {
                $cliente->where('rut', $rut)
                    ->orWhereRaw("REPLACE(REPLACE(rut, '.', ''), ' ', '') = ?", [$rutNormalizado]);
            });
        }

        if ($request->filled('cliente')) {
            $termino = trim((string) $request->query('cliente'));
            $query->whereHas('cliente', function (Builder $cliente) use ($termino) {
                $cliente->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('nombre_comercial', 'like', "%{$termino}%");
            });
        }

        if ($request->filled('modalidad')) {
            $modalidad = strtoupper(trim((string) $request->query('modalidad')));
            $query->whereHas('modalidad', fn (Builder $modalidadQuery) => $modalidadQuery->where('codigo', $modalidad));
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->integer('centro_costo_id'));
        }
    }

    private function estadosSolicitados(Request $request): array
    {
        $permitidos = ['vigente', 'aprobada'];
        $valor = $request->query('estado', $request->query('estados'));

        if (! $valor || $valor === 'ambas') {
            return config('comercial.api.default_estados', $permitidos);
        }

        $estados = collect(explode(',', (string) $valor))
            ->map(fn (string $estado) => trim($estado))
            ->filter(fn (string $estado) => in_array($estado, $permitidos, true))
            ->values()
            ->all();

        return $estados ?: config('comercial.api.default_estados', $permitidos);
    }

    private function limite(Request $request): int
    {
        $default = (int) config('comercial.api.default_limit', 500);

        return max(1, min((int) $request->query('limit', $default), 2000));
    }

    private function claveTarifa(Cotizacion $cotizacion): string
    {
        return implode('|', [
            $cotizacion->cliente_id,
            $cotizacion->centro_costo_id,
            $cotizacion->modalidad_id,
            mb_strtolower(trim((string) $cotizacion->cargo), 'UTF-8'),
        ]);
    }

    private function filaTarifa(Cotizacion $cotizacion): array
    {
        $calculo = $cotizacion->datos_calculo ?? [];
        $resumen = $calculo['resumen_excel'] ?? [];
        $horas = $calculo['horas'] ?? [];

        return [
            'cotizacion_id' => $cotizacion->id,
            'cotizacion_numero' => $cotizacion->numero,
            'estado' => $cotizacion->estado,
            'version' => $cotizacion->version,
            'titulo' => $cotizacion->titulo,
            'cargo' => $cotizacion->cargo,
            'modalidad' => $cotizacion->modalidad?->codigo,
            'modalidad_nombre' => $cotizacion->modalidad?->nombre,
            'cliente_id' => $cotizacion->cliente_id,
            'cliente_rut' => $cotizacion->cliente?->rut,
            'cliente_nombre' => $cotizacion->cliente?->nombre,
            'cliente_nombre_comercial' => $cotizacion->cliente?->nombre_comercial,
            'centro_costo_id' => $cotizacion->centro_costo_id,
            'centro_costo_codigo' => $cotizacion->centroCosto?->codigo,
            'centro_costo_nombre' => $cotizacion->centroCosto?->nombre,
            'fecha_cotizacion' => optional($cotizacion->fecha_cotizacion)->toDateString(),
            'fecha_aprobacion' => optional($cotizacion->fecha_aprobacion)->toIso8601String(),
            'fecha_vigencia' => optional($cotizacion->fecha_vigencia)->toIso8601String(),
            'fecha_vigencia_desde' => optional($cotizacion->fecha_vigencia_desde)->toDateString(),
            'fecha_vigencia_hasta' => optional($cotizacion->fecha_vigencia_hasta)->toDateString(),
            'moneda' => 'CLP',
            'sueldo_base' => $this->detalleValor($cotizacion, 'Sueldo Base'),
            'bono_asistencia' => $this->detalleValor($cotizacion, 'Bono Asistencia'),
            'bono_compromiso' => $this->detalleValor($cotizacion, 'Bono Compromiso'),
            'otros_haberes' => $this->detalleValor($cotizacion, 'Otros Haberes'),
            'gratificacion' => $this->numero($resumen['gratificacion'] ?? null),
            'total_imponible' => $this->numero($resumen['totalImponible'] ?? null),
            'total_no_imponible' => $this->numero($resumen['totalNoImponible'] ?? null),
            'total_haberes' => (float) $cotizacion->total_remuneraciones,
            'imposiciones' => $this->numero($resumen['imposiciones'] ?? null),
            'alcance_liquido' => $this->numero($resumen['alcanceLiquido'] ?? null),
            'renta_tributable' => $this->numero($resumen['rentaTributable'] ?? null),
            'impuesto_unico' => $this->numero($resumen['impuestoUnico'] ?? null),
            'refprev' => $this->numero($resumen['refprev'] ?? null),
            'sis' => $this->numero($resumen['sis'] ?? null),
            'mutual' => $this->numero($resumen['mutual'] ?? null),
            'seguro_cesantia' => $this->numero($resumen['seguroCesantia'] ?? null),
            'total_cotizaciones' => (float) $cotizacion->total_cotizaciones,
            'provision_vacaciones' => $this->numero($resumen['provisionVacaciones'] ?? null),
            'provision_indemnizaciones' => $this->numero($resumen['provisionIndemnizaciones'] ?? 0),
            'total_provisiones' => (float) $cotizacion->total_provisiones,
            'uniformes_total' => $this->uniformesTotal($cotizacion),
            'servicios_casino' => $this->detalleValor($cotizacion, 'Servicios de Casino'),
            'seguro_accidentes_personales' => $this->detalleValor($cotizacion, 'Seguro Accidentes Personales'),
            'otros_gastos' => $this->detalleValor($cotizacion, 'Otros Gastos'),
            'otros_beneficios' => $this->detalleValor($cotizacion, 'Otros Beneficios'),
            'gastos_administracion' => $this->numero($resumen['gastosAdministracion'] ?? null),
            'total_gastos' => (float) $cotizacion->total_gastos,
            'costo_bruto' => (float) $cotizacion->subtotal,
            'margen_porcentaje' => $this->numero($calculo['margen_porcentaje'] ?? null),
            'margen' => (float) $cotizacion->margen,
            'precio_venta' => (float) $cotizacion->precio_venta,
            'hora_normal' => $this->numero($horas['normal'] ?? $resumen['horaNormal'] ?? null),
            'hora_normal_hhee' => $this->numero($horas['normal_hhee'] ?? $resumen['horaNormalHhee'] ?? null),
            'hora_extra_50' => $this->numero($horas['extra_50'] ?? $resumen['horaExtra50'] ?? null),
            'hora_extra_100' => $this->numero($horas['extra_100'] ?? $resumen['horaExtra100'] ?? null),
            'jornada_semanal_hhee' => $this->numero($horas['jornada_semanal_hhee'] ?? $resumen['jornadaSemanalHhee'] ?? null),
            'factor_normal_hhee' => $this->numero($horas['factor_normal_hhee'] ?? $resumen['horaNormalFactorHhee'] ?? null),
            'actualizado_en' => optional($cotizacion->updated_at)->toIso8601String(),
        ];
    }

    private function detalleValor(Cotizacion $cotizacion, string $concepto): float
    {
        return (float) optional(
            $cotizacion->detalles->first(fn ($detalle) => $detalle->concepto === $concepto)
        )->valor;
    }

    private function uniformesTotal(Cotizacion $cotizacion): float
    {
        if ($cotizacion->relationLoaded('uniformes')) {
            return (float) $cotizacion->uniformes->sum('total');
        }

        return (float) $cotizacion->uniformes()->sum('total');
    }

    private function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? round((float) $valor, 6) : null;
    }

    private function respuestaCsv(array $filas, string $nombreArchivo): StreamedResponse
    {
        $headers = $filas ? array_keys($filas[0]) : self::COLUMNAS_TARIFA;

        return response()->streamDownload(function () use ($filas, $headers) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $headers, ';');

            foreach ($filas as $fila) {
                fputcsv($salida, array_map(fn ($header) => $fila[$header] ?? null, $headers), ';');
            }

            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
