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
            $table->string('estado_fuente', 40)->default('ACTIVA')->after('flujo_inventario');
            $table->string('alerta_fuente', 500)->nullable()->after('estado_fuente');
            $table->timestamp('fuente_ausente_desde')->nullable()->after('alerta_fuente');
            $table->index(['estado_fuente', 'flujo_inventario']);
        });

        DB::table('entregas_bodega')
            ->whereNull('estado_fuente')
            ->update(['estado_fuente' => 'ACTIVA']);
    }

    public function down(): void
    {
        Schema::table('entregas_bodega', function (Blueprint $table) {
            $table->dropIndex(['estado_fuente', 'flujo_inventario']);
            $table->dropColumn([
                'estado_fuente',
                'alerta_fuente',
                'fuente_ausente_desde',
            ]);
        });
    }
};
