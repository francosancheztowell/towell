<?php

namespace App\Http\Controllers;

use App\Helpers\StringTruncator;
use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
use App\Models\ReqEficienciaStd;
use App\Models\ReqVelocidadStd;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramaTejidoController extends Controller
{
    /* ======================================
     |  INDEX
     |======================================*/
    public function index()
    {
        try {
            $registros = ReqProgramaTejido::select([
                'Id','EnProceso','CuentaRizo','CalibreRizo2','SalonTejidoId','NoTelarId','Ultimo','CambioHilo','Maquina',
                'Ancho','EficienciaSTD','VelocidadSTD','FibraRizo','CalibrePie2','CalendarioId','TamanoClave','NoExisteBase',
                'ItemId','InventSizeId','Rasurado','NombreProducto','TotalPedido','Produccion','SaldoPedido','SaldoMarbete',
                'ProgramarProd','NoProduccion','Programado','FlogsId','NombreProyecto','CustName','AplicacionId',
                'Observaciones','TipoPedido','NoTiras','Peine','Luchaje','PesoCrudo','LargoCrudo','CalibreTrama2','FibraTrama','DobladilloId',
                'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5','AnchoToalla',
                'CodColorTrama','ColorTrama','CalibreComb1','FibraComb1','CodColorComb1','NombreCC1','CalibreComb2',
                'FibraComb2','CodColorComb2','NombreCC2','CalibreComb3','FibraComb3','CodColorComb3','NombreCC3',
                'CalibreComb4','FibraComb4','CodColorComb4','NombreCC4','CalibreComb5','FibraComb5','CodColorComb5',
                'NombreCC5','MedidaPlano','CuentaPie','CodColorCtaPie','NombreCPie','PesoGRM2','DiasEficiencia','ProdKgDia',
                'StdDia','ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','FechaInicio','Calc4','Calc5','Calc6',
                'FechaFinal','EntregaProduc','EntregaPT','EntregaCte','PTvsCte'
            ])->ordenado()->get();

            $columns = $this->getTableColumns();

            return view('modulos.programa-tejido.req-programa-tejido', compact('registros', 'columns'));
        } catch (\Throwable $e) {
            Log::error('Error al cargar programa de tejido', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return view('modulos.programa-tejido.req-programa-tejido', [
                'registros' => collect(),
                'columns' => $this->getTableColumns(),
                'error' => 'Error al cargar los datos: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Define las columnas de la tabla con sus tipos de fecha
     * DATE: solo fecha (sin hora)
     * DATETIME: fecha con hora
     */
    private function getTableColumns(): array
    {
        return [
            ['field' => 'EnProceso', 'label' => 'Estado', 'dateType' => null],
            ['field' => 'CuentaRizo', 'label' => 'Cuenta', 'dateType' => null],
            ['field' => 'CalibreRizo2', 'label' => 'Calibre Rizo', 'dateType' => null],
            ['field' => 'SalonTejidoId', 'label' => 'Salón', 'dateType' => null],
            ['field' => 'NoTelarId', 'label' => 'Telar', 'dateType' => null],
            ['field' => 'Ultimo', 'label' => 'Último', 'dateType' => null],
            ['field' => 'CambioHilo', 'label' => 'Cambios Hilo', 'dateType' => null],
            ['field' => 'Maquina', 'label' => 'Maq', 'dateType' => null],
            ['field' => 'Ancho', 'label' => 'Ancho', 'dateType' => null],
            ['field' => 'EficienciaSTD', 'label' => 'Ef Std', 'dateType' => null],
            ['field' => 'VelocidadSTD', 'label' => 'Vel', 'dateType' => null],
            ['field' => 'FibraRizo', 'label' => 'Hilo', 'dateType' => null],
            ['field' => 'CalibrePie2', 'label' => 'Calibre Pie', 'dateType' => null],
            ['field' => 'CalendarioId', 'label' => 'Jornada', 'dateType' => null],
            ['field' => 'TamanoClave', 'label' => 'Clave Mod.', 'dateType' => null],
            ['field' => 'NoExisteBase', 'label' => 'Usar cuando no existe en base', 'dateType' => null],
            ['field' => 'ItemId', 'label' => 'Clave AX', 'dateType' => null],
            ['field' => 'InventSizeId', 'label' => 'Tamaño AX', 'dateType' => null],
            ['field' => 'Rasurado', 'label' => 'Rasurado', 'dateType' => null],
            ['field' => 'NombreProducto', 'label' => 'Producto', 'dateType' => null],
            ['field' => 'TotalPedido', 'label' => 'Pedido', 'dateType' => null],
            ['field' => 'Produccion', 'label' => 'Producción', 'dateType' => null],
            ['field' => 'SaldoPedido', 'label' => 'Saldos', 'dateType' => null],
            ['field' => 'SaldoMarbete', 'label' => 'Saldo Marbetes', 'dateType' => null],
            ['field' => 'ProgramarProd', 'label' => 'Día Scheduling', 'dateType' => 'date'],
            ['field' => 'NoProduccion', 'label' => 'Orden Prod.', 'dateType' => null],
            ['field' => 'Programado', 'label' => 'INN', 'dateType' => 'date'],
            ['field' => 'FlogsId', 'label' => 'Id Flog', 'dateType' => null],
            ['field' => 'NombreProyecto', 'label' => 'Descrip.', 'dateType' => null],
            ['field' => 'CustName', 'label' => 'Nombre Cliente', 'dateType' => null],
            ['field' => 'AplicacionId', 'label' => 'Aplic.', 'dateType' => null],
            ['field' => 'Observaciones', 'label' => 'Obs', 'dateType' => null],
            ['field' => 'TipoPedido', 'label' => 'Tipo Ped.', 'dateType' => null],
            ['field' => 'NoTiras', 'label' => 'Tiras', 'dateType' => null],
            ['field' => 'Peine', 'label' => 'Pei.', 'dateType' => null],
            ['field' => 'LargoCrudo', 'label' => 'Lcr', 'dateType' => null],
            ['field' => 'Luchaje', 'label' => 'Luc', 'dateType' => null],
            ['field' => 'PesoCrudo', 'label' => 'Pcr', 'dateType' => null],
            ['field' => 'CalibreTrama2', 'label' => 'Calibre Tra', 'dateType' => null],
            ['field' => 'FibraTrama', 'label' => 'Fibra Trama', 'dateType' => null],
            ['field' => 'DobladilloId', 'label' => 'Dob', 'dateType' => null],
            ['field' => 'PasadasTrama', 'label' => 'Pasadas Tra', 'dateType' => null],
            ['field' => 'PasadasComb1', 'label' => 'Pasadas C1', 'dateType' => null],
            ['field' => 'PasadasComb2', 'label' => 'Pasadas C2', 'dateType' => null],
            ['field' => 'PasadasComb3', 'label' => 'Pasadas C3', 'dateType' => null],
            ['field' => 'PasadasComb4', 'label' => 'Pasadas C4', 'dateType' => null],
            ['field' => 'PasadasComb5', 'label' => 'Pasadas C5', 'dateType' => null],
            ['field' => 'AnchoToalla', 'label' => 'Ancho por Toalla', 'dateType' => null],
            ['field' => 'CodColorTrama', 'label' => 'Código Color Tra', 'dateType' => null],
            ['field' => 'ColorTrama', 'label' => 'Color Tra', 'dateType' => null],
            ['field' => 'CalibreComb1', 'label' => 'Calibre C1', 'dateType' => null],
            ['field' => 'FibraComb1', 'label' => 'Fibra C1', 'dateType' => null],
            ['field' => 'CodColorComb1', 'label' => 'Código Color C1', 'dateType' => null],
            ['field' => 'NombreCC1', 'label' => 'Color C1', 'dateType' => null],
            ['field' => 'CalibreComb2', 'label' => 'Calibre C2', 'dateType' => null],
            ['field' => 'FibraComb2', 'label' => 'Fibra C2', 'dateType' => null],
            ['field' => 'CodColorComb2', 'label' => 'Código Color C2', 'dateType' => null],
            ['field' => 'NombreCC2', 'label' => 'Color C2', 'dateType' => null],
            ['field' => 'CalibreComb3', 'label' => 'Calibre C3', 'dateType' => null],
            ['field' => 'FibraComb3', 'label' => 'Fibra C3', 'dateType' => null],
            ['field' => 'CodColorComb3', 'label' => 'Código Color C3', 'dateType' => null],
            ['field' => 'NombreCC3', 'label' => 'Color C3', 'dateType' => null],
            ['field' => 'CalibreComb4', 'label' => 'Calibre C4', 'dateType' => null],
            ['field' => 'FibraComb4', 'label' => 'Fibra C4', 'dateType' => null],
            ['field' => 'CodColorComb4', 'label' => 'Código Color C4', 'dateType' => null],
            ['field' => 'NombreCC4', 'label' => 'Color C4', 'dateType' => null],
            ['field' => 'CalibreComb5', 'label' => 'Calibre C5', 'dateType' => null],
            ['field' => 'FibraComb5', 'label' => 'Fibra C5', 'dateType' => null],
            ['field' => 'CodColorComb5', 'label' => 'Código Color C5', 'dateType' => null],
            ['field' => 'NombreCC5', 'label' => 'Color C5', 'dateType' => null],
            ['field' => 'MedidaPlano', 'label' => 'Plano', 'dateType' => null],
            ['field' => 'CuentaPie', 'label' => 'Cuenta Pie', 'dateType' => null],
            ['field' => 'CodColorCtaPie', 'label' => 'Código Color Pie', 'dateType' => null],
            ['field' => 'NombreCPie', 'label' => 'Color Pie', 'dateType' => null],
            ['field' => 'PesoGRM2', 'label' => 'Peso (gr/m²)', 'dateType' => null],
            ['field' => 'DiasEficiencia', 'label' => 'Días Ef.', 'dateType' => null],
            ['field' => 'ProdKgDia', 'label' => 'Prod (Kg)/Día', 'dateType' => null],
            ['field' => 'StdDia', 'label' => 'Std/Día', 'dateType' => null],
            ['field' => 'ProdKgDia2', 'label' => 'Prod (Kg)/Día 2', 'dateType' => null],
            ['field' => 'StdToaHra', 'label' => 'Std (Toa/Hr) 100%', 'dateType' => null],
            ['field' => 'DiasJornada', 'label' => 'Días Jornada', 'dateType' => null],
            ['field' => 'HorasProd', 'label' => 'Horas', 'dateType' => null],
            ['field' => 'StdHrsEfect', 'label' => 'Std/Hr Efectivo', 'dateType' => null],
            ['field' => 'FechaInicio', 'label' => 'Inicio', 'dateType' => 'datetime'],
            ['field' => 'FechaFinal', 'label' => 'Fin', 'dateType' => 'datetime'],
            ['field' => 'EntregaProduc', 'label' => 'Fecha Compromiso Prod.', 'dateType' => 'date'],
            ['field' => 'EntregaPT', 'label' => 'Fecha Compromiso PT', 'dateType' => 'date'],
            ['field' => 'EntregaCte', 'label' => 'Entrega', 'dateType' => 'datetime'],
            ['field' => 'PTvsCte', 'label' => 'Dif vs Compromiso', 'dateType' => null],
        ];
    }

    /* ======================================
     |  SHOW / EDIT
     |======================================*/
    public function edit(int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);
        $modeloCodificado = $registro->TamanoClave
            ? ReqModelosCodificados::where('TamanoClave', $registro->TamanoClave)->first()
            : null;

        return view('modulos.programa-tejido.programatejidoform.edit', compact('registro','modeloCodificado'));
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
        $fechaFinalOriginal = $registro->FechaFinal ? Carbon::parse($registro->FechaFinal) : null;

        // 3) Fechas (campo libre)
        if (!empty($data['fecha_fin'] ?? null)) {
            $this->setSafeDate($registro, 'FechaFinal', $data['fecha_fin']);
        }

        // 4) Campos calculados principales
        $this->applyCalculados($registro, $data);

        // 5) Eficiencia/Velocidad (acepta snake/camel/case db)
        $this->applyEficienciaVelocidad($registro, $request, $data);

        // 6) Colores/Calibres/Fibras (nombre + códigos + calibres)
        $this->applyColoresYCalibres($registro, $data);

        // 7) Aplicación
        if (array_key_exists('aplicacion_id', $data)) {
            $registro->AplicacionId = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
        }

        // 8) FlogsId/TipoPedido
        $this->applyFlogYTipoPedido($registro, $data['idflog'] ?? null);

        // 9) NombreProyecto
        if (array_key_exists('nombre_proyecto', $data)) {
            $registro->NombreProyecto = $data['nombre_proyecto'] ?: null;
        }

        // Log útil
        Log::info('UPDATE payload', [
            'Id' => $registro->Id,
            'keys' => array_keys($data),
        ]);

        // 10) Detectar cambio real de FechaFinal para cascada
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
                    UPDATE ReqProgramaTejido
                    SET Ultimo = 0
                    WHERE SalonTejidoId = ?
                      AND NoTelarId   = ?
                      AND (CAST(Ultimo AS NVARCHAR) = '1' OR CAST(Ultimo AS NVARCHAR) = 'UL')
                ", [$salon, $noTelarId]);

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
                $this->aplicarCamposFormulario($nuevo, $request);

                // Asignar ItemId y CustName explícitamente (pueden venir del request o del modelo codificado)
                if ($request->filled('ItemId')) {
                    $nuevo->ItemId = (string) $request->input('ItemId');
                }
                if ($request->filled('CustName')) {
                    $nuevo->CustName = StringTruncator::truncate('CustName', (string) $request->input('CustName'));
                }

                // Aplicar aliases (largo->Luchaje, etc.)
                $this->aplicarAliasesEnNuevo($nuevo, $valoresAlias, $request);

                // Fallback desde ReqModelosCodificados cuando falten nombres / medidas
                $this->aplicarFallbackModeloCodificado($nuevo, $request);

                // Truncamientos finales para strings críticos
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

                Log::info('ProgramaTejido.store - guardado', $nuevo->getAttributes());
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
     |  CATÁLOGOS / HELPERS PÚBLICOS
     |======================================*/
    public function getSalonTejidoOptions()
    {
        $programa = ReqProgramaTejido::query()->select('SalonTejidoId')->whereNotNull('SalonTejidoId')->distinct()->pluck('SalonTejidoId');
        $modelos  = ReqModelosCodificados::query()->select('SalonTejidoId')->whereNotNull('SalonTejidoId')->distinct()->pluck('SalonTejidoId');

        return response()->json($programa->merge($modelos)->filter()->unique()->sort()->values());
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
        $a = ReqProgramaTejido::query()->select('FlogsId')->whereNotNull('FlogsId')->distinct()->pluck('FlogsId');
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
            Log::error('getCalendarioLineas error', ['msg' => $e->getMessage()]);
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

            if ($op->isEmpty()) return response()->json(['mensaje' => 'No se encontraron opciones de aplicación disponibles']);
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

            if (!$salon) return response()->json(['error' => 'SalonTejidoId es requerido'], 400);

            $q = ReqModelosCodificados::where('SalonTejidoId', $salon);
            if ($tam) $q->where('TamanoClave', $tam);

            $datos = $q->select(
                'TamanoClave','SalonTejidoId','FlogsId','Nombre','NombreProyecto','InventSizeId',
                'ItemId',  //  ItemId existe en ReqModelosCodificados
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

        if (!$fibraId || !$noTelar || !$calTra) return response()->json(['error' => 'Faltan parámetros requeridos'], 400);

        $densidad = ($calTra > 40) ? 'Alta' : 'Normal';

        try {
            $ef = DB::table('ReqEficienciaStd')
                ->where('FibraId',$fibraId)
                ->where('NoTelarId',$noTelar)
                ->where('Densidad',$densidad)
                ->value('Eficiencia');

            Log::info('getEficienciaStd', compact('fibraId','noTelar','densidad','ef'));
            return response()->json(['eficiencia' => $ef, 'densidad' => $densidad, 'calibre_trama' => $calTra]);
        } catch (\Throwable $e) {
            Log::error('getEficienciaStd error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener eficiencia estándar'], 500);
        }
    }

    public function getVelocidadStd(Request $request)
    {
        $fibraId = $request->input('fibra_id');
        $noTelar = $request->input('no_telar_id');
        $calTra  = $request->input('calibre_trama');

        if (!$fibraId || !$noTelar || !$calTra) return response()->json(['error' => 'Faltan parámetros requeridos'], 400);

        $densidad = ($calTra > 40) ? 'Alta' : 'Normal';

        try {
            $vel = DB::table('ReqVelocidadStd')
                ->where('FibraId',$fibraId)
                ->where('NoTelarId',$noTelar)
                ->where('Densidad',$densidad)
                ->value('Velocidad');

            Log::info('getVelocidadStd', compact('fibraId','noTelar','densidad','vel'));
            return response()->json(['velocidad' => $vel, 'densidad' => $densidad, 'calibre_trama' => $calTra]);
        } catch (\Throwable $e) {
            Log::error('getVelocidadStd error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener velocidad estándar'], 500);
        }
    }

    public function getTelaresBySalon(Request $request)
    {
        try {
            $salon = $request->input('salon_tejido_id');
            if (!$salon) return response()->json(['error' => 'SalonTejidoId es requerido'], 400);

            $telares = ReqProgramaTejido::query()->salon($salon)
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

            $ultimo = ReqProgramaTejido::query()->salon($salon)->telar($telar)
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

    /* ======================================
     |  PRIORIDAD (DRAG AND DROP)
     |======================================*/
    /**
     * Verificar si se puede mover un registro a otro telar/salón
     *
     * @param Request $request
     * @param int $id ID del registro a mover
     * @return \Illuminate\Http\JsonResponse
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
            // NOTA: Se valida solo por SalonTejidoId y ClaveModelo/TamanoClave
            // No se valida por NoTelarId para permitir cambios entre telares del mismo salón
            $modeloDestino = ReqModelosCodificados::where('SalonTejidoId', $nuevoSalon)
                // ->where('NoTelarId', $nuevoTelar) // COMENTADO: Ya no se valida por telar específico
                ->where(function ($q) use ($registro) {
                    $q->where('ClaveModelo', $registro->TamanoClave)
                        ->orWhere('TamanoClave', $registro->TamanoClave);
                })
                ->first();

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
            [$nuevaEficiencia, $nuevaVelocidad] = $this->resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);

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

            // Nota: Máquina se mantiene igual (no está en modelos codificados)

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
        // NOTA: Se valida solo por SalonTejidoId y ClaveModelo/TamanoClave
        // No se valida por NoTelarId para permitir cambios entre telares del mismo salón
        $modeloDestino = ReqModelosCodificados::where('SalonTejidoId', $nuevoSalon)
            // ->where('NoTelarId', $nuevoTelar) // COMENTADO: Ya no se valida por telar específico
            ->where(function ($q) use ($registro) {
                $q->where('ClaveModelo', $registro->TamanoClave)
                    ->orWhere('TamanoClave', $registro->TamanoClave);
            })
            ->first();

        if (!$modeloDestino) {
            return response()->json([
                'success' => false,
                'message' => 'La clave modelo no existe en el telar destino.'
            ], 422);
        }

        DB::beginTransaction();
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
            $origenSin = $origenRegistros->reject(fn ($item) => $item->Id === $registro->Id)->values();

            $destRegistrosOriginal = ReqProgramaTejido::query()
                ->salon($nuevoSalon)
                ->telar($nuevoTelar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();
            $destInicio = $destRegistrosOriginal->first()?->FechaInicio ?? $registro->FechaInicio ?? now();

            // VALIDACIÓN: No se puede colocar antes de un registro en proceso
            // Encontrar el último registro en proceso del telar destino
            $ultimoEnProcesoIndex = -1;
            foreach ($destRegistrosOriginal as $index => $regDestino) {
                if ($regDestino->EnProceso == 1) {
                    $ultimoEnProcesoIndex = $index;
                }
            }

            // Si hay registros en proceso, la posición mínima debe ser después del último
            if ($ultimoEnProcesoIndex !== -1) {
                $posicionMinima = $ultimoEnProcesoIndex + 1;
                if ($targetPosition < $posicionMinima) {
                    DB::rollBack();
                    ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede colocar un registro antes de uno que está en proceso. La posición mínima permitida es ' . ($posicionMinima + 1) . '.'
                    ], 422);
                }
            }

            $registro->SalonTejidoId = $nuevoSalon;
            $registro->NoTelarId = $nuevoTelar;
            $registro->CambioHilo = 0;

            [$nuevaEficiencia, $nuevaVelocidad] = $this->resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);
            if (!is_null($nuevaEficiencia)) {
                $registro->EficienciaSTD = $nuevaEficiencia;
            }
            if (!is_null($nuevaVelocidad)) {
                $registro->VelocidadSTD = $nuevaVelocidad;
            }

            $destRegistros = $destRegistrosOriginal->values();
            $targetPosition = min(max($targetPosition, 0), $destRegistros->count());
            $destRegistros->splice($targetPosition, 0, [$registro]);
            $destRegistros = $destRegistros->values();

            if ($origenSin->count() > 0) {
                $inicioOrigenCarbon = Carbon::parse($inicioOrigen ?? $registro->FechaInicio ?? now());
                [$updatesOrigen, $detallesOrigen] = $this->recalcularFechasSecuencia($origenSin, $inicioOrigenCarbon);
                foreach ($updatesOrigen as $idU => $data) {
                    DB::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
                    $idsAfectados[] = $idU;
                }
                $detallesTotales = array_merge($detallesTotales, $detallesOrigen);
            }

            $destInicioCarbon = Carbon::parse($destInicio ?? now());
            [$updatesDestino, $detallesDestino] = $this->recalcularFechasSecuencia($destRegistros, $destInicioCarbon);

            $updatesDestino[$registro->Id] = array_merge(
                $updatesDestino[$registro->Id] ?? [],
                [
                    'SalonTejidoId' => $nuevoSalon,
                    'NoTelarId' => $nuevoTelar,
                    'EficienciaSTD' => $registro->EficienciaSTD,
                    'VelocidadSTD' => $registro->VelocidadSTD,
                    'UpdatedAt' => now(),
                ]
            );

            foreach ($updatesDestino as $idU => $data) {
                DB::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
                $idsAfectados[] = $idU;
            }
            $detallesTotales = array_merge($detallesTotales, $detallesDestino);

            DB::commit();

            $idsAfectados = array_values(array_unique($idsAfectados));

            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsAfectados as $idAfectado) {
                if ($r = ReqProgramaTejido::find($idAfectado)) {
                    $observer->saved($r);
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
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('cambiarTelar error', [
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
     *
     * @param Request $request
     * @param int $id ID del registro a mover
     * @return \Illuminate\Http\JsonResponse
     */
    public function moveToPosition(Request $request, int $id)
    {
        $request->validate([
            'new_position' => 'required|integer|min:0'
        ]);

        $registro = ReqProgramaTejido::findOrFail($id);

        // Validación 1: Registro no debe estar en proceso
        if ($registro->EnProceso == 1) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede mover un registro en proceso. Debe finalizar el proceso antes de moverlo.'
            ], 422);
        }

        try {
            $res = $this->moverAposicion($registro, $request->input('new_position'));
            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
                'cascaded_records' => count($res['detalles']),
                'detalles' => $res['detalles'],
                'registro_id' => $registro->Id
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $registro = ReqProgramaTejido::findOrFail($id);
            if ($registro->EnProceso == 1) throw new \RuntimeException('No se puede eliminar un registro que está en proceso.');

            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            $registros = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->lockForUpdate()->get();
            $idx = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idx === false) throw new \RuntimeException('No se encontró el registro a eliminar dentro del telar.');

            $primero = $registros->first();
            $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
            if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');

            // Eliminar registro (las líneas se eliminan por ON DELETE CASCADE en BD)
            $registro->delete();

            $restantes = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->get();
            if ($restantes->isEmpty()) {
                DB::commit();
                return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente']);
            }

            // Deshabilitar observers
            ReqProgramaTejido::unsetEventDispatcher();

            [$updates,$detalles] = $this->recalcularFechasSecuencia($restantes, $inicioOriginal);

            foreach ($updates as $idU => $data) {
                DB::table('ReqProgramaTejido')->where('Id',$idU)->update($data);
            }

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Regenerar líneas
            $observer = new ReqProgramaTejidoObserver();
            foreach (array_column($detalles,'Id') as $idAct) {
                if ($r = ReqProgramaTejido::find($idAct)) $observer->saved($r);
            }

            Log::info('destroy OK', ['id'=>$id,'salon'=>$salon,'telar'=>$telar,'n'=>count($detalles)]);
            return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente','cascaded_records'=>count($detalles),'detalles'=>$detalles]);

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('destroy error', ['id'=>$id,'msg'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    /* ======================================
     |  CASCADING FECHAS
     |======================================*/
    private function cascadeFechas(ReqProgramaTejido $registroActualizado)
    {
        DB::beginTransaction();
        try {
            $salon = $registroActualizado->SalonTejidoId;
            $telar = $registroActualizado->NoTelarId;
            $fin   = Carbon::parse($registroActualizado->FechaFinal);

            $todos = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->get()->values();

            $idx = $todos->search(fn($r) => $r->Id === $registroActualizado->Id);
            if ($idx === false) { DB::commit(); return []; }

            $detalles = [];
            $finAnterior = $fin;
            $idsActualizados = [];

            // Deshabilitar observers temporalmente para evitar regeneración durante actualizaciones masivas
            ReqProgramaTejido::unsetEventDispatcher();

            for ($i = $idx + 1; $i < $todos->count(); $i++) {
                $row = $todos[$i];

                if (!$row->FechaInicio || !$row->FechaFinal) {
                    Log::warning('cascade skip (fechas nulas)', ['id'=>$row->Id]);
                    continue;
                }

                $dInicio = Carbon::parse($row->FechaInicio);
                $dFinal  = Carbon::parse($row->FechaFinal);
                $dur     = $dInicio->diff($dFinal);

                $nuevoInicio = clone $finAnterior;
                $nuevoFin    = (clone $nuevoInicio)->add($dur);

                // Actualizar usando DB::table para evitar disparar observers
                DB::table('ReqProgramaTejido')->where('Id',$row->Id)->update([
                    'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal'  => $nuevoFin->format('Y-m-d H:i:s'),
                    'UpdatedAt'   => now(),
                ]);

                $idsActualizados[] = $row->Id;
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

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Regenerar líneas para todos los registros actualizados
            // El Observer eliminará las líneas existentes y creará nuevas basadas en las nuevas fechas
            if (!empty($idsActualizados)) {
                $observer = new ReqProgramaTejidoObserver();
                foreach ($idsActualizados as $idAct) {
                    if ($r = ReqProgramaTejido::find($idAct)) {
                        // El Observer.saved() eliminará las líneas existentes y creará nuevas
                        // Esto asegura que las FK se mantengan correctas y no haya duplicados
                        $observer->saved($r);
                    }
                }
                Log::info('cascadeFechas: Líneas regeneradas', ['ids_actualizados'=>count($idsActualizados)]);
            }

            return $detalles;

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('cascadeFechas error', [
                'id'=>$registroActualizado->Id ?? null,
                'msg'=>$e->getMessage(),
                'trace'=>$e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function recalcularFechasSecuencia($registrosOrdenados, Carbon $inicioOriginal)
    {
        $updates = [];
        $detalles = [];
        $lastFin = null;
        $now = now();
        $n = $registrosOrdenados->count();

        foreach ($registrosOrdenados as $i => $r) {
            $ini = $r->FechaInicio ? Carbon::parse($r->FechaInicio) : null;
            $fin = $r->FechaFinal ? Carbon::parse($r->FechaFinal) : null;
            if (!$ini || !$fin) throw new \RuntimeException("El registro {$r->Id} debe tener FechaInicio y FechaFinal completas.");

            $dur = $ini->diff($fin);

            if ($i === 0) {
                $nuevoInicio = $inicioOriginal->copy();
            } else {
                if ($lastFin === null) {
                    throw new \RuntimeException("Error: lastFin es null en iteración {$i}");
                }
                $nuevoInicio = $lastFin->copy();
            }
            $nuevoFin = (clone $nuevoInicio)->add($dur);

            // Calcular CambioHilo: comparar FibraRizo con el registro anterior
            $cambioHilo = '0'; // Por defecto 0
            if ($i > 0) {
                $registroAnterior = $registrosOrdenados[$i - 1];
                $fibraRizoActual = trim((string)$r->FibraRizo);
                $fibraRizoAnterior = trim((string)$registroAnterior->FibraRizo);

                // Si FibraRizo es diferente al anterior → CambioHilo = 1
                $cambioHilo = ($fibraRizoActual !== $fibraRizoAnterior) ? '1' : '0';
            }

            $updates[$r->Id] = [
                'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                'FechaFinal'  => $nuevoFin->format('Y-m-d H:i:s'),
                'EnProceso'   => $i === 0 ? 1 : 0,
                'Ultimo'      => $i === ($n - 1) ? '1' : '0',
                'CambioHilo'  => $cambioHilo,
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
                'CambioHilo_nuevo'  => $cambioHilo,
            ];

            $lastFin = $nuevoFin;
        }

        return [$updates,$detalles];
    }

    /**
     * Mover un registro a una posición específica y recalcular fechas
     *
     * @param ReqProgramaTejido $registro
     * @param int $nuevaPosicion
     * @return array
     */
    private function moverAposicion(ReqProgramaTejido $registro, int $nuevaPosicion): array
    {
        DB::beginTransaction();
        try {
            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            $registros = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            if ($registros->count() < 2) {
                throw new \RuntimeException('Se requieren al menos dos registros para reordenar la prioridad.');
            }

            $inicioOriginal = optional($registros->first()->FechaInicio)
                ? Carbon::parse($registros->first()->FechaInicio)
                : null;

            if (!$inicioOriginal) {
                throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');
            }

            // Encontrar la posición actual del registro
            $idxActual = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idxActual === false) {
                throw new \RuntimeException('No se encontró el registro a reordenar dentro del telar.');
            }

            // VALIDACIÓN: No se puede colocar antes de un registro en proceso
            // Encontrar el último registro en proceso
            $ultimoEnProcesoIndex = -1;
            foreach ($registros as $index => $reg) {
                if ($reg->EnProceso == 1) {
                    $ultimoEnProcesoIndex = $index;
                }
            }

            // Si hay registros en proceso, la posición mínima debe ser después del último
            if ($ultimoEnProcesoIndex !== -1) {
                $posicionMinima = $ultimoEnProcesoIndex + 1;
                if ($nuevaPosicion < $posicionMinima) {
                    throw new \RuntimeException('No se puede colocar un registro antes de uno que está en proceso. La posición mínima permitida es ' . ($posicionMinima + 1) . '.');
                }
            }

            // Validar nueva posición
            if ($nuevaPosicion < 0 || $nuevaPosicion >= $registros->count()) {
                throw new \RuntimeException('La nueva posición está fuera del rango válido.');
            }

            if ($idxActual === $nuevaPosicion) {
                throw new \RuntimeException('El registro ya está en esa posición.');
            }

            // Guardar IDs de los registros afectados para regenerar líneas después
            $idsAfectados = $registros->pluck('Id')->toArray();

            // Mover el registro a la nueva posición
            $registroMovido = $registros->splice($idxActual, 1)->first();
            $registros->splice($nuevaPosicion, 0, [$registroMovido]);
            $registros = $registros->values();

            // Deshabilitar observers temporalmente para evitar regeneración duplicada de líneas
            ReqProgramaTejido::unsetEventDispatcher();

            // Recalcular fechas para toda la secuencia
            [$updates, $detalles] = $this->recalcularFechasSecuencia($registros, $inicioOriginal);

            // Actualizar registros principales (esto cambia las fechas)
            foreach ($updates as $idU => $data) {
                DB::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
            }

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Regenerar líneas para TODOS los registros afectados
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsAfectados as $idAct) {
                if ($r = ReqProgramaTejido::find($idAct)) {
                    $observer->saved($r);
                }
            }

            Log::info('moverAposicion OK', [
                'id' => $registro->Id,
                'posicion_anterior' => $idxActual,
                'posicion_nueva' => $nuevaPosicion,
                'registros_afectados' => count($idsAfectados),
                'detalles_cascada' => count($detalles)
            ]);

            return ['success' => true, 'detalles' => $detalles];

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('moverAposicion error', [
                'id' => $registro->Id ?? null,
                'nueva_posicion' => $nuevaPosicion,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /* ======================================
     |  HELPERS PRIVADOS (reasignan sin romper tu lógica)
     |======================================*/
    private function applyCantidad(ReqProgramaTejido $r, array $data): void
    {
        if (!array_key_exists('cantidad', $data)) return;

        $nueva = $data['cantidad'];
        Log::info('Actualizando cantidad', ['Id'=>$r->Id,'SaldoPedido'=>$r->SaldoPedido,'Produccion'=>$r->Produccion,'nueva'=>$nueva]);

        if (!is_null($r->SaldoPedido))       $r->SaldoPedido = $nueva;
        elseif (!is_null($r->Produccion))    $r->Produccion  = $nueva;
        else                                 $r->SaldoPedido = $nueva;
    }

    private function setSafeDate(ReqProgramaTejido $r, string $attr, $value): void
    {
        try { $r->{$attr} = Carbon::parse($value); } catch (\Throwable $e) { /* silent */ }
    }

    private function applyCalculados(ReqProgramaTejido $r, array $d): void
    {
        // PesoGRM2 es float en BD: respetamos el valor decimal calculado (frontend u observer)
        if (array_key_exists('peso_grm2',$d))           $r->PesoGRM2      = is_null($d['peso_grm2']) ? null : (float) $d['peso_grm2'];
        if (array_key_exists('dias_eficiencia',$d))     $r->DiasEficiencia= $d['dias_eficiencia'];
        if (array_key_exists('prod_kg_dia',$d))         $r->ProdKgDia     = $d['prod_kg_dia'];
        if (array_key_exists('std_dia',$d))             $r->StdDia        = $d['std_dia'];
        if (array_key_exists('prod_kg_dia2',$d))        $r->ProdKgDia2    = $d['prod_kg_dia2'];
        if (array_key_exists('std_toa_hra',$d))         $r->StdToaHra     = $d['std_toa_hra'];
        if (array_key_exists('dias_jornada',$d))        $r->DiasJornada   = $d['dias_jornada'];
        if (array_key_exists('horas_prod',$d))          $r->HorasProd     = $d['horas_prod'];
        if (array_key_exists('std_hrs_efect',$d))       $r->StdHrsEfect   = $d['std_hrs_efect'];
    }

    private function applyEficienciaVelocidad(ReqProgramaTejido $r, Request $req, array $d): void
    {
        $ef = $d['eficiencia_std'] ?? $req->input('EficienciaSTD') ?? $req->input('eficienciaSTD');
        $ve = $d['velocidad_std']  ?? $req->input('VelocidadSTD')  ?? $req->input('velocidadSTD');

        if ($ef !== null && is_numeric($ef)) $r->EficienciaSTD = (float) $ef;
        if ($ve !== null && is_numeric($ve)) $r->VelocidadSTD  = (float) $ve;
    }

    private function applyColoresYCalibres(ReqProgramaTejido $r, array $d): void
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
        if (array_key_exists('calibre_c4',$d))     { $r->CalibreComb42 = $d['calibre_c4']; Log::info('Actualizando Calibre C4',['Id'=>$r->Id,'CalibreComb42'=>$d['calibre_c4']]);}
        if (array_key_exists('calibre_c5',$d))     $r->CalibreComb52 = $d['calibre_c5'];

        // Fibras
        if (array_key_exists('fibra_trama',$d)) $r->FibraTrama = $d['fibra_trama'];
        if (array_key_exists('fibra_c1',$d))    $r->FibraComb1 = $d['fibra_c1'];
        if (array_key_exists('fibra_c2',$d))    $r->FibraComb2 = $d['fibra_c2'];
        if (array_key_exists('fibra_c3',$d))    $r->FibraComb3 = $d['fibra_c3'];
        if (array_key_exists('fibra_c4',$d))    $r->FibraComb4 = $d['fibra_c4'];
        if (array_key_exists('fibra_c5',$d))    $r->FibraComb5 = $d['fibra_c5'];

        // Códigos
        if (array_key_exists('cod_color_1',$d)) $r->CodColorTrama = $d['cod_color_1'];
        if (array_key_exists('cod_color_2',$d)) $r->CodColorComb2 = $d['cod_color_2'];
        if (array_key_exists('cod_color_3',$d)) $r->CodColorComb4 = $d['cod_color_3'];
        if (array_key_exists('cod_color_4',$d)) $r->CodColorComb1 = $d['cod_color_4'];
        if (array_key_exists('cod_color_5',$d)) $r->CodColorComb3 = $d['cod_color_5'];
        if (array_key_exists('cod_color_6',$d)) $r->CodColorComb5 = $d['cod_color_6'];
    }

    private function applyFlogYTipoPedido(ReqProgramaTejido $r, ?string $flog): void
    {
        if ($flog !== null) {
            $prev = $r->getOriginal('FlogsId');
            $r->FlogsId = $flog ?: null;
            Log::info('UPDATE FlogsId', ['Id'=>$r->Id,'prev'=>$prev,'nuevo'=>$r->FlogsId]);

            if ($flog && strlen($flog) >= 2) {
                $pref = strtoupper(substr($flog,0,2));
                $r->TipoPedido = $pref;
                Log::info('TipoPedido desde FlogsId', ['Id'=>$r->Id,'prefijo'=>$pref]);
            }
        }
    }

    private function extractResumen(ReqProgramaTejido $r): array
    {
        return [
            'Id' => $r->Id,
            'Ancho' => $r->Ancho,
            'EficienciaSTD' => $r->EficienciaSTD,
            'VelocidadSTD' => $r->VelocidadSTD,
            'FibraRizo' => $r->FibraRizo,
            'CalibrePie2' => $r->CalibrePie2,
            'TotalPedido' => $r->TotalPedido,
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

    private function resolverStdSegunTelar(ReqProgramaTejido $registro, ?ReqModelosCodificados $modeloDestino, string $nuevoTelar, string $nuevoSalon): array
    {
        $fibra = $registro->FibraRizo
            ?? $registro->FibraTrama
            ?? ($modeloDestino->FibraRizo ?? null)
            ?? ($modeloDestino->FibraId ?? null);

        $calibreTrama = $registro->CalibreTrama
            ?? $registro->CalibreTrama2
            ?? ($modeloDestino->CalibreTrama ?? null)
            ?? ($modeloDestino->CalibreTrama2 ?? null);

        $densidad = ($calibreTrama !== null && (float) $calibreTrama > 40) ? 'Alta' : 'Normal';

        $eficiencia = null;
        $velocidad = null;

        if ($fibra) {
            $eficiencia = ReqEficienciaStd::where('NoTelarId', $nuevoTelar)
                ->where('FibraId', $fibra)
                ->where('Densidad', $densidad)
                ->value('Eficiencia');

            $velocidad = ReqVelocidadStd::where('NoTelarId', $nuevoTelar)
                ->where('FibraId', $fibra)
                ->where('Densidad', $densidad)
                ->value('Velocidad');
        }

        if (is_null($velocidad) && $modeloDestino && !is_null($modeloDestino->VelocidadSTD)) {
            $velocidad = (float) $modeloDestino->VelocidadSTD;
        }

        return [
            $eficiencia ?? $registro->EficienciaSTD,
            $velocidad ?? $registro->VelocidadSTD,
        ];
    }

    private function marcarCambioHiloAnterior(string $salon, $noTelarId, ?string $nuevoHilo): void
    {
        try {
            $anterior = ReqProgramaTejido::query()->salon($salon)->telar($noTelarId)->where('Ultimo',1)->first();
            if (!$anterior) {
                $anterior = ReqProgramaTejido::query()->salon($salon)->telar($noTelarId)->orderByDesc('Id')->first();
            }
            if ($anterior && $anterior->FibraRizo !== null && $anterior->FibraRizo !== '' && $anterior->FibraRizo !== $nuevoHilo) {
                $anterior->CambioHilo = 1;
                $anterior->save();
                Log::info('CambioHilo marcado', ['salon'=>$salon,'telar'=>$noTelarId,'ant'=>$anterior->FibraRizo,'nuevo'=>$nuevoHilo]);
            }
        } catch (\Throwable $e) {
            Log::warning('marcarCambioHiloAnterior error', ['msg'=>$e->getMessage()]);
        }
    }

    private function aplicarCamposFormulario(ReqProgramaTejido $nuevo, Request $req): void
    {
        $campos = [
            'CuentaRizo','CalibreRizo','CalibreRizo2','InventSizeId','NombreProyecto','NombreProducto',
            'ItemId',
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
            'DobladilloId','LargoCrudo',
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
                'CalibrePie','CalibrePie2','EficienciaSTD',
                'AnchoToalla' // Guardar con decimales para evitar truncarlo a 0
            ])) {
                $valor = is_numeric($valor) ? (float)$valor : null;
            } elseif (in_array($campo, ['VelocidadSTD','Peine','PesoCrudo','MedidaPlano','Ancho','NoTiras','Luchaje','LargoCrudo'])) {
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

    private function aplicarAliasesEnNuevo(ReqProgramaTejido $nuevo, array $valoresAlias, Request $req): void
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

    private function aplicarFallbackModeloCodificado(ReqProgramaTejido $nuevo, Request $req): void
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
            // Asignar ItemId desde modelo codificado si no está ya asignado
            // Nota: CustName NO existe en ReqModelosCodificados, debe venir del request u otra fuente
            if (empty($nuevo->ItemId) && !empty($modeloCod->ItemId)) {
                $nuevo->ItemId = (string) $modeloCod->ItemId;
            }
            if (empty($nuevo->MedidaPlano) && !empty($modeloCod->MedidaPlano)) {
                $nuevo->MedidaPlano = (int)$modeloCod->MedidaPlano;
            }
            if (empty($nuevo->NombreCPie) && !empty($modeloCod->NombreCPie)) {
                $nuevo->NombreCPie = StringTruncator::truncate('NombreCPie', (string)$modeloCod->NombreCPie);
            }
        } catch (\Throwable $e) {
            Log::warning('Fallback ReqModelosCodificados', ['msg'=>$e->getMessage()]);
        }
    }
}
