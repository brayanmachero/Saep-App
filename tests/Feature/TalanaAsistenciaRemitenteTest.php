<?php

namespace Tests\Feature;

use App\Console\Commands\TalanaReporteAsistencia;
use App\Mail\TalanaAsistenciaReporteMail;
use ReflectionMethod;
use Tests\TestCase;

class TalanaAsistenciaRemitenteTest extends TestCase
{
    public function test_daily_attendance_report_uses_the_official_saep_sender(): void
    {
        config()->set('talana_attendance.from.address', 'notificaciones@saep.cl');
        config()->set('talana_attendance.from.name', 'SAEP · Asistencia');

        $mail = new TalanaAsistenciaReporteMail([
            'centro_costo' => 'LTS QUILICURA',
            'alcance' => 'LTS QUILICURA · SAEP EST',
            'total_alertas' => 0,
            'total_activos' => 0,
            'total_completos' => 0,
            'total_incompletas' => 0,
            'total_sin_marcacion' => 0,
            'total_sin_historial' => 0,
            'total_descanso' => 0,
            'total_ausencias' => 0,
            'total_sin_evaluacion' => 0,
            'total_revision' => 0,
            'total_jornadas_cubiertas' => 0,
            'incompletas' => [],
            'sin_marcacion' => [],
            'sin_historial' => [],
            'descanso' => [],
            'ausencias' => [],
            'sin_evaluacion' => [],
            'revision' => [],
            'completos' => [],
        ], '2026-07-26');

        $envelope = $mail->envelope();

        $this->assertSame('notificaciones@saep.cl', $envelope->from->address);
        $this->assertSame('SAEP · Asistencia', $envelope->from->name);
    }

    public function test_daily_attendance_recipients_are_configured_separately_from_other_talana_alerts(): void
    {
        $this->assertSame('sgarcia@saep.cl', config('talana_attendance.recipients.to'));
        $this->assertSame('jrodriguez@saep.cl,bmachero@saep.cl', config('talana_attendance.recipients.cc'));
    }

    public function test_penon_attendance_report_has_its_own_recipient_and_copies(): void
    {
        $this->assertSame('LTS PEÑON EST', config('services.talana.asistencia_centro_costo_penon'));
        $this->assertSame(1081, config('services.talana.asistencia_empresa_id_penon'));
        $this->assertSame('fortiz@saep.cl', config('services.talana.asistencia_penon_email'));
        $this->assertSame('jrodriguez@saep.cl,bmachero@saep.cl', config('services.talana.asistencia_penon_cc'));
    }

    public function test_penon_copies_are_normalized_without_repeating_the_main_recipient(): void
    {
        $command = app(TalanaReporteAsistencia::class);
        $method = new ReflectionMethod($command, 'destinatariosEnCopia');

        $copias = $method->invoke(
            $command,
            'fortiz@saep.cl',
            'jrodriguez@saep.cl, bmachero@saep.cl, fortiz@saep.cl, correo-invalido'
        );

        $this->assertSame(['jrodriguez@saep.cl', 'bmachero@saep.cl'], $copias);
    }
}
