<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tejedores;

use App\Http\Controllers\Controller;
use App\Livewire\Tejedores\CalificarJulios;
use Illuminate\Contracts\View\View;

class CalificarJuliosController extends Controller
{
    /** Página del módulo; la captura y el historial viven en App\Livewire\Tejedores\CalificarJulios. */
    public function index(): View
    {
        abort_unless(userCan('acceso', CalificarJulios::MODULO), 403, 'No tienes acceso a Calificar Julios.');

        return view('modulos.tejedores.calificar-julios.index');
    }
}
