<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tableros Kanban
        Schema::create('kanban_tableros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->onDelete('set null');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Columnas del tablero (estados personalizables)
        Schema::create('kanban_columnas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablero_id')->constrained('kanban_tableros')->onDelete('cascade');
            $table->string('nombre', 100);
            $table->string('color', 7)->default('#6b7280'); // hex color
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Tareas del tablero
        Schema::create('kanban_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablero_id')->constrained('kanban_tableros')->onDelete('cascade');
            $table->foreignId('columna_id')->constrained('kanban_columnas')->onDelete('cascade');
            $table->string('titulo', 300);
            $table->text('descripcion')->nullable();
            $table->enum('prioridad', ['ALTA', 'MEDIA', 'BAJA'])->default('MEDIA');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('creado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->onDelete('set null');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Etiquetas para tareas
        Schema::create('kanban_etiquetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablero_id')->constrained('kanban_tableros')->onDelete('cascade');
            $table->string('nombre', 50);
            $table->string('color', 7)->default('#3b82f6');
            $table->timestamps();
        });

        // Pivot: tarea <-> etiqueta
        Schema::create('kanban_tarea_etiqueta', function (Blueprint $table) {
            $table->foreignId('tarea_id')->constrained('kanban_tareas')->onDelete('cascade');
            $table->foreignId('etiqueta_id')->constrained('kanban_etiquetas')->onDelete('cascade');
            $table->primary(['tarea_id', 'etiqueta_id']);
        });

        // Registrar módulo
        DB::table('modulos')->insert([
            'slug'        => 'kanban',
            'nombre'      => 'Tablero Kanban',
            'descripcion' => 'Gestión de tareas con tablero Kanban, vista lista y calendario',
            'icono'       => 'bi-kanban',
            'grupo'       => 'Mis Herramientas',
            'orden'       => 75,
            'activo'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Dar acceso completo a SUPER_ADMIN
        $moduloId = DB::table('modulos')->where('slug', 'kanban')->value('id');
        $rolIds = DB::table('roles')->where('codigo', 'SUPER_ADMIN')->pluck('id');
        foreach ($rolIds as $rolId) {
            DB::table('rol_modulo')->insert([
                'rol_id'          => $rolId,
                'modulo_id'       => $moduloId,
                'puede_ver'       => true,
                'puede_crear'     => true,
                'puede_editar'    => true,
                'puede_eliminar'  => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('slug', 'kanban')->value('id');
        if ($moduloId) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
            DB::table('modulos')->where('id', $moduloId)->delete();
        }

        Schema::dropIfExists('kanban_tarea_etiqueta');
        Schema::dropIfExists('kanban_etiquetas');
        Schema::dropIfExists('kanban_tareas');
        Schema::dropIfExists('kanban_columnas');
        Schema::dropIfExists('kanban_tableros');
    }
};
