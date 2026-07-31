<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas_vehiculos', function (Blueprint $table) {
            $table->string('calendar_event_id', 255)->nullable()->after('estado');
            $table->timestamp('calendar_synced_at')->nullable()->after('calendar_event_id');
            $table->string('calendar_last_error', 1000)->nullable()->after('calendar_synced_at');

            $table->index('calendar_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservas_vehiculos', function (Blueprint $table) {
            $table->dropIndex(['calendar_event_id']);
            $table->dropColumn(['calendar_event_id', 'calendar_synced_at', 'calendar_last_error']);
        });
    }
};
