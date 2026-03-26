<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Exports\Saldos2026Export;
use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SaldosController extends Controller
{
    public function index()
    {
        $registros = $this->query()->get();

        return view('modulos.tejido.reportes.saldos-2026', compact('registros'));
    }

    public function exportarExcel()
    {
        $registros = $this->query()->get();

        return Excel::download(new Saldos2026Export($registros), 'saldos-2026.xlsx');
    }

    private function query()
    {
        return ReqProgramaTejido::query()
            ->whereNotNull('NoProduccion')
            ->where('NoProduccion', '!=', '')
            ->leftJoin(DB::raw('(
                SELECT TamanoClave, Tolerancia, CodigoDibujo, FlogsId, Clave, Obs,
                       TipoRizo, AlturaRizo,
                       Comb1, Obs1, Comb2, Obs2, Comb3, Obs3, Comb4, Obs4,
                       MedidaCenefa, MedIniRizoCenefa
                FROM (
                    SELECT TamanoClave, Tolerancia, CodigoDibujo, FlogsId, Clave, Obs,
                           TipoRizo, AlturaRizo,
                           Comb1, Obs1, Comb2, Obs2, Comb3, Obs3, Comb4, Obs4,
                           MedidaCenefa, MedIniRizoCenefa,
                           ROW_NUMBER() OVER (PARTITION BY TamanoClave ORDER BY Id DESC) AS rn
                    FROM dbo.ReqModelosCodificados
                ) AS ranked
                WHERE rn = 1
            ) AS rmc'), 'rmc.TamanoClave', '=', 'ReqProgramaTejido.TamanoClave')
            ->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->orderBy('Posicion')
            ->select([
                'ReqProgramaTejido.Id', 'SalonTejidoId', 'NoTelarId', 'FechaInicio', 'NoExisteBase', 'NoProduccion',
                'EnProceso', 'OrdCompartida', 'OrdCompartidaLider',
                DB::raw('(SELECT TOP 1 r2.NoProduccion FROM dbo.ReqProgramaTejido r2 WHERE r2.OrdCompartida = ReqProgramaTejido.OrdCompartida AND r2.OrdCompartidaLider = 1) AS OrdenLider'),
                'FechaCreacion', 'EntregaCte',
                'Programado', 'Prioridad', 'NombreProducto', 'ReqProgramaTejido.TamanoClave',
                'ItemId', 'ReqProgramaTejido.FlogsId', 'EntregaProduc', 'TotalPedido', 'Peine', 'Ancho', 'LargoCrudo', 'PesoCrudo', 'Luchaje',
                'CalibreTrama2', 'FibraTrama', 'MedidaPlano', 'VelocidadSTD',
                'CuentaRizo', 'CalibreRizo2', 'FibraRizo',
                'CuentaPie', 'CalibrePie2', 'FibraPie',
                'Rasurado', 'NoTiras', 'Repeticiones',
                'TotalRollos', 'Produccion', 'SaldoPedido',
                'ReqProgramaTejido.Observaciones',
                // Campos de ReqModelosCodificados
                DB::raw('rmc.Tolerancia         AS Tolerancia'),
                DB::raw('rmc.CodigoDibujo        AS CodigoDibujo'),
                DB::raw('rmc.FlogsId             AS FlogsIdRmc'),
                DB::raw('rmc.Clave               AS Clave'),
                DB::raw('rmc.Obs                 AS ObsModelo'),
                DB::raw('rmc.TipoRizo            AS TipoRizo'),
                DB::raw('rmc.AlturaRizo          AS AlturaRizo'),
                DB::raw('rmc.Comb1               AS C1'),
                DB::raw('rmc.Obs1                AS ObsC1'),
                DB::raw('rmc.Comb2               AS C2'),
                DB::raw('rmc.Obs2                AS ObsC2'),
                DB::raw('rmc.Comb3               AS C3'),
                DB::raw('rmc.Obs3                AS ObsC3'),
                DB::raw('rmc.Comb4               AS C4'),
                DB::raw('rmc.Obs4                AS ObsC4'),
                DB::raw('rmc.MedidaCenefa        AS MedidaCenefa'),
                DB::raw('rmc.MedIniRizoCenefa    AS MedIniRizoCenefa'),
            ]);
    }
}
