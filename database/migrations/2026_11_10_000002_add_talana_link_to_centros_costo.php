<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Vínculo de centros_costo con su centro oficial en Talana.
 * Columnas opcionales: no cambian el nombre ni el estado que ven los demás módulos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros_costo', function (Blueprint $table) {
            $table->string('codigo_talana', 40)->nullable()->unique()->after('codigo');
            $table->string('modalidad_talana', 3)->nullable()->after('codigo_talana');
            $table->string('nombre_talana', 200)->nullable()->after('modalidad_talana');
            $table->boolean('talana_vigente')->nullable()->after('nombre_talana');
            $table->timestamp('talana_sincronizado_at')->nullable()->after('talana_vigente');
        });
    }

    public function down(): void
    {
        Schema::table('centros_costo', function (Blueprint $table) {
            $table->dropUnique(['codigo_talana']);
            $table->dropColumn(['codigo_talana', 'modalidad_talana', 'nombre_talana', 'talana_vigente', 'talana_sincronizado_at']);
        });
    }
};
