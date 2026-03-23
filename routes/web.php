<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CharlaSstController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\FormularioController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\RespuestaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// App (requiere autenticación)
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Usuarios
    Route::resource('usuarios', UserController::class)->except(['show']);

    // Departamentos
    Route::resource('departamentos', DepartamentoController::class)->except(['show']);

    // Formularios
    Route::resource('formularios', FormularioController::class);

    // Respuestas
    Route::resource('respuestas', RespuestaController::class);
    Route::patch('respuestas/{respuesta}/estado', [RespuestaController::class, 'cambiarEstado'])
        ->name('respuestas.estado');

    // Charlas SST
    Route::resource('charlas', CharlaSstController::class);
    Route::patch('charlas/{charla}/estado', [CharlaSstController::class, 'cambiarEstado'])
        ->name('charlas.estado');
    Route::get('charlas/{charla}/firmar/{asistente}', [CharlaSstController::class, 'firmar'])
        ->name('charlas.firmar');
    Route::post('charlas/{charla}/firmar/{asistente}', [CharlaSstController::class, 'guardarFirma'])
        ->name('charlas.guardarFirma');
    // PDF Downloads
    Route::get('pdf/respuesta/{respuesta}', [PdfController::class, 'respuesta'])->name('pdf.respuesta');
    Route::get('pdf/charla/{charla}',       [PdfController::class, 'charla'])->name('pdf.charla');
});
