<?php

use App\Http\Controllers\AccidenteSstController;
use App\Http\Controllers\OpcionAccidenteSstController;
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
use App\Http\Controllers\DescargaContenedorController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentacionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FormularioController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\CharlaTrackingController;
use App\Http\Controllers\KizeoDashboardController;
use App\Http\Controllers\KizeoAutomationController;
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
use App\Http\Controllers\ObservacionConductaCcuDashboardController;
use App\Http\Controllers\InspeccionPreventivaPdrDashboardController;
use App\Http\Controllers\EntregaBodegaDashboardController;
use App\Http\Controllers\InventarioBodegaController;
use App\Http\Controllers\GestionVehiculosController;
use App\Http\Controllers\ReservaVehiculoPublicoController;
use App\Http\Controllers\CampoOpcionController;
use App\Http\Controllers\MisFormulariosController;
use App\Http\Controllers\ContratacionController;
use App\Http\Controllers\ContratacionPublicoController;
use App\Http\Controllers\ReclutamientoWhatsappController;
use App\Http\Controllers\ReclutamientoWhatsappWebhookController;
use App\Http\Controllers\GrafanaController;
use Illuminate\Support\Facades\Route;

// --- WEBHOOK KIZEO (público, sin auth ni CSRF) ---
Route::post('/api/kizeo/webhook/{secret?}', [KizeoWebhookController::class, 'handle'])
    ->name('kizeo.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Webhook público de Meta WhatsApp Cloud API. La verificación usa el token
// configurado en Ploi y los POST posteriores validan la firma HMAC de Meta.
Route::get('/api/reclutamiento/whatsapp/webhook', [ReclutamientoWhatsappWebhookController::class, 'verify'])
    ->name('reclutamiento-whatsapp.webhook.verify');
Route::post('/api/reclutamiento/whatsapp/webhook', [ReclutamientoWhatsappWebhookController::class, 'handle'])
    ->name('reclutamiento-whatsapp.webhook.handle')
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
Route::view('/condiciones-servicio', 'proteccion-datos.condiciones-servicio')
    ->name('proteccion-datos.condiciones-servicio');
Route::get('/solicitud-arco', [ProteccionDatosController::class, 'crearSolicitudPublica'])
    ->name('proteccion-datos.publico.crear');
Route::post('/solicitud-arco', [ProteccionDatosController::class, 'guardarSolicitudPublica'])
    ->name('proteccion-datos.publico.guardar')
    ->middleware('throttle:5,1');
Route::get('/solicitud-arco/{numero}/{token}', [ProteccionDatosController::class, 'verSolicitudPublica'])
    ->name('proteccion-datos.publico.ver');

// --- DENUNCIA LEY KARIN PÚBLICA (sin autenticación SAEP, requiere Google OAuth) ---
Route::prefix('denuncia-ley-karin')->group(function () {
    Route::get('/',                [LeyKarinPublicoController::class, 'inicio'])->name('ley-karin-publico.inicio');
    Route::get('/auth/google',     [LeyKarinPublicoController::class, 'redirectGoogle'])->name('ley-karin-publico.google');
    Route::get('/auth/callback',   [LeyKarinPublicoController::class, 'callbackGoogle'])->name('ley-karin-publico.callback');
    Route::get('/formulario',      [LeyKarinPublicoController::class, 'formulario'])->name('ley-karin-publico.formulario');
    Route::post('/enviar',         [LeyKarinPublicoController::class, 'store'])->name('ley-karin-publico.store')->middleware('throttle:5,1');
    Route::get('/confirmacion/{folio}', [LeyKarinPublicoController::class, 'confirmacion'])->name('ley-karin-publico.confirmacion')->middleware('signed');
    Route::post('/logout',         [LeyKarinPublicoController::class, 'logout'])->name('ley-karin-publico.logout');
});

// --- PORTAL PÚBLICO CONTRATACIÓN (sin autenticación SAEP, requiere Google OAuth) ---
// Portal público con margen para postulantes desde redes compartidas.
// El POST final mantiene un límite más estricto que la navegación y pre-subida.
Route::prefix('postulacion')->middleware('throttle:180,1')->group(function () {
    Route::get('/',                [ContratacionPublicoController::class, 'inicio'])->name('contratacion-publico.inicio');
    Route::get('/auth/google',     [ContratacionPublicoController::class, 'redirectGoogle'])->name('contratacion-publico.google');
    Route::get('/auth/callback',   [ContratacionPublicoController::class, 'callbackGoogle'])->name('contratacion-publico.callback');
    Route::get('/formulario',      [ContratacionPublicoController::class, 'formulario'])->name('contratacion-publico.formulario');
    Route::post('/documentos/preupload', [ContratacionPublicoController::class, 'preuploadDocumento'])->middleware('throttle:120,1')->name('contratacion-publico.documento.preupload');
    Route::post('/documentos/descartar', [ContratacionPublicoController::class, 'descartarPreuploadDocumento'])->middleware('throttle:120,1')->name('contratacion-publico.documento.descartar');
    Route::post('/documentos/error', [ContratacionPublicoController::class, 'registrarErrorDocumento'])->middleware('throttle:120,1')->name('contratacion-publico.documento.error');
    Route::post('/enviar',         [ContratacionPublicoController::class, 'store'])->middleware('throttle:20,1')->name('contratacion-publico.store');
    Route::get('/confirmacion/{folio}', [ContratacionPublicoController::class, 'confirmacion'])->name('contratacion-publico.confirmacion')->middleware('signed');
    Route::post('/logout',         [ContratacionPublicoController::class, 'logout'])->name('contratacion-publico.logout');
});

// --- RESERVAS DE VEHICULOS (portal publico con cuenta corporativa Microsoft) ---
Route::prefix('reservas-vehiculos')->middleware('throttle:90,1')->group(function () {
    Route::get('/', [ReservaVehiculoPublicoController::class, 'inicio'])->name('reservas-vehiculos.inicio');
    Route::get('/auth/microsoft', [ReservaVehiculoPublicoController::class, 'redirectMicrosoft'])->name('reservas-vehiculos.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [ReservaVehiculoPublicoController::class, 'callbackMicrosoft'])->name('reservas-vehiculos.microsoft.callback');
    Route::post('/', [ReservaVehiculoPublicoController::class, 'guardar'])->middleware('throttle:12,1')->name('reservas-vehiculos.store');
    Route::post('/{reserva}/cancelar', [ReservaVehiculoPublicoController::class, 'cancelar'])->middleware('throttle:12,1')->name('reservas-vehiculos.cancelar');
    Route::post('/{reserva}/eventualidades', [ReservaVehiculoPublicoController::class, 'reportarEventualidad'])->middleware('throttle:12,1')->name('reservas-vehiculos.eventualidades.store');
    Route::post('/{reserva}/ampliar', [ReservaVehiculoPublicoController::class, 'ampliar'])->middleware('throttle:12,1')->name('reservas-vehiculos.ampliar');
    Route::post('/logout', [ReservaVehiculoPublicoController::class, 'logout'])->name('reservas-vehiculos.logout');
});

// App (requiere autenticación)
Route::middleware('auth')->group(function () {

    // --- DESCARGA DE ARCHIVOS ADJUNTOS (privados) ---
    Route::get('/archivos/{archivo}/descargar', function (\App\Models\ArchivoAdjunto $archivo) {
        if ($archivo->entidad_tipo === 'descarga_contenedor') {
            abort_unless(auth()->user()?->tieneAcceso('descarga_contenedores'), 403);
        }

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
        Route::get('centros-costo/plantilla-csv', [CentroCostoController::class, 'descargarPlantilla'])->name('centros-costo.plantilla');
        Route::post('centros-costo/importar', [CentroCostoController::class, 'importar'])->name('centros-costo.importar');
        Route::resource('centros-costo', CentroCostoController::class)->except(['show']);
    });
    Route::middleware('modulo:categorias_formularios')->group(function () {
        Route::resource('categorias-formularios', CategoriaFormularioController::class)->except(['show']);
    });

    // --- OPERACIONES: DESCARGA DE CONTENEDORES ---
    Route::middleware('modulo:descarga_contenedores')->group(function () {
        Route::get('descarga-contenedores/carga-rapida', [DescargaContenedorController::class, 'cargaRapida'])
            ->name('descarga-contenedores.carga-rapida')
            ->middleware('modulo:descarga_contenedores,puede_crear');
        Route::post('descarga-contenedores/carga-rapida', [DescargaContenedorController::class, 'storeBulk'])
            ->name('descarga-contenedores.store-bulk')
            ->middleware('modulo:descarga_contenedores,puede_crear');
        Route::get('descarga-contenedores/cargas', [DescargaContenedorController::class, 'cargas'])
            ->name('descarga-contenedores.cargas');
        Route::get('descarga-contenedores/dotacion', [DescargaContenedorController::class, 'dotacion'])
            ->name('descarga-contenedores.dotacion')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::post('descarga-contenedores/dotacion/trabajadores', [DescargaContenedorController::class, 'storeTrabajadorOperacion'])
            ->name('descarga-contenedores.dotacion.trabajadores.store')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::patch('descarga-contenedores/dotacion/trabajadores/centro-operativo', [DescargaContenedorController::class, 'updateTrabajadoresOperacionBulk'])
            ->name('descarga-contenedores.dotacion.trabajadores.bulk-update')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::patch('descarga-contenedores/dotacion/trabajadores/{trabajador}', [DescargaContenedorController::class, 'updateTrabajadorOperacion'])
            ->name('descarga-contenedores.dotacion.trabajadores.update')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::get('descarga-contenedores/liquidacion', [DescargaContenedorController::class, 'liquidacion'])
            ->name('descarga-contenedores.liquidacion')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::get('descarga-contenedores/liquidacion/exportar', [DescargaContenedorController::class, 'exportLiquidacion'])
            ->name('descarga-contenedores.liquidacion.exportar')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::get('descarga-contenedores/reportes', [DescargaContenedorController::class, 'reportes'])
            ->name('descarga-contenedores.reportes')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::get('descarga-contenedores/tarifas', [DescargaContenedorController::class, 'tarifas'])
            ->name('descarga-contenedores.tarifas')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::post('descarga-contenedores/tarifas', [DescargaContenedorController::class, 'storeTarifa'])
            ->name('descarga-contenedores.tarifas.store')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::match(['put', 'patch'], 'descarga-contenedores/tarifas/{tarifa}', [DescargaContenedorController::class, 'updateTarifa'])
            ->name('descarga-contenedores.tarifas.update')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::get('descarga-contenedores/evidencias/{archivo}', [DescargaContenedorController::class, 'verEvidencia'])
            ->name('descarga-contenedores.evidencias.ver');
        Route::get('descarga-contenedores', [DescargaContenedorController::class, 'index'])
            ->name('descarga-contenedores.index');
        Route::get('descarga-contenedores/create', [DescargaContenedorController::class, 'create'])
            ->name('descarga-contenedores.create')
            ->middleware('modulo:descarga_contenedores,puede_crear');
        Route::post('descarga-contenedores', [DescargaContenedorController::class, 'store'])
            ->name('descarga-contenedores.store')
            ->middleware('modulo:descarga_contenedores,puede_crear');
        Route::patch('descarga-contenedores/{descarga}/validar', [DescargaContenedorController::class, 'validar'])
            ->name('descarga-contenedores.validar')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::patch('descarga-contenedores/{descarga}/borrador', [DescargaContenedorController::class, 'volverBorrador'])
            ->name('descarga-contenedores.volver-borrador')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::patch('descarga-contenedores/{descarga}/liquidar', [DescargaContenedorController::class, 'liquidar'])
            ->name('descarga-contenedores.liquidar')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::patch('descarga-contenedores/{descarga}/volver-validado', [DescargaContenedorController::class, 'volverValidado'])
            ->name('descarga-contenedores.volver-validado')
            ->middleware('modulo:descarga_contenedores,puede_editar');
        Route::delete('descarga-contenedores/{descarga}/evidencias/{archivo}', [DescargaContenedorController::class, 'destroyEvidencia'])
            ->name('descarga-contenedores.evidencias.destroy');
        Route::get('descarga-contenedores/{descarga}', [DescargaContenedorController::class, 'show'])
            ->name('descarga-contenedores.show');
        Route::get('descarga-contenedores/{descarga}/edit', [DescargaContenedorController::class, 'edit'])
            ->name('descarga-contenedores.edit');
        Route::match(['put', 'patch'], 'descarga-contenedores/{descarga}', [DescargaContenedorController::class, 'update'])
            ->name('descarga-contenedores.update');
        Route::delete('descarga-contenedores/{descarga}', [DescargaContenedorController::class, 'destroy'])
            ->name('descarga-contenedores.destroy')
            ->middleware('modulo:descarga_contenedores,puede_eliminar');
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

        // --- Admin: gestión de opciones de campos dinámicos ---
        Route::patch('campo-opciones/{opcion}', [CampoOpcionController::class, 'update'])
            ->name('campo-opciones.update');
        Route::delete('campo-opciones/{opcion}', [CampoOpcionController::class, 'destroy'])
            ->name('campo-opciones.destroy');
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
        Route::post('respuestas/{respuesta}/reenviar-mail', [RespuestaController::class, 'reenviarMail'])
            ->name('respuestas.reenviarMail');
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
        Route::get('carta-gantt', [CartaGanttController::class, 'index'])
            ->name('carta-gantt.index');
        Route::get('carta-gantt/mis-tareas', [CartaGanttController::class, 'misTareas'])
            ->name('carta-gantt.mis-tareas');
        Route::get('carta-gantt/notificaciones', [CartaGanttController::class, 'notificaciones'])
            ->name('carta-gantt.notificaciones');
        Route::get('carta-gantt/dashboard', [CartaGanttController::class, 'dashboard'])
            ->name('carta-gantt.dashboard');
        Route::get('carta-gantt/create', [CartaGanttController::class, 'create'])
            ->name('carta-gantt.create')
            ->middleware('modulo:carta_gantt,puede_crear');
        Route::post('carta-gantt', [CartaGanttController::class, 'store'])
            ->name('carta-gantt.store')
            ->middleware('modulo:carta_gantt,puede_crear');
        Route::post('carta-gantt/{cartaGantt}/duplicar', [CartaGanttController::class, 'duplicate'])
            ->name('carta-gantt.duplicate')
            ->middleware('modulo:carta_gantt,puede_crear');
        Route::get('carta-gantt/{cartaGantt}', [CartaGanttController::class, 'show'])
            ->name('carta-gantt.show');
        Route::get('carta-gantt/{cartaGantt}/edit', [CartaGanttController::class, 'edit'])
            ->name('carta-gantt.edit')
            ->middleware('modulo:carta_gantt,puede_editar');
        Route::match(['put', 'patch'], 'carta-gantt/{cartaGantt}', [CartaGanttController::class, 'update'])
            ->name('carta-gantt.update')
            ->middleware('modulo:carta_gantt,puede_editar');
        Route::delete('carta-gantt/{cartaGantt}', [CartaGanttController::class, 'destroy'])
            ->name('carta-gantt.destroy')
            ->middleware('modulo:carta_gantt,puede_eliminar');
        // Categorías
        Route::post('carta-gantt/{cartaGantt}/categorias',   [CartaGanttController::class, 'storeCategoria'])
            ->name('carta-gantt.categorias.store')
            ->middleware('modulo:carta_gantt,puede_crear');
        Route::delete('carta-gantt/categorias/{categoria}',  [CartaGanttController::class, 'destroyCategoria'])
            ->name('carta-gantt.categorias.destroy')
            ->middleware('modulo:carta_gantt,puede_eliminar');
        // Actividades
        Route::post('carta-gantt/categorias/{categoria}/actividades', [CartaGanttController::class, 'storeActividad'])
            ->name('carta-gantt.actividades.store')
            ->middleware('modulo:carta_gantt,puede_crear');
        Route::put('carta-gantt/actividades/{actividad}',    [CartaGanttController::class, 'updateActividad'])
            ->name('carta-gantt.actividades.update');
        Route::delete('carta-gantt/actividades/{actividad}', [CartaGanttController::class, 'destroyActividad'])
            ->name('carta-gantt.actividades.destroy')
            ->middleware('modulo:carta_gantt,puede_eliminar');
        // Seguimiento AJAX
        Route::patch('carta-gantt/actividades/{actividad}/seguimiento', [CartaGanttController::class, 'updateSeguimiento'])
            ->name('carta-gantt.seguimiento.update');
        // Plan de Acción
        Route::post('carta-gantt/actividades/{actividad}/plan-accion', [CartaGanttController::class, 'storePlanAccion'])
            ->name('carta-gantt.plan-accion.store');
        Route::patch('carta-gantt/plan-accion/{plan}',       [CartaGanttController::class, 'updatePlanAccion'])
            ->name('carta-gantt.plan-accion.update');
        Route::delete('carta-gantt/plan-accion/{plan}',      [CartaGanttController::class, 'destroyPlanAccion'])
            ->name('carta-gantt.plan-accion.destroy')
            ->middleware('modulo:carta_gantt,puede_eliminar');
        // Comentarios operativos
        Route::post('carta-gantt/actividades/{actividad}/comentarios', [CartaGanttController::class, 'storeComentario'])
            ->name('carta-gantt.comentarios.store');
        Route::delete('carta-gantt/comentarios/{comentario}', [CartaGanttController::class, 'destroyComentario'])
            ->name('carta-gantt.comentarios.destroy');
        // Reprogramación de actividades
        Route::post('carta-gantt/actividades/{actividad}/reprogramar', [CartaGanttController::class, 'reprogramarActividad'])
            ->name('carta-gantt.actividades.reprogramar');
        // Importación masiva CSV
        Route::get('carta-gantt/importar/plantilla', [CartaGanttController::class, 'descargarPlantilla'])
            ->name('carta-gantt.plantilla');
        Route::post('carta-gantt/{cartaGantt}/importar', [CartaGanttController::class, 'importarActividades'])
            ->name('carta-gantt.importar')
            ->middleware('modulo:carta_gantt,puede_crear');
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
    Route::middleware('modulo:stop_dashboard')->group(function () {
        Route::get('stop-dashboard', [StopDashboardController::class, 'index'])->name('stop-dashboard');
        Route::post('stop-dashboard/sync', [StopDashboardController::class, 'sync'])->name('stop-dashboard.sync');
        Route::get('stop-dashboard/api/data', [StopDashboardController::class, 'apiData'])->name('stop-dashboard.api.data');
        Route::get('stop-dashboard/reporte/preview', [StopDashboardController::class, 'reportePreview'])->name('stop-dashboard.reporte.preview');
        Route::get('stop-dashboard/reporte/excel', [StopDashboardController::class, 'downloadExcelReport'])->name('stop-dashboard.reporte.excel');
        Route::post('stop-dashboard/reporte/test-send', [StopDashboardController::class, 'sendTestReport'])->name('stop-dashboard.reporte.test-send');
        Route::post('stop-dashboard/reporte/send-now', [StopDashboardController::class, 'sendReportNow'])->name('stop-dashboard.reporte.send-now');
    });

    // --- OBSERVACIONES DE CONDUCTA CCU (Kizeo) ---
    Route::middleware('modulo:pdr_ccu_dashboard')->group(function () {
        Route::get('observaciones-ccu', [ObservacionConductaCcuDashboardController::class, 'index'])
            ->name('pdr-ccu-dashboard.index');
        Route::get('observaciones-ccu/reporte/excel', [ObservacionConductaCcuDashboardController::class, 'downloadExcel'])
            ->name('pdr-ccu-dashboard.excel');
        Route::get('observaciones-ccu/reporte/preview', [ObservacionConductaCcuDashboardController::class, 'emailPreview'])
            ->name('pdr-ccu-dashboard.email-preview');
        Route::post('observaciones-ccu/reporte/enviar-mi-correo', [ObservacionConductaCcuDashboardController::class, 'sendToCurrentUser'])
            ->name('pdr-ccu-dashboard.email-self');
        Route::post('observaciones-ccu/sync', [ObservacionConductaCcuDashboardController::class, 'sync'])
            ->middleware('modulo:pdr_ccu_dashboard,puede_editar')
            ->name('pdr-ccu-dashboard.sync');
    });

    // --- PDR: INSPECCIONES PREVENTIVAS (Kizeo) ---
    Route::middleware('modulo:pdr_inspecciones_dashboard')->group(function () {
        Route::get('inspecciones-preventivas', [InspeccionPreventivaPdrDashboardController::class, 'index'])
            ->name('pdr-inspecciones-dashboard.index');
        Route::get('inspecciones-preventivas/reporte/excel', [InspeccionPreventivaPdrDashboardController::class, 'downloadExcel'])
            ->name('pdr-inspecciones-dashboard.excel');
        Route::get('inspecciones-preventivas/reporte/preview', [InspeccionPreventivaPdrDashboardController::class, 'emailPreview'])
            ->name('pdr-inspecciones-dashboard.email-preview');
        Route::post('inspecciones-preventivas/reporte/enviar-mi-correo', [InspeccionPreventivaPdrDashboardController::class, 'sendToCurrentUser'])
            ->name('pdr-inspecciones-dashboard.email-self');
        Route::post('inspecciones-preventivas/sync', [InspeccionPreventivaPdrDashboardController::class, 'sync'])
            ->middleware('modulo:pdr_inspecciones_dashboard,puede_editar')
            ->name('pdr-inspecciones-dashboard.sync');
    });

    // --- BODEGA: ENTREGAS DE EPP (Kizeo) ---
      Route::middleware('modulo:entregas_bodega_dashboard')->group(function () {
        Route::get('entregas-bodega', [EntregaBodegaDashboardController::class, 'index'])
            ->name('entregas-bodega-dashboard.index');
        Route::get('entregas-bodega/exportar', [EntregaBodegaDashboardController::class, 'downloadExcel'])
            ->name('entregas-bodega-dashboard.export');
        Route::get('entregas-bodega/{entrega}/documento', [EntregaBodegaDashboardController::class, 'viewDocument'])
            ->name('entregas-bodega-dashboard.document');
        Route::post('entregas-bodega/sync', [EntregaBodegaDashboardController::class, 'sync'])
            ->middleware('modulo:entregas_bodega_dashboard,puede_editar')
            ->name('entregas-bodega-dashboard.sync');
      });

      // --- BODEGA: INVENTARIO Y CONTEOS FISICOS ---
      Route::middleware('modulo:inventario_bodega')->prefix('inventario-bodega')->name('inventario-bodega.')->group(function () {
          Route::get('/', [InventarioBodegaController::class, 'index'])->name('index');
          Route::get('exportar', [InventarioBodegaController::class, 'exportBalances'])->name('export');
          Route::get('plantilla-productos', [InventarioBodegaController::class, 'productTemplate'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('productos.plantilla');
          Route::get('plantilla-ingresos', [InventarioBodegaController::class, 'receiptTemplate'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('ingresos.plantilla');
          Route::post('productos/importar', [InventarioBodegaController::class, 'importProducts'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('productos.importar');
          Route::post('ingresos/importar', [InventarioBodegaController::class, 'importReceipts'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('ingresos.importar');
          Route::post('maestros-operativos/importar', [InventarioBodegaController::class, 'importOperationalMasters'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('maestros.importar');
          Route::post('maestros-operativos/coordinadores', [InventarioBodegaController::class, 'storeOperationalCoordinator'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('maestros.coordinadores.store');
          Route::put('maestros-operativos/coordinadores/{coordinador}', [InventarioBodegaController::class, 'updateOperationalCoordinator'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('maestros.coordinadores.update');
          Route::post('maestros-operativos/centros-costo', [InventarioBodegaController::class, 'storeOperationalCostCenter'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('maestros.centros.store');
          Route::put('maestros-operativos/centros-costo/{centroCosto}', [InventarioBodegaController::class, 'updateOperationalCostCenter'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('maestros.centros.update');
          Route::post('ubicaciones', [InventarioBodegaController::class, 'storeLocation'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('ubicaciones.store');
          Route::put('ubicaciones/{ubicacion}', [InventarioBodegaController::class, 'updateLocation'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('ubicaciones.update');
          Route::post('proveedores', [InventarioBodegaController::class, 'storeProvider'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('proveedores.store');
          Route::put('proveedores/{proveedor}', [InventarioBodegaController::class, 'updateProvider'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('proveedores.update');
          Route::post('productos', [InventarioBodegaController::class, 'storeProduct'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('productos.store');
          Route::put('productos/{producto}', [InventarioBodegaController::class, 'updateProduct'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('productos.update');
          Route::post('stock-talla', [InventarioBodegaController::class, 'setVariantStock'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('stock-talla.store');
          Route::post('ingresos', [InventarioBodegaController::class, 'storeReceipt'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('ingresos.store');
          Route::post('ingresos/{ingreso}/revertir', [InventarioBodegaController::class, 'reverseReceipt'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('ingresos.revertir');
          Route::post('movimientos', [InventarioBodegaController::class, 'storeMovement'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('movimientos.store');
          Route::post('movimientos/{movimiento}/revertir', [InventarioBodegaController::class, 'reverseMovement'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('movimientos.revertir');
          Route::post('conteos', [InventarioBodegaController::class, 'storeStocktake'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('conteos.store');
          Route::get('conteos/{conteo}', [InventarioBodegaController::class, 'showStocktake'])->name('conteos.show');
          Route::put('conteos/{conteo}', [InventarioBodegaController::class, 'updateStocktake'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('conteos.update');
          Route::post('conteos/{conteo}/aprobar', [InventarioBodegaController::class, 'approveStocktake'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('conteos.aprobar');
          Route::post('entregas-kizeo/{entrega}/aplicar', [InventarioBodegaController::class, 'applyKizeoDelivery'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('entregas-kizeo.aplicar');
          Route::post('entregas-kizeo/aplicar-masivo', [InventarioBodegaController::class, 'applyKizeoDeliveriesBatch'])
              ->middleware('modulo:inventario_bodega,puede_crear')
              ->name('entregas-kizeo.aplicar-masivo');
          Route::post('entregas-kizeo/{aplicacion}/revertir', [InventarioBodegaController::class, 'reverseKizeoDelivery'])
              ->middleware('modulo:inventario_bodega,puede_editar')
              ->name('entregas-kizeo.revertir');
      });

      // --- BODEGA: FLOTA Y RESERVAS DE VEHICULOS ---
      Route::middleware('modulo:gestion_vehiculos')->group(function () {
          Route::get('gestion-vehiculos', [GestionVehiculosController::class, 'index'])
              ->name('gestion-vehiculos.index');
          Route::post('gestion-vehiculos', [GestionVehiculosController::class, 'store'])
              ->middleware('modulo:gestion_vehiculos,puede_crear')
              ->name('gestion-vehiculos.store');
          Route::put('gestion-vehiculos/{vehiculo}', [GestionVehiculosController::class, 'update'])
              ->middleware('modulo:gestion_vehiculos,puede_editar')
              ->name('gestion-vehiculos.update');
          Route::patch('gestion-vehiculos/reservas/{reserva}', [GestionVehiculosController::class, 'actualizarReserva'])
              ->middleware('modulo:gestion_vehiculos,puede_editar')
              ->name('gestion-vehiculos.reservas.update');
          Route::post('gestion-vehiculos/reservas', [GestionVehiculosController::class, 'storeReservaBodega'])
              ->middleware('modulo:gestion_vehiculos,puede_crear')
              ->name('gestion-vehiculos.reservas.store');
          Route::put('gestion-vehiculos/reservas/{reserva}/programacion', [GestionVehiculosController::class, 'reprogramarReserva'])
              ->middleware('modulo:gestion_vehiculos,puede_editar')
              ->name('gestion-vehiculos.reservas.programacion.update');
          Route::post('gestion-vehiculos/reservas/{reserva}/kizeo', [GestionVehiculosController::class, 'prepararActaKizeo'])
              ->middleware('modulo:gestion_vehiculos,puede_editar')
              ->name('gestion-vehiculos.reservas.kizeo');
          Route::get('gestion-vehiculos/reservas/{reserva}/actas/{tipo}', [GestionVehiculosController::class, 'verActa'])
              ->name('gestion-vehiculos.reservas.acta');
          Route::delete('gestion-vehiculos/reservas/{reserva}', [GestionVehiculosController::class, 'eliminarReserva'])
              ->middleware('modulo:gestion_vehiculos,puede_eliminar')
              ->name('gestion-vehiculos.reservas.destroy');
          Route::post('gestion-vehiculos/solicitantes', [GestionVehiculosController::class, 'storeSolicitante'])
              ->middleware('modulo:gestion_vehiculos,puede_crear')
              ->name('gestion-vehiculos.solicitantes.store');
          Route::patch('gestion-vehiculos/solicitantes/{solicitante}', [GestionVehiculosController::class, 'updateSolicitante'])
              ->middleware('modulo:gestion_vehiculos,puede_editar')
              ->name('gestion-vehiculos.solicitantes.update');
          Route::post('gestion-vehiculos/motivos', [GestionVehiculosController::class, 'storeMotivo'])
              ->middleware('modulo:gestion_vehiculos,puede_crear')
              ->name('gestion-vehiculos.motivos.store');
          Route::patch('gestion-vehiculos/motivos/{motivo}', [GestionVehiculosController::class, 'updateMotivo'])
              ->middleware('modulo:gestion_vehiculos,puede_editar')
              ->name('gestion-vehiculos.motivos.update');
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
        // Catálogo de opciones (lesiones, causas, medidas) — antes del resource para evitar conflicto
        Route::get('accidentes-sst-opciones', [OpcionAccidenteSstController::class, 'index'])
            ->name('accidentes-sst.opciones');
        Route::post('accidentes-sst-opciones', [OpcionAccidenteSstController::class, 'store'])
            ->name('accidentes-sst.opciones.store');
        Route::get('accidentes-sst/api/opciones/{tipo}', [OpcionAccidenteSstController::class, 'api'])
            ->name('accidentes-sst.opciones.api');
        Route::patch('accidentes-sst/opciones/{opcion}', [OpcionAccidenteSstController::class, 'update'])
            ->name('accidentes-sst.opciones.update');
        Route::patch('accidentes-sst/opciones/{opcion}/toggle', [OpcionAccidenteSstController::class, 'toggleActivo'])
            ->name('accidentes-sst.opciones.toggle');
        Route::delete('accidentes-sst/opciones/{opcion}', [OpcionAccidenteSstController::class, 'destroy'])
            ->name('accidentes-sst.opciones.destroy');

        Route::resource('accidentes-sst', AccidenteSstController::class);
        Route::patch('accidentes-sst/{accidentesSst}/accion-rapida', [AccidenteSstController::class, 'accionRapida'])
            ->name('accidentes-sst.accion-rapida');
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
        Route::get('ley-karin/dashboard', [LeyKarinController::class, 'dashboard'])->name('ley-karin.dashboard');
        Route::resource('ley-karin', LeyKarinController::class);
    });

    // --- CONFIGURACIÓN (solo roles con acceso al módulo) ---
    Route::middleware('modulo:configuracion')->group(function () {
        Route::get('configuraciones',   [ConfiguracionController::class, 'index'])->name('configuraciones.index');
        Route::put('configuraciones',   [ConfiguracionController::class, 'update'])->name('configuraciones.update');
        Route::get('kizeo-automations/lookup-form', [KizeoAutomationController::class, 'lookupForm'])
            ->name('kizeo-automations.lookup-form');
        Route::post('kizeo-automations/runs/{run}/retry', [KizeoAutomationController::class, 'retryRun'])
            ->name('kizeo-automations.runs.retry');
        Route::patch('kizeo-automations/{kizeoAutomation}/toggle', [KizeoAutomationController::class, 'toggle'])
            ->name('kizeo-automations.toggle');
        Route::resource('kizeo-automations', KizeoAutomationController::class)->except(['show']);
    });

    // --- WEBHOOK LOGS (solo configuracion / superadmin) ---
    Route::middleware('modulo:configuracion')->group(function () {
        Route::get('webhook-logs', [WebhookLogController::class, 'index'])->name('webhook-logs.index');
    });

    // --- PERMISOS POR ROL ---
    Route::middleware('modulo:permisos')->group(function () {
        Route::get('permisos',  [PermisoController::class, 'index'])->name('permisos.index');
        Route::put('permisos',  [PermisoController::class, 'update'])->name('permisos.update');
        Route::post('roles',    [PermisoController::class, 'storeRol'])->name('roles.store');
        Route::put('roles/{rol}',  [PermisoController::class, 'updateRol'])->name('roles.update');
        Route::delete('roles/{rol}', [PermisoController::class, 'destroyRol'])->name('roles.destroy');
        Route::post('modulos',           [PermisoController::class, 'storeModulo'])->name('modulos.store');
        Route::put('modulos/{modulo}',   [PermisoController::class, 'updateModulo'])->name('modulos.update');
        Route::delete('modulos/{modulo}',[PermisoController::class, 'destroyModulo'])->name('modulos.destroy');
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
        Route::post('/proteccion-datos/solicitud/{solicitud}/ejecutar-supresion', [ProteccionDatosController::class, 'ejecutarSupresion'])->name('proteccion-datos.ejecutar-supresion');
        Route::get('/proteccion-datos/registro-tratamiento', [ProteccionDatosController::class, 'registroTratamiento'])->name('proteccion-datos.registro-tratamiento');
        Route::get('/proteccion-datos/matriz-retencion', [ProteccionDatosController::class, 'matrizRetencion'])->name('proteccion-datos.matriz-retencion');
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

    // --- TABLERO KANBAN ---
    Route::middleware('modulo:kanban')->group(function () {
        // Mis Tareas (antes del resource para evitar conflicto con {kanban})
        Route::get('kanban-mis-tareas', [\App\Http\Controllers\KanbanController::class, 'misTareas'])->name('kanban.mis-tareas');
        // Dashboard / Analytics
        Route::get('kanban-dashboard', [\App\Http\Controllers\KanbanController::class, 'dashboard'])->name('kanban.dashboard');
        // Búsqueda global
        Route::get('kanban-buscar', [\App\Http\Controllers\KanbanController::class, 'buscar'])->name('kanban.buscar');
        // Plantilla
        Route::post('kanban-plantilla', [\App\Http\Controllers\KanbanController::class, 'crearDesdePlantilla'])->name('kanban.plantilla');

        Route::resource('kanban', \App\Http\Controllers\KanbanController::class);
        // Duplicar tablero
        Route::post('kanban/{kanban}/duplicar', [\App\Http\Controllers\KanbanController::class, 'duplicar'])->name('kanban.duplicar');
        // Eliminar definitivamente
        Route::delete('kanban/{kanban}/force-destroy', [\App\Http\Controllers\KanbanController::class, 'forceDestroy'])->name('kanban.force-destroy');
        // Exportar PDF
        Route::get('kanban/{kanban}/exportar-pdf', [\App\Http\Controllers\KanbanController::class, 'exportarPdf'])->name('kanban.exportar-pdf');
        // Tareas archivadas
        Route::get('kanban/{kanban}/archivadas', [\App\Http\Controllers\KanbanController::class, 'tareasArchivadas'])->name('kanban.archivadas');
        // Columnas
        Route::post('kanban/{kanban}/columnas', [\App\Http\Controllers\KanbanController::class, 'storeColumna'])->name('kanban.columnas.store');
        Route::put('kanban/columnas/{columna}', [\App\Http\Controllers\KanbanController::class, 'updateColumna'])->name('kanban.columnas.update');
        Route::patch('kanban/columnas/{columna}/toggle-completada', [\App\Http\Controllers\KanbanController::class, 'toggleCompletadaColumna'])->name('kanban.columnas.toggle-completada');
        Route::delete('kanban/columnas/{columna}', [\App\Http\Controllers\KanbanController::class, 'destroyColumna'])->name('kanban.columnas.destroy');
        // Tareas
        Route::get('kanban/tareas/{tarea}', [\App\Http\Controllers\KanbanController::class, 'showTarea'])->name('kanban.tareas.show');
        Route::post('kanban/{kanban}/tareas', [\App\Http\Controllers\KanbanController::class, 'storeTarea'])->name('kanban.tareas.store');
        Route::put('kanban/tareas/{tarea}', [\App\Http\Controllers\KanbanController::class, 'updateTarea'])->name('kanban.tareas.update');
        Route::delete('kanban/tareas/{tarea}', [\App\Http\Controllers\KanbanController::class, 'destroyTarea'])->name('kanban.tareas.destroy');
        Route::patch('kanban/tareas/{tarea}/mover', [\App\Http\Controllers\KanbanController::class, 'moverTarea'])->name('kanban.tareas.mover');
        Route::patch('kanban/tareas/{tarea}/archivar', [\App\Http\Controllers\KanbanController::class, 'archivarTarea'])->name('kanban.tareas.archivar');
        Route::patch('kanban/tareas/{tarea}/desarchivar', [\App\Http\Controllers\KanbanController::class, 'desarchivarTarea'])->name('kanban.tareas.desarchivar');
        // Comentarios
        Route::post('kanban/tareas/{tarea}/comentarios', [\App\Http\Controllers\KanbanController::class, 'storeComentario'])->name('kanban.comentarios.store');
        // Checklist
        Route::post('kanban/tareas/{tarea}/checklist', [\App\Http\Controllers\KanbanController::class, 'storeChecklistItem'])->name('kanban.checklist.store');
        Route::patch('kanban/checklist/{item}/toggle', [\App\Http\Controllers\KanbanController::class, 'toggleChecklistItem'])->name('kanban.checklist.toggle');
        Route::delete('kanban/checklist/{item}', [\App\Http\Controllers\KanbanController::class, 'destroyChecklistItem'])->name('kanban.checklist.destroy');
        // Adjuntos
        Route::post('kanban/tareas/{tarea}/adjuntos', [\App\Http\Controllers\KanbanController::class, 'storeAdjunto'])->name('kanban.adjuntos.store');
        Route::delete('kanban/adjuntos/{adjunto}', [\App\Http\Controllers\KanbanController::class, 'destroyAdjunto'])->name('kanban.adjuntos.destroy');
        Route::get('kanban/adjuntos/{adjunto}/descargar', [\App\Http\Controllers\KanbanController::class, 'descargarAdjunto'])->name('kanban.adjuntos.descargar');
        // Etiquetas
        Route::post('kanban/{kanban}/etiquetas', [\App\Http\Controllers\KanbanController::class, 'storeEtiqueta'])->name('kanban.etiquetas.store');
        Route::delete('kanban/etiquetas/{etiqueta}', [\App\Http\Controllers\KanbanController::class, 'destroyEtiqueta'])->name('kanban.etiquetas.destroy');
        // Miembros
        Route::post('kanban/{kanban}/miembros', [\App\Http\Controllers\KanbanController::class, 'storeMiembro'])->name('kanban.miembros.store');
        Route::delete('kanban/{kanban}/miembros/{user}', [\App\Http\Controllers\KanbanController::class, 'destroyMiembro'])->name('kanban.miembros.destroy');
        // Actividad
        Route::get('kanban/{kanban}/actividad', [\App\Http\Controllers\KanbanController::class, 'actividad'])->name('kanban.actividad');
        // Calendario API
        Route::get('kanban/{kanban}/calendar-data', [\App\Http\Controllers\KanbanController::class, 'calendarData'])->name('kanban.calendar-data');
    });

    // --- RECLUTAMIENTO WHATSAPP (RRHH) ---
    Route::middleware('modulo:reclutamiento_whatsapp')->prefix('reclutamiento/whatsapp')->name('reclutamiento-whatsapp.')->group(function () {
        Route::get('/', [ReclutamientoWhatsappController::class, 'index'])->name('index');
        Route::post('/contactos', [ReclutamientoWhatsappController::class, 'storeContacto'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_crear')->name('contactos.store');
        Route::post('/contactos/importar', [ReclutamientoWhatsappController::class, 'importarContactos'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_crear')->name('contactos.importar');
        Route::patch('/contactos/{contacto}/revocar', [ReclutamientoWhatsappController::class, 'revocarContacto'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_editar')->name('contactos.revocar');
        Route::post('/campanias', [ReclutamientoWhatsappController::class, 'storeCampania'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_crear')->name('campanias.store');
        Route::patch('/campanias/{campania}/aprobar', [ReclutamientoWhatsappController::class, 'aprobarCampania'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_editar')->name('campanias.aprobar');
        Route::patch('/campanias/{campania}/programar', [ReclutamientoWhatsappController::class, 'programarCampania'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_editar')->name('campanias.programar');
        Route::post('/plantillas/sincronizar', [ReclutamientoWhatsappController::class, 'sincronizarPlantillas'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_editar')->name('plantillas.sincronizar');
        Route::get('/bandeja', [ReclutamientoWhatsappController::class, 'bandeja'])->name('bandeja');
        Route::patch('/conversaciones/{conversacion}/asignar', [ReclutamientoWhatsappController::class, 'asignarConversacion'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_editar')->name('conversaciones.asignar');
        Route::patch('/conversaciones/{conversacion}/estado', [ReclutamientoWhatsappController::class, 'actualizarEstadoConversacion'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_crear')->name('conversaciones.estado');
        Route::post('/conversaciones/{conversacion}/responder', [ReclutamientoWhatsappController::class, 'responderConversacion'])
            ->middleware('modulo:reclutamiento_whatsapp,puede_crear')->name('conversaciones.responder');
    });

    // --- CONTRATACIÓN RRHH (panel admin) ---
    Route::middleware('modulo:contratacion')->prefix('contratacion')->name('contratacion.')->group(function () {
        Route::get('/',                              [ContratacionController::class, 'index'])->name('index');
        Route::get('/crear',                         [ContratacionController::class, 'create'])->middleware('modulo:contratacion,puede_crear')->name('crear');
        Route::post('/crear',                        [ContratacionController::class, 'storeManual'])->middleware('modulo:contratacion,puede_crear')->name('store-manual');
        Route::get('/exportar/excel',                [ContratacionController::class, 'exportarExcel'])->middleware('modulo:contratacion,puede_editar')->name('exportar.excel');
        Route::get('/configuracion/emails',          [ContratacionController::class, 'configuracion'])->middleware('modulo:contratacion,puede_editar')->name('configuracion');
        Route::patch('/configuracion/emails',        [ContratacionController::class, 'guardarConfiguracion'])->middleware('modulo:contratacion,puede_editar')->name('guardar-configuracion');
        Route::get('/{postulante}',                  [ContratacionController::class, 'show'])->name('show');
        Route::patch('/{postulante}',                [ContratacionController::class, 'update'])->middleware('modulo:contratacion,puede_editar')->name('update');
        Route::delete('/{postulante}',               [ContratacionController::class, 'destroy'])->middleware('modulo:contratacion,puede_eliminar')->name('destroy');
        Route::get('/{postulante}/zip',              [ContratacionController::class, 'descargarZip'])->middleware('modulo:contratacion,puede_editar')->name('zip');
        Route::get('/{postulante}/doc/{campo}',      [ContratacionController::class, 'descargarDocumento'])->middleware('modulo:contratacion,puede_editar')->name('documento');
        Route::get('/{postulante}/ficha-pdf',        [ContratacionController::class, 'fichaPdf'])->middleware('modulo:contratacion,puede_editar')->name('ficha-pdf');
        Route::post('/{postulante}/documentos',      [ContratacionController::class, 'updateDocumentos'])->middleware('modulo:contratacion,puede_editar')->name('update-documentos');
        Route::post('/{postulante}/resincronizar',   [ContratacionController::class, 'resincronizarSharePoint'])->middleware('modulo:contratacion,puede_editar')->name('resincronizar');
    });

    // --- GRAFANA: ANALYTICS TALANA (solo SUPER_ADMIN — beta) ---
    Route::middleware('role:SUPER_ADMIN')->prefix('grafana')->name('grafana.')->group(function () {
        Route::get('/',             [GrafanaController::class, 'index'])->name('index');
        Route::get('/stats',        [GrafanaController::class, 'stats'])->name('stats');
        Route::get('/sync-status',  [GrafanaController::class, 'syncStatus'])->name('sync-status');
        Route::get('/charts',       [GrafanaController::class, 'charts'])->name('charts');
        Route::post('/sync',        [GrafanaController::class, 'sync'])->name('sync')->middleware('throttle:4,5');
    });

    }); // fin middleware consentimiento
});
