<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo unificado: tipo distingue lesion / causa / medida
        Schema::create('opciones_accidente_sst', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);  // lesion, causa, medida
            $table->string('nombre', 300);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'nombre']);
            $table->index('tipo');
        });

        // Pivot: un accidente puede tener N opciones de cada tipo
        Schema::create('accidente_sst_opcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accidente_sst_id')->constrained('accidentes_sst')->cascadeOnDelete();
            $table->foreignId('opcion_id')->constrained('opciones_accidente_sst')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['accidente_sst_id', 'opcion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accidente_sst_opcion');
        Schema::dropIfExists('opciones_accidente_sst');
    }
};
