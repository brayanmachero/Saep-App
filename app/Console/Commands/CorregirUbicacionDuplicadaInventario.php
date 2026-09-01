<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CorregirUbicacionDuplicadaInventario extends Command
{
    protected $signature = 'inventario:eliminar-stock-inicial-ubicacion-erronea
        {--origen=2 : ID de la ubicación errónea}
        {--destino=1 : ID de la ubicación operativa}
        {--documento-consolidacion=CONSOL-UBIC-20260831 : Documento de los traslados técnicos asociados}
        {--aplicar : Ejecuta la corrección. Sin esta opción solo valida e informa el alcance.}';

    protected $description = 'Elimina únicamente saldos iniciales creados por error y ajusta sus traslados técnicos asociados.';

    public function handle(): int
    {
        $sourceId = (int) $this->option('origen');
        $targetId = (int) $this->option('destino');
        $consolidationDocument = trim((string) $this->option('documento-consolidacion'));

        if ($sourceId === $targetId || $sourceId < 1 || $targetId < 1 || $consolidationDocument === '') {
            $this->error('Las ubicaciones y el documento de consolidación no son válidos. No se hizo ningún cambio.');

            return self::FAILURE;
        }

        $scope = $this->scope($sourceId, $targetId, $consolidationDocument);
        $this->table(['Concepto', 'Cantidad'], [
            ['Saldos iniciales erróneos a eliminar', $scope['initials']->count()],
            ['Unidades iniciales erróneas', $this->number($scope['initial_total'])],
            ['Traslados técnicos a ajustar', $scope['pairs']->count() * 2],
            ['Pares que quedarán en cero y se retirarán', $scope['pairs']->filter(fn (array $pair) => $this->same(abs((float) $pair['out']->cantidad), (float) $pair['initial']->cantidad))->count()],
            ['Saldo total antes', $this->number($scope['before_total'])],
            ['Saldo total esperado después', $this->number($scope['before_total'] - $scope['initial_total'])],
            ['Saldo de Sede Central antes', $this->number($scope['before_target_total'])],
            ['Saldo de Sede Central esperado después', $this->number($scope['before_target_total'] - $scope['initial_total'])],
        ]);

        if (! $this->option('aplicar')) {
            $this->info('Validación correcta. Ejecuta nuevamente con --aplicar para eliminar solo estos saldos iniciales.');

            return self::SUCCESS;
        }

        $now = now();
        $backupPath = 'backups/inventario-correccion-stock-inicial-ubicacion-erronea-'.$now->format('Ymd-His').'.json';
        $afterTotal = null;
        $afterTargetTotal = null;
        $afterSourceTotal = null;

        DB::transaction(function () use ($scope, $sourceId, $targetId, $now, $backupPath, &$afterTotal, &$afterTargetTotal, &$afterSourceTotal): void {
            Storage::disk('local')->put($backupPath, json_encode([
                'generado_en' => $now->toDateTimeString(),
                'motivo' => 'Se eliminan únicamente saldos iniciales creados en una ubicación errónea; los ingresos y demás movimientos se conservan.',
                'ubicacion_origen_id' => $sourceId,
                'ubicacion_destino_id' => $targetId,
                'saldos_iniciales_eliminados' => $scope['initials']->values(),
                'traslados_tecnicos_antes' => $scope['pairs']->map(fn (array $pair) => [
                    'salida' => $pair['out'],
                    'entrada' => $pair['in'],
                ])->values(),
                'saldo_total_antes' => $scope['before_total'],
                'saldo_destino_antes' => $scope['before_target_total'],
                'unidades_eliminadas' => $scope['initial_total'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            foreach ($scope['pairs'] as $pair) {
                $initialQuantity = (float) $pair['initial']->cantidad;
                $newOutQuantity = (float) $pair['out']->cantidad + $initialQuantity;
                $newInQuantity = (float) $pair['in']->cantidad - $initialQuantity;

                if ($this->same($newOutQuantity, 0) && $this->same($newInQuantity, 0)) {
                    DB::table('inventario_movimientos')
                        ->whereIn('id', [$pair['out']->id, $pair['in']->id])
                        ->delete();

                    continue;
                }

                DB::table('inventario_movimientos')
                    ->where('id', $pair['out']->id)
                    ->update(['cantidad' => $newOutQuantity, 'updated_at' => $now]);
                DB::table('inventario_movimientos')
                    ->where('id', $pair['in']->id)
                    ->update(['cantidad' => $newInQuantity, 'updated_at' => $now]);
            }

            DB::table('inventario_movimientos')
                ->whereIn('id', $scope['initials']->pluck('id'))
                ->delete();

            $afterTotal = (float) DB::table('inventario_movimientos')->sum('cantidad');
            $afterTargetTotal = (float) DB::table('inventario_movimientos')->where('ubicacion_id', $targetId)->sum('cantidad');
            $afterSourceTotal = (float) DB::table('inventario_movimientos')->where('ubicacion_id', $sourceId)->sum('cantidad');

            if (! $this->same($scope['before_total'] - $scope['initial_total'], $afterTotal)
                || ! $this->same($scope['before_target_total'] - $scope['initial_total'], $afterTargetTotal)
                || ! $this->same($afterSourceTotal, 0)) {
                throw new RuntimeException('La corrección no pasó la validación final.');
            }
        });

        $this->info('Corrección aplicada. Se eliminaron solo los saldos iniciales erróneos y sus unidades dejaron de influir en Sede Central.');
        $this->line('Respaldo: '.$backupPath);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function scope(int $sourceId, int $targetId, string $consolidationDocument): array
    {
        if (! DB::table('inventario_ubicaciones')->where('id', $sourceId)->exists()
            || ! DB::table('inventario_ubicaciones')->where('id', $targetId)->exists()) {
            throw new RuntimeException('No se encontró una de las ubicaciones indicadas.');
        }

        $initials = DB::table('inventario_movimientos')
            ->where('ubicacion_id', $sourceId)
            ->where('tipo', 'STOCK_INICIAL')
            ->where('origen', 'NOMINA_INICIAL')
            ->orderBy('id')
            ->get();

        if ($initials->isEmpty()) {
            throw new RuntimeException('No se encontraron saldos iniciales erróneos en la ubicación indicada.');
        }

        $pairs = collect();
        foreach ($initials as $initial) {
            $out = DB::table('inventario_movimientos')
                ->where('ubicacion_id', $sourceId)
                ->where('producto_id', $initial->producto_id)
                ->where('variante_id', $initial->variante_id)
                ->where('tipo', 'TRASLADO_SALIDA')
                ->where('documento_numero', $consolidationDocument)
                ->first();

            if (! $out || ! $out->grupo_traslado) {
                throw new RuntimeException('Falta el traslado técnico de salida para uno de los saldos iniciales. No se hizo ningún cambio.');
            }

            $in = DB::table('inventario_movimientos')
                ->where('ubicacion_id', $targetId)
                ->where('tipo', 'TRASLADO_ENTRADA')
                ->where('grupo_traslado', $out->grupo_traslado)
                ->first();

            if (! $in
                || (int) $in->producto_id !== (int) $initial->producto_id
                || (int) $in->variante_id !== (int) $initial->variante_id
                || (float) $out->cantidad >= 0
                || ! $this->same($out->cantidad + $in->cantidad, 0)
                || abs((float) $out->cantidad) + 0.0005 < (float) $initial->cantidad) {
                throw new RuntimeException('El traslado técnico asociado a un saldo inicial no es consistente. No se hizo ningún cambio.');
            }

            $pairs->push(compact('initial', 'out', 'in'));
        }

        return [
            'initials' => $initials,
            'pairs' => $pairs,
            'initial_total' => (float) $initials->sum('cantidad'),
            'before_total' => (float) DB::table('inventario_movimientos')->sum('cantidad'),
            'before_target_total' => (float) DB::table('inventario_movimientos')->where('ubicacion_id', $targetId)->sum('cantidad'),
        ];
    }

    private function same(float|int|string $left, float|int|string $right): bool
    {
        return abs((float) $left - (float) $right) < 0.0005;
    }

    private function number(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}
