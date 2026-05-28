<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'contratacion_emails_notificacion'],
            [
                'clave'       => 'contratacion_emails_notificacion',
                'valor'       => '',
                'tipo'        => 'TEXT',
                'categoria'   => 'contratacion',
                'descripcion' => 'Correos separados por coma para notificaciones de nuevos postulantes en el portal de contratación',
                'editable'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('configuraciones')->where('clave', 'contratacion_emails_notificacion')->delete();
    }
};
