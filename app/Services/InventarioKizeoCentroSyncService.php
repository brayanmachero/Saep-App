<?php

namespace App\Services;

use App\Models\InventarioCentroCosto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventarioKizeoCentroSyncService
{
    public const COLUMNS = ['ID_SAEP', 'Codigo_CC', 'Modalidad', 'Coordinador', 'Correo_Coordinador', 'Telefono_Coordinador', 'Jefe_Operaciones', 'Direccion', 'Comuna', 'Ruta', 'Dias_Despacho', 'Se_Despacha', 'Estado'];

    public function __construct(private readonly KizeoService $api) {}

    public function listId(): string
    {
        return (string) config('services.kizeo.inventory_centers_list_id', '507253');
    }

    public function resolve(string $listId, string $itemId): ?InventarioCentroCosto
    {
        $id = DB::table('inventario_kizeo_centros')->where('list_id', $listId)->where('item_id', $itemId)->value('centro_costo_id');

        return $id ? InventarioCentroCosto::find($id) : null;
    }

    public function synchronize(bool $dryRun = false): array
    {
        $lock = Cache::lock('inventory-centers-kizeo-sync', 900);
        if (! $lock->get()) {
            throw new \RuntimeException('Ya hay una sincronización de centros en curso.');
        }
        try {
            return $this->run($dryRun);
        } finally {
            $lock->release();
        }
    }

    private function columns(): array
    {
        $list = $this->listId();
        Cache::forget("kizeo_list_def_{$list}");
        $definition = $this->api->getListDefinition($list);
        if ($definition['read_only'] ?? false) {
            throw new \RuntimeException('La lista es de solo lectura.');
        }
        $columns = [];
        foreach ($definition['properties_definition'] ?? [] as $key => $p) {
            if (! empty($p['delete_time'])) {
                continue;
            }
            $name = $p['display_name'] ?? '';
            if (! in_array($name, self::COLUMNS, true)) {
                continue;
            }
            if (isset($columns[$name]) || ($p['type'] ?? '') !== 'string') {
                throw new \RuntimeException("Columna duplicada o no textual: $name");
            }
            $columns[$name] = (string) ($p['id'] ?? $key);
        }
        foreach (self::COLUMNS as $name) {
            if (! isset($columns[$name])) {
                throw new \RuntimeException("Falta columna $name");
            }
        }

        return $columns;
    }

    private function payload(InventarioCentroCosto $c, array $columns): array
    {
        $values = [(string) $c->id, $c->codigo_origen, $c->tipo, $c->coordinador?->nombre ?? $c->coordinador_nombre_origen,
            $c->coordinador?->correo ?? $c->correo_contacto, $c->coordinador?->telefono ?? $c->telefono_contacto,
            $c->jefe_operaciones, $c->direccion, $c->comuna, $c->ruta, $c->dias_despacho,
            $c->se_despacha === null ? '' : ($c->se_despacha ? 'SI' : 'NO'), $c->activo ? 'ACTIVO' : 'INACTIVO'];
        $props = [];
        foreach (array_combine(self::COLUMNS, $values) as $name => $v) {
            $props[$columns[$name]] = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $v));
        }

        return ['label' => $c->nombre, 'properties' => $props];
    }

    private function index(array $items, string $property): array
    {
        $index = [];
        $ids = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            $source = trim((string) ($item['properties'][$property] ?? ''));
            if (! $id || isset($ids[$id])) {
                throw new \RuntimeException('ID interno de Kizeo ausente o duplicado.');
            }$ids[$id] = true;
            if ($source === '') {
                throw new \RuntimeException('Fila sin ID_SAEP: revisar antes de sincronizar.');
            }
            if (! ctype_digit($source) || isset($index[$source])) {
                throw new \RuntimeException('ID_SAEP inválido o duplicado: '.$source);
            }
            $index[$source] = $item;
        }

        return $index;
    }

    private function same(array $item, array $payload): bool
    {
        if (($item['label'] ?? '') !== $payload['label']) {
            return false;
        }
        foreach ($payload['properties'] as $key => $value) {
            if ((string) ($item['properties'][$key] ?? '') !== $value) {
                return false;
            }
        }

        return true;
    }

    private function run(bool $dryRun): array
    {
        $list = $this->listId();
        $columns = $this->columns();
        $before = $this->api->getListItems($list, true);
        $index = $this->index($before, $columns['ID_SAEP']);
        $centers = InventarioCentroCosto::with('coordinador')->orderBy('id')->get();
        $maps = DB::table('inventario_kizeo_centros')->where('list_id', $list)->get()->keyBy('centro_costo_id');
        $create = [];
        $update = [];
        $expected = [];
        $summary = ['list' => $list, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'total' => $centers->count(), 'unresolved' => [], 'remoteOnly' => array_values(array_diff(array_keys($index), $centers->pluck('id')->all())), 'dryRun' => $dryRun];
        foreach ($centers as $c) {
            $p = $this->payload($c, $columns);
            $expected[$c->id] = $p;
            $item = $index[$c->id] ?? null;
            $map = $maps->get($c->id);
            if ($map && (! $item || (string) $item['id'] !== $map->item_id)) {
                throw new \RuntimeException("El ID interno cambió o desapareció para {$c->nombre}. Se detuvo para proteger el historial.");
            }
            if (! $c->codigo_origen || ! $c->tipo) {
                $summary['unresolved'][] = $c->nombre;
            }
            if (! $item) {
                $create[] = $p;
                $summary['created']++;
            } elseif (! $this->same($item, $p)) {
                $update[] = ['item_id' => (string) $item['id']] + $p;
                $summary['updated']++;
            } else {
                $summary['unchanged']++;
            }
        }
        if ($dryRun) {
            return $summary;
        }
        $evidence = 'kizeo-centros/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        if (! Storage::disk('local')->put($evidence.'-before.json', json_encode(['items' => $before, 'expected' => $expected], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('No se pudo guardar respaldo.');
        }
        // No se reintenta un POST ambiguo. La siguiente ejecución recupera filas por ID_SAEP.
        foreach (array_chunk($create, 100) as $batch) {
            $this->api->createListItems($list, $batch);
        }
        foreach (array_chunk($update, 100) as $batch) {
            $this->api->updateListItems($list, $batch);
        }
        $after = $this->index($this->api->getListItems($list, true), $columns['ID_SAEP']);
        foreach ($expected as $id => $p) {
            $item = $after[$id] ?? null;
            if (! $item || ! $this->same($item, $p)) {
                throw new \RuntimeException("Verificación remota falló para centro $id; no se declara sincronizado.");
            }
            if (isset($index[$id]) && (string) $index[$id]['id'] !== (string) $item['id']) {
                throw new \RuntimeException('Kizeo cambió un ID existente.');
            }
        }
        DB::transaction(function () use ($expected, $after, $list) {
            foreach ($expected as $id => $p) {
                DB::table('inventario_kizeo_centros')->updateOrInsert(['list_id' => $list, 'centro_costo_id' => $id], ['item_id' => (string) $after[$id]['id'], 'verified_at' => now()]);
            }
        });
        $summary['verified'] = true;
        $summary['evidence'] = $evidence;
        Storage::disk('local')->put($evidence.'-after.json', json_encode(['summary' => $summary, 'items' => $after],JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $summary;
    }
}
