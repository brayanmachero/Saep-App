<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Models\Configuracion;
use App\Modules\Comercial\Models\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarifaApiController
{
    public function index(Request $request)
    {
        $this->autorizar($request);

        $validator = Validator::make($request->query(), [
            'cliente_id' => ['nullable', 'integer'],
            'cliente' => ['nullable', 'string', 'max:180'],
            'centro' => ['nullable', 'string', 'max:180'],
            'modalidad' => ['nullable', 'in:EST,SUB'],
            'cargo' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', 'string', 'max:80'],
            'formato' => ['nullable', 'in:json,csv'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filtros inválidos para la consulta de tarifas.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $estados = $this->estadosSolicitados($validated['estado'] ?? null);
        $limit = (int) ($validated['limit'] ?? 1000);

        $query = Cotizacion::with(['cliente', 'centroCosto', 'modalidad'])
            ->whereIn('estado', $estados);

        if (! empty($validated['cliente_id'])) {
            $query->where('cliente_id', $validated['cliente_id']);
        }

        if (! empty($validated['cliente'])) {
            $cliente = $validated['cliente'];
            $query->whereHas('cliente', function ($subQuery) use ($cliente) {
                $subQuery->where('nombre', 'like', "%{$cliente}%")
                    ->orWhere('nombre_comercial', 'like', "%{$cliente}%");
            });
        }

        if (! empty($validated['centro'])) {
            $centro = $validated['centro'];
            $query->whereHas('centroCosto', fn ($subQuery) => $subQuery->where('nombre', 'like', "%{$centro}%"));
        }

        if (! empty($validated['modalidad'])) {
            $query->whereHas('modalidad', fn ($subQuery) => $subQuery->where('codigo', $validated['modalidad']));
        }

        if (! empty($validated['cargo'])) {
            $query->where('cargo', 'like', "%{$validated['cargo']}%");
        }

        $cotizaciones = $query
            ->orderByRaw('COALESCE(fecha_vigencia, fecha_aprobacion, fecha_cotizacion, created_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->unique(fn (Cotizacion $cotizacion) => implode('|', [
                $cotizacion->cliente_id,
                $cotizacion->centro_costo_id,
                $cotizacion->modalidad_id,
                mb_strtolower((string) $cotizacion->cargo),
            ]))
            ->values();

        $rows = $cotizaciones->map(fn (Cotizacion $cotizacion) => $this->fila($cotizacion))->all();

        if (($validated['formato'] ?? 'json') === 'csv') {
            return $this->csv($rows);
        }

        return response()->json([
            'success' => true,
            'generated_at' => now()->toIso8601String(),
            'count' => count($rows),
            'data' => $rows,
        ]);
    }

    private function autorizar(Request $request): void
    {
        $esperado = config('comercial.api_token')
            ?: env('COMERCIAL_API_TOKEN')
            ?: Configuracion::get('comercial_api_token');

        if (! $esperado) {
            abort(503, 'Token API comercial no configurado.');
        }

        $recibido = $request->bearerToken() ?: $request->query('token');

        if (! $recibido || ! hash_equals((string) $esperado, (string) $recibido)) {
            abort(401, 'Token API comercial inválido.');
        }
    }

    private function estadosSolicitados(?string $estado): array
    {
        if (! $estado) {
            return ['vigente'];
        }

        return collect(explode(',', $estado))
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => in_array($item, ['aprobada', 'vigente'], true))
            ->values()
            ->all() ?: ['vigente'];
    }

    private function fila(Cotizacion $cotizacion): array
    {
        $horas = $cotizacion->datos_calculo['horas'] ?? [];
        $resumen = $cotizacion->datos_calculo['resumen_excel'] ?? [];

        return [
            'numero' => $cotizacion->numero,
            'estado' => $cotizacion->estado,
            'cliente_id' => $cotizacion->cliente_id,
            'cliente' => $cotizacion->cliente?->nombre_comercial ?: $cotizacion->cliente?->nombre,
            'centro_costo_id' => $cotizacion->centro_costo_id,
            'centro_costo' => $cotizacion->centroCosto?->nombre,
            'modalidad' => $cotizacion->modalidad?->codigo,
            'cargo' => $cotizacion->cargo,
            'precio_venta' => (float) $cotizacion->precio_venta,
            'total_haberes' => (float) $cotizacion->total_remuneraciones,
            'total_cotizaciones' => (float) $cotizacion->total_cotizaciones,
            'total_provisiones' => (float) $cotizacion->total_provisiones,
            'total_gastos' => (float) $cotizacion->total_gastos,
            'margen' => (float) $cotizacion->margen,
            'margen_porcentaje' => (float) ($cotizacion->datos_calculo['margen_porcentaje'] ?? 0),
            'hora_normal' => (float) ($horas['normal'] ?? ($resumen['horaNormal'] ?? 0)),
            'hora_normal_hhee' => (float) ($horas['normal_hhee'] ?? ($resumen['horaNormalHhee'] ?? 0)),
            'hora_extra_50' => (float) ($horas['extra_50'] ?? ($resumen['horaExtra50'] ?? 0)),
            'hora_extra_100' => (float) ($horas['extra_100'] ?? ($resumen['horaExtra100'] ?? 0)),
            'fecha_cotizacion' => optional($cotizacion->fecha_cotizacion)->format('Y-m-d'),
            'fecha_aprobacion' => optional($cotizacion->fecha_aprobacion)->format('Y-m-d H:i:s'),
            'fecha_vigencia' => optional($cotizacion->fecha_vigencia)->format('Y-m-d H:i:s'),
            'fecha_vigencia_hasta' => optional($cotizacion->fecha_vigencia_hasta)->format('Y-m-d'),
        ];
    }

    private function csv(array $rows): StreamedResponse
    {
        $headers = array_keys($rows[0] ?? [
            'numero' => null,
            'estado' => null,
            'cliente' => null,
            'centro_costo' => null,
            'modalidad' => null,
            'cargo' => null,
            'precio_venta' => null,
        ]);

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($header) => $row[$header] ?? null, $headers), ';');
            }

            fclose($handle);
        }, 'tarifas-comerciales-saep.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
