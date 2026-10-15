<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_centros_costo', function (Blueprint $table) {
            $table->string('codigo_origen', 80)->nullable()->unique();
            $table->string('ruta')->nullable();
            $table->string('dias_despacho')->nullable();
            $table->boolean('se_despacha')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_centros_costo', function (Blueprint $table) {
            $table->dropUnique(['codigo_origen']);
            $table->dropColumn(['codigo_origen', 'ruta', 'dias_despacho', 'se_despacha']);
        });
    }
};
