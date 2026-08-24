<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talana_kizeo_personal_items') || Schema::hasColumn('talana_kizeo_personal_items', 'proximo_intento_en')) {
            return;
        }

        Schema::table('talana_kizeo_personal_items', function (Blueprint $table) {
            $table->timestamp('proximo_intento_en')->nullable()->after('ultimo_error');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('talana_kizeo_personal_items') && Schema::hasColumn('talana_kizeo_personal_items', 'proximo_intento_en')) {
            Schema::table('talana_kizeo_personal_items', function (Blueprint $table) {
                $table->dropColumn('proximo_intento_en');
            });
        }
    }
};
