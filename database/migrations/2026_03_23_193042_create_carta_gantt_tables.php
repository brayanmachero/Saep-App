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
        // Programas anuales SST
        Schema::create('programas_sst', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            $table->string('titulo', 300);
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['BORRADOR','ACTIVO','CERRADO'])->default('BORRADOR');
            $table->foreignId('creado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // Categorías de actividades dentro del programa
        Schema::create('sst_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas_sst')->onDelete('cascade');
            $table->string('nombre', 200);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Actividades planificadas
        Schema::create('sst_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('sst_categorias')->onDelete('cascade');
            $table->string('nombre', 300);
            $table->text('descripcion')->nullable();
            $table->string('responsable', 200)->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Meses planificados vs ejecutados por actividad
        Schema::create('sst_seguimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->onDelete('cascade');
            $table->tinyInteger('mes'); // 1-12
            $table->boolean('programado')->default(false);
            $table->boolean('realizado')->default(false);
            $table->text('observacion')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_actualizacion')->nullable();
            $table->timestamps();
            $table->unique(['actividad_id','mes']);
        });

        // Plan de acción asociado a actividades no cumplidas
        Schema::create('sst_plan_accion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->onDelete('cascade');
            $table->string('accion', 500);
            $table->string('responsable', 200)->nullable();
            $table->date('fecha_compromiso')->nullable();
            $table->enum('estado', ['PENDIENTE','EN_PROGRESO','COMPLETADO','CANCELADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_plan_accion');
        Schema::dropIfExists('sst_seguimiento');
        Schema::dropIfExists('sst_actividades');
        Schema::dropIfExists('sst_categorias');
        Schema::dropIfExists('programas_sst');
    }
};
