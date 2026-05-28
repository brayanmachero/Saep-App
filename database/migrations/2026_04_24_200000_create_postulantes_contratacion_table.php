<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulantes_contratacion', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();

            // Datos del postulante
            $table->string('nombre');
            $table->string('rut', 20);
            $table->string('email');
            $table->string('google_id')->nullable();
            $table->string('google_name')->nullable();
            $table->string('google_avatar')->nullable();

            // Documentos (ruta en Azure Blob: contratacion/{rut}/archivo.ext)
            $table->string('carnet_frontal')->nullable();
            $table->string('carnet_reverso')->nullable();
            $table->string('certificado_afp')->nullable();
            $table->string('certificado_fonasa')->nullable();
            $table->string('licencia_conducir')->nullable(); // opcional

            // Estado
            $table->enum('estado', ['pendiente', 'en_revision', 'aprobado', 'rechazado'])->default('pendiente');
            $table->text('observaciones')->nullable(); // notas internas RRHH

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulantes_contratacion');
    }
};
