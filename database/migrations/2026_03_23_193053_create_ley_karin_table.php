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
        Schema::create('ley_karin', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->enum('tipo', ['ACOSO_LABORAL','ACOSO_SEXUAL','VIOLENCIA_EN_TRABAJO'])->default('ACOSO_LABORAL');
            $table->unsignedBigInteger('denunciante_id')->nullable();
            $table->string('denunciante_nombre', 200);
            $table->string('denunciante_rut', 20)->nullable();
            $table->string('denunciado_nombre', 200)->nullable();
            $table->string('denunciado_cargo', 200)->nullable();
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->date('fecha_denuncia');
            $table->text('descripcion_hechos');
            $table->text('medidas_cautelares')->nullable();
            $table->text('resultado_investigacion')->nullable();
            $table->date('fecha_resolucion')->nullable();
            $table->enum('estado', ['RECIBIDA','EN_INVESTIGACION','RESUELTA','ARCHIVADA'])->default('RECIBIDA');
            $table->boolean('anonima')->default(false);
            $table->unsignedBigInteger('investigador_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ley_karin');
    }
};
