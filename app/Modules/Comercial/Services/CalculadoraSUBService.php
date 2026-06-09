<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\Parametro;

class CalculadoraSUBService
{
    public function calcular(array $datos): array
    {
        $i = $this->normalizarEntrada($datos);

        $gratificacionTope = (float) Parametro::valor('GRATIFICACION_TOPE', 209396);
        $imposicionesPct = (float) Parametro::valor('IMPOSICIONES_PORCENTAJE', 19) / 100;
        $impuestoFactor = (float) Parametro::valor('IMPUESTO_UNICO_FACTOR', 4) / 100;
        $impuestoRebaja = (float) Parametro::valor('IMPUESTO_UNICO_REBAJA', 36338.76);

        $refprevPct = (float) Parametro::valor('REFPREV_SUB', 1);
        $sisPct = (float) Parametro::valor('SIS_SUB', 1.78);
        $mutualPct = (float) Parametro::valor('MUTUAL_SUB', 2.5);
        $cesantiaPct = (float) Parametro::valor('CESANTIA_SUB', 3);
        $vacacionesFactor = (float) Parametro::valor('VACACIONES_FACTOR_SUB', 1.75);
        $indemnizacionMeses = (float) Parametro::valor('INDEMNIZACION_MESES_SUB', 12);
        $gastosAdminPct = (float) Parametro::valor('GASTOS_ADMIN_SUB', 3);
        $margenPct = (float) Parametro::valor('MARGEN_SUB', 14);
        $horasMensuales = (float) Parametro::valor('HORAS_MENSUALES_SUB', 180);
        $horaNormalFactorHhee = (float) Parametro::valor('HORA_NORMAL_FACTOR_SUB', 0.005303);

        $baseGratificacion = $i['sueldo_base'] + $i['bono_asistencia'] + $i['bono_compromiso'] + $i['otros_haberes'];
        $gratificacion = min($baseGratificacion * 0.25, $gratificacionTope);
        $totalImponible = $baseGratificacion + $gratificacion;
        $totalNoImponible = $i['asignacion_movilizacion'] + $i['asignacion_colacion'];
        $totalHaberes = $totalImponible + $totalNoImponible;
        $imposiciones = $totalImponible * $imposicionesPct;
        $rentaTributable = $totalImponible - $imposiciones;
        $impuestoUnico = max(($rentaTributable * $impuestoFactor) - $impuestoRebaja, 0);
        $alcanceLiquido = $totalHaberes - $imposiciones - $impuestoUnico;

        $refprev = $totalImponible * ($refprevPct / 100);
        $sis = $totalImponible * ($sisPct / 100);
        $mutual = $totalImponible * ($mutualPct / 100);
        $seguroCesantia = $totalImponible * ($cesantiaPct / 100);
        $baseProvisiones = $totalImponible + $sis + $mutual + $seguroCesantia + $refprev;
        $provisionVacaciones = ($baseProvisiones / 30) * $vacacionesFactor;
        $provisionIndemnizaciones = $indemnizacionMeses > 0 ? $baseProvisiones / $indemnizacionMeses : 0;

        $gastosAdminBase = $totalHaberes
            + $i['uniformes_total']
            + $i['servicios_casino']
            + $refprev
            + $sis
            + $mutual
            + $seguroCesantia
            + $provisionVacaciones
            + $provisionIndemnizaciones
            + $i['seguro_accidentes']
            + $i['otros_gastos']
            + $i['otros_beneficios'];
        $gastosAdministracion = $gastosAdminBase * ($gastosAdminPct / 100);

        $totalCotizaciones = $refprev + $sis + $mutual + $seguroCesantia;
        $totalProvisiones = $provisionVacaciones + $provisionIndemnizaciones;
        $totalGastos = $i['uniformes_total'] + $i['servicios_casino'] + $i['seguro_accidentes'] + $i['otros_gastos'] + $i['otros_beneficios'] + $gastosAdministracion;
        $costoBruto = $totalHaberes + $totalCotizaciones + $totalProvisiones + $totalGastos;
        $margen = $costoBruto * ($margenPct / 100);
        $precioVenta = $costoBruto + $margen;

        $horaNormal = $horasMensuales > 0 ? $precioVenta / $horasMensuales : 0;
        $horaNormalHhee = $precioVenta * $horaNormalFactorHhee;
        $horaExtra50 = $horaNormalHhee * 1.5;
        $horaExtra100 = $horaNormalHhee * 2;

        $resumen = compact(
            'baseGratificacion',
            'gratificacion',
            'totalImponible',
            'totalNoImponible',
            'totalHaberes',
            'imposiciones',
            'rentaTributable',
            'impuestoUnico',
            'alcanceLiquido',
            'refprev',
            'sis',
            'mutual',
            'seguroCesantia',
            'provisionVacaciones',
            'provisionIndemnizaciones',
            'gastosAdministracion',
            'costoBruto',
            'margen',
            'precioVenta',
            'horaNormal',
            'horaExtra50',
            'horaExtra100'
        );

        return [
            'modalidad' => 'SUB',
            'total_remuneraciones' => round($totalHaberes, 2),
            'total_cotizaciones' => round($totalCotizaciones, 2),
            'total_provisiones' => round($totalProvisiones, 2),
            'total_gastos' => round($totalGastos, 2),
            'subtotal' => round($costoBruto, 2),
            'margen_porcentaje' => $margenPct,
            'margen' => round($margen, 2),
            'precio_venta' => round($precioVenta, 2),
            'detalles' => $this->construirDetalles($i, $resumen, [
                'refprev' => $refprevPct,
                'sis' => $sisPct,
                'mutual' => $mutualPct,
                'cesantia' => $cesantiaPct,
                'gastos_admin' => $gastosAdminPct,
                'margen' => $margenPct,
            ]),
            'uniformes' => $i['uniformes'],
            'resumen_excel' => array_map(fn ($v) => round((float) $v, 2), $resumen),
            'horas' => [
                'normal' => round($horaNormal, 2),
                'normal_hhee' => round($horaNormalHhee, 2),
                'extra_50' => round($horaExtra50, 2),
                'extra_100' => round($horaExtra100, 2),
            ],
        ];
    }

