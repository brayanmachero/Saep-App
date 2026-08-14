<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_variantes', function (Blueprint $table) {
            $table->decimal('costo_referencia', 14, 2)->nullable();
        });

        Schema::create('inventario_historial_costos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('inventario_variantes')->restrictOnDelete();
            $table->decimal('costo_unitario', 14, 2);
            $table->string('origen', 60);
            $table->string('referencia_tipo', 160)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->timestamp('vigente_desde');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['variante_id', 'vigente_desde']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });

        // Existing purchases already carry their cost in the kardex. Preserve that
        // financial history instead of asking Bodega to re-enter it after deployment.
        if (! Schema::hasTable('inventario_movimientos')) {
            return;
        }

        $lastCostByVariant = [];
        DB::table('inventario_movimientos')
            ->whereNotNull('costo_unitario')
            ->where('costo_unitario', '>', 0)
            ->orderBy('variante_id')
            ->orderBy('ocurrido_en')
            ->orderBy('id')
            ->each(function (object $movement) use (&$lastCostByVariant) {
                $variantId = (int) $movement->variante_id;
                $cost = round((float) $movement->costo_unitario, 2);

                if (isset($lastCostByVariant[$variantId]) && abs($lastCostByVariant[$variantId] - $cost) < 0.005) {
                    return;
                }

                $effectiveAt = $movement->ocurrido_en ?: now();
                DB::table('inventario_historial_costos')->insert([
                    'variante_id' => $variantId,
                    'costo_unitario' => $cost,
                    'origen' => $movement->origen ?: 'HISTORICO_KARDEX',
                    'referencia_tipo' => $movement->referencia_tipo,
                    'referencia_id' => $movement->referencia_id,
                    'vigente_desde' => $effectiveAt,
                    'registrado_por' => $movement->registrado_por,
                    'created_at' => $effectiveAt,
                    'updated_at' => $effectiveAt,
                ]);
                DB::table('inventario_variantes')
                    ->where('id', $variantId)
                    ->update(['costo_referencia' => $cost]);
                $lastCostByVariant[$variantId] = $cost;
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_historial_costos');

        Schema::table('inventario_variantes', function (Blueprint $table) {
            $table->dropColumn('costo_referencia');
        });
    }
};
