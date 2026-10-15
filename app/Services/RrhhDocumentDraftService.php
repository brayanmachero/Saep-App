<?php

namespace App\Services;

use App\Models\TalanaContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhDocumentDraftService
{
    public function query(array $filters)
    {
        $q = TalanaContrato::query()->where('finiquitado', false)->where(fn ($q) => $q->whereNull('desde')->orWhereDate('desde', '<=', today()))->where(fn ($q) => $q->whereNull('hasta')->orWhereDate('hasta', '>=', today()));
        foreach (['empresa_id', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $q->where($field, $filters[$field]);
            }
        }
        if (filled($filters['buscar'] ?? null)) {
            $term = '%'.$filters['buscar'].'%';
            $q->where(fn ($q) => $q->where('persona_nombre', 'like', $term)->orWhere('persona_rut', 'like', $term));
        }

        return $q;
    }

    public function create(array $data, int $userId): string
    {
        return DB::transaction(function () use ($data, $userId) {
            // Identificador de formulario: doble clic/reintento no crea dos lotes.
            $existing = DB::table('rrhh_document_batches')->where('id', $data['request_id'])->first();
            if ($existing) {
                abort_unless((int) $existing->created_by === $userId, 403);

                return $existing->id;
            }
            $battery = DB::table('rrhh_document_batteries')->find($data['battery_id']);
            if (! $battery) {
                throw ValidationException::withMessages(['battery_id' => 'Selecciona una batería existente.']);
            }
            $docs = json_decode($battery->documentos, true, 512, JSON_THROW_ON_ERROR);
            $rules = json_decode($battery->reglas, true, 512, JSON_THROW_ON_ERROR);
            $filters = array_intersect_key($data, array_flip(['empresa_id', 'centro_costo_nombre', 'cargo_nombre', 'tipo_contrato_nombre', 'buscar']));
            $q = $this->query($filters);
            if ($data['seleccion'] === 'manual') {
                $ids = array_unique($data['contratos'] ?? []);
                if (! $ids) {
                    throw ValidationException::withMessages(['contratos' => 'Selecciona contratos o todos los resultados.']);
                }$q->whereIn('id', $ids);
            }
            $count = (clone $q)->count();
            if (! $count || $count > 5000) {
                throw ValidationException::withMessages(['contratos' => 'El lote debe contener entre 1 y 5.000 contratos. Divide volúmenes mayores por centro o empresa.']);
            }
            $contracts = $q->orderBy('id')->get();
            if ($data['seleccion'] === 'manual' && $contracts->count() !== count($ids)) {
                throw ValidationException::withMessages(['contratos' => 'La selección cambió o incluye contratos fuera de los filtros/vigencia. Actualiza la lista.']);
            }
            $people = $contracts->groupBy(fn ($c) => $c->empresa_id.':'.$c->persona_talana_id);
            $previous = DB::table('rrhh_document_batch_items as i')->join('rrhh_document_batches as b', 'b.id', '=', 'i.batch_id')->where('b.estado', 'BORRADOR')->where('i.excluido', false)->where('b.battery_id', $battery->id)->whereIn('i.contrato_id', $contracts->pluck('id'))->pluck('i.contrato_id')->flip();
            $id = $data['request_id'];
            $rows = [];
            $observed = 0;
            foreach ($contracts as $c) {
                $warnings = [];
                foreach (['persona_rut' => 'RUT', 'persona_talana_id' => 'ID de trabajador', 'empresa_id' => 'Empresa', 'cargo_nombre' => 'Cargo', 'centro_costo_nombre' => 'Centro de costo', 'tipo_contrato_nombre' => 'Tipo de contrato', 'desde' => 'Inicio de vigencia'] as $field => $label) {
                    if (blank($c->$field)) {
                        $warnings[] = 'Falta '.$label;
                    }
                }
                if (! $c->synced_at || $c->synced_at->lt(now()->subHours(48))) {
                    $warnings[] = 'Datos de Talana sin actualización en las últimas 48 horas';
                }
                if ($people[$c->empresa_id.':'.$c->persona_talana_id]->count() > 1) {
                    $warnings[] = 'Más de un contrato seleccionado para la misma persona y empresa';
                }
                foreach ($rules as $field => $value) {
                    if (filled($value) && (string) $c->$field !== (string) $value) {
                        $warnings[] = 'No cumple regla de batería: '.$field;
                    }
                }
                if ($previous->has($c->id)) {
                    $warnings[] = 'Este contrato ya está incluido en otro borrador de esta batería';
                }
                if ($warnings) {
                    $observed++;
                }
                $rows[] = ['batch_id' => $id, 'contrato_id' => $c->id, 'snapshot' => json_encode($c->only(['talana_id', 'persona_talana_id', 'persona_nombre', 'persona_rut', 'empresa_id', 'empresa_nombre', 'cargo_nombre', 'centro_costo_nombre', 'tipo_contrato_nombre', 'desde', 'hasta', 'synced_at']), JSON_UNESCAPED_UNICODE), 'observaciones' => json_encode($warnings, JSON_UNESCAPED_UNICODE)];
            }
            DB::table('rrhh_document_batches')->insert(['id' => $id, 'nombre' => $data['nombre'], 'battery_id' => $battery->id, 'battery_snapshot' => json_encode(['nombre' => $battery->nombre, 'version' => $battery->version, 'documentos' => $docs, 'reglas' => $rules], JSON_UNESCAPED_UNICODE), 'filtros' => json_encode($filters), 'created_by' => $userId, 'estado' => 'BORRADOR', 'trabajadores' => $contracts->count(), 'documentos' => $contracts->count() * count($docs), 'observados' => $observed, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('rrhh_document_batch_items')->insert($chunk);
            }

            return $id;
        });
    }
}
