<?php

use App\Http\Controllers\Kardex\KardexDatosController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kardex — rutas alineadas con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: 209 Kardex (1500)                    → /kardex (grid de submódulos)
| Nivel 2: 211 Crudo - Acabado (1501)           → /kardex/crudo-acabado (en blanco por ahora)
| Nivel 2: 212 Acabado - Costura P.T. (1502)    → /kardex/acabado-costura-pt (en blanco por ahora)
| Nivel 2: 213 M.P. Produccion en Proceso (1503) → /kardex/prod-proceso (dashboard)
| Ojo: el 210 es "Calificar Julios"; con 210 la ruta validaba ese permiso y negaba Kardex.
|
| M.P. Producción en Proceso (/kardex/prod-proceso): resumen y flujo, kardex por
| almacén, qué se tejió, cuadre de 3 fuentes y correspondencia E↔S. Las secciones de urdido,
| julios, tejido y crudo salen del libro (hojas InvKardex* de ProdTowel .24) vía /kardex/datos/*;
| lo demás sigue simulado (resources/js/modulos/kardex/datos-simulados.ts).
*/

Route::middleware('module.permission:acceso,209')->group(function (): void {
    Route::get('/kardex', [UsuarioController::class, 'showSubModulos'])
        ->defaults('moduloPrincipal', 'kardex')
        ->name('kardex.index');
});

Route::middleware('module.permission:acceso,213')->group(function (): void {
    Route::view('/kardex/prod-proceso', 'modulos.kardex.index')->name('kardex.prod-proceso');

    Route::controller(KardexDatosController::class)->prefix('kardex/datos')->name('kardex.datos.')->group(function (): void {
        Route::get('/periodos', 'periodos')->name('periodos');
        Route::get('/libro', 'libro')->name('libro');
    });
});

Route::view('/kardex/crudo-acabado', 'modulos.kardex.en-blanco', ['titulo' => 'Crudo - Acabado'])
    ->middleware('module.permission:acceso,211')
    ->name('kardex.crudo-acabado');

Route::view('/kardex/acabado-costura-pt', 'modulos.kardex.en-blanco', ['titulo' => 'Acabado - Costura P.T.'])
    ->middleware('module.permission:acceso,212')
    ->name('kardex.acabado-costura-pt');
