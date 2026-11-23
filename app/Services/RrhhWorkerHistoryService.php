<?php

namespace App\Services;

use App\Models\TalanaContrato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class RrhhWorkerHistoryService
{
    public function contracts(TalanaContrato $worker)
    {
        return TalanaContrato::where('empresa_id', $worker->empresa_id)
            ->where('persona_talana_id', $worker->persona_talana_id);
    }

    public function documents(TalanaContrato $worker): array
    {
        return Cache::remember('rrhh-worker-docs-v1:'.$worker->empresa_id.':'.$worker->persona_talana_id, 120, function () use ($worker) {
            $documents = [];
            $more = false;
            for ($page = 1; $page <= 20; $page++) {
                $data = app(TalanaDocumentApi::class)->employeeDocuments((int)$worker->empresa_id, (int)$worker->persona_talana_id, $page)->throw()->json();
                foreach ($data['results'] ?? [] as $entry) {
                    // Never include a result belonging to a different employee.
                    if ((int)($entry['empleado'] ?? 0) !== (int)$worker->persona_talana_id) continue;
                    $name = (string)($entry['nombre'] ?? '');
                    $date = null;
                    try { if (!empty($entry['fechaCreacion'])) $date = CarbonImmutable::parse($entry['fechaCreacion'])->tz('America/Santiago')->toIso8601String(); } catch (\Throwable) {}
                    $documents[(int)$entry['id']] = ['id'=>(int)$entry['id'], 'name'=>$name, 'created_at'=>$date,
                        'contract_id'=>$entry['contrato_id'] ?? null,
                        'type'=>preg_match('/anexo/iu', $name) ? 'anexo' : (preg_match('/contrato|ctto/iu', $name) ? 'contrato' : 'otro')];
                }
                $more = !empty($data['next']);
                if (!$more) break;
            }
            return ['documents'=>array_values($documents), 'complete'=>!$more, 'consulted_at'=>now()->toIso8601String()];
        });
    }

    public function contractReference(TalanaContrato $worker, string $policy, string $field, ?int $id = null): array
    {
        if (!in_array($field, ['desde','fecha_contratacion'], true)) throw ValidationException::withMessages(['fecha_campo'=>'Selecciona inicio de vigencia o fecha de contratación.']);
        $query = $this->contracts($worker);
        if (in_array($policy, ['primero','ultimo'], true)) {
            // An unknown date makes the earliest/latest determination unreliable.
            if ((clone $query)->whereNull($field)->exists()) throw ValidationException::withMessages(['referencia'=>'Hay contratos sin esta fecha. Elige un contrato específico y verifica su fecha.']);
            $direction = $policy === 'primero' ? 'asc' : 'desc';
            $contract = $query->orderBy($field, $direction)->orderBy('talana_id', $direction)->first();
        } else {
            $contract = $query->where('id', $id ?? $worker->id)->first();
        }
        if (!$contract || !$contract->$field) throw ValidationException::withMessages(['referencia'=>'El contrato no pertenece a este trabajador/empresa o no tiene la fecha elegida.']);
        return ['type'=>'contrato', 'contract_id'=>(int)$contract->talana_id, 'local_id'=>(int)$contract->id,
            'company_id'=>(int)$worker->empresa_id, 'employee_id'=>(int)$worker->persona_talana_id,
            'field'=>$field, 'date'=>substr((string)$contract->getRawOriginal($field),0,10), 'policy'=>$policy,
            'source_synced_at'=>$contract->synced_at?->toIso8601String(), 'confirmed'=>false];
    }
}
