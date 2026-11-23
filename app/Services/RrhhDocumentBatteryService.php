<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhDocumentBatteryService
{
    public function documentState(array $metadata): string
    {
        if (($metadata['observation']['state'] ?? null) === 'open') return 'observados';
        if (empty($metadata['talana_id'])) return 'sin-vincular';
        return empty($metadata['validated_at']) ? 'por-revisar' : 'validados';
    }

    public function review(array $documents, array $details): array
    {
        $counts = array_fill_keys(['sin-vincular','por-revisar','observados','validados'],0);
        $issues = [];
        foreach ($documents as $position=>$name) {
            $state = $this->documentState($details[$name] ?? []);
            $counts[$state]++;
            if ($state !== 'validados') $issues[] = compact('position','name','state');
        }
        return ['counts'=>$counts,'issues'=>$issues,'ready'=>count($documents)>0 && !$issues];
    }

    public function compare(object $before, object $after): array
    {
        $old = json_decode($before->documentos,true);
        $new = json_decode($after->documentos,true);
        $oldMeta = json_decode($before->document_details ?? '{}',true);
        $newMeta = json_decode($after->document_details ?? '{}',true);
        $remaining = $old;
        $changes = [];
        foreach ($new as $position=>$name) {
            $reference = $newMeta[$name]['source_reference'] ?? $name;
            $match = null;
            foreach ($remaining as $oldPosition=>$oldName) {
                if (($oldMeta[$oldName]['source_reference'] ?? $oldName) === $reference) { $match = $oldPosition; break; }
            }
            if ($match === null) {
                $sameName = array_search($name,$remaining,true);
                if ($sameName !== false) $match = $sameName;
            }
            if ($match === null) {
                $changes[] = ['type'=>'added','before'=>null,'after'=>$name,'position'=>$position];
                continue;
            }
            $oldName = $remaining[$match];
            $oldId = $oldMeta[$oldName]['talana_id'] ?? null;
            $newId = $newMeta[$name]['talana_id'] ?? null;
            if ($oldName !== $name || $oldId !== $newId) {
                $changes[] = ['type'=>'replaced','before'=>$oldName,'after'=>$name,'before_id'=>$oldId,'after_id'=>$newId,'position'=>$position];
            } elseif ($match !== $position) {
                $changes[] = ['type'=>'moved','before'=>$oldName,'after'=>$name,'position'=>$position];
            }
            unset($remaining[$match]);
        }
        foreach ($remaining as $name) $changes[] = ['type'=>'removed','before'=>$name,'after'=>null,'position'=>null];
        $oldRules = json_decode($before->reglas,true);
        $newRules = json_decode($after->reglas,true);
        $rules = [];
        foreach (['empresa_id','centro_costo_nombre','cargo_nombre','tipo_contrato_nombre'] as $key) {
            if ((string)($oldRules[$key] ?? '') !== (string)($newRules[$key] ?? '')) $rules[] = ['field'=>$key,'before'=>$oldRules[$key] ?? null,'after'=>$newRules[$key] ?? null];
        }
        return compact('changes','rules') + ['composition_changed'=>($before->kind ?? 'independent') !== ($after->kind ?? 'independent') || (json_decode($before->parent_ids ?? '[]',true) ?: []) !== (json_decode($after->parent_ids ?? '[]',true) ?: [])];
    }

    public function selections(array $tokens, int $userId): array
    {
        $documents = [];
        foreach ($tokens as $token) {
            try {
                $selection = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
                if (($selection['user'] ?? null) !== $userId || ! is_string($selection['name'] ?? null) || ! is_int($selection['id'] ?? null) || $selection['id'] < 1) {
                    throw new \RuntimeException;
                }
                if (empty($selection['empresa_id']) || !is_int($selection['empresa_id'])) throw new \RuntimeException;
                $documents[$selection['name']] = ['talana_id' => $selection['id'], 'empresa_id' => $selection['empresa_id']];
            } catch (\Throwable) {
                throw ValidationException::withMessages(['documentos' => 'Busca y selecciona nuevamente el documento en Talana.']);
            }
        }

        return $documents;
    }

    // References selected before company filtering came from the default SAEP base.
    public function sourceCompany(array $metadata): int
    {
        return (int)($metadata['empresa_id'] ?? 1039);
    }

    public function assertCompany(array $details, ?int $companyId): void
    {
        foreach ($details as $metadata) {
            if (!$companyId || $this->sourceCompany($metadata) !== $companyId) {
                throw ValidationException::withMessages(['empresa_id'=>'Selecciona documentos de la misma empresa que la batería. Vuelve a buscarlos en Talana.']);
            }
        }
    }

    public function event(int $batteryId, int $actorId, string $action, array $detail = []): void
    {
        DB::table('rrhh_document_battery_events')->insert([
            'battery_id' => $batteryId, 'actor_id' => $actorId, 'action' => $action,
            'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
        ]);
    }

    public function editable(object $battery, int $revision): void
    {
        if ($battery->revision !== $revision) {
            throw ValidationException::withMessages(['bateria' => 'Otra persona modificó esta batería. Recarga para revisar los cambios.']);
        }
        if ($battery->estado !== 'BORRADOR' || DB::table('rrhh_document_batches')->where('battery_id', $battery->id)->exists()) {
            throw ValidationException::withMessages(['bateria' => 'Crea una nueva versión para modificar esta batería.']);
        }
    }
}
