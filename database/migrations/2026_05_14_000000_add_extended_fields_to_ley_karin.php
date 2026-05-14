<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            // Denunciante / Víctima — datos extendidos
            $table->string('denunciante_rango_etario', 20)->nullable()->after('denunciante_email');
            $table->string('denunciante_sexo', 20)->nullable()->after('denunciante_rango_etario');
            $table->string('denunciante_cargo_tipo', 50)->nullable()->after('denunciante_sexo');
            $table->string('denunciante_cargo_otro', 200)->nullable()->after('denunciante_cargo_tipo');
            $table->string('denunciante_empresa', 20)->nullable()->after('denunciante_cargo_otro');
            $table->string('denunciante_jerarquia', 30)->nullable()->after('denunciante_empresa');

            // Denunciado — datos extendidos
            $table->string('denunciado_rango_etario', 20)->nullable()->after('denunciado_cargo');
            $table->string('denunciado_sexo', 20)->nullable()->after('denunciado_rango_etario');
            $table->string('denunciado_cargo_tipo', 50)->nullable()->after('denunciado_sexo');
            $table->string('denunciado_cargo_otro', 200)->nullable()->after('denunciado_cargo_tipo');
            $table->string('denunciado_empresa', 20)->nullable()->after('denunciado_cargo_otro');

            // Tercero (cuando alguien denuncia en nombre de la víctima)
            $table->boolean('es_tercero')->default(false)->after('anonima');
            $table->string('tercero_nombre', 200)->nullable()->after('es_tercero');
            $table->string('tercero_rut', 20)->nullable()->after('tercero_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ley_karin', function (Blueprint $table) {
            $table->dropColumn([
                'denunciante_rango_etario',
                'denunciante_sexo',
                'denunciante_cargo_tipo',
                'denunciante_cargo_otro',
                'denunciante_empresa',
                'denunciante_jerarquia',
                'denunciado_rango_etario',
                'denunciado_sexo',
                'denunciado_cargo_tipo',
                'denunciado_cargo_otro',
                'denunciado_empresa',
                'es_tercero',
                'tercero_nombre',
                'tercero_rut',
            ]);
        });
    }
};
