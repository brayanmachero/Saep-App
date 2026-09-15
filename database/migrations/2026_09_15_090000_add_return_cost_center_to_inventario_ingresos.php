<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario_ingresos')) {
            return;
        }

        if (! Schema::hasColumn('inventario_ingresos', 'tipo_ingreso')) {
            Schema::table('inventario_ingresos', function (Blueprint $table) {
                $table->string('tipo_ingreso', 30)->default('COMPRA');
            });
        }

        if (! Schema::hasColumn('inventario_ingresos', 'centro_costo_id')) {
            Schema::table('inventario_ingresos', function (Blueprint $table) {
                $table->unsignedBigInteger('centro_costo_id')->nullable();
            });
        }

        if (! Schema::hasColumn('inventario_ingresos', 'centro_costo')) {
            Schema::table('inventario_ingresos', function (Blueprint $table) {
                $table->string('centro_costo', 220)->nullable();
                $table->index(['tipo_ingreso', 'centro_costo_id'], 'inv_ing_type_cost_center_idx');
            });
        }

        $foreignColumns = [];
        foreach (Schema::getForeignKeys('inventario_ingresos') as $foreignKey) {
            $foreignColumns = [...$foreignColumns, ...$foreignKey['columns']];
        }

        if (! in_array('centro_costo_id', $foreignColumns, true)) {
            Schema::table('inventario_ingresos', function (Blueprint $table) {
                $table->foreign('centro_costo_id', 'inv_ing_cost_center_fk')
                    ->references('id')
                    ->on('inventario_centros_costo')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventario_ingresos')) {
            return;
        }

        Schema::table('inventario_ingresos', function (Blueprint $table) {
            $table->dropForeign('inv_ing_cost_center_fk');
            $table->dropIndex('inv_ing_type_cost_center_idx');
            $table->dropColumn(['tipo_ingreso', 'centro_costo_id', 'centro_costo']);
        });
    }
};
