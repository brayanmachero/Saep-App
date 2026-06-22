<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_parametro_auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parametro_id')->nullable()->constrained('comercial_parametros')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('clave')->nullable();
            $table->string('nombre');
            $table->string('categoria')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->string('origen')->default('mantenedor');
            $table->string('descripcion')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('parametro_id');
            $table->index('usuario_id');
            $table->index('clave');
            $table->index('origen');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_parametro_auditorias');
    }
};
