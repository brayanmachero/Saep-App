<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kizeo_charla_tracking', function (Blueprint $table) {
            $table->dateTime('fecha_registro_kizeo')->nullable()->after('fecha_creacion')->index();
        });
    }

    public function down(): void
    {
        Schema::table('kizeo_charla_tracking', function (Blueprint $table) {
            $table->dropColumn('fecha_registro_kizeo');
        });
    }
};
