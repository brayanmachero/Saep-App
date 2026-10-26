<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('comercial_tarifas_referencia', function (Blueprint $table) {
            $table->foreignId('cotizacion_id')->nullable()->unique()->constrained('comercial_cotizaciones')->restrictOnDelete();
            $table->string('vinculo_tipo', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comercial_tarifas_referencia', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
            $table->dropUnique(['cotizacion_id']);
            $table->dropColumn(['cotizacion_id', 'vinculo_tipo']);
        });
    }
};
