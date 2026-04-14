<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Miembros de tablero
        Schema::create('kanban_tablero_miembros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablero_id')->constrained('kanban_tableros')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('rol', ['admin', 'editor', 'viewer'])->default('editor');
            $table->timestamps();
            $table->unique(['tablero_id', 'user_id']);
        });

        // Historial de actividad
        Schema::create('kanban_actividad_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablero_id')->constrained('kanban_tableros')->onDelete('cascade');
            $table->foreignId('tarea_id')->nullable()->constrained('kanban_tareas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('accion', 50); // created, updated, moved, commented, checklist, attachment, assigned, deleted
            $table->string('detalle', 500)->nullable();
            $table->timestamps();

            $table->index(['tablero_id', 'created_at']);
            $table->index(['tarea_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_actividad_log');
        Schema::dropIfExists('kanban_tablero_miembros');
    }
};
