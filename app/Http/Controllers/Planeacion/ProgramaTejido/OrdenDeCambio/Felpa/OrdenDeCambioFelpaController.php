<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa;

use App\Http\Controllers\Controller;
use App\Services\Planeacion\ProgramaTejido\OrdenDeCambio\OrdenDeCambioFelpaExcel;
use Illuminate\Http\Request;

class OrdenDeCambioFelpaController extends Controller
{
    /**
     * Excel de Orden de Cambio desde una plantilla con la hoja REGISTRO ya llena
     * (la subida en "plantilla" o la fija).
     */
    public function generarExcel(Request $request, OrdenDeCambioFelpaExcel $excel)
    {
        return $excel->generarExcel($request->file('plantilla')?->getRealPath() ?: null);
    }
}
