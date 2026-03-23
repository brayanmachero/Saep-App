<?php

use App\Http\Controllers\AccidenteSstController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditoriaSstController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\CartaGanttController;
use App\Http\Controllers\CategoriaFormularioController;
use App\Http\Controllers\CentroCostoController;
use App\Http\Controllers\CharlaSstController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FormularioController;
use App\Http\Controllers\LeyKarinController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\RespuestaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitaSstController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// App (requiere autenticación)
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // --- MAESTROS ---
    Route::resource('usuarios', UserController::class)->except(['show']);
    Route::resource('departamentos', DepartamentoController::class)->except(['show']);
    Route::resource('cargos', CargoController::class)->except(['show']);
    Route::resource('centros-costo', CentroCostoController::class)->except(['show']);
    Route::resource('categorias-formularios', CategoriaFormularioController::class)->except(['show']);

    // --- FORMULARIOS Y RESPUESTAS ---
    Route::resource('formularios', FormularioController::class);
    Route::resource('respuestas', RespuestaController::class);
    Route::patch('respuestas/{respuesta}/estado', [RespuestaController::class, 'cambiarEstado'])
        ->name('respuestas.estado');

    // --- SST: CHARLAS ---
    Route::resource('charlas', CharlaSstController::class);
    Route::patch('charlas/{charla}/estado', [CharlaSstController::class, 'cambiarEstado'])
        ->name('charlas.estado');
    Route::get('charlas/{charla}/firmar/{asistente}',  [CharlaSstController::class, 'firmar'])
        ->name('charlas.firmar');
    Route::post('charlas/{charla}/firmar/{asistente}', [CharlaSstController::class, 'guardarFirma'])
        ->name('charlas.guardarFirma');

    // --- SST: CARTA GANTT ---
    Route::resource('carta-gantt', CartaGanttController::class);
    Route::post('carta-gantt/{cartaGantt}/categorias',   [CartaGanttController::class, 'storeCategoria'])
        ->name('carta-gantt.categorias.store');
    Route::post('carta-gantt/categorias/{categoria}/actividades', [CartaGanttController::class, 'storeActividad'])
        ->name('carta-gantt.actividades.store');
    Route::patch('carta-gantt/actividades/{actividad}/seguimiento', [CartaGanttController::class, 'updateSeguimiento'])
        ->name('carta-gantt.seguimiento.update');

    // --- SST: INSPECCIONES ---
    Route::resource('visitas-sst', VisitaSstController::class);

    // --- SST: AUDITORÍAS ---
    Route::resource('auditorias-sst', AuditoriaSstController::class);

    // --- SST: ACCIDENTES ---
    Route::resource('accidentes-sst', AccidenteSstController::class);

    // --- SST: LEY KARIN ---
    Route::resource('ley-karin', LeyKarinController::class);

    // --- CONFIGURACIÓN ---
    Route::get('configuraciones',   [ConfiguracionController::class, 'index'])->name('configuraciones.index');
    Route::put('configuraciones',   [ConfiguracionController::class, 'update'])->name('configuraciones.update');

    // --- EXPORTACIONES ---
    Route::get('export/respuestas', [ExportController::class, 'respuestas'])->name('export.respuestas');
    Route::get('export/firmas',     [ExportController::class, 'firmas'])->name('export.firmas');

    // --- PDF ---
    Route::get('pdf/respuesta/{respuesta}', [PdfController::class, 'respuesta'])->name('pdf.respuesta');
    Route::get('pdf/charla/{charla}',       [PdfController::class, 'charla'])->name('pdf.charla');
});
