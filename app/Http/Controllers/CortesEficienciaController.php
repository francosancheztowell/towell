<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TurnoHelper;
use App\Models\InvSecuenciaCorteEf;
use App\Models\TejEficienciaLine;
use App\Models\TejEficiencia;

class CortesEficienciaController extends Controller
{
    /**
     * Mostrar la vista de cortes de eficiencia
     */
    public function index()
    {
        // Obtener orden de telares desde InvSecuenciaCorteEf
        $telares = InvSecuenciaCorteEf::orderBy('Orden', 'asc')
            ->pluck('NoTelarId')
            ->toArray();

        return view('modulos.cortes-eficiencia', compact('telares'));
    }

    /**
     * Mostrar la vista para consultar cortes de eficiencia
     */
    public function consultar()
    {
        try {
            // Obtener todos los cortes de eficiencia ordenados por fecha descendente
            $cortes = TejEficiencia::with(['usuario', 'lineas' => function($q){
                $q->orderBy('NoTelarId');
            }])
                ->orderBy('Date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Evitar caché del navegador para esta vista
            return response()
                ->view('modulos.consultar-cortes-eficiencia', compact('cortes'))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
            
        } catch (\Exception $e) {
            Log::error('Error al consultar cortes de eficiencia: ' . $e->getMessage());
            
            // Si hay error, retornar vista vacía y sin caché
            $cortes = collect([]);
            return response()
                ->view('modulos.consultar-cortes-eficiencia', compact('cortes'))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }
    }

    /**
     * Obtener información del turno actual
     */
    public function getTurnoInfo()
    {
        try {
            $turno = TurnoHelper::getTurnoActual();
            $descripcion = TurnoHelper::getDescripcionTurno($turno);

            return response()->json([
                'success' => true,
                'turno' => $turno,
                'descripcion' => $descripcion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener información del turno: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del turno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de telares desde ReqProgramaTejido
     */
    public function getDatosTelares()
    {
        try {
            // 1) Ordenar telares según InvSecuenciaCorteEf
            $secuencia = InvSecuenciaCorteEf::orderBy('Orden', 'asc')->get(['NoTelarId']);

            // 2) Para cada telar, buscar el último registro en TejEficienciaLine (si existe)
            $list = [];
            foreach ($secuencia as $row) {
                $noTelar = (int)$row->NoTelarId;
                $lastLine = TejEficienciaLine::where('NoTelarId', $noTelar)
                    ->orderBy('Date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $list[] = [
                    'NoTelarId'     => $noTelar,
                    // Mantener nombres esperados por el frontend
                    'VelocidadStd'  => $lastLine->RpmStd ?? null,
                    'EficienciaStd' => $lastLine->EficienciaSTD ?? null,
                ];
            }

            return response()->json(['success' => true, 'telares' => $list]);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de telares: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de telares: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar nuevo folio y obtener información del usuario
     */
    public function generarFolio(Request $request)
    {
        try {
            // Obtener información del usuario autenticado
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Generar folio único (formato: CE + contador de 4 dígitos)
            // Buscar el último folio existente para generar el siguiente consecutivo
            $ultimoFolio = $this->obtenerUltimoFolio();

            $numeroSiguiente = 1;
            if ($ultimoFolio) {
                // Extraer el número del último folio (ej: CE0001 -> 1)
                $numeroSiguiente = (int) substr($ultimoFolio, 2) + 1;
            }

            $folio = 'CE' . str_pad($numeroSiguiente, 4, '0', STR_PAD_LEFT);

            // Obtener turno actual
            $turno = TurnoHelper::getTurnoActual();

            // Obtener información del usuario actual desde la autenticación
            $usuario = [
                'nombre' => $user->nombre ?? 'Usuario',
                'numero_empleado' => $user->numero_empleado ?? 'N/A'
            ];

            Log::info('Folio generado para cortes de eficiencia', [
                'folio' => $folio,
                'usuario' => $usuario,
                'user_id' => $user->idusuario
            ]);

            return response()->json([
                'success' => true,
                'folio' => $folio,
                'turno' => $turno,
                'usuario' => $usuario
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar folio: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el folio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el último folio existente
     */
    private function obtenerUltimoFolio()
    {
        try {
            // Buscar en la tabla TejEficiencia
            $ultimoFolio = TejEficiencia::where('Folio', 'like', 'CE%')
                ->orderBy('Folio', 'desc')
                ->value('Folio');

            return $ultimoFolio;

        } catch (\Exception $e) {
            Log::warning('No se pudo obtener el último folio, usando CE0001 como inicial', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Guardar un nuevo corte de eficiencia
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'folio' => 'required|string|max:20',
                'fecha' => 'required|date',
                'turno' => 'required|string|max:10',
                'status' => 'required|string|max:20',
                'usuario' => 'required|string|max:100',
                'noEmpleado' => 'required|string|max:20',
                'datos_telares' => 'required|array',
                'horario1' => 'nullable|string',
                'horario2' => 'nullable|string',
                'horario3' => 'nullable|string',
            ]);

            DB::beginTransaction();

            try {
                // 1. Crear o actualizar el registro en TejEficiencia
                $tejEficiencia = TejEficiencia::updateOrCreate(
                    ['Folio' => $validated['folio']],
                    [
                        'Date' => $validated['fecha'],
                        'Turno' => $validated['turno'],
                        'Status' => $validated['status'],
                        'numero_empleado' => $validated['noEmpleado'],
                        'nombreEmpl' => $validated['usuario'],
                        'Horario1' => $validated['horario1'] ?? null,
                        'Horario2' => $validated['horario2'] ?? null,
                        'Horario3' => $validated['horario3'] ?? null,
                    ]
                );

                // 2. Guardar las líneas de telares en TejEficienciaLine
                foreach ($validated['datos_telares'] as $telar) {
                    // Verificar que el telar tenga los datos necesarios
                    if (!isset($telar['NoTelar'])) {
                        continue;
                    }

                    // Obtener RpmStd y EficienciaStd
                    $rpmStd = $telar['RpmStd'] ?? null;
                    $eficienciaStd = $telar['EficienciaStd'] ?? null;
                    
                    Log::info('Guardando datos STD en TejEficienciaLine (store)', [
                        'NoTelarId' => $telar['NoTelar'],
                        'RpmStd' => $rpmStd,
                        'EficienciaSTD' => $eficienciaStd
                    ]);
                    
                    Log::info('Guardando datos STD para telar', [
                        'NoTelar' => $telar['NoTelar'],
                        'RpmStd' => $rpmStd,
                        'EficienciaStd' => $eficienciaStd,
                        'Folio' => $validated['folio']
                    ]);
                    
                    // Buscar si ya existe un registro con estos criterios
                    $registroExistente = TejEficienciaLine::where('Folio', $validated['folio'])
                        ->where('NoTelarId', $telar['NoTelar'])
                        ->where('Turno', $validated['turno'])
                        ->where('Date', $validated['fecha'])
                        ->first();
                    
                    $datos = [
                        'Date' => $validated['fecha'],
                        'SalonTejidoId' => $telar['SalonTejidoId'] ?? null,
                        'RpmStd' => $rpmStd,
                        'EficienciaSTD' => $eficienciaStd,
                        'RpmR1' => $telar['RpmR1'] ?? null,
                        'EficienciaR1' => $telar['EficienciaR1'] ?? null,
                        'RpmR2' => $telar['RpmR2'] ?? null,
                        'EficienciaR2' => $telar['EficienciaR2'] ?? null,
                        'RpmR3' => $telar['RpmR3'] ?? null,
                        'EficienciaR3' => $telar['EficienciaR3'] ?? null,
                        'ObsR1' => $telar['ObsR1'] ?? null,
                        'ObsR2' => $telar['ObsR2'] ?? null,
                        'ObsR3' => $telar['ObsR3'] ?? null,
                        'StatusOB1' => $telar['StatusOB1'] ?? null,
                        'StatusOB2' => $telar['StatusOB2'] ?? null,
                        'StatusOB3' => $telar['StatusOB3'] ?? null,
                    ];
                    
                    if ($registroExistente) {
                        // Actualizar registro existente SIN usar PK 'id'
                        TejEficienciaLine::where('Folio', $validated['folio'])
                            ->where('NoTelarId', $telar['NoTelar'])
                            ->where('Turno', $validated['turno'])
                            ->where('Date', $validated['fecha'])
                            ->update($datos);
                    } else {
                        // Crear nuevo registro
                        $datos['Folio'] = $validated['folio'];
                        $datos['NoTelarId'] = $telar['NoTelar'];
                        $datos['Turno'] = $validated['turno'];
                        TejEficienciaLine::create($datos);
                    }
                }

                DB::commit();

                Log::info('Corte de eficiencia guardado exitosamente', [
                    'folio' => $validated['folio'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'usuario' => $validated['usuario'],
                    'total_telares' => count($validated['datos_telares'])
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Corte de eficiencia guardado exitosamente',
                    'folio' => $validated['folio']
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error al guardar corte de eficiencia: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un corte de eficiencia existente
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'folio' => 'required|string|max:20',
                'fecha' => 'required|date',
                'turno' => 'required|string|max:10',
                'status' => 'required|string|max:20',
                'usuario' => 'required|string|max:100',
                'noEmpleado' => 'required|string|max:20',
                'datos_telares' => 'required|array'
            ]);

            // Aquí iría la lógica para actualizar en la base de datos
            Log::info('Actualizando corte de eficiencia', [
                'id' => $id,
                'folio' => $request->folio,
                'fecha' => $request->fecha,
                'turno' => $request->turno,
                'status' => $request->status,
                'usuario' => $request->usuario,
                'noEmpleado' => $request->noEmpleado,
                'datos_telares' => $request->datos_telares
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalizar un corte de eficiencia
     */
    public function finalizar(Request $request, $id)
    {
        try {
            // Buscar el corte por folio
            $corte = TejEficiencia::where('Folio', $id)->first();
            
            if (!$corte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Corte no encontrado'
                ], 404);
            }

            // Verificar que no esté ya finalizado
            if ($corte->Status === 'Finalizado') {
                return response()->json([
                    'success' => false,
                    'message' => 'El corte ya está finalizado'
                ], 400);
            }

            // Actualizar el status a Finalizado
            $corte->Status = 'Finalizado';
            $corte->updated_at = now();
            $corte->save();

            Log::info('Corte de eficiencia finalizado', [
                'folio' => $id,
                'status_anterior' => $corte->getOriginal('Status'),
                'status_nuevo' => 'Finalizado'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia finalizado exitosamente',
                'data' => [
                    'folio' => $corte->Folio,
                    'status' => $corte->Status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al finalizar corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un corte de eficiencia por ID
     */
    public function show($id)
    {
        try {
            // Buscar corte por Folio
            $corte = TejEficiencia::where('Folio', $id)->first();

            if (!$corte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Corte no encontrado'
                ], 404);
            }

            // Obtener líneas asociadas del mismo Folio, Date y Turno
            $lineas = TejEficienciaLine::where('Folio', $corte->Folio)
                ->where('Date', $corte->Date)
                ->where('Turno', $corte->Turno)
                ->orderBy('NoTelarId')
                ->get()
                ->map(function ($l) {
                    return [
                        'NoTelar' => (int) $l->NoTelarId,
                        'SalonTejidoId' => $l->SalonTejidoId,
                        'RpmStd' => $l->RpmStd,
                        'EficienciaStd' => $l->EficienciaSTD,
                        'RpmR1' => $l->RpmR1,
                        'EficienciaR1' => $l->EficienciaR1,
                        'RpmR2' => $l->RpmR2,
                        'EficienciaR2' => $l->EficienciaR2,
                        'RpmR3' => $l->RpmR3,
                        'EficienciaR3' => $l->EficienciaR3,
                        'ObsR1' => $l->ObsR1,
                        'ObsR2' => $l->ObsR2,
                        'ObsR3' => $l->ObsR3,
                        'StatusOB1' => (int) ($l->StatusOB1 ?? 0),
                        'StatusOB2' => (int) ($l->StatusOB2 ?? 0),
                        'StatusOB3' => (int) ($l->StatusOB3 ?? 0),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'folio' => $corte->Folio,
                    'fecha' => optional($corte->Date)->format('Y-m-d'),
                    'turno' => (string) $corte->Turno,
                    'status' => $corte->Status,
                    'usuario' => $corte->nombreEmpl,
                    'noEmpleado' => $corte->numero_empleado,
                    'horario_1' => $corte->HoraHorario1,
                    'horario_2' => $corte->HoraHorario2,
                    'horario_3' => $corte->HoraHorario3,
                    'datos_telares' => $lineas
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener corte de eficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el corte de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de programa tejido para cortes de eficiencia
     */
    public function getDatosProgramaTejido()
    {
        try {
            // Obtener telares del orden de corte
            $telaresOrden = InvSecuenciaCorteEf::orderBy('Orden', 'asc')
                ->pluck('NoTelarId')
                ->toArray();

            // Obtener datos de ReqProgramaTejido para telares en proceso
            $telares = \App\Models\ReqProgramaTejido::whereIn('NoTelarId', $telaresOrden)
                ->where('EnProceso', 1)
                ->select('NoTelarId', 'VelocidadSTD', 'EficienciaSTD')
                ->get()
                ->map(function ($telar) use ($telaresOrden) {
                    Log::info('Datos de telar desde ReqProgramaTejido', [
                        'NoTelarId' => $telar->NoTelarId,
                        'VelocidadSTD' => $telar->VelocidadSTD,
                        'EficienciaSTD' => $telar->EficienciaSTD
                    ]);
                    
                    return [
                        'NoTelar' => $telar->NoTelarId,
                        'VelocidadSTD' => $telar->VelocidadSTD ?? 0,
                        'EficienciaSTD' => $telar->EficienciaSTD ?? 0
                    ];
                });

            // Si no se encontraron telares en proceso, intentar obtener los últimos datos disponibles
            if ($telares->isEmpty()) {
                Log::warning('No se encontraron telares con EnProceso=1, buscando últimos registros disponibles', [
                    'telares_solicitados' => $telaresOrden
                ]);
                
                $telares = collect($telaresOrden)->map(function ($telarId) {
                    $ultimoRegistro = \App\Models\ReqProgramaTejido::where('NoTelarId', $telarId)
                        ->orderBy('Id', 'desc')
                        ->select('NoTelarId', 'VelocidadSTD', 'EficienciaSTD')
                        ->first();
                    
                    if ($ultimoRegistro) {
                        Log::info('Último registro encontrado para telar', [
                            'NoTelarId' => $ultimoRegistro->NoTelarId,
                            'VelocidadSTD' => $ultimoRegistro->VelocidadSTD,
                            'EficienciaSTD' => $ultimoRegistro->EficienciaSTD
                        ]);
                        
                        return [
                            'NoTelar' => $ultimoRegistro->NoTelarId,
                            'VelocidadSTD' => $ultimoRegistro->VelocidadSTD ?? 0,
                            'EficienciaSTD' => $ultimoRegistro->EficienciaSTD ?? 0
                        ];
                    }
                    
                    Log::warning('No se encontraron datos para telar', ['NoTelarId' => $telarId]);
                    return [
                        'NoTelar' => $telarId,
                        'VelocidadSTD' => 0,
                        'EficienciaSTD' => 0
                    ];
                })->filter();
            }

            Log::info('Datos de programa tejido obtenidos', [
                'telares_solicitados' => $telaresOrden,
                'telares_encontrados' => $telares->pluck('NoTelar')->toArray(),
                'total_telares' => $telares->count()
            ]);

            return response()->json([
                'success' => true,
                'telares' => $telares
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de programa tejido: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de programa tejido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar hora en tabla TejEficiencia
     */
    public function guardarHora(Request $request)
    {
        try {
            $validated = $request->validate([
                'folio' => 'required|string',
                'turno' => 'required|integer',
                'horario' => 'required|integer|min:1|max:3',
                'hora' => 'required|string',
                'fecha' => 'required|date'
            ]);

            // Determinar el campo de horario correcto según el número
            $campoHorario = 'Horario' . $validated['horario'];

            // Buscar si ya existe el registro por Folio y Turno
            $registro = DB::table('TejEficiencia')
                ->where('Folio', $validated['folio'])
                ->where('Turno', $validated['turno'])
                ->first();

            $datos = [
                'Folio' => $validated['folio'],
                'Turno' => $validated['turno'],
                $campoHorario => $validated['hora'], // Usar Horario1, Horario2 o Horario3
                'Date' => $validated['fecha'],
                'updated_at' => now()
            ];

            if ($registro) {
                // Actualizar registro existente solo actualizando el campo de horario específico
                DB::table('TejEficiencia')
                    ->where('Folio', $validated['folio'])
                    ->where('Turno', $validated['turno'])
                    ->update([$campoHorario => $validated['hora'], 'updated_at' => now()]);

                Log::info('Hora actualizada en TejEficiencia', [
                    'folio' => $validated['folio'],
                    'turno' => $validated['turno'],
                    'campo' => $campoHorario,
                    'hora' => $validated['hora']
                ]);
            } else {
                // Crear nuevo registro
                $datos['created_at'] = now();
                DB::table('TejEficiencia')->insert($datos);

                Log::info('Hora guardada en TejEficiencia', $datos);
            }

            return response()->json([
                'success' => true,
                'message' => 'Hora guardada exitosamente',
                'data' => $datos
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar hora en TejEficiencia: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar hora: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar datos de la tabla directamente en TejEficienciaLine
     */
    public function guardarTabla(Request $request)
    {
        try {
            $validated = $request->validate([
                'folio' => 'required|string|max:20',
                'fecha' => 'required|date',
                'turno' => 'required|string|max:10',
                'datos_telares' => 'required|array',
                'datos_telares.*.NoTelar' => 'required|integer',
                'datos_telares.*.RpmStd' => 'nullable|numeric',
                'datos_telares.*.EficienciaStd' => 'nullable|numeric',
                'datos_telares.*.RpmR1' => 'nullable|integer',
                'datos_telares.*.EficienciaR1' => 'nullable|numeric',
                'datos_telares.*.RpmR2' => 'nullable|integer',
                'datos_telares.*.EficienciaR2' => 'nullable|numeric',
                'datos_telares.*.RpmR3' => 'nullable|integer',
                'datos_telares.*.EficienciaR3' => 'nullable|numeric',
                'datos_telares.*.ObsR1' => 'nullable|string',
                'datos_telares.*.ObsR2' => 'nullable|string',
                'datos_telares.*.ObsR3' => 'nullable|string',
                'datos_telares.*.StatusOB1' => 'nullable|integer|in:0,1',
                'datos_telares.*.StatusOB2' => 'nullable|integer|in:0,1',
                'datos_telares.*.StatusOB3' => 'nullable|integer|in:0,1',
            ]);

            DB::beginTransaction();

            try {
                $registrosGuardados = 0;

                foreach ($validated['datos_telares'] as $telar) {
                    // Obtener RpmStd y EficienciaStd independientemente
                    $rpmStd = $telar['RpmStd'] ?? null;
                    $eficienciaStd = $telar['EficienciaStd'] ?? null;
                    
                    Log::info('Guardando datos STD en TejEficienciaLine', [
                        'NoTelarId' => $telar['NoTelar'],
                        'RpmStd' => $rpmStd,
                        'EficienciaSTD' => $eficienciaStd
                    ]);
                    
                    // Buscar si ya existe un registro con estos criterios
                    $registroExistente = TejEficienciaLine::where('Folio', $validated['folio'])
                        ->where('NoTelarId', $telar['NoTelar'])
                        ->where('Turno', $validated['turno'])
                        ->where('Date', $validated['fecha'])
                        ->first();
                    
                    $datos = [
                        'SalonTejidoId' => $telar['SalonTejidoId'] ?? null,
                        'RpmStd' => $rpmStd,
                        'EficienciaSTD' => $eficienciaStd,
                        'RpmR1' => $telar['RpmR1'] ?? null,
                        'EficienciaR1' => $telar['EficienciaR1'] ?? null,
                        'RpmR2' => $telar['RpmR2'] ?? null,
                        'EficienciaR2' => $telar['EficienciaR2'] ?? null,
                        'RpmR3' => $telar['RpmR3'] ?? null,
                        'EficienciaR3' => $telar['EficienciaR3'] ?? null,
                        'ObsR1' => $telar['ObsR1'] ?? null,
                        'ObsR2' => $telar['ObsR2'] ?? null,
                        'ObsR3' => $telar['ObsR3'] ?? null,
                        'StatusOB1' => $telar['StatusOB1'] ?? 0,
                        'StatusOB2' => $telar['StatusOB2'] ?? 0,
                        'StatusOB3' => $telar['StatusOB3'] ?? 0,
                    ];
                    
                    if ($registroExistente) {
                        // Actualizar registro existente SIN usar PK 'id'
                        TejEficienciaLine::where('Folio', $validated['folio'])
                            ->where('NoTelarId', $telar['NoTelar'])
                            ->where('Turno', $validated['turno'])
                            ->where('Date', $validated['fecha'])
                            ->update($datos);
                    } else {
                        // Crear nuevo registro
                        $datos['Folio'] = $validated['folio'];
                        $datos['NoTelarId'] = $telar['NoTelar'];
                        $datos['Turno'] = $validated['turno'];
                        $datos['Date'] = $validated['fecha'];
                        TejEficienciaLine::create($datos);
                    }

                    $registrosGuardados++;
                }

                DB::commit();

                Log::info('Datos de tabla guardados en TejEficienciaLine', [
                    'folio' => $validated['folio'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'total_registros' => $registrosGuardados
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Datos guardados exitosamente en TejEficienciaLine',
                    'registros_guardados' => $registrosGuardados
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al guardar datos de tabla en TejEficienciaLine: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los datos: ' . $e->getMessage()
            ], 500);
        }
    }
}
