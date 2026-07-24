<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspecciones_preventivas_pdr', function (Blueprint $table) {
            $table->id();
            $table->string('kizeo_data_id', 32)->unique();
            $table->unsignedInteger('kizeo_record_number')->nullable();
            $table->timestamp('kizeo_created_at')->nullable();
            $table->timestamp('kizeo_updated_at')->nullable();
            $table->date('fecha_inspeccion')->nullable();
            $table->string('hora_inspeccion', 10)->nullable();
            $table->string('centro', 160)->nullable();
            $table->string('responsable_area', 200)->nullable();
            $table->string('inspector_nombre', 200)->nullable();
            $table->string('inspector_cargo', 180)->nullable();
            $table->string('inspector_secundario_nombre', 200)->nullable();
            $table->string('inspector_secundario_cargo', 180)->nullable();
            $table->string('area_inspeccionada', 255)->nullable();
            $table->string('objetivo', 100)->nullable();
            $table->unsignedSmallInteger('condiciones_count')->default(0);
            $table->unsignedSmallInteger('evidencias_count')->default(0);
            $table->text('condiciones_resumen')->nullable();
            $table->unsignedSmallInteger('medidas_count')->default(0);
            $table->text('medidas_resumen')->nullable();
            $table->text('frecuencias_text')->nullable();
            $table->text('verificaciones_text')->nullable();
            $table->string('responsable_medida', 200)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('fecha_inspeccion');
            $table->index('centro');
            $table->index('objetivo');
            $table->index('inspector_nombre');
            $table->index('responsable_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecciones_preventivas_pdr');
    }
};
