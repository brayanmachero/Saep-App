<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formularios', function (Blueprint $table) {
            $table->boolean('enviar_email_respuesta')->default(false)->after('genera_pdf');
            $table->text('email_notificacion')->nullable()->after('enviar_email_respuesta');
        });
    }

    public function down(): void
    {
        Schema::table('formularios', function (Blueprint $table) {
            $table->dropColumn(['enviar_email_respuesta', 'email_notificacion']);
        });
    }
};
