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
        Schema::create('auditorias_sst', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 300);
            $table->enum('tipo', ['INTERNA','EXTERNA'])->default('INTERNA');
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->unsignedBigInteger('auditor_id')->nullable();
            $table->date('fecha_auditoria');
            $table->text('alcance')->nullable();
            $table->text('hallazgos')->nullable();
            $table->text('no_conformidades')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->enum('resultado', ['CONFORME','NO_CONFORME','OBSERVACION'])->nullable();
            $table->enum('estado', ['PLANIFICADA','EN_PROCESO','COMPLETADA','CANCELADA'])->default('PLANIFICADA');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_sst');
    }
};
