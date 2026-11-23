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
        (require base_path('database/migrations/2026_11_19_140000_add_battery_composition.php'))->up();
        DB::table('rrhh_document_batteries')->insert(['id' => 1, 'estado'=>'ACTIVA', 'nombre' => 'Prueba', 'version' => 1, 'documentos' => '["Contrato","Anexo"]', 'reglas' => '{}', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_document_company_is_required_signed_and_preserved_for_preview(): void
    {
        $user = new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool { return true; }
        };
        $user->id=7; $user->setRelation('rol', new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        $this->contract(1); $this->contract(2,['empresa_id'=>1081,'empresa_nombre'=>'SAEP EST']);
        config(['services.talana_documents.token'=>'test']);
        Http::fake(['*/documentos/*'=>fn($r)=>str_contains($r->url(),'/500/') ? Http::response(['nombre'=>'010-DERECHO A SABER EST.pdf','adjunto'=>'https://talana.com/test-company.pdf']) : Http::response(['results'=>[['id'=>500,'nombre'=>'010-DERECHO A SABER EST.pdf']],'next'=>null]), 'https://talana.com/test-company.pdf'=>Http::response('%PDF-1.4 test')]);
        $url=route('rrhh-documentos.document-options');
        $this->getJson($url.'?q=DERECHO')->assertUnprocessable();
        Http::assertNothingSent();
        $option=$this->getJson($url.'?q=DERECHO&empresa_id=1081')->assertOk()->json('options.0');
        Http::assertSent(fn($r)=>$r['empresa']===1081);
        $service=app(\App\Services\RrhhDocumentBatteryService::class);
        $details=$service->selections([$option['value']],7);
        $this->assertSame(1081,$details[$option['name']]['empresa_id']);
        $this->post(route('rrhh-documentos.battery'),['nombre'=>'EST correcta','version'=>1,'empresa_id'=>1039,'documentos'=>[$option['value']]])->assertSessionHasErrors('empresa_id');
        $this->post(route('rrhh-documentos.battery'),['nombre'=>'EST correcta','version'=>1,'empresa_id'=>1081,'documentos'=>[$option['value']]])->assertRedirect(route('rrhh-documentos.battery-show',2));
        Http::fake(['*/documentos/500/?*'=>Http::response(['nombre'=>$option['name'],'adjunto'=>'https://talana.com/test-company.pdf']), 'https://talana.com/test-company.pdf'=>Http::response('%PDF-1.4 test')]);
        $this->get(route('rrhh-documentos.battery-pdf',[2,0]))->assertOk();
        Http::assertSent(fn($r)=>str_contains($r->url(),'documentos/500/') && $r['empresa']==1081);
        $this->post(route('rrhh-documentos.battery-validate',[2,0]),['revision'=>1,'confirmed'=>1])->assertSessionDoesntHaveErrors();
        DB::table('rrhh_document_batteries')->where('id',2)->update(['reglas'=>'{"empresa_id":1039}']);
        $composition=app(\App\Services\RrhhDocumentCompositionService::class)->resolve(DB::table('rrhh_document_batteries')->find(2));
        $this->assertNotEmpty($composition['pending']);
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
        $options = $this->getJson(route('rrhh-documentos.document-options',['q'=>'Ejemplo','empresa_id'=>1081]))->assertOk()->json('options');
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
            'reglas'=>'{"empresa_id":1039}', 'estado'=>'BORRADOR','documentos'=>'["Contrato"]','document_details'=>'{"Contrato":{"talana_id":123}}',
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
        Http::fake(['https://talana.com/es/remuneraciones/download-document/test'=>fn()=>Http::response('%PDF-1.4 example'), '*/documentos/123/?*'=>Http::response(['nombre'=>'Contrato','adjunto'=>'https://talana.com/es/remuneraciones/download-document/test'])]);
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
        $this->get($draft)->assertSee('Pendiente de validar');
        $this->post(route('rrhh-documentos.battery-change',2),['revision'=>1,'action'=>'remove','position'=>0])->assertRedirect($draft);
        $this->get($draft)->assertSee('La batería está vacía');
        $this->from($draft)->post(route('rrhh-documentos.battery-activate',2),['revision'=>2])->assertSessionHasErrors('bateria');
        $token=\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['user'=>7,'empresa_id'=>1039,'name'=>'Contrato','id'=>123]));
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

    public function test_filtered_review_and_replacement_preserve_requirement_and_invalidate_approval(): void
    {
        $user = new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool { return true; }
        };
        $user->id=7; $user->setRelation('rol',new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        DB::table('rrhh_document_batteries')->where('id',1)->update(['reglas'=>'{"empresa_id":1039}','estado'=>'BORRADOR','documentos'=>'["Contrato","Anexo","IRL requerido"]','document_details'=>json_encode([
            'Contrato'=>['talana_id'=>123,'validated_at'=>'2026-11-16 12:00:00','validated_by'=>7,'validated_name'=>'QA','source_reference'=>'Contrato EST'],
            'Anexo'=>['talana_id'=>124],
        ])]);
        $url=route('rrhh-documentos.battery-show',1);
        $this->get($url)->assertOk()->assertViewHas('linked',2)->assertViewHas('previous',0)->assertViewHas('next',2)->assertViewHas('selectedPosition',1);
        $this->get($url.'?filtro=sin-vincular&documento=0')->assertOk()->assertViewHas('visibleDocuments',[2=>'IRL requerido'])->assertViewHas('selectedPosition',2);
        $this->get($url.'?filtro=por-revisar')->assertOk()->assertViewHas('visibleDocuments',[1=>'Anexo']);
        $this->get($url.'?filtro=validados')->assertOk()->assertViewHas('visibleDocuments',[0=>'Contrato']);
        $token=fn($name,$id)=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['user'=>7,'empresa_id'=>1039,'name'=>$name,'id'=>$id]));
        $action=route('rrhh-documentos.battery-change',1);
        $this->from($url)->post($action,['revision'=>1,'action'=>'replace','position'=>0,'documentos'=>[$token('Anexo',124)]])->assertSessionHasErrors('documentos');
        $this->from($url)->post($action,['revision'=>1,'action'=>'replace','position'=>0,'documentos'=>[$token('Nuevo',125),$token('Otro',126)]])->assertSessionHasErrors('documentos');
        $this->from($url)->post($action,['revision'=>1,'action'=>'replace','position'=>0,'documentos'=>['invalid']])->assertSessionHasErrors('documentos');
        $this->post($action,['revision'=>1,'action'=>'replace','position'=>0,'documentos'=>[$token('Nuevo',125)]])->assertRedirect($url.'?documento=0');
        $r=DB::table('rrhh_document_batteries')->find(1);$details=json_decode($r->document_details,true);
        $this->assertSame(['Nuevo','Anexo','IRL requerido'],json_decode($r->documentos,true));
        $this->assertSame(['talana_id'=>125,'empresa_id'=>1039,'source_reference'=>'Contrato EST'],$details['Nuevo']);
        $this->assertArrayNotHasKey('Contrato',$details);
        $this->assertSame(1,DB::table('rrhh_document_battery_events')->where('action','replace')->count());
        $this->get($url.'?filtro=validados')->assertOk()->assertSee('No hay documentos con este estado')->assertDontSee('id="document-preview"',false);
        $this->from($url)->post($action,['revision'=>1,'action'=>'replace','position'=>2,'documentos'=>[$token('IRL.pdf',126)]])->assertSessionHasErrors('bateria');
        $this->post($action,['revision'=>2,'action'=>'replace','position'=>2,'documentos'=>[$token('IRL.pdf',126)]])->assertRedirect($url.'?documento=2');
        $this->assertCount(3,json_decode(DB::table('rrhh_document_batteries')->value('documentos'),true));
        config(['services.talana_documents.token'=>'test']);
        Http::fake(['*/documentos/125/?*'=>Http::response(['nombre'=>'Nuevo','adjunto'=>'https://talana.com/es/remuneraciones/download-document/candidate']), 'https://talana.com/es/remuneraciones/download-document/candidate'=>fn()=>Http::response('%PDF-1.4 candidate')]);
        $this->post(route('rrhh-documentos.candidate-pdf'),['selection'=>$token('Nuevo',125)])->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertNull(session('battery_preview.1.0'));
        $this->from($url)->post(route('rrhh-documentos.candidate-pdf'),['selection'=>'invalid'])->assertSessionHasErrors('documentos');
        DB::table('rrhh_document_batteries')->where('id',1)->update(['estado'=>'ACTIVA']);
        $this->from($url)->post($action,['revision'=>3,'action'=>'replace','position'=>0,'documentos'=>[$token('Otro',127)]])->assertSessionHasErrors('bateria');
    }

    public function test_observations_block_validation_until_resolved_and_final_review_is_read_only(): void
    {
        $user = new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public bool $allowEdits=true;
            public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool { return $accion === 'puede_ver' || $this->allowEdits; }
        };
        $user->id=7;$user->name='Revisor QA';$user->setRelation('rol',new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        DB::table('rrhh_document_batteries')->where('id',1)->update(['estado'=>'BORRADOR','documentos'=>'["Contrato"]','document_details'=>json_encode(['Contrato'=>['talana_id'=>123,'validated_at'=>'2026-11-16T12:00:00Z','validated_by'=>7,'validated_name'=>'QA','validated_sha256'=>'old']])]);
        $url=route('rrhh-documentos.battery-show',1);$change=route('rrhh-documentos.battery-change',1);$final=route('rrhh-documentos.battery-final',1);
        $this->get($final)->assertOk()->assertSee('Todos los documentos están vinculados y validados')->assertSee('Esta es la primera versión');
        $this->assertDatabaseHas('rrhh_document_batteries',['id'=>1,'revision'=>1,'estado'=>'BORRADOR']);
        $user->allowEdits=false;
        $this->post($change,['action'=>'observe','revision'=>1,'position'=>0,'reason'=>'Falta revisar vigencia'])->assertForbidden();
        $user->allowEdits=true;
        $this->from($url)->post($change,['action'=>'observe','revision'=>1,'position'=>0,'reason'=>''])->assertSessionHasErrors('reason');
        $this->post($change,['action'=>'observe','revision'=>1,'position'=>0,'reason'=>'Corregir modalidad <EST>'])->assertRedirect($url.'?documento=0');
        $meta=json_decode(DB::table('rrhh_document_batteries')->value('document_details'),true)['Contrato'];
        $this->assertSame('open',$meta['observation']['state']);$this->assertArrayNotHasKey('validated_at',$meta);$this->assertArrayNotHasKey('validated_sha256',$meta);
        $this->get($url.'?filtro=observados')->assertOk()->assertViewHas('visibleDocuments',[0=>'Contrato'])->assertSee('Corregir modalidad &lt;EST&gt;',false)->assertDontSee('id="validate-document"',false);
        $this->get($final)->assertOk()->assertSee('Pendientes que bloquean la activación');
        $this->from($url)->post(route('rrhh-documentos.battery-validate',[1,0]),['revision'=>2,'confirmed'=>1])->assertSessionHasErrors('documento');
        $this->from($url)->post(route('rrhh-documentos.battery-activate',1),['revision'=>2])->assertSessionHasErrors('bateria');
        $this->from($url)->post($change,['action'=>'resolve','revision'=>1,'position'=>0,'reason'=>'Revisado con el area'])->assertSessionHasErrors('bateria');
        $this->post($change,['action'=>'resolve','revision'=>2,'position'=>0,'reason'=>'Modalidad confirmada con el area'])->assertRedirect($url.'?documento=0');
        $this->get($final)->assertOk()->assertViewHas('review',fn($review)=>!$review['ready'] && $review['counts']['por-revisar']===1 && $review['counts']['observados']===0);
        config(['services.talana_documents.token'=>'test']);
        Http::fake(['*/documentos/123/?*'=>Http::response(['nombre'=>'Contrato','adjunto'=>'https://talana.com/es/remuneraciones/download-document/review']), 'https://talana.com/es/remuneraciones/download-document/review'=>fn()=>Http::response('%PDF-1.4 review')]);
        $this->get(route('rrhh-documentos.battery-pdf',[1,0]))->assertOk();
        $this->post(route('rrhh-documentos.battery-validate',[1,0]),['revision'=>3,'confirmed'=>1])->assertRedirect($url);
        $this->get($final)->assertOk()->assertViewHas('review',fn($review)=>$review['ready']);
        $this->post(route('rrhh-documentos.battery-activate',1),['revision'=>4])->assertRedirect($url);
        $this->from($url)->post($change,['action'=>'observe','revision'=>5,'position'=>0,'reason'=>'Una nueva observacion'])->assertSessionHasErrors('bateria');
        $this->assertSame(1,DB::table('rrhh_document_battery_events')->where('action','observe')->count());
        $this->assertSame(1,DB::table('rrhh_document_battery_events')->where('action','resolve')->count());
        DB::table('rrhh_document_batteries')->insert(['id'=>2,'nombre'=>'Prueba','version'=>2,'estado'=>'BORRADOR','documentos'=>'["Nuevo contrato","Adicional"]','document_details'=>json_encode(['Nuevo contrato'=>['talana_id'=>125,'source_reference'=>'Contrato']]),'reglas'=>'{"cargo_nombre":"Supervisor"}','created_by'=>7,'created_at'=>now(),'updated_at'=>now()]);
        $this->get(route('rrhh-documentos.battery-final',2))->assertOk()->assertSee('Reemplazado o vinculado')->assertSee('Agregado')->assertSee('Supervisor');
        DB::table('rrhh_document_batteries')->insert(['id'=>3,'nombre'=>'Otra familia','version'=>1,'documentos'=>'[]','reglas'=>'{}','created_by'=>7,'created_at'=>now(),'updated_at'=>now()]);
        $this->get(route('rrhh-documentos.battery-final',['battery'=>2,'comparar'=>3]))->assertNotFound();
    }

    public function test_version_comparison_detects_replacement_removal_order_and_scope(): void
    {
        $before=(object)['documentos'=>'["A","B","C","D"]','document_details'=>'{"A":{"talana_id":1},"C":{"talana_id":3}}','reglas'=>'{"empresa_id":1081}'];
        $after=(object)['documentos'=>'["C","A nueva","E"]','document_details'=>'{"C":{"talana_id":30},"A nueva":{"talana_id":2,"source_reference":"A"}}','reglas'=>'{"empresa_id":1039}'];
        $result=(new \App\Services\RrhhDocumentBatteryService)->compare($before,$after);
        $this->assertSame(['replaced','replaced','added','removed','removed'],array_column($result['changes'],'type'));
        $this->assertSame('A',$result['changes'][1]['before']);
        $this->assertSame(3,$result['changes'][0]['before_id']);
        $this->assertSame(30,$result['changes'][0]['after_id']);
        $this->assertSame([['field'=>'empresa_id','before'=>1081,'after'=>1039]],$result['rules']);
        $after->documentos='["B","A","C","D"]';$after->document_details=$before->document_details;
        $result=(new \App\Services\RrhhDocumentBatteryService)->compare($before,$after);
        $this->assertSame(['moved','moved'],array_column($result['changes'],'type'));
    }

    public function test_batch_preparation_counts_exclude_removed_workers_and_pdf_preview_is_scoped(): void
    {
        $user=new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public function tieneAcceso(string $moduloSlug,string $accion='puede_ver'): bool { return true; }
        };
        $user->id=7;$user->setRelation('rol',new \App\Models\Rol);$this->actingAs($user)->withoutVite();
        $this->contract(1);$this->contract(2,['persona_rut'=>'98765432-1']);
        $batch=(new RrhhDocumentDraftService)->create($this->data(),7);
        $items=DB::table('rrhh_document_batch_items')->where('batch_id',$batch)->orderBy('id')->get();
        $disk=Storage::fake('local');$disk->put('private/qa.pdf','%PDF-1.4 synthetic preview');
        foreach($items as $item)DB::table('rrhh_document_deliveries')->insert(['item_id'=>$item->id,'position'=>0,'path'=>'private/qa.pdf','category'=>'Contrato','sha256'=>hash('sha256','%PDF-1.4 synthetic preview'),'prepared_by'=>7]);
        $url=route('rrhh-documentos.show',$batch);
        $this->get($url)->assertOk()->assertViewHas('preparation',fn($p)=>$p['expected']===4 && $p['registered']===2 && $p['missing']===2)->assertSee('Previsualizar archivo elegido');
        (new RrhhDocumentReviewService)->update($batch,['accion'=>'retirar','item_id'=>$items[1]->id,'motivo'=>'Fuera del piloto'],7);
        $this->get($url)->assertOk()->assertViewHas('preparation',fn($p)=>$p['expected']===2 && $p['registered']===1 && $p['missing']===1);
        $this->get($url.'?revision=retirados')->assertOk()->assertViewHas('preparation',fn($p)=>$p['registered']===1)->assertDontSee('Guardar PDF de este trabajador');
        $pdf=route('rrhh-documentos.pdf',[$batch,$items[0]->id,0]);
        $response=$this->get($pdf.'?vista=1')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringContainsString('inline',$response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store',$response->headers->get('cache-control'));
        $this->assertStringContainsString('attachment',$this->get($pdf)->assertOk()->headers->get('content-disposition'));
        $this->get(route('rrhh-documentos.pdf',['invalid-batch',$items[0]->id,0,'vista'=>1]))->assertNotFound();
        $this->get(route('rrhh-documentos.pdf',[$batch,$items[0]->id,1,'vista'=>1]))->assertNotFound();
        $this->assertDatabaseHas('rrhh_document_batches',['id'=>$batch,'estado'=>'BORRADOR']);
        Http::assertNothingSent();
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

    private function compositionBattery(string $name, string $kind, array $docs, array $rules = [], array $parents = [], string $state = 'ACTIVA'): int
    {
        $details=[];
        foreach ($docs as $title=>$id) $details[$title]=['talana_id'=>$id,'validated_at'=>now()->toIso8601String(),'validated_by'=>7];
        return DB::table('rrhh_document_batteries')->insertGetId([
            'nombre'=>$name,'kind'=>$kind,'version'=>1,'estado'=>$state,'parent_ids'=>json_encode($parents),
            'documentos'=>json_encode(array_keys($docs)),'document_details'=>json_encode($details),'reglas'=>json_encode($rules),
            'created_by'=>7,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_composition_combines_base_centre_and_job_and_preserves_batch_snapshot(): void
    {
        $base=$this->compositionBattery('Base QA','base',['Obligatorio.pdf'=>901],['empresa_id'=>1039]);
        $centre=$this->compositionBattery('Cliente QA','specific',['Cliente.pdf'=>902],['centro_costo_nombre'=>'CCU'],[$base]);
        $job=$this->compositionBattery('Cargo QA','specific',['Cargo.pdf'=>903,'Obligatorio.pdf'=>901],['cargo_nombre'=>'Operario'],[$base,$centre]);
        $record=DB::table('rrhh_document_batteries')->find($job);
        $service=app(\App\Services\RrhhDocumentCompositionService::class);
        $result=$service->resolve($record);
        $this->assertSame([], $result['errors']);
        $this->assertSame(['Obligatorio.pdf','Cliente.pdf','Cargo.pdf'], $result['documents']);
        $this->assertCount(2, $result['origins']['Obligatorio.pdf']);
        $this->assertSame(['empresa_id'=>1039,'centro_costo_nombre'=>'CCU','cargo_nombre'=>'Operario'], $result['rules']);
        $this->contract(1);
        $id=app(RrhhDocumentDraftService::class)->create(['request_id'=>(string)Str::uuid(),'nombre'=>'Composicion QA','battery_id'=>$job,'seleccion'=>'todos'],7);
        $before=DB::table('rrhh_document_batches')->where('id',$id)->first();
        $snapshot=json_decode($before->battery_snapshot,true);
        $this->assertSame($result['documents'],$snapshot['documentos']);
        $this->assertCount(3,$snapshot['sources']);
        $this->assertSame(3,$before->documentos);
        DB::table('rrhh_document_batteries')->where('id',$base)->update(['estado'=>'ARCHIVADA']);
        $this->compositionBattery('Base QA nueva','base',['Nuevo.pdf'=>904],['empresa_id'=>1039]);
        $this->assertNotEmpty($service->resolve($record)['pending']);
        $this->assertSame($before->battery_snapshot,DB::table('rrhh_document_batches')->where('id',$id)->value('battery_snapshot'));
        try {
            app(RrhhDocumentDraftService::class)->create(['request_id'=>(string)Str::uuid(),'nombre'=>'Bloqueado','battery_id'=>$job,'seleccion'=>'todos'],7);
            $this->fail('No se puede preparar con una base archivada');
        } catch (ValidationException $e) { $this->assertArrayHasKey('composition',$e->errors()); }
        $this->assertSame(1,DB::table('rrhh_document_batches')->count());
    }

    public function test_composition_rejects_cycles_scope_conflicts_and_missing_base(): void
    {
        $service=app(\App\Services\RrhhDocumentCompositionService::class);
        $base=$this->compositionBattery('Base scope','base',['Comun.pdf'=>100],['empresa_id'=>1039]);
        $child=$this->compositionBattery('Child scope','specific',['Extra.pdf'=>101],['empresa_id'=>1081],[$base]);
        $result=$service->resolve(DB::table('rrhh_document_batteries')->find($child));
        $this->assertStringContainsString('incompatibles',implode(' ',$result['errors']));
        DB::table('rrhh_document_batteries')->where('id',$base)->update(['parent_ids'=>json_encode([$child])]);
        $this->assertStringContainsString('ciclo',implode(' ',$service->resolve(DB::table('rrhh_document_batteries')->find($child))['errors']));
        DB::table('rrhh_document_batteries')->where('id',$child)->update(['parent_ids'=>'[]']);
        $this->assertStringContainsString('base obligatoria',implode(' ',$service->resolve(DB::table('rrhh_document_batteries')->find($child))['errors']));
        DB::table('rrhh_document_batteries')->where('id',$base)->update(['parent_ids'=>'[]']);
        DB::table('rrhh_document_batteries')->where('id',$child)->update(['parent_ids'=>json_encode([$base]),'reglas'=>'{}','documentos'=>'["Comun.pdf"]','document_details'=>'{"Comun.pdf":{"talana_id":999}}']);
        $result=$service->resolve(DB::table('rrhh_document_batteries')->find($child));
        $this->assertStringContainsString('distintas con el mismo nombre',implode(' ',$result['errors']));
    }

    public function test_inherited_only_battery_ui_activation_and_edit_protection(): void
    {
        $user=new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public function tieneAcceso(string $moduloSlug,string $accion='puede_ver'): bool { return true; }
        };
        $user->id=7; $user->setRelation('rol',new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        $this->contract(1);
        $base=$this->compositionBattery('Base interfaz','base',['Comun.pdf'=>150],['empresa_id'=>1039]);
        $this->post(route('rrhh-documentos.battery'),['nombre'=>'Hereda sin extras','cargo_nombre'=>'Operario','version'=>1,'kind'=>'specific','parent_ids'=>[$base]])->assertRedirect();
        $child=DB::table('rrhh_document_batteries')->where('nombre','Hereda sin extras')->first();
        $this->assertNotNull($child);
        $this->get(route('rrhh-documentos.battery-show',$child->id))->assertOk()->assertSee('Heredados')->assertSee('Comun.pdf')->assertSee('Ver documento en origen')->assertDontSee('La batería está vacía');
        $this->get(route('rrhh-documentos.battery-final',$child->id))->assertOk()->assertDontSee('La batería está vacía');
        $this->post(route('rrhh-documentos.battery-change',$child->id),['revision'=>1,'action'=>'remove','position'=>0])->assertStatus(422);
        $this->post(route('rrhh-documentos.battery-activate',$child->id),['revision'=>1])->assertRedirect();
        $this->assertDatabaseHas('rrhh_document_batteries',['id'=>$child->id,'estado'=>'ACTIVA']);
        $this->post(route('rrhh-documentos.battery-clone',$child->id))->assertRedirect();
        $new=DB::table('rrhh_document_batteries')->where('nombre',$child->nombre)->where('version',2)->first();
        $this->assertSame($child->parent_ids,$new->parent_ids);
        $this->assertSame('specific',$new->kind);
        $this->post(route('rrhh-documentos.battery-change',$new->id),['revision'=>1,'action'=>'composition','kind'=>'specific','parent_ids'=>[$new->id]])->assertSessionHasErrors('composition');
        Http::assertNothingSent();
    }

    public function test_draft_dependencies_can_be_prepared_but_not_activated_and_rebinding_requires_review(): void
    {
        $user=new class extends \App\Models\User {
            public function tieneConsentimientoDatosVigente(): bool { return true; }
            public function tieneAcceso(string $moduloSlug,string $accion='puede_ver'): bool { return true; }
        };
        $user->id=7; $user->setRelation('rol',new \App\Models\Rol);
        $this->actingAs($user)->withoutVite();
        $base=$this->compositionBattery('Base draft','base',['Comun.pdf'=>180],['empresa_id'=>1039],[],'BORRADOR');
        $child=$this->compositionBattery('Cargo draft','specific',['Extra.pdf'=>181],['cargo_nombre'=>'Operario'],[$base],'BORRADOR');
        $this->post(route('rrhh-documentos.battery-activate',$child),['revision'=>1])->assertSessionHasErrors('composition');
        $this->assertDatabaseHas('rrhh_document_batteries',['id'=>$child,'estado'=>'BORRADOR']);
        $this->post(route('rrhh-documentos.battery-activate',$base),['revision'=>1])->assertRedirect();
        $this->post(route('rrhh-documentos.battery-change',$child),['revision'=>1,'action'=>'composition','kind'=>'specific','parent_ids'=>[$base]])->assertRedirect();
        $record=DB::table('rrhh_document_batteries')->find($child);
        $this->assertArrayNotHasKey('validated_at',json_decode($record->document_details,true)['Extra.pdf']);
        $this->post(route('rrhh-documentos.battery-activate',$child),['revision'=>2])->assertSessionHasErrors('bateria');
        $this->post(route('rrhh-documentos.battery-change',$child),['revision'=>1,'action'=>'composition','kind'=>'specific','parent_ids'=>[$base]])->assertSessionHasErrors('bateria');
        Http::assertNothingSent();
    }
}
