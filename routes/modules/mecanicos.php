<?php

use App\Http\Controllers\mecanicos\Catalogos\MecActividadesController;
use App\Http\Controllers\mecanicos\MecVerificaMaquinaController;
use App\Http\Controllers\mecanicos\OrdenesTrabajoMecaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mecánicos — rutas alineadas con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: 1100 Mecanicos              → /mecanicos
| Nivel 2: 1101 Ordenes de Trabajo     → /mecanicos/ordenes-trabajo
| Nivel 2: 1102 Estado Maquina         → /mecanicos/estado-maquina
| Nivel 2: 1103 Reportes               → /mecanicos/reportes
| Nivel 2: 1104 Catálogos              → /mecanicos/catalogos
| Nivel 3:      Actividades            → /mecanicos/catalogos/actividades
*/

// Actividades (registrar antes del grid de catálogos para evitar ambigüedad de path).
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

// Reportes (SYSRoles 1103 → Ruta=/mecanicos/reportes)
Route::get('/mecanicos/reportes', function () {
    return view('modulos.mecanicos.reportes.index', [
        'reportes' => [],
    ]);
})->name('mecanicos.reportes');

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
        Route::post('/{folio}/autorizar', [OrdenesTrabajoMecaController::class, 'autorizar'])->name('autorizar');
    });

Route::prefix('mecanicos/estado-maquina')
    ->as('mecanicos.estado-maquina.')
    ->group(function (): void {
        Route::get('/', [MecVerificaMaquinaController::class, 'index'])->name('index');
        Route::get('/{folio}', [MecVerificaMaquinaController::class, 'show'])->name('show');
    });

// Módulo principal (SYSRoles 1100 → Ruta=/mecanicos)
Route::get('/mecanicos/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'mecanicos')
    ->where('moduloPrincipal', 'mecanicos')
    ->name('mecanicos.index');

// Compatibilidad con rutas antiguas /submodulos/1100/...
Route::redirect('/submodulos/1100', '/mecanicos', 301);
Route::redirect('/submodulos/1100/ordenes-de-trabajo', '/mecanicos/ordenes-trabajo', 301);
Route::redirect('/submodulos/1100/estado-maquina', '/mecanicos/estado-maquina', 301);
Route::redirect('/submodulos/1100/catalogos', '/mecanicos/catalogos', 301);
Route::redirect('/submodulos/1100/reportes', '/mecanicos/reportes', 301);
