<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 50)->index();           // 'kizeo', 'otro'
            $table->string('form_id', 50)->nullable()->index();
            $table->string('data_id', 50)->nullable();
            $table->string('tipo', 80)->index();              // 'vehiculo_entrega', 'vehiculo_devolucion', 'charla_sst'
            $table->enum('estado', ['success', 'error', 'ignored'])->default('success')->index();
            $table->string('resumen', 500)->nullable();       // Descripción breve del resultado
            $table->string('archivo', 500)->nullable();       // Nombre del archivo generado
            $table->string('sharepoint_path', 500)->nullable(); // Ruta en SharePoint donde se guardó
            $table->boolean('email_enviado')->default(false);
            $table->json('destinatarios')->nullable();        // Emails a los que se envió
            $table->json('metadata')->nullable();             // Datos extra (patente, relator, etc.)
            $table->text('error_message')->nullable();        // Detalle del error si falló
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
