<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tejedores\Configuracion\CatalogoCalibres;

use App\Http\Controllers\Controller;
use App\Livewire\Tejedores\CatalogoCalibres;
use Illuminate\Contracts\View\View;

class CatalogoCalibresController extends Controller
{
    /**
     * Pagina del catalogo. El listado y el CRUD viven en el componente
     * App\Livewire\Tejedores\CatalogoCalibres (tabla reutilizable + permisos).
     */
    public function index(): View
    {
        abort_unless(userCan('acceso', CatalogoCalibres::MODULO), 403, 'No tienes acceso al catalogo de calibres.');

        return view('modulos.tejedores.catalogo-calibres.index');
    }
}
