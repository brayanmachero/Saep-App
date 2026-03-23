<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apellido_paterno', 200)->nullable()->after('name');
            $table->string('apellido_materno', 200)->nullable()->after('apellido_paterno');
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->onDelete('set null')->after('departamento_id');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->onDelete('set null')->after('cargo_id');
            $table->enum('tipo_nomina', ['NORMAL', 'TRANSITORIO'])->default('NORMAL')->after('centro_costo_id');
            $table->string('razon_social', 300)->nullable()->after('tipo_nomina');
            $table->date('fecha_nacimiento')->nullable()->after('razon_social');
            $table->string('nacionalidad', 100)->nullable()->after('fecha_nacimiento');
            $table->string('sexo', 10)->nullable()->after('nacionalidad');
            $table->string('estado_civil', 50)->nullable()->after('sexo');
            $table->date('fecha_ingreso')->nullable()->after('estado_civil');
            $table->string('telefono', 50)->nullable()->after('fecha_ingreso');
        });

        // Agregar categoría y kizeo a formularios
        Schema::table('formularios', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_formularios')->onDelete('set null')->after('departamento_id');
            $table->string('kizeo_form_id', 50)->nullable()->after('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('formularios', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['categoria_id', 'kizeo_form_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cargo_id']);
            $table->dropForeign(['centro_costo_id']);
            $table->dropColumn(['apellido_paterno','apellido_materno','cargo_id','centro_costo_id',
                'tipo_nomina','razon_social','fecha_nacimiento','nacionalidad','sexo',
                'estado_civil','fecha_ingreso','telefono']);
        });
    }
};
