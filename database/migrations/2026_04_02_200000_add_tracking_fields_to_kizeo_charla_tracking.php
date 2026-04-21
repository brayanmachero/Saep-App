<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // En MySQL/MariaDB se amplía el enum; en SQLite no es necesario.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE kizeo_charla_tracking MODIFY estado ENUM('completado','pendiente','transferido','recuperado') DEFAULT 'pendiente'");
        }

        Schema::table('kizeo_charla_tracking', function (Blueprint $table) {
            $table->string('estatus_kizeo', 30)->nullable()->after('estado')
                  ->comment('Estado Kizeo: registrado, transferido, recuperado, terminado');
            $table->string('titulo_actividad', 300)->nullable()->after('asignado_a_id');
            $table->string('lugar', 200)->nullable()->after('titulo_actividad');
            $table->dateTime('fecha_asignacion')->nullable()->after('fecha_creacion')
                  ->comment('Fecha en que se transfirió/asignó');
            $table->string('origin_answer', 20)->nullable()->after('fecha_respuesta')
                  ->comment('Origen: android, ios, csv, web');
            $table->string('direction', 30)->nullable()->after('origin_answer')
                  ->comment('Dirección Kizeo: null, pull');

            $table->index('asignado_por_id');
            $table->index('asignado_a_id');
            $table->index('estatus_kizeo');
        });
    }

    public function down(): void
    {
        Schema::table('kizeo_charla_tracking', function (Blueprint $table) {
            $table->dropIndex(['asignado_por_id']);
            $table->dropIndex(['asignado_a_id']);
            $table->dropIndex(['estatus_kizeo']);
            $table->dropColumn([
                'estatus_kizeo', 'titulo_actividad', 'lugar',
                'fecha_asignacion', 'origin_answer', 'direction',
            ]);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE kizeo_charla_tracking MODIFY estado ENUM('completado','pendiente') DEFAULT 'pendiente'");
        }
    }
};
