<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratacion_sync_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')
                ->constrained('postulantes_contratacion')
                ->cascadeOnDelete();
            $table->string('accion', 60);
            $table->string('status', 20)->index();
            $table->unsignedInteger('intento')->default(1);
            $table->string('archivo_nombre', 255)->nullable();
            $table->unsignedBigInteger('archivo_tamano')->nullable();
            $table->string('sharepoint_site', 100)->nullable();
            $table->text('sharepoint_path')->nullable();
            $table->string('sharepoint_item_id', 200)->nullable();
            $table->string('origen', 30)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['postulante_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacion_sync_log');
    }
};
