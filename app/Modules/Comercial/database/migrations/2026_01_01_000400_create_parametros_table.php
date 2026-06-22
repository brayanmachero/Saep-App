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
        Schema::create('comercial_parametros', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->text('valor');
            $table->string('tipo')->default('string'); // string, integer, decimal, date, json
            $table->boolean('editable')->default(true);
            $table->date('fecha_vigencia_desde')->nullable();
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->string('categoria')->nullable(); // UF, SUELDO_MINIMO, TASAS, MARGENES, UNIFORMES, etc
            $table->integer('version')->default(1);
            $table->text('valor_anterior')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('clave');
            $table->index('categoria');
            $table->index('fecha_vigencia_desde');
            $table->index('editable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_parametros');
    }
};
