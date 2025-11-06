<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\TejTrama;
use App\Models\TejTramaConsumos;
use App\Helpers\TurnoHelper;

class NuevoRequerimientoController extends Controller
{
    /**
     * Módulo: Nuevo Requerimiento
     * - Orden por Secuencia desde InvSecuenciaTrama (Tipo JACQUARD, ITEMA)
     * - Datos desde ReqProgramaTejido (ITEMA incluye SMIT)
     * NO modifica controladores existentes.
     */
    public function index()
    {
        $folioEditar = request()->query('folio');
        if ($folioEditar) {
            // Modo edición por folio: cargar datos desde TejTrama y TejTramaConsumos
            $cabecera = DB::table('TejTrama')->where('Folio', $folioEditar)->first();
            $detalles = DB::table('TejTramaConsumos')->where('Folio', $folioEditar)->get();

            // Agrupar por telar
            $consumosPorTelar = [];
            foreach ($detalles as $d) {
                $telar = (string) ($d->NoTelarId ?? '');
                if (!isset($consumosPorTelar[$telar])) {
                    $consumosPorTelar[$telar] = [
                        'salon' => (string) ($d->SalonTejidoId ?? ''),
                        'orden' => (string) ($d->NoProduccion ?? ''),
                        'producto' => (string) ($d->NombreProducto ?? ''),
                        'items' => []
                    ];
                }
                $consumosPorTelar[$telar]['items'][] = [
                    'id' => $d->Id,
                    'calibre' => $d->CalibreTrama,
                    'fibra' => (string) ($d->FibraTrama ?? ''),
                    'cod_color' => (string) ($d->CodColorTrama ?? ''),
                    'color' => (string) ($d->ColorTrama ?? ''),
                    'cantidad' => (float) ($d->Cantidad ?? 0),
                ];
            }

            $telaresEdit = array_keys($consumosPorTelar);

            return view('modulos/nuevo-requerimiento', [
                'editMode' => true,
                'editFolio' => $cabecera,
                'telaresEdit' => $telaresEdit,
                'consumosPorTelar' => $consumosPorTelar,
                // también pasamos vacíos los originales para no romper blade
                'telaresOrdenados' => [],
                'datosPorTelar' => [],
            ]);
        }

        // Secuencia de telares por tipo
        $secuenciaJacquard = DB::table('InvSecuenciaTrama')
            ->where('TipoTelar', 'JACQUARD')
            ->orderBy('Secuencia', 'asc')
            ->pluck('NoTelar')
            ->toArray();

        $secuenciaItema = DB::table('InvSecuenciaTrama')
            ->where('TipoTelar', 'ITEMA')
            ->orderBy('Secuencia', 'asc')
            ->pluck('NoTelar')
            ->toArray();

        $telaresOrdenados = array_merge($secuenciaJacquard, $secuenciaItema);

        // Verificar si hay un folio "En Proceso" en TejTrama
        $folioEnProceso = TejTrama::where('Status', 'En Proceso')->first();

        // CAMBIO: No redirigir automáticamente, permitir crear nuevo requerimiento
        // El usuario puede continuar con el existente desde "Consultar Requerimiento"

        // Cargar datos desde ReqProgramaTejido para mostrar información de telares
        $datosPorTelar = [];
        foreach ($telaresOrdenados as $telar) {
            $tipoSalon = $this->determinarTipoSalon($telar);
            $salones = $tipoSalon === 'ITEMA' ? ['ITEMA', 'SMIT'] : [$tipoSalon];

            // Para ITEMA, intentar primero con el número original, luego con el convertido
            $noTelarDb = $telar;
            if ($tipoSalon === 'ITEMA') {
                // Primero intentar con el número original (3XX)
                $enProceso = DB::table('ReqProgramaTejido')
                    ->whereIn('SalonTejidoId', $salones)
                    ->where('NoTelarId', $telar)
                    ->where('EnProceso', 1);

                // Si no encuentra nada, intentar con formato convertido (1XX)
                if (!$enProceso->exists()) {
                    $noTelarDb = 100 + ($telar % 100); // 318 -> 118
                    $enProceso = DB::table('ReqProgramaTejido')
                        ->whereIn('SalonTejidoId', $salones)
                        ->where('NoTelarId', $noTelarDb)
                        ->where('EnProceso', 1);
                }
            } else {
                $enProceso = DB::table('ReqProgramaTejido')
                    ->whereIn('SalonTejidoId', $salones)
                    ->where('NoTelarId', $telar)
                    ->where('EnProceso', 1);
            }

            $enProceso = $enProceso
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
                // Trama principal
                    'CuentaPie as Cuenta_Pie',
                    'CalibrePie as Calibre_Pie',
                    'FibraPie as Fibra_Pie',
                    'CalibreTrama as CALIBRE_TRA',
                    'ColorTrama as COLOR_TRAMA',
                'FibraTrama as FIBRA_TRA',
                'CodColorTrama as CODIGO_COLOR_TRAMA',
                    'TotalPedido as Saldos',
                'Produccion as Prod_Kg_Dia',
                'Produccion',
                    'SaldoMarbete as Marbetes_Pend',
                    'SaldoMarbete as MarbetesPend',
                    'FechaInicio as Inicio_Tejido',
                    'FechaFinal as Fin_Tejido',
                'EntregaCte as Fecha_Compromiso',
                'InventSizeId',
                // Campos de combinaciones (si existen en la tabla)
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
                'FibraComb5 as FIBRA_C5',
                'CodColorComb1 as CODIGO_COLOR_C1',
                'CodColorComb2 as CODIGO_COLOR_C2',
                'CodColorComb3 as CODIGO_COLOR_C3',
                'CodColorComb4 as CODIGO_COLOR_C4',
                'CodColorComb5 as CODIGO_COLOR_C5',
                ])
                ->first();

