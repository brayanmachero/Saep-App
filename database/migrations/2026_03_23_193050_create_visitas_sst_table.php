<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visitas_sst', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 300);
            $table->enum('tipo', ['INSPECCION','VISITA_TERRENO','SUPERVISION'])->default('INSPECCION');
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->unsignedBigInteger('inspector_id')->nullable();
            $table->date('fecha_visita');
            $table->text('hallazgos')->nullable();
            $table->text('medidas_correctivas')->nullable();
            $table->enum('estado', ['PROGRAMADA','REALIZADA','CANCELADA'])->default('PROGRAMADA');
            $table->json('fotos')->nullable();   // array de rutas
            $table->string('firma_inspector')->nullable(); // base64
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_sst');
    }
};
