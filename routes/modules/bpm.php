<?php

use Illuminate\Support\Facades\Route;

/*
 * BPM de Urdido, Engomado y Tejedores: las mismas tres pantallas Livewire por área
 * (App\Livewire\Bpm\Folios, Checklist, Actividades) en la vista host modulos.bpm.pagina.
 * Las URL son las de siempre (SYSRoles.Ruta y marcadores); los permisos los revisa cada componente
 * en mount() y en cada acción, porque las acciones de Livewire no pasan por estas rutas.
 */
$areas = [
    'urdido' => ['folios' => '/urd-bpm', 'checklist' => '/urd-bpm-line/{folio}', 'actividades' => '/urdido/configuracion/actividadesbpmurdido',
        'legado' => ['/urdido/configuracion/actividades-bpm', '/urd-actividades-bpm']],
    'engomado' => ['folios' => '/eng-bpm', 'checklist' => '/eng-bpm-line/{folio}', 'actividades' => '/engomado/configuracion/actividadesbpmengomado',
        'legado' => ['/engomado/configuracion/actividades-bpm', '/eng-actividades-bpm']],
    'tejedores' => ['folios' => '/tejedores/bpmtejedores', 'checklist' => '/tel-bpm/{folio}/lineas', 'actividades' => '/tejedores/configurar/actividadestejedores',
        'legado' => ['/tel-actividades-bpm', '/ActividadesBPM']],
];

foreach ($areas as $area => $url) {
    $titulo = 'BPM '.ucfirst($area);
    $host = fn (string $componente, string $titulo) => ['componente' => $componente, 'area' => $area, 'titulo' => $titulo];

    Route::view($url['folios'], 'modulos.bpm.pagina', $host('bpm.folios', $titulo))->name("{$area}.bpm.folios");
    Route::view($url['checklist'], 'modulos.bpm.pagina', $host('bpm.checklist', 'Checklist '.$titulo))->name("{$area}.bpm.checklist");
    Route::view($url['actividades'], 'modulos.bpm.pagina', $host('bpm.actividades', 'Actividades '.$titulo))->name("{$area}.bpm.actividades");
    foreach ($url['legado'] as $legado) {
        Route::redirect($legado, $url['actividades'], 301);
    }
}

Route::redirect('/tel-bpm', '/tejedores/bpmtejedores', 301);
