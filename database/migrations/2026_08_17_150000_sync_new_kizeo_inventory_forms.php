<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas_bodega', function (Blueprint $table) {
            $table->string('kizeo_form_id', 50)->nullable()->after('kizeo_data_id');
            $table->string('origen_formulario', 120)->nullable()->after('kizeo_form_id');
            $table->string('tipo_operacion', 120)->nullable()->after('origen_formulario');
            $table->string('flujo_inventario', 20)->default('SALIDA')->after('tipo_operacion');
        });

        // Los registros históricos pertenecen al formulario que estaba configurado antes.
        DB::table('entregas_bodega')
            ->whereNull('kizeo_form_id')
            ->update([
                'kizeo_form_id' => '947762',
                'origen_formulario' => 'Control de Entrega Bodega',
                'flujo_inventario' => 'SALIDA',
            ]);

        Schema::table('entregas_bodega', function (Blueprint $table) {
            $table->dropUnique(['kizeo_data_id']);
            $table->unique(['kizeo_form_id', 'kizeo_data_id'], 'entregas_bodega_kizeo_form_data_unique');
            $table->index(['flujo_inventario', 'fecha_pedido']);
        });
    }

    public function down(): void
    {
        Schema::table('entregas_bodega', function (Blueprint $table) {
            $table->dropIndex(['flujo_inventario', 'fecha_pedido']);
            $table->dropUnique('entregas_bodega_kizeo_form_data_unique');
            $table->unique('kizeo_data_id');
            $table->dropColumn([
                'kizeo_form_id',
                'origen_formulario',
                'tipo_operacion',
                'flujo_inventario',
            ]);
        });
    }
};
