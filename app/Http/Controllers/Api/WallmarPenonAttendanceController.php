<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TalanaContrato;
use App\Models\TalanaMarca;
use App\Support\TalanaMarcaDirection;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WallmarPenonAttendanceController extends Controller
{
    /**
     * Devuelve únicamente marcas de asistencia previamente sincronizadas.
     *
     * No hay llamadas a Talana ni operaciones de escritura en esta ruta. El
     * centro de costo se fija en configuración y no puede ser alterado por el
     * consumidor de la API.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = config('services.wallmar_attendance');
        $maximumPageSize = max(1, (int) ($settings['max_page_size'] ?? 100));

        $input = Validator::make($request->query(), [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:'.$maximumPageSize],
        ], [
            'desde.required' => 'El parámetro desde es obligatorio.',
            'hasta.required' => 'El parámetro hasta es obligatorio.',
        ])->validate();

        $from = CarbonImmutable::createFromFormat('Y-m-d', $input['desde'], 'America/Santiago')->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $input['hasta'], 'America/Santiago')->startOfDay();
        $minimumDate = CarbonImmutable::parse($settings['minimum_date'], 'America/Santiago')->startOfDay();
        $maximumDays = max(1, (int) ($settings['max_days_per_request'] ?? 31));

        if ($to->lessThan($from)) {
            throw ValidationException::withMessages([
                'hasta' => 'La fecha hasta debe ser igual o posterior a desde.',
            ]);
        }

        if ($from->lessThan($minimumDate)) {
            throw ValidationException::withMessages([
                'desde' => 'La consulta está disponible desde '.$minimumDate->toDateString().'.',
            ]);
        }

        if ($from->diffInDays($to) + 1 > $maximumDays) {
            throw ValidationException::withMessages([
                'hasta' => "El rango máximo permitido es de {$maximumDays} días.",
            ]);
        }

        $centerCodes = array_values(array_filter($settings['center_codes'] ?? []));
        if ($centerCodes === []) {
            Log::error('Wallmar attendance API has no configured center codes.');

            return response()->json([
                'message' => 'El servicio de asistencia no está configurado correctamente.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $pageSize = (int) ($input['page_size'] ?? min(50, $maximumPageSize));
        $page = (int) ($input['page'] ?? 1);

        $query = TalanaMarca::query()
            ->select([
                'id', 'persona_talana_id', 'persona_rut', 'persona_nombre', 'fecha', 'hora', 'tipo',
                'centro_costo_nombre', 'raw_ts', 'synced_at',
            ])
            ->whereBetween('fecha', [$from->toDateString(), $to->toDateString()])
            // Son valores de configuración interna, no parámetros del cliente.
            // Se comparan de forma exacta para preservar correctamente Ñ y tildes.
            ->whereIn('centro_costo_nombre', $centerCodes)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id');

        $marks = $query->paginate($pageSize, ['*'], 'page', $page);
        $cargoActualPorPersona = $this->cargoActualPorPersona($marks->getCollection(), $centerCodes);
        $lastSyncedAt = $marks->getCollection()
            ->pluck('synced_at')
            ->filter()
            ->map(static fn ($date): string => $date->toIso8601String())
            ->sort()
            ->last();

        Log::info('Wallmar Peñón attendance data queried.', [
            'desde' => $from->toDateString(),
            'hasta' => $to->toDateString(),
            'page' => $page,
            'page_size' => $pageSize,
            'returned' => $marks->count(),
        ]);

        return response()->json([
            'data' => $marks->getCollection()->map(fn (TalanaMarca $mark): array => [
                'rut' => $mark->persona_rut,
                'nombre' => $mark->persona_nombre,
                'centro_costo' => $settings['center_label'],
                // El cargo corresponde al contrato vigente de Peñón. No se
                // presenta como histórico, pues una persona pudo cambiar de
                // función después de haber realizado una marca anterior.
                'cargo_actual' => $cargoActualPorPersona->get($mark->persona_talana_id),
                'fecha' => $mark->fecha?->toDateString(),
                'hora' => $mark->hora,
                'direccion' => TalanaMarcaDirection::label($mark->tipo),
            ])->values(),
            'meta' => [
                'pagina' => $marks->currentPage(),
                'por_pagina' => $marks->perPage(),
                'total_registros' => $marks->total(),
                'total_paginas' => $marks->lastPage(),
                'desde' => $from->toDateString(),
                'hasta' => $to->toDateString(),
                'centro_costo' => $settings['center_label'],
                'solo_lectura' => true,
                'fuente' => 'SAEP — sincronización Talana',
                'actualizado_en' => $lastSyncedAt,
            ],
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, TalanaMarca> $marks
     * @param array<int, string> $centerCodes
     * @return \Illuminate\Support\Collection<int, string|null>
     */
    private function cargoActualPorPersona($marks, array $centerCodes)
    {
        $personIds = $marks->pluck('persona_talana_id')->filter()->unique()->values();

        if ($personIds->isEmpty()) {
            return collect();
        }

        $contracts = TalanaContrato::query()
            ->whereIn('persona_talana_id', $personIds)
            ->where('finiquitado', 0)
            ->whereIn('centro_costo_nombre', $centerCodes)
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->get(['persona_talana_id', 'cargo_nombre']);

        return $contracts
            ->unique('persona_talana_id')
            ->pluck('cargo_nombre', 'persona_talana_id');
    }
}
