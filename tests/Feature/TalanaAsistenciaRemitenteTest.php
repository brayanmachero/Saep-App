<?php

namespace Tests\Feature;

use App\Mail\TalanaAsistenciaReporteMail;
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
}
