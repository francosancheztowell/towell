<?php

use Illuminate\Support\Facades\Route;

/*
 * Panel de administración y monitoreo (contrato 11-CONTRACT.md §7).
 * Se requiere dentro del grupo `auth` de routes/web.php. Solo área Sistemas (Gate `admin`).
 * Fase 11 deja el placeholder; la fase 13 agrega sesiones, navegación, rendimiento, errores y accesos.
 */
Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
    Route::view('/', 'modulos.admin.index')->name('index');
});
