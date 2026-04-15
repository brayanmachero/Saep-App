<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_tarea_asignados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('kanban_tareas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['tarea_id', 'user_id']);
        });

        // Add 'completada' column to kanban_columnas to mark which column means "done"
        Schema::table('kanban_columnas', function (Blueprint $table) {
            $table->boolean('es_completada')->default(false)->after('orden');
        });

        // Migrate existing asignado_a data to the pivot table
        $tareas = \DB::table('kanban_tareas')->whereNotNull('asignado_a')->get();
        foreach ($tareas as $tarea) {
            \DB::table('kanban_tarea_asignados')->insert([
                'tarea_id' => $tarea->id,
                'user_id'  => $tarea->asignado_a,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_tarea_asignados');

        Schema::table('kanban_columnas', function (Blueprint $table) {
            $table->dropColumn('es_completada');
        });
    }
};
