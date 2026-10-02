<?php

declare(strict_types=1);

namespace App\Http\Controllers\Urdido;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ListaMaterialesController extends Controller
{
    /**
     * Página de la lista de materiales. Tabla y CRUD viven en el componente
     * App\Livewire\Urdido\ListaMateriales; el permiso lo pide la ruta (idrol 205).
     */
    public function index(): View
    {
        return view('modulos.urdido.lista-materiales.index');
    }
}