            $ordenSig = null;
            if ($enProceso && $enProceso->Inicio_Tejido) {
                $ordenSig = DB::table('ReqProgramaTejido')
                    ->whereIn('SalonTejidoId', $salones)
                    ->where('NoTelarId', $noTelarDb)
                    ->where('EnProceso', 0)
                    ->where('FechaInicio', '>', $enProceso->Inicio_Tejido)
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
                        'EntregaCte as Entrega',
                    ])
                    ->orderBy('FechaInicio')
                    ->first();
            }

            // Ajuste visual para ITEMA (mostrar 3XX)
            if ($enProceso && $tipoSalon === 'ITEMA') {
                $enProceso->Telar = $telar;
            }

            if (!$enProceso) {
                $enProceso = (object) [
                    'Telar' => $telar,
                    'en_proceso' => false,
                ];
            }

            $datosPorTelar[$telar] = [
                'telarData' => $enProceso,
                'ordenSig' => $ordenSig,
                'tipo' => strtolower($tipoSalon),
            ];
        }

        return view('modulos/nuevo-requerimiento', [
            'telaresOrdenados' => $telaresOrdenados,
            'datosPorTelar' => $datosPorTelar,
        ]);
    }

    private function determinarTipoSalon(int $telar): string
    {
        if ($telar >= 201 && $telar <= 215) {
            return 'JACQUARD';
        }
        if ($telar >= 299 && $telar <= 320) {
            return 'ITEMA';
        }
        return 'JACQUARD';
    }

    private function resolverNoTelarOriginal(int $telar): int
    {
        // Para ITEMA convertimos 3XX a 1XX si aplica (BD puede tener 1XX)
        if ($telar >= 300 && $telar < 400) {
            return 100 + ($telar % 100);
        }
        return $telar;
    }

    /**
     * Guardar requerimientos de trama
     */
    public function guardarRequerimientos(Request $request)
    {
        try {
            DB::beginTransaction();

            // Soportar edición por folio (si viene en el request)
            $providedFolio = trim((string) ($request->input('folio') ?? ''));

            // Buscar folio En Proceso existente; si no, crear uno nuevo
            $turno = TurnoHelper::getTurnoActual();
            $fecha = now('America/Mexico_City')->toDateString();

            if ($providedFolio !== '') {
                // Edición: validar que exista el folio
                $registro = TejTrama::query()->where('Folio', $providedFolio)->lockForUpdate()->first();
                if (!$registro) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'El folio indicado no existe'
                    ], 404);
                }

                // Actualizar información del empleado en edición
                $usuario = Auth::user();
                $numeroEmpleado = $usuario->numero_empleado ?? '';
                $nombreEmpleado = $usuario->nombre ?? '';

                Log::info('Actualizando información del usuario en edición:', [
                    'folio' => $providedFolio,
                    'user_id' => $usuario->id ?? 'N/A',
                    'numero_empleado' => $numeroEmpleado,
                    'nombre_empleado' => $nombreEmpleado
                ]);

                $registro->update([
                    'numero_empleado' => $numeroEmpleado,
                    'nombreEmpl' => $nombreEmpleado,
                ]);

                $folio = $registro->Folio;
            } else {
                $enProceso = TejTrama::query()
                    ->where('Status', 'En Proceso')
                    ->lockForUpdate()
                    ->first();

                if ($enProceso) {
                    $folio = $enProceso->Folio;
                } else {
                    // Generar folio incremental seguro (F0001, F0002, ...)
                    $folio = $this->generarFolioSecuencial();

                    // Obtener información del usuario autenticado
                    $usuario = Auth::user();
                    $numeroEmpleado = $usuario->numero_empleado ?? '';
                    $nombreEmpleado = $usuario->nombre ?? '';

                    Log::info('Información del usuario para nuevo requerimiento:', [
                        'user_id' => $usuario->id ?? 'N/A',
                        'numero_empleado' => $numeroEmpleado,
                        'nombre_empleado' => $nombreEmpleado,
                        'usuario_completo' => $usuario
                    ]);

                    // Crear registro principal en TejTrama
                    TejTrama::create([
                        'Folio' => $folio,
                        'Fecha' => $fecha,
                        'Status' => 'En Proceso',
                        'Turno' => $turno,
                        'numero_empleado' => $numeroEmpleado,
                        'nombreEmpl' => $nombreEmpleado,
                    ]);
                }
            }

            // Procesar consumos de cada telar (tomar del body JSON o form-data)
            $consumos = $request->input('consumos', []);
            if (!is_array($consumos)) {
                $consumos = [];
            }

            Log::info('GuardarRequerimientos - Datos recibidos', [
                'consumos_count' => count($consumos),
                'consumos' => $consumos,
                'provided_folio' => $providedFolio,
                'fecha' => $fecha,
                'turno' => $turno
            ]);

            // Procesar cada consumo individualmente con updateOrCreate
            foreach ($consumos as $consumo) {
                $calibre = isset($consumo['calibre']) && $consumo['calibre'] !== '' && $consumo['calibre'] !== null ? (float) $consumo['calibre'] : null;
                $cantidad = isset($consumo['cantidad']) ? (float) $consumo['cantidad'] : 0;

                // Truncar valores según límites de columnas
                $telar = substr($consumo['telar'] ?? '', 0, 10);
                $salon = substr($consumo['salon'] ?? '', 0, 10);
                $orden = substr($consumo['orden'] ?? '', 0, 15);
                $producto = substr($consumo['producto'] ?? '', 0, 20);
                $fibra = substr($consumo['fibra'] ?? '', 0, 15);
                $codColor = substr($consumo['cod_color'] ?? '', 0, 10);
                $color = substr($consumo['color'] ?? '', 0, 60);

                // Normalizar claves (null para vacíos)
                $fibra = ($fibra === '' || $fibra === '-') ? null : $fibra;
                $codColor = ($codColor === '' || $codColor === '-') ? null : $codColor;
                $color = ($color === '' || $color === '-') ? null : $color;

                // Buscar registro existente por clave compuesta
                $existente = TejTramaConsumos::where('Folio', substr($folio, 0, 10))
                    ->where('NoTelarId', $telar)
                    ->where('CalibreTrama', $calibre)
                    ->where('FibraTrama', $fibra)
                    ->where('CodColorTrama', $codColor)
                    ->where('ColorTrama', $color)
                    ->first();

                if ($existente) {
                    // Actualizar registro existente
                    $existente->update([
                        'SalonTejidoId' => $salon,
                        'NoProduccion' => $orden,
                        'NombreProducto' => $producto,
                        'Cantidad' => $cantidad,
                    ]);
                } else {
                    // Crear nuevo registro (incluso con cantidad 0)
                    TejTramaConsumos::create([
                        'Folio' => substr($folio, 0, 10),
                        'NoTelarId' => $telar,
                        'SalonTejidoId' => $salon,
                        'NoProduccion' => $orden,
                        'CalibreTrama' => $calibre,
                        'NombreProducto' => $producto,
                        'FibraTrama' => $fibra,
                        'CodColorTrama' => $codColor,
                        'ColorTrama' => $color,
                        'Cantidad' => $cantidad,
                    ]);
                }
            }

            // Si no hay consumos, crear al menos un registro vacío para que se genere el folio
            if (empty($consumos)) {
                Log::info('GuardarRequerimientos - No hay consumos, creando folio vacío', [
                    'folio' => $folio
                ]);
            }

            // Actualizar FechaInicio y FechaFinal en ReqProgramaTejido para los telares con consumos
            $this->actualizarFechasReqProgramaTejido($consumos, $fecha);

            Log::info('GuardarRequerimientos - Procesamiento completado', [
                'folio' => $folio,
                'consumos_procesados' => count($consumos),
                'fecha' => $fecha,
                'turno' => $turno
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Requerimientos guardados exitosamente',
                'folio' => $folio,
                'turno' => $turno
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar requerimientos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información del turno actual
     */
    public function getTurnoInfo()
    {
        $turno = TurnoHelper::getTurnoActual();
        $descripcion = TurnoHelper::getDescripcionTurno($turno);
        $folio = $this->generarFolioSugerido();

        return response()->json([
            'turno' => $turno,
            'descripcion' => $descripcion,
            'folio' => $folio
        ]);
    }

    /**
     * Genera el siguiente folio secuencial con bloqueo dentro de la transacción
     */
    private function generarFolioSecuencial(): string
    {
        // Obtener último folio existente con FOR UPDATE (simulado con orden descendente dentro de la misma transacción)
        $ultimo = TejTrama::query()->orderByDesc('Folio')->lockForUpdate()->first();
        if (!$ultimo || !preg_match('/^F(\d{4})$/', $ultimo->Folio, $m)) {
            return 'F0001';
        }
        $num = intval($m[1]) + 1;
        return 'F' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Genera un folio sugerido (sin bloqueo) para mostrar en UI
     */
    private function generarFolioSugerido(): string
    {
        $ultimo = TejTrama::query()->orderByDesc('Folio')->first();
        if (!$ultimo || !preg_match('/^F(\d{4})$/', $ultimo->Folio, $m)) {
            return 'F0001';
        }
        $num = intval($m[1]) + 1;
        return 'F' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Devuelve si existe un requerimiento en proceso y su folio
     */
    public function enProcesoInfo()
    {
        $enProceso = TejTrama::where('Status', 'En Proceso')
            ->orderByDesc('Fecha')
            ->first();

        return response()->json([
            'exists' => (bool) $enProceso,
            'folio' => $enProceso->Folio ?? null,
        ]);
    }

    /**
     * Actualizar solo la cantidad de un consumo específico
     */
    public function actualizarCantidad(Request $request)
    {
        try {
            Log::info('ActualizarCantidad - Request recibido', [
                'id' => $request->id,
                'cantidad' => $request->cantidad,
                'all_data' => $request->all()
            ]);

            $request->validate([
                'id' => 'required|integer',
                'cantidad' => 'required|numeric|min:0'
            ]);

            // Usar SQL directo para asegurar que funcione
            $sql = "UPDATE TejTramaConsumos SET Cantidad = ? WHERE Id = ?";
            $updated = DB::update($sql, [$request->cantidad, $request->id]);

            Log::info('Resultado actualización SQL directo', [
                'updated_rows' => $updated,
                'nueva_cantidad' => $request->cantidad,
                'id' => $request->id
            ]);

            if ($updated > 0) {
                // Verificar que se actualizó
                $consumoActualizado = DB::table('TejTramaConsumos')->where('Id', $request->id)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Cantidad actualizada correctamente',
                    'cantidad' => $consumoActualizado->Cantidad,
                    'updated_rows' => $updated
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro o no se pudo actualizar'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error en actualizarCantidad', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cantidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar FechaInicio y FechaFinal en ReqProgramaTejido para telares con consumos
     */
    private function actualizarFechasReqProgramaTejido(array $consumos, string $fecha)
    {
        try {
            // Obtener telares únicos que tienen consumos con sus fechas
            $telaresConConsumos = [];
            foreach ($consumos as $consumo) {
                $telar = $consumo['telar'] ?? '';
                $salon = $consumo['salon'] ?? '';
                $fechaInicio = $consumo['fecha_inicio'] ?? $fecha;
                $fechaFinal = $consumo['fecha_final'] ?? date('Y-m-d', strtotime($fecha . ' +7 days'));

                if ($telar && $salon) {
                    // Usar el telar como clave para evitar duplicados
                    $telaresConConsumos[$telar] = [
                        'telar' => $telar,
                        'salon' => $salon,
                        'fecha_inicio' => $fechaInicio,
                        'fecha_final' => $fechaFinal
                    ];
                }
            }

            // Actualizar FechaInicio y FechaFinal para cada telar
            foreach ($telaresConConsumos as $telarData) {
                $telar = $telarData['telar'];
                $salon = $telarData['salon'];
                $fechaInicio = $telarData['fecha_inicio'];
                $fechaFinal = $telarData['fecha_final'];

                // Buscar el registro en ReqProgramaTejido
                $registroReqPrograma = DB::table('ReqProgramaTejido')
                    ->where('NoTelarId', $telar)
                    ->where('SalonTejidoId', $salon)
                    ->where('EnProceso', 1)
                    ->first();

                if ($registroReqPrograma) {
                    DB::table('ReqProgramaTejido')
                        ->where('Id', $registroReqPrograma->Id)
                        ->update([
                            'FechaInicio' => $fechaInicio,
                            'FechaFinal' => $fechaFinal,
                            'UpdatedAt' => now()
                        ]);

                    Log::info('Fechas actualizadas en ReqProgramaTejido', [
                        'telar' => $telar,
                        'salon' => $salon,
                        'fecha_inicio' => $fechaInicio,
                        'fecha_final' => $fechaFinal
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar fechas en ReqProgramaTejido', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar excepción para no interrumpir el flujo principal
        }
    }
}


