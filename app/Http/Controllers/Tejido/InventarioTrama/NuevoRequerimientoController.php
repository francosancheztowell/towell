<?php

namespace App\Http\Controllers\Tejido\InventarioTrama;

use App\Http\Controllers\Controller;
use App\Services\Tejido\InventarioTrama\CatalogoTramaService;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;

class NuevoRequerimientoController extends Controller
{
    public function __construct(
        private readonly NuevoRequerimientoService $requerimientos,
        private readonly CatalogoTramaService $catalogo,
    ) {}

    public function index()
    {
        return view('modulos.inventario-trama.nuevo-requerimiento');
    }
}
