<?php

namespace App\Http\Controllers\Urdido\Configuracion\CatalogosJulios;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Páginas de los catálogos; tabla y CRUD viven en los componentes Livewire App\Livewire\Urdido\*. */
class CatalogosUrdidoController extends Controller
{
    /** Catálogo de julios: la misma pantalla para Urdido y Engomado, según la ruta. */
    public function catalogosJulios(Request $request): View
    {
        $departamento = $request->route()?->getName() === 'engomado.configuracion.catalogos.julios' ? 'Engomado' : 'Urdido';

        return view('catalogosurdido.catalago-julios', ['departamento' => $departamento]);
    }

    /** Catálogo de máquinas (CatalogoMaquinas, idrol 156). */
    public function catalogoMaquinas(): View
    {
        return view('catalogosurdido.catalago-maquinas');
    }
}