    private function normalizarEntrada(array $datos): array
    {
        $base = [
            'sueldo_base' => 0.0,
            'bono_asistencia' => 0.0,
            'bono_compromiso' => 0.0,
            'otros_haberes' => 0.0,
            'asignacion_movilizacion' => (float) ($datos['asignacion_movilizacion'] ?? 0),
            'asignacion_colacion' => (float) ($datos['asignacion_colacion'] ?? 0),
            'servicios_casino' => (float) ($datos['servicios_casino'] ?? 0),
            'seguro_accidentes' => (float) ($datos['seguro_accidentes'] ?? 0),
            'otros_gastos' => (float) ($datos['otros_gastos'] ?? 0),
            'otros_beneficios' => (float) ($datos['otros_beneficios'] ?? Parametro::valor('AGUINALDO_SUB', 5000)),
        ];

        foreach ($datos['remuneraciones'] ?? [] as $item) {
            $concepto = $this->normalizarTexto((string) ($item['concepto'] ?? ''));
            $valor = (float) ($item['valor'] ?? 0);

            if (str_contains($concepto, 'sueldo')) {
                $base['sueldo_base'] += $valor;
            } elseif (str_contains($concepto, 'asistencia')) {
                $base['bono_asistencia'] += $valor;
            } elseif (str_contains($concepto, 'compromiso')) {
                $base['bono_compromiso'] += $valor;
            } elseif (str_contains($concepto, 'movil')) {
                $base['asignacion_movilizacion'] += $valor;
            } elseif (str_contains($concepto, 'colacion') || str_contains($concepto, 'colaci')) {
                $base['asignacion_colacion'] += $valor;
            } elseif (! str_contains($concepto, 'gratificacion') && ! str_contains($concepto, 'gratificaci')) {
                $base['otros_haberes'] += $valor;
            }
        }

        $uniformes = collect($datos['uniformes'] ?? [])
            ->filter(fn ($item) => ! empty($item['descripcion']) && (int) ($item['cantidad'] ?? 0) > 0)
            ->map(fn ($item) => [
                'descripcion' => $item['descripcion'],
                'cantidad' => (int) ($item['cantidad'] ?? 0),
                'precio_unitario' => (float) ($item['precio_unitario'] ?? 0),
            ])
            ->values()
            ->all();

        $base['uniformes'] = $uniformes;
        $base['uniformes_total'] = array_reduce($uniformes, fn ($carry, $item) => $carry + ($item['cantidad'] * $item['precio_unitario']), 0.0);

        return $base;
    }

