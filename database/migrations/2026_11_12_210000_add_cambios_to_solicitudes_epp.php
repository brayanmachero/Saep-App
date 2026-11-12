<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_epp', fn (Blueprint $table) => $table->json('cambios')->nullable());
    }

    public function down(): void
    {
        Schema::table('solicitudes_epp', fn (Blueprint $table) => $table->dropColumn('cambios'));
    }
};
