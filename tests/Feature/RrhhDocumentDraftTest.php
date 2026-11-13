<?php

namespace Tests\Feature;

use App\Models\TalanaContrato;
use App\Services\RrhhDocumentDraftService;
use App\Services\RrhhDocumentReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RrhhDocumentDraftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        foreach (['rrhh_document_deliveries', 'rrhh_document_batch_events', 'rrhh_document_batch_items', 'rrhh_document_batches', 'rrhh_document_batteries', 'talana_contratos', 'modulos'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('modulos', function (Blueprint $t) {
            $t->id();
            foreach (['slug', 'nombre', 'descripcion', 'icono', 'grupo'] as $f) {
                $t->string($f);
            }$t->integer('orden');
            $t->boolean('activo');
            $t->timestamps();
        });
        Schema::create('talana_contratos', function (Blueprint $t) {
            $t->id();
            foreach (['talana_id', 'persona_talana_id', 'empresa_id'] as $f) {
                $t->integer($f);
            }foreach (['persona_nombre', 'persona_rut', 'empresa_nombre', 'cargo_nombre', 'centro_costo_nombre', 'tipo_contrato_nombre'] as $f) {
                $t->string($f)->nullable();
            }$t->boolean('finiquitado');
            $t->date('desde')->nullable();
            $t->date('hasta')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_15_200000_create_rrhh_document_drafts.php'))->up();
        (require base_path('database/migrations/2026_10_15_210000_add_document_batch_review.php'))->up();
        (require base_path('database/migrations/2026_11_13_130000_create_rrhh_document_deliveries.php'))->up();
        DB::table('rrhh_document_batteries')->insert(['id' => 1, 'nombre' => 'Prueba', 'version' => 1, 'documentos' => '["Contrato","Anexo"]', 'reglas' => '{}', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function contract(int $n, array $extra = []): TalanaContrato
    {
        return TalanaContrato::create(array_merge(['talana_id' => $n, 'persona_talana_id' => $n, 'empresa_id' => 1039, 'empresa_nombre' => 'SAEP SUB', 'persona_nombre' => 'Persona '.$n, 'persona_rut' => '12345678-9', 'cargo_nombre' => 'Operario', 'centro_costo_nombre' => 'CCU', 'tipo_contrato_nombre' => 'Indefinido', 'finiquitado' => false, 'desde' => today()->subMonth(), 'synced_at' => now()], $extra));
    }

    public function test_est_view_search_and_saving_a_reviewed_version(): void
    {
        $user = new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool
            {
                return $moduloSlug === 'gestion_documental';
            }
        };
        $user->id = 7;
        $user->setRelation('rol', new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        $this->contract(1);
        config(['services.talana_documents.token' => 'test']);
        Http::fake(['*/documentos/*' => Http::response(['results'=>[['nombre'=>'Ejemplo.pdf'],['nombre'=>'Ejemplo.pdf']], 'next'=>null, 'count'=>2])]);
        $url = route('rrhh-documentos.est', ['grupo'=>'IDL Macul', 'buscar'=>'Ejemplo']);
        $this->get($url)->assertOk()->assertSee('20 referencias')->assertSee('Ejemplo.pdf')->assertSee('1 título distinto');
        $post = ['nombre'=>'QA IDL', 'centro_costo_nombre'=>'CCU', 'documentos'=>"Anexo\nIRL", 'version'=>1,'origen_est'=>1];
        $this->from($url)->post(route('rrhh-documentos.battery'),$post)->assertRedirect(route('rrhh-documentos.index'));
        $this->assertDatabaseHas('rrhh_document_batteries',['nombre'=>'QA IDL','created_by'=>7]);
        $this->from($url)->post(route('rrhh-documentos.battery'),$post)->assertSessionHasErrors('nombre');
        $this->get($url)->assertSee('Ya existe esa batería')->assertSee('QA IDL');
    }

    private function data(array $extra = []): array
    {
        return array_merge(['nombre' => 'Lote prueba', 'request_id' => (string) Str::uuid(), 'battery_id' => 1, 'seleccion' => 'todos'], $extra);
    }

    public function test_dispatch_uploads_once_then_requests_each_signature_once(): void
    {
        $this->contract(1);
        $batch = (new RrhhDocumentDraftService)->create($this->data(), 1);
        $item = DB::table('rrhh_document_batch_items')->value('id');
        $disk = Storage::fake('local');
        foreach ([0, 1] as $slot) {
            $pdf = '%PDF-1.4 synthetic '.$slot;
            $disk->put("private/$slot.pdf", $pdf);
            DB::table('rrhh_document_deliveries')->insert(['item_id' => $item, 'position' => $slot, 'category' => 'Prueba', 'path' => "private/$slot.pdf", 'sha256' => hash('sha256', $pdf), 'prepared_by' => 1]);
        }
        config(['services.talana_documents.token' => 'fake', 'services.talana_documents.writes' => false]);
        $this->artisan('rrhh:documentos', ['lote' => $batch])->assertSuccessful();
        $this->artisan('rrhh:documentos', ['lote' => $batch, '--ejecutar' => true])->assertFailed();
        Http::assertNothingSent();
        config(['services.talana_documents.writes' => true]);
        Http::fake(['*/documentos/' => Http::sequence()->push(['id' => 101], 201)->push(['id' => 102], 201),
            '*/integration-signature-requests-paginated/*' => Http::response(['results' => [['id' => 201, 'signed' => true], ['id' => 202, 'signed' => false]]]),
            '*/requestSignature' => Http::sequence()->push(['sr_id' => 201])->push(['sr_id' => 202])]);
        foreach (['--ejecutar', '--ejecutar', '--firmar', '--firmar'] as $option) {
            $this->artisan('rrhh:documentos', ['lote' => $batch, $option => true])->assertSuccessful();
        }
        Http::assertSentCount(4);
        $this->assertSame(2, DB::table('rrhh_document_deliveries')->where('state', 'firma_solicitada')->count());
        $this->artisan('rrhh:documentos', ['lote' => $batch, '--consultar' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('rrhh_document_deliveries')->where('state', 'firmado')->count());
    }

    public function test_batch_supports_fifty_workers_with_twenty_documents(): void
    {
        DB::table('rrhh_document_batteries')->update(['documentos' => json_encode(array_map(fn ($i) => 'Documento '.$i, range(1, 20)))]);
        for ($i = 1; $i <= 50; $i++) {
            $this->contract($i, ['persona_rut' => "test-{$i}"]);
        }
        $batch = (new RrhhDocumentDraftService)->create($this->data(), 1);
        $this->assertSame(1000, (int) DB::table('rrhh_document_batches')->where('id', $batch)->value('documentos'));
        Http::assertNothingSent();
    }

    public function test_ambiguous_response_is_not_retried(): void
    {
        $this->contract(1);
        DB::table('rrhh_document_batteries')->update(['documentos' => '["Contrato"]']);
        $batch = (new RrhhDocumentDraftService)->create($this->data(), 1);
        Storage::fake('local')->put('file.pdf', 'synthetic');
        DB::table('rrhh_document_deliveries')->insert(['item_id' => DB::table('rrhh_document_batch_items')->value('id'), 'position' => 0, 'category' => 'Contrato', 'path' => 'file.pdf', 'sha256' => hash('sha256', 'synthetic'), 'prepared_by' => 1]);
        config(['services.talana_documents.token' => 'fake', 'services.talana_documents.writes' => true]);
        Http::fake(['*/documentos/' => Http::response([], 502)]);
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('rrhh:documentos', ['lote' => $batch, '--ejecutar' => true])->assertFailed();
        }
        Http::assertSentCount(1);
        $this->assertSame('revisar_carga', DB::table('rrhh_document_deliveries')->value('state'));
    }

    public function test_manifest_is_atomic_and_repeatable(): void
    {
        $this->contract(1);
        $batch = (new RrhhDocumentDraftService)->create($this->data(), 1);
        $disk = Storage::fake('local');
        $dir = sys_get_temp_dir().'/'.Str::uuid();
        mkdir($dir);
        try {
            $args = ['lote' => $batch, 'directorio' => $dir];
            $this->artisan('rrhh:preparar-pdfs', $args)->assertSuccessful();
            $csv = file_get_contents("$dir/manifiesto.csv");
            file_put_contents("$dir/manifiesto.csv", preg_replace('/,(\r?\n)/', ',Personal$1', $csv));
            file_put_contents("$dir/contrato-1-doc-1.pdf", "%PDF-1.4\n%%EOF");
            $this->artisan('rrhh:preparar-pdfs', $args + ['--importar' => true])->assertFailed();
            $this->assertSame(0, DB::table('rrhh_document_deliveries')->count());
            $this->assertEmpty($disk->allFiles());
            file_put_contents("$dir/contrato-1-doc-2.pdf", "%PDF-1.4\n%%EOF");
            for ($i = 0; $i < 2; $i++) {
                $this->artisan('rrhh:preparar-pdfs', $args + ['--importar' => true])->assertSuccessful();
            }
            $this->assertSame(2, DB::table('rrhh_document_deliveries')->count());
            Http::assertNothingSent();
        } finally {
            foreach (glob("$dir/*") as $f) {
                unlink($f);
            }
            rmdir($dir);
        }
    }

    public function test_selects_all_results_beyond_page_and_excludes_expired_future_terminated(): void
    {
        for ($i = 1; $i <= 65; $i++) {
            $this->contract($i);
        }
        $this->contract(66, ['finiquitado' => true]);
        $this->contract(67, ['hasta' => today()->subDay()]);
        $this->contract(68, ['desde' => today()->addDay()]);
        $service = new RrhhDocumentDraftService;
        $data = $this->data();
        $id = $service->create($data, 1);
        $batch = DB::table('rrhh_document_batches')->find($id);
        $this->assertSame(65, $batch->trabajadores);
        $this->assertSame(130, $batch->documentos);
        $this->assertSame(0, $batch->observados);
        $this->assertSame(65, DB::table('rrhh_document_batch_items')->count());
        $this->assertSame($id, $service->create($data, 1));
        $this->assertSame(1, DB::table('rrhh_document_batches')->count());
        Http::assertNothingSent();
    }

    public function test_manual_selection_cannot_include_other_filter_results(): void
    {
        $c = $this->contract(1, ['empresa_id' => 1081]);
        $this->expectException(ValidationException::class);
        (new RrhhDocumentDraftService)->create($this->data(['seleccion' => 'manual', 'contratos' => [$c->id], 'empresa_id' => 1039]), 1);
    }

    public function test_snapshot_rules_stale_data_and_duplicate_battery_are_visible(): void
    {
        $c = $this->contract(1, ['synced_at' => now()->subDays(3)]);
        DB::table('rrhh_document_batteries')->where('id', 1)->update(['reglas' => '{"cargo_nombre":"Supervisor"}']);
        $s = new RrhhDocumentDraftService;
        $id = $s->create($this->data(), 1);
        $batch = DB::table('rrhh_document_batches')->find($id);
        $this->assertSame(1, $batch->observados);
        $item = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->first();
        $this->assertCount(2, json_decode($item->observaciones, true));
        $c->update(['persona_nombre' => 'Nombre cambiado']);
        DB::table('rrhh_document_batteries')->where('id', 1)->update(['documentos' => '["Otra definición"]']);
        $this->assertSame('Persona 1', json_decode($item->snapshot, true)['persona_nombre']);
        $this->assertCount(2, json_decode($batch->battery_snapshot, true)['documentos']);
        $second = $s->create($this->data(), 1);
        $this->assertStringContainsString('otro borrador', DB::table('rrhh_document_batch_items')->where('batch_id', $second)->value('observaciones'));
        Http::assertNothingSent();
    }

    public function test_review_preserves_snapshot_recalculates_and_locks_cancelled_batch(): void
    {
        $this->contract(1);
        $this->contract(2, ['synced_at' => now()->subDays(3)]);
        $id = (new RrhhDocumentDraftService)->create($this->data(), 1);
        $item = DB::table('rrhh_document_batch_items')->where('batch_id', $id)->orderByDesc('id')->first();
        $review = new RrhhDocumentReviewService;
        $review->update($id, ['accion' => 'retirar', 'item_id' => $item->id, 'motivo' => 'Fuera de alcance'], 1);
        $b = DB::table('rrhh_document_batches')->find($id);
        $this->assertSame(1, $b->trabajadores);
        $this->assertSame(2, $b->documentos);
        $this->assertSame(0, $b->observados);
        $this->assertSame($item->snapshot, DB::table('rrhh_document_batch_items')->find($item->id)->snapshot);
        $review->update($id, ['accion' => 'restaurar', 'item_id' => $item->id, 'motivo' => 'Corresponde al lote'], 1);
        $this->assertSame(2, DB::table('rrhh_document_batches')->find($id)->trabajadores);
        $review->update($id, ['accion' => 'cancelar', 'motivo' => 'Solicitud cancelada'], 1);
        $this->assertSame('CANCELADO', DB::table('rrhh_document_batches')->find($id)->estado);
        $this->assertSame(3, DB::table('rrhh_document_batch_events')->count());
        $this->expectException(ValidationException::class);
        $review->update($id, ['accion' => 'retirar', 'item_id' => $item->id, 'motivo' => 'No permitido'], 1);
    }

    public function test_cannot_change_item_from_another_batch(): void
    {
        $this->contract(1);
        $service = new RrhhDocumentDraftService;
        $id = $service->create($this->data(), 1);
        $other = $service->create($this->data(), 1);
        $item = DB::table('rrhh_document_batch_items')->where('batch_id', $other)->first();
        try {
            (new RrhhDocumentReviewService)->update($id, ['accion' => 'retirar', 'item_id' => $item->id, 'motivo' => 'Otro lote'], 1);
            $this->fail('Expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertSame(0,DB::table('rrhh_document_batch_events')->count());
    }
}
