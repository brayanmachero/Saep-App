<?php

namespace Tests\Feature;

use App\Http\Controllers\ImportController;
use App\Models\CentroCosto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CentroCostoTalanaSyncTest extends TestCase
{
    use RefreshDatabase;

    private array $talana = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.talana.base_url' => 'https://talana.test/api',
            'services.talana.token' => 'test',
            'services.talana.inventory_companies' => ['SUB' => 1039, 'EST' => 1081],
        ]);

        // La migración de Contenedores ya creó 35-SUB, 136-SUB, 150-EST, etc. con codigo = código Talana.
        $this->talana = [
            1039 => [
                ['codigo' => '35', 'nombre' => 'MAERSK LA VARA', 'vigente' => true],
                ['codigo' => '136', 'nombre' => 'LTS PEÑON', 'vigente' => true],
                ['codigo' => '50', 'nombre' => 'CCU CENTRAL', 'vigente' => true],
                ['codigo' => '60', 'nombre' => 'BODEGA NORTE', 'vigente' => true],
                ['codigo' => '61', 'nombre' => 'DUPLICADO LOCAL', 'vigente' => true],
                ['codigo' => '80', 'nombre' => 'HISTORICO', 'vigente' => false],
                ['codigo' => '90', 'nombre' => 'NUEVO VIGENTE', 'vigente' => true],
                ['codigo' => '95', 'nombre' => 'CERRADO EN TALANA', 'vigente' => false],
            ],
            1081 => [
                ['codigo' => '150', 'nombre' => 'CLUB DE PERROS Y GATOS EST', 'vigente' => true],
                ['codigo' => '60', 'nombre' => 'BODEGA NORTE', 'vigente' => true],
                ['codigo' => '70', 'nombre' => 'BODEGA TRANSITORIA', 'vigente' => true],
            ],
        ];

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $empresa = (int) ($query['empresa'] ?? 0);

            return Http::response(array_map(
                fn ($row) => $row + ['empresa' => $empresa],
                $this->talana[$empresa] ?? []
            ));
        });

        $this->centro('CCU-LOCAL', 'CCU Central');                   // nombre base, otra capitalización
        $this->centro('BN-LOCAL', 'BODEGA NORTE');                   // existe en SUB y EST: decide razón social
        $this->centro('DUP-1', 'DUPLICADO LOCAL');                   // dos locales iguales: conflicto
        $this->centro('DUP-2', 'DUPLICADO LOCAL');
        $this->centro('SOLO-EST', 'BODEGA TRANSITORIA');                     // NORMAL por defecto del CSV, solo existe en EST
        $this->centro('CERR-LOCAL', 'CERRADO EN TALANA');            // vinculable, no vigente
        $this->centro('ADM', 'ADMINISTRACION CENTRAL');              // sin equivalente en Talana
    }

    public function test_simulation_reports_matches_and_changes_nothing(): void
    {
        $antes = CentroCosto::orderBy('id')->get()->toArray();

        $this->artisan('centros:sync-talana')
            ->expectsOutputToContain('SIMULACIÓN')
            ->assertSuccessful();

        $this->assertSame($antes, CentroCosto::orderBy('id')->get()->toArray());
    }

    public function test_plan_links_by_code_name_and_business_name_with_conflicts(): void
    {
        $r = app(\App\Services\CentroCostoTalanaSyncService::class)->synchronize(true);
        $vinculos = collect($r['vinculos'])->keyBy('talana');

        $this->assertSame('código local', $vinculos['35-SUB']['metodo']);
        $this->assertSame('código local', $vinculos['136-SUB']['metodo']);
        $this->assertSame('código local', $vinculos['150-EST']['metodo']);
        $this->assertSame('nombre base', $vinculos['50-SUB']['metodo']);
        $this->assertSame('CCU Central', $vinculos['50-SUB']['local_nombre']);

        // BODEGA NORTE existe en SUB y EST: el local NORMAL va a SUB y EST queda faltante.
        $this->assertSame('BODEGA NORTE', $vinculos['60-SUB']['local_nombre']);
        $this->assertArrayNotHasKey('60-EST', $vinculos->all());
        $this->assertContains('60-EST', collect($r['faltantes'])->pluck('talana')->all());

        // Solo existe en EST: se vincula aunque el local quedó como NORMAL.
        $this->assertSame('nombre base (razón social local distinta)', $vinculos['70-EST']['metodo']);

        $this->assertContains('61-SUB', collect($r['conflictos'])->pluck('talana')->all());
        $this->assertArrayNotHasKey('61-SUB', $vinculos->all());

        $this->assertContains('ADMINISTRACION CENTRAL', collect($r['locales_sin_talana'])->pluck('nombre')->all());
    }

    public function test_apply_only_links_by_default_without_renaming_creating_or_deactivating(): void
    {
        $totalAntes = CentroCosto::count();

        $this->artisan('centros:sync-talana --aplicar')->assertSuccessful();

        $this->assertSame($totalAntes, CentroCosto::count());
        $ccu = CentroCosto::where('codigo', 'CCU-LOCAL')->first();
        $this->assertSame('50-SUB', $ccu->codigo_talana);
        $this->assertSame('SUB', $ccu->modalidad_talana);
        $this->assertSame('CCU CENTRAL', $ccu->nombre_talana);
        $this->assertSame('CCU Central', $ccu->nombre, 'el nombre local no se cambia sin --actualizar-nombres');

        $cerrado = CentroCosto::where('codigo', 'CERR-LOCAL')->first();
        $this->assertSame('95-SUB', $cerrado->codigo_talana);
        $this->assertFalse($cerrado->talana_vigente);
        $this->assertTrue($cerrado->activo, 'no se desactiva sin --desactivar-no-vigentes');

        $this->assertNull(CentroCosto::where('codigo', 'DUP-1')->value('codigo_talana'));

        // Segunda corrida: todo queda como vínculo existente, sin cambios.
        $r = app(\App\Services\CentroCostoTalanaSyncService::class)->synchronize(true);
        $this->assertTrue(collect($r['vinculos'])->every(fn ($v) => $v['metodo'] === 'vínculo existente'));
    }

    public function test_optional_actions_create_rename_and_deactivate(): void
    {
        $this->artisan('centros:sync-talana --aplicar --crear-faltantes --actualizar-nombres --desactivar-no-vigentes')
            ->assertSuccessful();

        $nuevo = CentroCosto::where('codigo_talana', '90-SUB')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('NUEVO VIGENTE SUB', $nuevo->nombre);
        $this->assertSame('NORMAL', $nuevo->razon_social);

        $bodegaEst = CentroCosto::where('codigo_talana', '60-EST')->first();
        $this->assertSame('BODEGA NORTE EST', $bodegaEst->nombre);
        $this->assertSame('TRANSITORIO', $bodegaEst->razon_social);

        $this->assertNull(CentroCosto::where('codigo_talana', '80-SUB')->first(), 'los no vigentes no se crean');
        $this->assertSame('CCU CENTRAL SUB', CentroCosto::where('codigo', 'CCU-LOCAL')->value('nombre'));
        $this->assertFalse((bool) CentroCosto::where('codigo', 'CERR-LOCAL')->value('activo'));
        $this->assertTrue((bool) CentroCosto::where('codigo', 'ADM')->value('activo'), 'los centros sin Talana no se tocan');
    }

    public function test_invalid_talana_response_aborts_without_changes(): void
    {
        $this->talana[1081] = [];

        $this->artisan('centros:sync-talana --aplicar')->assertFailed();

        $this->assertSame(0, CentroCosto::whereNotNull('codigo_talana')->count());
    }

    public function test_worker_import_reuses_center_with_modality_suffix_instead_of_duplicating(): void
    {
        $lookup = (new \ReflectionMethod(ImportController::class, 'centrosCostoLookup'))
            ->invoke(app(ImportController::class));

        $penon = CentroCosto::where('codigo', '136-SUB')->first();
        $this->assertSame($penon->id, $lookup->get('lts peñon')?->id);
        $this->assertSame($penon->id, $lookup->get('lts peñon sub')?->id);
        // Alias ambiguo (dos "DUPLICADO LOCAL") no se resuelve por alias, pero sí por nombre exacto.
        $this->assertNotNull($lookup->get('duplicado local'));
    }

    private function centro(string $codigo, string $nombre, string $razon = 'NORMAL'): CentroCosto
    {
        return CentroCosto::create(['codigo' => $codigo, 'nombre' => $nombre, 'razon_social' => $razon, 'activo' => true]);
    }
}
