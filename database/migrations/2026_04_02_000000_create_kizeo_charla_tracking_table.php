<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kizeo_charla_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 20)->unique();
            $table->string('kizeo_form_id', 20)->index();
            $table->string('asignado_por', 150)->nullable();
            $table->string('asignado_por_id', 20)->nullable();
            $table->string('asignado_a', 150)->nullable();
            $table->string('asignado_a_id', 20)->nullable();
            $table->enum('estado', ['completado', 'pendiente'])->default('pendiente')->index();
            $table->dateTime('fecha_creacion')->nullable();
            $table->dateTime('fecha_respuesta')->nullable();
            $table->unsignedSmallInteger('semana')->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['anio', 'semana']);
            $table->index('fecha_creacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kizeo_charla_tracking');
    }
};
