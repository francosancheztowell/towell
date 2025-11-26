<?php
namespace App\Http\Controllers\ProgramaTejido;
use App\Helpers\StringTruncator;
use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgramaTejido\funciones\EliminarTejido;
use App\Http\Controllers\ProgramaTejido\funciones\DragAndDropTejido;
use App\Http\Controllers\ProgramaTejido\funciones\EditTejido;
use App\Http\Controllers\ProgramaTejido\funciones\DuplicarTejido;
use App\Http\Controllers\ProgramaTejido\funciones\DividirTejido;
use App\Http\Controllers\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UtilityHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

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
            $registros = ReqProgramaTejido::select([
                'Id','EnProceso','CuentaRizo','CalibreRizo2','SalonTejidoId','NoTelarId','Ultimo','CambioHilo','Maquina',
                'Ancho','EficienciaSTD','VelocidadSTD','FibraRizo','CalibrePie2','CalendarioId','TamanoClave','NoExisteBase',
                'ItemId','InventSizeId','Rasurado','NombreProducto','TotalPedido','Produccion','SaldoPedido','SaldoMarbete',
                'ProgramarProd','OrdCompartida','NoProduccion','Programado','FlogsId','NombreProyecto','CustName','AplicacionId',
                'Observaciones','TipoPedido','NoTiras','Peine','Luchaje','PesoCrudo','LargoCrudo','CalibreTrama2','FibraTrama','DobladilloId',
                'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5','AnchoToalla',
                'CodColorTrama','ColorTrama','CalibreComb1','FibraComb1','CodColorComb1','NombreCC1','CalibreComb2',
                'FibraComb2','CodColorComb2','NombreCC2','CalibreComb3','FibraComb3','CodColorComb3','NombreCC3',
                'CalibreComb4','FibraComb4','CodColorComb4','NombreCC4','CalibreComb5','FibraComb5','CodColorComb5',
                'NombreCC5','MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2','DiasEficiencia','ProdKgDia',
                'StdDia','ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','FechaInicio','Calc4','Calc5','Calc6',
                'FechaFinal','EntregaProduc','EntregaPT','EntregaCte','PTvsCte'
            ])->ordenado()->get();

            $columns = UtilityHelpers::getTableColumns();

            return view('modulos.programa-tejido.req-programa-tejido', compact('registros', 'columns'));
        } catch (\Throwable $e) {
            LogFacade::error('Error al cargar programa de tejido', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('modulos.programa-tejido.req-programa-tejido', [
                'registros' => collect(),
                'columns' => UtilityHelpers::getTableColumns(),
                'error' => 'Error al cargar los datos: '.$e->getMessage(),
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
        $registro = ReqProgramaTejido::findOrFail($id);

        $data = $request->validate([
            'cantidad' => ['nullable','numeric','min:0'],
            'fecha_fin' => ['nullable','string'],
            'fecha_inicio' => ['nullable','date'],
            'calendario_id' => ['nullable','string'],
            'tamano_clave' => ['nullable','string'],
            'no_existe_base' => ['nullable','string'],
            'rasurado' => ['nullable','string'],
            'programar_prod' => ['nullable','date'],
            'idflog' => ['nullable','string'],
            'nombre_proyecto' => ['nullable','string'],
            'aplicacion_id' => ['nullable','string'],
            'observaciones' => ['nullable','string'],
            'no_tiras' => ['nullable','numeric'],
            'peine' => ['nullable','numeric'],
            'largo_crudo' => ['nullable','numeric'],
            'luchaje' => ['nullable','numeric'],
            'peso_crudo' => ['nullable','numeric'],
            'calibre_trama' => ['nullable','numeric'],
            'calibre_trama2' => ['nullable','numeric'],
            'calibre_c1' => ['nullable','numeric'],
            'calibre_c2' => ['nullable','numeric'],
            'calibre_c3' => ['nullable','numeric'],
            'calibre_c4' => ['nullable','numeric'],
            'calibre_c5' => ['nullable','numeric'],
            'fibra_trama' => ['nullable','string'],
            'fibra_c1' => ['nullable','string'],
            'fibra_c2' => ['nullable','string'],
            'fibra_c3' => ['nullable','string'],
            'fibra_c4' => ['nullable','string'],
            'fibra_c5' => ['nullable','string'],
            'cod_color_trama' => ['nullable','string'],
            'cod_color_comb1' => ['nullable','string'],
            'cod_color_comb2' => ['nullable','string'],
            'cod_color_comb3' => ['nullable','string'],
            'cod_color_comb4' => ['nullable','string'],
            'cod_color_comb5' => ['nullable','string'],
            'nombre_cc1' => ['nullable','string'],
            'nombre_cc2' => ['nullable','string'],
            'nombre_cc3' => ['nullable','string'],
            'nombre_cc4' => ['nullable','string'],
            'nombre_cc5' => ['nullable','string'],
            'dobladillo_id' => ['nullable','string'],
            'pasadas_comb1' => ['nullable','numeric'],
            'pasadas_comb2' => ['nullable','numeric'],
            'pasadas_comb3' => ['nullable','numeric'],
            'pasadas_comb4' => ['nullable','numeric'],
            'pasadas_comb5' => ['nullable','numeric'],
            'ancho_toalla' => ['nullable','numeric'],
            'medida_plano' => ['nullable','numeric'],
            'cuenta_pie' => ['nullable','string'],
            'cod_color_cta_pie' => ['nullable','string'],
            'nombre_c_pie' => ['nullable','string'],
            'nombre_color_1' => ['nullable','string'],
            'nombre_color_2' => ['nullable','string'],
            'nombre_color_3' => ['nullable','string'],
            'nombre_color_6' => ['nullable','string'],
            'cod_color_1' => ['nullable','string'],
            'cod_color_2' => ['nullable','string'],
            'cod_color_3' => ['nullable','string'],
            'cod_color_4' => ['nullable','string'],
            'cod_color_5' => ['nullable','string'],
            'cod_color_6' => ['nullable','string'],
            'eficiencia_std' => ['nullable','numeric'],
            'velocidad_std' => ['nullable','numeric'],
            'peso_grm2' => ['nullable','numeric'],
            'dias_eficiencia' => ['nullable','numeric'],
            'prod_kg_dia' => ['nullable','numeric'],
            'std_dia' => ['nullable','numeric'],
            'prod_kg_dia2' => ['nullable','numeric'],
            'std_toa_hra' => ['nullable','numeric'],
            'dias_jornada' => ['nullable','numeric'],
            'horas_prod' => ['nullable','numeric'],
            'std_hrs_efect' => ['nullable','numeric'],
            'entrega_produc' => ['nullable','date'],
            'entrega_pt' => ['nullable','date'],
            'entrega_cte' => ['nullable','date'],
            'pt_vs_cte' => ['nullable','numeric'],
        ]);

        // 1) Cantidad => SaldoPedido/Produccion
        UpdateHelpers::applyCantidad($registro, $data);

        // 2) Guardar original de FechaFinal
        $fechaFinalOriginal = $registro->FechaFinal ? Carbon::parse($registro->FechaFinal) : null;

        // 3) Fechas (campo libre)
        if (!empty($data['fecha_fin'] ?? null)) {
            DateHelpers::setSafeDate($registro, 'FechaFinal', $data['fecha_fin']);
        }

        // 4) Campos calculados principales
        UpdateHelpers::applyCalculados($registro, $data);

        // 5) Eficiencia/Velocidad (acepta snake/camel/case db)
        UpdateHelpers::applyEficienciaVelocidad($registro, $request, $data);

        // 6) Colores/Calibres/Fibras (nombre + códigos + calibres)
        UpdateHelpers::applyColoresYCalibres($registro, $data);

        // 7) Aplicación
        if (array_key_exists('aplicacion_id', $data)) {
            $registro->AplicacionId = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
        }

        // 8) FlogsId/TipoPedido
        UpdateHelpers::applyFlogYTipoPedido($registro, $data['idflog'] ?? null);

        // 9) NombreProyecto
        if (array_key_exists('nombre_proyecto', $data)) {
            $registro->NombreProyecto = $data['nombre_proyecto'] ?: null;
        }

        // 10) Campos de edición en línea adicionales
        UpdateHelpers::applyInlineFieldUpdates($registro, $data);

        // Log útil
        LogFacade::info('UPDATE payload', [
            'Id' => $registro->Id,
            'keys' => array_keys($data),
        ]);

        // 11) Detectar cambio real de FechaFinal para cascada
        $fechaFinalCambiada = false;
        if (array_key_exists('fecha_fin', $data) && !empty($data['fecha_fin'])) {
            $nueva = Carbon::parse($data['fecha_fin']);
            if (!$fechaFinalOriginal || !$fechaFinalOriginal->equalTo($nueva)) {
                $registro->FechaFinal = $nueva;
                $fechaFinalCambiada = true;
            }
        }

        $registro->save();

        // 11) Cascada si aplica
        $detallesCascade = [];
        if ($fechaFinalCambiada && !empty($data['fecha_fin'])) {
            try {
                $detallesCascade = DateHelpers::cascadeFechas($registro);
            } catch (\Throwable $e) {
                LogFacade::error('Cascading error', ['id' => $registro->Id, 'msg' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'cascaded_records' => count($detallesCascade),
            'detalles' => $detallesCascade,
            'data' => UtilityHelpers::extractResumen($registro),
        ]);
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
                        $query->where('Ultimo', '1')
                              ->orWhere('Ultimo', 'UL');
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
                foreach ([
                    'NombreProducto','NombreProyecto','NombreCC1','NombreCC2','NombreCC3','NombreCC4','NombreCC5',
                          'NombreCPie','ColorTrama','CodColorTrama','Maquina','FlogsId','AplicacionId','CalendarioId',
                    'Observaciones','Rasurado'
                ] as $campoStr) {
                    if (isset($nuevo->{$campoStr}) && is_string($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                LogFacade::info('ProgramaTejido.store - guardado', $nuevo->getAttributes());
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
            LogFacade::error('ProgramaTejido.store error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error al crear programa de tejido: '.$e->getMessage()], 500);
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
        $search = $request->input('search','');

        $q = ReqModelosCodificados::query()
            ->select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave','!=','');

        if ($salon) {
            $q->where('SalonTejidoId', $salon);
        }
        if ($search) {
            $q->where('TamanoClave','LIKE',"%{$search}%");
        }

        $op = $q->distinct()->limit(50)->get()->pluck('TamanoClave')->filter()->values();
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
                ->table('dbo.TwFlogsTable as f')
                ->select('f.IDFLOG')
                ->whereIn('f.ESTADOFLOG', [4,5])
                ->whereIn('f.TIPOPEDIDO', [1,2,3])
                ->where('f.DATAAREAID','PRO')
                ->whereNotNull('f.IDFLOG')
                ->distinct()
                ->orderBy('f.IDFLOG')
                ->get()
                ->pluck('IDFLOG')
                ->filter()
                ->values();

            return response()->json($op);
        } catch (\Throwable $e) {
            LogFacade::error('getFlogsIdFromTwFlogsTable', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al cargar opciones de FlogsId: '.$e->getMessage()], 500);
        }
    }

    public function getDescripcionByIdFlog($idflog)
    {
        try {
            $row = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable as f')
                ->select('f.NAMEPROYECT as NombreProyecto')
                ->where('f.IDFLOG',$idflog)
                ->first();

            return response()->json(['nombreProyecto' => $row->NombreProyecto ?? '']);
        } catch (\Throwable $e) {
            LogFacade::error('getDescripcionByIdFlog', ['idflog'=>$idflog,'msg'=>$e->getMessage()]);
            return response()->json(['nombreProyecto' => ''], 500);
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
            $lineas = \App\Models\ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->orderBy('FechaInicio')
                ->get()
                ->map(function($linea) {
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
            LogFacade::error('getCalendarioLineas error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener líneas del calendario'], 500);
        }
    }

    public function getAplicacionIdOptions()
    {
        try {
            $op = ReqProgramaTejido::query()
                ->select('AplicacionId')
                ->whereNotNull('AplicacionId')
                ->where('AplicacionId','!=','')
                ->distinct()
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

    public function getDatosRelacionados(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            $tam   = $request->input('tamano_clave');

            if (!$salon) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            $q = ReqModelosCodificados::where('SalonTejidoId', $salon);
            if ($tam) {
                $q->where('TamanoClave', $tam);
            }

            $datos = $q->select(
                'TamanoClave','SalonTejidoId','FlogsId','Nombre','NombreProyecto','InventSizeId',
                'ItemId','CustName',
                'CuentaRizo','CalibreRizo','CalibreRizo2','FibraRizo',
                'CalibreTrama','CalibreTrama2','CodColorTrama','ColorTrama','FibraId',
                'CalibrePie','CalibrePie2','CuentaPie','FibraPie',
                'CodColorC1','NomColorC1','CodColorC2','NomColorC2','CodColorC3','NomColorC3','CodColorC4','NomColorC4','CodColorC5','NomColorC5',
                'CalibreComb1','CalibreComb12','FibraComb1',
                'CalibreComb2','CalibreComb22','FibraComb2',
                'CalibreComb3','CalibreComb32','FibraComb3',
                'CalibreComb4','CalibreComb42','FibraComb4',
                'CalibreComb5','CalibreComb52','FibraComb5',
                'AnchoToalla','LargoToalla','PesoCrudo','Luchaje','Peine','NoTiras','TotalMarbetes',
                'CambioRepaso','Vendedor','CatCalidad','AnchoPeineTrama','LogLuchaTotal','MedidaPlano','Rasurado',
                'CalTramaFondoC1','CalTramaFondoC12','FibraTramaFondoC1','PasadasTramaFondoC1',
                'PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5',
                'DobladilloId','Obs','Obs1','Obs2','Obs3','Obs4','Obs5',
                'Total'
            )->first();

            return response()->json(['datos' => $datos]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener datos: '.$e->getMessage()], 500);
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
                ->pluck('NoTelarId')
                ->sort()
                ->values()
                ->toArray();

            return response()->json($telares);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener telares: '.$e->getMessage()], 500);
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
                ->select('FechaFinal','FibraRizo','Maquina','Ancho')
                ->first();

            return response()->json([
                'ultima_fecha_final' => $ultimo->FechaFinal ?? null,
                'hilo' => $ultimo->FibraRizo ?? null,
                'maquina' => $ultimo->Maquina ?? null,
                'ancho' => $ultimo->Ancho ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener última fecha final: '.$e->getMessage()], 500);
        }
    }

    public function getHilosOptions()
    {
        try {
            $op = \App\Models\ReqMatrizHilos::query()
                ->whereNotNull('Hilo')
                ->where('Hilo','!=','')
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

            $origenSalon = $registro->SalonTejidoId;
            $origenTelar = $registro->NoTelarId;

            $origenRegistros = ReqProgramaTejido::query()
                ->salon($origenSalon)
                ->telar($origenTelar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            $inicioOrigen = $origenRegistros->first()?->FechaInicio;
            $origenSin = $origenRegistros
                ->reject(fn ($item) => $item->Id === $registro->Id)
                ->values();

            $destRegistrosOriginal = ReqProgramaTejido::query()
                ->salon($nuevoSalon)
                ->telar($nuevoTelar)
                ->orderBy('FechaInicio', 'asc')
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

            // Actualizar Eficiencia y Velocidad según el nuevo telar
            [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);
            if (!is_null($nuevaEficiencia)) {
                $registro->EficienciaSTD = $nuevaEficiencia;
            }
            if (!is_null($nuevaVelocidad)) {
                $registro->VelocidadSTD = $nuevaVelocidad;
            }

            // Actualizar Maquina con el nuevo número de telar
            // Formato: Prefijo del salón + espacio + número de telar (ej: "SMI 320" -> "SMI 319")
            $maquinaActual = $registro->Maquina ?? '';
            if (preg_match('/^([A-Za-z]+)\s*\d*/', $maquinaActual, $matches)) {
                // Usar el prefijo existente (ej: "SMI", "SMIT")
                $prefijo = $matches[1];
            } else {
                // Fallback: usar las primeras 3-4 letras del salón destino
                $prefijo = substr($nuevoSalon, 0, 4);
                $prefijo = rtrim($prefijo, '0123456789'); // Remover números del final si hay
            }
            $registro->Maquina = trim($prefijo) . ' ' . $nuevoTelar;

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

            $destRegistros = $destRegistrosOriginal->values();
            $targetPosition = min(max($targetPosition, 0), $destRegistros->count());
            $destRegistros->splice($targetPosition, 0, [$registro]);
            $destRegistros = $destRegistros->values();

            if ($origenSin->count() > 0) {
                $inicioOrigenCarbon = Carbon::parse($inicioOrigen ?? $registro->FechaInicio ?? now());
                [$updatesOrigen, $detallesOrigen] = DateHelpers::recalcularFechasSecuencia($origenSin, $inicioOrigenCarbon);
                foreach ($updatesOrigen as $idU => $data) {
                    DBFacade::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
                    $idsAfectados[] = $idU;
                }
                $detallesTotales = array_merge($detallesTotales, $detallesOrigen);
            }

            $destInicioCarbon = Carbon::parse($destInicio ?? now());
            [$updatesDestino, $detallesDestino] = DateHelpers::recalcularFechasSecuencia($destRegistros, $destInicioCarbon);

            // Preparar actualización del registro movido con todos los campos necesarios
            $updateRegistroMovido = [
                'SalonTejidoId' => $nuevoSalon,
                'NoTelarId' => $nuevoTelar,
                'EficienciaSTD' => $registro->EficienciaSTD,
                'VelocidadSTD' => $registro->VelocidadSTD,
                'Maquina' => $registro->Maquina,
                'CambioHilo' => $registro->CambioHilo,
                'UpdatedAt' => now(),
            ];

            // Actualizar Ancho y AnchoToalla del modelo codificado si existe
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $updateRegistroMovido['Ancho'] = $modeloDestino->AnchoToalla;
                $updateRegistroMovido['AnchoToalla'] = $modeloDestino->AnchoToalla;
            }

            $updatesDestino[$registro->Id] = array_merge(
                $updatesDestino[$registro->Id] ?? [],
                $updateRegistroMovido
            );

            foreach ($updatesDestino as $idU => $data) {
                DBFacade::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
                $idsAfectados[] = $idU;
            }
            $detallesTotales = array_merge($detallesTotales, $detallesDestino);

            DBFacade::commit();

            $idsAfectados = array_values(array_unique($idsAfectados));

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

            return response()->json([
                'success' => true,
                'message' => 'Telar actualizado correctamente',
                'registros_afectados' => count($idsAfectados),
                'detalles' => $detallesTotales,
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
                    DBFacade::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
                }
            }

            if ($registrosNuevos->count() > 0) {
                $inicioNuevo = $registrosNuevos->first()->FechaInicio
                    ? Carbon::parse($registrosNuevos->first()->FechaInicio)
                    : now();
                [$updatesNuevos, $detallesNuevos] = DateHelpers::recalcularFechasSecuencia($registrosNuevos, $inicioNuevo);
                foreach ($updatesNuevos as $idU => $data) {
                    DBFacade::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
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
     * Vista de balanceo - muestra registros que comparten OrdCompartida
     */
    public function balancear()
    {
        // Obtener todos los registros que tienen OrdCompartida (no null)
        // Incluimos campos adicionales para calcular la fecha final en el frontend
        $registrosCompartidos = ReqProgramaTejido::select([
            'Id', 'SalonTejidoId', 'NoTelarId', 'ItemId', 'NombreProducto',
            'TamanoClave', 'TotalPedido', 'SaldoPedido', 'Produccion',
            'FechaInicio', 'FechaFinal', 'OrdCompartida',
            'VelocidadSTD', 'EficienciaSTD', 'NoTiras', 'Luchaje', 'PesoCrudo'
        ])
        ->whereNotNull('OrdCompartida')
        ->orderBy('OrdCompartida')
        ->orderBy('SalonTejidoId')
        ->orderBy('NoTelarId')
        ->get();

        // Agrupar por OrdCompartida
        $gruposCompartidos = $registrosCompartidos->groupBy('OrdCompartida');

        return view('modulos.programa-tejido.balancear', [
            'gruposCompartidos' => $gruposCompartidos
        ]);
    }

    /**
     * Obtener detalles de un registro para el modal de balanceo
     */
    public function detallesBalanceo($id)
    {
        try {
            $registro = ReqProgramaTejido::select([
                'Id', 'SalonTejidoId', 'NoTelarId', 'ItemId', 'NombreProducto',
                'TamanoClave', 'TotalPedido', 'SaldoPedido', 'Produccion',
                'FechaInicio', 'FechaFinal', 'OrdCompartida', 'FlogsId',
                'CustName', 'NombreProyecto'
            ])->find($id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
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
     * Actualizar los pedidos desde la pantalla de balanceo
     */
    public function actualizarPedidosBalanceo(Request $request)
    {
        return BalancearTejido::actualizarPedidos($request);
    }
}
