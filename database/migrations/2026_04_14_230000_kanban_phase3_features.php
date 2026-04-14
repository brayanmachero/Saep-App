<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Archivar tareas — campo booleano
        Schema::table('kanban_tareas', function (Blueprint $table) {
            $table->boolean('archivada')->default(false)->after('orden');
            $table->index('archivada');
        });

        // 2) Tareas recurrentes
        Schema::table('kanban_tareas', function (Blueprint $table) {
            $table->string('recurrencia', 20)->nullable()->after('archivada'); // diaria, semanal, quincenal, mensual
            $table->date('recurrencia_hasta')->nullable()->after('recurrencia');
            $table->unsignedBigInteger('tarea_origen_id')->nullable()->after('recurrencia_hasta');
            $table->foreign('tarea_origen_id')->references('id')->on('kanban_tareas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kanban_tareas', function (Blueprint $table) {
            $table->dropForeign(['tarea_origen_id']);
            $table->dropColumn(['archivada', 'recurrencia', 'recurrencia_hasta', 'tarea_origen_id']);
        });
    }
};
