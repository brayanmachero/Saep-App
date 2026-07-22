<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'contratacion_cierre_diario_emails'],
            [
                'valor' => 'mmejias@saep.cl, bmachero@saep.cl',
                'tipo' => 'TEXT',
                'categoria' => 'contratacion',
                'descripcion' => 'Destinatarios del cierre diario de postulantes RRHH.',
                'editable' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'mail_auto_ContratacionCierreDiarioMail_enabled'],
            [
                'valor' => 'true',
                'tipo' => 'BOOLEAN',
                'categoria' => 'mail_automations',
                'descripcion' => 'Automatizacion email: Cierre diario postulantes',
                'editable' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->whereIn('clave', [
                'contratacion_cierre_diario_emails',
                'mail_auto_ContratacionCierreDiarioMail_enabled',
            ])
            ->delete();
    }
};
