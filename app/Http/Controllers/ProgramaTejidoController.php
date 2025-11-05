<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqProgramaTejido;
use App\Models\ReqModelosCodificados;
use App\Helpers\StringTruncator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramaTejidoController extends Controller
{
    /**
     * Endpoint JSON para obtener un registro por id
     */
    public function showJson(int $id)
    {
        $registro = \App\Models\ReqProgramaTejido::findOrFail($id);
        return response()->json(['success' => true, 'data' => $registro]);
    }

    public function edit(int $id)
    {
        $registro = \App\Models\ReqProgramaTejido::findOrFail($id);

        // Obtener datos de ReqModelosCodificados basado en TamanoClave
        $modeloCodificado = null;
        if ($registro->TamanoClave) {
            $modeloCodificado = \App\Models\ReqModelosCodificados::where('TamanoClave', $registro->TamanoClave)->first();
        }

        return view('modulos.programa-tejido-nuevo.edit', compact('registro', 'modeloCodificado'));
    }

    public function update(\Illuminate\Http\Request $request, int $id)
    {
        $registro = \App\Models\ReqProgramaTejido::findOrFail($id);

        $data = $request->validate([
            'cantidad' => ['nullable','numeric','min:0'],
            'fecha_fin' => ['nullable','string'],
            'nombre_color_1' => ['nullable','string'], // NombreCC1
            'nombre_color_2' => ['nullable','string'], // NombreCC2
            'nombre_color_3' => ['nullable','string'], // NombreCC3
            'nombre_color_6' => ['nullable','string'], // NombreCC5
            'calibre_trama' => ['nullable','numeric'], // CalibreTrama
            'calibre_c1' => ['nullable','numeric'],    // CalibreComb12
            'calibre_c2' => ['nullable','numeric'],    // CalibreComb22
            'calibre_c3' => ['nullable','numeric'],    // CalibreComb32
            'calibre_c4' => ['nullable','numeric'],    // CalibreComb42
            'calibre_c5' => ['nullable','numeric'],    // CalibreComb52
            'fibra_trama' => ['nullable','string'],    // FibraTrama
            'fibra_c1' => ['nullable','string'],       // FibraComb1
            'fibra_c2' => ['nullable','string'],       // FibraComb2
            'fibra_c3' => ['nullable','string'],       // FibraComb3
            'fibra_c4' => ['nullable','string'],       // FibraComb4
            'fibra_c5' => ['nullable','string'],       // FibraComb5
            'cod_color_1' => ['nullable','string'],    // CodColorTrama
            'cod_color_2' => ['nullable','string'],    // CodColorComb2
            'cod_color_3' => ['nullable','string'],    // CodColorComb4
            'cod_color_4' => ['nullable','string'],    // CodColorComb1
            'cod_color_5' => ['nullable','string'],    // CodColorComb3
            'cod_color_6' => ['nullable','string'],    // CodColorComb5
            // Eficiencia y Velocidad
            'eficiencia_std' => ['nullable','numeric'],
            'velocidad_std' => ['nullable','numeric'],
            // Campos calculados opcionales
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

        if (array_key_exists('cantidad', $data)) {
            $nuevaCantidad = $data['cantidad'];
            \Illuminate\Support\Facades\Log::info('Actualizando cantidad ReqProgramaTejido', [
                'Id' => $registro->Id,
                'SaldoPedido_actual' => $registro->SaldoPedido,
                'Produccion_actual' => $registro->Produccion,
                'nuevaCantidad' => $nuevaCantidad,
            ]);
            // Si el registro tiene SaldoPedido definido, actualiza SaldoPedido
            if (!is_null($registro->SaldoPedido)) {
                $registro->SaldoPedido = $nuevaCantidad;
            // Si no, y Produccion está definido, actualiza Produccion
            } elseif (!is_null($registro->Produccion)) {
                $registro->Produccion = $nuevaCantidad;
            // En caso contrario, cae en SaldoPedido por defecto
            } else {
                $registro->SaldoPedido = $nuevaCantidad;
            }
            \Illuminate\Support\Facades\Log::info('Cantidad actualizada', [
                'SaldoPedido_nuevo' => $registro->SaldoPedido,
                'Produccion_nuevo' => $registro->Produccion,
            ]);
        }

        //  CAPTURAR VALOR ORIGINAL DE FECHAFINAL ANTES DE CUALQUIER CAMBIO
        $fechaFinalOriginal = $registro->FechaFinal ? \Carbon\Carbon::parse($registro->FechaFinal) : null;

        // Actualizar FechaFinal si viene
        if (!empty($data['fecha_fin'] ?? null)) {
            try {
                $registro->FechaFinal = \Carbon\Carbon::parse($data['fecha_fin']);
            } catch (\Throwable $e) {
                // Ignorar parse fallido para no romper actualización de otros campos
            }
        }

        // Campos calculados (si vienen en el payload)
        // En BD, PesoGRM2 es entero: redondear para evitar fallos de conversión
        if (array_key_exists('peso_grm2', $data)) {
            $registro->PesoGRM2 = is_null($data['peso_grm2']) ? null : (int) round((float) $data['peso_grm2']);
        }
        if (array_key_exists('dias_eficiencia', $data)) { $registro->DiasEficiencia = $data['dias_eficiencia']; }
        if (array_key_exists('prod_kg_dia', $data)) { $registro->ProdKgDia = $data['prod_kg_dia']; }
        if (array_key_exists('std_dia', $data)) { $registro->StdDia = $data['std_dia']; }
        if (array_key_exists('prod_kg_dia2', $data)) { $registro->ProdKgDia2 = $data['prod_kg_dia2']; }
        if (array_key_exists('std_toa_hra', $data)) { $registro->StdToaHra = $data['std_toa_hra']; }
        if (array_key_exists('dias_jornada', $data)) { $registro->DiasJornada = $data['dias_jornada']; }
        if (array_key_exists('horas_prod', $data)) { $registro->HorasProd = $data['horas_prod']; }
        if (array_key_exists('std_hrs_efect', $data)) { $registro->StdHrsEfect = $data['std_hrs_efect']; }

        // Actualizar EficienciaSTD y VelocidadSTD (acepta snake_case y camelCase)
        $eficiencia = $data['eficiencia_std'] ?? $request->input('EficienciaSTD') ?? $request->input('eficienciaSTD');
        $velocidad = $data['velocidad_std'] ?? $request->input('VelocidadSTD') ?? $request->input('velocidadSTD');

        if ($eficiencia !== null && is_numeric($eficiencia)) {
            $registro->EficienciaSTD = (float) $eficiencia;
        }
        if ($velocidad !== null && is_numeric($velocidad)) {
            $registro->VelocidadSTD = (float) $velocidad;
        }

        // Actualización de nombres de color
        if (array_key_exists('nombre_color_1', $data)) {
            $registro->NombreCC1 = $data['nombre_color_1'];
        }
        if (array_key_exists('nombre_color_3', $data)) {
            $registro->NombreCC3 = $data['nombre_color_3'];
        }
        if (array_key_exists('nombre_color_2', $data)) {
            $registro->NombreCC2 = $data['nombre_color_2'];
        }
        if (array_key_exists('nombre_color_6', $data)) {
            $registro->NombreCC5 = $data['nombre_color_6'];
        }

        // Actualización de calibres C1/C3/C5
        if (array_key_exists('calibre_trama', $data)) {
            $registro->CalibreTrama = $data['calibre_trama'];
        }
        if (array_key_exists('calibre_c1', $data)) {
            $registro->CalibreComb12 = $data['calibre_c1'];
        }
        if (array_key_exists('calibre_c2', $data)) {
            $registro->CalibreComb22 = $data['calibre_c2'];
        }
        if (array_key_exists('calibre_c3', $data)) {
            $registro->CalibreComb32 = $data['calibre_c3'];
        }
        if (array_key_exists('calibre_c4', $data)) {
            $registro->CalibreComb42 = $data['calibre_c4'];
            Log::info('Actualizando Calibre C4', ['Id' => $registro->Id, 'CalibreComb42' => $data['calibre_c4']]);
        }
        if (array_key_exists('calibre_c5', $data)) {
            $registro->CalibreComb52 = $data['calibre_c5'];
        }

        // Fibras
        if (array_key_exists('fibra_trama', $data)) { $registro->FibraTrama = $data['fibra_trama']; }
        if (array_key_exists('fibra_c1', $data)) { $registro->FibraComb1 = $data['fibra_c1']; }
        if (array_key_exists('fibra_c2', $data)) { $registro->FibraComb2 = $data['fibra_c2']; }
        if (array_key_exists('fibra_c3', $data)) { $registro->FibraComb3 = $data['fibra_c3']; }
        if (array_key_exists('fibra_c4', $data)) { $registro->FibraComb4 = $data['fibra_c4']; }
        if (array_key_exists('fibra_c5', $data)) { $registro->FibraComb5 = $data['fibra_c5']; }

        // Códigos de color
        if (array_key_exists('cod_color_1', $data)) { $registro->CodColorTrama = $data['cod_color_1']; }
        if (array_key_exists('cod_color_2', $data)) { $registro->CodColorComb2 = $data['cod_color_2']; }
        if (array_key_exists('cod_color_3', $data)) { $registro->CodColorComb4 = $data['cod_color_3']; }
        if (array_key_exists('cod_color_4', $data)) { $registro->CodColorComb1 = $data['cod_color_4']; }
        if (array_key_exists('cod_color_5', $data)) { $registro->CodColorComb3 = $data['cod_color_5']; }
        if (array_key_exists('cod_color_6', $data)) { $registro->CodColorComb5 = $data['cod_color_6']; }

        // ✅ Actualizar FechaFinal si fue calculado en el frontend
        $fechaFinalCambiada = false;
        if (array_key_exists('fecha_fin', $data) && !empty($data['fecha_fin'])) {
            $nuevaFechaFinal = \Carbon\Carbon::parse($data['fecha_fin']);

            // Comparar con el valor ORIGINAL capturado antes de cualquier cambio
            // Si no había valor original, o si cambió, marcar como cambiado
            if (!$fechaFinalOriginal || !$fechaFinalOriginal->equalTo($nuevaFechaFinal)) {
                $registro->FechaFinal = $nuevaFechaFinal;
                $fechaFinalCambiada = true;
                Log::info('UPDATE - FechaFinal cambió detectado', [
                    'Id' => $registro->Id,
                    'FechaFinal_original' => $fechaFinalOriginal ? $fechaFinalOriginal->format('Y-m-d H:i:s') : 'NULL',
                    'FechaFinal_nueva' => $nuevaFechaFinal->format('Y-m-d H:i:s')
                ]);
            } else {
                Log::info('UPDATE - FechaFinal SIN CAMBIO', [
                    'Id' => $registro->Id,
                    'FechaFinal' => $fechaFinalOriginal->format('Y-m-d H:i:s')
                ]);
            }
        }

        $registro->save();

        // ✅ CASCADING DE FECHAS: Si cambió FechaFinal, actualizar registros siguientes
        $registrosCascadeados = [];
        Log::info('UPDATE - Iniciando cascading', [
            'Id' => $registro->Id,
            'fecha_fin_recibida' => $data['fecha_fin'] ?? 'NO RECIBIDA',
            'FechaFinal_actual' => $registro->FechaFinal,
            'FechaFinal_cambio_detectado' => $fechaFinalCambiada
        ]);

        // Ejecutar cascading solo si FechaFinal fue realmente modificada
        if ($fechaFinalCambiada && !empty($data['fecha_fin'])) {
            try {
                $registrosCascadeados = $this->cascadeFechas($registro);
                Log::info('UPDATE - Cascading completado con éxito', [
                    'Id' => $registro->Id,
                    'registros_actualizados' => count($registrosCascadeados),
                    'detalles' => $registrosCascadeados
                ]);
            } catch (\Throwable $e) {
                Log::error('Error en cascading de fechas', [
                    'Id' => $registro->Id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // No detener la actualización si el cascading falla
            }
        } else {
            Log::info('UPDATE - Cascading NO ejecutado', [
                'Id' => $registro->Id,
                'fecha_fin' => $data['fecha_fin'] ?? null,
                'FechaFinal_cambio_detectado' => $fechaFinalCambiada
            ]);
        }

        // ✅ EL OBSERVER SE ENCARGA DE GENERAR LAS LÍNEAS AUTOMÁTICAMENTE
        // No duplicar la lógica aquí

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'cascaded_records' => count($registrosCascadeados),
            'detalles' => $registrosCascadeados,
            'data' => [
                'Id' => $registro->Id,
                'SaldoPedido' => $registro->SaldoPedido,
                'Produccion' => $registro->Produccion,
                'NombreCC1' => $registro->NombreCC1,
                'NombreCC2' => $registro->NombreCC2,
                'NombreCC3' => $registro->NombreCC3,
                'NombreCC5' => $registro->NombreCC5,
                'CalibreTrama' => $registro->CalibreTrama,
                'CalibreComb12' => $registro->CalibreComb12,
                'CalibreComb22' => $registro->CalibreComb22,
                'CalibreComb32' => $registro->CalibreComb32,
                'CalibreComb42' => $registro->CalibreComb42,
                'CalibreComb52' => $registro->CalibreComb52,
                'FibraTrama' => $registro->FibraTrama,
                'FibraComb1' => $registro->FibraComb1,
                'FibraComb2' => $registro->FibraComb2,
                'FibraComb3' => $registro->FibraComb3,
                'FibraComb4' => $registro->FibraComb4,
                'FibraComb5' => $registro->FibraComb5,
                'CodColorTrama' => $registro->CodColorTrama,
                'CodColorComb1' => $registro->CodColorComb1,
                'CodColorComb2' => $registro->CodColorComb2,
                'CodColorComb3' => $registro->CodColorComb3,
                'CodColorComb4' => $registro->CodColorComb4,
                'CodColorComb5' => $registro->CodColorComb5,
            ],
        ]);
    }
    /**
     * Crear nuevas órdenes de programa de tejido.
     * - Marca como Ultimo=0 el anterior registro del mismo salón/telar
     * - Inserta el nuevo con Ultimo=1
     */
    public function store(Request $request)
    {
        $payload = $request->all();
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

        try {
            DB::beginTransaction();

            $salon = $request->input('salon_tejido_id');
            $tamanoClave = $request->input('tamano_clave');
            $hilo = $request->input('hilo');
            $flogsId = $request->input('idflog');
            $calendarioId = $request->input('calendario_id');
            $aplicacionId = $request->input('aplicacion_id');

            // Log de payload recibido (claves principales + tamaño)
            Log::info('ProgramaTejido.store - payload recibido', [
                'keys' => array_keys($request->all()),
                'count' => count($request->all()),
                'salon' => $salon,
                'tamanoClave' => $tamanoClave,
                'hilo' => $hilo,
            ]);

            // Normaliza alias de campos críticos que pueden venir con otros nombres
            $aliasToDb = [
                'NombreProducto' => ['Nombre', 'NombreProducto', 'Modelo', 'Producto'],
                'NoTiras'      => ['NoTiras', 'Tiras'],
                // CUALQUIER valor largo/largo toalla/altura debería guardarse en Luchaje
                'Luchaje'      => ['Luchaje', 'LargoToalla', 'Largo', 'Altura', 'Alto'],
                'ColorTrama'   => ['ColorTrama'],
                'NombreCC1'    => ['NombreCC1', 'NomColorC1'],
                'NombreCC2'    => ['NombreCC2', 'NomColorC2'],
                'MedidaPlano'  => ['MedidaPlano', 'Plano'],
                'NombreCPie'   => ['NombreCPie', 'Color Pie', 'Nombre C Pie'],
                'PasadasTrama' => ['PasadasTrama', 'Total'],
                'CodColorComb2'=> ['CodColorC2', 'FibraC2', 'FibraComb2'],
            ];

            $aplicados = [];
            foreach ($aliasToDb as $dbField => $aliases) {
                foreach ($aliases as $alias) {
                    if ($request->has($alias) && $request->input($alias) !== null && $request->input($alias) !== '') {
                        $val = $request->input($alias);
                        // Cast básicos según campo
                        if (in_array($dbField, ['NoTiras', 'Luchaje', 'MedidaPlano', 'PasadasTrama'])) {
                            $val = is_numeric($val) ? (int)$val : $val;
                        } else if (in_array($dbField, ['NombreProducto', 'ColorTrama', 'NombreCC1', 'NombreCC2', 'NombreCPie', 'CodColorComb2'])) {
                            $val = (string)$val;
                        }
                        $aplicados[$dbField] = $val;
                        break;
                    }
                }
            }

            // Aplica al modelo base si ya está creado más abajo; de momento guardamos en $valoresAlias
            $valoresAlias = $aplicados;
            Log::info('ProgramaTejido.store - alias aplicados', $valoresAlias);

            $creados = [];

            foreach ($request->input('telares', []) as $fila) {
                $noTelarId = $fila['no_telar_id'];

                // ✅ DETECTAR CAMBIO DE HILO desde el registro anterior ANTES de actualizar Ultimo
                // Si el telar tenía un registro anterior con diferente hilo,
                // marcar CambioHilo = 1 EN EL REGISTRO ANTERIOR
                $anterior = null;
                $hayCambioHilo = false;
                try {
                    // Primero buscar registro con Ultimo = 1
                    $anterior = ReqProgramaTejido::where('SalonTejidoId', $salon)
                        ->where('NoTelarId', $noTelarId)
                        ->where('Ultimo', 1)
                        ->first();

                    // Si no hay con Ultimo = 1, buscar el más reciente por ID
                    if (!$anterior) {
                        $anterior = ReqProgramaTejido::where('SalonTejidoId', $salon)
                            ->where('NoTelarId', $noTelarId)
                            ->orderBy('Id', 'desc')
                            ->first();
                    }

                    if ($anterior && $anterior->FibraRizo !== $hilo && $anterior->FibraRizo !== null && $anterior->FibraRizo !== '') {
                        // Marcar CambioHilo = 1 en el registro anterior
                        $anterior->CambioHilo = 1;
                        $anterior->save();
                        $hayCambioHilo = true;
                        Log::info('ProgramaTejido.store - Cambio de hilo detectado y marcado', [
                            'Salon' => $salon,
                            'Telar' => $noTelarId,
                            'IdAnterior' => $anterior->Id,
                            'HiloAnterior' => $anterior->FibraRizo,
                            'HiloNuevo' => $hilo,
                            'CambioHiloMarcado' => 1,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('ProgramaTejido.store - Error al detectar cambio de hilo', [
                        'error' => $e->getMessage(),
                    ]);
                }

                // Quitar bandera Ultimo del registro anterior del mismo telar/salón
                // Buscar registros con Ultimo = 1 o Ultimo = NULL (que indica último)
                DB::statement("
                    UPDATE ReqProgramaTejido
                    SET Ultimo = 0
                    WHERE SalonTejidoId = ?
                    AND NoTelarId = ?
                    AND (CAST(Ultimo AS NVARCHAR) = '1' OR CAST(Ultimo AS NVARCHAR) = 'UL')
                ", [$salon, $noTelarId]);

                // Crear nuevo registro como Ultimo
                $nuevo = new ReqProgramaTejido();
                $nuevo->EnProceso = 0;
                $nuevo->SalonTejidoId = $salon;
                $nuevo->NoTelarId = $noTelarId;
                $nuevo->Ultimo = 1;
                $nuevo->TamanoClave = $tamanoClave;
                $nuevo->FibraRizo = $hilo; // Guardar Hilo seleccionado en FibraRizo
                $nuevo->FlogsId = $flogsId;
                $nuevo->CalendarioId = $calendarioId;
                $nuevo->AplicacionId = $aplicacionId;

                // Fechas y cantidades (opcionales por ahora)
                $nuevo->FechaInicio = $fila['fecha_inicio'] ?? null;
                $nuevo->FechaFinal = $fila['fecha_final'] ?? null;
                $nuevo->EntregaProduc = $fila['compromiso_tejido'] ?? null;
                $nuevo->EntregaCte = $fila['fecha_cliente'] ?? null;
                $nuevo->EntregaPT = $fila['fecha_entrega'] ?? null;
                $nuevo->TotalPedido = $fila['cantidad'] ?? null;

                // CambioHilo: no establecer aquí, se maneja arriba con la detección del registro anterior
                // Solo establecer CambioHilo = 0 por defecto
                $nuevo->CambioHilo = 0;

                // Maquina: usar el valor del payload principal si existe
                if ($request->has('Maquina') && $request->input('Maquina') !== null) {
                    $nuevo->Maquina = $request->input('Maquina');
                }

                // Campos que vienen del formulario y se deben guardar (solo los que existen en la tabla)
                $camposFormulario = [
                    // Campos básicos del formulario
                    'CuentaRizo', 'CalibreRizo', 'CalibreRizo2', 'InventSizeId', 'NombreProyecto', 'NombreProducto',

                    // Campos adicionales
                    'Ancho', 'EficienciaSTD', 'VelocidadSTD', 'Maquina',

                    // Campos de Trama
                    'CodColorTrama', 'ColorTrama', 'CalibreTrama', 'CalibreTrama2', 'FibraTrama',

                    // Combinaciones C1-C5 - Campos base (rosas) y campos *2 (verdes)
                    'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorComb1', 'NombreCC1',
                    'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorComb2', 'NombreCC2',
                    'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorComb3', 'NombreCC3',
                    'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorComb4', 'NombreCC4',
                    'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorComb5', 'NombreCC5',

                    // Pie
                    'CalibrePie', 'CalibrePie2', 'CuentaPie', 'FibraPie', 'CodColorCtaPie', 'NombreCPie',

                    // Medidas y especificaciones
                    'AnchoToalla', 'PesoCrudo', 'Peine', 'MedidaPlano', 'NoTiras', 'Luchaje', 'Rasurado',

                    // Pasadas
                    'PasadasTrama', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5',

                    // Otros campos
                    'DobladilloId',

                    // Campos adicionales que pueden venir del request
                    'Produccion', 'SaldoPedido', 'SaldoMarbete', 'ProgramarProd', 'NoProduccion', 'Programado',
                    'CustName', 'Observaciones', 'TipoPedido', 'PesoGRM2',
                    'DiasEficiencia', 'ProdKgDia', 'StdDia', 'ProdKgDia2', 'StdToaHra', 'DiasJornada',
                    'HorasProd', 'StdHrsEfect', 'Calc4', 'Calc5', 'Calc6'
                ];

                foreach ($camposFormulario as $campo) {
                    if ($request->has($campo) && $request->input($campo) !== null && $request->input($campo) !== '') {
                        $valor = $request->input($campo);

                        // Tipado
                        if (in_array($campo, ['CalibreRizo', 'CalibreRizo2', 'CalibreTrama', 'CalibreTrama2', 'CalibreComb1', 'CalibreComb12', 'CalibreComb2', 'CalibreComb22', 'CalibreComb3', 'CalibreComb32', 'CalibreComb4', 'CalibreComb42', 'CalibreComb5', 'CalibreComb52', 'CalibrePie', 'CalibrePie2', 'EficienciaSTD', 'VelocidadSTD'])) {
                            $valor = is_numeric($valor) ? (float)$valor : null;
                        } elseif (in_array($campo, ['Peine', 'PesoCrudo', 'AnchoToalla', 'MedidaPlano', 'Ancho', 'NoTiras', 'Luchaje'])) {
                            $valor = is_numeric($valor) ? (int)$valor : null;
                        } elseif (in_array($campo, ['TotalPedido', 'Produccion', 'SaldoPedido', 'SaldoMarbete', 'PesoGRM2', 'DiasEficiencia', 'ProdKgDia', 'StdDia', 'ProdKgDia2', 'StdToaHra', 'DiasJornada', 'HorasProd', 'StdHrsEfect', 'Calc4', 'Calc5', 'Calc6'])) {
                            $valor = is_numeric($valor) ? (float)$valor : null;
                        } elseif (in_array($campo, ['FibraTrama', 'FibraComb1', 'FibraComb2', 'FibraComb3', 'FibraComb4', 'FibraComb5', 'FibraPie', 'FibraRizo', 'CodColorTrama', 'ColorTrama', 'CodColorComb1', 'NombreCC1', 'CodColorComb2', 'NombreCC2', 'CodColorComb3', 'NombreCC3', 'CodColorComb4', 'NombreCC4', 'CodColorComb5', 'NombreCC5', 'CodColorCtaPie', 'NombreCPie', 'InventSizeId', 'NombreProyecto', 'NombreProducto', 'Maquina', 'Rasurado'])) {
                            $valor = (string)$valor;
                            // ✅ TRUNCAR VALORES STRING SEGÚN LÍMITES DE BD
                            $valor = StringTruncator::truncate($campo, $valor);
                        }

                        if ($valor !== null) {
                            $nuevo->{$campo} = $valor;
                            // Log para debugging de fórmulas
                            if (in_array($campo, ['DiasEficiencia', 'StdHrsEfect', 'ProdKgDia', 'ProdKgDia2'])) {
                                Log::info("ProgramaTejido.store - Guardando {$campo}", [
                                    'valor_request' => $request->input($campo),
                                    'valor_convertido' => $valor,
                                    'telar' => $noTelarId,
                                    'fecha_inicio' => $fila['fecha_inicio'],
                                    'fecha_final' => $fila['fecha_final']
                                ]);
                            }
                        }
                    }
                }

                // FORZAR MAPEOS DE ALTURA/LARGO A LUCHAJE (LCR)
                foreach ($valoresAlias as $dbField => $val) {
                    if ($dbField === 'Luchaje' && $val !== null && $val !== '') {
                        $nuevo->Luchaje = is_numeric($val) ? (int)$val : $nuevo->Luchaje;
                        continue;
                    }
                    if ($val !== null && $val !== '') {
                        if (!isset($nuevo->{$dbField}) || $nuevo->{$dbField} === null || $nuevo->{$dbField} === '') {
                            $nuevo->{$dbField} = $val;
                        }
                    }
                }

                // Fallback desde ReqModelosCodificados para NombreProducto/NombreProyecto si siguen nulos
                if ((empty($nuevo->NombreProducto) || $nuevo->NombreProducto === 'null') || (empty($nuevo->NombreProyecto) || $nuevo->NombreProyecto === 'null')) {
                    try {
                        $claveTc = $request->input('tamano_clave') ?? $request->input('TamanoClave');
                        $salonTc = $request->input('salon_tejido_id') ?? $request->input('SalonTejidoId');
                        if ($claveTc) {
                            $q = \App\Models\ReqModelosCodificados::query()->where('TamanoClave', $claveTc);
                            if ($salonTc) $q->where('SalonTejidoId', $salonTc);
                            $modeloCod = $q->orderByDesc('FechaTejido')->first();
                            if ($modeloCod) {
                                if (empty($nuevo->NombreProducto) || $nuevo->NombreProducto === 'null') {
                                    $nuevo->NombreProducto = StringTruncator::truncate('NombreProducto', (string)$modeloCod->Nombre);
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
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Fallback ReqModelosCodificados falló', ['msg' => $e->getMessage()]);
                    }
                }

                // Asegurar truncamiento de TODOS los campos string antes de guardar
                foreach (['NombreProducto', 'NombreProyecto', 'NombreCC1', 'NombreCC2', 'NombreCC3', 'NombreCC4', 'NombreCC5', 'NombreCPie', 'ColorTrama', 'CodColorTrama', 'Maquina', 'FlogsId', 'AplicacionId', 'CalendarioId', 'Observaciones', 'Rasurado'] as $campoString) {
                    if (isset($nuevo->{$campoString}) && is_string($nuevo->{$campoString})) {
                        $nuevo->{$campoString} = StringTruncator::truncate($campoString, $nuevo->{$campoString});
                    }
                }

                // Debug específico de campos críticos
                Log::info('ProgramaTejido.store - campos críticos recibidos', [
                    'NombreProducto' => $request->input('NombreProducto'),
                    'NombreProyecto' => $request->input('NombreProyecto'),
                    'NoTiras' => $request->input('NoTiras'),
                    'Luchaje' => $nuevo->Luchaje ?? null,
                    'ColorTrama' => $request->input('ColorTrama'),
                    'NombreCC1' => $request->input('NombreCC1'),
                    'NombreCC2' => $request->input('NombreCC2'),
                    'MedidaPlano' => $request->input('MedidaPlano'),
                    'NombreCPie' => $request->input('NombreCPie'),
                ]);

                // Log de valores truncados antes de guardar
                Log::info('ProgramaTejido.store - valores finales antes de guardar', [
                    'NombreProducto' => $nuevo->NombreProducto,
                    'NombreProyecto' => $nuevo->NombreProyecto,
                    'NombreProducto_len' => strlen($nuevo->NombreProducto ?? ''),
                    'NombreProyecto_len' => strlen($nuevo->NombreProyecto ?? ''),
                ]);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                // Log atributos realmente guardados (El ID ya está asignado por save())
                Log::info('ProgramaTejido.store - registro guardado', $nuevo->getAttributes());

                $creados[] = $nuevo;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Programa de tejido creado correctamente',
                'data' => $creados,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Log del error para debugging
            Log::error('Error al crear programa de tejido', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear programa de tejido: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Obtener opciones de SalonTejidoId desde ambas tablas
     */
    public function getSalonTejidoOptions()
    {
        // Obtener valores únicos de SalonTejidoId de ReqProgramaTejido
        $programaTejido = ReqProgramaTejido::select('SalonTejidoId')
            ->whereNotNull('SalonTejidoId')
            ->distinct()
            ->get()
            ->pluck('SalonTejidoId')
            ->filter()
            ->values();

        // Obtener valores únicos de SalonTejidoId de ReqModelosCodificados
        $modelosCodificados = ReqModelosCodificados::select('SalonTejidoId')
            ->whereNotNull('SalonTejidoId')
            ->distinct()
            ->get()
            ->pluck('SalonTejidoId')
            ->filter()
            ->values();

        // Combinar y eliminar duplicados
        $opciones = $programaTejido->merge($modelosCodificados)
            ->unique()
            ->sort()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de TamanoClave (Clave Modelo)
     */
    public function getTamanoClaveOptions()
    {
        // Obtener valores únicos de TamanoClave SOLO de ReqModelosCodificados
        $opciones = ReqModelosCodificados::select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave', '!=', '')
            ->distinct()
            ->get()
            ->pluck('TamanoClave')
            ->filter()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de TamanoClave filtradas por SalonTejidoId
     */
    public function getTamanoClaveBySalon(Request $request)
    {
        $salonTejidoId = $request->input('salon_tejido_id');
        $search = $request->input('search', '');

        $query = ReqModelosCodificados::select('TamanoClave')
            ->whereNotNull('TamanoClave')
            ->where('TamanoClave', '!=', '');

        // Filtrar por SalonTejidoId si se proporciona
        if ($salonTejidoId) {
            $query->where('SalonTejidoId', $salonTejidoId);
        }

        // Filtrar por búsqueda si se proporciona
        if ($search) {
            $query->where('TamanoClave', 'LIKE', '%' . $search . '%'); // Buscar en cualquier parte del texto
        }

        // Limitar resultados para mejor rendimiento
        $opciones = $query->distinct()
            ->limit(50) // Limitar a 50 resultados máximo
            ->get()
            ->pluck('TamanoClave')
            ->filter()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de FlogsId (IdFlog)
     */
    public function getFlogsIdOptions()
    {
        // Obtener valores únicos de FlogsId de ReqProgramaTejido
        $programaTejido = ReqProgramaTejido::select('FlogsId')
            ->whereNotNull('FlogsId')
            ->distinct()
            ->get()
            ->pluck('FlogsId')
            ->filter()
            ->values();

        // Obtener valores únicos de FlogsId de ReqModelosCodificados
        $modelosCodificados = ReqModelosCodificados::select('FlogsId')
            ->whereNotNull('FlogsId')
            ->distinct()
            ->get()
            ->pluck('FlogsId')
            ->filter()
            ->values();

        // Combinar y eliminar duplicados
        $opciones = $programaTejido->merge($modelosCodificados)
            ->unique()
            ->sort()
            ->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de CalendarioId (Calendario) desde ReqCalendarioTab
     */
    public function getCalendarioIdOptions()
    {
        // Obtener valores únicos de CalendarioId desde ReqCalendarioTab
        $opciones = DB::table('ReqCalendarioTab')
            ->select('CalendarioId')
            ->whereNotNull('CalendarioId')
            ->where('CalendarioId', '!=', '')
            ->distinct()
            ->pluck('CalendarioId')
            ->filter()
            ->values();

        // Ordenar las opciones obtenidas de la base de datos
        $opciones = $opciones->sort()->values();

        return response()->json($opciones);
    }

    /**
     * Obtener opciones de AplicacionId (Aplicación)
     */
    public function getAplicacionIdOptions()
    {
        try {
            // Obtener valores únicos de AplicacionId de ReqProgramaTejido
            $opciones = ReqProgramaTejido::select('AplicacionId')
                ->whereNotNull('AplicacionId')
                ->where('AplicacionId', '!=', '')
                ->distinct()
                ->pluck('AplicacionId')
                ->filter()
                ->values();

            // Si no hay datos en la base, devolver mensaje
            if ($opciones->isEmpty()) {
                return response()->json([
                    'mensaje' => 'No se encontraron opciones de aplicación disponibles'
                ]);
            }

            return response()->json($opciones);
        } catch (\Exception $e) {
            // En caso de error, devolver mensaje de error
            return response()->json([
                'error' => 'Error al cargar opciones de aplicación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener datos relacionados por SalonTejidoId y TamanoClave
     */
    public function getDatosRelacionados(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');
            $tamanoClave = $request->input('tamano_clave');

            if (!$salonTejidoId) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            $query = ReqModelosCodificados::where('SalonTejidoId', $salonTejidoId);

            // Si se proporciona TamanoClave, filtrar por él también
            if ($tamanoClave) {
                $query->where('TamanoClave', $tamanoClave);
            }

            // Obtener solo los campos que existen en la tabla
            $datos = $query->select(
                // Campos básicos
                'TamanoClave', 'SalonTejidoId', 'FlogsId', 'Nombre', 'NombreProyecto', 'InventSizeId',

                // Rizo
                'CuentaRizo', 'CalibreRizo', 'CalibreRizo2', 'FibraRizo',

                // Trama
                'CalibreTrama', 'CalibreTrama2', 'CodColorTrama', 'ColorTrama', 'FibraId',

                // Pie
                'CalibrePie', 'CalibrePie2', 'CuentaPie', 'FibraPie',

                // Colores C1-C5
                'CodColorC1', 'NomColorC1', 'CodColorC2', 'NomColorC2',
                'CodColorC3', 'NomColorC3', 'CodColorC4', 'NomColorC4',
                'CodColorC5', 'NomColorC5',

                // Combinaciones C1-C5 - Calibres
                'CalibreComb1', 'CalibreComb12', 'FibraComb1',
                'CalibreComb2', 'CalibreComb22', 'FibraComb2',
                'CalibreComb3', 'CalibreComb32', 'FibraComb3',
                'CalibreComb4', 'CalibreComb42', 'FibraComb4',
                'CalibreComb5', 'CalibreComb52', 'FibraComb5',

                // Medidas y especificaciones
                'AnchoToalla', 'LargoToalla', 'PesoCrudo', 'Luchaje', 'Peine',
                'NoTiras', 'Repeticiones', 'TotalMarbetes', 'CambioRepaso', 'Vendedor',
                'CatCalidad', 'AnchoPeineTrama', 'LogLuchaTotal', 'MedidaPlano', 'Rasurado',

                // Trama Fondo C1
                'CalTramaFondoC1', 'CalTramaFondoC12', 'FibraTramaFondoC1', 'PasadasTramaFondoC1',

                // Pasadas
                'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5',

                // Otros campos
                'DobladilloId', 'Obs', 'Obs1', 'Obs2', 'Obs3', 'Obs4', 'Obs5',

                // Campo Total para las fórmulas
                'Total'
            )->first();


        return response()->json([
            'datos' => $datos
        ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener eficiencia estándar basada en FibraId, NoTelarId y densidad
     */
    public function getEficienciaStd(Request $request)
    {
        $fibraId = $request->input('fibra_id');
        $noTelarId = $request->input('no_telar_id');
        $calibreTrama = $request->input('calibre_trama');

        if (!$fibraId || !$noTelarId || !$calibreTrama) {
            return response()->json(['error' => 'Faltan parámetros requeridos'], 400);
        }

        // Determinar densidad basada en calibre de trama
        $densidad = ($calibreTrama > 40) ? 'Alta' : 'Normal';

        try {
            $eficiencia = DB::table('ReqEficienciaStd')
                ->where('FibraId', $fibraId)
                ->where('NoTelarId', $noTelarId)
                ->where('Densidad', $densidad)
                ->value('Eficiencia');

            Log::info('Consulta eficiencia:', [
                'fibra_id' => $fibraId,
                'no_telar_id' => $noTelarId,
                'densidad' => $densidad,
                'resultado' => $eficiencia
            ]);

            return response()->json([
                'eficiencia' => $eficiencia,
                'densidad' => $densidad,
                'calibre_trama' => $calibreTrama
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener eficiencia estándar: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener eficiencia estándar'], 500);
        }
    }

    /**
     * Obtener velocidad estándar basada en FibraId, NoTelarId y densidad
     */
    public function getVelocidadStd(Request $request)
    {
        $fibraId = $request->input('fibra_id');
        $noTelarId = $request->input('no_telar_id');
        $calibreTrama = $request->input('calibre_trama');

        if (!$fibraId || !$noTelarId || !$calibreTrama) {
            return response()->json(['error' => 'Faltan parámetros requeridos'], 400);
        }

        // Determinar densidad basada en calibre de trama
        $densidad = ($calibreTrama > 40) ? 'Alta' : 'Normal';

        try {
            $velocidad = DB::table('ReqVelocidadStd')
                ->where('FibraId', $fibraId)
                ->where('NoTelarId', $noTelarId)
                ->where('Densidad', $densidad)
                ->value('Velocidad');

            Log::info('Consulta velocidad:', [
                'fibra_id' => $fibraId,
                'no_telar_id' => $noTelarId,
                'densidad' => $densidad,
                'resultado' => $velocidad
            ]);

            return response()->json([
                'velocidad' => $velocidad,
                'densidad' => $densidad,
                'calibre_trama' => $calibreTrama
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener velocidad estándar: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener velocidad estándar'], 500);
        }
    }

    public function getTelaresBySalon(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');

            if (!$salonTejidoId) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            // Obtener telares únicos para el salón seleccionado
            $telares = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                ->distinct()
                ->whereNotNull('NoTelarId')
                ->pluck('NoTelarId')
                ->sort()
                ->values()
                ->toArray();

            return response()->json($telares);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener telares: ' . $e->getMessage()], 500);
        }
    }

    public function getUltimaFechaFinalTelar(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');
            $noTelarId = $request->input('no_telar_id');

            if (!$salonTejidoId || !$noTelarId) {
                return response()->json(['error' => 'SalonTejidoId y NoTelarId son requeridos'], 400);
            }

            // Obtener la última fecha final, hilo, maquina y ancho del telar seleccionado
            $ultimoRegistro = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                ->where('NoTelarId', $noTelarId)
                ->whereNotNull('FechaFinal')
                ->orderBy('FechaFinal', 'desc')
                ->select('FechaFinal', 'FibraTrama', 'Maquina', 'Ancho')
                ->first();

            return response()->json([
                'ultima_fecha_final' => $ultimoRegistro ? $ultimoRegistro->FechaFinal : null,
                'hilo' => $ultimoRegistro ? $ultimoRegistro->FibraTrama : null,
                'maquina' => $ultimoRegistro ? $ultimoRegistro->Maquina : null,
                'ancho' => $ultimoRegistro ? $ultimoRegistro->Ancho : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener última fecha final: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener el último registro de un salón para detectar cambio de hilo
     */
    public function getUltimoRegistroSalon(Request $request)
    {
        try {
            $salonTejidoId = $request->input('salon_tejido_id');

            if (!$salonTejidoId) {
                return response()->json(['error' => 'SalonTejidoId es requerido'], 400);
            }

            // Obtener el último registro del salón ordenado por fecha de creación o ID
            $ultimoRegistro = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                ->orderBy('Id', 'desc')
                ->select('Hilo', 'Id', 'FechaInicio')
                ->first();

            if (!$ultimoRegistro) {
                return response()->json(['message' => 'No hay registros previos en este salón'], 200);
            }

            return response()->json([
                'Hilo' => $ultimoRegistro->Hilo,
                'Id' => $ultimoRegistro->Id,
                'FechaInicio' => $ultimoRegistro->FechaInicio
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener último registro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener opciones de hilos desde ReqMatrizHilos
     */
    public function getHilosOptions()
    {
        try {
            $opciones = \App\Models\ReqMatrizHilos::distinct()
                ->whereNotNull('Hilo')
                ->where('Hilo', '!=', '')
                ->pluck('Hilo')
                ->sort()
                ->values()
                ->toArray();

            return response()->json($opciones);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al cargar opciones de hilos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Calcular fecha final basada en fórmulas de producción
     */
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

            $telar = $request->input('telar');
            $hilo = $request->input('hilo');
            $cantidad = $request->input('cantidad');
            $fecha_inicio = $request->input('fecha_inicio');
            $tipo_calendario = $request->input('calendario');
            $salon_tejido_id = $request->input('salon_tejido_id');
            $tamano_clave = $request->input('tamano_clave');

            // Obtener datos del modelo desde ReqModelosCodificados
            $modelo = ReqModelosCodificados::where('SalonTejidoId', $salon_tejido_id)
                ->where('TamanoClave', $tamano_clave)
                ->first();

            if (!$modelo) {
                return response()->json([
                    'error' => true,
                    'message' => 'No se encontró un modelo con los datos proporcionados.'
                ], 404);
            }

            // Calcular densidad basada en Tra (asumiendo que existe en el modelo)
            $densidad = isset($modelo->Tra) && $modelo->Tra > 40 ? 'Alta' : 'Normal';

            // Obtener velocidad y eficiencia desde catálogos
            $velocidad = \App\Models\CatalagoVelocidad::where('telar', $telar)
                ->where('tipo_hilo', $hilo)
                ->where('densidad', $densidad)
                ->value('velocidad');

            $eficiencia = \App\Models\CatalagoEficiencia::where('telar', $telar)
                ->where('tipo_hilo', $hilo)
                ->where('densidad', $densidad)
                ->value('eficiencia');

            if (!$velocidad || !$eficiencia) {
                return response()->json([
                    'error' => true,
                    'message' => 'No se encontraron datos de velocidad o eficiencia para el telar y hilo seleccionados.'
                ], 404);
            }

            // Calcular Std_Toa_Hr_100
            $std_toa_hr_100 = (($modelo->NoTiras * 60) / ((($modelo->Total / 1) + (($modelo->Luchaje * 0.5) / 0.0254) / $modelo->Repeticiones) / $velocidad));

            // Calcular horas necesarias
            $horas = $cantidad / ($std_toa_hr_100 * $eficiencia);

            // Calcular fecha final usando el calendario
            $fecha_final = $this->sumarHorasCalendario($fecha_inicio, $horas, $tipo_calendario);

            return response()->json([
                'success' => true,
                'fecha_final' => $fecha_final,
                'horas_calculadas' => round($horas, 2),
                'std_toa_hr_100' => round($std_toa_hr_100, 3),
                'velocidad' => $velocidad,
                'eficiencia' => $eficiencia,
                'densidad' => $densidad
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Error al calcular fecha final: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sumar horas respetando el tipo de calendario
     */
    private function sumarHorasCalendario($fecha_inicio, $horas, $tipo_calendario)
    {
        $dias = floor($horas / 24);
        $horas_restantes = floor($horas % 24);
        $minutos = round(($horas - floor($horas)) * 60);
        $fecha = \Carbon\Carbon::parse($fecha_inicio);

        switch ($tipo_calendario) {
            case 'Calendario Tej1':
                // Suma directo
                $fecha->addDays($dias)->addHours($horas_restantes)->addMinutes($minutos);
                break;

            case 'Calendario Tej2':
                // Suma solo lunes a sábado (domingo no cuenta)
                for ($i = 0; $i < $dias; $i++) {
                    $fecha->addDay();
                    // Si es domingo, sumar 1 día más
                    if ($fecha->dayOfWeek == \Carbon\Carbon::SUNDAY) {
                        $fecha->addDay();
                    }
                }
                // Suma horas y minutos saltando domingos
                $fecha = $this->sumarHorasSinDomingo($fecha, $horas_restantes, $minutos);
                break;

            case 'Calendario Tej3':
                // Lunes a viernes completos, sábado solo hasta 18:29
                $fecha = $this->sumarHorasTej3($fecha, $dias, $horas, $minutos);
                break;

            default:
                // Por defecto, suma directo
                $fecha->addDays($dias)->addHours($horas_restantes)->addMinutes($minutos);
                break;
        }

        return $fecha->format('Y-m-d H:i:s');
    }

    /**
     * Suma horas y minutos, saltando domingos
     */
    private function sumarHorasSinDomingo($fecha, $horas, $minutos)
    {
        for ($i = 0; $i < $horas; $i++) {
            $fecha->addHour();
            if ($fecha->dayOfWeek == \Carbon\Carbon::SUNDAY) {
                $fecha->addDay();
                $fecha->setTime(0, 0); // Reinicia a las 00:00
            }
        }
        // Sumar minutos, si pasa de domingo igual salta
        for ($i = 0; $i < $minutos; $i++) {
            $fecha->addMinute();
            if ($fecha->dayOfWeek == \Carbon\Carbon::SUNDAY) {
                $fecha->addDay();
                $fecha->setTime(0, 0);
            }
        }
        return $fecha;
    }

    /**
     * Tej3: Lunes a viernes completos, sábado solo hasta 18:29
     */
    private function sumarHorasTej3($fecha, $dias, $horas, $minutos)
    {
        // Suma días, saltando domingos y controlando sábado
        for ($i = 0; $i < $dias; $i++) {
            $fecha->addDay();
            if ($fecha->dayOfWeek == \Carbon\Carbon::SUNDAY) {
                $fecha->addDay();
            }
            if (
                $fecha->dayOfWeek == \Carbon\Carbon::SATURDAY && $fecha->hour > 18 ||
                ($fecha->hour == 18 && $fecha->minute > 29)
            ) {
                // Si ya son después de las 18:29 del sábado, ir al lunes 7:00am
                $fecha->addDays(2)->setTime(7, 0);
            }
        }
        // Suma horas y minutos con control de sábado
        return $this->sumarHorasSinDomingo($fecha, $horas, $minutos);
    }

    public function moveUp(Request $request, int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);

        try {
            $resultado = $this->moverPrioridad($registro, 'up');

            return response()->json([
                'success' => true,
                'message' => 'Prioridad incrementada',
                'cascaded_records' => count($resultado['detalles']),
                'detalles' => $resultado['detalles'],
                'registro_id' => $registro->Id,
                'direccion' => 'up',
            ]);
        } catch (\Throwable $e) {
            $codigo = $e instanceof \RuntimeException ? 422 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $codigo);
        }
    }

    public function moveDown(Request $request, int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);

        try {
            $resultado = $this->moverPrioridad($registro, 'down');

            return response()->json([
                'success' => true,
                'message' => 'Prioridad decrementada',
                'cascaded_records' => count($resultado['detalles']),
                'detalles' => $resultado['detalles'],
                'registro_id' => $registro->Id,
                'direccion' => 'down',
            ]);
        } catch (\Throwable $e) {
            $codigo = $e instanceof \RuntimeException ? 422 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $codigo);
        }
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();

        try {
            $registro = ReqProgramaTejido::findOrFail($id);

            // Verificar si el registro está en proceso
            if ($registro->EnProceso == 1) {
                throw new \RuntimeException('No se puede eliminar un registro que está en proceso.');
            }

            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;
            $esUltimo = ($registro->Ultimo == '1');

            // Obtener todos los registros del telar ordenados por FechaInicio
            $registros = ReqProgramaTejido::where('SalonTejidoId', $salon)
                ->where('NoTelarId', $telar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            // Encontrar el índice del registro a eliminar
            $indiceEliminar = $registros->search(fn ($item) => $item->Id === $registro->Id);

            if ($indiceEliminar === false) {
                throw new \RuntimeException('No se encontró el registro a eliminar dentro del telar.');
            }

            // Guardar la fecha de inicio del primer registro antes de eliminar
            $primerRegistro = $registros->first();
            $fechaInicioOriginal = $primerRegistro->FechaInicio ? \Carbon\Carbon::parse($primerRegistro->FechaInicio) : null;

            if (!$fechaInicioOriginal) {
                throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');
            }

            // Eliminar el registro y sus líneas asociadas
            $registroId = $registro->Id;

            // Eliminar líneas asociadas primero
            DB::table('ReqProgramaTejidoLine')->where('ProgramaId', $registroId)->delete();

            // Eliminar el registro
            $registro->delete();

            // Obtener los registros restantes (sin el eliminado)
            $registrosRestantes = ReqProgramaTejido::where('SalonTejidoId', $salon)
                ->where('NoTelarId', $telar)
                ->orderBy('FechaInicio', 'asc')
                ->get();

            if ($registrosRestantes->count() == 0) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Registro eliminado correctamente',
                ]);
            }

            // Deshabilitar temporalmente el observer
            \App\Models\ReqProgramaTejido::unsetEventDispatcher();

            // Recalcular fechas para los registros restantes
            $updates = [];
            $detalles = [];
            $lastFin = null;
            $now = now();
            $totalRegistros = $registrosRestantes->count();

            foreach ($registrosRestantes as $i => $registroItem) {
                // Duración original usando las fechas del registro
                $inicio = $registroItem->FechaInicio ? \Carbon\Carbon::parse($registroItem->FechaInicio) : null;
                $fin = $registroItem->FechaFinal ? \Carbon\Carbon::parse($registroItem->FechaFinal) : null;

                if (!$inicio || !$fin) {
                    throw new \RuntimeException("El registro {$registroItem->Id} debe tener FechaInicio y FechaFinal completas.");
                }

                // Calcular duración usando diff()
                $duracion = $inicio->diff($fin);

                // Asignar nuevo FechaInicio
                if ($i == 0) {
                    $nuevoInicio = $fechaInicioOriginal->copy();
                } else {
                    if (!$lastFin) {
                        throw new \RuntimeException("Error: lastFin es null para el registro en posición {$i}");
                    }
                    $nuevoInicio = $lastFin->copy();
                }

                // Calcula el nuevo Fin sumando la duración original
                $nuevoFin = (clone $nuevoInicio)->add($duracion);

                // Preparar actualización
                $updates[$registroItem->Id] = [
                    'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal' => $nuevoFin->format('Y-m-d H:i:s'),
                    'EnProceso' => $i == 0 ? 1 : 0,
                    'Ultimo' => $i == ($totalRegistros - 1) ? '1' : '0',
                    'UpdatedAt' => $now,
                ];

                $detalles[] = [
                    'Id' => $registroItem->Id,
                    'NoTelar' => $registroItem->NoTelarId,
                    'Posicion' => $i,
                    'FechaInicio_nueva' => $updates[$registroItem->Id]['FechaInicio'],
                    'FechaFinal_nueva' => $updates[$registroItem->Id]['FechaFinal'],
                    'EnProceso_nuevo' => $updates[$registroItem->Id]['EnProceso'],
                    'Ultimo_nuevo' => $updates[$registroItem->Id]['Ultimo'],
                ];

                $lastFin = $nuevoFin;
            }

            // Ejecutar actualizaciones en batch
            foreach ($updates as $idUpdate => $data) {
                DB::table('ReqProgramaTejido')
                    ->where('Id', $idUpdate)
                    ->update($data);
            }

            DB::commit();

            // Re-habilitar el observer después del commit
            \App\Models\ReqProgramaTejido::observe(\App\Observers\ReqProgramaTejidoObserver::class);

            // Regenerar líneas diarias para los registros actualizados
            $idsActualizados = array_column($detalles, 'Id');
            $observer = new \App\Observers\ReqProgramaTejidoObserver();
            foreach ($idsActualizados as $idActualizado) {
                $registroActualizado = ReqProgramaTejido::find($idActualizado);
                if ($registroActualizado) {
                    $observer->saved($registroActualizado);
                }
            }

            Log::info('ProgramaTejido.destroy - Registro eliminado exitosamente', [
                'RegistroId' => $registroId,
                'Salon' => $salon,
                'Telar' => $telar,
                'EraUltimo' => $esUltimo,
                'TotalRegistrosActualizados' => count($detalles),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente',
                'cascaded_records' => count($detalles),
                'detalles' => $detalles,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Asegurar que el observer se re-habilite incluso en caso de error
            \App\Models\ReqProgramaTejido::observe(\App\Observers\ReqProgramaTejidoObserver::class);

            Log::error('ProgramaTejido.destroy - Error al eliminar registro', [
                'RegistroId' => $id,
                'error' => $e->getMessage(),
            ]);

            $codigo = $e instanceof \RuntimeException ? 422 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $codigo);
        }
    }

    private function moverPrioridad(ReqProgramaTejido $registro, string $direccion): array
    {
        DB::beginTransaction();

        try {
            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            // Obtener todos los registros del telar ordenados por FechaInicio
            $registros = ReqProgramaTejido::where('SalonTejidoId', $salon)
                ->where('NoTelarId', $telar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            if ($registros->count() < 2) {
                throw new \RuntimeException('Se requieren al menos dos registros para reordenar la prioridad.');
            }

            // Extraer la fecha de INICIO del primer registro ANTES de cualquier cambio
            $primerRegistro = $registros->first();
            $fechaInicioOriginal = $primerRegistro->FechaInicio ? \Carbon\Carbon::parse($primerRegistro->FechaInicio) : null;

            if (!$fechaInicioOriginal) {
                throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');
            }

            // Encontrar el índice del registro a mover
            $indiceActual = $registros->search(fn ($item) => $item->Id === $registro->Id);
            if ($indiceActual === false) {
                throw new \RuntimeException('No se encontró el registro a reordenar dentro del telar.');
            }

            // Calcular el índice destino
            $indiceDestino = $direccion === 'up' ? $indiceActual - 1 : $indiceActual + 1;
            if ($indiceDestino < 0) {
                throw new \RuntimeException('Este registro ya es el primero en la secuencia.');
            }
            if ($indiceDestino >= $registros->count()) {
                throw new \RuntimeException('Este registro ya es el último en la secuencia.');
            }

            // Intercambiar los registros (igual que en el código viejo)
            if ($direccion === 'up' && $indiceActual > 0) {
                $temp = $registros[$indiceActual];
                $registros[$indiceActual] = $registros[$indiceActual - 1];
                $registros[$indiceActual - 1] = $temp;
            } elseif ($direccion === 'down' && $indiceActual < ($registros->count() - 1)) {
                $temp = $registros[$indiceActual];
                $registros[$indiceActual] = $registros[$indiceActual + 1];
                $registros[$indiceActual + 1] = $temp;
            }

            // Reindexar la colección
            $registros = $registros->values();

            // Deshabilitar temporalmente el observer para evitar procesamiento en cada save()
            \App\Models\ReqProgramaTejido::unsetEventDispatcher();

            // Preparar actualizaciones en batch
            $updates = [];
            $detalles = [];
            $lastFin = null;
            $now = now();

            foreach ($registros as $i => $registroItem) {
                // Duración original usando las fechas del registro
                $inicio = $registroItem->FechaInicio ? \Carbon\Carbon::parse($registroItem->FechaInicio) : null;
                $fin = $registroItem->FechaFinal ? \Carbon\Carbon::parse($registroItem->FechaFinal) : null;

                if (!$inicio || !$fin) {
                    throw new \RuntimeException("El registro {$registroItem->Id} debe tener FechaInicio y FechaFinal completas.");
                }

                // Calcular duración usando diff()
                $duracion = $inicio->diff($fin);

                // Asignar nuevo FechaInicio
                if ($i == 0) {
                    $nuevoInicio = $fechaInicioOriginal->copy();
                } else {
                    if (!$lastFin) {
                        throw new \RuntimeException("Error: lastFin es null para el registro en posición {$i}");
                    }
                    $nuevoInicio = $lastFin->copy();
                }

                // Calcula el nuevo Fin sumando la duración original
                $nuevoFin = (clone $nuevoInicio)->add($duracion);

                // Preparar actualización
                $updates[$registroItem->Id] = [
                    'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal' => $nuevoFin->format('Y-m-d H:i:s'),
                    'EnProceso' => $i == 0 ? 1 : 0,
                    'Ultimo' => $i == ($registros->count() - 1) ? '1' : '0',
                    'UpdatedAt' => $now,
                ];

                    $detalles[] = [
                    'Id' => $registroItem->Id,
                    'NoTelar' => $registroItem->NoTelarId,
                        'Posicion' => $i,
                    'FechaInicio_nueva' => $updates[$registroItem->Id]['FechaInicio'],
                    'FechaFinal_nueva' => $updates[$registroItem->Id]['FechaFinal'],
                    'EnProceso_nuevo' => $updates[$registroItem->Id]['EnProceso'],
                    'Ultimo_nuevo' => $updates[$registroItem->Id]['Ultimo'],
                ];

                $lastFin = $nuevoFin;
            }

            // Ejecutar actualizaciones en batch usando queries directas (más rápido que save() individual)
            foreach ($updates as $id => $data) {
                DB::table('ReqProgramaTejido')
                    ->where('Id', $id)
                    ->update($data);
            }

            DB::commit();

            // Re-habilitar el observer después del commit
            \App\Models\ReqProgramaTejido::observe(\App\Observers\ReqProgramaTejidoObserver::class);

            // Regenerar líneas diarias solo para los registros actualizados (fuera de transacción)
            // Hacerlo después del commit para no bloquear la respuesta
            $idsActualizados = array_column($detalles, 'Id');
            $observer = new \App\Observers\ReqProgramaTejidoObserver();
            foreach ($idsActualizados as $idActualizado) {
                $registroActualizado = ReqProgramaTejido::find($idActualizado);
                if ($registroActualizado) {
                    // Disparar el observer manualmente solo una vez por registro
                    $observer->saved($registroActualizado);
                }
            }

            Log::info('ProgramaTejido.moverPrioridad - Reordenamiento exitoso', [
                'RegistroId' => $registro->Id,
                'Salon' => $salon,
                'Telar' => $telar,
                'Direccion' => $direccion,
                'TotalRegistrosActualizados' => count($detalles),
            ]);

            return [
                'success' => true,
                'cascaded_records' => count($detalles),
                'detalles' => $detalles,
                'registro_id' => $registro->Id,
                'direccion' => $direccion,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            // Asegurar que el observer se re-habilite incluso en caso de error
            \App\Models\ReqProgramaTejido::observe(\App\Observers\ReqProgramaTejidoObserver::class);

            Log::error('ProgramaTejido.moverPrioridad - Error en reordenamiento', [
                'RegistroId' => $registro->Id ?? null,
                'Direccion' => $direccion,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * CASCADING DE FECHAS (Mejorado v2)
     * Cuando se actualiza FechaFinal de un registro, todos los registros posteriores
     * del mismo telar se recorren automáticamente.
     *
     * Regla: fecha_inicio[N] = fecha_fin[N-1]
     * Se preserva la duración original de cada registro.
     *
     * Este método maneja:
     * - Registros intermedios (no solo el último)
     * - Fechas NULL o incompletas
     * - Rollback automático en caso de error
     * - Actualización correcta de fechas en cascada
     */
    private function cascadeFechas($registroActualizado)
    {
        DB::beginTransaction();
        try {
            $salonTejidoId = $registroActualizado->SalonTejidoId;
            $noTelarId = $registroActualizado->NoTelarId;
            $nuevaFechaFin = \Carbon\Carbon::parse($registroActualizado->FechaFinal);

            Log::info('cascadeFechas - Iniciando cascading', [
                'Id' => $registroActualizado->Id,
                'SalonTejidoId' => $salonTejidoId,
                'NoTelarId' => $noTelarId,
                'Nueva_FechaFinal' => $nuevaFechaFin->format('Y-m-d H:i:s')
            ]);

            //  Obtener TODOS los registros del mismo telar/salón ordenados por FechaInicio
            // SIN lockForUpdate (será liberado después del commit de esta función)
            $todosRegistros = ReqProgramaTejido::where('SalonTejidoId', $salonTejidoId)
                ->where('NoTelarId', $noTelarId)
                ->orderBy('FechaInicio', 'asc')
                ->get()
                ->all(); // Convertir a array para mejor manejo

            if (empty($todosRegistros)) {
                Log::info('cascadeFechas - No hay registros para cascading', [
                    'salon' => $salonTejidoId,
                    'telar' => $noTelarId
                ]);
                DB::commit();
                return [];
            }

            //  Encontrar el índice del registro actualizado
            $indiceActual = null;
            foreach ($todosRegistros as $idx => $reg) {
                if ($reg->Id === $registroActualizado->Id) {
                    $indiceActual = $idx;
                    break;
                }
            }

            if ($indiceActual === null) {
                Log::warning('cascadeFechas - Registro actualizado no encontrado', [
                    'id' => $registroActualizado->Id,
                    'salon' => $salonTejidoId,
                    'telar' => $noTelarId
                ]);
                DB::commit();
                return [];
            }

            $registrosCascadeados = [];
            $registrosActualizados = 0;

            // Variable para rastrear la FechaFinal del registro anterior cascadeado
            $fechaFinAnterior = $nuevaFechaFin;

            // ✅ CASCADA: Iterar sobre registros POSTERIORES
            for ($i = $indiceActual + 1; $i < count($todosRegistros); $i++) {
                $registroSiguiente = $todosRegistros[$i];

                // Obtener fechas originales
                $fechaInicioOriginal = $registroSiguiente->FechaInicio;
                $fechaFinalOriginal = $registroSiguiente->FechaFinal;

                // ✅ Saltar registros sin fechas válidas
                if (!$fechaInicioOriginal || !$fechaFinalOriginal) {
                    Log::warning('cascadeFechas - Registro saltado (sin fechas)', [
                        'id' => $registroSiguiente->Id,
                        'FechaInicio' => $fechaInicioOriginal,
                        'FechaFinal' => $fechaFinalOriginal
                    ]);
                    continue;
                }

                try {
                    // Calcular duración original (preservar para mantener calendarios)
                    $dInicio = \Carbon\Carbon::parse($fechaInicioOriginal);
                    $dFinal = \Carbon\Carbon::parse($fechaFinalOriginal);
                    $duracion = $dInicio->diff($dFinal);

                    // ✅ Nueva FechaInicio = FechaFinal del registro anterior cascadeado
                    $nuevoInicio = clone $fechaFinAnterior;

                    // Aplicar la duración original
                    $nuevoFin = (clone $nuevoInicio)->add($duracion);

                    // ✅ Actualizar el registro en BD directamente con query (no usar ORM para evitar locks)
                    ReqProgramaTejido::where('Id', $registroSiguiente->Id)
                        ->update([
                            'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                            'FechaFinal' => $nuevoFin->format('Y-m-d H:i:s'),
                        ]);

                    $registrosActualizados++;

                    // Actualizar la referencia de fechaFinAnterior para el siguiente registro
                    $fechaFinAnterior = $nuevoFin;

                    // Registrar en el array de cascadeados
                    $registrosCascadeados[] = [
                        'Id' => $registroSiguiente->Id,
                        'NoTelar' => $registroSiguiente->NoTelarId,
                        'FechaInicio_anterior' => $fechaInicioOriginal,
                        'FechaInicio_nueva' => $nuevoInicio->format('Y-m-d H:i:s'),
                        'FechaFinal_anterior' => $fechaFinalOriginal,
                        'FechaFinal_nueva' => $nuevoFin->format('Y-m-d H:i:s'),
                        'Duracion_dias' => $duracion->days,
                        'Duracion_horas' => $duracion->h,
                        'Duracion_minutos' => $duracion->i
                    ];

                    Log::info('cascadeFechas - Registro cascadeado exitosamente', [
                        'Id' => $registroSiguiente->Id,
                        'NoTelar' => $noTelarId,
                        'FechaInicio_anterior' => $fechaInicioOriginal,
                        'FechaInicio_nueva' => $nuevoInicio->format('Y-m-d H:i:s'),
                        'FechaFinal_anterior' => $fechaFinalOriginal,
                        'FechaFinal_nueva' => $nuevoFin->format('Y-m-d H:i:s')
                    ]);

                } catch (\Throwable $e) {
                    Log::error('cascadeFechas - Error al procesar registro individual', [
                        'Id' => $registroSiguiente->Id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continuar con el siguiente en lugar de fallar completamente
                    continue;
                }
            }

            // ✅ Confirmar transacción
            DB::commit();

            Log::info('cascadeFechas - Cascading completado', [
                'salon' => $salonTejidoId,
                'telar' => $noTelarId,
                'registrosActualizados' => $registrosActualizados,
                'detalles' => $registrosCascadeados
            ]);

            return $registrosCascadeados;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('cascadeFechas - Error crítico durante cascading', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $registroActualizado->Id ?? 'UNKNOWN'
            ]);
            // Relanzar excepción pero con rollback realizado
            throw $e;
        }
    }
}
