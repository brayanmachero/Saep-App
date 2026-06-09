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
        Schema::create('comercial_modalidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // EST, SUB
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('margen_operacional', 5, 2)->comment('Margen en porcentaje');
            $table->decimal('sis_porcentaje', 5, 2)->comment('SIS en porcentaje');
            $table->decimal('mutual_porcentaje', 5, 2)->comment('Mutual en porcentaje');
            $table->decimal('cesantia_porcentaje', 5, 2)->comment('Cesantía en porcentaje');
            $table->decimal('factor_vacaciones', 5, 3)->nullable()->default(1.0)->comment('Factor vacacional');
            $table->decimal('refprev_porcentaje', 5, 2)->nullable()->comment('REFPREV en porcentaje');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->json('configuracion_adicional')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('codigo');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_modalidades');
    }
};
