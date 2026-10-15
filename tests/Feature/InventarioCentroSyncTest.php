<?php

namespace Tests\Feature;

use App\Models\InventarioCentroCosto;
use App\Services\InventarioKizeoCentroSyncService as Sync;
use App\Services\InventarioTalanaCentroAuditService as Audit;
use App\Services\KizeoService;
use App\Services\TalanaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventarioCentroSyncTest extends TestCase
{
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Schema::dropIfExists('inventario_kizeo_centros');
        Schema::dropIfExists('inventario_centros_costo');
        Schema::dropIfExists('inventario_coordinadores');
        Schema::create('inventario_centros_costo', function (Blueprint $t) {
            $t->id();
            foreach (['nombre', 'nombre_normalizado', 'tipo', 'codigo_origen', 'coordinador_nombre_origen', 'correo_contacto', 'telefono_contacto', 'jefe_operaciones', 'direccion', 'comuna', 'ruta', 'dias_despacho'] as $key) {
                $t->string($key)->nullable();
            }$t->unsignedBigInteger('coordinador_id')->nullable();
            $t->boolean('activo')->default(true);
            $t->boolean('se_despacha')->nullable();
            $t->timestamps();
        });
        Schema::create('inventario_coordinadores', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('correo')->nullable();
            $t->string('telefono')->nullable();
        });
        (require base_path('database/migrations/2026_10_15_180000_create_inventario_kizeo_centros.php'))->up();
        config(['services.kizeo.inventory_centers_list_id' => '507253']);
    }

    private function service(): Sync
    {
        $api = \Mockery::mock(KizeoService::class);
        $props = [];
        foreach (Sync::COLUMNS as $name) {
            $props[$name] = ['id' => $name, 'display_name' => $name, 'type' => 'string'];
        }
        $api->shouldReceive('getListDefinition')->andReturn(['properties_definition' => $props, 'read_only' => false]);
        $api->shouldReceive('getListItems')->andReturnUsing(fn () => array_values($this->items));
        $api->shouldReceive('createListItems')->andReturnUsing(function ($list, $batch) {
            foreach ($batch as $p) {
                $id = 'opaque-'.count($this->items);
                $this->items[$id] = ['id' => $id] + $p;
            }

return [];
        });
        $api->shouldReceive('updateListItems')->andReturnUsing(function ($list, $batch) {
            foreach ($batch as $p) {
                $id = $p['item_id'];
                unset($p['item_id']);
                $this->items[$id] = ['id' => $id] + $p;
            }

return [];
        });

        return new Sync($api);
    }

    private function center(): InventarioCentroCosto
    {
        return InventarioCentroCosto::create(['nombre' => 'Centro Sur SUB', 'nombre_normalizado' => 'centro sur sub', 'tipo' => 'SUB', 'codigo_origen' => '20-SUB', 'activo' => true]);
    }

    public function test_sync_is_idempotent_and_preserves_opaque_id_on_rename_and_inactivation(): void
    {
        $c = $this->center();
        $s = $this->service();
        $this->assertSame(1, $s->synchronize(true)['created']);
        $this->assertCount(0, $this->items);
        $this->assertTrue($s->synchronize()['verified']);
        $this->assertSame(1, $s->synchronize()['unchanged']);
        $c->update(['nombre' => 'Centro Sur actualizado', 'activo' => false]);
        $this->assertSame(1, $s->synchronize()['updated']);
        $this->assertCount(1, $this->items);
        $this->assertSame('INACTIVO', $this->items['opaque-0']['properties']['Estado']);
        $this->assertSame($c->id, $s->resolve('507253', 'opaque-0')->id);
        $this->assertNull($s->resolve('OTHER', 'opaque-0'));
    }

    public function test_recovers_post_without_local_mapping_from_remote_id_saep(): void
    {
        $c = $this->center();
        $s = $this->service();
        $s->synchronize();
        DB::table('inventario_kizeo_centros')->delete();
        $this->assertSame(0, $s->synchronize()['created']);
        $this->assertSame(1, DB::table('inventario_kizeo_centros')->count());
    }

    public function test_duplicate_source_identity_blocks_all_writes(): void
    {
        $c = $this->center();
        $this->items = ['x' => ['id' => 'x', 'label' => 'A', 'properties' => ['ID_SAEP' => (string) $c->id]], 'y' => ['id' => 'y', 'label' => 'B', 'properties' => ['ID_SAEP' => (string) $c->id]]];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('duplicado');
        $this->service()->synchronize();
    }

    public function test_missing_previously_mapped_row_is_not_recreated(): void
    {
        $this->center();
        $s = $this->service();
        $s->synchronize();
        $this->items = [];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('historial');
        $s->synchronize();
    }

    public function test_talana_uses_company_and_code_and_does_not_overwrite_app(): void
    {
        $c = $this->center();
        config(['services.talana.inventory_companies' => ['SUB' => 1039, 'EST' => 1081]]);
        $api = \Mockery::mock(TalanaService::class);
        $api->shouldReceive('centrosCosto')->with(1039)->once()->andReturn([['empresa' => 1039, 'codigo' => '20', 'nombre' => 'Talana SUB', 'vigente' => false]]);
        $api->shouldReceive('centrosCosto')->with(1081)->once()->andReturn([['empresa' => 1081, 'codigo' => '20', 'nombre' => 'Otro EST', 'vigente' => true]]);
        $r = (new Audit($api))->audit();
        $this->assertSame(1, $r['matched']);
        $this->assertSame('Talana SUB', $r['nameDifferences'][0]['talana']);
        $this->assertCount(1, $r['statusDifferences']);
        $this->assertTrue($c->fresh()->activo);
    }
}
