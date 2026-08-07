<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class CatalogosFallasController extends Controller
{
    /**
     * Página del catálogo. El listado y el CRUD viven en el componente
     * App\Livewire\Mantenimiento\CatalogoFallas (tabla reutilizable + permisos).
     */
    public function index(): View
    {
        abort_unless(userCan('acceso', 'Catalogo de Fallas'), 403, 'No tienes acceso al catálogo de fallas.');

        return view('modulos.mantenimiento.catalogos-fallas.index');
    }
}
