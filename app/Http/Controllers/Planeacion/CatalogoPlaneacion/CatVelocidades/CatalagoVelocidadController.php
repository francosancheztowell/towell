<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatVelocidades;

use App\Enums\Planeacion\VarianteEstandar;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\EstandarCatalogoController;
use App\Imports\Contracts\ImportConEstadisticas;
use App\Imports\ReqVelocidadStdImport;

/** Velocidad STD: todo en EstandarCatalogoController / EstandarCatalogoService (dedupe con Eficiencia). */
class CatalagoVelocidadController extends EstandarCatalogoController
{
    protected function variante(): VarianteEstandar
    {
        return VarianteEstandar::Velocidad;
    }

    protected function import(): ImportConEstadisticas
    {
        return new ReqVelocidadStdImport;
    }
}
