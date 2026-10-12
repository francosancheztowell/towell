<?php

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Costos — ruta alineada con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: Costos → /costos (orden 1400). Muestra sus submódulos, como /planeacion.
| Nivel 2: Cuotas → /costos/cuotas. CosCuotasReal y CosCuotasSTD en una pantalla (Livewire Costos\Cuotas).
*/

Route::get('/costos', fn () => app(UsuarioController::class)->showSubModulos('costos'))
    ->name('costos.index');

// El acceso lo valida el componente (App\Livewire\Costos\Cuotas::mount).
Route::view('/costos/cuotas', 'modulos.costos.cuotas.index')->name('costos.cuotas');

// Hoja de costo unitario por artículo. ponytail: sin módulo en SYSRoles aún, basta con auth.
Route::view('/costos/hojacostos', 'modulos.costos.hoja-costos.index')->name('costos.hojacostos');
