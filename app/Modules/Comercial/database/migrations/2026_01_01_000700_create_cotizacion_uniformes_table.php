<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comercial_cotizacion_uniformes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('comercial_cotizaciones')->cascadeOnDelete();

            $table->string('descripcion');
            $table->text('especificaciones')->nullable();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('total', 15, 2);

            $table->timestamps();
            $table->softDeletes();

            $table->index('cotizacion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_cotizacion_uniformes');
    }
};
