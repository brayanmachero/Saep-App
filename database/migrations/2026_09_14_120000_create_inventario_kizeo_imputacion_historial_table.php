<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario_kizeo_imputacion_historial')) {
            Schema::create('inventario_kizeo_imputacion_historial', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('aplicacion_id');
                $table->unsignedBigInteger('centro_costo_anterior_id')->nullable();
                $table->string('centro_costo_anterior', 220)->nullable();
                $table->unsignedBigInteger('centro_costo_nuevo_id')->nullable();
                $table->string('centro_costo_nuevo', 220);
                $table->unsignedBigInteger('registrado_por')->nullable();
                $table->string('registrado_por_nombre', 200)->nullable();
                $table->timestamps();

                $table->index(['aplicacion_id', 'created_at']);
            });
        }

        // Los nombres explícitos evitan superar el máximo de 64 caracteres de
        // MySQL y también permiten retomar con seguridad una creación parcial.
        $foreignColumns = [];
        foreach (Schema::getForeignKeys('inventario_kizeo_imputacion_historial') as $foreignKey) {
            $foreignColumns = [...$foreignColumns, ...$foreignKey['columns']];
        }

        Schema::table('inventario_kizeo_imputacion_historial', function (Blueprint $table) use ($foreignColumns) {
            if (! in_array('aplicacion_id', $foreignColumns, true)) {
                $table->foreign('aplicacion_id', 'inv_kiz_imp_app_fk')
                    ->references('id')
                    ->on('inventario_entrega_kizeo_aplicaciones')
                    ->cascadeOnDelete();
            }
            if (! in_array('centro_costo_anterior_id', $foreignColumns, true)) {
                $table->foreign('centro_costo_anterior_id', 'inv_kiz_imp_old_cc_fk')
                    ->references('id')
                    ->on('inventario_centros_costo')
                    ->nullOnDelete();
            }
            if (! in_array('centro_costo_nuevo_id', $foreignColumns, true)) {
                $table->foreign('centro_costo_nuevo_id', 'inv_kiz_imp_new_cc_fk')
                    ->references('id')
                    ->on('inventario_centros_costo')
                    ->nullOnDelete();
            }
            if (! in_array('registrado_por', $foreignColumns, true)) {
                $table->foreign('registrado_por', 'inv_kiz_imp_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_kizeo_imputacion_historial');
    }
};
