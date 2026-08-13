<?php

namespace App\Http\Controllers\mecanicos;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MecReportesController extends Controller
{
    public function otDiarias(): View
    {
        return view('modulos.mecanicos.reportes.placeholder', [
            'titulo' => 'Órdenes de Trabajo Diarias',
        ]);
    }

    public function estadoMaquina(): View
    {
        return view('modulos.mecanicos.reportes.placeholder', [
            'titulo' => 'Reporte Estado de Máquina',
        ]);
    }
}
