<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comercial_parametros')) {
            return;
        }

        $actual = DB::table('comercial_parametros')
            ->where('clave', 'JORNADA_SEMANAL_SUB')
            ->first();
        $factor = DB::table('comercial_parametros')
            ->where('clave', 'HORA_NORMAL_FACTOR_SUB')
            ->first();

        if ($actual) {
            if ($factor) {
                DB::table('comercial_parametros')
                    ->where('id', $factor->id)
                    ->update([
                        'editable' => false,
                        'descripcion' => 'Parámetro reemplazado por Jornada Semanal HHEE SUB.',
                        'updated_at' => now(),
                    ]);
            }

            return;
        }

        if ($factor) {
            $factorNumerico = $this->normalizarNumero($factor->valor);
            $jornadaSemanal = $factorNumerico > 0 ? round(7 / (30 * $factorNumerico), 2) : 44;

            DB::table('comercial_parametros')
                ->where('id', $factor->id)
                ->update([
                    'clave' => 'JORNADA_SEMANAL_SUB',
                    'nombre' => 'Jornada Semanal HHEE SUB',
                    'descripcion' => 'Horas semanales usadas para calcular dinámicamente el factor de hora normal HHEE SUB.',
                    'valor' => (string) $jornadaSemanal,
                    'tipo' => 'decimal',
                    'editable' => true,
                    'categoria' => 'HORAS',
                    'valor_anterior' => $factor->valor,
                    'version' => ((int) ($factor->version ?? 1)) + 1,
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);

            return;
        }

        DB::table('comercial_parametros')->insert([
            'clave' => 'JORNADA_SEMANAL_SUB',
            'nombre' => 'Jornada Semanal HHEE SUB',
            'descripcion' => 'Horas semanales usadas para calcular dinámicamente el factor de hora normal HHEE SUB.',
            'valor' => '44',
            'tipo' => 'decimal',
            'editable' => true,
            'categoria' => 'HORAS',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('comercial_parametros')) {
            return;
        }

        $jornada = DB::table('comercial_parametros')
            ->where('clave', 'JORNADA_SEMANAL_SUB')
            ->first();

        if (! $jornada) {
            return;
        }

        $anterior = DB::table('comercial_parametros')
            ->where('clave', 'HORA_NORMAL_FACTOR_SUB')
            ->first();
        $horasSemanales = $this->normalizarNumero($jornada->valor);
        $factor = $horasSemanales > 0 ? round(7 / (30 * $horasSemanales), 6) : 0.005303;

        if ($anterior && (int) $anterior->id !== (int) $jornada->id) {
            DB::table('comercial_parametros')
                ->where('id', $anterior->id)
                ->update([
                    'editable' => true,
                    'valor' => (string) $factor,
                    'updated_at' => now(),
                ]);
            DB::table('comercial_parametros')
                ->where('id', $jornada->id)
                ->delete();

            return;
        }

        DB::table('comercial_parametros')
            ->where('id', $jornada->id)
            ->update([
                'clave' => 'HORA_NORMAL_FACTOR_SUB',
                'nombre' => 'Factor Hora HHEE SUB',
                'descripcion' => 'Factor de hora normal HHEE usado por la plantilla SUB.',
                'valor' => (string) $factor,
                'tipo' => 'decimal',
                'editable' => true,
                'categoria' => 'HORAS',
                'valor_anterior' => $jornada->valor,
                'version' => ((int) ($jornada->version ?? 1)) + 1,
                'updated_at' => now(),
            ]);
    }

    private function normalizarNumero(mixed $valor): float
    {
        $texto = trim((string) $valor);
        $texto = preg_replace('/[^\d,.\-]/', '', $texto) ?? '';

        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? (float) $texto : 0.0;
    }
};
