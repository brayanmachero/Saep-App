<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercial_tarifa_fuentes', function (Blueprint $table) {
            $table->id();
            $table->string('identidad', 64)->unique();
            $table->text('archivo');
            $table->text('carpeta')->nullable();
            $table->string('hash_sha256', 64);
            $table->unsignedInteger('hojas')->default(0);
            $table->string('estado')->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
        Schema::create('comercial_tarifas_referencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuente_id')->nullable()->constrained('comercial_tarifa_fuentes')->restrictOnDelete();
            $table->string('identidad', 64)->unique();
            $table->string('grupo', 64)->index();
            $table->string('cliente');
            $table->string('centro')->nullable();
            $table->string('cargo');
            $table->string('categoria')->nullable();
            $table->string('modalidad')->nullable();
            $table->string('unidad')->nullable();
            $table->string('variante')->nullable();
            $table->string('moneda', 8)->default('CLP');
            $table->decimal('precio', 18, 6)->nullable();
            $table->string('periodo')->nullable();
            $table->date('fecha_referencia')->nullable();
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->string('estado')->default('pendiente')->index();
            $table->string('hoja')->nullable();
            $table->string('celda')->nullable();
            $table->json('conceptos')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();
            $table->index(['grupo', 'estado', 'vigente_desde'], 'tarifas_grupo_vigencia');
        });
        Schema::create('comercial_tarifa_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarifa_id')->constrained('comercial_tarifas_referencia')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('antes');
            $table->json('despues');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercial_tarifa_revisiones');
        Schema::dropIfExists('comercial_tarifas_referencia');
        Schema::dropIfExists('comercial_tarifa_fuentes');
    }
};
