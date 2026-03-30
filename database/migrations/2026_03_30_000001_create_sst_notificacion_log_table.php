<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sst_notificacion_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('tipo', 30); // recordatorio, vencimiento, vencida, asignacion, seguimiento_pendiente
            $table->tinyInteger('mes')->nullable();
            $table->string('rol_destinatario', 30)->nullable(); // responsable, jefe, superadmin
            $table->timestamps();

            $table->index(['actividad_id', 'tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_notificacion_log');
    }
};
