<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoCatalogosController.php
 * @description Controlador de catálogos para Programa Tejido. Endpoints de opciones (salón, telar,
 *              calendario, aplicación, hilos), búsquedas (FlogsId, tamano_clave) y datos relacionados.
 * @dependencies ReqProgramaTejido, ReqModelosCodificados, ReqAplicaciones, ReqCalendarioLine, QueryHelpers
 */
class ProgramaTejidoCatalogosController extends Controller
{
    public function getSalonTejidoOptions()
    {
        $programa = ReqProgramaTejido::query()
            ->select('SalonTejidoId')
            ->whereNotNull('SalonTejidoId')
            ->distinct()
            ->pluck('SalonTejidoId');

        $modelos  = ReqModelosCodificados::query()
            ->select('SalonTejidoId')
            ->whereNotNull('SalonTejidoId')
            ->distinct()
            ->pluck('SalonTejidoId');

        return response()->json(
            $programa->merge($modelos)->filter()->unique()->sort()->values()
        );
    }

    public function getTamanoClaveBySalon(Request $request)
    {
        $salon  = $request->input('salon_tejido_id');
        $search = $request->input('search', '');

        $q = ReqModelosCodificados::query()
            ->select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave', '!=', '');

        if ($salon) {
            $q->where('SalonTejidoId', $salon);
        }
        if ($search) {
            $q->where('TamanoClave', 'LIKE', "%{$search}%");
        }

        $op = $q->distinct()->limit(50)->pluck('TamanoClave')->filter()->values();
        return response()->json($op);
    }

