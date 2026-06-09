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
        Schema::create('comercial_cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('comercial_cotizaciones')->cascadeOnDelete();

            $table->string('tipo'); // remuneracion, cotizacion, provision, gasto, uniforme
            $table->string('concepto');
            $table->text('descripcion')->nullable();

            $table->decimal('valor_base', 15, 2);
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->decimal('valor', 15, 2);

            $table->json('formula')->nullable()->comment('Referencia a la fórmula utilizada');
            $table->json('calculos_paso_a_paso')->nullable();

            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('cotizacion_id');
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_cotizacion_detalles');
    }
};
