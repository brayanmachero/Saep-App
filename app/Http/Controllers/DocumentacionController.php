<?php

namespace App\Http\Controllers;

use App\Modules\Comercial\Models\CentroCosto as ComercialCentroCosto;
use App\Modules\Comercial\Models\Cliente as ComercialCliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\CotizacionAuditoria;
use App\Modules\Comercial\Models\Parametro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'infraestructura' => [
                'titulo'      => 'Infraestructura y DevOps',
                'icono'       => 'bi-cloud-fill',
                'descripcion' => 'Stack técnico, arquitectura Azure, deploy automático, variables de entorno y servicios externos.',
                'estado'      => 'completo',
                'version'     => '2.0',
            ],
            'contratacion' => [
                'titulo'      => 'Contratación RRHH',
                'icono'       => 'bi-person-badge-fill',
                'descripcion' => 'Portal de postulación, panel admin RRHH, generación de ficha PDF consolidada y sincronización con SharePoint.',
                'estado'      => 'completo',
                'version'     => '1.1',
            ],
            'comercial' => [
                'titulo'      => 'Comercial y Cotizador',
                'icono'       => 'bi-calculator-fill',
                'descripcion' => 'Cotizador EST/SUB, mantenedor de parámetros, auditoría, PDF, email y API de tarifas aprobadas.',
                'estado'      => 'completo',
                'version'     => '1.2',
            ],
            'monitor-correos' => [
                'titulo'      => 'Monitor de Correos',
                'icono'       => 'bi-envelope-check-fill',
                'descripcion' => 'Log de correos transaccionales enviados y fallidos. Estadísticas, filtros, preview de HTML y limpieza de registros.',
                'estado'      => 'completo',
                'version'     => '1.0',
            ],
            'kanban' => [
                'titulo'      => 'Tablero Kanban',
                'icono'       => 'bi-kanban-fill',
                'descripcion' => 'Tableros de tareas por centro de costo: columnas, prioridades, asignados, checklist, adjuntos y log de actividad.',
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

        $data = compact('meta', 'modulo', 'modulos');

        if ($modulo === 'comercial') {
            $data['comercialDocs'] = $this->comercialContext();
        }

        return view("documentacion.modulos.{$modulo}", $data);
    }

    private function comercialContext(): array
    {
        $context = [
            'available' => false,
            'error' => null,
            'stats' => [
                'cotizaciones' => 0,
                'clientes' => 0,
                'centros' => 0,
                'parametros' => 0,
                'vigentes' => 0,
                'aprobadas' => 0,
                'precio_vigente' => 0,
            ],
            'estados' => [],
            'categorias_parametros' => [],
            'ultima_actualizacion' => null,
            'ultima_cotizacion' => null,
            'api' => [
                'enabled' => (bool) config('comercial.api.enabled', true),
                'token_configurado' => false,
                'query_token' => (bool) config('comercial.api.allow_query_token', false),
                'default_limit' => (int) config('comercial.api.default_limit', 500),
            ],
            'auditorias' => collect(),
        ];

        try {
            $context['available'] = Schema::hasTable('comercial_cotizaciones');

            if (! $context['available']) {
                return $context;
            }

            $estadoCounts = Cotizacion::query()
                ->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')
                ->pluck('total', 'estado')
                ->map(fn ($total) => (int) $total)
                ->all();

            $context['stats'] = [
                'cotizaciones' => array_sum($estadoCounts),
                'clientes' => Schema::hasTable('comercial_clientes') ? ComercialCliente::query()->count() : 0,
                'centros' => Schema::hasTable('comercial_centros_costo') ? ComercialCentroCosto::query()->count() : 0,
                'parametros' => Schema::hasTable('comercial_parametros') ? Parametro::query()->count() : 0,
                'vigentes' => $estadoCounts['vigente'] ?? 0,
                'aprobadas' => $estadoCounts['aprobada'] ?? 0,
                'precio_vigente' => (float) Cotizacion::query()
                    ->whereIn('estado', ['vigente', 'aprobada'])
                    ->sum('precio_venta'),
            ];

            $context['estados'] = $estadoCounts;

            if (Schema::hasTable('comercial_parametros')) {
                $context['categorias_parametros'] = Parametro::query()
                    ->select('categoria', DB::raw('COUNT(*) as total'))
                    ->groupBy('categoria')
                    ->orderBy('categoria')
                    ->pluck('total', 'categoria')
                    ->map(fn ($total) => (int) $total)
                    ->all();

                $context['ultima_actualizacion'] = Parametro::with('actualizadoPor')
                    ->latest('updated_at')
                    ->first();
            }

            $context['ultima_cotizacion'] = Cotizacion::query()
                ->latest('updated_at')
                ->first();

            $context['api']['token_configurado'] = filled(config('comercial.api.token'))
                || (
                    Schema::hasTable('configuraciones')
                    && DB::table('configuraciones')
                        ->where('clave', 'comercial_api_token')
                        ->whereNotNull('valor')
                        ->where('valor', '<>', '')
                        ->exists()
                );

            if (Schema::hasTable('comercial_cotizacion_auditorias')) {
                $context['auditorias'] = CotizacionAuditoria::with([
                        'usuario:id,name',
                        'cotizacion:id,numero',
                    ])
                    ->latest('created_at')
                    ->limit(6)
                    ->get();
            }
        } catch (\Throwable $e) {
            report($e);
            $context['available'] = false;
            $context['error'] = 'No fue posible leer indicadores dinámicos del módulo Comercial.';
        }

        return $context;
    }
}
