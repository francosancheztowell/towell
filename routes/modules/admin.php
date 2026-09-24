<?php

use Illuminate\Support\Facades\Route;

/*
 * Panel de administración y monitoreo (contrato 11-CONTRACT.md §7, fase 13).
 * Se requiere dentro del grupo `auth` de routes/web.php. Solo área Sistemas (Gate `admin`).
 * Cada pantalla es un componente Livewire de app/Livewire/Admin montado en la vista host;
 * los componentes vuelven a exigir el Gate en cada update (Concerns\SoloAdmin).
 * /admin/pulse lo registra Laravel Pulse (config/pulse.php) con el mismo Gate.
 */
Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
    $host = 'modulos.admin.panel';

    Route::view('/', $host, ['componente' => 'admin.en-linea', 'titulo' => 'En línea'])->name('index');
    Route::view('/sesiones', $host, ['componente' => 'admin.sesiones', 'titulo' => 'Sesiones'])->name('sesiones');
    Route::view('/navegacion', $host, ['componente' => 'admin.navegacion', 'titulo' => 'Navegación'])->name('navegacion');
    Route::view('/rendimiento', $host, ['componente' => 'admin.rendimiento', 'titulo' => 'Rendimiento'])->name('rendimiento');
    Route::view('/errores', $host, ['componente' => 'admin.errores', 'titulo' => 'Errores'])->name('errores');
    Route::view('/errores/{id}', $host, ['componente' => 'admin.error-detalle', 'titulo' => 'Detalle del error'])
        ->whereNumber('id')->name('errores.show');
    Route::view('/accesos', $host, ['componente' => 'admin.accesos', 'titulo' => 'Accesos'])->name('accesos');
});
