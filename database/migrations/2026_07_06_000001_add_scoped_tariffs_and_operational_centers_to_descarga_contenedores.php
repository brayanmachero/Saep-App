<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descarga_contenedor_tarifas', function (Blueprint $table) {
            if (!Schema::hasColumn('descarga_contenedor_tarifas', 'centro_costo_id')) {
                $table->foreignId('centro_costo_id')
                    ->nullable()
                    ->after('cliente')
                    ->constrained('centros_costo')
                    ->nullOnDelete();
                $table->index(['cliente', 'centro_costo_id', 'codigo'], 'dc_tarifas_cliente_centro_codigo_idx');
            }
        });

        Schema::table('talana_trabajadores', function (Blueprint $table) {
            if (!Schema::hasColumn('talana_trabajadores', 'centro_operativo_id')) {
                $table->foreignId('centro_operativo_id')
                    ->nullable()
                    ->after('centro_costo_nombre')
                    ->constrained('centros_costo')
                    ->nullOnDelete();
                $table->string('centro_operativo_nombre', 180)
                    ->nullable()
                    ->after('centro_operativo_id');
                $table->index(['activo', 'centro_operativo_id'], 'talana_trabajadores_activo_centro_operativo_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talana_trabajadores', function (Blueprint $table) {
            if (Schema::hasColumn('talana_trabajadores', 'centro_operativo_id')) {
                $table->dropIndex('talana_trabajadores_activo_centro_operativo_idx');
                $table->dropConstrainedForeignId('centro_operativo_id');
                $table->dropColumn('centro_operativo_nombre');
            }
        });

        Schema::table('descarga_contenedor_tarifas', function (Blueprint $table) {
            if (Schema::hasColumn('descarga_contenedor_tarifas', 'centro_costo_id')) {
                $table->dropIndex('dc_tarifas_cliente_centro_codigo_idx');
                $table->dropConstrainedForeignId('centro_costo_id');
            }
        });
    }
};
