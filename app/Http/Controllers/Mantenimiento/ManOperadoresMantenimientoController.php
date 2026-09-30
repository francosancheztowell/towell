<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ManOperadoresMantenimientoController extends Controller
{
    /**
     * Catálogo de operadores de mantenimiento. Listado, filtros y CRUD viven en el
     * componente Livewire CatalogoOperadores (19-08), que también revisa permisos
     * (idrol 53) en mount() y en cada acción.
     */
    public function index(): View
    {
        return view('modulos.mantenimiento.operadores-mantenimiento.index');
    }
}
