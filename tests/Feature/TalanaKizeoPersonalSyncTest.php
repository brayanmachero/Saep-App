<?php

namespace Tests\Feature;

use App\Models\InventarioCentroCosto;
use App\Models\TalanaContrato;
use App\Models\TalanaKizeoPersonalItem;
use App\Models\TalanaPersona;
use App\Services\TalanaKizeoPersonalSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TalanaKizeoPersonalSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('talana_kizeo_personal_items');
        Schema::dropIfExists('talana_contratos');
        Schema::dropIfExists('talana_personas');
        Schema::dropIfExists('inventario_centros_costo');

        Schema::create('talana_personas', function (Blueprint $table) {
            $table->id();
            $table->integer('talana_id')->unique();
            $table->string('rut', 20)->nullable();
            $table->string('nombre', 100)->nullable();
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('apellido_materno', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('talana_contratos', function (Blueprint $table) {
            $table->id();
            $table->integer('talana_id')->unique();
            $table->integer('persona_talana_id')->nullable();
            $table->string('persona_nombre', 200)->nullable();
            $table->string('persona_rut', 20)->nullable();
            $table->string('persona_email', 150)->nullable();
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->boolean('finiquitado')->default(false);
            $table->string('centro_costo_nombre', 150)->nullable();
            $table->string('cargo_nombre', 150)->nullable();
            $table->string('jefe_nombre', 200)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventario_centros_costo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 220);
            $table->string('nombre_normalizado', 240)->unique();
            $table->string('jefe_operaciones', 200)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_21_200000_create_talana_kizeo_personal_items_table.php';
        $migration->up();
        Cache::flush();
        config(['services.kizeo.personal_cdd_list_id' => '501626', 'services.kizeo.token' => 'kizeo-test-token']);
    }

    public function test_active_talana_worker_is_published_to_kizeo_with_cdd_boss_and_rut_label(): void
    {
        $this->seedVigenteWorker();
        InventarioCentroCosto::create([
            'nombre' => 'CCU RENCA',
            'nombre_normalizado' => 'ccu renca',
            'jefe_operaciones' => 'Gonzalo Apablaza',
            'activo' => true,
        ]);

        $this->fakeKizeoList();
        $summary = app(TalanaKizeoPersonalSyncService::class)->synchronize();

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['removed']);
        $this->assertSame([], $summary['errors']);
        $this->assertDatabaseHas('talana_kizeo_personal_items', [
            'rut' => '161495123',
            'kizeo_item_id' => 'remote-jessica',
        ]);
        Http::assertSent(function (HttpRequest $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/lists/501626/items')
                && ($request['items'][0]['label'] ?? null) === '16149512-3'
                && ($request['items'][0]['properties']['property-jefe'] ?? null) === 'Gonzalo Apablaza'
                && ($request['items'][0]['properties']['property-cdd'] ?? null) === 'CCU RENCA';
        });
    }

    public function test_finiquited_or_inactive_workers_are_removed_from_kizeo(): void
    {
        $this->seedVigenteWorker();
        TalanaKizeoPersonalItem::create([
            'rut' => '161495123',
            'kizeo_list_id' => '501626',
            'kizeo_item_id' => 'remote-jessica',
        ]);
        TalanaContrato::query()->update(['finiquitado' => true]);

        $deleted = false;
        $this->fakeKizeoList(existing: [[
            'id' => 'remote-jessica',
            'label' => '16149512-3',
            'properties' => [],
        ]], deleted: $deleted);

        $summary = app(TalanaKizeoPersonalSyncService::class)->synchronize();

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['removed']);
        $this->assertSame([], $summary['errors']);
        $this->assertDatabaseMissing('talana_kizeo_personal_items', ['rut' => '161495123']);
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/lists/501626/items/remote-jessica'));
    }

    public function test_expired_contract_without_finiquito_is_not_published(): void
    {
        $this->seedVigenteWorker();
        TalanaContrato::query()->update([
            'finiquitado' => false,
            'hasta' => now('America/Santiago')->subDay()->toDateString(),
        ]);
        $this->fakeKizeoList();

        $summary = app(TalanaKizeoPersonalSyncService::class)->synchronize();

        $this->assertSame(0, $summary['created']);
        $this->assertSame(0, $summary['total']);
        Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'POST');
    }

    private function seedVigenteWorker(): void
    {
        TalanaPersona::create([
            'talana_id' => 10,
            'rut' => '16149512-3',
            'nombre' => 'Jessica Paola',
            'apellido_paterno' => 'Morales',
            'apellido_materno' => null,
            'email' => 'jmorales@saep.cl',
            'activo' => true,
        ]);
        TalanaContrato::create([
            'talana_id' => 100,
            'persona_talana_id' => 10,
            'persona_rut' => '16149512-3',
            'desde' => now('America/Santiago')->subMonth()->toDateString(),
            'hasta' => null,
            'finiquitado' => false,
            'centro_costo_nombre' => 'CCU RENCA',
            'cargo_nombre' => 'SUPERVISOR(A) SENIOR DE OPERACIONES',
            'jefe_nombre' => 'Jefe del contrato',
        ]);
    }

    private function fakeKizeoList(array $existing = [], bool &$deleted = false): void
    {
        $definition = [
            'properties_definition' => [
                'cdd' => ['id' => 'property-cdd', 'display_name' => 'CD'],
                'nombres' => ['id' => 'property-nombres', 'display_name' => 'Nombres'],
                'apellido' => ['id' => 'property-apellido', 'display_name' => 'Apellido'],
                'nombre' => ['id' => 'property-completo', 'display_name' => 'Nombre completo'],
                'email' => ['id' => 'property-email', 'display_name' => 'Email'],
                'cargo' => ['id' => 'property-cargo', 'display_name' => 'Cargo'],
                'jefe' => ['id' => 'property-jefe', 'display_name' => 'Jefe'],
            ],
        ];

        Http::fake(function (HttpRequest $request) use ($definition, $existing, &$deleted) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/definition')) {
                return Http::response($definition);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/items')) {
                return Http::response($deleted ? [] : $existing);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/items')) {
                return Http::response(['id' => 'remote-jessica'], 201);
            }
            if ($request->method() === 'DELETE' && str_contains($request->url(), '/items/')) {
                $deleted = true;

                return Http::response(['status' => 'ok']);
            }

            return Http::response(['message' => 'Ruta de prueba no esperada'], 404);
        });
    }
}
