<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_ingresos', function (Blueprint $table) {
            $table->foreignId('reversado_por')->nullable()->after('registrado_por')->constrained('users')->nullOnDelete();
            $table->timestamp('reversado_en')->nullable()->after('reversado_por');
            $table->string('motivo_reversion', 500)->nullable()->after('reversado_en');

            $table->index('reversado_en');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ingresos', function (Blueprint $table) {
            $table->dropIndex(['reversado_en']);
            $table->dropConstrainedForeignId('reversado_por');
            $table->dropColumn(['reversado_en', 'motivo_reversion']);
        });
    }
};
