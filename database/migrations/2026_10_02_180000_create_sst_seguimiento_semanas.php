<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sst_seguimiento_semanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->date('semana_inicio');
            $table->unsignedInteger('cantidad_realizada')->default(0);
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_actualizacion')->nullable();
            $table->timestamps();

            $table->unique(['actividad_id', 'mes', 'semana_inicio'], 'sst_weekly_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_seguimiento_semanas');
    }
};
