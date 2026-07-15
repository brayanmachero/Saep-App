<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sst_actividad_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('sst_actividades')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comentario');
            $table->timestamps();

            $table->index(['actividad_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_actividad_comentarios');
    }
};
