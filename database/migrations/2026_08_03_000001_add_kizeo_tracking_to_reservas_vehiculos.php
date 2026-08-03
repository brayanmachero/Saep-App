<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas_vehiculos', function (Blueprint $table) {
            $table->string('kizeo_form_id', 50)->nullable()->after('calendar_last_error');
            $table->string('kizeo_data_id', 100)->nullable()->after('kizeo_form_id');
            $table->timestamp('kizeo_pushed_at')->nullable()->after('kizeo_data_id');
            $table->timestamp('kizeo_synced_at')->nullable()->after('kizeo_pushed_at');
            $table->string('kizeo_last_error', 1000)->nullable()->after('kizeo_synced_at');
            $table->string('kizeo_entrega_sharepoint_path', 1000)->nullable()->after('kizeo_last_error');
            $table->string('kizeo_devolucion_sharepoint_path', 1000)->nullable()->after('kizeo_entrega_sharepoint_path');
            $table->timestamp('entregada_at')->nullable()->after('kizeo_devolucion_sharepoint_path');
            $table->timestamp('devuelta_at')->nullable()->after('entregada_at');

            $table->index('kizeo_data_id');
            $table->index(['kizeo_form_id', 'kizeo_pushed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reservas_vehiculos', function (Blueprint $table) {
            $table->dropIndex(['kizeo_data_id']);
            $table->dropIndex(['kizeo_form_id', 'kizeo_pushed_at']);
            $table->dropColumn([
                'kizeo_form_id',
                'kizeo_data_id',
                'kizeo_pushed_at',
                'kizeo_synced_at',
                'kizeo_last_error',
                'kizeo_entrega_sharepoint_path',
                'kizeo_devolucion_sharepoint_path',
                'entregada_at',
                'devuelta_at',
            ]);
        });
    }
};
