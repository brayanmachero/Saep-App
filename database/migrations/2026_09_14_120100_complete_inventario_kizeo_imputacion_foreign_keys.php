<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario_kizeo_imputacion_historial')) {
            return;
        }

        $foreignColumns = [];
        foreach (Schema::getForeignKeys('inventario_kizeo_imputacion_historial') as $foreignKey) {
            $foreignColumns = [...$foreignColumns, ...$foreignKey['columns']];
        }

        // Se agregan por separado para completar de forma segura un intento de
        // creación anterior que MySQL pudo interrumpir por nombres muy largos.
        if (! in_array('centro_costo_anterior_id', $foreignColumns, true)) {
            Schema::table('inventario_kizeo_imputacion_historial', function (Blueprint $table) {
                $table->foreign('centro_costo_anterior_id', 'inv_kiz_imp_old_cc_fk')
                    ->references('id')
                    ->on('inventario_centros_costo')
                    ->nullOnDelete();
            });
        }
        if (! in_array('centro_costo_nuevo_id', $foreignColumns, true)) {
            Schema::table('inventario_kizeo_imputacion_historial', function (Blueprint $table) {
                $table->foreign('centro_costo_nuevo_id', 'inv_kiz_imp_new_cc_fk')
                    ->references('id')
                    ->on('inventario_centros_costo')
                    ->nullOnDelete();
            });
        }
        if (! in_array('registrado_por', $foreignColumns, true)) {
            Schema::table('inventario_kizeo_imputacion_historial', function (Blueprint $table) {
                $table->foreign('registrado_por', 'inv_kiz_imp_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventario_kizeo_imputacion_historial')) {
            return;
        }

        Schema::table('inventario_kizeo_imputacion_historial', function (Blueprint $table) {
            $table->dropForeign('inv_kiz_imp_old_cc_fk');
            $table->dropForeign('inv_kiz_imp_new_cc_fk');
            $table->dropForeign('inv_kiz_imp_user_fk');
        });
    }
};
