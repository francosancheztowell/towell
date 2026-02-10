<?php

namespace App\Http\Controllers\Engomado;

use App\Http\Controllers\Controller;

class ReportesEngomadoController extends Controller
{
    /**
     * Selector de reportes: 03-OEE URD-ENG y Kaizen (usan el controlador de Urdido)
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Reportes de Producción 03-OEE URD-ENG',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.03-oee'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Kaizen urd-eng (AX ENGOMADO / AX URDIDO)',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.kaizen'),
                'disponible' => true,
            ],
            [
                'nombre' => 'ROTURAS X MILLÓN',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.roturas'),
                'disponible' => true,
            ],
        ];

        return view('modulos.engomado.reportes-engomado-index', ['reportes' => $reportes]);
    }
}
