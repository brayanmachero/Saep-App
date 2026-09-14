<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_kizeo_imputacion_historial')) {
            return;
        }

        Schema::create('inventario_kizeo_imputacion_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplicacion_id')
                ->constrained('inventario_entrega_kizeo_aplicaciones')
                ->cascadeOnDelete();
            $table->foreignId('centro_costo_anterior_id')
                ->nullable()
                ->constrained('inventario_centros_costo')
                ->nullOnDelete();
            $table->string('centro_costo_anterior', 220)->nullable();
            $table->foreignId('centro_costo_nuevo_id')
                ->nullable()
                ->constrained('inventario_centros_costo')
                ->nullOnDelete();
            $table->string('centro_costo_nuevo', 220);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('registrado_por_nombre', 200)->nullable();
            $table->timestamps();

            $table->index(['aplicacion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_kizeo_imputacion_historial');
    }
};
