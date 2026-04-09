<?php

namespace App\Http\Controllers;

class DocumentacionController extends Controller
{
    /**
     * Módulos documentados con su metadata.
     */
    private function modulos(): array
    {
        return [
            'charlas' => [
                'titulo'      => 'Charlas SST',
                'icono'       => 'bi-megaphone-fill',
                'descripcion' => 'Gestión de charlas de seguridad, capacitaciones e inducciones con firma electrónica.',
                'estado'      => 'completo',
                'version'     => '1.0',
            ],
            'usuarios' => [
                'titulo'      => 'Gestión de Usuarios',
                'icono'       => 'bi-people-fill',
                'descripcion' => 'Administración de usuarios, roles, importación masiva desde Talana.',
                'estado'      => 'completo',
                'version'     => '1.0',
            ],
            'formularios' => [
                'titulo'      => 'Formularios y Respuestas',
                'icono'       => 'bi-ui-checks-grid',
                'descripcion' => 'Creación de formularios dinámicos, envío de respuestas y aprobaciones.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
            'carta-gantt' => [
                'titulo'      => 'Carta Gantt SST',
                'icono'       => 'bi-bar-chart-steps',
                'descripcion' => 'Planificación anual de actividades de prevención de riesgos.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
            'inspecciones' => [
                'titulo'      => 'Inspecciones SST',
                'icono'       => 'bi-clipboard-check-fill',
                'descripcion' => 'Registro de visitas e inspecciones de seguridad en terreno.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
            'auditorias' => [
                'titulo'      => 'Auditorías SST',
                'icono'       => 'bi-search',
                'descripcion' => 'Gestión de auditorías internas y externas de seguridad.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
            'accidentes' => [
                'titulo'      => 'Accidentes SST',
                'icono'       => 'bi-exclamation-triangle-fill',
                'descripcion' => 'Registro e investigación de accidentes laborales.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
            'ley-karin' => [
                'titulo'      => 'Ley Karin',
                'icono'       => 'bi-shield-exclamation',
                'descripcion' => 'Canal de denuncias por acoso laboral y sexual (Ley 21.643).',
                'estado'      => 'completo',
                'version'     => '1.0',
            ],
            'proteccion-datos' => [
                'titulo'      => 'Protección de Datos',
                'icono'       => 'bi-shield-lock-fill',
                'descripcion' => 'Cumplimiento Ley 21.719, consentimiento, derechos ARCO.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
            'importacion' => [
                'titulo'      => 'Importación de Datos',
                'icono'       => 'bi-cloud-upload-fill',
                'descripcion' => 'Importación masiva de usuarios desde CSV (formato Talana).',
                'estado'      => 'completo',
                'version'     => '1.0',
            ],
            'seguridad' => [
                'titulo'      => 'Seguridad y Perfil de Usuario',
                'icono'       => 'bi-shield-lock-fill',
                'descripcion' => 'Perfil de usuario, foto, contraseñas, notificaciones, soft deletes y políticas de seguridad.',
                'estado'      => 'completo',
                'version'     => '1.0',
            ],
            'configuracion' => [
                'titulo'      => 'Configuración',
                'icono'       => 'bi-gear-fill',
                'descripcion' => 'Parámetros globales de la plataforma.',
                'estado'      => 'pendiente',
                'version'     => null,
            ],
        ];
    }

    public function index()
    {
        $modulos = $this->modulos();
        return view('documentacion.index', compact('modulos'));
    }

    public function show(string $modulo)
    {
        $modulos = $this->modulos();

        if (!isset($modulos[$modulo])) {
            abort(404);
        }

        $meta = $modulos[$modulo];

        if (!view()->exists("documentacion.modulos.{$modulo}")) {
            return view('documentacion.pendiente', compact('meta', 'modulo', 'modulos'));
        }

        return view("documentacion.modulos.{$modulo}", compact('meta', 'modulo', 'modulos'));
    }
}
