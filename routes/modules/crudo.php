<?php

declare(strict_types=1);

use App\Http\Controllers\Crudo\CrudoAuditController;
use App\Http\Controllers\Crudo\CrudoController;
use Illuminate\Support\Facades\Route;

Route::get('/Crudo', [CrudoController::class, 'index'])->name('crudo.index');
Route::get('/Crudo/flog-imagen', [CrudoController::class, 'flogImagen'])->name('crudo.flog-imagen');

Route::prefix('/Crudo/auditorias')->name('crudo.auditorias.')->group(function (): void {
    Route::get('/telar/{telar}/hoy', [CrudoAuditController::class, 'today'])->name('today');
    Route::post('/', [CrudoAuditController::class, 'store'])->name('store');
    Route::post('/paro', [CrudoAuditController::class, 'storeWithStop'])->name('store-with-stop');
});
