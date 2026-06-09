<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comercial_parametros') || ! Schema::hasColumn('comercial_parametros', 'actualizado_por')) {
            return;
        }

        Schema::table('comercial_parametros', function (Blueprint $table) {
            $table->dropForeign('comercial_parametros_actualizado_por_foreign');
            $table->foreignId('actualizado_por')->nullable()->change();
            $table->foreign('actualizado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comercial_parametros') || ! Schema::hasColumn('comercial_parametros', 'actualizado_por')) {
            return;
        }

        Schema::table('comercial_parametros', function (Blueprint $table) {
            $table->dropForeign('comercial_parametros_actualizado_por_foreign');
            $table->foreignId('actualizado_por')->nullable()->change();
            $table->foreign('actualizado_por')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
