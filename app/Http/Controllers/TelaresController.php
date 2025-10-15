<?php

namespace App\Http\Controllers;

use App\Models\Planeacion;
use App\Models\InvSecuenciaTelares;
use App\Models\ReqProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelaresController
{
    /**
     * Mostrar información individual de un telar
     * Obtiene datos reales desde ReqProgramaTejido
     */
    public function mostrarTelarSulzer($telar)
    {
        // Determinar el tipo de salón según el rango del telar
        $tipoSalon = $this->determinarTipoSalon($telar);

        // Obtener el telar en proceso
        $telarEnProceso = DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $telar)
            ->where('EnProceso', 1)
            ->select([
                'NoTelarId as Telar',
                'EnProceso as en_proceso',
                'NoProduccion as Orden_Prod',
                'FlogsId as Id_Flog',
                'CustName as Cliente',
                'NoTiras as Tiras',
                'TamanoClave as Tamano_AX',
                'ItemId as ItemId',
                'NombreProducto as Nombre_Producto',
                'CuentaRizo as Cuenta',
                'CalibreRizo as Calibre_Rizo',
                'FibraRizo as Fibra_Rizo',
                'CuentaPie as Cuenta_Pie',
                'CalibrePie as Calibre_Pie',
                'FibraPie as Fibra_Pie',
                'CalibreTrama as CALIBRE_TRA',
                'ColorTrama as COLOR_TRAMA',
                'TotalPedido as Saldos',
                'Produccion as Prod_Kg_Dia',
                'SaldoMarbete as Marbetes_Pend',
                'SaldoMarbete as MarbetesPend',
                'FechaInicio as Inicio_Tejido',
                'FechaFinal as Fin_Tejido',
                'EntregaCte as Fecha_Compromiso',
                DB::raw('0 as Total_Paros'),
                DB::raw('NULL as Tiempo_Paro'),
                // Campos adicionales para mostrar información completa
                'PasadasTrama as PASADAS_TRAMA',
                'NombreCC1 as COLOR_C1',
                'NombreCC2 as COLOR_C2',
                'NombreCC3 as COLOR_C3',
                'NombreCC4 as COLOR_C4',
                'NombreCC5 as COLOR_C5',
                'CalibreComb12 as CALIBRE_C1',
                'CalibreComb22 as CALIBRE_C2',
                'CalibreComb32 as CALIBRE_C3',
                'CalibreComb42 as CALIBRE_C4',
                'CalibreComb52 as CALIBRE_C5'
            ])
            ->first();

        // Si no hay datos en proceso, crear objeto vacío
        if (!$telarEnProceso) {
            $telarEnProceso = (object) [
                'Telar' => $telar,
                'en_proceso' => false
            ];
        }

        // Obtener la siguiente orden programada (basada en FechaInicio, no FechaFinal)
        $ordenSig = null;
        if ($telarEnProceso && isset($telarEnProceso->Inicio_Tejido)) {
            $ordenSig = DB::table('ReqProgramaTejido')
                ->where('SalonTejidoId', $tipoSalon)
                ->where('NoTelarId', $telar)
                ->where('EnProceso', 0)
                ->where('FechaInicio', '>', $telarEnProceso->Inicio_Tejido)
                ->select([
                    'NoTelarId as Telar',
                    'NoProduccion as Orden_Prod',
                    'ItemId as ItemId',
                    'TamanoClave as Tamano_AX',
                    'NombreProducto as Nombre_Producto',
                    'CuentaRizo as Cuenta',
                    'CalibreRizo as Calibre_Rizo',
                    'FibraRizo as Fibra_Rizo',
                    'CuentaPie as Cuenta_Pie',
                    'CalibrePie as Calibre_Pie',
                    'FibraPie as Fibra_Pie',
                    'TotalPedido as Saldos',
                    'FechaInicio as Inicio_Tejido',
                    'EntregaCte as Entrega'
                ])
                ->orderBy('FechaInicio')
                ->first();
        }

        $datos = collect([$telarEnProceso]);
        $tipo = strtolower($tipoSalon);

        return view('modulos/tejido/telares/telar-informacion-individual', compact('telar', 'datos', 'ordenSig', 'tipo'));
    }

    /**
     * Determinar el tipo de salón según el número de telar
     */
    private function determinarTipoSalon($telar)
    {
        // Jacquard: 201-215
        if ($telar >= 201 && $telar <= 215) {
            return 'JACQUARD';
        }
        // Itema: 299-320
        if ($telar >= 299 && $telar <= 320) {
            return 'ITEMA';
        }
        // Karl Mayer: 303-306 (ajustar según tu configuración)
        if ($telar >= 303 && $telar <= 306) {
            return 'KARL MAYER';
        }

        // Por defecto
        return 'JACQUARD';
    }

    /**
     * Obtener órdenes programadas para un telar específico
     */
    public function obtenerOrdenesProgramadas($telar)
    {
        $tipoSalon = $this->determinarTipoSalon($telar);

        // Obtener todas las órdenes del telar ordenadas por fecha de inicio
        $ordenes = DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $telar)
            ->select([
                'NoTelarId as Telar',
                'EnProceso as en_proceso',
                'FechaInicio as Inicio_Tejido',
                'NombreProducto as Producto',
                'CustName as Cliente',
                'SaldoPedido as Cantidad',
                DB::raw("CASE WHEN EnProceso = 1 THEN 'En Proceso' ELSE 'Programado' END as Estado"),
                'NoProduccion as Orden_Prod',
                'EntregaCte as Entrega'
            ])
            ->orderBy('FechaInicio')
            ->get();

        return view('modulos/tejido/telares/ordenes-programadas', compact('ordenes', 'telar'));
    }

    /**
     * Mostrar inventario de telares Jacquard ordenados por secuencia
     * Obtiene datos reales desde ReqProgramaTejido
     */
    public function inventarioJacquard()
    {
        // Orden correcto de telares Jacquard según InvSecuenciaTelares
        $telaresOrdenados = [202, 201, 204, 203, 206, 205, 208, 207, 210, 209, 211, 215, 213, 214];

        // Obtener datos completos de todos los telares Jacquard
        $datosTelaresCompletos = [];

        foreach ($telaresOrdenados as $numeroTelar) {
            // Obtener datos del telar en proceso
            $telarEnProceso = DB::table('ReqProgramaTejido')
                ->where('SalonTejidoId', 'JACQUARD')
                ->where('NoTelarId', $numeroTelar)
                ->where('EnProceso', 1)
                ->select([
                    'NoTelarId as Telar',
                    'EnProceso as en_proceso',
                    'NoProduccion as Orden_Prod',
                    'FlogsId as Id_Flog',
                    'CustName as Cliente',
                    'NoTiras as Tiras',
                    'TamanoClave as Tamano_AX',
                    'ItemId as ItemId',
                    'NombreProducto as Nombre_Producto',
                    'CuentaRizo as Cuenta',
                    'CalibreRizo as Calibre_Rizo',
                    'FibraRizo as Fibra_Rizo',
                    'CuentaPie as Cuenta_Pie',
                    'CalibrePie as Calibre_Pie',
                    'FibraPie as Fibra_Pie',
                    'CalibreTrama as CALIBRE_TRA',
                    'ColorTrama as COLOR_TRAMA',
                    'TotalPedido as Saldos',
                    'Produccion as Prod_Kg_Dia',
                    'SaldoMarbete as Marbetes_Pend',
                    'SaldoMarbete as MarbetesPend',
                    'FechaInicio as Inicio_Tejido',
                    'FechaFinal as Fin_Tejido',
                    'EntregaCte as Fecha_Compromiso',
                    DB::raw('0 as Total_Paros'),
                    DB::raw('NULL as Tiempo_Paro'),
                    // Campos adicionales para mostrar información completa
                    'PasadasTrama as PASADAS_TRAMA',
                    'NombreCC1 as COLOR_C1',
                    'NombreCC2 as COLOR_C2',
                    'NombreCC3 as COLOR_C3',
                    'NombreCC4 as COLOR_C4',
                    'NombreCC5 as COLOR_C5',
                    'CalibreComb12 as CALIBRE_C1',
                    'CalibreComb22 as CALIBRE_C2',
                    'CalibreComb32 as CALIBRE_C3',
                    'CalibreComb42 as CALIBRE_C4',
                    'CalibreComb52 as CALIBRE_C5'
                ])
                ->first();

            // Si no hay datos en proceso, crear objeto vacío
            if (!$telarEnProceso) {
                $telarEnProceso = (object) [
                    'Telar' => $numeroTelar,
                    'en_proceso' => false
                ];
                $ordenSig = null;
            } else {
                // Obtener la siguiente orden programada (basada en FechaInicio, no FechaFinal)
                $ordenSig = DB::table('ReqProgramaTejido')
                    ->where('SalonTejidoId', 'JACQUARD')
                    ->where('NoTelarId', $numeroTelar)
                    ->where('EnProceso', 0)
                    ->where('FechaInicio', '>', $telarEnProceso->Inicio_Tejido)
                    ->select([
                        'NoTelarId as Telar',
                        'NoProduccion as Orden_Prod',
                        'ItemId as ItemId',
                        'TamanoClave as Tamano_AX',
                        'NombreProducto as Nombre_Producto',
                        'CuentaRizo as Cuenta',
                        'CalibreRizo as Calibre_Rizo',
                        'FibraRizo as Fibra_Rizo',
                        'CuentaPie as Cuenta_Pie',
                        'CalibrePie as Calibre_Pie',
                        'FibraPie as Fibra_Pie',
                        'TotalPedido as Saldos',
                        'FechaInicio as Inicio_Tejido',
                        'EntregaCte as Entrega'
                    ])
                    ->orderBy('FechaInicio')
                    ->first();
            }

            $datosTelaresCompletos[$numeroTelar] = [
                'telarData' => $telarEnProceso,
                'ordenSig' => $ordenSig
            ];
        }

        return view('modulos/tejido/inventario-telas/jacquard', [
            'telaresJacquard' => $telaresOrdenados,
            'datosTelaresCompletos' => $datosTelaresCompletos
        ]);
    }

    /**
     * Mostrar inventario de telares Itema
     * Obtiene datos reales desde ReqProgramaTejido y ordena según InvSecuenciaTelares
     */
    public function inventarioItema()
    {
        // Primero obtener solo los telares que están en proceso (EnProceso = 1)
        $telaresEnProceso = DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', 'ITEMA')
            ->where('EnProceso', 1)
            ->pluck('NoTelarId')
            ->toArray();

        // Verificar si hay telares en proceso
        if (empty($telaresEnProceso)) {
            // Si no hay telares en proceso, verificar si hay telares futuros
            $telaresFuturos = DB::table('ReqProgramaTejido')
                ->where('SalonTejidoId', 'ITEMA')
                ->where('EnProceso', 0)
                ->pluck('NoTelarId')
                ->unique()
                ->toArray();

            if (empty($telaresFuturos)) {
                // No hay telares en proceso ni futuros
                $telaresOrdenados = [];
            } else {
                // Hay telares futuros, mostrar la secuencia completa para mostrar mensaje
                $telaresOrdenados = DB::table('InvSecuenciaTelares')
                    ->where('TipoTelar', 'ITEMA')
                    ->orderBy('Secuencia')
                    ->pluck('NoTelar')
                    ->toArray();
            }
        } else {
            // Ordenar los telares en proceso según la secuencia de InvSecuenciaTelares
            $telaresOrdenados = DB::table('InvSecuenciaTelares')
                ->where('TipoTelar', 'ITEMA')
                ->whereIn('NoTelar', $telaresEnProceso)
                ->orderBy('Secuencia')
                ->pluck('NoTelar')
                ->toArray();
        }

        // Convertir a enteros para consistencia
        $telaresOrdenados = array_map('intval', $telaresOrdenados);

        // Obtener datos completos de los telares Itema
        $datosTelaresCompletos = [];

        foreach ($telaresOrdenados as $numeroTelar) {
            // Obtener datos del telar en proceso
            $telarEnProceso = DB::table('ReqProgramaTejido')
                ->where('SalonTejidoId', 'ITEMA')
                ->where('NoTelarId', $numeroTelar)
                ->where('EnProceso', 1)
                ->select([
                    'NoTelarId as Telar',
                    'EnProceso as en_proceso',
                    'NoProduccion as Orden_Prod',
                    'FlogsId as Id_Flog',
                    'CustName as Cliente',
                    'NoTiras as Tiras',
                    'TamanoClave as Tamano_AX',
                    'ItemId as ItemId',
                    'NombreProducto as Nombre_Producto',
                    'CuentaRizo as Cuenta',
                    'CalibreRizo as Calibre_Rizo',
                    'FibraRizo as Fibra_Rizo',
                    'CuentaPie as Cuenta_Pie',
                    'CalibrePie as Calibre_Pie',
                    'FibraPie as Fibra_Pie',
                    'CalibreTrama as CALIBRE_TRA',
                    'ColorTrama as COLOR_TRAMA',
                    'TotalPedido as Saldos',
                    'Produccion as Prod_Kg_Dia',
                    'SaldoMarbete as Marbetes_Pend',
                    'SaldoMarbete as MarbetesPend',
                    'FechaInicio as Inicio_Tejido',
                    'FechaFinal as Fin_Tejido',
                    'EntregaCte as Fecha_Compromiso',
                    DB::raw('0 as Total_Paros'),
                    DB::raw('NULL as Tiempo_Paro'),
                    // Campos adicionales para mostrar información completa
                    'PasadasTrama as PASADAS_TRAMA',
                    'NombreCC1 as COLOR_C1',
                    'NombreCC2 as COLOR_C2',
                    'NombreCC3 as COLOR_C3',
                    'NombreCC4 as COLOR_C4',
                    'NombreCC5 as COLOR_C5',
                    'CalibreComb12 as CALIBRE_C1',
                    'CalibreComb22 as CALIBRE_C2',
                    'CalibreComb32 as CALIBRE_C3',
                    'CalibreComb42 as CALIBRE_C4',
                    'CalibreComb52 as CALIBRE_C5',
                    'FibraComb1 as FIBRA_C1',
                    'FibraComb2 as FIBRA_C2',
                    'FibraComb3 as FIBRA_C3',
                    'FibraComb4 as FIBRA_C4',
                    'FibraComb5 as FIBRA_C5'
                ])
                ->first();

            // Obtener siguiente orden
            $ordenSig = null;
            if ($telarEnProceso) {
                $ordenSig = DB::table('ReqProgramaTejido')
                    ->where('SalonTejidoId', 'ITEMA')
                    ->where('NoTelarId', $numeroTelar)
                    ->where('EnProceso', 0)
                    ->where('FechaInicio', '>', $telarEnProceso->Inicio_Tejido)
                    ->select([
                        'NoTelarId as Telar',
                        'NoProduccion as Orden_Prod',
                        'ItemId as ItemId',
                        'TamanoClave as Tamano_AX',
                        'NombreProducto as Nombre_Producto',
                        'CuentaRizo as Cuenta',
                        'CalibreRizo as Calibre_Rizo',
                        'FibraRizo as Fibra_Rizo',
                        'CuentaPie as Cuenta_Pie',
                        'CalibrePie as Calibre_Pie',
                        'FibraPie as Fibra_Pie',
                        'TotalPedido as Saldos',
                        'FechaInicio as Inicio_Tejido',
                        'EntregaCte as Entrega'
                    ])
                    ->orderBy('FechaInicio')
                    ->first();
            }

            // Si no hay telar en proceso, crear objeto vacío
            if (!$telarEnProceso) {
                $telarEnProceso = (object) [
                    'Telar' => $numeroTelar,
                    'en_proceso' => false,
                    'Orden_Prod' => null,
                    'Id_Flog' => null,
                    'Cliente' => null,
                    'Tiras' => null,
                    'Tamano_AX' => null,
                    'ItemId' => null,
                    'Nombre_Producto' => null,
                    'Cuenta' => null,
                    'Calibre_Rizo' => null,
                    'Fibra_Rizo' => null,
                    'Cuenta_Pie' => null,
                    'Calibre_Pie' => null,
                    'Fibra_Pie' => null,
                    'CALIBRE_TRA' => null,
                    'COLOR_TRAMA' => null,
                    'Saldos' => null,
                    'Prod_Kg_Dia' => null,
                    'Marbetes_Pend' => null,
                    'MarbetesPend' => null,
                    'Inicio_Tejido' => null,
                    'Fin_Tejido' => null,
                    'Fecha_Compromiso' => null,
                    'Total_Paros' => 0,
                    'Tiempo_Paro' => null
                ];
            }

            $datosTelaresCompletos[$numeroTelar] = [
                'telarData' => $telarEnProceso,
                'ordenSig' => $ordenSig
            ];
        }

        return view('modulos/tejido/inventario-telas/itema', [
            'telaresItema' => $telaresOrdenados,
            'datosTelaresCompletos' => $datosTelaresCompletos
        ]);
    }
}
