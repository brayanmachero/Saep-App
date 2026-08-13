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
    public function import(UploadedFile|string $source): array
    {
        $spreadsheet = IOFactory::load($source instanceof UploadedFile ? $source->getRealPath() : $source);
        $coordinatorSheet = $this->sheetNamed($spreadsheet, 'Maestro_Coordinador');
        $costCenterSheet = $this->sheetNamed($spreadsheet, 'Maestro_CC');

        if (! $coordinatorSheet || ! $costCenterSheet) {
            throw ValidationException::withMessages([
                'archivo' => 'El libro debe incluir las hojas Maestro_CC y Maestro_Coordinador.',
            ]);
        }

        $coordinatorRows = $this->rows($coordinatorSheet);
        $costCenterRows = $this->rows($costCenterSheet);

        if ($coordinatorRows === [] || $costCenterRows === []) {
            throw ValidationException::withMessages([
                'archivo' => 'Las hojas Maestro_CC y Maestro_Coordinador deben contener datos además de sus encabezados.',
            ]);
        }

        return DB::transaction(function () use ($coordinatorRows, $costCenterRows) {
            $createdCoordinators = 0;
            $updatedCoordinators = 0;

            foreach ($coordinatorRows as $row) {
                $name = $this->value($row, 'nombre_completo');
                $normalizedName = $this->normalize($name);
                if (! $normalizedName) {
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
                    'rut' => $rut,
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
                if ($sourceCoordinator && ! $matchedCoordinator) {
                    $unmatched[$sourceCoordinator] = $sourceCoordinator;
                }

                $costCenter = InventarioCentroCosto::query()->where('nombre_normalizado', $normalizedName)->first();
                $isNew = ! $costCenter;
                $costCenter ??= new InventarioCentroCosto();
                $number = $this->numericValue($row, 'n');
                $costCenter->fill([
                    'numero_maestro' => $number,
                    'nombre' => $name,
                    'nombre_normalizado' => $normalizedName,
                    'tipo' => $this->value($row, 'tipo'),
                    'comuna' => $this->value($row, 'comuna'),
                    'direccion' => $this->value($row, 'direccion'),
                    'jefe_operaciones' => $this->value($row, 'jefe_de_operaciones'),
                    'coordinador_id' => $matchedCoordinator?->id,
                    'coordinador_nombre_origen' => $sourceCoordinator,
                    'cargo_contacto' => $this->value($row, 'cargo'),
                    'correo_contacto' => $this->value($row, 'correo'),
                    'telefono_contacto' => $this->value($row, 'tlf'),
                    'activo' => true,
                ]);
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
            'centro_de_costo', 'centro_de_costos' => 'centro_de_costos',
            'nombre_completo' => 'nombre_completo',
            'jefe_de_operaciones' => 'jefe_de_operaciones',
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
