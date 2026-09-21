<?php

use App\Http\Controllers\mecanicos\Catalogos\MecActividadesController;
use App\Http\Controllers\mecanicos\MecReportesController;
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
| Nivel 3: 1103-1 OT Diarias           → /mecanicos/reportes/ot-diarias
| Nivel 3: 1103-2 Estado de Máquina    → /mecanicos/reportes/estado-maquina
| Nivel 2: 1104 Catálogos              → /mecanicos/catalogos
| Nivel 3:      Actividades            → /mecanicos/catalogos/actividades
*/

// Actividades (registrar antes del grid de catálogos para evitar ambigüedad de path).
Route::prefix('mecanicos/catalogos/actividades')
    ->as('mecanicos.catalogos.actividades.')
    ->group(function (): void {
        Route::get('/', [MecActividadesController::class, 'index'])->name('index');
        Route::post('/', [MecActividadesController::class, 'store'])
            ->middleware('module.permission:crear,198')->name('store'); // Actividades Mecanicos
        Route::get('/{id}', [MecActividadesController::class, 'show'])->name('show')->whereNumber('id');
        Route::put('/{id}', [MecActividadesController::class, 'update'])->whereNumber('id')
            ->middleware('module.permission:modificar,198')->name('update'); // Actividades Mecanicos
        Route::delete('/{id}', [MecActividadesController::class, 'destroy'])->whereNumber('id')
            ->middleware('module.permission:eliminar,198')->name('destroy'); // Actividades Mecanicos
    });

// Catálogos nivel 3 (SYSRoles 1104 → Ruta=/mecanicos/catalogos)
Route::get('/mecanicos/catalogos/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
    ->defaults('moduloPadre', '1104')
    ->where('moduloPadre', '1104')
    ->name('mecanicos.catalogos');

// Reportes nivel 3 (registrar antes del grid para evitar ambigüedad de path).
Route::prefix('mecanicos/reportes')
    ->as('mecanicos.reportes.')
    ->group(function (): void {
        Route::get('/ot-diarias', [MecReportesController::class, 'otDiarias'])->name('ot-diarias');
        Route::post('/ot-diarias/excel', [MecReportesController::class, 'exportarExcelOtDiarias'])->name('ot-diarias.excel');
        Route::post('/ot-diarias/pdf', [MecReportesController::class, 'exportarPdfOtDiarias'])->name('ot-diarias.pdf');
        Route::post('/ot-diarias/telegram-imagen', [MecReportesController::class, 'telegramImagenOtDiarias'])->name('ot-diarias.telegram-imagen');
        Route::get('/estado-maquina', [MecReportesController::class, 'estadoMaquina'])->name('estado-maquina');
        Route::get('/estado-maquina/semanas', [MecReportesController::class, 'semanasEstadoMaquina'])->name('estado-maquina.semanas');
        Route::post('/estado-maquina/excel', [MecReportesController::class, 'exportarExcelEstadoMaquina'])->name('estado-maquina.excel');
        Route::post('/estado-maquina/pdf', [MecReportesController::class, 'exportarPdfEstadoMaquina'])->name('estado-maquina.pdf');
        Route::post('/estado-maquina/telegram-imagen', [MecReportesController::class, 'telegramImagenEstadoMaquina'])->name('estado-maquina.telegram-imagen');
    });

// Reportes nivel 2 (SYSRoles 1103 → Ruta=/mecanicos/reportes)
Route::get('/mecanicos/reportes/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
    ->defaults('moduloPadre', '1103')
    ->where('moduloPadre', '1103')
    ->name('mecanicos.reportes');

Route::prefix('mecanicos/ordenes-trabajo')
    ->as('mecanicos.ordenes-trabajo.')
    ->group(function (): void {
        Route::get('/', [OrdenesTrabajoMecaController::class, 'index'])->name('index');
        Route::get('/registros', [OrdenesTrabajoMecaController::class, 'registros'])->name('registros');
        Route::get('/paros-activos', [OrdenesTrabajoMecaController::class, 'parosActivos'])->name('paros-activos');
        Route::get('/paros-historial', [OrdenesTrabajoMecaController::class, 'parosHistorial'])->name('paros-historial');
        Route::post('/', [OrdenesTrabajoMecaController::class, 'store'])->name('store');
        Route::get('/{folio}/captura', [OrdenesTrabajoMecaController::class, 'captura'])->name('captura');
        Route::get('/{folio}/refacciones', [OrdenesTrabajoMecaController::class, 'refacciones'])->name('refacciones');
        Route::get('/{folio}', [OrdenesTrabajoMecaController::class, 'show'])->name('show');
        Route::put('/{folio}', [OrdenesTrabajoMecaController::class, 'update'])->name('update');
        Route::delete('/{folio}', [OrdenesTrabajoMecaController::class, 'destroy'])
            ->middleware('module.permission:eliminar,193')->name('destroy'); // Ordenes de Trabajo
        Route::post('/{folio}/lineas', [OrdenesTrabajoMecaController::class, 'storeLinea'])->name('lineas.store');
        Route::put('/{folio}/lineas/{linea}', [OrdenesTrabajoMecaController::class, 'updateLinea'])->name('lineas.update')->whereNumber('linea');
        Route::delete('/{folio}/lineas/{linea}', [OrdenesTrabajoMecaController::class, 'destroyLinea'])->whereNumber('linea')
            ->middleware('module.permission:eliminar,193')->name('lineas.destroy'); // Ordenes de Trabajo
        Route::post('/{folio}/finalizar', [OrdenesTrabajoMecaController::class, 'finalizar'])
            ->middleware('module.permission:modificar,193')->name('finalizar'); // Ordenes de Trabajo
        // OrdenesTrabajoMecaController::autorizar ya exige puedeRegistrar() (userCan registrar).
        // La ruta decia 'modificar': se alinea con el controller.
        Route::post('/{folio}/autorizar', [OrdenesTrabajoMecaController::class, 'autorizar'])
            ->middleware('module.permission:registrar,193')->name('autorizar'); // Ordenes de Trabajo
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
