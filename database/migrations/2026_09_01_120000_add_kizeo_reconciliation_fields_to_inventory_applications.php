<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_entrega_kizeo_aplicaciones', function (Blueprint $table) {
            $table->timestamp('corregida_en')->nullable()->after('aplicada_en');
            $table->timestamp('fuente_corregida_en')->nullable()->after('corregida_en');
            $table->json('correccion_snapshot')->nullable()->after('motivo_reversion');
            $table->string('correccion_pendiente_motivo', 500)->nullable()->after('correccion_snapshot');
            $table->index('estado', 'inv_kizeo_aplicaciones_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_entrega_kizeo_aplicaciones', function (Blueprint $table) {
            $table->dropIndex('inv_kizeo_aplicaciones_estado_idx');
            $table->dropColumn([
                'corregida_en',
                'fuente_corregida_en',
                'correccion_snapshot',
                'correccion_pendiente_motivo',
            ]);
        });
    }
};
