<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratacion_cierre_diario_envios', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('destinatario');
            $table->string('estado', 20)->default('enviando');
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'destinatario'], 'contratacion_cierre_diario_fecha_destinatario_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacion_cierre_diario_envios');
    }
};
