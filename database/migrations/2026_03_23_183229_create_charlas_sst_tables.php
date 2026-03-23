<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charlas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 300);
            $table->longText('contenido')->nullable();
            $table->enum('tipo', ['CHARLA_5MIN', 'CAPACITACION', 'INDUCCION', 'CHARLA_ESPECIAL'])
                  ->default('CHARLA_5MIN');
            $table->string('lugar', 300)->nullable();
            $table->dateTime('fecha_programada');
            $table->unsignedInteger('duracion_minutos')->default(15);
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('estado', ['BORRADOR', 'PROGRAMADA', 'EN_CURSO', 'COMPLETADA', 'CANCELADA'])
                  ->default('BORRADOR');
            $table->json('archivos_adjuntos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_dictado')->nullable();
            $table->timestamps();
        });

        Schema::create('charla_asistentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charla_id')->constrained('charlas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->enum('estado', ['PENDIENTE', 'FIRMADO'])->default('PENDIENTE');
            $table->longText('firma_imagen')->nullable();
            $table->timestamp('fecha_firma')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('documento_hash', 128)->nullable();
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->unique(['charla_id', 'usuario_id'], 'uq_charla_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charla_asistentes');
        Schema::dropIfExists('charlas');
    }
};
