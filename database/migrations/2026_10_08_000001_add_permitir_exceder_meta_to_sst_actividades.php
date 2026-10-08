<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sst_actividades', function (Blueprint $table) {
            $table->boolean('permitir_exceder_meta')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sst_actividades', fn (Blueprint $table) => $table->dropColumn('permitir_exceder_meta'));
    }
};
