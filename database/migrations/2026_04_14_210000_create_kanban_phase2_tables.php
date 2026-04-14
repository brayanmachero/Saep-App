<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comentarios en tareas
        Schema::create('kanban_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('kanban_tareas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('contenido');
            $table->timestamps();
        });

        // Adjuntos de tareas
        Schema::create('kanban_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('kanban_tareas')->onDelete('cascade');
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamanio')->default(0);
            $table->foreignId('subido_por')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // Checklist items dentro de tareas
        Schema::create('kanban_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('kanban_tareas')->onDelete('cascade');
            $table->string('texto', 500);
            $table->boolean('completado')->default(false);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_checklist_items');
        Schema::dropIfExists('kanban_adjuntos');
        Schema::dropIfExists('kanban_comentarios');
    }
};
