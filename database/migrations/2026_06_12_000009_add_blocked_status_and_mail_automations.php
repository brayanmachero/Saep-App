<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mail_logs MODIFY status ENUM('sent','failed','blocked') NOT NULL DEFAULT 'sent'");
        }

        $now = now();
        $automations = [
            'BienvenidaUsuarioMail' => 'Bienvenida de usuario',
            'PasswordResetMail' => 'Restablecimiento de clave',
            'ContratacionAcuseReciboMail' => 'Acuse postulante',
            'ContratacionNuevoPostulanteMail' => 'Nuevo postulante RRHH',
            'ComercialCotizacionMail' => 'Cotizacion comercial',
            'RespuestaCreadaMail' => 'Formulario pendiente',
            'RespuestaFormularioMail' => 'Confirmacion formulario',
            'RespuestaAprobadaMail' => 'Resultado aprobacion',
            'LeyKarinAcuseReciboMail' => 'Acuse Ley Karin',
            'LeyKarinDenunciaMail' => 'Nueva denuncia Ley Karin',
            'LeyKarinResolucionMail' => 'Resolucion Ley Karin',
            'KanbanTareaAsignadaMail' => 'Tarea Kanban asignada',
            'KanbanVencimientoMail' => 'Vencimiento Kanban',
            'SstActividadAlertaMail' => 'Alertas SST',
            'CharlaTrackingReporteMail' => 'Reporte charlas SST',
            'StopReporteMail' => 'Reporte STOP',
            'VehiculoEntregaMail' => 'Acta entrega vehiculo',
            'VehiculoDevolucionMail' => 'Acta devolucion vehiculo',
        ];

        foreach ($automations as $key => $label) {
            $clave = 'mail_auto_'.$key.'_enabled';
            $exists = DB::table('configuraciones')->where('clave', $clave)->exists();

            if (! $exists) {
                DB::table('configuraciones')->insert([
                    'clave' => $clave,
                    'valor' => 'true',
                    'tipo' => 'BOOLEAN',
                    'categoria' => 'mail_automations',
                    'descripcion' => 'Automatizacion email: '.$label,
                    'editable' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('configuraciones')->where('clave', $clave)->update([
                'tipo' => 'BOOLEAN',
                'categoria' => 'mail_automations',
                'descripcion' => 'Automatizacion email: '.$label,
                'editable' => true,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('mail_logs')->where('status', 'blocked')->update([
            'status' => 'failed',
            'updated_at' => now(),
        ]);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mail_logs MODIFY status ENUM('sent','failed') NOT NULL DEFAULT 'sent'");
        }

        DB::table('configuraciones')->where('clave', 'like', 'mail_auto_%_enabled')->delete();
    }
};
