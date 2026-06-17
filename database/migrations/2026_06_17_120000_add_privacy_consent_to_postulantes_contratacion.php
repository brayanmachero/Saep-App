<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->boolean('consentimiento_datos')->default(false)->after('observaciones');
            $table->timestamp('consentimiento_at')->nullable()->after('consentimiento_datos');
            $table->string('consentimiento_version', 50)->nullable()->after('consentimiento_at');
            $table->text('consentimiento_texto')->nullable()->after('consentimiento_version');
            $table->string('consentimiento_ip', 45)->nullable()->after('consentimiento_texto');
            $table->string('consentimiento_user_agent', 500)->nullable()->after('consentimiento_ip');

            $table->index('consentimiento_at', 'idx_postulantes_consentimiento_at');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes_contratacion', function (Blueprint $table) {
            $table->dropIndex('idx_postulantes_consentimiento_at');
            $table->dropColumn([
                'consentimiento_datos',
                'consentimiento_at',
                'consentimiento_version',
                'consentimiento_texto',
                'consentimiento_ip',
                'consentimiento_user_agent',
            ]);
        });
    }
};
