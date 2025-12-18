<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
// PDF facade (alias configurado en config/app.php)
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Exports\CortesEficienciaExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\TurnoHelper;
use App\Helpers\FolioHelper;
use App\Models\InvSecuenciaCorteEf;
use App\Models\TejEficienciaLine;
use App\Models\TejEficiencia;

class CortesEficienciaController extends Controller
{
    /**
     * Mostrar la vista de cortes de eficiencia
     */
    public function index(Request $request)
    {
        // Verificar si existe algún folio que no esté finalizado
        $folioEnProceso = TejEficiencia::where('Status', '!=', 'Finalizado')
            ->orderBy('created_at', 'desc')
            ->first();

        // Si hay un folio en proceso y no se está editando específicamente, redirigir
        if ($folioEnProceso && !$request->has('folio')) {
            return redirect()->route('cortes.eficiencia.consultar')
                ->with('warning', 'Ya existe un folio en proceso: ' . $folioEnProceso->Folio . '. Debe finalizarlo antes de crear uno nuevo.');
        }

        // Obtener orden de telares desde InvSecuenciaCorteEf
        $telares = InvSecuenciaCorteEf::orderBy('Orden', 'asc')
            ->pluck('NoTelarId')
            ->toArray();

        return view('modulos.cortes-eficiencia.cortes-eficiencia', compact('telares'));
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
                ->view('modulos.cortes-eficiencia.consultar-cortes-eficiencia', compact('cortes'))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error al consultar cortes de eficiencia: ' . $e->getMessage());

            // Si hay error, retornar vista vacía y sin caché
            $cortes = collect([]);
            return response()
                ->view('modulos.cortes-eficiencia.consultar-cortes-eficiencia', compact('cortes'))
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

            // Verificar si existe algún folio que no esté finalizado
            $folioEnProceso = TejEficiencia::where('Status', '!=', 'Finalizado')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($folioEnProceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio en proceso',
                    'folio_existente' => $folioEnProceso->Folio
                ], 400);
            }

            // Generar el folio real (incrementa el consecutivo)
            // Este será el folio definitivo que se usará para guardar
            $folio = FolioHelper::obtenerSiguienteFolio('CorteEficiencia', 4);

            // Crear inmediatamente el registro en TejEficiencia con status "En Proceso"
            // Esto reserva el folio y evita duplicados
            TejEficiencia::create([
                'Folio' => $folio,
                'Date' => now()->toDateString(),
                'Turno' => TurnoHelper::getTurnoActual(),
                'Status' => 'En Proceso',
                'numero_empleado' => $user->numero_empleado ?? 'N/A',
                'nombreEmpl' => $user->nombre ?? 'Usuario',
            ]);

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

            // Verificar si existe otro folio en proceso (excepto el actual)
            $folioEnProceso = TejEficiencia::where('Status', '!=', 'Finalizado')
                ->where('Folio', '!=', $validated['folio'])
                ->first();

            if ($folioEnProceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio en proceso: ' . $folioEnProceso->Folio . '. Debe finalizarlo antes de crear uno nuevo.',
                    'folio_existente' => $folioEnProceso->Folio
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Verificar si el folio del request ya existe Y está en proceso (no finalizado)
                $folioExistenteEnProceso = TejEficiencia::where('Folio', $validated['folio'])
                    ->where('Status', '!=', 'Finalizado')
                    ->first();

                if ($folioExistenteEnProceso) {
                    // Si el folio ya existe y está en proceso, usarlo (es una actualización)
                    $folioFinal = $validated['folio'];
                } else {
                    // No existe el folio en proceso, generar uno nuevo
                    // (puede ser que no exista, o que exista pero ya esté finalizado)
                    $folioFinal = FolioHelper::obtenerSiguienteFolio('CorteEficiencia', 4);

                    // Si no se pudo generar, usar el del request como fallback
                    if (empty($folioFinal)) {
                        $folioFinal = $validated['folio'];
                    }
                }

                // 1. Crear o actualizar el registro en TejEficiencia
                $tejEficiencia = TejEficiencia::updateOrCreate(
                    ['Folio' => $folioFinal],
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
                        'Folio' => $folioFinal
                    ]);

                    // Buscar si ya existe un registro con estos criterios
                    $registroExistente = TejEficienciaLine::where('Folio', $folioFinal)
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
                        TejEficienciaLine::where('Folio', $folioFinal)
                            ->where('NoTelarId', $telar['NoTelar'])
                            ->where('Turno', $validated['turno'])
                            ->where('Date', $validated['fecha'])
                            ->update($datos);
                    } else {
                        // Crear nuevo registro
                        $datos['Folio'] = $folioFinal;
                        $datos['NoTelarId'] = $telar['NoTelar'];
                        $datos['Turno'] = $validated['turno'];
                        TejEficienciaLine::create($datos);
                    }
                }

                DB::commit();

                Log::info('Corte de eficiencia guardado exitosamente', [
                    'folio' => $folioFinal,
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'usuario' => $validated['usuario'],
                    'total_telares' => count($validated['datos_telares'])
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Corte de eficiencia guardado exitosamente',
                    'folio' => $folioFinal
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

            $pdfUrl = route('cortes.eficiencia.pdf', $corte->Folio);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia finalizado exitosamente',
                'data' => [
                    'folio' => $corte->Folio,
                    'status' => $corte->Status,
                    'pdf_url' => $pdfUrl,
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
                    'horario_1' => $this->formatearHora($corte->Horario1),
                    'horario_2' => $this->formatearHora($corte->Horario2),
                    'horario_3' => $this->formatearHora($corte->Horario3),
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
     * Descargar PDF de un corte de eficiencia
     */
    public function pdf($id)
    {
        $corte = TejEficiencia::where('Folio', $id)->first();
        if (!$corte) {
            return redirect()->route('cortes.eficiencia.consultar')->with('error', 'Folio no encontrado');
        }

        // Líneas del folio y turno/fecha
        $lineas = TejEficienciaLine::where('Folio', $corte->Folio)
            ->where('Date', $corte->Date)
            ->where('Turno', $corte->Turno)
            ->orderBy('NoTelarId')
            ->get();

        // Lista de telares en orden de secuencia
        $telaresSecuencia = InvSecuenciaCorteEf::orderBy('Orden', 'asc')->pluck('NoTelarId')->toArray();
        $telaresLineas = $lineas->pluck('NoTelarId')->unique()->toArray();
        $telares = collect(array_unique(array_merge($telaresSecuencia, $telaresLineas)))->sort()->values();

        // Indexado por telar para acceso rápido
        $lineasPorTelar = $lineas->keyBy('NoTelarId');

        $pdf = PDF::loadView('modulos.cortes-eficiencia.pdf', [
            'corte' => $corte,
            'telares' => $telares,
            'lineasPorTelar' => $lineasPorTelar,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("corte-eficiencia-{$corte->Folio}.pdf");
    }

    /**
     * Devuelve solo HH:MM si viene con segundos o fracciones; null si vacío
     */
    private function formatearHora($valor): ?string
    {
        if (empty($valor)) {
            return null;
        }
        $str = (string) $valor;
        // Remover milisegundos o fracciones si existen
        if (str_contains($str, '.')) {
            $str = explode('.', $str)[0];
        }
        // Tomar solo HH:MM si viene HH:MM:SS
        if (strlen($str) >= 5) {
            return substr($str, 0, 5);
        }
        return $str;
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
     * Visualizar cortes de eficiencia de los 3 turnos por fecha
     */
    public function visualizar($folio)
    {
        try {
            $corteBase = TejEficiencia::where('Folio', $folio)->first();
            if (!$corteBase) {
                return redirect()->route('cortes.eficiencia.consultar')
                    ->with('error', 'Folio no encontrado');
            }

            $info = $this->obtenerDatosVisualizacionPorFecha($corteBase->Date);

            return view('modulos.cortes-eficiencia.visualizar-cortes-eficiencia', [
                'folio' => $folio,
                'fecha' => $info['fecha'],
                'datos' => $info['datos'],
                'foliosPorTurno' => $info['foliosPorTurno'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al visualizar cortes de eficiencia: ' . $e->getMessage());
            return redirect()->route('cortes.eficiencia.consultar')
                ->with('error', 'Error al visualizar: ' . $e->getMessage());
        }
    }

    public function visualizarFolio($folio)
    {
        try {
            $corte = TejEficiencia::where('Folio', $folio)->first();
            if (!$corte) {
                return redirect()->route('cortes.eficiencia.consultar')
                    ->with('error', 'Folio no encontrado');
            }

            $telares = InvSecuenciaCorteEf::orderBy('Orden', 'asc')
                ->pluck('NoTelarId')
                ->toArray();

            return view('modulos.cortes-eficiencia.cortes-eficiencia', [
                'telares' => $telares,
                'soloLectura' => true,
                'folioInicial' => $corte->Folio,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al visualizar folio de cortes: ' . $e->getMessage());
            return redirect()->route('cortes.eficiencia.consultar')
                ->with('error', 'Error al visualizar folio');
        }
    }

    public function exportarVisualizacionExcel(Request $request)
    {
        try {
            $fecha = $request->input('fecha');
            if (!$fecha) {
                return response()->json(['error' => 'Fecha requerida'], 400);
            }

            $fechaNorm = $this->normalizarFecha($fecha);
            $info = $this->obtenerDatosVisualizacionPorFecha($fechaNorm);

            if ($info['datos']->isEmpty()) {
                return response()->json(['error' => 'Sin datos para la fecha seleccionada'], 404);
            }

            $filename = 'cortes_eficiencia_' . $fechaNorm . '.xlsx';

            return Excel::download(new CortesEficienciaExport($info, $fechaNorm), $filename);
        } catch (\Throwable $th) {
            Log::error('Error al exportar Excel de cortes de eficiencia', [
                'mensaje' => $th->getMessage()
            ]);
            return response()->json(['error' => 'Error al exportar: ' . $th->getMessage()], 500);
        }
    }

    public function descargarVisualizacionPDF(Request $request)
    {
        try {
            $fecha = $request->input('fecha');
            if (!$fecha) {
                return response()->json(['error' => 'Fecha requerida'], 400);
            }

            $fechaNorm = $this->normalizarFecha($fecha);
            $info = $this->obtenerDatosVisualizacionPorFecha($fechaNorm);
            if ($info['datos']->isEmpty()) {
                return response()->json(['error' => 'Sin datos para la fecha seleccionada'], 404);
            }

            $html = view('modulos.cortes-eficiencia.visualizar-cortes-eficiencia-pdf', [
                'fecha' => $info['fecha'],
                'datos' => $info['datos'],
                'foliosPorTurno' => $info['foliosPorTurno'],
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            $options->set('isPhpEnabled', false);
            $options->set('chroot', public_path());
            $options->set('tempDir', sys_get_temp_dir());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();

            $pdfContent = $dompdf->output();
            $filename = 'cortes_eficiencia_' . $fechaNorm . '.pdf';

            if (empty($pdfContent)) {
                Log::error('PDF de cortes de eficiencia generado vacío', ['fecha' => $fechaNorm]);
                return response()->json(['error' => 'Error: PDF generado está vacío'], 500);
            }

            Log::info('PDF de cortes de eficiencia generado correctamente', [
                'fecha' => $fechaNorm,
                'filename' => $filename,
                'size_kb' => round(strlen($pdfContent) / 1024, 2),
            ]);

            try {
                $this->enviarReporteCortesPdfTelegram($pdfContent, $filename, $fechaNorm, Auth::user());
            } catch (\Throwable $e) {
                Log::error('Error al enviar PDF de cortes a Telegram (continuando con descarga)', [
                    'error' => $e->getMessage(),
                    'fecha' => $fechaNorm,
                ]);
            }

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $th) {
            Log::error('Error al generar PDF de cortes de eficiencia', [
                'mensaje' => $th->getMessage()
            ]);
            return response()->json(['error' => 'Error al generar PDF: ' . $th->getMessage()], 500);
        }
    }

    private function obtenerDatosVisualizacionPorFecha($fecha)
    {
        $fechaNorm = $this->normalizarFecha($fecha);

        $lineasFecha = TejEficienciaLine::whereDate('Date', $fechaNorm)
            ->orderBy('Turno')
            ->orderBy('NoTelarId')
            ->get();

        $secuencia = InvSecuenciaCorteEf::orderBy('Orden', 'asc')->get(['NoTelarId']);
        $telaresSecuencia = $secuencia->pluck('NoTelarId')->toArray();
        $telaresLineas = $lineasFecha->pluck('NoTelarId')->unique()->toArray();
        $telares = collect(array_unique(array_merge($telaresSecuencia, $telaresLineas)))->sort()->values();

        $porTelarTurno = [];
        $foliosPorTurno = [];

        foreach ($lineasFecha as $linea) {
            $telar = $linea->NoTelarId;
            $turno = (string)$linea->Turno;
            if (!isset($porTelarTurno[$telar])) {
                $porTelarTurno[$telar] = [];
            }
            $porTelarTurno[$telar][$turno] = $linea;
            if (!isset($foliosPorTurno[$turno])) {
                $foliosPorTurno[$turno] = $linea->Folio;
            }
        }

        $datos = $telares->map(function ($telar) use ($porTelarTurno) {
            $t1 = $porTelarTurno[$telar]['1'] ?? null;
            $t2 = $porTelarTurno[$telar]['2'] ?? null;
            $t3 = $porTelarTurno[$telar]['3'] ?? null;
            return [
                'telar' => $telar,
                't1' => $t1,
                't2' => $t2,
                't3' => $t3,
            ];
        });

        return [
            'fecha' => $fechaNorm,
            'datos' => $datos,
            'foliosPorTurno' => $foliosPorTurno,
        ];
    }

    private function enviarReporteCortesPdfTelegram(string $pdfContent, string $filename, string $fecha, $usuario = null): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (empty($botToken) || empty($chatId)) {
                Log::warning('No se pudo enviar PDF de cortes: credenciales Telegram no configuradas');
                return;
            }

            if (empty($pdfContent)) {
                Log::warning('PDF de cortes vacío, no se envía a Telegram', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                ]);
                return;
            }

            $pdfSizeMB = strlen($pdfContent) / 1024 / 1024;
            if ($pdfSizeMB > 50) {
                Log::warning('PDF de cortes excede límite de Telegram', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                    'size_mb' => round($pdfSizeMB, 2),
                ]);
                return;
            }

            $nombreUsuario = $usuario->nombre ?? $usuario->name ?? null;
            $numeroEmpleado = $usuario->numero_empleado ?? null;

            $caption = "Reporte Cortes de Eficiencia\n";
            $caption .= "Fecha: {$fecha}\n";
            if (!empty($nombreUsuario)) {
                $caption .= "Generado por: {$nombreUsuario}";
                if (!empty($numeroEmpleado)) {
                    $caption .= " ({$numeroEmpleado})";
                }
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendDocument";

            Log::info('Enviando reporte de cortes a Telegram', [
                'fecha' => $fecha,
                'filename' => $filename,
                'size_kb' => round(strlen($pdfContent) / 1024, 2),
                'chat_id' => $chatId,
            ]);

            $response = Http::timeout(30)
                ->attach('document', $pdfContent, $filename)
                ->post($url, [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok'] ?? false) {
                    Log::info('PDF de cortes enviado a Telegram', [
                        'fecha' => $fecha,
                        'filename' => $filename,
                        'chat_id' => $chatId,
                        'message_id' => $data['result']['message_id'] ?? null,
                    ]);
                } else {
                    Log::error('Telegram respondió ok=false para cortes', [
                        'response' => $data,
                        'fecha' => $fecha,
                        'filename' => $filename,
                    ]);
                }
            } else {
                Log::error('Error HTTP al enviar cortes a Telegram', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'fecha' => $fecha,
                    'filename' => $filename,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar PDF de cortes a Telegram', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fecha' => $fecha,
                'filename' => $filename,
            ]);
        }
    }

    private function normalizarFecha($fecha)
    {
        return date('Y-m-d', strtotime(str_replace('/', '-', $fecha)));
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
