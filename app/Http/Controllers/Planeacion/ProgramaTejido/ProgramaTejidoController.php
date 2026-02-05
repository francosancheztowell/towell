<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DragAndDropTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EditTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DuplicarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DividirTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatCalendarios\CalendarioController;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqMatrizHilos;
/**
 * Controlador para gestionar el programa de tejido
 */
class ProgramaTejidoController extends Controller
{
    /**
     * INDEX
     */
    public function index()
    {
        try {
            $isMuestras = request()->is('planeacion/muestras');
            $basePath = $isMuestras ? '/planeacion/muestras' : '/planeacion/programa-tejido';
            $apiPath = $isMuestras ? '/muestras' : '/programa-tejido';
            $linePath = $isMuestras ? '/planeacion/muestras-line' : '/planeacion/req-programa-tejido-line';
            $pageTitle = $isMuestras ? 'Muestras' : 'Programa Tejido';

            $registros = ReqProgramaTejido::select([
                'Id',
                'EnProceso',
                'Reprogramar',
                'CuentaRizo',
                'CalibreRizo2',
                'SalonTejidoId',
                'NoTelarId',
                'Posicion',
                'Ultimo',
                'CambioHilo',
                'Maquina',
                'Ancho',
                'EficienciaSTD',
                'VelocidadSTD',
                'FibraRizo',
                'CalibrePie2',
                'CalendarioId',
                'TamanoClave',
                'NoExisteBase',
                'ItemId',
                'InventSizeId',
                'Rasurado',
                'NombreProducto',
                'TotalPedido',
                'PorcentajeSegundos',
                'Produccion',
                'SaldoPedido',
                'SaldoMarbete',
                'ProgramarProd',
                'OrdCompartida',
                'NoProduccion',
                'Programado',
                'FlogsId',
                'CategoriaCalidad',
                'NombreProyecto',
                'CustName',
                'AplicacionId',
                'Observaciones',
                'TipoPedido',
                'NoTiras',
                'Peine',
                'Luchaje',
                'PesoCrudo',
                'LargoCrudo',
                'CalibreTrama2',
                'FibraTrama',
                'DobladilloId',
                'PasadasTrama',
                'PasadasComb1',
                'PasadasComb2',
                'PasadasComb3',
                'PasadasComb4',
                'PasadasComb5',
                'AnchoToalla',
                'CodColorTrama',
                'ColorTrama',
                'CalibreComb1',
                'FibraComb1',
                'CodColorComb1',
                'NombreCC1',
                'CalibreComb2',
                'FibraComb2',
                'CodColorComb2',
                'NombreCC2',
                'CalibreComb3',
                'FibraComb3',
                'CodColorComb3',
                'NombreCC3',
                'CalibreComb4',
                'FibraComb4',
                'CodColorComb4',
                'NombreCC4',
                'CalibreComb5',
                'FibraComb5',
                'CodColorComb5',
                'NombreCC5',
                'MedidaPlano',
                'CuentaPie',
                'CodColorCtaPie',
                'NombreCPie',
                'PesoGRM2',
                'DiasEficiencia',
                'ProdKgDia',
                'StdDia',
                'ProdKgDia2',
                'StdToaHra',
                'DiasJornada',
                'HorasProd',
                'StdHrsEfect',
                'FechaInicio',
                'Calc4',
                'Calc5',
                'Calc6',
                'FechaFinal',
                'EntregaProduc',
                'EntregaPT',
                'EntregaCte',
                'PTvsCte'
            ])->ordenado()->get();

            $columns = UtilityHelpers::getTableColumns();

            return view('modulos.programa-tejido.req-programa-tejido', compact(
                'registros',
                'columns',
                'basePath',
                'apiPath',
                'linePath',
                'pageTitle'
            ));
        } catch (\Throwable $e) {
            LogFacade::error('Error al cargar programa de tejido', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('modulos.programa-tejido.req-programa-tejido', [
                'registros' => collect(),
                'columns' => UtilityHelpers::getTableColumns(),
                'error' => 'Error al cargar los datos: ' . $e->getMessage(),
                'basePath' => $basePath ?? '/planeacion/programa-tejido',
                'apiPath' => $apiPath ?? '/programa-tejido',
                'linePath' => $linePath ?? '/planeacion/req-programa-tejido-line',
                'pageTitle' => $pageTitle ?? 'Programa de Tejido',
            ]);
        }
    }


    /* ======================================
     |  SHOW / EDIT
     |======================================*/
    public function edit(int $id)
    {
        return EditTejido::editar($id);
    }

    /* ======================================
     |  UPDATE
     |======================================*/
    public function update(Request $request, int $id)
    {
        return UpdateTejido::actualizar($request, $id);
    }

    /* ======================================
     |  STORE
     |======================================*/
    public function store(Request $request)
    {
        $request->validate([
            'salon_tejido_id' => 'required|string',
            'tamano_clave' => 'nullable|string',
            'hilo' => 'nullable|string',
            'idflog' => 'nullable|string',
            'calendario_id' => 'nullable|string',
            'aplicacion_id' => 'nullable|string',
            'telares' => 'required|array|min:1',
            'telares.*.no_telar_id' => 'required|string',
            'telares.*.cantidad' => 'nullable|numeric',
            'telares.*.fecha_inicio' => 'nullable|date',
            'telares.*.fecha_final' => 'nullable|date',
            'telares.*.compromiso_tejido' => 'nullable|date',
            'telares.*.fecha_cliente' => 'nullable|date',
            'telares.*.fecha_entrega' => 'nullable|date',
        ]);

        $salon = $request->string('salon_tejido_id')->toString();
        $tamanoClave = $request->input('tamano_clave');
        $hilo = $request->input('hilo');
        $flogsId = $request->input('idflog');
        $calendarioId = $request->input('calendario_id');
        $aplicacionId = $request->input('aplicacion_id');

        $tipoPedido = UtilityHelpers::resolveTipoPedidoFromFlog($flogsId);

        // Aliases -> campos de BD
        $valoresAlias = UtilityHelpers::resolverAliases($request);

        $creados = [];

        DBFacade::beginTransaction();
        try {
            foreach ($request->input('telares', []) as $fila) {
                $noTelarId = $fila['no_telar_id'];

                // Detectar y marcar CambioHilo en registro anterior (si cambia el hilo)
                UtilityHelpers::marcarCambioHiloAnterior($salon, $noTelarId, $hilo);

                // Limpiar Ultimo previo (1/UL)
                ReqProgramaTejido::where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $noTelarId)
                    ->where(function ($query) {
                        $query->where('Ultimo', '1');
                    })
                    ->update(['Ultimo' => 0]);

                // Crear nuevo
                $nuevo = new ReqProgramaTejido();
                $nuevo->EnProceso      = 0;
                $nuevo->SalonTejidoId  = $salon;
                $nuevo->NoTelarId      = $noTelarId;
                $nuevo->Ultimo         = 1;
                $nuevo->TamanoClave    = $tamanoClave;
                $nuevo->FibraRizo      = $hilo;
                $nuevo->FlogsId        = $flogsId;
                $nuevo->CalendarioId   = $calendarioId;
                $nuevo->AplicacionId   = $aplicacionId;

                // CambioHilo: usar valor calculado en frontend por telar, o fallback al global
                if (isset($fila['cambio_hilo'])) {
                    $nuevo->CambioHilo = $fila['cambio_hilo'];
                } else {
                    $nuevo->CambioHilo = $request->input('CambioHilo', 0);
                }

                // Fechas/cantidad
                $nuevo->FechaInicio     = $fila['fecha_inicio'] ?? null;
                $nuevo->FechaFinal      = $fila['fecha_final'] ?? null;
                $nuevo->EntregaProduc   = $fila['compromiso_tejido'] ?? null;
                $nuevo->EntregaCte      = $fila['fecha_cliente'] ?? null;
                $nuevo->EntregaPT       = $fila['fecha_entrega'] ?? null;
                $nuevo->TotalPedido     = $fila['cantidad'] ?? null;

                // TipoPedido desde FlogsId o request explícito
                $nuevo->TipoPedido      = $tipoPedido ?? $request->input('TipoPedido');

                // Maquina opcional
                if ($request->filled('Maquina')) {
                    $nuevo->Maquina = (string) $request->input('Maquina');
                }

                // Ancho desde AnchoToalla (mapeo explícito)
                if ($request->filled('AnchoToalla')) {
                    $nuevo->Ancho = $request->input('AnchoToalla');
                } elseif ($request->filled('Ancho')) {
                    $nuevo->Ancho = $request->input('Ancho');
                }

                // Mapear campos del formulario (con casteo/truncamiento)
                UpdateHelpers::aplicarCamposFormulario($nuevo, $request);

                // Asignar ItemId y CustName explícitamente (pueden venir del request o del modelo codificado)
                if ($request->filled('ItemId')) {
                    $nuevo->ItemId = (string) $request->input('ItemId');
                }
                if ($request->filled('CustName')) {
                    $nuevo->CustName = StringTruncator::truncate('CustName', (string) $request->input('CustName'));
                }

                // Aplicar aliases (largo->Luchaje, etc.)
                UpdateHelpers::aplicarAliasesEnNuevo($nuevo, $valoresAlias, $request);

                // Fallback desde ReqModelosCodificados cuando falten nombres / medidas
                UpdateHelpers::aplicarFallbackModeloCodificado($nuevo, $request);

                // Truncamientos finales para strings críticos
                foreach (
                    [
                        'NombreProducto',
                        'NombreProyecto',
                        'NombreCC1',
                        'NombreCC2',
                        'NombreCC3',
                        'NombreCC4',
                        'NombreCC5',
                        'NombreCPie',
                        'ColorTrama',
                        'CodColorTrama',
                        'Maquina',
                        'FlogsId',
                        'AplicacionId',
                        'CalendarioId',
                        'Observaciones',
                        'Rasurado'
                    ] as $campoStr
                ) {
                    if (isset($nuevo->{$campoStr}) && is_string($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                // Asignar posición consecutiva para este telar
                $nuevo->Posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($salon, $noTelarId);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();
                $creados[] = $nuevo;
            }

            DBFacade::commit();

            return response()->json([
                'success' => true,
                'message' => 'Programa de tejido creado correctamente',
                'data'    => $creados,
            ]);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al crear programa de tejido: ' . $e->getMessage()], 500);
        }
    }

    /* ======================================
     |  CATÁLOGOS / HELPERS PÚBLICOS
     |======================================*/
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
            // Segunda consulta: Obtener todos los flogs disponibles para búsqueda libre
            // SELECT ft.IDFLOG, ft.NAMEPROYECT
            // FROM dbo.TwFlogsTable AS ft
            // WHERE ft.EstadoFlog IN (3,4,5,21)
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
            // Obtener NAMEPROYECT y CUSTNAME directamente desde TwFlogsTable usando el IDFLOG
            // La descripción será igual a NAMEPROYECT del FLOGID
            $row = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable as ft')
                ->select('ft.NAMEPROYECT as NombreProyecto', 'ft.CUSTNAME as CustName')
                ->where('ft.IDFLOG', trim((string)$idflog))
                ->first();

            // Asegurar que NAMEPROYECT y CUSTNAME se retornen correctamente (trim para limpiar espacios)
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
        // Validar que se proporcione item_id e invent_size_id, O tamano_clave y salon_tejido_id
        $hasItemId = $request->has('item_id') && $request->has('invent_size_id');
        $hasTamanoClave = $request->has('tamano_clave') && $request->has('salon_tejido_id');

        if (!$hasItemId && !$hasTamanoClave) {
            return response()->json([
                'error' => 'Se requiere item_id e invent_size_id, o tamano_clave y salon_tejido_id'
            ], 400);
        }

        $itemId = null;
        $inventSizeId = null;

        // Si se proporciona tamano_clave, obtener ItemId e InventSizeId desde ReqModelosCodificados
        if ($hasTamanoClave) {
            $tamanoClave = trim((string) $request->input('tamano_clave'));
            $salonTejidoId = trim((string) $request->input('salon_tejido_id'));

            $modelo = ReqModelosCodificados::where('SalonTejidoId', $salonTejidoId)
                ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tamanoClave)])
                ->select('ItemId', 'InventSizeId')
                ->first();

            if (!$modelo) {
                // Intentar con LIKE
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
            // Primera consulta: Obtener flog, descripción y custname basado en itemId e inventSizeId
            // SELECT ft.IDFLOG, ft.NAMEPROYECT, ft.CUSTNAME
            // FROM dbo.TwFlogsItemLine AS fil
            // JOIN dbo.TwFlogsTable AS ft ON ft.idFlog = fil.IdFlog
            // WHERE fil.itemId = '7267' AND fil.inventSizeId = 'MB' AND ft.EstadoFlog IN (3,4,5,21)
            // Ordenar por IDFLOG descendente para obtener el más reciente (mayor número)
            $rows = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsItemLine as fil')
                ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
                ->select('ft.IDFLOG as IdFlog', 'ft.NAMEPROYECT as NombreProyecto', 'ft.CUSTNAME as CustName')
                ->whereRaw('LTRIM(RTRIM(fil.ITEMID)) = ?', [$itemId])
                ->whereRaw('LTRIM(RTRIM(fil.INVENTSIZEID)) = ?', [$inventSizeId])
                ->whereIn('ft.ESTADOFLOG', [3, 4, 5, 21])
                ->orderByDesc('ft.IDFLOG')
                ->get();

            // Log todos los flogs encontrados para debugging


            // Si hay múltiples flogs, ordenar numéricamente por el número al final del IDFLOG
            // Esto asegura que F000827 venga antes que F000826
            $row = $rows->sortByDesc(function ($item) {
                $idflog = trim((string)($item->IdFlog ?? ''));
                // Extraer el número al final del IDFLOG (ej: RS-JUL24-LGONZ-F000827 -> 827)
                if (preg_match('/(\d+)$/', $idflog, $matches)) {
                    return (int)$matches[1];
                }
                // Si no hay número, usar orden alfabético inverso
                return 0;
            })->first();

            // Asegurar que NAMEPROYECT y CUSTNAME se retornen correctamente (trim para limpiar espacios)
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

    /**
     * Obtener múltiples opciones de Flogs basadas en tamano_clave
     * Busca en todos los salones donde existe la clave modelo
     * Para autocompletado en el modal
     */
    public function getFlogsByTamanoClave(Request $request)
    {
        $request->validate([
            'tamano_clave' => 'required|string',
        ]);

        $tamanoClave = trim((string) $request->input('tamano_clave'));
        $salonTejidoId = $request->input('salon_tejido_id'); // Opcional, si se proporciona busca solo en ese salón

        try {
            // Buscar en todos los salones donde existe la clave modelo
            $query = ReqModelosCodificados::whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tamanoClave)])
                ->select('ItemId', 'InventSizeId', 'SalonTejidoId')
                ->whereNotNull('ItemId')
                ->whereNotNull('InventSizeId')
                ->where('ItemId', '!=', '')
                ->where('InventSizeId', '!=', '');

            // Si se proporciona salon_tejido_id, filtrar por ese salón
            if ($salonTejidoId) {
                $query->where('SalonTejidoId', trim((string) $salonTejidoId));
            }

            $modelos = $query->get();

            if ($modelos->isEmpty()) {
                // Intentar con LIKE si no se encontró con búsqueda exacta
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

            // Obtener todos los ItemId e InventSizeId únicos
            $items = $modelos->map(function ($m) {
                return [
                    'itemId' => trim((string) $m->ItemId),
                    'inventSizeId' => trim((string) $m->InventSizeId),
                ];
            })->unique(function ($item) {
                return $item['itemId'] . '|' . $item['inventSizeId'];
            })->values();

            // Buscar flogs para cada combinación de ItemId e InventSizeId
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

            // Eliminar duplicados y ordenar
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
                // Normalizar: quitar dobles espacios y usar mayúsculas para comparación flexible
                $tam = preg_replace('/\s+/', ' ', $tam);
            }


            if (!$salon) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            // Usar solo columnas que existen en ReqModelosCodificados - incluir todos los campos necesarios
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
                'PasadasTramaFondoC1', // PasadasTrama no existe, solo PasadasTramaFondoC1
                'FibraTramaFondoC1', // FibraTrama se obtiene de FibraTramaFondoC1
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

            $qBase = ReqModelosCodificados::where('SalonTejidoId', $salon);
            if ($tam) {
                // Intento exacto
                $datos = (clone $qBase)
                    ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tam)])
                    ->select($selectCols)
                    ->first();

                // Prefijo
                if (!$datos) {
                    $datos = (clone $qBase)
                        ->whereRaw('UPPER(TamanoClave) like ?', [strtoupper($tam) . '%'])
                        ->select($selectCols)
                        ->first();
                }

                // Contiene
                if (!$datos) {
                    $datos = (clone $qBase)
                        ->whereRaw('UPPER(TamanoClave) like ?', ['%' . strtoupper($tam) . '%'])
                        ->select($selectCols)
                        ->first();
                }
            } else {
                $datos = $qBase->select($selectCols)->first();
            }

            if (!$datos) {

                return response()->json(['datos' => null]);
            }

            // Mapear campos del modelo codificado a los nombres que se usan en ReqProgramaTejido
            // Algunos campos tienen nombres diferentes
            $datosMapeados = [
                'TamanoClave' => $datos->TamanoClave ?? null,
                'SalonTejidoId' => $datos->SalonTejidoId ?? null,
                'FlogsId' => $datos->FlogsId ?? null,
                'NombreProyecto' => $datos->NombreProyecto ?? null,
                'InventSizeId' => $datos->InventSizeId ?? null,
                'ItemId' => $datos->ItemId ?? null,
                'Nombre' => $datos->Nombre ?? null,
                'NombreProducto' => $datos->Nombre ?? null, // Alias
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
                'Ancho' => $datos->AnchoToalla ?? null, // Ancho se obtiene de AnchoToalla del modelo codificado
                'CodColorTrama' => $datos->CodColorTrama ?? null,
                'ColorTrama' => $datos->ColorTrama ?? null,
                'DobladilloId' => $datos->DobladilloId ?? null,
                'PasadasTrama' => $datos->PasadasTramaFondoC1 ?? null, // Mapeo: PasadasTramaFondoC1 -> PasadasTrama
                'FibraTrama' => $datos->FibraTramaFondoC1 ?? null, // Mapeo: FibraTramaFondoC1 -> FibraTrama (fallback a FibraId)
                'PasadasComb1' => $datos->PasadasComb1 ?? null,
                'PasadasComb2' => $datos->PasadasComb2 ?? null,
                'PasadasComb3' => $datos->PasadasComb3 ?? null,
                'PasadasComb4' => $datos->PasadasComb4 ?? null,
                'PasadasComb5' => $datos->PasadasComb5 ?? null,
                'CalibreComb1' => $datos->CalibreComb1 ?? null,
                'CalibreComb12' => $datos->CalibreComb12 ?? null,
                'FibraComb1' => $datos->FibraComb1 ?? null,
                'CodColorComb1' => $datos->CodColorC1 ?? null, // Mapeo de CodColorC1
                'NombreCC1' => $datos->NomColorC1 ?? null, // Mapeo de NomColorC1
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

            // LOG: Datos mapeados que se van a devolver al frontend
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

    /**
     * Obtener eficiencia y velocidad juntos basándose en telar, hilo y calibre trama
     */
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

    public function getUltimaFechaFinalTelar(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            $telar = $request->input('no_telar_id');
            if (!$salon || !$telar) {
                return response()->json(['error' => 'SalonTejidoId y NoTelarId son requeridos'], 400);
            }

            // Optimizado: aprovecha índice IX_ReqProgramaTejido_Telar_FechaFinal
            // Orden: SalonTejidoId, NoTelarId, FechaFinal DESC (INCLUDE: Id)
            // Nota: FibraRizo, Maquina, Ancho no están en INCLUDE, pero Id sí está
            $ultimo = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->whereNotNull('FechaFinal')
                ->orderByDesc('FechaFinal') // Aprovecha índice IX_ReqProgramaTejido_Telar_FechaFinal
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

    /**
     * Verificar si se puede mover un registro a otro telar/salón
     */
    public function verificarCambioTelar(Request $request, int $id)
    {
        $request->validate([
            'nuevo_salon' => 'required|string',
            'nuevo_telar' => 'required|string'
        ]);

        try {
            $registro = ReqProgramaTejido::findOrFail($id);
            $nuevoSalon = $request->input('nuevo_salon');
            $nuevoTelar = $request->input('nuevo_telar');

            // Si es el mismo telar y salón, no requiere validación
            if ($registro->SalonTejidoId === $nuevoSalon && $registro->NoTelarId === $nuevoTelar) {
                return response()->json([
                    'puede_mover' => true,
                    'requiere_confirmacion' => false,
                    'mensaje' => 'Mismo telar'
                ]);
            }

            // Validar que la clave modelo exista en el salón destino
            $modeloDestino = QueryHelpers::findModeloDestino($nuevoSalon, $registro);

            if (!$modeloDestino) {
                return response()->json([
                    'puede_mover' => false,
                    'requiere_confirmacion' => false,
                    'mensaje' => 'La clave modelo no existe en el telar destino. No se puede realizar el cambio.',
                    'clave_modelo' => $registro->TamanoClave,
                    'telar_destino' => $nuevoTelar,
                    'salon_destino' => $nuevoSalon
                ]);
            }

            // Calcular valores que cambiarán
            [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);

            // Identificar cambios
            $cambios = [];

            // Cambio de Salón
            if ($registro->SalonTejidoId !== $nuevoSalon) {
                $cambios[] = [
                    'campo' => 'Salón',
                    'actual' => $registro->SalonTejidoId,
                    'nuevo' => $nuevoSalon
                ];
            }

            // Cambio de Telar
            if ($registro->NoTelarId !== $nuevoTelar) {
                $cambios[] = [
                    'campo' => 'Telar',
                    'actual' => $registro->NoTelarId,
                    'nuevo' => $nuevoTelar
                ];
            }

            // Cambio de Ultimo (siempre se resetea)
            if ($registro->Ultimo == 1 || $registro->Ultimo == 'UL' || $registro->Ultimo == '1') {
                $cambios[] = [
                    'campo' => 'Último',
                    'actual' => $registro->Ultimo,
                    'nuevo' => '0'
                ];
            }

            // Cambio de CambioHilo (siempre se resetea a 0)
            if ($registro->CambioHilo != 0 && $registro->CambioHilo != null) {
                $cambios[] = [
                    'campo' => 'Cambio Hilo',
                    'actual' => $registro->CambioHilo,
                    'nuevo' => '0'
                ];
            }

            // Cambio de Ancho (del modelo codificado)
            if ($modeloDestino->AnchoToalla && $registro->Ancho != $modeloDestino->AnchoToalla) {
                $cambios[] = [
                    'campo' => 'Ancho',
                    'actual' => $registro->Ancho ?? 'N/A',
                    'nuevo' => $modeloDestino->AnchoToalla
                ];
            }

            // Cambio de Eficiencia
            if ($nuevaEficiencia !== null && $registro->EficienciaSTD != $nuevaEficiencia) {
                $cambios[] = [
                    'campo' => 'Eficiencia STD',
                    'actual' => $registro->EficienciaSTD ?? 'N/A',
                    'nuevo' => number_format($nuevaEficiencia, 2)
                ];
            }

            // Cambio de Velocidad
            if ($nuevaVelocidad !== null && $registro->VelocidadSTD != $nuevaVelocidad) {
                $cambios[] = [
                    'campo' => 'Velocidad STD',
                    'actual' => $registro->VelocidadSTD ?? 'N/A',
                    'nuevo' => $nuevaVelocidad
                ];
            }

            // Cambio de Calibre Rizo (comparar con CalibreRizo2 del modelo)
            if (isset($modeloDestino->CalibreRizo2) && $modeloDestino->CalibreRizo2 !== null) {
                $calibreRizoActual = $registro->CalibreRizo2 ?? $registro->CalibreRizo ?? null;
                if ($calibreRizoActual != $modeloDestino->CalibreRizo2) {
                    $cambios[] = [
                        'campo' => 'Calibre Rizo',
                        'actual' => $calibreRizoActual ?? 'N/A',
                        'nuevo' => $modeloDestino->CalibreRizo2
                    ];
                }
            }

            // Cambio de Calibre Pie (comparar con CalibrePie2 del modelo)
            if (isset($modeloDestino->CalibrePie2) && $modeloDestino->CalibrePie2 !== null) {
                $calibrePieActual = $registro->CalibrePie2 ?? $registro->CalibrePie ?? null;
                if ($calibrePieActual != $modeloDestino->CalibrePie2) {
                    $cambios[] = [
                        'campo' => 'Calibre Pie',
                        'actual' => $calibrePieActual ?? 'N/A',
                        'nuevo' => $modeloDestino->CalibrePie2
                    ];
                }
            }

            // Fechas se recalcularán (siempre cambian)
            $cambios[] = [
                'campo' => 'Fecha Inicio',
                'actual' => $registro->FechaInicio ? Carbon::parse($registro->FechaInicio)->format('d/m/Y H:i') : 'N/A',
                'nuevo' => 'Se recalculará'
            ];

            $cambios[] = [
                'campo' => 'Fecha Final',
                'actual' => $registro->FechaFinal ? Carbon::parse($registro->FechaFinal)->format('d/m/Y H:i') : 'N/A',
                'nuevo' => 'Se recalculará'
            ];

            // Cálculos se recalcularán
            if ($registro->Calc4 || $registro->Calc5 || $registro->Calc6) {
                $cambios[] = [
                    'campo' => 'Cálculos (Calc4, Calc5, Calc6)',
                    'actual' => 'Tienen valores',
                    'nuevo' => 'Se recalcularán'
                ];
            }

            return response()->json([
                'puede_mover' => true,
                'requiere_confirmacion' => true,
                'mensaje' => 'Se moverá el registro a otro telar. Se aplicarán los siguientes cambios:',
                'clave_modelo' => $registro->TamanoClave,
                'telar_origen' => $registro->NoTelarId,
                'salon_origen' => $registro->SalonTejidoId,
                'telar_destino' => $nuevoTelar,
                'salon_destino' => $nuevoSalon,
                'cambios' => $cambios
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'puede_mover' => false,
                'mensaje' => 'Error al verificar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cambiarTelar(Request $request, int $id)
    {
        $request->validate([
            'nuevo_salon' => 'required|string',
            'nuevo_telar' => 'required|string',
            'target_position' => 'required|integer|min:0'
        ]);

        $registro = ReqProgramaTejido::findOrFail($id);

        // Guardar valores antes del cambio para auditoría
        $antes = [
            'salon' => $registro->SalonTejidoId,
            'telar' => $registro->NoTelarId,
            'posicion' => $registro->Posicion ?? 0
        ];

        if ($registro->EnProceso == 1) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede mover un registro en proceso. Debe finalizar el proceso antes de moverlo.'
            ], 422);
        }

        $nuevoSalon = $request->input('nuevo_salon');
        $nuevoTelar = $request->input('nuevo_telar');
        $targetPosition = max(0, (int)$request->input('target_position'));

        if ($registro->SalonTejidoId === $nuevoSalon && $registro->NoTelarId === $nuevoTelar) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona un telar diferente para aplicar el cambio.'
            ], 422);
        }

        // Validar que la clave modelo exista en el salón destino
        $modeloDestino = QueryHelpers::findModeloDestino($nuevoSalon, $registro);

        if (!$modeloDestino) {
            return response()->json([
                'success' => false,
                'message' => 'La clave modelo no existe en el telar destino.'
            ], 422);
        }

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            $idsAfectados = [];
            $detallesTotales = [];
            $updatesOrigen = [];
            $updatesDestino = [];

            $origenSalon = $registro->SalonTejidoId;
            $origenTelar = $registro->NoTelarId;

            // PARKING: quita al registro movido del rango 1..N en ORIGEN para evitar choque al renumerar
            DBFacade::table(ReqProgramaTejido::tableName())
                ->where('Id', $registro->Id)
                ->where('SalonTejidoId', $origenSalon)
                ->where('NoTelarId', $origenTelar)
                ->update(['Posicion' => -1 * (int)$registro->Id]);

            // Optimizado: usar Posicion primero, luego FechaInicio como fallback
            $origenRegistros = ReqProgramaTejido::query()
                ->salon($origenSalon)
                ->telar($origenTelar)
                ->orderBy('Posicion', 'asc') // Aprovecha índice IX_ReqProgramaTejido_Telar_Posicion
                ->orderBy('FechaInicio', 'asc') // Fallback
                ->lockForUpdate()
                ->get();

            $inicioOrigen = $origenRegistros->first()?->FechaInicio;
            $origenSin = $origenRegistros
                ->reject(fn($item) => $item->Id === $registro->Id)
                ->values();

            // Optimizado: usar Posicion primero, luego FechaInicio como fallback
            $destRegistrosOriginal = ReqProgramaTejido::query()
                ->salon($nuevoSalon)
                ->telar($nuevoTelar)
                ->orderBy('Posicion', 'asc') // Aprovecha índice IX_ReqProgramaTejido_Telar_Posicion
                ->orderBy('FechaInicio', 'asc') // Fallback
                ->lockForUpdate()
                ->get();

            $destInicio = $destRegistrosOriginal->first()?->FechaInicio ?? $registro->FechaInicio ?? now();

            // VALIDACIÓN: No se puede colocar antes de un registro en proceso
            $ultimoEnProcesoIndex = -1;
            foreach ($destRegistrosOriginal as $index => $regDestino) {
                if ($regDestino->EnProceso == 1) {
                    $ultimoEnProcesoIndex = $index;
                }
            }

            if ($ultimoEnProcesoIndex !== -1) {
                $posicionMinima = $ultimoEnProcesoIndex + 1;
                if ($targetPosition < $posicionMinima) {
                    DBFacade::rollBack();
                    ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede colocar un registro antes de uno que está en proceso. La posición mínima permitida es ' . ($posicionMinima + 1) . '.'
                    ], 422);
                }
            }

            // Actualizar campos básicos del registro
            $registro->SalonTejidoId = $nuevoSalon;
            $registro->NoTelarId = $nuevoTelar;
            // La posición se calculará después según el orden en el telar destino

            // RECALCULAR velocidad y eficiencia según el nuevo telar
            [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);
            if (!is_null($nuevaEficiencia)) {
                $registro->EficienciaSTD = round($nuevaEficiencia, 2);
            }
            if (!is_null($nuevaVelocidad)) {
                $registro->VelocidadSTD = $nuevaVelocidad;
            }

            // Actualizar Maquina con prefijo según salón destino (SMI / JAC) y número de telar
            $registro->Maquina = $this->construirMaquinaSegunSalon(
                $registro->Maquina ?? '',
                $nuevoSalon,
                $nuevoTelar
            );

            // Actualizar Ancho y AnchoToalla del modelo codificado si existe
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $registro->Ancho = $modeloDestino->AnchoToalla;
                $registro->AnchoToalla = $modeloDestino->AnchoToalla;
            }

            // Calcular CambioHilo basándose en el registro anterior del telar destino
            $cambioHilo = 0;
            if ($destRegistrosOriginal->count() > 0) {
                // Obtener el registro que quedará ANTES del movido en la posición destino
                $registroAnterior = null;
                if ($targetPosition > 0 && $targetPosition <= $destRegistrosOriginal->count()) {
                    $registroAnterior = $destRegistrosOriginal->get($targetPosition - 1);
                } elseif ($targetPosition === 0 && $destRegistrosOriginal->count() > 0) {
                    // Si va en primera posición, verificar contra el primero actual
                    $registroAnterior = null; // No hay anterior
                }

                if ($registroAnterior) {
                    $fibraRizoActual = trim((string) ($registro->FibraRizo ?? ''));
                    $fibraRizoAnterior = trim((string) ($registroAnterior->FibraRizo ?? ''));
                    $cambioHilo = ($fibraRizoActual !== $fibraRizoAnterior && $fibraRizoAnterior !== '') ? 1 : 0;
                }
            }
            $registro->CambioHilo = $cambioHilo;

            // IMPORTANTE: Asegurar que el registro movido tenga todos los campos actualizados
            // antes de insertarlo en la colección destino para que las fórmulas se calculen correctamente
            // IMPORTANTE: El registro ya tiene velocidad y eficiencia actualizadas según el nuevo telar
            // Ahora lo insertamos en la colección destino para recalcular fechas y fórmulas
            $destRegistros = $destRegistrosOriginal->values();
            $targetPosition = min(max($targetPosition, 0), $destRegistros->count());
            $destRegistros->splice($targetPosition, 0, [$registro]);
            $destRegistros = $destRegistros->values();

            // Actualizar posiciones del telar origen
            if ($origenSin->count() > 0) {
                $inicioOrigenCarbon = Carbon::parse($inicioOrigen ?? $registro->FechaInicio ?? now());
                [$updatesOrigen, $detallesOrigen] = DateHelpers::recalcularFechasSecuencia($origenSin, $inicioOrigenCarbon);

                // IMPORTANTE: Asegurar que todos los registros del telar origen tengan su posición actualizada
                foreach ($origenSin->values() as $index => $r) {
                    $idRegistro = (int)$r->Id;
                    $nuevaPosicion = $index + 1;
                    if (!isset($updatesOrigen[$idRegistro])) {
                        $updatesOrigen[$idRegistro] = [];
                    }
                    $updatesOrigen[$idRegistro]['Posicion'] = $nuevaPosicion;
                }

                if (!empty($updatesOrigen)) {
                    $idsOrigen = array_keys($updatesOrigen);
                    DBFacade::table(ReqProgramaTejido::tableName())
                        ->whereIn('Id', $idsOrigen)
                        ->where('SalonTejidoId', $origenSalon)
                        ->where('NoTelarId', $origenTelar)
                        ->update(['Posicion' => DBFacade::raw('Posicion + 10000')]);
                }
                foreach ($updatesOrigen as $idU => $data) {
                    if (isset($data['Posicion'])) {
                        $data['Posicion'] = (int)$data['Posicion'];
                    }
                    DBFacade::table(ReqProgramaTejido::tableName())
                        ->where('Id', $idU)
                        ->where('SalonTejidoId', $origenSalon)
                        ->where('NoTelarId', $origenTelar)
                        ->update($data);
                    $idsAfectados[] = $idU;
                }
                $detallesTotales = array_merge($detallesTotales, $detallesOrigen);
            }

            // Actualizar posiciones del telar destino
            $destInicioCarbon = Carbon::parse($destInicio ?? now());
            [$updatesDestino, $detallesDestino] = DateHelpers::recalcularFechasSecuencia($destRegistros, $destInicioCarbon);

            // IMPORTANTE: Asegurar que todos los registros del telar destino tengan su posición actualizada
            foreach ($destRegistros->values() as $index => $r) {
                $idRegistro = (int)$r->Id;
                $nuevaPosicion = $index + 1;
                if (!isset($updatesDestino[$idRegistro])) {
                    $updatesDestino[$idRegistro] = [];
                }
                $updatesDestino[$idRegistro]['Posicion'] = $nuevaPosicion;
            }

            // Preparar actualización del registro movido con todos los campos necesarios
            // Las fórmulas ya están incluidas en $updatesDestino[$registro->Id] desde recalcularFechasSecuencia
            // Solo necesitamos asegurar que velocidad, eficiencia y otros campos estén presentes
            $updateRegistroMovido = [
                'SalonTejidoId' => $nuevoSalon,
                'NoTelarId' => $nuevoTelar,
                'EficienciaSTD' => $registro->EficienciaSTD, // Ya recalculada según nuevo telar
                'VelocidadSTD' => $registro->VelocidadSTD, // Ya recalculada según nuevo telar
                'Maquina' => $registro->Maquina,
                'CambioHilo' => $registro->CambioHilo,
                'UpdatedAt' => now(),
            ];

            // Actualizar Ancho y AnchoToalla del modelo codificado si existe
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $updateRegistroMovido['Ancho'] = $modeloDestino->AnchoToalla;
                $updateRegistroMovido['AnchoToalla'] = $modeloDestino->AnchoToalla;
            }

            // IMPORTANTE: Agregar los campos del registro movido a $updatesDestino para que se devuelvan al frontend
            if (!isset($updatesDestino[$registro->Id])) {
                $updatesDestino[$registro->Id] = [];
            }
            // Mergear los campos del registro movido (incluyendo Maquina) para que se actualicen en el frontend
            $updatesDestino[$registro->Id] = array_merge($updatesDestino[$registro->Id], [
                'SalonTejidoId' => $nuevoSalon,
                'NoTelarId' => $nuevoTelar,
                'EficienciaSTD' => $registro->EficienciaSTD,
                'VelocidadSTD' => $registro->VelocidadSTD,
                'Maquina' => $registro->Maquina,
                'CambioHilo' => $registro->CambioHilo,
            ]);
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $updatesDestino[$registro->Id]['Ancho'] = $modeloDestino->AnchoToalla;
                $updatesDestino[$registro->Id]['AnchoToalla'] = $modeloDestino->AnchoToalla;
            }

            // =====================================================
            // DESTINO: RESERVA DE POSICIONES (anti-duplicados)
            // =====================================================

            // 1) Reserva posiciones únicas en destino para todos los registros actuales
            // (Id es único, así que Id+1000000 jamás duplica dentro del telar)
            DBFacade::table(ReqProgramaTejido::tableName())
                ->where('SalonTejidoId', $nuevoSalon)
                ->where('NoTelarId', $nuevoTelar)
                ->update(['Posicion' => DBFacade::raw('Id + 1000000')]);

            // 2) Mueve el registro al destino con posición temporal única también
            DBFacade::table(ReqProgramaTejido::tableName())
                ->where('Id', $registro->Id)
                ->where('SalonTejidoId', $origenSalon)
                ->where('NoTelarId', $origenTelar)
                ->update([
                    'SalonTejidoId' => $nuevoSalon,
                    'NoTelarId'     => $nuevoTelar,
                    'Posicion'      => DBFacade::raw('Id + 1000000'), // temporal única
                ]);

            // 3) Ordenar updates por posición para aplicar en orden
            $updatesDestinoOrdenados = [];
            foreach ($updatesDestino as $idU => $data) {
                $posicion = isset($data['Posicion']) ? (int)$data['Posicion'] : 999999;
                $updatesDestinoOrdenados[] = [
                    'id' => (int)$idU,
                    'posicion' => $posicion,
                    'data' => $data
                ];
            }
            // Ordenar por posición ascendente
            usort($updatesDestinoOrdenados, fn($a, $b) => $a['posicion'] <=> $b['posicion']);

            // 4) Aplica updates finales (1..N) sin miedo a colisiones
            foreach ($updatesDestinoOrdenados as $item) {
                $idU  = (int)$item['id'];
                $data = $item['data'];

                if (isset($data['Posicion'])) {
                    $data['Posicion'] = (int)$data['Posicion'];
                }

                // Si es el movido, agrega campos extra
                if ($idU === (int)$registro->Id) {
                    $data = array_merge($data, $updateRegistroMovido);
                }

                DBFacade::table(ReqProgramaTejido::tableName())
                    ->where('Id', $idU)
                    ->where('SalonTejidoId', $nuevoSalon)
                    ->where('NoTelarId', $nuevoTelar)
                    ->update($data);

                $idsAfectados[] = $idU;
            }
            $detallesTotales = array_merge($detallesTotales, $detallesDestino);

            DBFacade::commit();

            $idsAfectados = array_values(array_unique($idsAfectados));
            $updatesMerged = [];
            foreach ($updatesOrigen as $idU => $data) {
                $updatesMerged[(string) $idU] = $data;
            }
            foreach ($updatesDestino as $idU => $data) {
                $updatesMerged[(string) $idU] = array_merge($updatesMerged[(string) $idU] ?? [], $data);
            }

            // Reactivar el Observer y recalcular fórmulas para todos los registros afectados
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsAfectados as $idAfectado) {
                // Refrescar el modelo desde la base de datos para tener los valores actualizados
                if ($r = ReqProgramaTejido::find($idAfectado)) {
                    $r->refresh(); // Asegurar que tiene los valores más recientes
                    $observer->saved($r); // Disparar Observer para recalcular fórmulas
                }
            }

            // Registrar evento de auditoría después del commit
            $despues = [
                'salon' => $registro->SalonTejidoId,
                'telar' => $registro->NoTelarId,
                'posicion' => $registro->Posicion ?? $targetPosition
            ];
            \App\Helpers\AuditoriaHelper::logDragDrop(
                'ReqProgramaTejido',
                $registro->Id,
                $antes,
                $despues,
                $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Telar actualizado correctamente',
                'registros_afectados' => count($idsAfectados),
                'detalles' => $detallesTotales,
                'updates' => $updatesMerged,
                'registro_id' => $registro->Id
            ]);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('cambiarTelar error', [
                'id' => $id ?? null,
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar de telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mover registro a una posición específica (drag and drop)
     */
    public function moveToPosition(Request $request, int $id)
    {
        return DragAndDropTejido::mover($request, $id);
    }

    public function destroy(int $id)
    {
        return EliminarTejido::eliminar($id);
    }

    /**
     * Duplicar todos los registros de un telar
     */
    public function duplicarTelar(Request $request)
    {
        return DuplicarTejido::duplicar($request);
    }

    /**
     * Dividir un telar en dos telares
     */
    public function dividirTelar(Request $request)
    {
        $request->validate([
            'salon_tejido_id' => 'required|string',
            'no_telar_id' => 'required|string',
            'posicion_division' => 'required|integer|min:0',
            'nuevo_telar' => 'required|string',
            'nuevo_salon' => 'nullable|string',
        ]);

        $salon = $request->input('salon_tejido_id');
        $telar = $request->input('no_telar_id');
        $posicionDivision = (int) $request->input('posicion_division');
        $nuevoTelar = $request->input('nuevo_telar');
        $nuevoSalon = $request->input('nuevo_salon') ?? $salon;

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            // Obtener todos los registros del telar ordenados
            $registros = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            if ($registros->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren al menos 2 registros para dividir un telar'
                ], 422);
            }

            if ($posicionDivision < 0 || $posicionDivision >= $registros->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La posición de división está fuera del rango válido'
                ], 422);
            }

            // Dividir los registros
            $registrosOriginales = $registros->take($posicionDivision);
            $registrosNuevos = $registros->skip($posicionDivision);

            // Actualizar los registros que se moverán al nuevo telar
            $idsActualizados = [];
            foreach ($registrosNuevos as $registro) {
                $registro->SalonTejidoId = $nuevoSalon;
                $registro->NoTelarId = $nuevoTelar;
                // Recalcular posición para el nuevo telar
                $registro->Posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($nuevoSalon, $nuevoTelar);
                $registro->CambioHilo = 0;
                $registro->Ultimo = 0;
                $registro->EnProceso = 0;
                $registro->UpdatedAt = now();
                $registro->save();
                $idsActualizados[] = $registro->Id;
            }

            // Recalcular fechas para ambos telares
            if ($registrosOriginales->count() > 0) {
                $inicioOriginal = $registrosOriginales->first()->FechaInicio
                    ? Carbon::parse($registrosOriginales->first()->FechaInicio)
                    : now();
                [$updatesOriginales, $detallesOriginales] = DateHelpers::recalcularFechasSecuencia($registrosOriginales, $inicioOriginal);
                foreach ($updatesOriginales as $idU => $data) {
                    DBFacade::table(ReqProgramaTejido::tableName())->where('Id', $idU)->update($data);
                }
            }

            if ($registrosNuevos->count() > 0) {
                $inicioNuevo = $registrosNuevos->first()->FechaInicio
                    ? Carbon::parse($registrosNuevos->first()->FechaInicio)
                    : now();
                [$updatesNuevos, $detallesNuevos] = DateHelpers::recalcularFechasSecuencia($registrosNuevos, $inicioNuevo);
                foreach ($updatesNuevos as $idU => $data) {
                    DBFacade::table(ReqProgramaTejido::tableName())->where('Id', $idU)->update($data);
                }
            }

            DBFacade::commit();

            // Re-habilitar observer y regenerar líneas
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsActualizados as $idAct) {
                if ($r = ReqProgramaTejido::find($idAct)) {
                    $observer->saved($r);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Telar dividido correctamente. Se movieron {$registrosNuevos->count()} registro(s) al nuevo telar.",
                'registros_movidos' => count($idsActualizados),
                'nuevo_telar' => $nuevoTelar,
                'nuevo_salon' => $nuevoSalon
            ]);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('dividirTelar error', [
                'salon' => $salon,
                'telar' => $telar,
                'posicion_division' => $posicionDivision,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al dividir el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dividir el saldo de un registro entre múltiples telares
     * Usa OrdCompartida para relacionar los registros divididos
     */
    public function dividirSaldo(Request $request)
    {
        return DividirTejido::dividir($request);
    }

    /**
     * Vincular tejidos nuevos desde cero con un OrdCompartida
     * Ahora usa DuplicarTejido con el parámetro vincular=true
     */
    public function vincularTelar(Request $request)
    {
        // Agregar el parámetro vincular=true para que DuplicarTejido maneje la lógica de vincular
        $request->merge(['vincular' => true]);
        return DuplicarTejido::duplicar($request);
    }

    /**
     * Vincular registros existentes con un OrdCompartida
     * Permite seleccionar múltiples registros y asignarles el mismo OrdCompartida
     * sin importar el salón o la diferencia de clave modelo
     */
    public function vincularRegistrosExistentes(Request $request)
    {
        return VincularTejido::vincularRegistrosExistentes($request);
    }

    /**
     * Desvincular un registro de su OrdCompartida
     *
     * - Si hay 2 registros con la misma OrdCompartida: ambos se ponen OrdCompartida = null y OrdCompartidaLider = null
     * - Si hay más de 2: solo el seleccionado se pone OrdCompartida = null, y de los restantes se busca el que tiene la fecha más antigua para ponerlo como líder (OrdCompartidaLider = 1)
     */
    public function desvincularRegistro(Request $request, $id)
    {
        return VincularTejido::desvincularRegistro($request, $id);
    }


    /**
     * Vista de balanceo - muestra registros que comparten OrdCompartida
     */
    public function balancear()
    {
        // Obtener todos los registros que tienen OrdCompartida (no null ni vacío)
        // Incluimos campos adicionales para calcular la fecha final en el frontend
        $registrosCompartidos = ReqProgramaTejido::query()
            ->select([
                'Id',
                'SalonTejidoId',
                'NoTelarId',
                'ItemId',
                'NombreProducto',
                'TamanoClave',
                'TotalPedido',
                'PorcentajeSegundos',
                'SaldoPedido',
                'Produccion',
                'FechaInicio',
                'FechaFinal',
                'OrdCompartida',
                'VelocidadSTD',
                'EficienciaSTD',
                'NoTiras',
                'Luchaje',
                'PesoCrudo'
            ])
            ->whereNotNull('OrdCompartida')
            ->whereRaw("LTRIM(RTRIM(CAST(OrdCompartida AS NVARCHAR(50)))) <> ''")
            ->orderBy('OrdCompartida')
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('NoTelarId', 'asc')
            ->get();

        // Agrupar por OrdCompartida (normalizada) para evitar fallas por espacios o tipos
        $gruposCompartidos = $registrosCompartidos->groupBy(function ($item) {
            return (string) ((int) $item->OrdCompartida);
        });

        return view('modulos.programa-tejido.balancear', [
            'gruposCompartidos' => $gruposCompartidos
        ]);
    }

    /**
     * Obtener detalles de un registro para el modal de balanceo
     * También se usa para obtener el registro completo después de duplicar
     */
    public function detallesBalanceo($id)
    {
        try {
            // Obtener el registro completo usando fresh() para asegurar datos actualizados
            // fresh() hace un nuevo query completo desde la BD, asegurando que tenemos los datos más recientes
            $registro = ReqProgramaTejido::find($id);

            if (!$registro) {
                LogFacade::warning('detallesBalanceo: registro no encontrado', [
                    'id' => $id,
                    'table' => ReqProgramaTejido::tableName(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // Usar fresh() para obtener los datos más recientes desde la BD
            // Esto es más confiable que refresh() cuando se usan selects específicos
            $registro = $registro->fresh();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado después de refrescar'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'registro' => $registro
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview de fechas exactas para el modal de balanceo (no guarda)
     */
    public function previewFechasBalanceo(Request $request)
    {
        return BalancearTejido::previewFechas($request);
    }

    /**
     * Actualizar los pedidos desde la pantalla de balanceo
     */
    public function actualizarPedidosBalanceo(Request $request)
    {
        return BalancearTejido::actualizarPedidos($request);
    }

    /**
     * Balanceo automático con fecha fin objetivo
     */
    public function balancearAutomatico(Request $request)
    {
        return BalancearTejido::balancearAutomatico($request);
    }

    /**
     * Obtener todos los registros que comparten el mismo OrdCompartida
     * Se usa en el modal de dividir para mostrar telares ya divididos
     */
    public function getRegistrosPorOrdCompartida($ordCompartida)
    {
        $ordCompartida = $this->normalizeOrdCompartida($ordCompartida);
        if ($ordCompartida === null) {
            return response()->json([
                'success' => false,
                'message' => 'OrdCompartida invalida'
            ], 400);
        }

        return BalancearTejido::getRegistrosPorOrdCompartida($ordCompartida);
    }

    /**
     * Obtener todos los registros de ProgramaTejido para el modal de actualizar calendarios
     * Retorna solo los campos necesarios: Id, NoTelarId, NombreProducto
     */
    public function getAllRegistrosJson()
    {
        try {
            $registros = ReqProgramaTejido::query()
                ->orderBy('NoTelarId')
                ->orderBy('Id')
                ->get(['Id', 'NoTelarId', 'NombreProducto']);

            return response()->json([
                'success' => true,
                'data' => $registros
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los registros: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar calendario de múltiples registros de ProgramaTejido
     * Actualiza la jornada (calendario) y recalcula SOLO los registros actualizados (optimizado)
     */
    public function actualizarCalendariosMasivo(Request $request)
    {
        set_time_limit(300); // 5 minutos

        try {
            $request->validate([
                'calendario_id' => 'required|string',
                'registros_ids' => 'required|array|min:1',
                'registros_ids.*' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')]
            ]);

            $calendarioId = $request->input('calendario_id');
            $registrosIds = array_unique($request->input('registros_ids', []));

            // Desactivar eventos para mejor rendimiento
            $dispatcher = ReqProgramaTejido::getEventDispatcher();
            ReqProgramaTejido::unsetEventDispatcher();

            $t0 = microtime(true);
            $procesados = 0;
            $actualizados = 0;
            $errores = 0;

            DBFacade::beginTransaction();

            try {
                // 1. Actualizar solo el calendario de los registros seleccionados
                $actualizados = ReqProgramaTejido::whereIn('Id', $registrosIds)
                    ->update(['CalendarioId' => $calendarioId]);

                // 2. Recalcular SOLO los registros actualizados (optimizado)
                // Agrupar por telar para mantener la lógica de cascada
                $registros = ReqProgramaTejido::whereIn('Id', $registrosIds)
                    ->whereNotNull('FechaInicio')
                    ->orderBy('SalonTejidoId')
                    ->orderBy('NoTelarId')
                    ->orderBy('FechaInicio', 'asc')
                    ->orderBy('Id', 'asc')
                    ->get([
                        'Id',
                        'CalendarioId',
                        'SalonTejidoId',
                        'NoTelarId',
                        'FechaInicio',
                        'FechaFinal',
                        'HorasProd',
                        'SaldoPedido',
                        'Produccion',
                        'TotalPedido',
                        'PesoCrudo',
                        'DiasEficiencia',
                        'StdHrsEfect',
                        'ProdKgDia2',
                        'DiasJornada',
                        'Ultimo',
                        'EnProceso'
                    ]);

                $calendarioController = new CalendarioController();
                $prevFin = null;
                $prevTelar = null;
                $observer = new ReqProgramaTejidoObserver();

                foreach ($registros as $p) {
                    try {
                        if (empty($p->FechaInicio)) {
                            $errores++;
                            continue;
                        }

                        $inicioOriginal = Carbon::parse($p->FechaInicio);
                        $inicio = $inicioOriginal->copy();

                        // Si cambió de telar y es el primer registro del telar
                        $esPrimerRegistroTelar = ($prevTelar === null ||
                            ($prevTelar->SalonTejidoId !== $p->SalonTejidoId ||
                             $prevTelar->NoTelarId !== $p->NoTelarId));

                        // RESPETAR: El primer registro de cada telar mantiene su fecha original sin modificaciones
                        if ($esPrimerRegistroTelar) {
                            // Mantener la fecha original del primer registro sin cambios
                            $inicio = $inicioOriginal->copy();
                        } else {
                            // Para registros siguientes: ajustar según el registro anterior
                            if ($prevFin) {
                                // Ajustar inicio basado en el registro anterior (cascada)
                                if (!$prevFin->equalTo($inicioOriginal)) {
                                    $inicio = $prevFin->copy();
                                }
                            }

                            // Snap al calendario solo para registros que no son el primero
                            $snap = $calendarioController->snapInicioAlCalendario($calendarioId, $inicio);
                            if ($snap && !$snap->equalTo($inicio)) {
                                $inicio = $snap;
                            }
                        }

                        // HorasProd: usar la del registro
                        $horas = (float)($p->HorasProd ?? 0);
                        if ($horas <= 0) {
                            $horas = $calendarioController->calcularHorasProd($p);
                            if ($horas > 0) $p->HorasProd = $horas;
                        }
                        if ($horas <= 0) {
                            $errores++;
                            continue;
                        }

                        // Calcular FechaFinal
                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horas);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int)round($horas * 3600));
                        }
                        if ($fin->lt($inicio)) $fin = $inicio->copy();

                        $inicioStr = $inicio->format('Y-m-d H:i:s');
                        $finStr = $fin->format('Y-m-d H:i:s');

                        $oldInicioStr = null;
                        try {
                            $oldInicioStr = Carbon::parse($p->FechaInicio)->format('Y-m-d H:i:s');
                        } catch (\Throwable $e) {}
                        $oldFinStr = null;
                        if (!empty($p->FechaFinal)) {
                            try {
                                $oldFinStr = Carbon::parse($p->FechaFinal)->format('Y-m-d H:i:s');
                            } catch (\Throwable $e) {}
                        }

                        $cambio = ($oldInicioStr !== $inicioStr) || ($oldFinStr !== $finStr);
                        
                        // Auditoría: cambio de FechaInicio al actualizar calendarios
                        if ($oldInicioStr !== $inicioStr) {
                            $enProceso = ($p->EnProceso == 1 || $p->EnProceso === true);
                            \App\Helpers\AuditoriaHelper::logCambioFechaInicio(
                                'ReqProgramaTejido',
                                $p->Id,
                                $oldInicioStr,
                                $inicioStr,
                                'Actualizar Calendarios',
                                $request,
                                $enProceso
                            );
                        }
                        
                        $p->FechaInicio = $inicioStr;
                        $p->FechaFinal = $finStr;

                        // Recalcular fórmulas dependientes de fechas
                        $deps = $calendarioController->calcularFormulasDependientesDeFechas($p, $inicio, $fin, $horas);
                        foreach ($deps as $campo => $valor) {
                            $p->{$campo} = $valor;
                        }

                        $p->saveQuietly();
                        $procesados++;

                        if ($cambio) $actualizados++;

                        // Regenerar líneas solo para los registros actualizados
                        $programaFull = ReqProgramaTejido::find($p->Id);
                        if ($programaFull) {
                            $observer->saved($programaFull);
                        }

                        $prevFin = $fin->copy();
                        $prevTelar = $p;

                    } catch (\Throwable $e) {
                        $errores++;
                        LogFacade::error('Error recalculando registro', [
                            'registro_id' => $p->Id ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                DBFacade::commit();

                // Restaurar dispatcher
                if ($dispatcher) {
                    ReqProgramaTejido::setEventDispatcher($dispatcher);
                }

                $tiempo = round(microtime(true) - $t0, 2);

                return response()->json([
                    'success' => true,
                    'message' => "Se actualizaron {$actualizados} registro(s) con el calendario {$calendarioId} en {$tiempo}s",
                    'data' => [
                        'actualizados' => $actualizados,
                        'procesados' => $procesados,
                        'errores' => $errores,
                        'tiempo_segundos' => $tiempo
                    ]
                ]);

            } catch (\Exception $e) {
                DBFacade::rollBack();
                if ($dispatcher) {
                    ReqProgramaTejido::setEventDispatcher($dispatcher);
                }
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            LogFacade::error('Error en actualizarCalendariosMasivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar calendarios: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Normaliza OrdCompartida: trim y castea a entero; null si vacío o no numérico.
     */
    private function normalizeOrdCompartida($value): ?int
    {
        if (is_null($value)) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            return null;
        }
        return (int) $trimmed;
    }

    /**
     * Construye el valor de Maquina con prefijo dependiente del salón.
     * - Si el salón contiene "SMI" / "SMIT" => prefijo "SMI"
     * - Si el salón contiene "JAC" / "JACQUARD" => prefijo "JAC"
     * - Si no, intenta usar el prefijo existente (letras iniciales) del valor previo
     * - Fallback: primeras 4 letras del salón sin números
     */
    private function construirMaquinaSegunSalon(string $maquinaBase, ?string $salon, $nuevoTelar): string
    {
        $salonNorm = strtoupper(trim((string) $salon));

        if ($salonNorm !== '') {
            if (preg_match('/SMI(T)?/i', $salonNorm)) {
                $prefijo = 'SMI';
            } elseif (preg_match('/JAC/i', $salonNorm)) {
                $prefijo = 'JAC';
            }
        }

        if (!isset($prefijo)) {
            if (preg_match('/^([A-Za-z]+)/', $maquinaBase, $m)) {
                $prefijo = $m[1];
            }
        }

        if (!isset($prefijo)) {
            $prefijo = substr($salonNorm, 0, 4);
            $prefijo = rtrim($prefijo, '0123456789');
        }

        return trim($prefijo) . ' ' . $nuevoTelar;
    }

    /**
     * Actualizar campo Reprogramar
     */
    public function actualizarReprogramar(Request $request, int $id)
    {
        try {
            $request->validate([
                'reprogramar' => 'nullable|string|in:1,2'
            ]);

            $registro = ReqProgramaTejido::findOrFail($id);

            // Validar que el registro esté en proceso
            if ($registro->EnProceso != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede actualizar Reprogramar en registros que están en proceso'
                ], 422);
            }

            // Si reprogramar es null o string vacío, limpiar el campo
            $reprogramar = $request->input('reprogramar');
            if ($reprogramar === null || $reprogramar === '') {
                $reprogramar = null;
            }

            $registro->Reprogramar = $reprogramar;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Reprogramar actualizado correctamente',
                'reprogramar' => $registro->Reprogramar
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado'
            ], 404);
        } catch (\Exception $e) {
            LogFacade::error('Error al actualizar Reprogramar', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar Reprogramar: ' . $e->getMessage()
            ], 500);
        }
    }
}
