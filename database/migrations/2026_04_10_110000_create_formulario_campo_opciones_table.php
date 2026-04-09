<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formulario_campo_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulario_id')->constrained('formularios')->cascadeOnDelete();
            $table->string('campo_id', 50);
            $table->string('valor', 500);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['formulario_id', 'campo_id', 'valor'], 'campo_opcion_unica');
            $table->index(['formulario_id', 'campo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulario_campo_opciones');
    }
};
