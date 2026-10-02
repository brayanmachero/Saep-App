<?php

namespace Tests\Unit;

use App\Models\ProgramaSst;
use App\Models\SstActividad;
use App\Models\SstCategoria;
use App\Models\SstSeguimiento;
use App\Models\SstSeguimientoSemana;
use App\Models\User;
use App\Services\CartaGanttPdfReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CartaGanttPdfReportDataTest extends TestCase
{
    public function test_monthly_report_only_counts_selected_month_and_uses_real_calendar_weeks(): void
    {
        $programa = $this->programaConActividades();
        $reporte = (new CartaGanttPdfReportData)->build($programa, 'mensual', 10);

        $this->assertSame([10], $reporte['mesesSeleccionados']);
        $this->assertSame(1, $reporte['actividadesPeriodo']->count());
        $this->assertSame(6, $reporte['totalProgramado']);
        $this->assertSame(3, $reporte['totalRealizado']);
        $this->assertSame(50, $reporte['pct']);
        $this->assertSame([[1, 4], [5, 11], [12, 18], [19, 25], [26, 31]],
            array_values(array_map(fn ($s) => [$s['desde'], $s['hasta']], $reporte['semanasReporte'])));
        $this->assertSame(1, $reporte['semanasPorActividad'][101]['2026-10-01']);
        $this->assertSame(2, $reporte['semanasPorActividad'][101]['2026-10-05']);
        $this->assertSame(0, $reporte['sinSemanaPorActividad'][101]);
    }

    public function test_semester_and_year_only_include_activity_months_in_their_period(): void
    {
        $programa = $this->programaConActividades();
        $servicio = new CartaGanttPdfReportData;

        $primero = $servicio->build($programa, 'semestral', null, 1);
        $segundo = $servicio->build($programa, 'semestral', null, 2);
        $anual = $servicio->build($programa, 'anual');

        $this->assertSame(0, $primero['totalProgramado']);
        $this->assertSame(0, $primero['actividadesPeriodo']->count());
        $this->assertSame(range(7, 12), $segundo['mesesSeleccionados']);
        $this->assertSame(7, $segundo['totalProgramado']);
        $this->assertSame(4, $segundo['totalRealizado']);
        $this->assertSame(7, $anual['totalProgramado']);
    }

    public function test_historical_monthly_progress_is_not_assigned_to_a_guessed_week(): void
    {
        $programa = $this->programaConActividades();
        $actividad = $programa->categorias->first()->actividades->first();
        $actividad->setRelation('seguimientoSemanas', new Collection);

        $reporte = (new CartaGanttPdfReportData)->build($programa, 'mensual', 10);

        $this->assertSame(3, $reporte['totalRealizado']);
        $this->assertSame(3, $reporte['sinSemanaPorActividad'][101]);
        $this->assertSame(0, array_sum($reporte['semanasPorActividad'][101]));
    }

    public function test_pdf_templates_render_in_all_three_formats(): void
    {
        Auth::setUser(new User(['name' => 'Usuario de prueba']));
        $programa = $this->programaConActividades();
        $categoria = $programa->categorias->first();
        $extraActivities = max(0, min(100, (int) getenv('SAEP_PDF_QA_EXTRA_ACTIVITIES')));
        for ($n = 0; $n < 24 + $extraActivities; $n++) {
            $actividad = new SstActividad([
                'nombre' => 'Actividad preventiva mensual ' . ($n + 1),
                'periodicidad' => 'MENSUAL',
                'cantidad_programada' => 6,
                'estado' => 'PENDIENTE',
                'prioridad' => 'ALTA',
            ]);
            $actividad->id = 200 + $n;
            $actividad->setRelation('seguimiento', new Collection([
                new SstSeguimiento(['mes' => 10, 'programado' => true, 'realizado' => false, 'cantidad_realizada' => 0]),
            ]));
            $actividad->setRelation('seguimientoSemanas', new Collection);
            $actividad->setRelation('ocurrencias', new Collection);
            $actividad->setRelation('reprogramaciones', new Collection);
            $actividad->setRelation('categoria', $categoria);
            $categoria->actividades->push($actividad);
        }
        $servicio = new CartaGanttPdfReportData;

        foreach ([['mensual', 10, null], ['semestral', null, 2], ['anual', null, null]] as [$tipo, $mes, $semestre]) {
            $datos = $servicio->build($programa, $tipo, $mes, $semestre);
            $estados = collect($datos['resumenActividades'])->pluck('estado');
            $pdf = Pdf::loadView('pdf.carta_gantt_reporte', array_merge($datos, [
                'cartaGantt' => $programa,
                'mesActual' => 10,
                'totalAct' => $datos['actividadesPeriodo']->count(),
                'completadas' => $estados->filter(fn ($estado) => $estado === 'COMPLETADA')->count(),
                'enProgreso' => $estados->filter(fn ($estado) => $estado === 'EN_PROGRESO')->count(),
                'pendientes' => $estados->filter(fn ($estado) => $estado === 'PENDIENTE')->count(),
                'canceladas' => 0,
                'vencidas' => collect(),
                'reprogramaciones' => collect(),
                'prioridades' => [
                    'ALTA' => $datos['actividadesPeriodo']->where('prioridad', 'ALTA')->count(),
                    'MEDIA' => $datos['actividadesPeriodo']->where('prioridad', 'MEDIA')->count(),
                    'BAJA' => $datos['actividadesPeriodo']->where('prioridad', 'BAJA')->count(),
                ],
            ]))->setPaper('a4', 'landscape')->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ]);

            $pdf->render();
            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
            $canvas->page_text($canvas->get_width() - 105, $canvas->get_height() - 18, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 6.5, [0.29, 0.33, 0.41]);

            $bytes = $pdf->output();
            $this->assertStringStartsWith('%PDF-', $bytes);
            if ($visualQaDir = getenv('SAEP_PDF_VISUAL_QA_DIR')) {
                if (!is_dir($visualQaDir)) {
                    mkdir($visualQaDir, 0775, true);
                }
                file_put_contents($visualQaDir.DIRECTORY_SEPARATOR.$tipo.'.pdf', $bytes);
            }
        }
    }

    private function programaConActividades(): ProgramaSst
    {
        $programa = new ProgramaSst(['anio' => 2026, 'titulo' => 'Prueba SST', 'estado' => 'ACTIVO']);
        $programa->codigo = 'SST-2026-TEST';
        $categoria = new SstCategoria(['nombre' => 'Prevención', 'orden' => 1]);

        $octubre = new SstActividad(['nombre' => 'Inspección mensual', 'periodicidad' => 'MENSUAL', 'cantidad_programada' => 6, 'estado' => 'EN_PROGRESO', 'prioridad' => 'ALTA']);
        $octubre->id = 101;
        $octubre->setRelation('seguimiento', new Collection([
            new SstSeguimiento(['mes' => 10, 'programado' => true, 'realizado' => false, 'cantidad_realizada' => 3]),
        ]));
        $octubre->setRelation('seguimientoSemanas', new Collection([
            new SstSeguimientoSemana(['mes' => 10, 'semana_inicio' => '2026-10-01', 'cantidad_realizada' => 1]),
            new SstSeguimientoSemana(['mes' => 10, 'semana_inicio' => '2026-10-05', 'cantidad_realizada' => 2]),
        ]));
        $octubre->setRelation('ocurrencias', new Collection);
        $octubre->setRelation('reprogramaciones', new Collection);
        $octubre->setRelation('categoria', $categoria);

        $noviembre = new SstActividad(['nombre' => 'Charla', 'periodicidad' => 'MENSUAL', 'cantidad_programada' => 1, 'estado' => 'COMPLETADA', 'prioridad' => 'MEDIA']);
        $noviembre->id = 102;
        $noviembre->setRelation('seguimiento', new Collection([
            new SstSeguimiento(['mes' => 11, 'programado' => true, 'realizado' => true, 'cantidad_realizada' => 1]),
        ]));
        $noviembre->setRelation('seguimientoSemanas', new Collection);
        $noviembre->setRelation('ocurrencias', new Collection);
        $noviembre->setRelation('reprogramaciones', new Collection);
        $noviembre->setRelation('categoria', $categoria);

        $categoria->setRelation('actividades', new Collection([$octubre, $noviembre]));
        $programa->setRelation('categorias', new Collection([$categoria]));
        return $programa;
    }
}
