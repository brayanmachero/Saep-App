<?php

namespace App\Console\Commands;

use App\Models\InventarioIngreso;
use App\Models\InventarioMovimiento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ReiniciarTrazabilidadOperativaInventario extends Command
{
    protected $signature = 'inventario:reiniciar-trazabilidad
        {--fecha= : Fecha de inicio operativo (AAAA-MM-DD)}
        {--importado-desde= : Marca inicial exacta de la nómina que será el saldo inicial (AAAA-MM-DD HH:MM:SS)}
        {--importado-hasta= : Marca final exacta de la nómina que será el saldo inicial (AAAA-MM-DD HH:MM:SS)}
        {--aplicar : Ejecuta el saneamiento. Sin esta opción sólo informa el alcance.}';

    protected $description = 'Reemplaza el historial anterior por una nómina inicial y conserva los movimientos posteriores al corte.';

    public function handle(): int
    {
        [$startAt, $importFrom, $importTo] = $this->dates();
        $targetMovements = $this->targetMovements($startAt, $importFrom, $importTo)->get();
        $importMovements = $targetMovements->filter(fn ($movement) => $movement->origen === 'IMPORTACION_CATALOGO'
            && Carbon::parse($movement->created_at)->betweenIncluded($importFrom, $importTo));
        $legacyMovements = $targetMovements->filter(fn ($movement) => Carbon::parse($movement->ocurrido_en)->lt($startAt));
        $legacyReceiptIds = DB::table('inventario_ingresos')
            ->where('created_at', '<', $startAt)
            ->pluck('id');
        $baseRows = $this->baseRows($startAt, $importFrom, $importTo);
        $negativeBaseRows = $baseRows->filter(fn (object $row) => (float) $row->cantidad < 0);
        $currentKizeoBeforeImport = InventarioMovimiento::query()
            ->where('origen', 'KIZEO_EPP')
            ->where('ocurrido_en', '>=', $startAt)
            ->where('created_at', '<', $importFrom)
            ->count();

        if ($targetMovements->isEmpty() || $importMovements->isEmpty() || $baseRows->isEmpty()) {
            $this->error('No se encontró una nómina de importación y un historial previo compatibles con el corte indicado. No se hizo ningún cambio.');

            return self::FAILURE;
        }
        if ($negativeBaseRows->isNotEmpty() || $currentKizeoBeforeImport > 0) {
            $this->error('La validación de saldos no es segura: existen saldos base negativos o descuentos Kizeo anteriores a la nómina. No se hizo ningún cambio.');

            return self::FAILURE;
        }

        $summary = [
            ['Movimientos anteriores', $legacyMovements->count()],
            ['Movimientos de nómina a reemplazar', $importMovements->count()],
            ['Ingresos anteriores a retirar', $legacyReceiptIds->count()],
            ['Saldos iniciales oficiales', $baseRows->where('cantidad', '>', 0)->count()],
            ['Unidades de la nómina', $baseRows->sum('cantidad')],
            ['Descuentos Kizeo protegidos', InventarioMovimiento::query()->where('origen', 'KIZEO_EPP')->where('ocurrido_en', '>=', $startAt)->count()],
        ];
        $this->table(['Concepto', 'Cantidad'], $summary);

        if (! $this->option('aplicar')) {
            $this->info('Validación completada. Ejecuta nuevamente con --aplicar para confirmar el saneamiento.');

            return self::SUCCESS;
        }

        $documentNumber = 'NOMINA-STOCK-'.$startAt->format('Ymd');
        if (InventarioMovimiento::query()->where('documento_numero', $documentNumber)->exists()) {
            $this->error("Ya existe una nómina inicial oficial para {$startAt->toDateString()}. No se hizo ningún cambio.");

            return self::FAILURE;
        }

        $beforeBalances = $this->balanceSignature();
        $now = now();
        $backupPath = 'backups/inventario-reinicio-'.$startAt->format('Ymd-His').'.json';
        $backup = [
            'generado_en' => $now->toDateTimeString(),
            'corte' => $startAt->toDateTimeString(),
            'nomina_importada_desde' => $importFrom->toDateTimeString(),
            'nomina_importada_hasta' => $importTo->toDateTimeString(),
            'movimientos_reemplazados' => $targetMovements,
            'ingresos_historicos' => DB::table('inventario_ingresos')->whereIn('id', $legacyReceiptIds)->get(),
            'lineas_ingresos_historicos' => DB::table('inventario_ingreso_items')->whereIn('ingreso_id', $legacyReceiptIds)->get(),
            'costos_historicos' => DB::table('inventario_historial_costos')
                ->where('referencia_tipo', InventarioIngreso::class)
                ->whereIn('referencia_id', $legacyReceiptIds)
                ->get(),
            'saldos_iniciales_nuevos' => $baseRows,
        ];
        Storage::disk('local')->put($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        try {
            DB::transaction(function () use ($targetMovements, $legacyReceiptIds, $baseRows, $startAt, $documentNumber, $now, $beforeBalances): void {
                $movementIds = $targetMovements->pluck('id');
                $actor = $targetMovements
                    ->firstWhere('origen', 'IMPORTACION_CATALOGO');

                DB::table('inventario_importacion_movimientos')->whereIn('movimiento_id', $movementIds)->delete();
                DB::table('inventario_movimientos')->whereIn('id', $movementIds)->delete();
                DB::table('inventario_historial_costos')
                    ->where('referencia_tipo', InventarioIngreso::class)
                    ->whereIn('referencia_id', $legacyReceiptIds)
                    ->delete();
                DB::table('inventario_ingresos')->whereIn('id', $legacyReceiptIds)->delete();

                $initialRows = $baseRows
                    ->filter(fn (object $row) => (float) $row->cantidad > 0)
                    ->values()
                    ->map(fn (object $row, int $index) => [
                        'codigo' => 'INI-'.$startAt->format('Ymd').'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'tipo' => 'STOCK_INICIAL',
                        'origen' => 'NOMINA_INICIAL',
                        'ubicacion_id' => $row->ubicacion_id,
                        'producto_id' => $row->producto_id,
                        'variante_id' => $row->variante_id,
                        'cantidad' => $row->cantidad,
                        'costo_unitario' => null,
                        'grupo_traslado' => null,
                        'referencia_tipo' => null,
                        'referencia_id' => null,
                        'documento_tipo' => 'NOMINA_STOCK_INICIAL',
                        'documento_numero' => $documentNumber,
                        'destinatario_nombre' => null,
                        'destinatario_rut' => null,
                        'centro_costo' => null,
                        'observacion' => 'Saldo inicial oficial desde la nómina actualizada. El historial operativo comienza en este corte.',
                        'ocurrido_en' => $startAt,
                        'registrado_por' => $actor?->registrado_por,
                        'registrado_por_nombre' => $actor?->registrado_por_nombre ?: 'Reinicio operativo de inventario',
                        'reverso_de_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();
                DB::table('inventario_movimientos')->insert($initialRows);

                $todayReceipts = DB::table('inventario_ingresos')
                    ->whereDate('created_at', $startAt->toDateString())
                    ->get(['id', 'created_at']);
                foreach ($todayReceipts as $receipt) {
                    DB::table('inventario_movimientos')
                        ->where('referencia_tipo', InventarioIngreso::class)
                        ->where('referencia_id', $receipt->id)
                        ->where('tipo', 'INGRESO_COMPRA')
                        ->update(['ocurrido_en' => $receipt->created_at, 'updated_at' => $now]);
                }

                DB::table('configuraciones')->updateOrInsert(
                    ['clave' => 'inventario_resumen_trazabilidad_desde'],
                    [
                        'valor' => $startAt->toDateString(),
                        'tipo' => 'TEXT',
                        'categoria' => 'inventario',
                        'descripcion' => 'Inicio oficial del kardex operativo de Inventario.',
                        'editable' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                if ($beforeBalances !== $this->balanceSignature()) {
                    throw new RuntimeException('El saneamiento no conservó el saldo actual.');
                }
            });
        } catch (\Throwable $exception) {
            $this->error('La transacción fue revertida. El respaldo se conserva en storage/app/'.$backupPath.'.');
            throw $exception;
        }

        $this->info("Saneamiento completado. Respaldo privado: storage/app/{$backupPath}");

        return self::SUCCESS;
    }

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon} */
    private function dates(): array
    {
        $start = $this->dateOption('fecha', 'Y-m-d')->startOfDay();
        $importFrom = $this->dateOption('importado-desde', 'Y-m-d H:i:s');
        $importTo = $this->dateOption('importado-hasta', 'Y-m-d H:i:s');
        if ($importFrom->gt($importTo) || $importFrom->lt($start)) {
            throw new RuntimeException('El rango de la nómina importada no es válido para el corte indicado.');
        }

        return [$start, $importFrom, $importTo];
    }

    private function dateOption(string $option, string $format): Carbon
    {
        $value = trim((string) $this->option($option));
        if ($value === '') {
            throw new RuntimeException("Debes indicar --{$option} con formato {$format}.");
        }

        return Carbon::createFromFormat($format, $value);
    }

    private function targetMovements(Carbon $startAt, Carbon $importFrom, Carbon $importTo)
    {
        return DB::table('inventario_movimientos')
            ->where(function ($query) use ($startAt, $importFrom, $importTo): void {
                $query->where('ocurrido_en', '<', $startAt)
                    ->orWhere(function ($imports) use ($importFrom, $importTo): void {
                        $imports->where('origen', 'IMPORTACION_CATALOGO')
                            ->whereBetween('created_at', [$importFrom, $importTo]);
                    });
            });
    }

    private function baseRows(Carbon $startAt, Carbon $importFrom, Carbon $importTo)
    {
        return $this->targetMovements($startAt, $importFrom, $importTo)
            ->selectRaw('ubicacion_id, producto_id, variante_id, SUM(cantidad) as cantidad')
            ->groupBy('ubicacion_id', 'producto_id', 'variante_id')
            ->orderBy('ubicacion_id')
            ->orderBy('producto_id')
            ->orderBy('variante_id')
            ->get();
    }

    /** @return array<string, string> */
    private function balanceSignature(): array
    {
        return DB::table('inventario_movimientos')
            ->selectRaw('ubicacion_id, variante_id, SUM(cantidad) as cantidad')
            ->groupBy('ubicacion_id', 'variante_id')
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->ubicacion_id.'-'.$row->variante_id => number_format((float) $row->cantidad, 3, '.', '')])
            ->sortKeys()
            ->all();
    }
}
