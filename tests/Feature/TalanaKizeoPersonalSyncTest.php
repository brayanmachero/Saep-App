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
            $table->date('fecha_nacimiento')->nullable();
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
            $table->date('fecha_contratacion')->nullable();
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
        $retryMigration = require dirname(__DIR__, 2).'/database/migrations/2026_08_24_120000_add_retry_window_to_talana_kizeo_personal_items_table.php';
        $retryMigration->up();
        Cache::flush();
        config([
            'services.kizeo.personal_cdd_list_id' => '501626',
            'services.kizeo.token' => 'kizeo-test-token',
            'services.kizeo.personal_cdd_minimum_count' => 0,
            'services.kizeo.personal_cdd_max_source_age_minutes' => 0,
        ]);
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

    public function test_current_contract_is_published_even_when_the_person_table_has_no_matching_talana_id(): void
    {
        TalanaContrato::create([
            'talana_id' => 200,
            'persona_talana_id' => 999999,
            'persona_nombre' => 'Camila Andrea Perez Soto',
            'persona_rut' => '17222333-4',
            'persona_email' => 'camila@saep.cl',
            'desde' => now('America/Santiago')->subMonth()->toDateString(),
            'hasta' => null,
            'finiquitado' => false,
            'centro_costo_nombre' => 'CCU RENCA',
            'cargo_nombre' => 'OPERADORA',
        ]);
        $this->fakeKizeoList();

        $summary = app(TalanaKizeoPersonalSyncService::class)->synchronize();

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['created']);
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
            && ($request['items'][0]['label'] ?? null) === '17222333-4');
    }

    public function test_duplicate_remote_rut_is_mapped_without_creating_a_third_item(): void
    {
        $this->seedVigenteWorker();
        $this->fakeKizeoList(existing: [
            ['id' => 'remote-a', 'label' => '16149512-3', 'properties' => []],
            ['id' => 'remote-b', 'label' => '16149512-3', 'properties' => []],
        ]);

        $summary = app(TalanaKizeoPersonalSyncService::class)->synchronize();

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['duplicates']);
        Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'POST');
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/lists/501626/items')
            && ($request['items'][0]['item_id'] ?? null) === 'remote-a');
    }

    public function test_age_and_company_seniority_are_published_when_talana_has_the_source_dates(): void
    {
        $this->seedVigenteWorker();
        TalanaPersona::query()->update([
            'fecha_nacimiento' => now('America/Santiago')->subYears(30)->toDateString(),
        ]);
        TalanaContrato::query()->update([
            'fecha_contratacion' => now('America/Santiago')->subYears(2)->subMonths(3)->toDateString(),
        ]);

        $this->fakeKizeoList();
        app(TalanaKizeoPersonalSyncService::class)->synchronize();

        Http::assertSent(function (HttpRequest $request) {
            return $request->method() === 'POST'
                && ($request['items'][0]['properties']['property-edad'] ?? null) === '30'
                && ($request['items'][0]['properties']['property-antiguedad'] ?? null) === '2 años y 3 meses';
        });
    }

    public function test_reconciliation_removes_duplicate_and_stale_ruts_only_after_the_source_is_validated(): void
    {
        $this->seedVigenteWorker();
        $this->fakeKizeoList(existing: [
            ['id' => 'remote-a', 'label' => '16149512-3', 'properties' => []],
            ['id' => 'remote-b', 'label' => '16149512-3', 'properties' => []],
            ['id' => 'remote-stale', 'label' => '19111111-1', 'properties' => []],
        ]);

        $summary = app(TalanaKizeoPersonalSyncService::class)->synchronize(false, 250, true);

        $this->assertSame([], $summary['errors']);
        $this->assertSame(1, $summary['stale']);
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/lists/501626/items/remote-b'));
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/lists/501626/items/remote-stale'));
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
                'edad' => ['id' => 'property-edad', 'display_name' => 'Edad'],
                'antiguedad' => ['id' => 'property-antiguedad', 'display_name' => 'Antigüedad'],
            ],
        ];

        Http::fake(function (HttpRequest $request) use ($definition, &$existing, &$deleted) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/definition')) {
                return Http::response($definition);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/items')) {
                return Http::response($deleted ? [] : $existing);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/items')) {
                $created = [];
                foreach ($request['items'] as $index => $payload) {
                    $item = [
                        'id' => $index === 0 ? 'remote-jessica' : 'remote-'.$index,
                        'label' => $payload['label'],
                        'properties' => $payload['properties'],
                    ];
                    $existing[] = $item;
                    $created[] = $item;
                }

                return Http::response(['items' => $created], 201);
            }
            if ($request->method() === 'PATCH' && (str_ends_with($request->url(), '/items') || str_contains($request->url(), '/items/'))) {
                return Http::response(['status' => 'ok']);
            }
            if ($request->method() === 'DELETE' && str_contains($request->url(), '/items/')) {
                $deleted = true;

                return Http::response(['status' => 'ok']);
            }

            return Http::response(['message' => 'Ruta de prueba no esperada'], 404);
        });
    }
}
