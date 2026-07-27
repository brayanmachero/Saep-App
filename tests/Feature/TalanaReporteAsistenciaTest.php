<?php

namespace Tests\Feature;

use App\Console\Commands\TalanaReporteAsistencia;
use App\Mail\TalanaAsistenciaReporteMail;
use App\Models\TalanaContrato;
use App\Support\TalanaMarcaDirection;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class TalanaReporteAsistenciaTest extends TestCase
{
    public function test_talana_exit_x_is_normalized_as_a_valid_exit(): void
    {
        $this->assertSame('E', TalanaMarcaDirection::normalize('Entrada'));
        $this->assertSame('S', TalanaMarcaDirection::normalize('X'));
        $this->assertSame('Salida', TalanaMarcaDirection::label('S'));

        [$marcas, $historial] = $this->agrupar([
            $this->marca(10, '2026-07-26T07:00:00-04:00', 'E'),
            $this->marca(10, '2026-07-26T16:00:00-04:00', 'X'),
        ]);

        $this->assertTrue($historial[10]);
        $this->assertSame('completo', $marcas[10]['categoria']);

        $reporte = $this->analizar(
            collect([$this->contrato(10, '2026-01-01')]),
            $marcas,
            [10 => true],
            $historial
        );

        $this->assertSame(1, $reporte['total_completos']);
        $this->assertSame(0, $reporte['total_incompletas']);
        $this->assertSame(0, $reporte['total_alertas']);
    }

    public function test_night_entry_is_completed_by_next_day_exit(): void
    {
        [$marcas, $historial] = $this->agrupar([
            $this->marca(20, '2026-07-26T23:50:00-04:00', 'Entrada'),
            $this->marca(20, '2026-07-27T07:00:00-04:00', 'Salida'),
        ]);

        $this->assertTrue($historial[20]);
        $this->assertSame('completo', $marcas[20]['categoria']);

        $reporte = $this->analizar(
            collect([$this->contrato(20, '2026-01-01')]),
            $marcas,
            [20 => true],
            $historial
        );

        $this->assertSame(1, $reporte['total_completos']);
        $this->assertStringContainsString('día siguiente', $reporte['completos'][0]['marcas']);
    }

    public function test_missing_mark_without_confirmed_workday_is_not_an_alert(): void
    {
        $reporte = $this->analizar(
            collect([$this->contrato(30, '2026-01-01')]),
            [],
            [],
            []
        );

        $this->assertSame(0, $reporte['total_sin_marcacion']);
        $this->assertSame(1, $reporte['total_sin_evaluacion']);
        $this->assertSame(0, $reporte['total_alertas']);
    }

    public function test_complete_marks_without_official_shift_duration_are_not_short_hour_alerts(): void
    {
        [$marcas, $historial] = $this->agrupar([
            $this->marca(40, '2026-07-26T23:50:00-04:00', 'Entrada'),
            $this->marca(40, '2026-07-27T04:15:00-04:00', 'Salida'),
        ]);

        $reporte = $this->analizar(
            collect([$this->contrato(40, '2026-01-01')]),
            $marcas,
            [],
            $historial
        );

        $this->assertSame(1, $reporte['total_completos']);
        $this->assertSame(0, $reporte['total_revision']);
        $this->assertSame(0, $reporte['total_alertas']);
    }

    public function test_email_prioritizes_actions_and_keeps_informational_data_separate(): void
    {
        $mail = new TalanaAsistenciaReporteMail([
            'total_activos' => 10,
            'total_completos' => 2,
            'total_incompletas' => 1,
            'total_sin_marcacion' => 1,
            'total_sin_historial' => 2,
            'total_descanso' => 1,
            'total_ausencias' => 1,
            'total_sin_evaluacion' => 2,
            'total_revision' => 0,
            'total_alertas' => 2,
            'total_jornadas_cubiertas' => 4,
            'incompletas' => [[
                'nombre' => 'María Prueba',
                'rut' => '12.345.678-5',
                'centro_costo' => 'Centro QA',
                'marcas' => '08:00:00 (Entrada)',
            ]],
            'sin_marcacion' => [[
                'nombre' => 'Pedro Prueba',
                'rut' => '12.345.678-5',
                'centro_costo' => 'Centro QA',
                'motivo' => 'Jornada confirmada sin marca registrada',
            ]],
            'sin_historial' => [],
            'descanso' => [],
            'ausencias' => [],
            'sin_evaluacion' => [],
            'revision' => [],
            'completos' => [],
        ], '2026-07-26');

        $html = $mail->render();

        $this->assertStringContainsString('Qué se debe revisar', $html);
        $this->assertStringContainsString('Información que no requiere acción inmediata', $html);
        $this->assertStringContainsString('Sin jornada informada', $html);
        $this->assertStringContainsString('Detalle completo en el Excel adjunto', $html);
        $this->assertStringContainsString('María Prueba', $html);
        $this->assertStringContainsString('Validar y completar', $html);
    }

    private function agrupar(array $raw): array
    {
        return $this->invoke('agruparMarcas', [$raw, Carbon::parse('2026-07-26', 'America/Santiago')]);
    }

    private function analizar(Collection $contratos, array $marcas, array $jornadas, array $historial): array
    {
        return $this->invoke('analizarTrabajadores', [
            $contratos,
            $marcas,
            $jornadas,
            $historial,
            [],
            Carbon::parse('2026-07-26', 'America/Santiago'),
            60,
            [],
            16.0,
            7.0,
            false,
        ]);
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $command = app(TalanaReporteAsistencia::class);
        $reflection = new ReflectionMethod($command, $method);

        return $reflection->invokeArgs($command, $arguments);
    }

    private function contrato(int $personaId, string $desde): TalanaContrato
    {
        return new TalanaContrato([
            'persona_talana_id' => $personaId,
            'persona_nombre' => "Trabajador {$personaId}",
            'persona_rut' => '12.345.678-5',
            'empresa_nombre' => 'SAEP',
            'centro_costo_nombre' => 'Centro QA',
            'cargo_nombre' => 'Operario QA',
            'desde' => $desde,
            'finiquitado' => false,
        ]);
    }

    private function marca(int $personaId, string $timestamp, string $direction): array
    {
        return [
            'person' => [
                'id' => $personaId,
                'nombre' => 'Trabajador',
                'apellidoPaterno' => 'QA',
                'rut' => '12.345.678-5',
            ],
            'TS' => $timestamp,
            'direction' => $direction,
        ];
    }
}
