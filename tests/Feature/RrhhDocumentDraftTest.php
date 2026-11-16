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
        foreach (['rrhh_document_battery_events', 'rrhh_document_deliveries', 'rrhh_document_batch_events', 'rrhh_document_batch_items', 'rrhh_document_batches', 'rrhh_document_batteries', 'talana_contratos', 'modulos'] as $t) {
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
        (require base_path('database/migrations/2026_11_16_180000_add_document_battery_review.php'))->up();
        DB::table('rrhh_document_batteries')->insert(['id' => 1, 'estado'=>'ACTIVA', 'nombre' => 'Prueba', 'version' => 1, 'documentos' => '["Contrato","Anexo"]', 'reglas' => '{}', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
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
        $this->contract(1, ['empresa_id' => 1081]);
        config(['services.talana_documents.token' => 'test']);
        Http::fake(['*/documentos/*' => Http::response(['results'=>[['nombre'=>'Ejemplo.pdf','id'=>10],['nombre'=>'Ejemplo.pdf','id'=>11]], 'next'=>null, 'count'=>2])]);
        $url = route('rrhh-documentos.est', ['grupo'=>'IDL Macul', 'buscar'=>'Ejemplo']);
        $this->get($url)->assertOk()->assertSee('20 referencias')->assertSee('Guía de documentos EST')->assertDontSee('Pendiente de validar');
        Http::assertNothingSent();
        $url = route('rrhh-documentos.battery-create', ['grupo'=>'IDL Macul','buscar'=>'Ejemplo']);
        $this->get($url)->assertOk()->assertSee('Crear borrador y abrir documentos')->assertSee('Ejemplo');
        $this->get(route('rrhh-documentos.index'))->assertOk()->assertSee('Prueba')->assertSee('Activas');
        $this->get(route('rrhh-documentos.index',['estado'=>'BORRADOR']))->assertOk()->assertDontSee('Ver batería y documentos');
        $this->get(route('rrhh-documentos.lots'))->assertOk()->assertSee('Lotes de trabajadores');
        $options = $this->getJson(route('rrhh-documentos.document-options',['q'=>'Ejemplo']))->assertOk()->json('options');
        $post = ['nombre'=>'QA IDL', 'centro_costo_nombre'=>'CCU', 'documentos'=>[$options[0]['value']], 'version'=>1,'origen_est'=>1];
        $this->from($url)->post(route('rrhh-documentos.battery'),$post)->assertRedirect(route('rrhh-documentos.battery-show',2));
        $tampered = $post;
        $tampered['documentos'] = ['inventado'];
        $this->from($url)->post(route('rrhh-documentos.battery'),$tampered)->assertSessionHasErrors('documentos');
        $this->assertDatabaseHas('rrhh_document_batteries',['nombre'=>'QA IDL','created_by'=>7]);
        $this->from($url)->post(route('rrhh-documentos.battery'),$post)->assertSessionHasErrors('nombre');
        $this->get($url)->assertSee('Ya existe esa batería')->assertSee('QA IDL');
    }

    public function test_battery_review_activation_removal_and_version_history(): void
    {
        $user = new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public bool $allowEdits = true;
            public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool { return $accion === 'puede_ver' || $this->allowEdits; }
        };
        $user->id=7;
        $user->name='Revisor QA';
        $user->setRelation('rol',new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        $this->contract(1);
        config(['services.talana_documents.token'=>'test']);
        DB::table('rrhh_document_batteries')->where('id',1)->update([
            'estado'=>'BORRADOR','documentos'=>'["Contrato"]','document_details'=>'{"Contrato":{"talana_id":123}}',
        ]);
        $detail=route('rrhh-documentos.battery-show',1);
        DB::table('rrhh_document_batteries')->where('id',1)->update(['documentos'=>'["Contrato","Anexo"]','document_details'=>'{"Contrato":{"talana_id":123,"validated_at":"2026-11-16 10:00:00","validated_by":7}}']);
        $this->get($detail)->assertOk()->assertSee('<h3 class="doc-review-title">Anexo</h3>',false);
        $this->get($detail.'?documento=0')->assertOk()->assertSee('<h3 class="doc-review-title">Contrato</h3>',false);
        DB::table('rrhh_document_batteries')->where('id',1)->update(['documentos'=>'["Contrato"]','document_details'=>'{"Contrato":{"talana_id":123}}']);

        DB::table('rrhh_document_batteries')->where('id',1)->update(['document_details'=>json_encode(['Contrato'=>['talana_id'=>123,'review_note'=>'Confirmar EST antes de vincular','candidate_names'=>['Candidato <EST>.pdf'],'checked_at'=>'2026-11-16T12:00:00Z']])]);
        $this->get($detail)->assertOk()->assertSee('Resultado de la conciliación')->assertSee('Candidato &lt;EST&gt;.pdf',false)->assertDontSee('Candidato <EST>.pdf',false);
        $user->allowEdits=false;
        $this->get($detail)->assertOk()->assertDontSee('Quitar de esta batería');
        $this->post(route('rrhh-documentos.battery-change',1),['revision'=>1,'action'=>'remove','position'=>0])->assertForbidden();
        $user->allowEdits=true;
        $this->get($detail)->assertOk()->assertSee('document-preview')->assertSee('Validar documento')->assertSee('Quitar de esta batería');
        $this->from($detail)->post(route('rrhh-documentos.battery-activate',1),['revision'=>1])->assertSessionHasErrors('bateria');
        $validate=route('rrhh-documentos.battery-validate',[1,0]);
        $this->from($detail)->post($validate,['revision'=>1,'confirmed'=>1])->assertSessionHasErrors('documento');
        Http::fake(['https://talana.com/es/remuneraciones/download-document/test'=>fn()=>Http::response('%PDF-1.4 example'), '*/documentos/123/'=>Http::response(['nombre'=>'Contrato','adjunto'=>'https://talana.com/es/remuneraciones/download-document/test'])]);
        $this->get(route('rrhh-documentos.battery-preview',[1,0]))->assertOk()->assertSee('Validar documento');
        $this->get(route('rrhh-documentos.battery-pdf',[1,0]))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->post($validate,['revision'=>1,'confirmed'=>1])->assertRedirect($detail);
        $this->get($detail)->assertSee('Revisor QA')->assertSee('1 de 1 documentos validados');
        $this->post(route('rrhh-documentos.battery-activate',1),['revision'=>2])->assertRedirect($detail);
        $this->assertDatabaseHas('rrhh_document_batteries',['id'=>1,'estado'=>'ACTIVA','revision'=>3]);
        $batch=(new RrhhDocumentDraftService)->create($this->data(),7);
        $snapshot=DB::table('rrhh_document_batches')->where('id',$batch)->value('battery_snapshot');
        $this->from($detail)->post(route('rrhh-documentos.battery-change',1),['revision'=>3,'action'=>'remove','position'=>0])->assertSessionHasErrors('bateria');
        for($i=0;$i<2;$i++){
            $this->post(route('rrhh-documentos.battery-clone',1))->assertRedirect(route('rrhh-documentos.battery-show',2));
        }
        $this->assertSame(2,DB::table('rrhh_document_batteries')->count());
        $draft=route('rrhh-documentos.battery-show',2);
        $this->get($draft)->assertSee('PDF vinculado · pendiente de revisión');
        $this->post(route('rrhh-documentos.battery-change',2),['revision'=>1,'action'=>'remove','position'=>0])->assertRedirect($draft);
        $this->get($draft)->assertSee('La batería está vacía');
        $this->from($draft)->post(route('rrhh-documentos.battery-activate',2),['revision'=>2])->assertSessionHasErrors('bateria');
        $token=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['user'=>7,'name'=>'Contrato','id'=>123]));
        $this->post(route('rrhh-documentos.battery-change',2),['revision'=>2,'action'=>'add','documentos'=>[$token]])->assertRedirect($draft);
        $this->from($draft)->post(route('rrhh-documentos.battery-change',2),['revision'=>2,'action'=>'remove','position'=>0])->assertSessionHasErrors('bateria');
        $this->get(route('rrhh-documentos.battery-preview',[2,0]))->assertOk();
        $this->get(route('rrhh-documentos.battery-pdf',[2,0]))->assertOk();
        $this->post(route('rrhh-documentos.battery-validate',[2,0]),['revision'=>3,'confirmed'=>1])->assertRedirect($draft);
        $this->post(route('rrhh-documentos.battery-activate',2),['revision'=>4])->assertRedirect($draft);
        $this->assertDatabaseHas('rrhh_document_batteries',['id'=>1,'estado'=>'ARCHIVADA']);
        $this->assertDatabaseHas('rrhh_document_batteries',['id'=>2,'estado'=>'ACTIVA']);
        $this->assertSame($snapshot,DB::table('rrhh_document_batches')->where('id',$batch)->value('battery_snapshot'));
        $this->assertSame(1,DB::table('rrhh_document_battery_events')->where('battery_id',2)->where('action','remove')->count());
    }

    public function test_preview_rejects_external_hosts_without_sending_credentials(): void
    {
        try {
            (new \App\Services\TalanaDocumentApi)->previewPdf('https://example.com/document.pdf');
            $this->fail('External host should be rejected');
        } catch (\RuntimeException $exception) {
            $this->assertSame('El adjunto requiere revisión.', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_document_dates_are_grouped_sorted_and_missing_dates_stay_last(): void
    {
        $api = new \App\Services\TalanaDocumentApi;
        $rows = [
            ['nombre'=>'Anexo rv.01.pdf','fechaCreacion'=>'2026-01-01T10:00:00Z'],
            ['nombre'=>'Anexo rv.01.pdf','fechaCreacion'=>'2026-11-16T15:30:00Z'],
            ['nombre'=>'Manual V1.2.pdf','fechaCreacion'=>'2026-05-01T10:00:00Z'],
            ['nombre'=>'Sin fecha.pdf','fechaCreacion'=>'invalid'],
        ];
        $recent = array_values($api->summarizeDocuments($rows));
        $this->assertCount(3,$recent);
        $this->assertSame('16/11/2026 12:30',$recent[0]['date']);
        $this->assertSame('01',$recent[0]['version']);
        $this->assertSame('1.2',$recent[1]['version']);
        $this->assertNull($recent[2]['date']);
        $oldest = array_values($api->summarizeDocuments($rows,'oldest'));
        $this->assertSame('Manual V1.2.pdf',$oldest[0]['name']);
        $this->assertSame('Sin fecha.pdf',$oldest[2]['name']);
        $this->assertSame([], $api->summarizeDocuments([]));
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
