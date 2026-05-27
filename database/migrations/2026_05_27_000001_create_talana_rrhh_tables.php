<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Ausencias / Licencias / Permisos ────────────────────────────────
        Schema::create('talana_ausencias', function (Blueprint $table) {
            $table->id();
            $table->integer('talana_id')->unique();
            $table->integer('empleado_id');
            $table->string('persona_rut', 20)->nullable();
            $table->string('persona_nombre', 200)->nullable();
            $table->string('tipo_ausencia', 100)->nullable();   // licencia medica, permiso con goce, etc.
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->integer('numero_dias')->nullable();
            $table->boolean('aprobada')->default(false);
            $table->boolean('rebaja_salario')->default(false);
            $table->boolean('es_continuacion')->default(false);
            $table->date('fecha_retorno')->nullable();
            $table->string('numero_licencia', 100)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('empleado_id');
            $table->index('tipo_ausencia');
            $table->index('fecha_desde');
            $table->index(['tipo_ausencia', 'fecha_desde']);
        });

        // ─── Saldo de vacaciones por empleado ─────────────────────────────────
        Schema::create('talana_saldo_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('empleado_id')->unique();
            $table->string('rut', 20)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->date('fecha_corte')->nullable();
            $table->decimal('dias_normales', 8, 4)->default(0);
            $table->decimal('dias_progresivos', 8, 4)->default(0);
            $table->decimal('dias_restantes', 8, 4)->default(0);
            $table->decimal('dias_zona_extrema', 8, 4)->default(0);
            $table->boolean('tiene_error')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('dias_restantes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talana_saldo_vacaciones');
        Schema::dropIfExists('talana_ausencias');
    }
};
