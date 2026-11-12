<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_epp', function (Blueprint $table) {
            $table->timestamp('completada_en')->nullable();
            $table->foreignId('completada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_epp', function (Blueprint $table) {
            $table->dropForeign(['completada_por']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['completada_en', 'completada_por']);
        });
    }
};
