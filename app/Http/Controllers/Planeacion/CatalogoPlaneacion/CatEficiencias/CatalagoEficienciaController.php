<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatEficiencias;

use App\Enums\Planeacion\VarianteEstandar;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\EstandarCatalogoController;
use App\Imports\Contracts\ImportConEstadisticas;
use App\Imports\ReqEficienciaStdImport;

/** Eficiencia STD: todo en EstandarCatalogoController / EstandarCatalogoService (dedupe con Velocidad). */
class CatalagoEficienciaController extends EstandarCatalogoController
{
    protected function variante(): VarianteEstandar
    {
        return VarianteEstandar::Eficiencia;
    }

    protected function import(): ImportConEstadisticas
    {
        return new ReqEficienciaStdImport;
    }
}
