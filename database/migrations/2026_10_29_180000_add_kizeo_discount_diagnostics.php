<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entregas_bodega', function (Blueprint $table) {
            $table->text('descuento_error')->nullable();
            $table->timestamp('descuento_intentado_en')->nullable();
        });
        Schema::create('inventario_cierre_envios', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('destinatario');
            $table->string('estado');
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_cierre_envios');
        Schema::table('entregas_bodega', fn (Blueprint $table) => $table->dropColumn(['descuento_error', 'descuento_intentado_en']));
    }
};
