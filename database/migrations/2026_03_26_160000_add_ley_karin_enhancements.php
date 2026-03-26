<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New fields for ley_karin
        Schema::table('ley_karin', function (Blueprint $table) {
            $table->string('metodo_contacto', 50)->nullable()->after('consentimiento_geolocalizacion');
        });

        // Audit log table
        Schema::create('ley_karin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ley_karin_id')->constrained('ley_karin')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accion', 60);          // CREADA, VISTA, EDITADA, ESTADO_CAMBIADO, ARCHIVO_SUBIDO, MENSAJE_ENVIADO
            $table->string('detalle', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            $table->dropColumn('metodo_contacto');
        });

        Schema::dropIfExists('ley_karin_logs');
    }
};
