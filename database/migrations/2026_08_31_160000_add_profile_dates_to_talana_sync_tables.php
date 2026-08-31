<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talana_personas') && ! Schema::hasColumn('talana_personas', 'fecha_nacimiento')) {
            Schema::table('talana_personas', function (Blueprint $table) {
                $table->date('fecha_nacimiento')->nullable()->after('email');
            });
        }

        if (Schema::hasTable('talana_contratos') && ! Schema::hasColumn('talana_contratos', 'fecha_contratacion')) {
            Schema::table('talana_contratos', function (Blueprint $table) {
                $table->date('fecha_contratacion')->nullable()->after('tipo_contrato_nombre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('talana_contratos') && Schema::hasColumn('talana_contratos', 'fecha_contratacion')) {
            Schema::table('talana_contratos', function (Blueprint $table) {
                $table->dropColumn('fecha_contratacion');
            });
        }

        if (Schema::hasTable('talana_personas') && Schema::hasColumn('talana_personas', 'fecha_nacimiento')) {
            Schema::table('talana_personas', function (Blueprint $table) {
                $table->dropColumn('fecha_nacimiento');
            });
        }
    }
};
