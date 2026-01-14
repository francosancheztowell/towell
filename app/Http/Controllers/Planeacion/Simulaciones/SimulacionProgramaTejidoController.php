<?php

namespace App\Http\Controllers\Simulaciones;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Models\Simulaciones\SimulacionProgramaTejido;
use App\Models\Simulaciones\SimulacionProgramaTejidoLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulacionProgramaTejidoController extends \App\Http\Controllers\Controller
{
    /* ======================================
     |  INDEX
     |======================================*/
    public function index()
    {
        try {
            // Mantengo tu selecciÃ³n explÃ­cita pero encapsulo el ORDER en scopeOrdenado()
            $registros = SimulacionProgramaTejido::select([
                'Id','EnProceso','CuentaRizo','CalibreRizo2','SalonTejidoId','NoTelarId','Ultimo','CambioHilo','Maquina',
                'Ancho','EficienciaSTD','VelocidadSTD','FibraRizo','CalibrePie2','CalendarioId','TamanoClave','NoExisteBase',
                'ItemId','InventSizeId','Rasurado','NombreProducto','TotalPedido','Produccion','SaldoPedido','SaldoMarbete',
                'ProgramarProd','NoProduccion','Programado','FlogsId','NombreProyecto','CustName','AplicacionId',
                'Observaciones','TipoPedido','NoTiras','Peine','Luchaje','PesoCrudo','CalibreTrama2','FibraTrama','DobladilloId',
                'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5','AnchoToalla',
                'CodColorTrama','ColorTrama','CalibreComb1','FibraComb1','CodColorComb1','NombreCC1','CalibreComb2',
                'FibraComb2','CodColorComb2','NombreCC2','CalibreComb3','FibraComb3','CodColorComb3','NombreCC3',
                'CalibreComb4','FibraComb4','CodColorComb4','NombreCC4','CalibreComb5','FibraComb5','CodColorComb5',
                'NombreCC5','MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2','DiasEficiencia','ProdKgDia',
                'StdDia','ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','FechaInicio','Calc4','Calc5','Calc6',
                'FechaFinal','EntregaProduc','EntregaPT','EntregaCte','PTvsCte'
            ])->ordenado()->get();

            return view('modulos.simulacion.req-programa-tejido', compact('registros'));
        } catch (\Throwable $e) {
            Log::error('Error al cargar programa de tejido', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return view('modulos.simulacion.req-programa-tejido', [
                'registros' => collect(),
                'error' => 'Error al cargar los datos: '.$e->getMessage(),
            ]);
        }
    }

    /* ======================================
     |  SHOW / EDIT
     |======================================*/
    public function showJson(int $id)
    {
        $registro = SimulacionProgramaTejido::findOrFail($id);
        return response()->json(['success' => true, 'data' => $registro]);
    }

    public function edit(int $id)
    {
        $registro = SimulacionProgramaTejido::findOrFail($id);
        $modeloCodificado = $registro->TamanoClave
            ? ReqModelosCodificados::where('TamanoClave', $registro->TamanoClave)->first()
            : null;

        return view('modulos.simulacion.simulacionform.edit', compact('registro','modeloCodificado'));
    }

    /* ======================================
     |  UPDATE
     |======================================*/
    public function update(Request $request, int $id)
    {
        $registro = SimulacionProgramaTejido::findOrFail($id);

        $data = $request->validate([
            'cantidad' => ['nullable','numeric','min:0'],
            'fecha_fin' => ['nullable','string'],
            'idflog' => ['nullable','string'],
            'nombre_proyecto' => ['nullable','string'],
            'aplicacion_id' => ['nullable','string'],
            'nombre_color_1' => ['nullable','string'],
            'nombre_color_2' => ['nullable','string'],
            'nombre_color_3' => ['nullable','string'],
            'nombre_color_6' => ['nullable','string'],
            'calibre_trama' => ['nullable','numeric'],
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
        ]);

        // 1) Cantidad => SaldoPedido/Produccion
        $this->applyCantidad($registro, $data);

        // 2) Guardar original de FechaFinal
        $fechaFinalOriginal = $registro->FechaFinal ? \Carbon\Carbon::parse($registro->FechaFinal) : null;

        // 3) Fechas (campo libre)
        if (!empty($data['fecha_fin'] ?? null)) {
            $this->setSafeDate($registro, 'FechaFinal', $data['fecha_fin']);
        }

        // 4) Campos calculados principales
        $this->applyCalculados($registro, $data);

        // 5) Eficiencia/Velocidad (acepta snake/camel/case db)
        $this->applyEficienciaVelocidad($registro, $request, $data);

        // 6) Colores/Calibres/Fibras (nombre + cÃ³digos + calibres)
        $this->applyColoresYCalibres($registro, $data);

        // 7) AplicaciÃ³n
        if (array_key_exists('aplicacion_id', $data)) {
            $registro->AplicacionId = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
        }

        // 8) FlogsId/TipoPedido
        $this->applyFlogYTipoPedido($registro, $data['idflog'] ?? null);

        // 9) NombreProyecto
        if (array_key_exists('nombre_proyecto', $data)) {
            $registro->NombreProyecto = $data['nombre_proyecto'] ?: null;
        }

        // 10) Detectar cambio real de FechaFinal para cascada
        $fechaFinalCambiada = false;
        if (array_key_exists('fecha_fin', $data) && !empty($data['fecha_fin'])) {
            $nueva = \Carbon\Carbon::parse($data['fecha_fin']);
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
                $detallesCascade = $this->cascadeFechas($registro);
            } catch (\Throwable $e) {
                Log::error('Cascading error', ['id' => $registro->Id, 'msg' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'cascaded_records' => count($detallesCascade),
            'detalles' => $detallesCascade,
            'data' => $this->extractResumen($registro),
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

        $tipoPedido = $this->resolveTipoPedidoFromFlog($flogsId);

        // Aliases -> campos de BD
        $valoresAlias = $this->resolverAliases($request);

        $creados = [];

        DB::beginTransaction();
        try {
            foreach ($request->input('telares', []) as $fila) {
                $noTelarId = $fila['no_telar_id'];

                // Detectar y marcar CambioHilo en registro anterior (si cambia el hilo)
                $this->marcarCambioHiloAnterior($salon, $noTelarId, $hilo);

                // Limpiar Ultimo previo (1/UL)
                DB::statement("
                    UPDATE SimulacionProgramaTejido
                    SET Ultimo = 0
                    WHERE SalonTejidoId = ?
                      AND NoTelarId   = ?
                      AND (CAST(Ultimo AS NVARCHAR) = '1' OR CAST(Ultimo AS NVARCHAR) = 'UL')
                ", [$salon, $noTelarId]);

                // Crear nuevo
                $nuevo = new SimulacionProgramaTejido();
                $nuevo->EnProceso      = 0;
                $nuevo->SalonTejidoId  = $salon;
                $nuevo->NoTelarId      = $noTelarId;
                $nuevo->Ultimo         = 1;
                $nuevo->TamanoClave    = $tamanoClave;
                $nuevo->FibraRizo      = $hilo;
                $nuevo->FlogsId        = $flogsId;
                $nuevo->CalendarioId   = $calendarioId;
                $nuevo->AplicacionId   = $aplicacionId;
                $nuevo->CambioHilo     = 0; // por defecto

                // Fechas/cantidad
                $nuevo->FechaInicio     = $fila['fecha_inicio'] ?? null;
                $nuevo->FechaFinal      = $fila['fecha_final'] ?? null;
                $nuevo->EntregaProduc   = $fila['compromiso_tejido'] ?? null;
                $nuevo->EntregaCte      = $fila['fecha_cliente'] ?? null;
                $nuevo->EntregaPT       = $fila['fecha_entrega'] ?? null;
                $nuevo->TotalPedido     = $fila['cantidad'] ?? null;

                // TipoPedido desde FlogsId o request explÃ­cito
                $nuevo->TipoPedido      = $tipoPedido ?? $request->input('TipoPedido');

                // Maquina opcional
                if ($request->filled('Maquina')) {
                    $nuevo->Maquina = (string) $request->input('Maquina');
                }

                // Mapear campos del formulario (con casteo/truncamiento)
                $this->aplicarCamposFormulario($nuevo, $request);

                // Aplicar aliases (largo->Luchaje, etc.)
                $this->aplicarAliasesEnNuevo($nuevo, $valoresAlias, $request);

                // Fallback desde ReqModelosCodificados cuando falten nombres / medidas
                $this->aplicarFallbackModeloCodificado($nuevo, $request);

                // Truncamientos finales para strings crÃ­ticos
                foreach (['NombreProducto','NombreProyecto','NombreCC1','NombreCC2','NombreCC3','NombreCC4','NombreCC5',
                          'NombreCPie','ColorTrama','CodColorTrama','Maquina','FlogsId','AplicacionId','CalendarioId',
                          'Observaciones','Rasurado'] as $campoStr) {
                    if (isset($nuevo->{$campoStr}) && is_string($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $creados[] = $nuevo;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Programa de tejido creado correctamente',
                'data'    => $creados,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ProgramaTejido.store error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error al crear programa de tejido: '.$e->getMessage()], 500);
        }
    }

    /* ======================================
     |  CATÃLOGOS / HELPERS PÃšBLICOS
     |======================================*/
    public function getSalonTejidoOptions()
    {
        $programa = SimulacionProgramaTejido::query()->select('SalonTejidoId')->whereNotNull('SalonTejidoId')->distinct()->pluck('SalonTejidoId');
        $modelos  = ReqModelosCodificados::query()->select('SalonTejidoId')->whereNotNull('SalonTejidoId')->distinct()->pluck('SalonTejidoId');

        return response()->json($programa->merge($modelos)->filter()->unique()->sort()->values());
    }

    public function getTamanoClaveOptions()
    {
        $op = ReqModelosCodificados::query()
            ->select('TamanoClave')->whereNotNull('TamanoClave')->where('TamanoClave','!=','')
            ->distinct()->pluck('TamanoClave')->filter()->values();

        return response()->json($op);
    }

    public function getTamanoClaveBySalon(Request $request)
    {
        $salon  = $request->input('salon_tejido_id');
        $search = $request->input('search','');

        $q = ReqModelosCodificados::query()
            ->select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave','!=','');

        if ($salon) $q->where('SalonTejidoId', $salon);
        if ($search) $q->where('TamanoClave','LIKE',"%{$search}%");

        $op = $q->distinct()->limit(50)->get()->pluck('TamanoClave')->filter()->values();
        return response()->json($op);
    }

    public function getFlogsIdOptions()
    {
        $a = SimulacionProgramaTejido::query()->select('FlogsId')->whereNotNull('FlogsId')->distinct()->pluck('FlogsId');
        $b = ReqModelosCodificados::query()->select('FlogsId')->whereNotNull('FlogsId')->distinct()->pluck('FlogsId');

        return response()->json($a->merge($b)->filter()->unique()->sort()->values());
    }

    public function getFlogsIdFromTwFlogsTable()
    {
        try {
            $op = DB::connection('sqlsrv_ti')
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
            Log::error('getFlogsIdFromTwFlogsTable', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al cargar opciones de FlogsId: '.$e->getMessage()], 500);
        }
    }

    public function getDescripcionByIdFlog($idflog)
    {
        try {
            $row = DB::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsTable as f')
                ->select('f.NAMEPROYECT as NombreProyecto')
                ->where('f.IDFLOG',$idflog)
                ->first();

            return response()->json(['nombreProyecto' => $row->NombreProyecto ?? '']);
        } catch (\Throwable $e) {
            Log::error('getDescripcionByIdFlog', ['idflog'=>$idflog,'msg'=>$e->getMessage()]);
            return response()->json(['nombreProyecto' => ''], 500);
        }
    }

    public function getCalendarioIdOptions()
    {
        $op = DB::table('ReqCalendarioTab')
            ->select('CalendarioId')
            ->whereNotNull('CalendarioId')
            ->where('CalendarioId','!=','')
            ->distinct()
            ->pluck('CalendarioId')
            ->filter()
            ->sort()
            ->values();

        return response()->json($op);
    }

    public function getAplicacionIdOptions()
    {
        try {
            $op = SimulacionProgramaTejido::query()
                ->select('AplicacionId')
                ->whereNotNull('AplicacionId')
                ->where('AplicacionId','!=','')
                ->distinct()
                ->pluck('AplicacionId')
                ->filter()
                ->values();

            if ($op->isEmpty()) return response()->json(['mensaje' => 'No se encontraron opciones de aplicaciÃ³n disponibles']);
            return response()->json($op);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al cargar opciones de aplicaciÃ³n: '.$e->getMessage()]);
        }
    }

    public function getDatosRelacionados(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            $tam   = $request->input('tamano_clave');

            if (!$salon) return response()->json(['error' => 'SalonTejidoId es requerido'], 400);

            $q = ReqModelosCodificados::where('SalonTejidoId', $salon);
            if ($tam) $q->where('TamanoClave', $tam);

            $datos = $q->select(
                'TamanoClave','SalonTejidoId','FlogsId','Nombre','NombreProyecto','InventSizeId',
                'ItemId','CustName',  // Campos agregados: Clave AX y Nombre Cliente
                'CuentaRizo','CalibreRizo','CalibreRizo2','FibraRizo',
                'CalibreTrama','CalibreTrama2','CodColorTrama','ColorTrama','FibraId',
                'CalibrePie','CalibrePie2','CuentaPie','FibraPie',
                'CodColorC1','NomColorC1','CodColorC2','NomColorC2','CodColorC3','NomColorC3','CodColorC4','NomColorC4','CodColorC5','NomColorC5',
                'CalibreComb1','CalibreComb12','FibraComb1',
                'CalibreComb2','CalibreComb22','FibraComb2',
                'CalibreComb3','CalibreComb32','FibraComb3',
                'CalibreComb4','CalibreComb42','FibraComb4',
                'CalibreComb5','CalibreComb52','FibraComb5',
                'AnchoToalla','LargoToalla','PesoCrudo','Luchaje','Peine','NoTiras','Repeticiones','TotalMarbetes',
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
        $fibraId = $request->input('fibra_id');
        $noTelar = $request->input('no_telar_id');
        $calTra  = $request->input('calibre_trama');

        if (!$fibraId || !$noTelar || !$calTra) return response()->json(['error' => 'Faltan parÃ¡metros requeridos'], 400);

        $densidad = ($calTra > 40) ? 'Alta' : 'Normal';

        try {
            $ef = DB::table('ReqEficienciaStd')
                ->where('FibraId',$fibraId)
                ->where('NoTelarId',$noTelar)
                ->where('Densidad',$densidad)
                ->value('Eficiencia');

            return response()->json(['eficiencia' => $ef, 'densidad' => $densidad, 'calibre_trama' => $calTra]);
        } catch (\Throwable $e) {
            Log::error('getEficienciaStd error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener eficiencia estÃ¡ndar'], 500);
        }
    }

    public function getVelocidadStd(Request $request)
    {
        $fibraId = $request->input('fibra_id');
        $noTelar = $request->input('no_telar_id');
        $calTra  = $request->input('calibre_trama');

        if (!$fibraId || !$noTelar || !$calTra) return response()->json(['error' => 'Faltan parÃ¡metros requeridos'], 400);

        $densidad = ($calTra > 40) ? 'Alta' : 'Normal';

        try {
            $vel = DB::table('ReqVelocidadStd')
                ->where('FibraId',$fibraId)
                ->where('NoTelarId',$noTelar)
                ->where('Densidad',$densidad)
                ->value('Velocidad');

            return response()->json(['velocidad' => $vel, 'densidad' => $densidad, 'calibre_trama' => $calTra]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener velocidad estÃ¡ndar'], 500);
        }
    }

    public function getTelaresBySalon(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            if (!$salon) return response()->json(['error' => 'SalonTejidoId es requerido'], 400);

            $telares = SimulacionProgramaTejido::query()->salon($salon)
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
            if (!$salon || !$telar) return response()->json(['error' => 'SalonTejidoId y NoTelarId son requeridos'], 400);

            $ultimo = SimulacionProgramaTejido::query()->salon($salon)->telar($telar)
                ->whereNotNull('FechaFinal')
                ->orderByDesc('FechaFinal')
                ->select('FechaFinal','FibraTrama','Maquina','Ancho')
                ->first();

            return response()->json([
                'ultima_fecha_final' => $ultimo->FechaFinal ?? null,
                'hilo' => $ultimo->FibraTrama ?? null,
                'maquina' => $ultimo->Maquina ?? null,
                'ancho' => $ultimo->Ancho ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener Ãºltima fecha final: '.$e->getMessage()], 500);
        }
    }

    public function getUltimoRegistroSalon(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            if (!$salon) return response()->json(['error' => 'SalonTejidoId es requerido'], 400);

            $ultimo = SimulacionProgramaTejido::query()->salon($salon)
                ->orderByDesc('Id')
                ->select('Hilo','Id','FechaInicio')
                ->first();

            if (!$ultimo) return response()->json(['message' => 'No hay registros previos en este salÃ³n'], 200);

            return response()->json([
                'Hilo' => $ultimo->Hilo,
                'Id' => $ultimo->Id,
                'FechaInicio' => $ultimo->FechaInicio
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener Ãºltimo registro: '.$e->getMessage()], 500);
        }
    }

    public function getHilosOptions()
    {
        try {
            $op = \App\Models\Planeacion\ReqMatrizHilos::query()
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

    /* ======================================
     |  CÃLCULO FECHA FIN (igual a tu lÃ³gica)
     |======================================*/
    public function calcularFechaFin(Request $request)
    {
        try {
            $request->validate([
                'telar' => 'required|string',
                'hilo' => 'required|string',
                'cantidad' => 'required|numeric|min:1',
                'fecha_inicio' => 'required|date',
                'calendario' => 'required|string',
                'salon_tejido_id' => 'required|string',
                'tamano_clave' => 'required|string'
            ]);

            $telar  = $request->input('telar');
            $hilo   = $request->input('hilo');
            $cant   = $request->input('cantidad');
            $inicio = $request->input('fecha_inicio');
            $cal    = $request->input('calendario');
            $salon  = $request->input('salon_tejido_id');
            $tc     = $request->input('tamano_clave');

            $modelo = ReqModelosCodificados::where('SalonTejidoId',$salon)->where('TamanoClave',$tc)->first();
            if (!$modelo) return response()->json(['error'=>true,'message'=>'No se encontrÃ³ un modelo con los datos proporcionados.'],404);

            $densidad = (isset($modelo->Tra) && $modelo->Tra > 40) ? 'Alta' : 'Normal';

            $velocidad = \App\Models\Planeacion\Catalogos\CatalagoVelocidad::where('telar',$telar)->where('tipo_hilo',$hilo)->where('densidad',$densidad)->value('velocidad');
            $eficiencia = \App\Models\Planeacion\Catalogos\CatalagoEficiencia::where('telar',$telar)->where('tipo_hilo',$hilo)->where('densidad',$densidad)->value('eficiencia');

            if (!$velocidad || !$eficiencia) return response()->json(['error'=>true,'message'=>'No se encontraron datos de velocidad o eficiencia para el telar y hilo seleccionados.'],404);

            $std_toa_hr_100 = (($modelo->NoTiras * 60) / (((($modelo->Total / 1) + (($modelo->Luchaje * 0.5) / 0.0254) / $modelo->Repeticiones) / $velocidad)));

            $horas = $cant / ($std_toa_hr_100 * $eficiencia);

            $fecha_final = $this->sumarHorasCalendario($inicio, $horas, $cal);

            return response()->json([
                'success' => true,
                'fecha_final' => $fecha_final,
                'horas_calculadas' => round($horas,2),
                'std_toa_hr_100' => round($std_toa_hr_100,3),
                'velocidad' => $velocidad,
                'eficiencia' => $eficiencia,
                'densidad' => $densidad
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error'=>true,'message'=>'Error al calcular fecha final: '.$e->getMessage()],500);
        }
    }

    private function sumarHorasCalendario($fecha_inicio, $horas, $tipo_calendario)
    {
        $dias = floor($horas / 24);
        $horas_rest = floor($horas % 24);
        $mins = round(($horas - floor($horas)) * 60);
        $fecha = \Carbon\Carbon::parse($fecha_inicio);

        switch ($tipo_calendario) {
            case 'Calendario Tej1':
                $fecha->addDays($dias)->addHours($horas_rest)->addMinutes($mins);
                break;
            case 'Calendario Tej2':
                for ($i=0;$i<$dias;$i++) {
                    $fecha->addDay();
                    if ($fecha->dayOfWeek == \Carbon\CarbonInterface::SUNDAY) $fecha->addDay();
                }
                $fecha = $this->sumarHorasSinDomingo($fecha, $horas_rest, $mins);
                break;
            case 'Calendario Tej3':
                $fecha = $this->sumarHorasTej3($fecha, $dias, $horas_rest, $mins);
                break;
            default:
                $fecha->addDays($dias)->addHours($horas_rest)->addMinutes($mins);
        }

        return $fecha->format('Y-m-d H:i:s');
    }

    private function sumarHorasSinDomingo($fecha, $horas, $minutos)
    {
        for ($i=0;$i<$horas;$i++) {
            $fecha->addHour();
            if ($fecha->dayOfWeek == \Carbon\CarbonInterface::SUNDAY) { $fecha->addDay()->setTime(0,0); }
        }
        for ($i=0;$i<$minutos;$i++) {
            $fecha->addMinute();
            if ($fecha->dayOfWeek == \Carbon\CarbonInterface::SUNDAY) { $fecha->addDay()->setTime(0,0); }
        }
        return $fecha;
    }

    private function sumarHorasTej3($fecha, $dias, $horas, $minutos)
    {
        for ($i=0;$i<$dias;$i++) {
            $fecha->addDay();
            if ($fecha->dayOfWeek == \Carbon\CarbonInterface::SUNDAY) $fecha->addDay();
            if (
                $fecha->dayOfWeek == \Carbon\CarbonInterface::SATURDAY && $fecha->hour > 18 ||
                ($fecha->hour == 18 && $fecha->minute > 29)
            ) {
                $fecha->addDays(2)->setTime(7,0);
            }
        }
        return $this->sumarHorasSinDomingo($fecha, $horas, $minutos);
    }

    /* ======================================
     |  PRIORIDAD (UP/DOWN)
     |======================================*/
    public function moveUp(Request $request, int $id)   { return $this->move($id, 'up'); }
    public function moveDown(Request $request, int $id) { return $this->move($id, 'down'); }

    private function move(int $id, string $dir)
    {
        $registro = SimulacionProgramaTejido::findOrFail($id);
        try {
            $res = $this->moverPrioridad($registro, $dir);
            return response()->json(['success'=>true,'message'=>"Prioridad ".($dir==='up'?'incrementada':'decrementada'),'cascaded_records'=>count($res['detalles']),'detalles'=>$res['detalles'],'registro_id'=>$registro->Id,'direccion'=>$dir]);
        } catch (\Throwable $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $registro = SimulacionProgramaTejido::findOrFail($id);
            if ($registro->EnProceso == 1) throw new \RuntimeException('No se puede eliminar un registro que estÃ¡ en proceso.');

            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;
            $esUltimo = ($registro->Ultimo == '1');

            $registros = SimulacionProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->lockForUpdate()->get();
            $idx = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idx === false) throw new \RuntimeException('No se encontrÃ³ el registro a eliminar dentro del telar.');

            $primero = $registros->first();
            $inicioOriginal = $primero->FechaInicio ? \Carbon\Carbon::parse($primero->FechaInicio) : null;
            if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio vÃ¡lida.');

            // Eliminar lÃ­neas y registro
            DB::table('SimulacionProgramaTejidoLine')->where('ProgramaId', $registro->Id)->delete();
            $registro->delete();

            $restantes = SimulacionProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->get();
            if ($restantes->isEmpty()) {
                DB::commit();
                return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente']);
            }

            // Deshabilitar observers
            SimulacionProgramaTejido::unsetEventDispatcher();

            [$updates,$detalles] = $this->recalcularFechasSecuencia($restantes, $inicioOriginal);

            foreach ($updates as $idU => $data) {
                DB::table('SimulacionProgramaTejido')->where('Id',$idU)->update($data);
            }

            DB::commit();

            // Observer no implementado para simulación
            // SimulacionProgramaTejido::observe(\App\Observers\SimulacionProgramaTejidoObserver::class);

            // Regenerar líneas - deshabilitado para simulación
            // $observer = new \App\Observers\SimulacionProgramaTejidoObserver();
            // foreach (array_column($detalles,'Id') as $idAct) {
            //     if ($r = SimulacionProgramaTejido::find($idAct)) $observer->saved($r);
            // }

            return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente','cascaded_records'=>count($detalles),'detalles'=>$detalles]);

        } catch (\Throwable $e) {
            DB::rollBack();
            // Observer no implementado para simulación
            // SimulacionProgramaTejido::observe(\App\Observers\SimulacionProgramaTejidoObserver::class);
            Log::error('destroy error', ['id'=>$id,'msg'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    private function moverPrioridad(SimulacionProgramaTejido $registro, string $direccion): array
    {
        DB::beginTransaction();
        try {
            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            $registros = SimulacionProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->lockForUpdate()->get();
            if ($registros->count() < 2) throw new \RuntimeException('Se requieren al menos dos registros para reordenar la prioridad.');

            $inicioOriginal = optional($registros->first()->FechaInicio) ? \Carbon\Carbon::parse($registros->first()->FechaInicio) : null;
            if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio vÃ¡lida.');

            $idx = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idx === false) throw new \RuntimeException('No se encontrÃ³ el registro a reordenar dentro del telar.');

            $dst = $direccion === 'up' ? $idx - 1 : $idx + 1;
            if ($dst < 0) throw new \RuntimeException('Este registro ya es el primero en la secuencia.');
            if ($dst >= $registros->count()) throw new \RuntimeException('Este registro ya es el Ãºltimo en la secuencia.');

            // Intercambio
            $tmp = $registros[$idx];
            $registros[$idx] = $registros[$dst];
            $registros[$dst] = $tmp;
            $registros = $registros->values();

            SimulacionProgramaTejido::unsetEventDispatcher();

            [$updates,$detalles] = $this->recalcularFechasSecuencia($registros, $inicioOriginal);

            foreach ($updates as $idU => $data) {
                DB::table('SimulacionProgramaTejido')->where('Id',$idU)->update($data);
            }

            DB::commit();

            // Observer no implementado para simulación
            // SimulacionProgramaTejido::observe(\App\Observers\SimulacionProgramaTejidoObserver::class);

            // $observer = new \App\Observers\SimulacionProgramaTejidoObserver();
            // foreach (array_column($detalles,'Id') as $idAct) {
            //     if ($r = SimulacionProgramaTejido::find($idAct)) $observer->saved($r);
            // }

            return ['success'=>true,'detalles'=>$detalles];

        } catch (\Throwable $e) {
            DB::rollBack();
            // Observer no implementado para simulación
            // SimulacionProgramaTejido::observe(\App\Observers\SimulacionProgramaTejidoObserver::class);
            Log::error('moverPrioridad error', ['id'=>$registro->Id ?? null,'dir'=>$direccion,'msg'=>$e->getMessage()]);
            throw $e;
        }
    }

    /* ======================================
     |  CASCADING FECHAS
     |======================================*/
    private function cascadeFechas(SimulacionProgramaTejido $registroActualizado)
    {
        DB::beginTransaction();
        try {
            $salon = $registroActualizado->SalonTejidoId;
            $telar = $registroActualizado->NoTelarId;
            $fin   = \Carbon\Carbon::parse($registroActualizado->FechaFinal);

            $todos = SimulacionProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->get()->values();

            $idx = $todos->search(fn($r) => $r->Id === $registroActualizado->Id);
            if ($idx === false) { DB::commit(); return []; }

            $detalles = [];
            $finAnterior = $fin;

            for ($i = $idx + 1; $i < $todos->count(); $i++) {
                $row = $todos[$i];

                if (!$row->FechaInicio || !$row->FechaFinal) {
                    Log::warning('cascade skip (fechas nulas)', ['id'=>$row->Id]);
                    continue;
                }

                $dInicio = \Carbon\Carbon::parse($row->FechaInicio);
                $dFinal  = \Carbon\Carbon::parse($row->FechaFinal);
                $dur     = $dInicio->diff($dFinal);

                $nuevoInicio = clone $finAnterior;
                $nuevoFin    = (clone $nuevoInicio)->add($dur);

                SimulacionProgramaTejido::where('Id',$row->Id)->update([
                    'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal'  => $nuevoFin->format('Y-m-d H:i:s'),
                ]);

                $finAnterior = $nuevoFin;

                $detalles[] = [
                    'Id' => $row->Id,
                    'NoTelar' => $row->NoTelarId,
                    'FechaInicio_anterior' => $dInicio->format('Y-m-d H:i:s'),
                    'FechaInicio_nueva'    => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal_anterior'  => $dFinal->format('Y-m-d H:i:s'),
                    'FechaFinal_nueva'     => $nuevoFin->format('Y-m-d H:i:s'),
                    'Duracion_dias' => $dur->days,
                    'Duracion_horas'=> $dur->h,
                    'Duracion_minutos'=>$dur->i,
                ];
            }

            DB::commit();
            return $detalles;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('cascadeFechas error', ['id'=>$registroActualizado->Id ?? null, 'msg'=>$e->getMessage()]);
            throw $e;
        }
    }

    private function recalcularFechasSecuencia($registrosOrdenados, \Carbon\Carbon $inicioOriginal)
    {
        $updates = [];
        $detalles = [];
        $lastFin = null;
        $now = now();
        $n = $registrosOrdenados->count();

        foreach ($registrosOrdenados as $i => $r) {
            $ini = $r->FechaInicio ? \Carbon\Carbon::parse($r->FechaInicio) : null;
            $fin = $r->FechaFinal ? \Carbon\Carbon::parse($r->FechaFinal) : null;
            if (!$ini || !$fin) throw new \RuntimeException("El registro {$r->Id} debe tener FechaInicio y FechaFinal completas.");

            $dur = $ini->diff($fin);

            if ($i === 0) {
                $nuevoInicio = $inicioOriginal->copy();
            } else {
                if ($lastFin === null) {
                    throw new \RuntimeException("Error: lastFin es null en iteraciÃ³n {$i}");
                }
                $nuevoInicio = $lastFin->copy();
            }
            $nuevoFin = (clone $nuevoInicio)->add($dur);

            $updates[$r->Id] = [
                'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                'FechaFinal'  => $nuevoFin->format('Y-m-d H:i:s'),
                'EnProceso'   => $i === 0 ? 1 : 0,
                'Ultimo'      => $i === ($n - 1) ? '1' : '0',
                'UpdatedAt'   => $now,
            ];

            $detalles[] = [
                'Id' => $r->Id,
                'NoTelar' => $r->NoTelarId,
                'Posicion' => $i,
                'FechaInicio_nueva' => $updates[$r->Id]['FechaInicio'],
                'FechaFinal_nueva'  => $updates[$r->Id]['FechaFinal'],
                'EnProceso_nuevo'   => $updates[$r->Id]['EnProceso'],
                'Ultimo_nuevo'      => $updates[$r->Id]['Ultimo'],
            ];

            $lastFin = $nuevoFin;
        }

        return [$updates,$detalles];
    }

    /* ======================================
     |  HELPERS PRIVADOS (reasignan sin romper tu lÃ³gica)
     |======================================*/
    private function applyCantidad(SimulacionProgramaTejido $r, array $data): void
    {
        if (!array_key_exists('cantidad', $data)) return;

        $nueva = $data['cantidad'];

        if (!is_null($r->SaldoPedido))       $r->SaldoPedido = $nueva;
        elseif (!is_null($r->Produccion))    $r->Produccion  = $nueva;
        else                                 $r->SaldoPedido = $nueva;
    }

    private function setSafeDate(SimulacionProgramaTejido $r, string $attr, $value): void
    {
        try { $r->{$attr} = \Carbon\Carbon::parse($value); } catch (\Throwable $e) { /* silent */ }
    }

    private function applyCalculados(SimulacionProgramaTejido $r, array $d): void
    {
        if (array_key_exists('peso_grm2',$d))           $r->PesoGRM2      = is_null($d['peso_grm2']) ? null : (int) round((float)$d['peso_grm2']);
        if (array_key_exists('dias_eficiencia',$d))     $r->DiasEficiencia= $d['dias_eficiencia'];
        if (array_key_exists('prod_kg_dia',$d))         $r->ProdKgDia     = $d['prod_kg_dia'];
        if (array_key_exists('std_dia',$d))             $r->StdDia        = $d['std_dia'];
        if (array_key_exists('prod_kg_dia2',$d))        $r->ProdKgDia2    = $d['prod_kg_dia2'];
        if (array_key_exists('std_toa_hra',$d))         $r->StdToaHra     = $d['std_toa_hra'];
        if (array_key_exists('dias_jornada',$d))        $r->DiasJornada   = $d['dias_jornada'];
        if (array_key_exists('horas_prod',$d))          $r->HorasProd     = $d['horas_prod'];
        if (array_key_exists('std_hrs_efect',$d))       $r->StdHrsEfect   = $d['std_hrs_efect'];
    }

    private function applyEficienciaVelocidad(SimulacionProgramaTejido $r, Request $req, array $d): void
    {
        $ef = $d['eficiencia_std'] ?? $req->input('EficienciaSTD') ?? $req->input('eficienciaSTD');
        $ve = $d['velocidad_std']  ?? $req->input('VelocidadSTD')  ?? $req->input('velocidadSTD');

        if ($ef !== null && is_numeric($ef)) $r->EficienciaSTD = (float) $ef;
        if ($ve !== null && is_numeric($ve)) $r->VelocidadSTD  = (float) $ve;
    }

    private function applyColoresYCalibres(SimulacionProgramaTejido $r, array $d): void
    {
        // Nombres
        if (array_key_exists('nombre_color_1',$d)) $r->NombreCC1 = $d['nombre_color_1'];
        if (array_key_exists('nombre_color_2',$d)) $r->NombreCC2 = $d['nombre_color_2'];
        if (array_key_exists('nombre_color_3',$d)) $r->NombreCC3 = $d['nombre_color_3'];
        if (array_key_exists('nombre_color_6',$d)) $r->NombreCC5 = $d['nombre_color_6'];

        // Calibres
        if (array_key_exists('calibre_trama',$d))  $r->CalibreTrama  = $d['calibre_trama'];
        if (array_key_exists('calibre_c1',$d))     $r->CalibreComb12 = $d['calibre_c1'];
        if (array_key_exists('calibre_c2',$d))     $r->CalibreComb22 = $d['calibre_c2'];
        if (array_key_exists('calibre_c3',$d))     $r->CalibreComb32 = $d['calibre_c3'];
        if (array_key_exists('calibre_c4',$d))     { $r->CalibreComb42 = $d['calibre_c4'];}
        if (array_key_exists('calibre_c5',$d))     $r->CalibreComb52 = $d['calibre_c5'];

        // Fibras
        if (array_key_exists('fibra_trama',$d)) $r->FibraTrama = $d['fibra_trama'];
        if (array_key_exists('fibra_c1',$d))    $r->FibraComb1 = $d['fibra_c1'];
        if (array_key_exists('fibra_c2',$d))    $r->FibraComb2 = $d['fibra_c2'];
        if (array_key_exists('fibra_c3',$d))    $r->FibraComb3 = $d['fibra_c3'];
        if (array_key_exists('fibra_c4',$d))    $r->FibraComb4 = $d['fibra_c4'];
        if (array_key_exists('fibra_c5',$d))    $r->FibraComb5 = $d['fibra_c5'];

        // CÃ³digos
        if (array_key_exists('cod_color_1',$d)) $r->CodColorTrama = $d['cod_color_1'];
        if (array_key_exists('cod_color_2',$d)) $r->CodColorComb2 = $d['cod_color_2'];
        if (array_key_exists('cod_color_3',$d)) $r->CodColorComb4 = $d['cod_color_3'];
        if (array_key_exists('cod_color_4',$d)) $r->CodColorComb1 = $d['cod_color_4'];
        if (array_key_exists('cod_color_5',$d)) $r->CodColorComb3 = $d['cod_color_5'];
        if (array_key_exists('cod_color_6',$d)) $r->CodColorComb5 = $d['cod_color_6'];
    }

    private function applyFlogYTipoPedido(SimulacionProgramaTejido $r, ?string $flog): void
    {
        if ($flog !== null) {
            $prev = $r->getOriginal('FlogsId');
            $r->FlogsId = $flog ?: null;

            if ($flog && strlen($flog) >= 2) {
                $pref = strtoupper(substr($flog,0,2));
                $r->TipoPedido = $pref;
            }
        }
    }

    private function extractResumen(SimulacionProgramaTejido $r): array
    {
        return [
            'Id' => $r->Id,
            'SaldoPedido' => $r->SaldoPedido,
            'Produccion'  => $r->Produccion,
            'NombreCC1'   => $r->NombreCC1,
            'NombreCC2'   => $r->NombreCC2,
            'NombreCC3'   => $r->NombreCC3,
            'NombreCC5'   => $r->NombreCC5,
            'CalibreTrama'=> $r->CalibreTrama,
            'CalibreComb12'=> $r->CalibreComb12,
            'CalibreComb22'=> $r->CalibreComb22,
            'CalibreComb32'=> $r->CalibreComb32,
            'CalibreComb42'=> $r->CalibreComb42,
            'CalibreComb52'=> $r->CalibreComb52,
            'FibraTrama'  => $r->FibraTrama,
            'FibraComb1'  => $r->FibraComb1,
            'FibraComb2'  => $r->FibraComb2,
            'FibraComb3'  => $r->FibraComb3,
            'FibraComb4'  => $r->FibraComb4,
            'FibraComb5'  => $r->FibraComb5,
            'CodColorTrama'=> $r->CodColorTrama,
            'CodColorComb1'=> $r->CodColorComb1,
            'CodColorComb2'=> $r->CodColorComb2,
            'CodColorComb3'=> $r->CodColorComb3,
            'CodColorComb4'=> $r->CodColorComb4,
            'CodColorComb5'=> $r->CodColorComb5,
        ];
    }

    private function resolveTipoPedidoFromFlog(?string $flogsId): ?string
    {
        if (!$flogsId || strlen($flogsId) < 2) return null;
        return strtoupper(substr($flogsId,0,2)); // RS, CE, etc.
    }

    private function resolverAliases(Request $req): array
    {
        $map = [
            'NombreProducto' => ['Nombre','NombreProducto','Modelo','Producto'],
            'NoTiras'        => ['NoTiras','Tiras'],
            'Luchaje'        => ['Luchaje','LargoToalla','Largo','Altura','Alto'],
            'ColorTrama'     => ['ColorTrama'],
            'NombreCC1'      => ['NombreCC1','NomColorC1'],
            'NombreCC2'      => ['NombreCC2','NomColorC2'],
            'MedidaPlano'    => ['MedidaPlano','Plano'],
            'NombreCPie'     => ['NombreCPie','Color Pie','Nombre C Pie'],
            'PasadasTrama'   => ['PasadasTrama','Total'],
            'CodColorComb2'  => ['CodColorC2','FibraC2','FibraComb2'],
        ];
        $out = [];
        foreach ($map as $db => $aliases) {
            foreach ($aliases as $a) {
                if ($req->has($a) && $req->filled($a)) {
                    $val = $req->input($a);
                    if (in_array($db,['NoTiras','Luchaje','MedidaPlano','PasadasTrama'])) $val = is_numeric($val) ? (int)$val : $val;
                    else $val = (string)$val;
                    $out[$db] = $val; break;
                }
            }
        }
        return $out;
    }

    private function marcarCambioHiloAnterior(string $salon, $noTelarId, ?string $nuevoHilo): void
    {
        try {
            $anterior = SimulacionProgramaTejido::query()->salon($salon)->telar($noTelarId)->where('Ultimo',1)->first();
            if (!$anterior) {
                $anterior = SimulacionProgramaTejido::query()->salon($salon)->telar($noTelarId)->orderByDesc('Id')->first();
            }
            if ($anterior && $anterior->FibraRizo !== null && $anterior->FibraRizo !== '' && $anterior->FibraRizo !== $nuevoHilo) {
                $anterior->CambioHilo = 1;
                $anterior->save();
            }
        } catch (\Throwable $e) {
        }
    }

    private function aplicarCamposFormulario(SimulacionProgramaTejido $nuevo, Request $req): void
    {
        $campos = [
            'CuentaRizo','CalibreRizo','CalibreRizo2','InventSizeId','NombreProyecto','NombreProducto',
            'Ancho','EficienciaSTD','VelocidadSTD','Maquina',
            'CodColorTrama','ColorTrama','CalibreTrama','CalibreTrama2','FibraTrama',
            'CalibreComb1','CalibreComb12','FibraComb1','CodColorComb1','NombreCC1',
            'CalibreComb2','CalibreComb22','FibraComb2','CodColorComb2','NombreCC2',
            'CalibreComb3','CalibreComb32','FibraComb3','CodColorComb3','NombreCC3',
            'CalibreComb4','CalibreComb42','FibraComb4','CodColorComb4','NombreCC4',
            'CalibreComb5','CalibreComb52','FibraComb5','CodColorComb5','NombreCC5',
            'CalibrePie','CalibrePie2','CuentaPie','FibraPie','CodColorCtaPie','NombreCPie',
            'AnchoToalla','PesoCrudo','Peine','MedidaPlano','NoTiras','Luchaje','Rasurado',
            'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5',
            'DobladilloId',
            'Produccion','SaldoPedido','SaldoMarbete','ProgramarProd','NoProduccion','Programado',
            'CustName','Observaciones','TipoPedido','PesoGRM2','DiasEficiencia','ProdKgDia','StdDia',
            'ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','Calc4','Calc5','Calc6'
        ];

        foreach ($campos as $campo) {
            if (!$req->has($campo) || $req->input($campo) === null || $req->input($campo) === '') continue;

            $valor = $req->input($campo);

            if (in_array($campo, [
                'CalibreRizo','CalibreRizo2','CalibreTrama','CalibreTrama2',
                'CalibreComb1','CalibreComb12','CalibreComb2','CalibreComb22','CalibreComb3','CalibreComb32',
                'CalibreComb4','CalibreComb42','CalibreComb5','CalibreComb52',
                'CalibrePie','CalibrePie2','EficienciaSTD'
            ])) {
                $valor = is_numeric($valor) ? (float)$valor : null;
            } elseif (in_array($campo, ['VelocidadSTD','Peine','PesoCrudo','AnchoToalla','MedidaPlano','Ancho','NoTiras','Luchaje'])) {
                $valor = is_numeric($valor) ? (int)$valor : null;
            } elseif (in_array($campo, ['TotalPedido','Produccion','SaldoPedido','SaldoMarbete','PesoGRM2','DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','Calc4','Calc5','Calc6'])) {
                $valor = is_numeric($valor) ? (float)$valor : null;
            } else {
                $valor = (string)$valor;
                $valor = StringTruncator::truncate($campo, $valor);
            }

            if ($valor !== null) $nuevo->{$campo} = $valor;
        }
    }

    private function aplicarAliasesEnNuevo(SimulacionProgramaTejido $nuevo, array $valoresAlias, Request $req): void
    {
        foreach ($valoresAlias as $dbField => $val) {
            if ($dbField === 'Luchaje' && $val !== '') {
                $nuevo->Luchaje = is_numeric($val) ? (int)$val : $nuevo->Luchaje;
                continue;
            }
            if ($val !== '' && ($nuevo->{$dbField} ?? null) === null) {
                $nuevo->{$dbField} = $val;
            }
        }
    }

    private function aplicarFallbackModeloCodificado(SimulacionProgramaTejido $nuevo, Request $req): void
    {
        try {
            $claveTc = $req->input('tamano_clave') ?? $req->input('TamanoClave');
            $salonTc = $req->input('salon_tejido_id') ?? $req->input('SalonTejidoId');
            if (!$claveTc) return;

            $q = ReqModelosCodificados::query()->where('TamanoClave',$claveTc);
            if ($salonTc) $q->where('SalonTejidoId',$salonTc);

            $modeloCod = $q->orderByDesc('FechaTejido')->first();
            if (!$modeloCod) return;

            if (empty($nuevo->NombreProducto) || $nuevo->NombreProducto === 'null') {
                $nuevo->NombreProducto = StringTruncator::truncate('NombreProducto', (string)($modeloCod->Nombre ?? ''));
            }
            if (empty($nuevo->NombreProyecto) || $nuevo->NombreProyecto === 'null') {
                $nuevo->NombreProyecto = StringTruncator::truncate('NombreProyecto', (string)($modeloCod->NombreProyecto ?? $modeloCod->Descrip ?? $modeloCod->Descripcion ?? ''));
            }
            if (empty($nuevo->MedidaPlano) && !empty($modeloCod->MedidaPlano)) {
                $nuevo->MedidaPlano = (int)$modeloCod->MedidaPlano;
            }
            if (empty($nuevo->NombreCPie) && !empty($modeloCod->NombreCPie)) {
                $nuevo->NombreCPie = StringTruncator::truncate('NombreCPie', (string)$modeloCod->NombreCPie);
            }
        } catch (\Throwable $e) {
        }
    }

    /* ======================================
     |  DUPLICAR DATOS DE PROGRAMA-TEJIDO
     |======================================*/
    public function duplicarDatos()
    {
        try {
            DB::beginTransaction();

            // Primero, eliminar todos los datos existentes en las tablas de simulación
            $registrosEliminados = SimulacionProgramaTejido::count();
            $lineasEliminadas = SimulacionProgramaTejidoLine::count();

            // Eliminar líneas primero (por la relación de clave foránea)
            SimulacionProgramaTejidoLine::truncate();
            // Luego eliminar los registros principales
            SimulacionProgramaTejido::truncate();

            // Obtener todos los registros de ReqProgramaTejido
            $registrosOriginales = ReqProgramaTejido::all();
            $totalRegistros = $registrosOriginales->count();

            if ($totalRegistros === 0) {
                DB::commit();
                return response()->json([
                    'success' => false,
                    'message' => 'No hay datos en Programa de Tejido para duplicar'
                ], 400);
            }

            // Obtener las columnas reales de la tabla de destino (una sola vez)
            $tablaDestino = (new SimulacionProgramaTejido())->getTable();
            $columnasDestino = DB::getSchemaBuilder()->getColumnListing($tablaDestino);
            $columnasDestino = array_diff($columnasDestino, ['Id']); // Excluir Id

            // Preparar arrays para inserción masiva
            $datosRegistros = [];
            $idsOriginales = [];

            // Preparar todos los datos de registros principales
            foreach ($registrosOriginales as $original) {
                $atributosOriginales = $original->getAttributes();
                $datosNuevos = [];
                foreach ($columnasDestino as $campo) {
                    if (array_key_exists($campo, $atributosOriginales)) {
                        $datosNuevos[$campo] = $atributosOriginales[$campo];
                    }
                }
                $datosRegistros[] = $datosNuevos;
                $idsOriginales[] = $original->Id;
            }

            // Insertar registros principales en lotes para evitar el límite de 2100 parámetros de SQL Server
            $tablaSimulacion = (new SimulacionProgramaTejido())->getTable();

            // Calcular el tamaño del lote basado en el número de columnas
            // SQL Server tiene un límite de 2100 parámetros, así que dividimos por el número de columnas
            $numColumnas = count($columnasDestino);
            $tamanoLote = max(1, floor(2000 / $numColumnas)); // Usamos 2000 para estar seguros

            // Obtener el último ID antes de insertar
            $ultimoIdAntes = DB::table($tablaSimulacion)->max('Id') ?? 0;

            // Insertar en lotes
            $mapaIds = [];
            $chunks = array_chunk($datosRegistros, $tamanoLote);
            $chunkIds = array_chunk($idsOriginales, $tamanoLote);

            foreach ($chunks as $chunkIndex => $chunk) {
                // Insertar el lote
                DB::table($tablaSimulacion)->insert($chunk);

                // Obtener los IDs recién insertados para este lote
                $ultimoIdDespues = DB::table($tablaSimulacion)->max('Id') ?? $ultimoIdAntes;
                $cantidadInsertada = count($chunk);
                $primerIdNuevo = $ultimoIdDespues - $cantidadInsertada + 1;

                // Crear mapeo para este lote
                $idsChunk = $chunkIds[$chunkIndex];
                for ($i = 0; $i < $cantidadInsertada; $i++) {
                    if (isset($idsChunk[$i])) {
                        $mapaIds[$idsChunk[$i]] = $primerIdNuevo + $i;
                    }
                }

                $ultimoIdAntes = $ultimoIdDespues;
            }

            // Obtener todas las líneas de una vez (evitar N+1 queries)
            $todasLasLineas = ReqProgramaTejidoLine::whereIn('ProgramaId', $idsOriginales)->get();

            // Agrupar líneas por ProgramaId para mapeo eficiente
            $lineasPorPrograma = [];
            foreach ($todasLasLineas as $linea) {
                if (!isset($lineasPorPrograma[$linea->ProgramaId])) {
                    $lineasPorPrograma[$linea->ProgramaId] = [];
                }
                $lineasPorPrograma[$linea->ProgramaId][] = $linea;
            }

            // Preparar datos de líneas para inserción masiva
            $datosLineas = [];
            foreach ($mapaIds as $idOriginal => $idNuevo) {
                if (isset($lineasPorPrograma[$idOriginal])) {
                    foreach ($lineasPorPrograma[$idOriginal] as $lineaOriginal) {
                        $datosLineas[] = [
                            'ProgramaId' => $idNuevo,
                            'Fecha' => $lineaOriginal->Fecha,
                            'Cantidad' => $lineaOriginal->Cantidad,
                            'Kilos' => $lineaOriginal->Kilos,
                            'Aplicacion' => $lineaOriginal->Aplicacion,
                            'Trama' => $lineaOriginal->Trama,
                            'Combina1' => $lineaOriginal->Combina1,
                            'Combina2' => $lineaOriginal->Combina2,
                            'Combina3' => $lineaOriginal->Combina3,
                            'Combina4' => $lineaOriginal->Combina4,
                            'Combina5' => $lineaOriginal->Combina5,
                            'Pie' => $lineaOriginal->Pie,
                            'Rizo' => $lineaOriginal->Rizo,
                            'MtsRizo' => $lineaOriginal->MtsRizo,
                            'MtsPie' => $lineaOriginal->MtsPie,
                        ];
                    }
                }
            }

            // Insertar todas las líneas en lotes calculados dinámicamente
            $tablaLineas = (new SimulacionProgramaTejidoLine())->getTable();
            $columnasLineas = DB::getSchemaBuilder()->getColumnListing($tablaLineas);
            $columnasLineas = array_diff($columnasLineas, ['Id']); // Excluir Id
            $numColumnasLineas = count($columnasLineas);

            // Calcular tamaño del lote: dejar margen de seguridad (usar 2000 en lugar de 2100)
            $tamanoLoteLineas = max(1, floor(2000 / $numColumnasLineas));

            $chunks = array_chunk($datosLineas, $tamanoLoteLineas);
            foreach ($chunks as $chunk) {
                DB::table($tablaLineas)->insert($chunk);
            }

            $registrosDuplicados = $totalRegistros;
            $lineasDuplicadas = count($datosLineas);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Simulación actualizada exitosamente. Se eliminaron {$registrosEliminados} registro(s) y {$lineasEliminadas} línea(s) anteriores. Se crearon {$registrosDuplicados} registro(s) y {$lineasDuplicadas} línea(s) nuevos.",
                'data' => [
                    'registros_eliminados' => $registrosEliminados,
                    'lineas_eliminadas' => $lineasEliminadas,
                    'registros' => $registrosDuplicados,
                    'lineas' => $lineasDuplicadas
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al duplicar datos de Programa de Tejido a Simulación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar los datos: ' . $e->getMessage()
            ], 500);
        }
    }
}
