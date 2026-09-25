<?php

/*
 * Rutas de desarrollo (fase 16). Solo existen con APP_ENV=local: en testing, staging y
 * producción ni siquiera se registran (404). Van dentro del grupo `auth` de routes/web.php
 * porque la galería usa el layout de la app (navbar con usuario).
 */

use Illuminate\Support\Facades\Route;

if (app()->isLocal()) {
    // Galería de componentes: docs/cerebro-towell/Arquitectura/receta-componentes.md
    Route::view('/dev/ui-kit', 'dev.ui-kit')->name('dev.ui-kit');
}
