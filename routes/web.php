<?php

use Illuminate\Support\Facades\Route;


require __DIR__ . '/public.php';

Route::middleware(['auth'])->group(function () {
    require __DIR__ . '/modules/navigation.php';
    require __DIR__ . '/modules/planeacion.php';
    require __DIR__ . '/modules/tejido.php';
    require __DIR__ . '/modules/tejedores.php';
    require __DIR__ . '/modules/urdido.php';
    require __DIR__ . '/modules/engomado.php';
    require __DIR__ . '/modules/atadores.php';
    require __DIR__ . '/modules/programa-urd-eng.php';
    require __DIR__ . '/modules/configuracion.php';
    require __DIR__ . '/modules/mantenimiento.php';
    require __DIR__ . '/modules/telegram.php';
});
