<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descarga_contenedores', function (Blueprint $table) {
            if (!Schema::hasColumn('descarga_contenedores', 'tarifa_id')) {
                $table->foreignId('tarifa_id')
                    ->nullable()
                    ->after('fact_codigo')
                    ->constrained('descarga_contenedor_tarifas')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('descarga_contenedores', 'tarifa_cliente_snapshot')) {
                $table->string('tarifa_cliente_snapshot', 80)->nullable()->after('tarifa_id');
            }
            if (!Schema::hasColumn('descarga_contenedores', 'tarifa_proceso_snapshot')) {
                $table->string('tarifa_proceso_snapshot', 180)->nullable()->after('tarifa_cliente_snapshot');
            }
            if (!Schema::hasColumn('descarga_contenedores', 'costo_unitario_snapshot')) {
                $table->decimal('costo_unitario_snapshot', 12, 2)->nullable()->after('tarifa_proceso_snapshot');
            }
            if (!Schema::hasColumn('descarga_contenedores', 'pago_colaborador_snapshot')) {
                $table->decimal('pago_colaborador_snapshot', 12, 2)->nullable()->after('costo_unitario_snapshot');
            }
            if (!Schema::hasColumn('descarga_contenedores', 'requiere_revision_tarifa')) {
                $table->boolean('requiere_revision_tarifa')->default(false)->after('pago_colaborador_snapshot');
            }
        });

        Schema::table('descarga_contenedor_participantes', function (Blueprint $table) {
            if (!Schema::hasColumn('descarga_contenedor_participantes', 'porcentaje_participacion')) {
                $table->decimal('porcentaje_participacion', 6, 2)->nullable()->after('rol_en_descarga');
            }
        });

        $this->backfillTarifas();
        $this->backfillDistribucion();
    }

    public function down(): void
    {
        Schema::table('descarga_contenedor_participantes', function (Blueprint $table) {
            if (Schema::hasColumn('descarga_contenedor_participantes', 'porcentaje_participacion')) {
                $table->dropColumn('porcentaje_participacion');
            }
        });

        Schema::table('descarga_contenedores', function (Blueprint $table) {
            foreach ([
                'requiere_revision_tarifa',
                'pago_colaborador_snapshot',
                'costo_unitario_snapshot',
                'tarifa_proceso_snapshot',
                'tarifa_cliente_snapshot',
            ] as $column) {
                if (Schema::hasColumn('descarga_contenedores', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('descarga_contenedores', 'tarifa_id')) {
                $table->dropConstrainedForeignId('tarifa_id');
            }
        });
    }

    private function backfillTarifas(): void
    {
        if (!Schema::hasTable('descarga_contenedores') || !Schema::hasTable('descarga_contenedor_tarifas')) {
            return;
        }

        $tarifas = DB::table('descarga_contenedor_tarifas')
            ->where('activo', true)
            ->orderBy('cliente')
            ->orderBy('id')
            ->get()
            ->groupBy('codigo');

        DB::table('descarga_contenedores')
            ->whereNotNull('fact_codigo')
            ->orderBy('id')
            ->chunkById(200, function ($descargas) use ($tarifas) {
                foreach ($descargas as $descarga) {
                    $codigo = strtoupper(trim((string) $descarga->fact_codigo));
                    $tarifa = $tarifas->get($codigo)?->first();
                    if (!$tarifa) {
                        continue;
                    }

                    DB::table('descarga_contenedores')
                        ->where('id', $descarga->id)
                        ->update([
                            'tarifa_id' => $tarifa->id,
                            'tarifa_cliente_snapshot' => $tarifa->cliente,
                            'tarifa_proceso_snapshot' => $tarifa->proceso,
                            'costo_unitario_snapshot' => $tarifa->costo_unitario,
                            'pago_colaborador_snapshot' => $tarifa->pago_colaborador,
                            'requiere_revision_tarifa' => (bool) $tarifa->requiere_revision,
                        ]);
                }
            });
    }

    private function backfillDistribucion(): void
    {
        if (!Schema::hasTable('descarga_contenedores') || !Schema::hasTable('descarga_contenedor_participantes')) {
            return;
        }

        DB::table('descarga_contenedores')
            ->orderBy('id')
            ->chunkById(200, function ($descargas) {
                foreach ($descargas as $descarga) {
                    $participantes = DB::table('descarga_contenedor_participantes')
                        ->where('descarga_contenedor_id', $descarga->id)
                        ->orderBy('id')
                        ->get();

                    $count = $participantes->count();
                    if ($count === 0) {
                        continue;
                    }

                    $base = round(100 / $count, 2);
                    $assigned = 0.0;

                    foreach ($participantes as $index => $participante) {
                        $porcentaje = $index === $count - 1
                            ? round(100 - $assigned, 2)
                            : $base;
                        $assigned += $porcentaje;

                        $monto = null;
                        if ($descarga->pago_colaborador_snapshot !== null) {
                            $monto = round(((float) $descarga->pago_colaborador_snapshot) * $porcentaje / 100, 2);
                        }

                        DB::table('descarga_contenedor_participantes')
                            ->where('id', $participante->id)
                            ->update([
                                'porcentaje_participacion' => $porcentaje,
                                'monto_calculado' => $monto,
                            ]);
                    }
                }
            });
    }
};
