<?php

namespace App\Services;

use App\Models\InventarioCentroCosto;
use App\Models\InventarioCoordinador;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventarioOperationalMasterService
{
    /**
     * @return array{coordinadoresCreados:int,coordinadoresActualizados:int,centrosCreados:int,centrosActualizados:int,coordinadoresSinRelacion:array<int,string>}
     */
    public function import(UploadedFile|string $source, array $centerMappings = []): array
    {
        $spreadsheet = IOFactory::load($source instanceof UploadedFile ? $source->getRealPath() : $source);
        $coordinatorSheet = $this->sheetNamed($spreadsheet, 'Maestro_Coordinador') ?? $this->sheetNamed($spreadsheet, 'Personal');
        $costCenterSheet = $this->sheetNamed($spreadsheet, 'Maestro_CC') ?? $this->sheetNamed($spreadsheet, 'Centros');
        $routeFormat = $costCenterSheet && Str::lower($costCenterSheet->getTitle()) === 'centros';

        if (! $coordinatorSheet || ! $costCenterSheet) {
            throw ValidationException::withMessages([
                'archivo' => 'El libro debe incluir Maestro_CC y Maestro_Coordinador, o Centros y Personal.',
            ]);
        }

        $coordinatorRows = $this->rows($coordinatorSheet);
        $costCenterRows = $this->rows($costCenterSheet);

        if ($coordinatorRows === [] || $costCenterRows === []) {
            throw ValidationException::withMessages([
                'archivo' => 'Las hojas Maestro_CC y Maestro_Coordinador deben contener datos además de sus encabezados.',
            ]);
        }

        if ($routeFormat) {
            $codes = [];
            foreach ($costCenterRows as $row) {
                $code = Str::upper(preg_replace('/\s+/', '', $this->value($row, 'id') ?? ''));
                if ($code !== '' && isset($codes[$code])) {
                    throw ValidationException::withMessages(['archivo' => "El código {$code} está repetido en Centros."]);
                }
                $codes[$code] = true;
                if (! in_array($this->normalize($this->value($row, 'estado')), ['activo', 'no activo'], true)) {
                    throw ValidationException::withMessages(['archivo' => 'Cada centro debe indicar Activo o No activo.']);
                }
            }
        }

        return DB::transaction(function () use ($coordinatorRows, $costCenterRows, $routeFormat, $centerMappings) {
            $createdCoordinators = 0;
            $updatedCoordinators = 0;

            foreach ($coordinatorRows as $row) {
                $name = $this->value($row, 'nombre_completo');
                $normalizedName = $this->normalize($name);
                if (! $normalizedName || ($routeFormat && ! $this->value($row, 'cargo'))) {
                    continue;
                }

                $rut = $this->value($row, 'rut');
                $coordinator = InventarioCoordinador::query()
                    ->when($rut, fn ($query) => $query->where('rut', $rut))
                    ->orWhere('nombre_normalizado', $normalizedName)
                    ->first();
                $isNew = ! $coordinator;
                $coordinator ??= new InventarioCoordinador();
                $coordinator->fill([
                    'nombre' => $name,
                    'nombre_normalizado' => $normalizedName,
                    'rut' => $rut ?? $coordinator->rut,
                    'cargo' => $this->value($row, 'cargo'),
                    'correo' => $this->value($row, 'correo'),
                    'telefono' => $this->value($row, 'tlf'),
                    'jefe_operaciones' => $this->value($row, 'jefe_de_operaciones'),
                    'activo' => true,
                ]);
                $coordinator->save();
                $isNew ? $createdCoordinators++ : $updatedCoordinators++;
            }

            $coordinators = InventarioCoordinador::query()->get()->keyBy('nombre_normalizado');
            $createdCostCenters = 0;
            $updatedCostCenters = 0;
            $unmatched = [];

            foreach ($costCenterRows as $row) {
                $name = $this->value($row, 'centro_de_costos');
                $normalizedName = $this->normalize($name);
                if (! $normalizedName) {
                    continue;
                }

                $sourceCoordinator = $this->value($row, 'coordinador');
                $matchedCoordinator = $coordinators->get($this->normalize($sourceCoordinator));
                if (! $matchedCoordinator && $sourceCoordinator) {
                    $contactName = trim(preg_replace('/\s*\([^)]*\)/', '', $sourceCoordinator));
                    $matchedCoordinator = $coordinators->get($this->normalize($contactName));
                }
                if ($sourceCoordinator && ! $matchedCoordinator) {
                    $unmatched[$sourceCoordinator] = $sourceCoordinator;
                }

                $code = $routeFormat ? $this->value($row, 'id') : null;
                $code = $code ? Str::upper(preg_replace('/\s+/', '', $code)) : null;
                $costCenter = InventarioCentroCosto::query()->where('nombre_normalizado', $normalizedName)->first();
                if (isset($centerMappings[$name])) {
                    $mapped = InventarioCentroCosto::query()->findOrFail($centerMappings[$name]);
                    if ($costCenter && $costCenter->id !== $mapped->id) {
                        throw ValidationException::withMessages(['archivo' => "La equivalencia de {$name} entra en conflicto con un centro existente."]);
                    }
                    $costCenter = $mapped;
                }
                if ($routeFormat && $code) {
                    $byCode = InventarioCentroCosto::query()->where('codigo_origen', $code)->first();
                    if ($costCenter && $byCode && $costCenter->id !== $byCode->id) {
                        throw ValidationException::withMessages(['archivo' => "El código {$code} corresponde a otro centro. Revisa {$name}."]);
                    }
                    $costCenter ??= $byCode;
                }
                $isNew = ! $costCenter;
                $costCenter ??= new InventarioCentroCosto();
                $number = $this->numericValue($row, 'n');
                $costCenter->fill([
                    'numero_maestro' => $routeFormat ? $costCenter->numero_maestro : $number,
                    'nombre' => $name,
                    'nombre_normalizado' => $normalizedName,
                    'tipo' => $this->value($row, 'tipo') ?? (preg_match('/\b(EST|SUB)\b/i', $code ?? $name, $match) ? Str::upper($match[1]) : $costCenter->tipo),
                    'comuna' => $this->value($row, 'comuna'),
                    'direccion' => $this->value($row, 'direccion'),
                    'jefe_operaciones' => $this->value($row, 'jefe_de_operaciones'),
                    'coordinador_id' => $matchedCoordinator?->id,
                    'coordinador_nombre_origen' => $sourceCoordinator,
                    'cargo_contacto' => $this->value($row, 'cargo'),
                    'correo_contacto' => $this->value($row, 'correo'),
                    'telefono_contacto' => $this->value($row, 'tlf'),
                    'activo' => $routeFormat ? $this->normalize($this->value($row, 'estado')) === 'activo' : true,
                ]);
                if ($routeFormat) {
                    $costCenter->fill([
                        'codigo_origen' => $code,
                        'ruta' => $this->value($row, 'ruta'),
                        'dias_despacho' => $this->value($row, 'dias_de_despacho'),
                        'se_despacha' => $this->normalize($this->value($row, 'se_despacha')) === 'si',
                    ]);
                }
                $costCenter->save();
                $isNew ? $createdCostCenters++ : $updatedCostCenters++;
            }

            return [
                'coordinadoresCreados' => $createdCoordinators,
                'coordinadoresActualizados' => $updatedCoordinators,
                'centrosCreados' => $createdCostCenters,
                'centrosActualizados' => $updatedCostCenters,
                'coordinadoresSinRelacion' => array_values($unmatched),
            ];
        });
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(Worksheet $sheet): array
    {
        $rawRows = $sheet->toArray(null, true, true, false);
        if (in_array(Str::lower($sheet->getTitle()), ['centros', 'personal'], true)) {
            $rawRows = array_slice($rawRows, 3);
        }
        $headers = array_map(fn ($header) => $this->header((string) $header), array_shift($rawRows) ?: []);

        return collect($rawRows)
            ->map(function (array $row) use ($headers) {
                $row = array_pad($row, count($headers), null);
                return array_combine($headers, array_slice($row, 0, count($headers))) ?: [];
            })
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => $this->clean($value) !== null))
            ->values()
            ->all();
    }

    private function header(string $value): string
    {
        $value = Str::of($value)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        return match ($value) {
            'n', 'nro', 'numero' => 'n',
            'centro', 'centro_de_costo', 'centro_de_costos' => 'centro_de_costos',
            'nombre', 'nombre_completo' => 'nombre_completo',
            'reporta_a', 'jefe_de_operaciones' => 'jefe_de_operaciones',
            'coordinador_a' => 'coordinador',
            'telefono', 'tel', 'tlf' => 'tlf',
            default => $value,
        };
    }

    private function sheetNamed(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $expectedName): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (Str::lower(trim($sheet->getTitle())) === Str::lower($expectedName)) {
                return $sheet;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row */
    private function value(array $row, string $key): ?string
    {
        return $this->clean($row[$key] ?? null);
    }

    /** @param array<string,mixed> $row */
    private function numericValue(array $row, string $key): ?int
    {
        $value = $this->value($row, $key);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || in_array(Str::upper($value), ['#N/A', 'N/A', 'NULL', '-'], true)) {
            return null;
        }

        return $value === '0' ? null : $value;
    }

    private function normalize(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return $normalized !== '' ? $normalized : null;
    }
}
