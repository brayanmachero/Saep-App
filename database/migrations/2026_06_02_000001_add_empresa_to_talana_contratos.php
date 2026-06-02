<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talana_contratos', function (Blueprint $table) {
            $table->integer('empresa_id')->nullable()->after('talana_id');
            $table->string('empresa_nombre', 100)->nullable()->after('empresa_id');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::table('talana_contratos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropColumn(['empresa_id', 'empresa_nombre']);
        });
    }
};
