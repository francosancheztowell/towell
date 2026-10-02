<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoCatalogosController.php
 *
 * @description Controlador de catálogos para Programa Tejido. Endpoints de opciones (salón, telar,
 *              calendario, aplicación, hilos), FlogsId y estándares de eficiencia/velocidad.
 *
 * @dependencies ReqProgramaTejido, ReqAplicaciones, QueryHelpers
 */
class ProgramaTejidoCatalogosController extends Controller
{
    public function getFlogsIdFromTwFlogsTable()
    {
        try {
            $op = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable as ft')
                ->select('ft.IDFLOG')
                ->whereIn('ft.EstadoFlog', [3, 4, 5, 21])
                ->whereNotNull('ft.IDFLOG')
                ->distinct()
                ->orderBy('ft.IDFLOG')
                ->pluck('IDFLOG')
                ->filter()
                ->values();

            return response()->json($op);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al cargar opciones de FlogsId: '.$e->getMessage()], 500);
        }
    }

    public function getCalendarioIdOptions()
    {
        $op = QueryHelpers::pluckDistinctNonEmpty('ReqCalendarioTab', 'CalendarioId');

        return response()->json($op);
    }

    public function getAplicacionIdOptions()
    {
        try {
            $op = ReqAplicaciones::query()
                ->select('AplicacionId')
                ->whereNotNull('AplicacionId')
                ->where('AplicacionId', '!=', '')
                ->orderBy('AplicacionId')
                ->pluck('AplicacionId')
                ->filter()
                ->values();

            if ($op->isEmpty()) {
                return response()->json(['mensaje' => 'No se encontraron opciones de aplicación disponibles']);
            }

            return response()->json($op);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al cargar opciones de aplicación: '.$e->getMessage()]);
        }
    }

    public function getVelocidadStd(Request $request)
    {
        return QueryHelpers::getStdValue('ReqVelocidadStd', 'Velocidad', 'velocidad', $request);
    }

    public function getEficienciaVelocidadStd(Request $request)
    {
        $fibraId = $request->input('fibra_id');
        $noTelar = $request->input('no_telar_id');
        $calTra = $request->input('calibre_trama');

        if ($fibraId === null || $noTelar === null || $calTra === null) {
            return response()->json([
                'eficiencia' => null,
                'velocidad' => null,
                'error' => 'Faltan parámetros requeridos',
            ], 400);
        }

        try {
            $result = QueryHelpers::getEficienciaVelocidadStd($fibraId, $noTelar, (float) $calTra);

            return response()->json($result);
        } catch (\Throwable $e) {
            LogFacade::error('getEficienciaVelocidadStd error', ['msg' => $e->getMessage()]);

            return response()->json([
                'eficiencia' => null,
                'velocidad' => null,
                'error' => 'Error al obtener eficiencia y velocidad estándar',
            ], 500);
        }
    }

    public function getTelaresAll()
    {
        try {
            $pares = ReqProgramaTejido::query()
                ->select('SalonTejidoId', 'NoTelarId')
                ->whereNotNull('SalonTejidoId')
                ->whereNotNull('NoTelarId')
                ->where('NoTelarId', '!=', '')
                ->distinct()
                ->orderBy('SalonTejidoId')
                ->orderBy('NoTelarId')
                ->get();

            $result = $pares->map(fn ($p) => [
                'value' => trim($p->SalonTejidoId).'|'.trim($p->NoTelarId),
                'label' => trim($p->NoTelarId),
            ])->values()->toArray();

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener telares: '.$e->getMessage()], 500);
        }
    }

    public function getHilosOptions()
    {
        try {
            $op = ReqMatrizHilos::query()
                ->whereNotNull('Hilo')
                ->where('Hilo', '!=', '')
                ->distinct()
                ->pluck('Hilo')
                ->sort()
                ->values()
                ->toArray();

            return response()->json($op);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al cargar opciones de hilos: '.$e->getMessage()], 500);
        }
    }
}
