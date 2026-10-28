<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_actas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reserva_vehiculo_id')->constrained('reservas_vehiculos')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('estado', 30)->default('BORRADOR');
            $table->unsignedInteger('revision')->default(1);
            $table->json('datos');
            $table->json('evidencias')->nullable();
            $table->text('acuerdo');
            $table->string('acuerdo_version', 100);
            $table->string('contenido_hash', 64)->nullable();
            $table->string('firma_encargado_path')->nullable();
            $table->string('firma_conductor_path')->nullable();
            $table->foreignId('preparada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('preparada_at')->nullable();
            $table->timestamp('firmada_at')->nullable();
            $table->json('auditoria_firma')->nullable();
            $table->string('documento_hash', 64)->nullable();
            $table->string('email_estado', 30)->default('PENDIENTE');
            $table->text('email_error')->nullable();
            $table->timestamp('email_enviado_at')->nullable();
            $table->string('kizeo_estado', 40)->default('PENDIENTE_VALIDACION_API');
            $table->timestamps();
            $table->unique(['reserva_vehiculo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_actas');
    }
};
