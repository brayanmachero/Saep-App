<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioCoordinador;
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
            ->get(['centro_costo_id', 'centro_costo_nombre', 'razon_social']);

        $companies = [];
        $costCenters = [];

        foreach ($workerRows as $worker) {
            $company = trim((string) $worker->razon_social);
            if ($company !== '') {
                $companyId = $this->companyId($company);
                $companies[$companyId] = ['id' => $companyId, 'name' => $company];
            }

            $centerName = trim((string) $worker->centro_costo_nombre);
            $centerId = $worker->centro_costo_id
                ? 'talana-'.$worker->centro_costo_id
                : 'nombre-'.sha1($this->normalizar($centerName));

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
        }

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
            'job_roles' => Cargo::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (Cargo $cargo): array => [
                    'id' => 'talana-cargo-'.$cargo->id,
                    'name' => $cargo->nombre,
                ])
                ->values(),
        ]);
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

    private function normalizar(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
