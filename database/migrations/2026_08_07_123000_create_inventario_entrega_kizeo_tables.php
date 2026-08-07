<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_entrega_kizeo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_bodega_id')->unique()->constrained('entregas_bodega')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones')->restrictOnDelete();
            $table->string('estado', 30)->default('APLICADA');
            $table->timestamp('fuente_actualizada_en')->nullable();
            $table->foreignId('aplicada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aplicada_en')->nullable();
            $table->foreignId('revertida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revertida_en')->nullable();
            $table->string('motivo_reversion', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'ubicacion_id']);
        });

        Schema::create('inventario_entrega_kizeo_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplicacion_id')->constrained('inventario_entrega_kizeo_aplicaciones')->cascadeOnDelete();
            $table->unsignedSmallInteger('linea_fuente');
            $table->string('articulo_fuente', 200);
            $table->string('talla_fuente', 80)->nullable();
            $table->decimal('cantidad_fuente', 14, 3);
            $table->foreignId('producto_id')->constrained('inventario_productos')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('inventario_variantes')->restrictOnDelete();
            $table->foreignId('movimiento_id')->nullable()->constrained('inventario_movimientos')->nullOnDelete();
            $table->foreignId('reverso_movimiento_id')->nullable()->constrained('inventario_movimientos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['aplicacion_id', 'linea_fuente'], 'inv_kizeo_lineas_app_fuente_uq');
            $table->index('variante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_entrega_kizeo_lineas');
        Schema::dropIfExists('inventario_entrega_kizeo_aplicaciones');
    }
};
