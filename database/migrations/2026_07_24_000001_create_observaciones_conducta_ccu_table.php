<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observaciones_conducta_ccu', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 32)->unique();
            $table->unsignedInteger('kizeo_record_number')->nullable();
            $table->timestamp('kizeo_created_at')->nullable();
            $table->timestamp('kizeo_updated_at')->nullable();
            $table->date('fecha_observacion')->nullable();
            $table->string('centro', 160)->nullable();
            $table->string('observador_nombre', 200)->nullable();
            $table->string('observador_cargo', 180)->nullable();
            $table->string('trabajador_rut', 30)->nullable();
            $table->string('trabajador_nombre', 200)->nullable();
            $table->string('trabajador_cargo', 180)->nullable();
            $table->string('antiguedad_cargo', 80)->nullable();
            $table->string('tipo_observacion', 600)->nullable();
            $table->string('clasificacion', 20)->nullable();
            $table->text('conducta_observada')->nullable();
            $table->string('medida_control', 250)->nullable();
            $table->text('retroalimentacion')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('fecha_observacion');
            $table->index('centro');
            $table->index('clasificacion');
            $table->index('observador_nombre');
            $table->index('trabajador_nombre');
            $table->index(['fecha_observacion', 'clasificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones_conducta_ccu');
    }
};
