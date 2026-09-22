<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ventas — ruta alineada con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: Ventas → /ventas
|
| Pantalla de demostración Plan de Ventas vs Pedido (OC) vs Real.
| Los datos son mock; no consulta las tablas de reportes.
*/

Route::view('/ventas', 'modulos.ventas.dashboard-pv-vs-oc.index')->name('ventas.index');
