<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Conectar categorías con formularios (si no existe ya)
        if (!Schema::hasColumn('formularios', 'categoria_id')) {
            Schema::table('formularios', function (Blueprint $table) {
                $table->foreignId('categoria_id')
                      ->nullable()
                      ->after('departamento_id')
                      ->constrained('categorias_formularios')
                      ->onDelete('set null');
            });
        }

        // 2. Programación: fechas y frecuencia
        if (!Schema::hasColumn('formularios', 'fecha_inicio')) {
            Schema::table('formularios', function (Blueprint $table) {
                $table->date('fecha_inicio')->nullable()->after('activo');
                $table->date('fecha_fin')->nullable()->after('fecha_inicio');
                $table->enum('frecuencia', ['unica', 'diaria', 'semanal', 'quincenal', 'mensual'])
                      ->nullable()->after('fecha_fin');
            });
        }

        // 3. Tabla pivot: asignación de formularios a usuarios
        Schema::create('formulario_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulario_id')->constrained('formularios')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('estado', ['Pendiente', 'Completado', 'Vencido'])->default('Pendiente');
            $table->date('fecha_limite')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();

            $table->unique(['formulario_id', 'user_id', 'fecha_limite'], 'form_user_fecha_unique');
            $table->index('estado');
        });

        // Soft-deletes en respuestas (ya existe en modelo pero asegurar columna)
        if (!Schema::hasColumn('respuestas', 'deleted_at')) {
            Schema::table('respuestas', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('formulario_usuario');

        Schema::table('formularios', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['categoria_id', 'fecha_inicio', 'fecha_fin', 'frecuencia']);
        });

        if (Schema::hasColumn('respuestas', 'deleted_at')) {
            Schema::table('respuestas', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
