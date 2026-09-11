<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioCoordinador;
use App\Models\RecruitmentCatalogJobRoleCenter;
use App\Models\TalanaTrabajador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecruitmentCatalogController extends Controller
{
    /**
     * Entrega los maestros operacionales ya sincronizados por SAEP.
     *
     * La ruta nunca llama a Talana ni expone su credencial. Reclutamiento
     * consume una vista de sólo lectura mediante una clave de servicio propia.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $operationalCenters = InventarioCentroCosto::query()
            ->with('coordinador:id,nombre')
            ->where('activo', true)
            ->get()
            ->keyBy(fn (InventarioCentroCosto $center): string => $this->normalizar($center->nombre));

        $workerRows = TalanaTrabajador::query()
            ->where('activo', true)
            ->whereNotNull('centro_costo_nombre')
            ->where('centro_costo_nombre', '<>', '')
            ->get(['centro_costo_id', 'centro_costo_nombre', 'cargo_nombre', 'razon_social']);

        $companies = [];
        $costCenters = [];
        $jobRoles = [];

        foreach ($workerRows as $worker) {
            $company = trim((string) $worker->razon_social);
            if ($company !== '') {
                $companyId = $this->companyId($company);
                $companies[$companyId] = ['id' => $companyId, 'name' => $company];
            }

            $centerName = trim((string) $worker->centro_costo_nombre);
            $centerId = $this->costCenterId($worker->centro_costo_id, $centerName);

            if (! isset($costCenters[$centerId])) {
                $masterCenter = $operationalCenters->get($this->normalizar($centerName));
                $costCenters[$centerId] = [
                    'id' => $centerId,
                    'name' => $centerName,
                    'company_ids' => [],
                    'coordinator_id' => $masterCenter?->coordinador_id
                        ? 'operaciones-'.$masterCenter->coordinador_id
                        : null,
                    'operational_lead' => $masterCenter?->jefe_operaciones,
                ];
            }

            if ($company !== '') {
                $costCenters[$centerId]['company_ids'][$this->companyId($company)] = true;
            }

            $jobRoleName = trim((string) $worker->cargo_nombre);
            $jobRoleKey = $this->normalizar($jobRoleName);
            if ($jobRoleKey !== '') {
                if (! isset($jobRoles[$jobRoleKey])) {
                    $jobRoles[$jobRoleKey] = [
                        'id' => 'talana-cargo-'.sha1($jobRoleKey),
                        'name' => $jobRoleName,
                        'cost_center_ids' => [],
                        'is_manual' => false,
                    ];
                }

                $jobRoles[$jobRoleKey]['cost_center_ids'][$centerId] = true;
            }
        }

        $manualRoles = Cargo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $manualRoleCenterIds = RecruitmentCatalogJobRoleCenter::query()
            ->whereIn('cargo_id', $manualRoles->pluck('id'))
            ->get(['cargo_id', 'cost_center_external_id'])
            ->groupBy('cargo_id')
            ->map(fn ($assignments): array => $assignments
                ->pluck('cost_center_external_id')
                ->unique()
                ->values()
                ->all());

        $manualRoles->each(function (Cargo $cargo) use (&$jobRoles, $manualRoleCenterIds): void {
            $jobRoleKey = $this->normalizar($cargo->nombre);
            $costCenterIds = $manualRoleCenterIds->get($cargo->id, []);
            if ($jobRoleKey === '' || $costCenterIds === []) {
                return;
            }

            if (isset($jobRoles[$jobRoleKey])) {
                foreach ($costCenterIds as $costCenterId) {
                    $jobRoles[$jobRoleKey]['cost_center_ids'][$costCenterId] = true;
                }

                return;
            }

            $jobRoles[$jobRoleKey] = [
                'id' => 'manual-cargo-'.$cargo->id,
                'name' => $cargo->nombre,
                'cost_center_ids' => array_fill_keys($costCenterIds, true),
                'is_manual' => true,
            ];
        });

        $coordinators = InventarioCoordinador::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (InventarioCoordinador $coordinator): array => [
                'id' => 'operaciones-'.$coordinator->id,
                'name' => $coordinator->nombre,
            ])
            ->values();

        $operationalLeads = $operationalCenters
            ->pluck('jefe_operaciones')
            ->filter()
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => $this->normalizar($name))
            ->sort()
            ->values()
            ->map(fn (string $name): array => [
                'id' => 'jefatura-'.sha1($this->normalizar($name)),
                'name' => $name,
            ])
            ->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'source' => 'SAEP Platform · maestros sincronizados desde Talana',
            'companies' => collect($companies)->sortBy('name')->values(),
            'cost_centers' => collect($costCenters)
                ->map(function (array $center): array {
                    $center['company_ids'] = array_keys($center['company_ids']);

                    return $center;
                })
                ->sortBy('name')
                ->values(),
            'coordinators' => $coordinators,
            'operational_leads' => $operationalLeads,
            'job_roles' => collect($jobRoles)
                ->map(function (array $jobRole): array {
                    $jobRole['cost_center_ids'] = array_keys($jobRole['cost_center_ids']);

                    return $jobRole;
                })
                ->sortBy('name')
                ->values(),
        ]);
    }

    public function storeJobRoles(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $names = $request->input('names');
        if (! is_array($names) || count($names) === 0 || count($names) > 20) {
            abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Los cargos no son válidos.');
        }
        $costCenterId = $request->input('cost_center_id');
        if (! is_string($costCenterId) || ! in_array($costCenterId, $this->catalogCostCenterIds(), true)) {
            abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'El centro de costo no es válido.');
        }

        $existingRoles = Cargo::query()->get(['id', 'codigo', 'nombre', 'activo']);
        $jobRoles = [];
        foreach ($names as $name) {
            $jobRoleName = $this->jobRoleName($name);
            $normalizedName = $this->normalizar($jobRoleName);
            if ($normalizedName === '') {
                abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Los cargos no son válidos.');
            }

            $jobRole = $existingRoles->first(
                fn (Cargo $role): bool => $this->normalizar($role->nombre) === $normalizedName
            );
            if ($jobRole) {
                if (! $jobRole->activo) {
                    $jobRole->update(['activo' => true]);
                }
            } else {
                $jobRole = Cargo::create([
                    'codigo' => $this->jobRoleCode($jobRoleName),
                    'nombre' => $jobRoleName,
                    'activo' => true,
                ]);
                $existingRoles->push($jobRole);
            }
            RecruitmentCatalogJobRoleCenter::query()->firstOrCreate([
                'cargo_id' => $jobRole->id,
                'cost_center_external_id' => $costCenterId,
            ]);

            $jobRoles[] = [
                'id' => 'manual-cargo-'.$jobRole->id,
                'name' => $jobRole->nombre,
                'cost_center_ids' => [$costCenterId],
                'is_manual' => true,
            ];
        }

        return response()->json(['job_roles' => $jobRoles]);
    }

    private function authorizeRequest(Request $request): void
    {
        $expectedToken = trim((string) config('services.recruitment_catalog.token'));
        if ($expectedToken === '') {
            abort(JsonResponse::HTTP_SERVICE_UNAVAILABLE, 'El catálogo de Reclutamiento no está configurado.');
        }

        $providedToken = $request->header('X-SAEP-Catalog-Key') ?: $request->bearerToken();
        if (! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            abort(JsonResponse::HTTP_UNAUTHORIZED, 'No autorizado.');
        }
    }

    private function companyId(string $company): string
    {
        return 'talana-empresa-'.sha1($this->normalizar($company));
    }

    private function catalogCostCenterIds(): array
    {
        return TalanaTrabajador::query()
            ->where('activo', true)
            ->whereNotNull('centro_costo_nombre')
            ->where('centro_costo_nombre', '<>', '')
            ->get(['centro_costo_id', 'centro_costo_nombre'])
            ->map(fn (TalanaTrabajador $worker): string => $this->costCenterId(
                $worker->centro_costo_id,
                trim((string) $worker->centro_costo_nombre)
            ))
            ->unique()
            ->values()
            ->all();
    }

    private function costCenterId(mixed $centerId, string $centerName): string
    {
        return $centerId
            ? 'talana-'.$centerId
            : 'nombre-'.sha1($this->normalizar($centerName));
    }

    private function normalizar(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function jobRoleName(mixed $name): string
    {
        $value = is_string($name) ? $name : '';
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        abort_if(mb_strlen($value) < 2 || mb_strlen($value) > 160, JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Los cargos no son válidos.');

        return $value;
    }

    private function jobRoleCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $code = Str::limit($base, 50, '');
        $suffix = 0;

        while (Cargo::query()->where('codigo', $code)->exists()) {
            $suffix++;
            $code = Str::limit($base, 47, '').'_'.$suffix;
        }

        return $code;
    }
}
