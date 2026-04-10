<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sst_actividades', function (Blueprint $table) {
            $table->unsignedSmallInteger('cantidad_programada')->default(1)->after('periodicidad');
        });

        Schema::table('sst_seguimiento', function (Blueprint $table) {
            $table->unsignedSmallInteger('cantidad_realizada')->default(0)->after('realizado');
        });
    }

    public function down(): void
    {
        Schema::table('sst_actividades', function (Blueprint $table) {
            $table->dropColumn('cantidad_programada');
        });

        Schema::table('sst_seguimiento', function (Blueprint $table) {
            $table->dropColumn('cantidad_realizada');
        });
    }
};
