<?php

namespace Tests\Feature;

use App\Mail\ContratacionCierreDiarioMail;
use App\Models\Configuracion;
use App\Models\PostulanteContratacion;
use App\Services\OneDriveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ContratacionCierreDiarioTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daily_close_sends_only_selected_day_postulants_to_configured_recipients(): void
    {
        Mail::fake();

        Configuracion::updateOrCreate(
            ['clave' => 'contratacion_cierre_diario_emails'],
            [
                'valor' => 'mmejias@saep.cl, bmachero@saep.cl',
                'tipo' => 'TEXT',
                'categoria' => 'contratacion',
                'descripcion' => 'Destinatarios QA',
                'editable' => true,
            ]
        );

        $today = PostulanteContratacion::create([
            'nombre' => 'Postulante Dia QA',
            'rut' => '12.345.678-5',
            'email' => 'postulante.dia@example.test',
            'carnet_frontal' => 'contratacion/123456785/carnet_frontal.pdf',
            'carnet_reverso' => 'contratacion/123456785/carnet_reverso.pdf',
            'certificado_afp' => 'contratacion/123456785/certificado_afp.pdf',
            'certificado_fonasa' => 'contratacion/123456785/certificado_fonasa.pdf',
        ]);
        $today->forceFill([
            'created_at' => '2026-07-21 09:30:00',
            'updated_at' => '2026-07-21 09:30:00',
        ])->save();

        $yesterday = PostulanteContratacion::create([
            'nombre' => 'Postulante Ayer QA',
            'rut' => '11.111.111-1',
            'email' => 'postulante.ayer@example.test',
        ]);
        $yesterday->forceFill([
            'created_at' => '2026-07-20 09:30:00',
            'updated_at' => '2026-07-20 09:30:00',
        ])->save();

        $oneDrive = Mockery::mock(OneDriveService::class);
        $oneDrive->shouldReceive('isConfigured')->once()->andReturn(true);
        $oneDrive->shouldReceive('getItemWebUrlForSite')
            ->once()
            ->with('RRH', 'Postulantes Documents/' . $today->rut . ' - ' . $today->nombre)
            ->andReturn('https://sharepoint.example.test/postulante-dia');
        $this->app->instance(OneDriveService::class, $oneDrive);

        $exit = Artisan::call('contratacion:cierre-diario', [
            '--date' => '2026-07-21',
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);

        Mail::assertSent(ContratacionCierreDiarioMail::class, 2);
        Mail::assertSent(ContratacionCierreDiarioMail::class, function (ContratacionCierreDiarioMail $mail) use ($today) {
            return $mail->postulantes->count() === 1
                && $mail->postulantes->first()->is($today)
                && $mail->filas[0]['sharepoint_url'] === 'https://sharepoint.example.test/postulante-dia';
        });
    }

    public function test_daily_close_mail_renders_summary_and_sharepoint_button(): void
    {
        $postulante = PostulanteContratacion::make([
            'folio' => 'POST-2026-0001',
            'nombre' => 'Postulante Render QA',
            'rut' => '12.345.678-5',
            'email' => 'render@example.test',
            'estado' => 'pendiente',
            'created_at' => now(),
        ]);

        $mail = new ContratacionCierreDiarioMail(
            now(),
            collect([$postulante]),
            [[
                'folio' => 'POST-2026-0001',
                'nombre' => 'Postulante Render QA',
                'rut' => '12.345.678-5',
                'email' => 'render@example.test',
                'estado' => 'Pendiente',
                'estado_color' => '#f59e0b',
                'hora' => '17:00',
                'documentos_completos' => false,
                'documentos_recibidos' => 0,
                'documentos_faltantes' => 4,
                'faltantes_labels' => 'Carnet frontal, Carnet reverso',
                'sharepoint_path' => 'Postulantes Documents/12.345.678-5 - Postulante Render QA',
                'sharepoint_url' => 'https://sharepoint.example.test/render',
                'panel_url' => 'https://saep.example.test/contratacion/1',
            ]],
            [
                'total' => 1,
                'documentos_completos' => 0,
                'documentos_pendientes' => 1,
                'pendiente' => 1,
                'en_revision' => 0,
                'aprobado' => 0,
                'rechazado' => 0,
            ]
        );

        $html = $mail->render();

        $this->assertStringContainsString('Cierre diario de postulaciones', $html);
        $this->assertStringContainsString('Logo_Saep_email.png', $html);
        $this->assertStringContainsString('Postulante Render QA', $html);
        $this->assertStringContainsString('https://sharepoint.example.test/render', $html);
        $this->assertStringContainsString('SharePoint', $html);
    }
}
