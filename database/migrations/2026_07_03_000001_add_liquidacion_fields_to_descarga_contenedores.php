<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descarga_contenedores', function (Blueprint $table) {
            if (!Schema::hasColumn('descarga_contenedores', 'liquidado_por')) {
                $table->foreignId('liquidado_por')
                    ->nullable()
                    ->after('validado_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('descarga_contenedores', 'liquidado_at')) {
                $table->timestamp('liquidado_at')->nullable()->after('liquidado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('descarga_contenedores', function (Blueprint $table) {
            if (Schema::hasColumn('descarga_contenedores', 'liquidado_at')) {
                $table->dropColumn('liquidado_at');
            }

            if (Schema::hasColumn('descarga_contenedores', 'liquidado_por')) {
                $table->dropConstrainedForeignId('liquidado_por');
            }
        });
    }
};
