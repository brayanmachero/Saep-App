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
        Schema::create('accidentes_sst', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['ACCIDENTE_TRABAJO','ENFERMEDAD_PROFESIONAL','CASI_ACCIDENTE','PRIMEROS_AUXILIOS'])->default('ACCIDENTE_TRABAJO');
            $table->unsignedBigInteger('trabajador_id')->nullable();
            $table->string('trabajador_nombre', 200);
            $table->string('trabajador_rut', 20)->nullable();
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->dateTime('fecha_hora_accidente');
            $table->string('lugar', 300);
            $table->text('descripcion');
            $table->text('causas')->nullable();
            $table->text('medidas_inmediatas')->nullable();
            $table->text('medidas_correctivas')->nullable();
            $table->enum('gravedad', ['LEVE','GRAVE','FATAL','SIN_LESION'])->default('LEVE');
            $table->integer('dias_perdidos')->default(0);
            $table->boolean('reportado_mutual')->default(false);
            $table->string('numero_diat', 100)->nullable();  // DIAT mutual
            $table->enum('estado', ['BORRADOR','REPORTADO','EN_INVESTIGACION','CERRADO'])->default('BORRADOR');
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accidentes_sst');
    }
};
