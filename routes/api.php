<?php

use App\Http\Controllers\Api\WallmarPenonAttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API externa de Wallmar / LTS Peñón
|--------------------------------------------------------------------------
|
| Esta API no tiene rutas de escritura. Solo expone las marcas que SAEP ya
| recibió de Talana y guardó localmente para el centro autorizado.
|
*/
Route::prefix('v1/wallmar/penon')
    ->middleware(['wallmar.attendance', 'throttle:30,1'])
    ->group(function (): void {
        Route::get('asistencia', [WallmarPenonAttendanceController::class, 'index'])
            ->name('api.wallmar.penon.asistencia');
    });
