<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_importacion_movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 120)->unique();
            $table->foreignId('movimiento_id')->constrained('inventario_movimientos')->restrictOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_importacion_movimientos');
    }
};