    private function construirDetalles(array $i, array $r, array $tasas): array
    {
        return [
            $this->detalle('remuneracion', 'Sueldo Base', $i['sueldo_base'], null, $i['sueldo_base'], 10),
            $this->detalle('remuneracion', 'Bono Asistencia', $i['bono_asistencia'], null, $i['bono_asistencia'], 20),
            $this->detalle('remuneracion', 'Bono Compromiso', $i['bono_compromiso'], null, $i['bono_compromiso'], 30),
            $this->detalle('remuneracion', 'Otros Haberes', $i['otros_haberes'], null, $i['otros_haberes'], 40),
            $this->detalle('remuneracion', 'Gratificación Legal', $r['baseGratificacion'], 25, $r['gratificacion'], 50, 'MIN(SUMA haberes imponibles * 25%, tope legal)'),
            $this->detalle('remuneracion', 'Asignación Movilización', $i['asignacion_movilizacion'], null, $i['asignacion_movilizacion'], 60),
            $this->detalle('remuneracion', 'Asignación Colación', $i['asignacion_colacion'], null, $i['asignacion_colacion'], 70),
            $this->detalle('cotizacion', 'REFPREV', $r['totalImponible'], $tasas['refprev'], $r['refprev'], 100),
            $this->detalle('cotizacion', 'SIS', $r['totalImponible'], $tasas['sis'], $r['sis'], 110),
            $this->detalle('cotizacion', 'Mutual Seguridad I.S.T.', $r['totalImponible'], $tasas['mutual'], $r['mutual'], 120),
            $this->detalle('cotizacion', 'Seguro Cesantía', $r['totalImponible'], $tasas['cesantia'], $r['seguroCesantia'], 130),
            $this->detalle('provision', 'Provisión Vacaciones', $r['totalImponible'], null, $r['provisionVacaciones'], 200, '((Total imponible + ISES) / 30) * factor vacaciones SUB'),
            $this->detalle('provision', 'Provisión Indemnizaciones', $r['totalImponible'], null, $r['provisionIndemnizaciones'], 210, '(Total imponible + ISES) / meses indemnización'),
            $this->detalle('gasto', 'Uniformes', $i['uniformes_total'], null, $i['uniformes_total'], 300),
            $this->detalle('gasto', 'Servicios de Casino', $i['servicios_casino'], null, $i['servicios_casino'], 310),
            $this->detalle('gasto', 'Seguro Accidentes Personales', $i['seguro_accidentes'], null, $i['seguro_accidentes'], 320),
            $this->detalle('gasto', 'Otros Gastos', $i['otros_gastos'], null, $i['otros_gastos'], 330),
            $this->detalle('gasto', 'Otros Beneficios', $i['otros_beneficios'], null, $i['otros_beneficios'], 340),
            $this->detalle('gasto', 'Gastos de Administración', $r['costoBruto'] - $r['gastosAdministracion'], $tasas['gastos_admin'], $r['gastosAdministracion'], 350),
            $this->detalle('margen', 'Margen Operacional', $r['costoBruto'], $tasas['margen'], $r['margen'], 400),
        ];
    }

    private function detalle(string $tipo, string $concepto, float $base, ?float $porcentaje, float $valor, int $orden, ?string $formula = null): array
    {
        return [
            'tipo' => $tipo,
            'concepto' => $concepto,
            'valor_base' => round($base, 2),
            'porcentaje' => $porcentaje,
            'valor' => round($valor, 2),
            'orden' => $orden,
            'formula' => $formula ? ['descripcion' => $formula] : null,
        ];
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $texto);

        return $texto;
    }
}
