<?php

use App\Http\Controllers\Ventas\VentasDatosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ventas — ruta alineada con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: Ventas → /ventas
|
| Dashboard Plan de Ventas vs Pedido (OC) vs Real y Ventas históricas, con datos de
| dbo.TwHistoricosPronostico, dbo.TwHistoricosPedidos y dbo.TwHistoricosVentas.
| La página se sirve vacía y el navegador pide los datos a /ventas/datos/*.
*/

Route::view('/ventas', 'modulos.ventas.dashboard-pv-vs-oc.index')->name('ventas.index');

Route::controller(VentasDatosController::class)->prefix('ventas/datos')->name('ventas.datos.')->group(function () {
    Route::get('/compara', 'compara')->name('compara');
    Route::get('/historico', 'historico')->name('historico');
});
