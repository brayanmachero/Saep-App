<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Comercial\Http\Controllers\CotizacionController;
use App\Modules\Comercial\Http\Controllers\ClienteController;
use App\Modules\Comercial\Http\Controllers\CentroCostoController;
use App\Modules\Comercial\Http\Controllers\ParametroController;
use App\Modules\Comercial\Http\Controllers\ReporteController;

Route::middleware(['auth', 'consentimiento', 'force.password', 'modulo:comercial'])
    ->prefix('comercial')
    ->name('comercial.')
    ->group(function () {
    // Cotizaciones
    Route::get('cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::get('cotizaciones/create', [CotizacionController::class, 'create'])->middleware('modulo:comercial,puede_crear')->name('cotizaciones.create');
    Route::post('cotizaciones', [CotizacionController::class, 'store'])->middleware('modulo:comercial,puede_crear')->name('cotizaciones.store');
    Route::post('cotizaciones/previsualizar', [CotizacionController::class, 'previsualizar'])->name('cotizaciones.preview');
    Route::get('cotizaciones/{cotizacion}', [CotizacionController::class, 'show'])->name('cotizaciones.show');
    Route::get('cotizaciones/{cotizacion}/edit', [CotizacionController::class, 'edit'])->middleware('modulo:comercial,puede_editar')->name('cotizaciones.edit');
    Route::match(['put', 'patch'], 'cotizaciones/{cotizacion}', [CotizacionController::class, 'update'])->middleware('modulo:comercial,puede_editar')->name('cotizaciones.update');
    Route::delete('cotizaciones/{cotizacion}', [CotizacionController::class, 'destroy'])->middleware('modulo:comercial,puede_eliminar')->name('cotizaciones.destroy');
    Route::patch('cotizaciones/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])->middleware('modulo:comercial,puede_editar')->name('cotizaciones.aprobar');
    Route::patch('cotizaciones/{cotizacion}/hacer-vigente', [CotizacionController::class, 'hacerVigente'])->middleware('modulo:comercial,puede_editar')->name('cotizaciones.hacer-vigente');
    Route::patch('cotizaciones/{cotizacion}/cancelar', [CotizacionController::class, 'cancelar'])->middleware('modulo:comercial,puede_editar')->name('cotizaciones.cancelar');
    Route::get('cotizaciones/{cotizacion}/historico', [CotizacionController::class, 'historico'])->name('cotizaciones.historico');
    Route::post('cotizaciones/{cotizacion}/enviar-email', [CotizacionController::class, 'enviarEmail'])->middleware('modulo:comercial,puede_editar')->name('cotizaciones.enviar-email');
    Route::get('cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'generatePdf'])->name('cotizaciones.pdf');

    // Clientes
    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('clientes/create', [ClienteController::class, 'create'])->middleware('modulo:comercial,puede_crear')->name('clientes.create');
    Route::post('clientes', [ClienteController::class, 'store'])->middleware('modulo:comercial,puede_crear')->name('clientes.store');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('clientes/{cliente}/edit', [ClienteController::class, 'edit'])->middleware('modulo:comercial,puede_editar')->name('clientes.edit');
    Route::match(['put', 'patch'], 'clientes/{cliente}', [ClienteController::class, 'update'])->middleware('modulo:comercial,puede_editar')->name('clientes.update');
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('modulo:comercial,puede_eliminar')->name('clientes.destroy');

    // Centros de Costo
    Route::get('centros-costo', [CentroCostoController::class, 'index'])->name('centros-costo.index');
    Route::get('centros-costo/create', [CentroCostoController::class, 'create'])->middleware('modulo:comercial,puede_crear')->name('centros-costo.create');
    Route::post('centros-costo', [CentroCostoController::class, 'store'])->middleware('modulo:comercial,puede_crear')->name('centros-costo.store');
    Route::get('centros-costo/{centroCosto}', [CentroCostoController::class, 'show'])->name('centros-costo.show');
    Route::get('centros-costo/{centroCosto}/edit', [CentroCostoController::class, 'edit'])->middleware('modulo:comercial,puede_editar')->name('centros-costo.edit');
    Route::match(['put', 'patch'], 'centros-costo/{centroCosto}', [CentroCostoController::class, 'update'])->middleware('modulo:comercial,puede_editar')->name('centros-costo.update');
    Route::delete('centros-costo/{centroCosto}', [CentroCostoController::class, 'destroy'])->middleware('modulo:comercial,puede_eliminar')->name('centros-costo.destroy');

    // Parámetros (Mantenedor)
    Route::get('mantenedor/parametros', [ParametroController::class, 'index'])->name('parametros.index');
    Route::patch('mantenedor/parametros', [ParametroController::class, 'update'])->middleware('modulo:comercial,puede_editar')->name('parametros.update');
    Route::post('mantenedor/parametros/batch-update', [ParametroController::class, 'batchUpdate'])->middleware('modulo:comercial,puede_editar')->name('parametros.batch-update');
    Route::post('mantenedor/parametros/actualizar-gobierno', [ParametroController::class, 'actualizarGobierno'])->middleware('modulo:comercial,puede_editar')->name('parametros.actualizar-gobierno');

    // Reportes
    Route::get('reportes/cotizaciones', [ReporteController::class, 'cotizaciones'])->name('reportes.cotizaciones');
    Route::get('reportes/clientes', [ReporteController::class, 'clientes'])->name('reportes.clientes');
    Route::post('reportes/export-excel', [ReporteController::class, 'exportExcel'])->name('reportes.exportExcel');
});
