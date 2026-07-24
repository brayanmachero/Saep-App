<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('observaciones_conducta_ccu', 'turno')) {
            return;
        }

        Schema::table('observaciones_conducta_ccu', function (Blueprint $table) {
            $table->string('turno', 50)->nullable()->after('centro');
            $table->index('turno');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('observaciones_conducta_ccu', 'turno')) {
            return;
        }

        Schema::table('observaciones_conducta_ccu', function (Blueprint $table) {
            $table->dropIndex(['turno']);
            $table->dropColumn('turno');
        });
    }
};
