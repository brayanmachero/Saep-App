<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sst_reprogramaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes_original')->comment('Mes programado original (1-12)');
            $table->unsignedTinyInteger('mes_nuevo')->comment('Mes al que se reprogramó (1-12)');
            $table->string('motivo', 500);
            $table->foreignId('reprogramado_por')->constrained('users');
            $table->timestamps();

            $table->index(['actividad_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sst_reprogramaciones');
    }
};
