<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('programa-tejido:recalcular-fechas-produccion')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20)
    ->runInBackground();

// Reporte de telares por correo tres veces al día. El de las 06:00 cae antes
// del corte de las 06:30, así que cierra el día de producción que termina.
foreach (['06:00', '14:00', '22:00'] as $hora) {
    Schedule::command('crudo:enviar-reporte')
        ->dailyAt($hora)
        ->withoutOverlapping(10)
        ->runInBackground();
}
