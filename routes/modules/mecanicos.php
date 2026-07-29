<?php

use App\Http\Controllers\mecanicos\Catalogos\MecActividadesController;
use App\Http\Controllers\mecanicos\MecVerificaMaquinaController;
use App\Http\Controllers\mecanicos\OrdenesTrabajoMecaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Ruta configurada para el submódulo 1100 en SYSRoles.
Route::get('/submodulos/1100/ordenes-de-trabajo', [OrdenesTrabajoMecaController::class, 'index'])
    ->name('mecanicos.ordenes-trabajo.submodulo');

// Ruta configurada para el submódulo 1102 (Estado Maquina) en SYSRoles, dependiente de 1100.
Route::get('/submodulos/1100/estado-maquina', [MecVerificaMaquinaController::class, 'index'])
    ->name('mecanicos.estado-maquina.index');

// CRUD actividades (registrar antes del grid de catálogos para evitar ambigüedad de path).
// Alineado con SYSRoles 1104-1 → Ruta=/mecanicos/catalogos/actividades
Route::prefix('mecanicos/catalogos/actividades')
    ->as('mecanicos.catalogos.actividades.')
    ->group(function (): void {
        Route::get('/', [MecActividadesController::class, 'index'])->name('index');
        Route::post('/', [MecActividadesController::class, 'store'])->name('store');
        Route::get('/{id}', [MecActividadesController::class, 'show'])->name('show')->whereNumber('id');
        Route::put('/{id}', [MecActividadesController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('/{id}', [MecActividadesController::class, 'destroy'])->name('destroy')->whereNumber('id');
    });

// Catálogos nivel 3 (SYSRoles 1104 → Ruta=/mecanicos/catalogos)
Route::get('/mecanicos/catalogos/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
    ->defaults('moduloPadre', '1104')
    ->where('moduloPadre', '1104')
    ->name('mecanicos.catalogos');

Route::get('/submodulos/1100/catalogos', [UsuarioController::class, 'showSubModulosNivel3'])
    ->defaults('moduloPadre', '1104')
    ->name('mecanicos.catalogos.submodulo');

Route::prefix('mecanicos/ordenes-trabajo')
    ->as('mecanicos.ordenes-trabajo.')
    ->group(function (): void {
        Route::get('/', [OrdenesTrabajoMecaController::class, 'index'])->name('index');
        Route::get('/registros', [OrdenesTrabajoMecaController::class, 'registros'])->name('registros');
        Route::get('/paros-activos', [OrdenesTrabajoMecaController::class, 'parosActivos'])->name('paros-activos');
        Route::post('/', [OrdenesTrabajoMecaController::class, 'store'])->name('store');
        Route::get('/{folio}/captura', [OrdenesTrabajoMecaController::class, 'captura'])->name('captura');
        Route::get('/{folio}', [OrdenesTrabajoMecaController::class, 'show'])->name('show');
        Route::put('/{folio}', [OrdenesTrabajoMecaController::class, 'update'])->name('update');
        Route::delete('/{folio}', [OrdenesTrabajoMecaController::class, 'destroy'])->name('destroy');
        Route::post('/{folio}/lineas', [OrdenesTrabajoMecaController::class, 'storeLinea'])->name('lineas.store');
        Route::put('/{folio}/lineas/{linea}', [OrdenesTrabajoMecaController::class, 'updateLinea'])->name('lineas.update')->whereNumber('linea');
        Route::delete('/{folio}/lineas/{linea}', [OrdenesTrabajoMecaController::class, 'destroyLinea'])->name('lineas.destroy')->whereNumber('linea');
    });

Route::prefix('mecanicos/estado-maquina')
    ->as('mecanicos.estado-maquina.')
    ->group(function (): void {
        Route::get('/', [MecVerificaMaquinaController::class, 'index'])->name('index');
        Route::get('/{folio}', [MecVerificaMaquinaController::class, 'show'])->name('show');
    });
