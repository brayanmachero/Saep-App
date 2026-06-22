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
        Schema::create('comercial_centros_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('comercial_clientes')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo')->nullable()->unique();
            $table->text('descripcion')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('responsable')->nullable();
            $table->string('email_responsable')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->json('datos_adicionales')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
            $table->index('codigo');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercial_centros_costo');
    }
};
