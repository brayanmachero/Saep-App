<?php

namespace App\Modules\Comercial\database\seeders;

use App\Modules\Comercial\Models\Modalidad;
use App\Modules\Comercial\Models\Parametro;
use Illuminate\Database\Seeder;

class ComercialSeeder extends Seeder
{
    public function run(): void
    {
        Modalidad::firstOrCreate(['codigo' => 'EST'], [
            'nombre' => 'Servicios Transitorios (EST)',
            'descripcion' => 'Cotización de servicios transitorios según plantilla EST SAEP',
            'margen_operacional' => 10.00,
            'sis_porcentaje' => 1.49,
            'mutual_porcentaje' => 1.27,
            'cesantia_porcentaje' => 3.00,
            'factor_vacaciones' => 1.0,
            'refprev_porcentaje' => 1.00,
            'estado' => 'activo',
        ]);

        Modalidad::firstOrCreate(['codigo' => 'SUB'], [
            'nombre' => 'Subcontratación (SUB)',
            'descripcion' => 'Cotización de subcontratación según plantilla SUB SAEP',
            'margen_operacional' => 14.00,
            'sis_porcentaje' => 1.78,
            'mutual_porcentaje' => 2.50,
            'cesantia_porcentaje' => 3.00,
            'factor_vacaciones' => 1.75,
            'refprev_porcentaje' => 1.00,
            'estado' => 'activo',
        ]);

        foreach ($this->parametros() as $parametro) {
            Parametro::firstOrCreate(['clave' => $parametro['clave']], $parametro);
        }
    }

