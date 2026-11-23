<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Compose immutable versions; never rewrite an ancestor's documents in a child. */
class RrhhDocumentCompositionService
{
    public function resolve(object $battery, bool $lock = false): array
    {
        $documents = $details = $origins = $sources = $rules = $errors = $pending = $seen = $path = $families = [];
        $identities = [];
        $hasBase = false;
        $allOwnValidated = true;
        $walk = function (object $node, int $depth = 0) use (&$walk, &$documents, &$details, &$origins, &$sources, &$rules, &$errors, &$pending, &$seen, &$path, &$families, &$identities, &$hasBase, &$allOwnValidated, $battery, $lock) {
            if (isset($path[$node->id]) || $depth > 10) {
                $errors[] = 'La composición contiene un ciclo o supera diez niveles.';
                return;
            }
            if (isset($seen[$node->id])) return;
            if (isset($families[$node->nombre]) && $families[$node->nombre] !== $node->id) $errors[] = 'Selecciona una sola versión de cada batería de origen.';
            $families[$node->nombre] = $node->id;
            $path[$node->id] = true;
            $parents = json_decode($node->parent_ids ?? '[]', true) ?: [];
            if (($node->kind ?? '') === 'independent' && $parents) $errors[] = 'Clasifica la batería como específica para heredar documentos.';
            if (($node->kind ?? '') === 'base') {
                $hasBase = true;
                if ($parents) $errors[] = 'Una base común no puede heredar otras baterías.';
            }
            if (($node->kind ?? '') === 'specific' && !$parents) $errors[] = "{$node->nombre}: selecciona una base obligatoria.";
            foreach ($parents as $id) {
                $query = DB::table('rrhh_document_batteries')->where('id', $id);
                if ($lock) $query->lockForUpdate();
                $parent = $query->first();
                if (!$parent) { $errors[] = 'Una batería de origen ya no existe.'; continue; }
                if ($parent->nombre === $node->nombre) $errors[] = 'Una batería no puede heredar otra versión de sí misma.';
                $walk($parent, $depth + 1);
            }
            unset($path[$node->id]);
            $seen[$node->id] = true;
            $inherited = $node->id !== $battery->id;
            $sources[] = ['id'=>$node->id, 'nombre'=>$node->nombre, 'version'=>$node->version, 'estado'=>$node->estado, 'kind'=>$node->kind ?? 'independent', 'inherited'=>$inherited];
            if ($inherited && $node->estado !== 'ACTIVA') $pending[] = "{$node->nombre} v{$node->version}: la versión de origen no está activa. Selecciona la versión vigente y revisa la composición.";
            foreach (json_decode($node->reglas, true) as $field=>$value) {
                if (blank($value)) continue;
                if (isset($rules[$field]) && (string)$rules[$field] !== (string)$value) $errors[] = "Las baterías tienen alcances incompatibles: {$field}.";
                $rules[$field] = $value;
            }
            $meta = json_decode($node->document_details ?? '{}', true);
            $own = json_decode($node->documentos, true);
            if ($own && !app(RrhhDocumentBatteryService::class)->review($own, $meta)['ready']) $allOwnValidated = false;
            foreach ($own as $position=>$name) {
                $entry = $meta[$name] ?? [];
                $identity = !empty($entry['talana_id']) ? 'talana:'.app(RrhhDocumentBatteryService::class)->sourceCompany($entry).':'.$entry['talana_id'] : 'name:'.$name;
                $origin = ['battery_id'=>$node->id, 'nombre'=>$node->nombre, 'version'=>$node->version, 'position'=>$position, 'inherited'=>$inherited];
                if (isset($identities[$identity])) {
                    $origins[$identities[$identity]][] = $origin;
                    continue;
                }
                if (in_array($name, $documents, true)) {
                    $errors[] = "{$name}: hay referencias de Talana distintas con el mismo nombre. Revisa cuál corresponde.";
                    continue;
                }
                $identities[$identity] = $name;
                $documents[] = $name;
                $details[$name] = $entry;
                $origins[$name][] = $origin;
            }
        };
        $walk($battery);
        if (($battery->kind ?? '') === 'specific' && !$hasBase) $errors[] = 'La composición debe incluir una batería marcada como base común obligatoria.';
        if (($battery->kind ?? '') === 'specific' && !array_filter(array_intersect_key($rules, array_flip(['centro_costo_nombre','cargo_nombre','tipo_contrato_nombre'])))) $pending[] = 'Define el centro, cargo o tipo de contrato de esta batería específica antes de activarla.';
        if (count($documents) > 50) $errors[] = 'La composición admite hasta 50 documentos únicos.';
        foreach ($details as $name=>$metadata) {
            if (!empty($metadata['talana_id']) && !empty($rules['empresa_id']) && app(RrhhDocumentBatteryService::class)->sourceCompany($metadata) !== (int)$rules['empresa_id']) {
                $pending[] = $name.': la referencia pertenece a otra base de Talana. Reemplázala por un documento de la empresa de esta batería.';
            }
        }
        $review = app(RrhhDocumentBatteryService::class)->review($documents, $details);
        $review['ready'] = $review['ready'] && $allOwnValidated;
        return compact('documents', 'details', 'origins', 'sources', 'rules', 'review') + ['errors'=>array_values(array_unique($errors)), 'pending'=>array_values(array_unique($pending))];
    }

    public function assertUsable(array $composition, bool $validated = false): void
    {
        $errors = $composition['errors'];
        if ($validated) $errors = array_merge($errors, $composition['pending']);
        if ($validated && !$composition['review']['ready']) $errors[] = 'Valida los documentos propios y activa las baterías de origen antes de continuar.';
        if ($errors) throw ValidationException::withMessages(['composition'=>$errors]);
    }

    public function choices(?int $exclude = null)
    {
        return DB::table('rrhh_document_batteries')->whereIn('kind', ['base', 'specific'])->whereIn('estado', ['ACTIVA','BORRADOR'])
            ->when($exclude, fn($q) => $q->where('id', '!=', $exclude))->orderBy('nombre')->orderByDesc('version')->get();
    }
}
