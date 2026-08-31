<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talana_contratos') && ! Schema::hasColumn('talana_contratos', 'persona_fecha_nacimiento')) {
            Schema::table('talana_contratos', function (Blueprint $table) {
                $table->date('persona_fecha_nacimiento')->nullable()->after('persona_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('talana_contratos') && Schema::hasColumn('talana_contratos', 'persona_fecha_nacimiento')) {
            Schema::table('talana_contratos', function (Blueprint $table) {
                $table->dropColumn('persona_fecha_nacimiento');
            });
        }
    }
};
