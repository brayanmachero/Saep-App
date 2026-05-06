<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->string('licencia_conducir_frontal')->nullable()->after('licencia_conducir');
            $table->string('licencia_conducir_reverso')->nullable()->after('licencia_conducir_frontal');
        });

        // Migrar datos existentes: si había licencia, se copia al frontal
        \DB::table('postulantes_contratacion')
            ->whereNotNull('licencia_conducir')
            ->update(['licencia_conducir_frontal' => \DB::raw('licencia_conducir')]);

        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->dropColumn('licencia_conducir');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->string('licencia_conducir')->nullable()->after('certificado_fonasa');
        });

        \DB::table('postulantes_contratacion')
            ->whereNotNull('licencia_conducir_frontal')
            ->update(['licencia_conducir' => \DB::raw('licencia_conducir_frontal')]);

        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->dropColumn(['licencia_conducir_frontal', 'licencia_conducir_reverso']);
        });
    }
};
