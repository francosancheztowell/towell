<?php

use App\Http\Controllers\Monitoreo\TelemetriaController;
use App\Http\Middleware\ProgramaTejidoContext;
use App\Http\Middleware\SetSqlContextInfo;
use Illuminate\Support\Facades\Route;

/*
 * Telemetría cliente → servidor (contrato 11-CONTRACT.md §4).
 * Se requiere dentro del grupo `auth` de routes/web.php. CSRF normal: el cliente manda
 * X-CSRF-TOKEN y sendBeacon manda `_token` en FormData.
 */
Route::prefix('telemetria')
    ->name('telemetria.')
    ->middleware('throttle:telemetria')
    ->withoutMiddleware([SetSqlContextInfo::class, ProgramaTejidoContext::class])
    ->group(function () {
        Route::post('/latido', [TelemetriaController::class, 'latido'])->name('latido');
        Route::post('/vista', [TelemetriaController::class, 'vista'])->name('vista');
        Route::post('/vista/{uuid}/fin', [TelemetriaController::class, 'vistaFin'])->name('vista.fin');
        Route::post('/error', [TelemetriaController::class, 'error'])->name('error');
        Route::post('/dispositivo/nombre', [TelemetriaController::class, 'nombre'])->name('dispositivo.nombre');
    });
