<?php

use App\Http\Controllers\Kardex\KardexDatosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kardex — ruta alineada con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: Kardex → /kardex (idrol 210, orden 1500).
|
| Kardex Materia Prima y Producción en Proceso: resumen y flujo, kardex por almacén, qué se
| tejió, cuadre de 3 fuentes y correspondencia E↔S. Las secciones de urdido, julios, tejido y
| crudo salen del libro (hojas InvKardex* de ProdTowel .24) vía /kardex/datos/*; lo demás sigue
| simulado (resources/js/modulos/kardex/datos-simulados.ts).
*/

Route::middleware('module.permission:acceso,210')->group(function (): void {
    Route::view('/kardex', 'modulos.kardex.index')->name('kardex.index');

    Route::controller(KardexDatosController::class)->prefix('kardex/datos')->name('kardex.datos.')->group(function (): void {
        Route::get('/periodos', 'periodos')->name('periodos');
        Route::get('/libro', 'libro')->name('libro');
    });
});