    public function getFlogsIdOptions()
    {
        $a = ReqProgramaTejido::query()
            ->select('FlogsId')
            ->whereNotNull('FlogsId')
            ->distinct()
            ->pluck('FlogsId');

        $b = ReqModelosCodificados::query()
            ->select('FlogsId')
            ->whereNotNull('FlogsId')
            ->distinct()
            ->pluck('FlogsId');

        return response()->json(
            $a->merge($b)->filter()->unique()->sort()->values()
        );
    }

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
            return response()->json(['error' => 'Error al cargar opciones de FlogsId: ' . $e->getMessage()], 500);
        }
    }

    public function getDescripcionByIdFlog($idflog)
    {
        try {
            $row = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable as ft')
                ->select('ft.NAMEPROYECT as NombreProyecto', 'ft.CUSTNAME as CustName')
                ->where('ft.IDFLOG', trim((string)$idflog))
                ->first();

            $nombreProyecto = $row ? trim((string)($row->NombreProyecto ?? '')) : '';
            $custName = $row ? trim((string)($row->CustName ?? '')) : '';

            return response()->json([
                'nombreProyecto' => $nombreProyecto,
                'custName' => $custName
            ]);
        } catch (\Throwable $e) {
            LogFacade::error('getDescripcionByIdFlog', ['idflog' => $idflog, 'msg' => $e->getMessage()]);
            return response()->json(['nombreProyecto' => ''], 500);
        }
    }

    public function getFlogByItem(Request $request)
    {
        $hasItemId = $request->has('item_id') && $request->has('invent_size_id');
        $hasTamanoClave = $request->has('tamano_clave') && $request->has('salon_tejido_id');

        if (!$hasItemId && !$hasTamanoClave) {
            return response()->json([
                'error' => 'Se requiere item_id e invent_size_id, o tamano_clave y salon_tejido_id'
            ], 400);
        }

        $itemId = null;
        $inventSizeId = null;

        if ($hasTamanoClave) {
            $tamanoClave = trim((string) $request->input('tamano_clave'));
            $salonTejidoId = trim((string) $request->input('salon_tejido_id'));

            $modelo = ReqModelosCodificados::where('SalonTejidoId', $salonTejidoId)
                ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tamanoClave)])
                ->select('ItemId', 'InventSizeId')
                ->first();

            if (!$modelo) {
                $modelo = ReqModelosCodificados::where('SalonTejidoId', $salonTejidoId)
                    ->whereRaw('UPPER(TamanoClave) like ?', [strtoupper($tamanoClave) . '%'])
                    ->select('ItemId', 'InventSizeId')
                    ->first();
            }

            if ($modelo && $modelo->ItemId && $modelo->InventSizeId) {
                $itemId = trim((string) $modelo->ItemId);
                $inventSizeId = trim((string) $modelo->InventSizeId);
            } else {
                return response()->json([
                    'idflog' => null,
                    'nombreProyecto' => '',
                    'error' => 'No se encontró ItemId e InventSizeId para la clave modelo proporcionada'
                ]);
            }
        } else {
            $itemId = trim((string) $request->input('item_id'));
            $inventSizeId = trim((string) $request->input('invent_size_id'));
        }

        try {
            $rows = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsItemLine as fil')
                ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
                ->select('ft.IDFLOG as IdFlog', 'ft.NAMEPROYECT as NombreProyecto', 'ft.CUSTNAME as CustName')
                ->whereRaw('LTRIM(RTRIM(fil.ITEMID)) = ?', [$itemId])
                ->whereRaw('LTRIM(RTRIM(fil.INVENTSIZEID)) = ?', [$inventSizeId])
                ->whereIn('ft.ESTADOFLOG', [3, 4, 5, 21])
                ->orderByDesc('ft.IDFLOG')
                ->get();

            $row = $rows->sortByDesc(function ($item) {
                $idflog = trim((string)($item->IdFlog ?? ''));
                if (preg_match('/(\d+)$/', $idflog, $matches)) {
                    return (int)$matches[1];
                }
                return 0;
            })->first();

            $idflog = $row ? trim((string)($row->IdFlog ?? '')) : null;
            $nombreProyecto = $row ? trim((string)($row->NombreProyecto ?? '')) : '';
            $custName = $row ? trim((string)($row->CustName ?? '')) : '';

            return response()->json([
                'idflog' => $idflog,
                'nombreProyecto' => $nombreProyecto,
                'custName' => $custName,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['idflog' => null, 'nombreProyecto' => '', 'custName' => ''], 500);
        }
    }

    public function getFlogsByTamanoClave(Request $request)
    {
        $request->validate([
            'tamano_clave' => 'required|string',
        ]);

        $tamanoClave = trim((string) $request->input('tamano_clave'));
        $salonTejidoId = $request->input('salon_tejido_id');

        try {
            $query = ReqModelosCodificados::whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tamanoClave)])
                ->select('ItemId', 'InventSizeId', 'SalonTejidoId')
                ->whereNotNull('ItemId')
                ->whereNotNull('InventSizeId')
                ->where('ItemId', '!=', '')
                ->where('InventSizeId', '!=', '');

            if ($salonTejidoId) {
                $query->where('SalonTejidoId', trim((string) $salonTejidoId));
            }

            $modelos = $query->get();

            if ($modelos->isEmpty()) {
                $queryLike = ReqModelosCodificados::whereRaw('UPPER(TamanoClave) like ?', [strtoupper($tamanoClave) . '%'])
                    ->select('ItemId', 'InventSizeId', 'SalonTejidoId')
                    ->whereNotNull('ItemId')
                    ->whereNotNull('InventSizeId')
                    ->where('ItemId', '!=', '')
                    ->where('InventSizeId', '!=', '');

                if ($salonTejidoId) {
                    $queryLike->where('SalonTejidoId', trim((string) $salonTejidoId));
                }

                $modelos = $queryLike->get();
            }

            if ($modelos->isEmpty()) {
                return response()->json([]);
            }

            $items = $modelos->map(function ($m) {
                return [
                    'itemId' => trim((string) $m->ItemId),
                    'inventSizeId' => trim((string) $m->InventSizeId),
                ];
            })->unique(function ($item) {
                return $item['itemId'] . '|' . $item['inventSizeId'];
            })->values();

            $allFlogs = collect();
            foreach ($items as $item) {
                $flogs = DBFacade::connection('sqlsrv_ti')
                    ->table('dbo.TwFlogsItemLine as il')
                    ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'il.IDFLOG')
                    ->select('il.IDFLOG as IdFlog', 'ft.NAMEPROYECT as NombreProyecto', 'ft.CUSTNAME as CustName')
                    ->whereRaw('LTRIM(RTRIM(il.ITEMID)) = ?', [$item['itemId']])
                    ->whereRaw('LTRIM(RTRIM(il.INVENTSIZEID)) = ?', [$item['inventSizeId']])
                    ->whereIn('ft.ESTADOFLOG', [3, 4, 5, 21])
                    ->orderByDesc('ft.IDFLOG')
                    ->get();

                $allFlogs = $allFlogs->merge($flogs);
            }

            $result = $allFlogs->unique('IdFlog')
                ->map(function ($row) {
                    return [
                        'idflog' => $row->IdFlog ?? null,
                        'nombreProyecto' => $row->NombreProyecto ?? '',
                        'custName' => $row->CustName ?? '',
                    ];
                })
                ->filter(function ($item) {
                    return !empty($item['idflog']);
                })
                ->sortByDesc('idflog')
                ->values();

            return response()->json($result);
        } catch (\Throwable $e) {
            LogFacade::error('getFlogsByTamanoClave', [
                'tamano_clave' => $tamanoClave,
                'salon_tejido_id' => $salonTejidoId,
                'msg' => $e->getMessage()
            ]);
            return response()->json([], 500);
        }
    }

    public function getCalendarioIdOptions()
    {
        $op = QueryHelpers::pluckDistinctNonEmpty('ReqCalendarioTab', 'CalendarioId');
        return response()->json($op);
    }

    public function getCalendarioLineas($calendarioId)
    {
        try {
            $lineas = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->select(['Id', 'CalendarioId', 'FechaInicio', 'FechaFin', 'HorasTurno', 'Turno'])
                ->orderBy('FechaInicio')
                ->get()
                ->map(function ($linea) {
                    return [
                        'Id' => $linea->Id,
                        'CalendarioId' => $linea->CalendarioId,
                        'FechaInicio' => $linea->FechaInicio ? $linea->FechaInicio->format('Y-m-d H:i:s') : null,
                        'FechaFin' => $linea->FechaFin ? $linea->FechaFin->format('Y-m-d H:i:s') : null,
                        'HorasTurno' => $linea->HorasTurno,
                        'Turno' => $linea->Turno
                    ];
                });

            return response()->json([
                'success' => true,
                'lineas' => $lineas
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener líneas del calendario'], 500);
        }
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
            return response()->json(['error' => 'Error al cargar opciones de aplicación: ' . $e->getMessage()]);
        }
    }

    public function getDatosRelacionados(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            $tamRaw = $request->input('tamano_clave');
            $tam   = $tamRaw ? trim($tamRaw) : null;
            if ($tam) {
                $tam = preg_replace('/\s+/', ' ', $tam);
            }

            if (!$salon) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            $selectCols = [
                'TamanoClave',
                'SalonTejidoId',
                'FlogsId',
                'NombreProyecto',
                'InventSizeId',
                'ItemId',
                'Nombre',
                'VelocidadSTD',
                'AnchoToalla',
                'CuentaPie',
                'MedidaPlano',
                'PesoCrudo',
                'NoTiras',
                'Luchaje',
                'Repeticiones',
                'Total',
                'CalibreTrama',
                'CalibreTrama2',
                'FibraId',
                'FibraRizo',
                'CalibreRizo',
                'CalibreRizo2',
                'CuentaRizo',
                'CalibrePie',
                'CalibrePie2',
                'Peine',
                'Rasurado',
                'CodColorTrama',
                'ColorTrama',
                'DobladilloId',
                'PasadasTramaFondoC1',
                'FibraTramaFondoC1',
                'PasadasComb1',
                'PasadasComb2',
                'PasadasComb3',
                'PasadasComb4',
                'PasadasComb5',
                'CalibreComb1',
                'CalibreComb12',
                'FibraComb1',
                'CodColorC1',
                'NomColorC1',
                'CalibreComb2',
                'CalibreComb22',
                'FibraComb2',
                'CodColorC2',
                'NomColorC2',
                'CalibreComb3',
                'CalibreComb32',
                'FibraComb3',
                'CodColorC3',
                'NomColorC3',
                'CalibreComb4',
                'CalibreComb42',
                'FibraComb4',
                'CodColorC4',
                'NomColorC4',
                'CalibreComb5',
                'CalibreComb52',
                'FibraComb5',
                'CodColorC5',
                'NomColorC5'
            ];

            if ($tam) {
                $datos = TejidoHelpers::obtenerModeloPorTamanoClave($tam, $salon, $selectCols);
            } else {
                $datos = ReqModelosCodificados::where('SalonTejidoId', $salon)->select($selectCols)->first();
            }

            if (!$datos) {
                return response()->json(['datos' => null]);
            }

            $datosMapeados = [
                'TamanoClave' => $datos->TamanoClave ?? null,
                'SalonTejidoId' => $datos->SalonTejidoId ?? null,
                'FlogsId' => $datos->FlogsId ?? null,
                'NombreProyecto' => $datos->NombreProyecto ?? null,
                'InventSizeId' => $datos->InventSizeId ?? null,
                'ItemId' => $datos->ItemId ?? null,
                'Nombre' => $datos->Nombre ?? null,
                'NombreProducto' => $datos->Nombre ?? null,
                'VelocidadSTD' => $datos->VelocidadSTD ?? null,
                'AnchoToalla' => $datos->AnchoToalla ?? null,
                'CuentaPie' => $datos->CuentaPie ?? null,
                'MedidaPlano' => $datos->MedidaPlano ?? null,
                'PesoCrudo' => $datos->PesoCrudo ?? null,
                'NoTiras' => $datos->NoTiras ?? null,
                'Luchaje' => $datos->Luchaje ?? null,
                'Repeticiones' => $datos->Repeticiones ?? null,
                'Total' => $datos->Total ?? null,
                'CalibreTrama' => $datos->CalibreTrama ?? null,
                'CalibreTrama2' => $datos->CalibreTrama2 ?? null,
                'FibraId' => $datos->FibraId ?? null,
                'FibraRizo' => $datos->FibraRizo ?? null,
                'CalibreRizo' => $datos->CalibreRizo ?? null,
                'CalibreRizo2' => $datos->CalibreRizo2 ?? null,
                'CuentaRizo' => $datos->CuentaRizo ?? null,
                'CalibrePie' => $datos->CalibrePie ?? null,
                'CalibrePie2' => $datos->CalibrePie2 ?? null,
                'Peine' => $datos->Peine ?? null,
                'Rasurado' => $datos->Rasurado ?? null,
                'Ancho' => $datos->AnchoToalla ?? null,
                'CodColorTrama' => $datos->CodColorTrama ?? null,
                'ColorTrama' => $datos->ColorTrama ?? null,
                'DobladilloId' => $datos->DobladilloId ?? null,
                'PasadasTrama' => $datos->PasadasTramaFondoC1 ?? null,
                'FibraTrama' => $datos->FibraTramaFondoC1 ?? null,
                'PasadasComb1' => $datos->PasadasComb1 ?? null,
                'PasadasComb2' => $datos->PasadasComb2 ?? null,
                'PasadasComb3' => $datos->PasadasComb3 ?? null,
                'PasadasComb4' => $datos->PasadasComb4 ?? null,
                'PasadasComb5' => $datos->PasadasComb5 ?? null,
                'CalibreComb1' => $datos->CalibreComb1 ?? null,
                'CalibreComb12' => $datos->CalibreComb12 ?? null,
                'FibraComb1' => $datos->FibraComb1 ?? null,
                'CodColorComb1' => $datos->CodColorC1 ?? null,
                'NombreCC1' => $datos->NomColorC1 ?? null,
                'CalibreComb2' => $datos->CalibreComb2 ?? null,
                'CalibreComb22' => $datos->CalibreComb22 ?? null,
                'FibraComb2' => $datos->FibraComb2 ?? null,
                'CodColorComb2' => $datos->CodColorC2 ?? null,
                'NombreCC2' => $datos->NomColorC2 ?? null,
                'CalibreComb3' => $datos->CalibreComb3 ?? null,
                'CalibreComb32' => $datos->CalibreComb32 ?? null,
                'FibraComb3' => $datos->FibraComb3 ?? null,
                'CodColorComb3' => $datos->CodColorC3 ?? null,
                'NombreCC3' => $datos->NomColorC3 ?? null,
                'CalibreComb4' => $datos->CalibreComb4 ?? null,
                'CalibreComb42' => $datos->CalibreComb42 ?? null,
                'FibraComb4' => $datos->FibraComb4 ?? null,
                'CodColorComb4' => $datos->CodColorC4 ?? null,
                'NombreCC4' => $datos->NomColorC4 ?? null,
                'CalibreComb5' => $datos->CalibreComb5 ?? null,
                'CalibreComb52' => $datos->CalibreComb52 ?? null,
                'FibraComb5' => $datos->FibraComb5 ?? null,
                'CodColorComb5' => $datos->CodColorC5 ?? null,
                'NombreCC5' => $datos->NomColorC5 ?? null,
            ];

            LogFacade::info('getDatosRelacionados: Datos mapeados enviados al frontend', [
                'tamano_clave' => $tam,
                'salon' => $salon,
                'datos_mapeados' => $datosMapeados,
                'campos_principales' => [
                    'CuentaRizo' => $datosMapeados['CuentaRizo'] ?? null,
                    'CalibreRizo' => $datosMapeados['CalibreRizo'] ?? null,
                    'FibraRizo' => $datosMapeados['FibraRizo'] ?? null,
                    'NoTiras' => $datosMapeados['NoTiras'] ?? null,
                    'Peine' => $datosMapeados['Peine'] ?? null,
                    'Luchaje' => $datosMapeados['Luchaje'] ?? null,
                    'PesoCrudo' => $datosMapeados['PesoCrudo'] ?? null,
                    'TipoPedido' => $datosMapeados['TipoPedido'] ?? null
                ]
            ]);

            return response()->json(['datos' => (object)$datosMapeados]);
        } catch (\Throwable $e) {
            LogFacade::error('Error en getDatosRelacionados', [
                'salon' => $salon,
                'tamano_clave' => $tam,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al obtener datos: ' . $e->getMessage(),
                'salon' => $salon,
                'tamano_clave' => $tam
            ], 500);
        }
    }

    public function getEficienciaStd(Request $request)
    {
        return QueryHelpers::getStdValue('ReqEficienciaStd', 'Eficiencia', 'eficiencia', $request);
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
                'error' => 'Faltan parámetros requeridos'
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
                'error' => 'Error al obtener eficiencia y velocidad estándar'
            ], 500);
        }
    }

    public function getTelaresBySalon(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            if (!$salon) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            $telares = ReqProgramaTejido::query()
                ->salon($salon)
                ->whereNotNull('NoTelarId')
                ->distinct()
                ->orderBy('NoTelarId')
                ->pluck('NoTelarId')
                ->values()
                ->toArray();

            return response()->json($telares);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener telares: ' . $e->getMessage()], 500);
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
                'value' => trim($p->SalonTejidoId) . '|' . trim($p->NoTelarId),
                'label' => trim($p->NoTelarId),
            ])->values()->toArray();

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener telares: ' . $e->getMessage()], 500);
        }
    }

    public function getUltimaFechaFinalTelar(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            $telar = $request->input('no_telar_id');
            if (!$salon || !$telar) {
                return response()->json(['error' => 'SalonTejidoId y NoTelarId son requeridos'], 400);
            }

            $ultimo = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->whereNotNull('FechaFinal')
                ->orderByDesc('FechaFinal')
                ->select('Id', 'FechaFinal', 'FibraRizo', 'Maquina', 'Ancho')
                ->first();

            return response()->json([
                'ultima_fecha_final' => $ultimo->FechaFinal ?? null,
                'hilo' => $ultimo->FibraRizo ?? null,
                'maquina' => $ultimo->Maquina ?? null,
                'ancho' => $ultimo->Ancho ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener última fecha final: ' . $e->getMessage()], 500);
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
            return response()->json(['error' => 'Error al cargar opciones de hilos: ' . $e->getMessage()], 500);
        }
    }
}
