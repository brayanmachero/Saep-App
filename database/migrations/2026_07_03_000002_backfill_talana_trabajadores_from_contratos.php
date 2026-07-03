<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('talana_trabajadores') || !Schema::hasTable('talana_contratos')) {
            return;
        }

        $centros = Schema::hasTable('centros_costo')
            ? DB::table('centros_costo')->get(['id', 'nombre'])->keyBy(fn ($row) => $this->normalize($row->nombre))
            : collect();

        $cargos = Schema::hasTable('cargos')
            ? DB::table('cargos')->get(['id', 'nombre'])->keyBy(fn ($row) => $this->normalize($row->nombre))
            : collect();

        DB::table('talana_contratos')
            ->whereNotNull('persona_talana_id')
            ->where('finiquitado', false)
            ->where(function ($query) {
                $query->whereNull('hasta')->orWhere('hasta', '>=', now()->toDateString());
            })
            ->orderBy('persona_talana_id')
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->get([
                'id',
                'talana_id',
                'persona_talana_id',
                'persona_nombre',
                'persona_rut',
                'persona_email',
                'tipo_contrato_nombre',
                'desde',
                'hasta',
                'centro_costo_nombre',
                'cargo_nombre',
                'empresa_nombre',
            ])
            ->unique('persona_talana_id')
            ->each(function ($contrato) use ($centros, $cargos) {
                $nombre = trim((string) $contrato->persona_nombre);

                if ($nombre === '') {
                    return;
                }

                $centro = $centros->get($this->normalize($contrato->centro_costo_nombre));
                $cargo = $cargos->get($this->normalize($contrato->cargo_nombre));

                DB::table('talana_trabajadores')->updateOrInsert(
                    ['talana_id' => (string) $contrato->persona_talana_id],
                    [
                        'rut' => $this->cleanRut($contrato->persona_rut),
                        'nombre' => $nombre,
                        'apellido_paterno' => null,
                        'apellido_materno' => null,
                        'email' => $this->blankToNull($contrato->persona_email),
                        'cargo_id' => $cargo?->id,
                        'cargo_nombre' => $this->blankToNull($contrato->cargo_nombre),
                        'centro_costo_id' => $centro?->id,
                        'centro_costo_nombre' => $this->blankToNull($contrato->centro_costo_nombre),
                        'tipo_nomina' => $this->blankToNull($contrato->tipo_contrato_nombre),
                        'razon_social' => $this->blankToNull($contrato->empresa_nombre),
                        'fecha_ingreso' => $contrato->desde,
                        'fecha_termino' => $contrato->hasta,
                        'activo' => true,
                        'origen' => 'talana_contratos',
                        'raw_payload' => json_encode([
                            'contrato_id' => $contrato->id,
                            'contrato_talana_id' => $contrato->talana_id,
                        ], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('talana_trabajadores')) {
            DB::table('talana_trabajadores')->where('origen', 'talana_contratos')->delete();
        }
    }

    private function normalize(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->upper()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function cleanRut(?string $value): ?string
    {
        $clean = strtoupper(preg_replace('/[^0-9kK]/', '', (string) $value));

        return $clean !== '' ? $clean : null;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
};
