<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consentimientos de tratamiento de datos personales
        Schema::create('consentimientos_datos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('version_politica', 20); // ej: "1.0", "1.1"
            $table->text('texto_aceptado'); // Texto resumido de lo aceptado
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('fecha_aceptacion')->useCurrent();
            $table->timestamp('fecha_revocacion')->nullable();
            $table->boolean('vigente')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'vigente']);
        });

        // Solicitudes ARCO (Acceso, Rectificación, Cancelación/Supresión, Oposición, Portabilidad)
        Schema::create('solicitudes_arco', function (Blueprint $table) {
            $table->id();
            $table->string('numero_solicitud', 30)->unique(); // ARCO-2026-0001
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', ['acceso', 'rectificacion', 'supresion', 'oposicion', 'portabilidad']);
            $table->text('descripcion'); // Detalle de la solicitud
            $table->text('datos_afectados')->nullable(); // Qué datos específicos
            $table->enum('estado', ['pendiente', 'en_revision', 'aprobada', 'rechazada', 'completada'])->default('pendiente');
            $table->text('respuesta')->nullable(); // Respuesta del responsable
            $table->foreignId('responsable_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->timestamp('fecha_vencimiento')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado']);
            $table->index('tipo');
        });

        // Registro de actividades de tratamiento de datos (auditoría)
        Schema::create('registro_tratamiento_datos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('accion', 50); // consulta, modificacion, eliminacion, exportacion, cesion
            $table->string('tabla_afectada', 100);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->string('tipo_dato', 100); // personal, sensible
            $table->text('descripcion')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('datos_anteriores')->nullable(); // Snapshot antes del cambio
            $table->json('datos_nuevos')->nullable(); // Snapshot después del cambio
            $table->timestamps();

            $table->index(['tabla_afectada', 'registro_id']);
            $table->index('accion');
            $table->index('created_at');
        });

        // Agregar campos de consentimiento a la tabla users
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('acepta_politica_datos')->default(false)->after('activo');
            $table->timestamp('fecha_aceptacion_politica')->nullable()->after('acepta_politica_datos');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['acepta_politica_datos', 'fecha_aceptacion_politica']);
        });
        Schema::dropIfExists('registro_tratamiento_datos');
        Schema::dropIfExists('solicitudes_arco');
        Schema::dropIfExists('consentimientos_datos');
    }
};
