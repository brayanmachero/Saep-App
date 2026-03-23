<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmas_electronicas', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_tipo', 50);       // respuesta, charla, charla_asistente
            $table->unsignedBigInteger('entidad_id');
            $table->foreignId('firmante_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('firmante_nombre', 200);
            $table->string('firmante_rut', 20)->nullable();
            $table->string('firmante_email', 200)->nullable();
            $table->string('firmante_cargo', 200)->nullable();
            $table->text('firma_imagen');              // base64 PNG
            $table->string('hash_sha256', 64);        // integridad
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('geolocalizacion', 500)->nullable();
            $table->string('proposito', 100)->default('firma_solicitud'); // firma_solicitud, firma_asistencia, firma_relator
            $table->timestamps();

            $table->index(['entidad_tipo', 'entidad_id']);
        });

        // Tabla de archivos adjuntos en respuestas
        Schema::create('archivos_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_tipo', 50);   // respuesta, charla
            $table->unsignedBigInteger('entidad_id');
            $table->string('nombre_original', 500);
            $table->string('nombre_archivo', 500);
            $table->string('ruta', 1000);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamanio')->default(0); // bytes
            $table->string('campo_formulario', 200)->nullable(); // qué campo del form
            $table->foreignId('subido_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['entidad_tipo', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_adjuntos');
        Schema::dropIfExists('firmas_electronicas');
    }
};
