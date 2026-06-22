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
        Schema::create('comercial_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('rut')->nullable()->unique();
            $table->string('nombre');
            $table->string('nombre_comercial')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('telefono')->nullable();
            $table->text('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('region')->nullable();
            $table->string('contacto_principal')->nullable();
            $table->string('contacto_email')->nullable();
            $table->string('contacto_telefono')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->json('datos_adicionales')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('rut');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_clientes');
    }
};
