<?php

use App\Http\Controllers\AccidenteSstController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\AuditoriaSstController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\CartaGanttController;
use App\Http\Controllers\CategoriaFormularioController;
use App\Http\Controllers\CentroCostoController;
use App\Http\Controllers\CharlaSstController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentacionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FormularioController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\CharlaTrackingController;
use App\Http\Controllers\KizeoDashboardController;
use App\Http\Controllers\KizeoWebhookController;
use App\Http\Controllers\LeyKarinController;
use App\Http\Controllers\WebhookLogController;
use App\Http\Controllers\NotaPersonalController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProteccionDatosController;
use App\Http\Controllers\RespuestaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitaSstController;
use App\Http\Controllers\LeyKarinPublicoController;
use App\Http\Controllers\StopDashboardController;
use App\Http\Controllers\CampoOpcionController;
use App\Http\Controllers\MisFormulariosController;
use Illuminate\Support\Facades\Route;

// --- WEBHOOK KIZEO (público, sin auth ni CSRF) ---
Route::post('/api/kizeo/webhook', [KizeoWebhookController::class, 'handle'])
    ->name('kizeo.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Auth (con throttle para prevenir fuerza bruta)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Recuperar contraseña
Route::get('/password/forgot', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:3,1');
Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('password.update');

// Política de Privacidad (pública, accesible sin auth)
Route::get('/politica-privacidad', [ProteccionDatosController::class, 'politicaPrivacidad'])
    ->name('proteccion-datos.politica-privacidad');

// --- DENUNCIA LEY KARIN PÚBLICA (sin autenticación SAEP, requiere Google OAuth) ---
Route::prefix('denuncia-ley-karin')->group(function () {
    Route::get('/',                [LeyKarinPublicoController::class, 'inicio'])->name('ley-karin-publico.inicio');
    Route::get('/auth/google',     [LeyKarinPublicoController::class, 'redirectGoogle'])->name('ley-karin-publico.google');
    Route::get('/auth/callback',   [LeyKarinPublicoController::class, 'callbackGoogle'])->name('ley-karin-publico.callback');
    Route::get('/formulario',      [LeyKarinPublicoController::class, 'formulario'])->name('ley-karin-publico.formulario');
    Route::post('/enviar',         [LeyKarinPublicoController::class, 'store'])->name('ley-karin-publico.store');
    Route::get('/confirmacion/{folio}', [LeyKarinPublicoController::class, 'confirmacion'])->name('ley-karin-publico.confirmacion');
    Route::post('/logout',         [LeyKarinPublicoController::class, 'logout'])->name('ley-karin-publico.logout');
});

// App (requiere autenticación)
Route::middleware('auth')->group(function () {

    // --- DESCARGA DE ARCHIVOS ADJUNTOS (privados) ---
    Route::get('/archivos/{archivo}/descargar', function (\App\Models\ArchivoAdjunto $archivo) {
        $path = storage_path('app/private/' . $archivo->ruta);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type' => $archivo->mime_type,
            'Content-Disposition' => 'inline; filename="' . $archivo->nombre_original . '"',
        ]);
    })->name('archivos.descargar');

    // --- PROTECCIÓN DE DATOS (Ley 21.719) ---
    Route::get('/proteccion-datos/consentimiento', fn () => view('proteccion-datos.consentimiento'))
        ->name('proteccion-datos.consentimiento');
    Route::post('/proteccion-datos/aceptar-politica', [ProteccionDatosController::class, 'aceptarPolitica'])
        ->name('proteccion-datos.aceptar-politica');

    // --- MI PERFIL ---
    Route::get('perfil', [ProfileController::class, 'show'])->name('perfil.show');
    Route::put('perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::put('perfil/password', [ProfileController::class, 'updatePassword'])->name('perfil.password');
    Route::post('perfil/foto', [ProfileController::class, 'updatePhoto'])->name('perfil.foto');
    Route::delete('perfil/foto', [ProfileController::class, 'deletePhoto'])->name('perfil.foto.delete');

    // --- NOTIFICACIONES ---
    Route::get('notificaciones', function () {
        return response()->json(auth()->user()->unreadNotifications->take(20));
    })->name('notificaciones.index');
    Route::post('notificaciones/{id}/read', function ($id) {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['ok' => true]);
    })->name('notificaciones.read');
    Route::post('notificaciones/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    })->name('notificaciones.read-all');

    // Rutas protegidas por consentimiento
    Route::middleware(['consentimiento', 'force.password'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // --- MAESTROS (Administración) ---
    Route::middleware('modulo:usuarios')->group(function () {
        Route::post('usuarios/bulk-reset-password', [UserController::class, 'bulkResetPassword'])
            ->name('usuarios.bulkResetPassword');
        Route::post('usuarios/{usuario}/reset-password', [UserController::class, 'resetPassword'])
            ->name('usuarios.resetPassword');
        Route::resource('usuarios', UserController::class)->except(['show']);
    });
    Route::middleware('modulo:departamentos')->group(function () {
        Route::resource('departamentos', DepartamentoController::class)->except(['show']);
    });
    Route::middleware('modulo:cargos')->group(function () {
        Route::resource('cargos', CargoController::class)->except(['show']);
    });
    Route::middleware('modulo:centros_costo')->group(function () {
        Route::resource('centros-costo', CentroCostoController::class)->except(['show']);
    });
    Route::middleware('modulo:categorias_formularios')->group(function () {
        Route::resource('categorias-formularios', CategoriaFormularioController::class)->except(['show']);
    });

    // --- FORMULARIOS Y RESPUESTAS ---
    Route::get('mis-formularios', [MisFormulariosController::class, 'index'])
        ->name('mis-formularios.index');

    Route::middleware('modulo:formularios')->group(function () {
        Route::resource('formularios', FormularioController::class);
        Route::get('formularios/{formulario}/dashboard', [FormularioController::class, 'dashboard'])
            ->name('formularios.dashboard');
        Route::post('formularios/{formulario}/asignar', [FormularioController::class, 'asignar'])
            ->name('formularios.asignar');
        Route::patch('formularios/{formulario}/toggle-activo', [FormularioController::class, 'toggleActivo'])
            ->name('formularios.toggleActivo');
        Route::delete('formularios/{formulario}/desasignar/{user}', [FormularioController::class, 'desasignar'])
            ->name('formularios.desasignar');
    });

    // --- Campo opciones: acceso para cualquier usuario autenticado (campos dinámicos en formularios) ---
    Route::get('formularios/{formulario}/campo-opciones/{campoId}', [CampoOpcionController::class, 'index'])
        ->name('campo-opciones.index');
    Route::post('formularios/{formulario}/campo-opciones/{campoId}', [CampoOpcionController::class, 'store'])
        ->name('campo-opciones.store');

    // --- Respuestas: acceso para cualquier usuario autenticado (completar formularios asignados) ---
    Route::get('respuestas/create', [RespuestaController::class, 'create'])->name('respuestas.create');
    Route::post('respuestas', [RespuestaController::class, 'store'])->name('respuestas.store');
    Route::get('respuestas/{respuesta}', [RespuestaController::class, 'show'])->name('respuestas.show');

    // --- Respuestas: gestión administrativa (accedida desde detalle de formulario) ---
    Route::middleware('modulo:formularios')->group(function () {
        Route::delete('respuestas/bulk-destroy', [RespuestaController::class, 'bulkDestroy'])
            ->name('respuestas.bulkDestroy');
        Route::post('respuestas/bulk-estado', [RespuestaController::class, 'bulkEstado'])
            ->name('respuestas.bulkEstado')
            ->middleware('permission:puede_aprobar');
        Route::get('respuestas/{respuesta}/edit', [RespuestaController::class, 'edit'])->name('respuestas.edit');
        Route::put('respuestas/{respuesta}', [RespuestaController::class, 'update'])->name('respuestas.update');
        Route::delete('respuestas/{respuesta}', [RespuestaController::class, 'destroy'])->name('respuestas.destroy');
        Route::patch('respuestas/{respuesta}/estado', [RespuestaController::class, 'cambiarEstado'])
            ->name('respuestas.estado')
            ->middleware('permission:puede_aprobar');
        Route::get('respuestas-exportar', [RespuestaController::class, 'exportar'])
            ->name('respuestas.exportar');
        Route::get('respuestas-plantilla/{formulario}', [RespuestaController::class, 'plantillaImport'])
            ->name('respuestas.plantillaImport');
        Route::post('respuestas-importar/{formulario}', [RespuestaController::class, 'importar'])
            ->name('respuestas.importar');
    });

    // --- SST: CHARLAS ---
    Route::middleware('modulo:charlas')->group(function () {
        Route::resource('charlas', CharlaSstController::class);
        Route::patch('charlas/{charla}/estado', [CharlaSstController::class, 'cambiarEstado'])
            ->name('charlas.estado');
        Route::get('charlas/{charla}/firmar/{asistente}',  [CharlaSstController::class, 'firmar'])
            ->name('charlas.firmar');
        Route::post('charlas/{charla}/firmar/{asistente}', [CharlaSstController::class, 'guardarFirma'])
            ->name('charlas.guardarFirma');
        Route::get('charlas/{charla}/relator/{relator}/firmar',  [CharlaSstController::class, 'firmarRelator'])
            ->name('charlas.firmarRelator');
        Route::post('charlas/{charla}/relator/{relator}/firmar', [CharlaSstController::class, 'guardarFirmaRelator'])
            ->name('charlas.guardarFirmaRelator');
    });

    // --- SST: CARTA GANTT ---
    Route::middleware('modulo:carta_gantt')->group(function () {
        Route::get('carta-gantt/{cartaGantt}/reporte-pdf', [CartaGanttController::class, 'exportPdf'])
            ->name('carta-gantt.reporte-pdf');
        Route::resource('carta-gantt', CartaGanttController::class);
        // Categorías
        Route::post('carta-gantt/{cartaGantt}/categorias',   [CartaGanttController::class, 'storeCategoria'])
            ->name('carta-gantt.categorias.store');
        Route::delete('carta-gantt/categorias/{categoria}',  [CartaGanttController::class, 'destroyCategoria'])
            ->name('carta-gantt.categorias.destroy');
        // Actividades
        Route::post('carta-gantt/categorias/{categoria}/actividades', [CartaGanttController::class, 'storeActividad'])
            ->name('carta-gantt.actividades.store');
        Route::put('carta-gantt/actividades/{actividad}',    [CartaGanttController::class, 'updateActividad'])
            ->name('carta-gantt.actividades.update');
        Route::delete('carta-gantt/actividades/{actividad}', [CartaGanttController::class, 'destroyActividad'])
            ->name('carta-gantt.actividades.destroy');
        // Seguimiento AJAX
        Route::patch('carta-gantt/actividades/{actividad}/seguimiento', [CartaGanttController::class, 'updateSeguimiento'])
            ->name('carta-gantt.seguimiento.update');
        // Plan de Acción
        Route::post('carta-gantt/actividades/{actividad}/plan-accion', [CartaGanttController::class, 'storePlanAccion'])
            ->name('carta-gantt.plan-accion.store');
        Route::patch('carta-gantt/plan-accion/{plan}',       [CartaGanttController::class, 'updatePlanAccion'])
            ->name('carta-gantt.plan-accion.update');
        Route::delete('carta-gantt/plan-accion/{plan}',      [CartaGanttController::class, 'destroyPlanAccion'])
            ->name('carta-gantt.plan-accion.destroy');
        // Reprogramación de actividades
        Route::post('carta-gantt/actividades/{actividad}/reprogramar', [CartaGanttController::class, 'reprogramarActividad'])
            ->name('carta-gantt.actividades.reprogramar');
        // Importación masiva CSV
        Route::get('carta-gantt/importar/plantilla', [CartaGanttController::class, 'descargarPlantilla'])
            ->name('carta-gantt.plantilla');
        Route::post('carta-gantt/{cartaGantt}/importar', [CartaGanttController::class, 'importarActividades'])
            ->name('carta-gantt.importar');
        // Preview email template
        Route::get('carta-gantt/email-preview/{tipo}', [CartaGanttController::class, 'previewEmail'])
            ->name('carta-gantt.email-preview');
    });

    // --- KIZEO FORMS ANALYTICS ---
    Route::middleware('modulo:kizeo_analytics')->group(function () {
        Route::get('kizeo', [KizeoDashboardController::class, 'index'])->name('kizeo.dashboard');
        Route::get('kizeo/api/dashboard', [KizeoDashboardController::class, 'dashboardData'])->name('kizeo.api.dashboard');
        Route::get('kizeo/api/forms', [KizeoDashboardController::class, 'forms'])->name('kizeo.api.forms');
        Route::get('kizeo/api/deep-all', [KizeoDashboardController::class, 'allDeepData'])->name('kizeo.api.deep.all');
        Route::get('kizeo/api/deep/{formId}', [KizeoDashboardController::class, 'deepData'])->name('kizeo.api.deep');
        Route::get('kizeo/api/media/{formId}/{recordId}/{mediaId}', [KizeoDashboardController::class, 'media'])->name('kizeo.api.media');
        Route::get('kizeo/api/record/{formId}/{recordId}', [KizeoDashboardController::class, 'recordDetail'])->name('kizeo.api.record');
        Route::get('charla-tracking', [CharlaTrackingController::class, 'index'])->name('charla-tracking.index');
        Route::get('charla-tracking/email-preview', [CharlaTrackingController::class, 'emailPreview'])->name('charla-tracking.email-preview');
        Route::post('charla-tracking/sync', [CharlaTrackingController::class, 'sync'])->name('charla-tracking.sync');
        Route::post('charla-tracking/send-report', [CharlaTrackingController::class, 'sendNow'])->name('charla-tracking.send-report');
    });

    // --- TARJETA STOP CCU (Google Drive) ---
    Route::middleware('modulo:kizeo_analytics')->group(function () {
        Route::get('stop-dashboard', [StopDashboardController::class, 'index'])->name('stop-dashboard');
        Route::post('stop-dashboard/sync', [StopDashboardController::class, 'sync'])->name('stop-dashboard.sync');
        Route::get('stop-dashboard/api/data', [StopDashboardController::class, 'apiData'])->name('stop-dashboard.api.data');
        Route::get('stop-dashboard/reporte/preview', [StopDashboardController::class, 'reportePreview'])->name('stop-dashboard.reporte.preview');
        Route::post('stop-dashboard/reporte/test-send', [StopDashboardController::class, 'sendTestReport'])->name('stop-dashboard.reporte.test-send');
    });

    // --- SST: INSPECCIONES ---
    Route::middleware('modulo:visitas_sst')->group(function () {
        Route::resource('visitas-sst', VisitaSstController::class);
    });

    // --- SST: AUDITORÍAS ---
    Route::middleware('modulo:auditorias_sst')->group(function () {
        Route::resource('auditorias-sst', AuditoriaSstController::class);
    });

    // --- SST: ACCIDENTES ---
    Route::middleware('modulo:accidentes_sst')->group(function () {
        Route::resource('accidentes-sst', AccidenteSstController::class);
    });

    // --- SST: LEY KARIN ---
    // Canal de Denuncia (accesible a quienes tengan el módulo denuncia)
    Route::middleware('modulo:ley_karin_denuncia')->group(function () {
        Route::get('ley-karin/denuncia',  [LeyKarinController::class, 'createTrabajador'])->name('ley-karin.denuncia');
        Route::post('ley-karin/denuncia', [LeyKarinController::class, 'storeTrabajador'])->name('ley-karin.denuncia.store');
        Route::get('ley-karin/denuncia/{leyKarin}/confirmacion', [LeyKarinController::class, 'confirmacion'])->name('ley-karin.confirmacion');
    });

    // Admin / Prevencionista: gestión completa
    Route::middleware('modulo:ley_karin')->group(function () {
        Route::resource('ley-karin', LeyKarinController::class);
    });

    // --- CONFIGURACIÓN (solo roles con acceso al módulo) ---
    Route::middleware('modulo:configuracion')->group(function () {
        Route::get('configuraciones',   [ConfiguracionController::class, 'index'])->name('configuraciones.index');
        Route::put('configuraciones',   [ConfiguracionController::class, 'update'])->name('configuraciones.update');
    });

    // --- WEBHOOK LOGS (solo configuracion / superadmin) ---
    Route::middleware('modulo:configuracion')->group(function () {
        Route::get('webhook-logs', [WebhookLogController::class, 'index'])->name('webhook-logs.index');
    });

    // --- PERMISOS POR ROL ---
    Route::middleware('modulo:permisos')->group(function () {
        Route::get('permisos',  [PermisoController::class, 'index'])->name('permisos.index');
        Route::put('permisos',  [PermisoController::class, 'update'])->name('permisos.update');
    });

    // --- IMPORTACIÓN DE DATOS ---
    Route::middleware('modulo:importacion')->group(function () {
        Route::get('importacion',             [ImportController::class, 'index'])->name('importacion.index');
        Route::post('importacion/preview',    [ImportController::class, 'preview'])->name('importacion.preview');
        Route::post('importacion/import',     [ImportController::class, 'import'])->name('importacion.import');
        Route::get('importacion/plantilla/{tipo}', [ImportController::class, 'plantilla'])->name('importacion.plantilla');
    });

    // --- EXPORTACIONES ---
    Route::middleware('modulo:exportaciones')->group(function () {
        Route::get('export/respuestas', [ExportController::class, 'respuestas'])->name('export.respuestas');
        Route::get('export/firmas',     [ExportController::class, 'firmas'])->name('export.firmas');
    });

    // --- PDF ---
    Route::get('pdf/respuesta/{respuesta}', [PdfController::class, 'respuesta'])->name('pdf.respuesta');
    Route::get('pdf/charla/{charla}',       [PdfController::class, 'charla'])->name('pdf.charla');

    // --- PROTECCIÓN DE DATOS: Portal del titular ---
    Route::get('/proteccion-datos', [ProteccionDatosController::class, 'index'])->name('proteccion-datos.index');
    Route::get('/proteccion-datos/solicitud', [ProteccionDatosController::class, 'crearSolicitud'])->name('proteccion-datos.crear-solicitud');
    Route::post('/proteccion-datos/solicitud', [ProteccionDatosController::class, 'guardarSolicitud'])->name('proteccion-datos.guardar-solicitud');
    Route::get('/proteccion-datos/solicitud/{solicitud}', [ProteccionDatosController::class, 'verSolicitud'])->name('proteccion-datos.ver-solicitud');
    Route::get('/proteccion-datos/exportar', [ProteccionDatosController::class, 'exportarDatos'])->name('proteccion-datos.exportar');
    Route::post('/proteccion-datos/revocar', [ProteccionDatosController::class, 'revocarConsentimiento'])->name('proteccion-datos.revocar-consentimiento');

    // --- PROTECCIÓN DE DATOS: Administración ---
    Route::middleware('modulo:proteccion_datos,puede_editar')->group(function () {
        Route::get('/proteccion-datos/administrar', [ProteccionDatosController::class, 'administrar'])->name('proteccion-datos.administrar');
        Route::put('/proteccion-datos/solicitud/{solicitud}/responder', [ProteccionDatosController::class, 'responderSolicitud'])->name('proteccion-datos.responder-solicitud');
        Route::get('/proteccion-datos/registro-tratamiento', [ProteccionDatosController::class, 'registroTratamiento'])->name('proteccion-datos.registro-tratamiento');
    });

    // --- DOCUMENTACIÓN ---
    Route::get('documentacion', [DocumentacionController::class, 'index'])->name('documentacion.index');
    Route::get('documentacion/{modulo}', [DocumentacionController::class, 'show'])->name('documentacion.show');

    // --- NOTAS PERSONALES (dictado por voz) ---
    Route::middleware('modulo:notas_personales')->group(function () {
        Route::get('notas', [NotaPersonalController::class, 'index'])->name('notas.index');
        Route::post('notas', [NotaPersonalController::class, 'store'])->name('notas.store');
        Route::put('notas/{nota}', [NotaPersonalController::class, 'update'])->name('notas.update');
        Route::patch('notas/{nota}/toggle', [NotaPersonalController::class, 'toggleCompletada'])->name('notas.toggle');
        Route::delete('notas/{nota}', [NotaPersonalController::class, 'destroy'])->name('notas.destroy');
    });

    }); // fin middleware consentimiento
});
