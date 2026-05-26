<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Personas (trabajadores) ──────────────────────────────────────────
        Schema::create('talana_personas', function (Blueprint $table) {
            $table->id();
            $table->integer('talana_id')->unique();
            $table->string('rut', 20)->nullable();
            $table->string('nombre', 100)->nullable();
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('apellido_materno', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('rut');
            $table->index('activo');
        });

        // ─── Contratos ────────────────────────────────────────────────────────
        Schema::create('talana_contratos', function (Blueprint $table) {
            $table->id();
            $table->integer('talana_id')->unique();
            $table->integer('persona_talana_id')->nullable();
            $table->string('persona_nombre', 200)->nullable();
            $table->string('persona_rut', 20)->nullable();
            $table->string('persona_email', 150)->nullable();
            $table->string('tipo_contrato', 50)->nullable();           // código
            $table->string('tipo_contrato_nombre', 100)->nullable();   // label legible
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->boolean('finiquitado')->default(false);
            $table->string('sucursal_nombre', 150)->nullable();
            $table->string('centro_costo_nombre', 150)->nullable();
            $table->string('cargo_nombre', 150)->nullable();
            $table->decimal('horas_jornada', 5, 1)->nullable();        // hrs/semana
            $table->string('jefe_nombre', 200)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('persona_talana_id');
            $table->index('finiquitado');
            $table->index('hasta');
            $table->index(['finiquitado', 'hasta']);
            $table->index('centro_costo_nombre');
        });

        // ─── Marcas de asistencia ─────────────────────────────────────────────
        Schema::create('talana_marcas', function (Blueprint $table) {
            $table->id();
            $table->integer('persona_talana_id');
            $table->string('persona_nombre', 200)->nullable();
            $table->string('persona_rut', 20)->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('tipo', 5)->nullable();        // 'E' entrada / 'S' salida
            $table->string('centro_costo_nombre', 150)->nullable();
            $table->timestamp('raw_ts')->nullable();       // timestamp original
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('persona_talana_id');
            $table->index('fecha');
            $table->index('tipo');
            $table->index(['persona_talana_id', 'fecha']);
            $table->index('centro_costo_nombre');
            // Evitar duplicados: una marca por persona+ts exacto
            $table->unique(['persona_talana_id', 'raw_ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talana_marcas');
        Schema::dropIfExists('talana_contratos');
        Schema::dropIfExists('talana_personas');
    }
};
