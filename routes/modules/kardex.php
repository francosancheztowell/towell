<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kardex — ruta alineada con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: Kardex → /kardex (idrol 210, orden 1500).
|
| Kardex Materia Prima y Producción en Proceso: resumen y flujo, kardex por almacén, qué se
| tejió, cuadre de 3 fuentes y correspondencia E↔S. Hoy la página trae datos simulados
| (resources/js/modulos/kardex/datos-simulados.ts); falta conectar el backend.
*/

Route::view('/kardex', 'modulos.kardex.index')
    ->middleware('module.permission:acceso,210')
    ->name('kardex.index');
