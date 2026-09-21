<?php

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ventas — ruta alineada con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: Ventas → /ventas
|
| El contenido visible depende de los submódulos de Ventas asignados al
| usuario en SYSRoles / SYSUsuariosRoles.
*/

Route::get('/ventas/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'ventas')
    ->where('moduloPrincipal', 'ventas')
    ->name('ventas.index');
