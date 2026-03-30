<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_personales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenido');
            $table->string('categoria', 50)->default('General');
            $table->date('fecha_recordatorio')->nullable();
            $table->boolean('completada')->default(false);
            $table->string('origen', 20)->default('texto'); // texto | voz
            $table->timestamps();

            $table->index(['user_id', 'categoria']);
            $table->index(['user_id', 'fecha_recordatorio']);
            $table->index(['user_id', 'completada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_personales');
    }
};