    private function parametros(): array
    {
        return [
            ['clave' => 'UF', 'nombre' => 'Unidad de Fomento', 'descripcion' => 'Valor vigente de UF. Se puede actualizar por integración configurada.', 'valor' => '0', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'GOBIERNO', 'version' => 1],
            ['clave' => 'SUELDO_MINIMO', 'nombre' => 'Sueldo Mínimo Legal', 'descripcion' => 'Ingreso mínimo mensual vigente desde 01-01-2026 para trabajadores mayores de 18 y hasta 65 años. Sin API oficial configurada queda editable.', 'valor' => '539000', 'tipo' => 'integer', 'editable' => true, 'categoria' => 'GOBIERNO', 'version' => 1],
            ['clave' => 'IPC', 'nombre' => 'IPC Mensual', 'descripcion' => 'Variación mensual IPC usada para reajustes comerciales.', 'valor' => '0', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'GOBIERNO', 'version' => 1],

            ['clave' => 'GRATIFICACION_TOPE', 'nombre' => 'Tope Gratificación', 'descripcion' => 'Tope aplicado a gratificación legal de la planilla.', 'valor' => '209396', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS', 'version' => 1],
            ['clave' => 'IMPOSICIONES_PORCENTAJE', 'nombre' => 'Imposiciones Trabajador', 'descripcion' => 'Porcentaje de imposiciones usado para alcance líquido y renta tributable.', 'valor' => '19', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS', 'version' => 1],
            ['clave' => 'IMPUESTO_UNICO_FACTOR', 'nombre' => 'Factor Impuesto Único', 'descripcion' => 'Factor de impuesto único utilizado en la plantilla base.', 'valor' => '4', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS', 'version' => 1],
            ['clave' => 'IMPUESTO_UNICO_REBAJA', 'nombre' => 'Rebaja Impuesto Único', 'descripcion' => 'Rebaja fija aplicada en el cálculo de impuesto único de la plantilla.', 'valor' => '36338.76', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS', 'version' => 1],

            ['clave' => 'MARGEN_EST', 'nombre' => 'Margen EST', 'descripcion' => 'Margen operacional para servicios transitorios.', 'valor' => '10', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'MARGENES', 'version' => 1],
            ['clave' => 'MARGEN_SUB', 'nombre' => 'Margen SUB', 'descripcion' => 'Margen operacional para subcontratación.', 'valor' => '14', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'MARGENES', 'version' => 1],

            ['clave' => 'REFPREV_EST', 'nombre' => 'REFPREV EST', 'descripcion' => 'Tasa REFPREV modalidad EST.', 'valor' => '1', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_EST', 'version' => 1],
            ['clave' => 'SIS_EST', 'nombre' => 'SIS EST', 'descripcion' => 'Seguro de Invalidez y Sobrevivencia modalidad EST.', 'valor' => '1.49', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_EST', 'version' => 1],
            ['clave' => 'MUTUAL_EST', 'nombre' => 'Mutual EST', 'descripcion' => 'Mutual Seguridad I.S.T. modalidad EST.', 'valor' => '1.27', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_EST', 'version' => 1],
            ['clave' => 'CESANTIA_EST', 'nombre' => 'Seguro Cesantía EST', 'descripcion' => 'Tasa de seguro de cesantía modalidad EST.', 'valor' => '3', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_EST', 'version' => 1],
            ['clave' => 'VACACIONES_DIAS_EST', 'nombre' => 'Días Vacaciones EST', 'descripcion' => 'Días usados en provisión de vacaciones EST.', 'valor' => '21', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_EST', 'version' => 1],
            ['clave' => 'GASTOS_ADMIN_EST', 'nombre' => 'Gastos Administración EST', 'descripcion' => 'Porcentaje de gastos de administración EST.', 'valor' => '3', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_EST', 'version' => 1],
            ['clave' => 'AGUINALDO_EST', 'nombre' => 'Otros Beneficios EST', 'descripcion' => 'Valor por defecto para otros beneficios o aguinaldos EST.', 'valor' => '5000', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_EST', 'version' => 1],
            ['clave' => 'HORAS_MENSUALES_EST', 'nombre' => 'Horas Mensuales EST', 'descripcion' => 'Divisor de hora normal EST.', 'valor' => '180', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'HORAS', 'version' => 1],
            ['clave' => 'HORAS_HHEE_EST', 'nombre' => 'Horas HHEE EST', 'descripcion' => 'Divisor para cálculo de horas extra EST.', 'valor' => '176', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'HORAS', 'version' => 1],

            ['clave' => 'REFPREV_SUB', 'nombre' => 'REFPREV SUB', 'descripcion' => 'Tasa REFPREV modalidad SUB.', 'valor' => '1', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_SUB', 'version' => 1],
            ['clave' => 'SIS_SUB', 'nombre' => 'SIS SUB', 'descripcion' => 'Seguro de Invalidez y Sobrevivencia modalidad SUB.', 'valor' => '1.78', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_SUB', 'version' => 1],
            ['clave' => 'MUTUAL_SUB', 'nombre' => 'Mutual SUB', 'descripcion' => 'Mutual Seguridad I.S.T. modalidad SUB.', 'valor' => '2.5', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_SUB', 'version' => 1],
            ['clave' => 'CESANTIA_SUB', 'nombre' => 'Seguro Cesantía SUB', 'descripcion' => 'Tasa de seguro de cesantía modalidad SUB.', 'valor' => '3', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'TASAS_SUB', 'version' => 1],
            ['clave' => 'VACACIONES_FACTOR_SUB', 'nombre' => 'Factor Vacaciones SUB', 'descripcion' => 'Factor aplicado a provisión de vacaciones SUB.', 'valor' => '1.75', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_SUB', 'version' => 1],
            ['clave' => 'INDEMNIZACION_MESES_SUB', 'nombre' => 'Meses Indemnización SUB', 'descripcion' => 'Divisor usado en provisión de indemnizaciones SUB.', 'valor' => '12', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_SUB', 'version' => 1],
            ['clave' => 'GASTOS_ADMIN_SUB', 'nombre' => 'Gastos Administración SUB', 'descripcion' => 'Porcentaje de gastos de administración SUB.', 'valor' => '3', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_SUB', 'version' => 1],
            ['clave' => 'AGUINALDO_SUB', 'nombre' => 'Otros Beneficios SUB', 'descripcion' => 'Valor por defecto para otros beneficios o aguinaldos SUB.', 'valor' => '5000', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'FORMULAS_SUB', 'version' => 1],
            ['clave' => 'HORAS_MENSUALES_SUB', 'nombre' => 'Horas Mensuales SUB', 'descripcion' => 'Divisor de hora normal SUB.', 'valor' => '180', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'HORAS', 'version' => 1],
            ['clave' => 'JORNADA_SEMANAL_SUB', 'nombre' => 'Jornada Semanal HHEE SUB', 'descripcion' => 'Horas semanales usadas para calcular dinámicamente el factor de hora normal HHEE SUB.', 'valor' => '44', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'HORAS', 'version' => 1],

            ['clave' => 'UNIFORME_POLAR', 'nombre' => 'Polar', 'descripcion' => 'Precio referencial uniforme.', 'valor' => '11500', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'UNIFORMES', 'version' => 1],
            ['clave' => 'UNIFORME_POLERA_PIQUE', 'nombre' => 'Polera Piqué', 'descripcion' => 'Precio referencial uniforme.', 'valor' => '9000', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'UNIFORMES', 'version' => 1],
            ['clave' => 'UNIFORME_PANTALON', 'nombre' => 'Pantalón', 'descripcion' => 'Precio referencial uniforme.', 'valor' => '9800', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'UNIFORMES', 'version' => 1],
            ['clave' => 'UNIFORME_ZAPATO', 'nombre' => 'Zapato Quebec', 'descripcion' => 'Precio referencial uniforme.', 'valor' => '23300', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'UNIFORMES', 'version' => 1],
            ['clave' => 'UNIFORME_CASCO', 'nombre' => 'Casco', 'descripcion' => 'Precio referencial uniforme.', 'valor' => '3400', 'tipo' => 'decimal', 'editable' => true, 'categoria' => 'UNIFORMES', 'version' => 1],
        ];
    }
}
