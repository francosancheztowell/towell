<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Exports\Saldos2026Export;
use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaldosController extends Controller
{
    public function index()
    {
        $registros = $this->query()->get();
        $registros = $registros->concat($this->fetchFinalizadosEnGrupos($registros));
        $registros = $this->preprocesarGrupos($registros);

        return view('modulos.tejido.reportes.saldos-2026', compact('registros'));
    }

    public function exportarExcel()
    {
        $registros = $this->query()->get();
        $registros = $registros->concat($this->fetchFinalizadosEnGrupos($registros));
        $registros = $this->preprocesarGrupos($registros);

        return (new Saldos2026Export($registros))->downloadResponse('saldos-2026.xlsx');
    }

    /**
     * Busca en CatCodificados los registros que ya finalizaron pero pertenecen a un
     * OrdCompartida que aún tiene miembros activos en ReqProgramaTejido.
     * Los retorna como objetos planos con los campos esperados por la vista,
     * marcados con _finalizado = true para mostrar el punto rojo.
     */
    private function fetchFinalizadosEnGrupos(Collection $registros): Collection
    {
        $ordCompartidas = $registros
            ->pluck('OrdCompartida')
            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->toArray();

        if (empty($ordCompartidas)) {
            return collect();
        }

        // OrdenTejido que ya están en los activos — excluirlos para no duplicar
        $noProduccionesActivas = $registros
            ->pluck('NoProduccion')
            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
            ->unique()
            ->values()
            ->toArray();

        $finalizados = CatCodificados::whereIn('OrdCompartida', $ordCompartidas)
            ->whereNotNull('OrdenTejido')
            ->where('OrdenTejido', '!=', '')
            ->when(! empty($noProduccionesActivas), fn ($q) => $q->whereNotIn('OrdenTejido', $noProduccionesActivas))
            ->get([
                'Id', 'OrdenTejido', 'TelarId', 'Nombre',
                'Pedido', 'Produccion', 'Saldos',
                'OrdCompartida', 'OrdCompartidaLider',
                'FechaTejido', 'Departamento',
                'TotalRollos', 'PzasRollo',
                'ItemId', 'Prioridad', 'NoMarbete',
            ]);

        return $finalizados->map(function ($cat) {
            $obj = new \stdClass;

            // Campos mapeados desde CatCodificados
            $obj->Id                = 'cat_'.$cat->Id;
            $obj->NoProduccion      = $cat->OrdenTejido;
            $obj->NoTelarId         = $cat->TelarId;
            $obj->SalonTejidoId     = $cat->Departamento;
            $obj->NombreProducto    = $cat->Nombre;
            $obj->TotalPedido       = $cat->Pedido;
            $obj->Produccion        = $cat->Produccion;
            $obj->SaldoPedido       = $cat->Saldos;
            $obj->OrdCompartida     = $cat->OrdCompartida;
            $obj->OrdCompartidaLider = $cat->OrdCompartidaLider;
            $obj->FechaInicio       = $cat->FechaTejido;
            $obj->TotalRollos       = $cat->TotalRollos;
            $obj->PzasRollo         = $cat->PzasRollo;
            $obj->ItemId            = $cat->ItemId;
            $obj->Prioridad         = $cat->Prioridad;
            $obj->NoMarbete         = $cat->NoMarbete;

            // Campos sin equivalente en CatCodificados
            $obj->Posicion          = null;
            $obj->EnProceso         = null;
            $obj->NoExisteBase      = null;
            $obj->FechaCreacion     = null;
            $obj->EntregaCte        = null;
            $obj->Programado        = null;
            $obj->FlogsId           = null;
            $obj->EntregaProduc     = null;
            $obj->TamanoClave       = null;
            $obj->Peine             = null;
            $obj->Ancho             = null;
            $obj->LargoCrudo        = null;
            $obj->PesoCrudo         = null;
            $obj->Luchaje           = null;
            $obj->CalibreTrama2     = null;
            $obj->FibraTrama        = null;
            $obj->MedidaPlano       = null;
            $obj->VelocidadSTD      = null;
            $obj->CuentaRizo        = null;
            $obj->CalibreRizo2      = null;
            $obj->FibraRizo         = null;
            $obj->CuentaPie         = null;
            $obj->CalibrePie2       = null;
            $obj->FibraPie          = null;
            $obj->Rasurado          = null;
            $obj->NoTiras           = null;
            $obj->Repeticiones      = null;
            $obj->Observaciones     = null;
            $obj->OrdenLider        = null;
            // Campos de ReqModelosCodificados (no disponibles)
            $obj->Tolerancia        = null;
            $obj->CodigoDibujo      = null;
            $obj->FlogsIdRmc        = null;
            $obj->Clave             = null;
            $obj->ObsModelo         = null;
            $obj->TipoRizo          = null;
            $obj->AlturaRizo        = null;
            $obj->C1                = null;
            $obj->ObsC1             = null;
            $obj->C2                = null;
            $obj->ObsC2             = null;
            $obj->C3                = null;
            $obj->ObsC3             = null;
            $obj->C4                = null;
            $obj->ObsC4             = null;
            $obj->MedidaCenefa      = null;
            $obj->MedIniRizoCenefa  = null;

            $obj->_finalizado = true;

            return $obj;
        });
    }

    private function preprocesarGrupos($registros)
    {
        $grupos = $registros->groupBy(function ($r) {
            $clave = trim($r->OrdCompartida ?? '');

            return $clave !== '' ? $clave : '__solo__'.$r->Id;
        });

        $processed = collect();

        foreach ($grupos as $key => $grupo) {
            $esGrupoVinculado = ! str_starts_with($key, '__solo__');

            if ($esGrupoVinculado) {
                $lider = $grupo->first(); // el primero de arriba es el líder
                $noLiderOrden = $lider->NoProduccion;

                $sumTotalPedido = $grupo->sum('TotalPedido');
                $sumSaldoPedido = $grupo->sum('SaldoPedido');
                $sumProduccion = $grupo->sum('Produccion');
                $sumTotalRollos = $grupo->sum('TotalRollos');
                $sumTotalPzasProgramadas = $grupo->sum(function ($r) {
                    return (float) ($r->PzasRollo ?? 0) * (float) ($r->TotalRollos ?? 0);
                });

                foreach ($grupo as $r) {
                    $r->_esLider = ($r->Id === $lider->Id); // líder = el primero del grupo
                    $r->_esGrupoVinculado = true;
                    $r->_ordenLider = $noLiderOrden;
                    if ($r->_esLider) {
                        $r->_sumTotalPedido = $sumTotalPedido;
                        $r->_sumSaldoPedido = $sumSaldoPedido;
                        $r->_sumProduccion = $sumProduccion;
                        $r->_sumTotalRollos = $sumTotalRollos;
                        $r->_sumTotalPzasProgramadas = $sumTotalPzasProgramadas;
                    } else {
                        $r->_sumTotalPedido = null;
                        $r->_sumSaldoPedido = null;
                        $r->_sumProduccion = null;
                        $r->_sumTotalRollos = null;
                        $r->_sumTotalPzasProgramadas = null;
                    }
                }
            } else {
                foreach ($grupo as $r) {
                    $r->_esLider = true;
                    $r->_esGrupoVinculado = false;
                    $r->_ordenLider = null;
                    $r->_sumTotalPedido = $r->TotalPedido;
                    $r->_sumSaldoPedido = $r->SaldoPedido;
                    $r->_sumProduccion = $r->Produccion;
                    $r->_sumTotalRollos = $r->TotalRollos;
                    $r->_sumTotalPzasProgramadas = (float) ($r->PzasRollo ?? 0) * (float) ($r->TotalRollos ?? 0);
                }
            }

            $processed = $processed->merge($grupo);
        }

        return $processed;
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
                DB::raw('(SELECT TOP 1 cc.NoMarbete FROM dbo.CatCodificados cc WHERE cc.OrdenTejido = ReqProgramaTejido.NoProduccion ORDER BY cc.Id DESC) AS NoMarbete'),
                'FechaCreacion', 'EntregaCte',
                'Programado', 'Prioridad', 'NombreProducto', 'ReqProgramaTejido.TamanoClave',
                'ItemId', 'ReqProgramaTejido.FlogsId', 'EntregaProduc', 'TotalPedido', 'Peine', 'Ancho', 'LargoCrudo', 'PesoCrudo', 'Luchaje',
                'CalibreTrama2', 'FibraTrama', 'MedidaPlano', 'VelocidadSTD',
                'CuentaRizo', 'CalibreRizo2', 'FibraRizo',
                'CuentaPie', 'CalibrePie2', 'FibraPie',
                'Rasurado', 'NoTiras', 'PzasRollo', 'Repeticiones',
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
