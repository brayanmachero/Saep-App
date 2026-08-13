<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_coordinadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('nombre_normalizado', 220)->unique();
            $table->string('rut', 30)->nullable()->unique();
            $table->string('cargo', 180)->nullable();
            $table->string('correo', 180)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('jefe_operaciones', 200)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'nombre']);
        });

        Schema::create('inventario_centros_costo', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero_maestro')->nullable()->unique();
            $table->string('nombre', 220);
            $table->string('nombre_normalizado', 240)->unique();
            $table->string('tipo', 20)->nullable();
            $table->string('comuna', 120)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->string('jefe_operaciones', 200)->nullable();
            $table->foreignId('coordinador_id')->nullable()->constrained('inventario_coordinadores')->nullOnDelete();
            $table->string('coordinador_nombre_origen', 200)->nullable();
            $table->string('cargo_contacto', 180)->nullable();
            $table->string('correo_contacto', 180)->nullable();
            $table->string('telefono_contacto', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'tipo']);
            $table->index('comuna');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->foreignId('centro_costo_id')->nullable()->after('centro_costo')->constrained('inventario_centros_costo')->nullOnDelete();
            $table->foreignId('coordinador_id')->nullable()->after('centro_costo_id')->constrained('inventario_coordinadores')->nullOnDelete();
            $table->index(['centro_costo_id', 'coordinador_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->dropIndex(['centro_costo_id', 'coordinador_id']);
            $table->dropConstrainedForeignId('coordinador_id');
            $table->dropConstrainedForeignId('centro_costo_id');
        });

        Schema::dropIfExists('inventario_centros_costo');
        Schema::dropIfExists('inventario_coordinadores');
    }
};
