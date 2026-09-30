<?php

namespace App\Http\Controllers\Tejido\CortesEficiencia;

use App\Exports\CortesEficienciaExport;
use App\Helpers\FolioHelper;
use App\Helpers\StringTruncator;
use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaCorteEf;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\SYSMensaje;
use App\Models\Tejido\TejeFallasCeModel;
use App\Models\Tejido\TejEficiencia;
use App\Models\Tejido\TejEficienciaLine;
use App\Services\Telegram\TelegramEnvio;
use App\Support\Http\Concerns\HandlesApiErrors;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CortesEficienciaController extends Controller
{
    use HandlesApiErrors;

    /** Filas por INSERT de TejEficienciaLine: 21 columnas y el tope de 2100 parámetros de SQL Server. */
    private const LINEAS_POR_INSERT = 95; // 95 × 21 columnas = 1 995 parámetros (SQL Server admite < 2 100 por RPC)

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
        if ($folioEnProceso && ! $request->has('folio')) {
            return redirect()->route('cortes.eficiencia.consultar')
                ->with('warning', 'Ya existe un folio en proceso: '.$folioEnProceso->Folio.'. Debe finalizarlo antes de crear uno nuevo.');
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
            $user = Auth::user();
            $puesto = strtolower(trim($user->puesto ?? ''));
            $esSupervisor = ($puesto === 'supervisor');

            // Sin eager load: la vista solo pinta columnas de la cabecera, y cargar 'lineas'
            // de todo el historial armaba un IN con un parámetro por folio (tope 2100 en SQL Server).
            $cortes = TejEficiencia::query()
                ->orderBy('Date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Evitar caché del navegador para esta vista
            return response()
                ->view('modulos.cortes-eficiencia.consultar-cortes-eficiencia', compact('cortes', 'esSupervisor'))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Error al consultar cortes de eficiencia: '.$e->getMessage());

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
            $info = TurnoHelper::info();

            return response()->json([
                'success' => true,
                'turno' => $info['turno'],
                'descripcion' => $info['horario'],
            ]);

        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener información del turno', 'Error al obtener información del turno');
        }
    }

    /**
     * Obtener datos de telares desde TejEficienciaLine.
     * Para RPM: busca la última RPM real (no-cero) de cualquier turno y horario,
     * revisando los registros más recientes y priorizando Horario 3 > 2 > 1.
     */
    public function getDatosTelares()
    {
        try {
            // 1) Ordenar telares según InvSecuenciaCorteEf
            $secuencia = InvSecuenciaCorteEf::orderBy('Orden', 'asc')->get(['NoTelarId']);

            // 2) Las 20 líneas más recientes de cada telar en UNA consulta (antes, una por telar).
            //    ROW_NUMBER() existe desde SQL Server 2005.
            $recientesPorTelar = $this->lineasRecientesPorTelar($secuencia->pluck('NoTelarId')->map(fn ($t) => (int) $t)->all(), 20);

            // 3) Para cada telar, buscar la última RPM real != 0 de cualquier turno/horario
            $list = [];
            foreach ($secuencia as $row) {
                $noTelar = (int) $row->NoTelarId;
                $recentLines = $recientesPorTelar[$noTelar] ?? collect();

                $lastRpm = null;
                $lastEficiencia = null;

                foreach ($recentLines as $line) {
                    // Buscar última RPM real != 0: priorizar Horario 3 > 2 > 1
                    if ($lastRpm === null) {
                        if (! empty($line->RpmR3) && (float) $line->RpmR3 != 0) {
                            $lastRpm = $line->RpmR3;
                        } elseif (! empty($line->RpmR2) && (float) $line->RpmR2 != 0) {
                            $lastRpm = $line->RpmR2;
                        } elseif (! empty($line->RpmR1) && (float) $line->RpmR1 != 0) {
                            $lastRpm = $line->RpmR1;
                        }
                    }

                    // Buscar última Eficiencia real != 0: misma lógica Horario 3 > 2 > 1
                    if ($lastEficiencia === null) {
                        if (! empty($line->EficienciaR3) && (float) $line->EficienciaR3 != 0) {
                            $lastEficiencia = $line->EficienciaR3;
                        } elseif (! empty($line->EficienciaR2) && (float) $line->EficienciaR2 != 0) {
                            $lastEficiencia = $line->EficienciaR2;
                        } elseif (! empty($line->EficienciaR1) && (float) $line->EficienciaR1 != 0) {
                            $lastEficiencia = $line->EficienciaR1;
                        }
                    }

                    // Si ya encontramos ambos, no seguir buscando
                    if ($lastRpm !== null && $lastEficiencia !== null) {
                        break;
                    }
                }

                // Fallback al RpmStd / EficienciaSTD del registro más reciente
                $lastLine = $recentLines->first();

                $list[] = [
                    'NoTelarId' => $noTelar,
                    // Mantener nombres esperados por el frontend
                    'VelocidadStd' => $lastRpm ?? ($lastLine->RpmStd ?? null),
                    'EficienciaStd' => $lastEficiencia ?? ($lastLine->EficienciaSTD ?? null),
                ];
            }

            return response()->json(['success' => true, 'telares' => $list]);

        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener datos de telares', 'Error al obtener datos de telares');
        }
    }

    /**
     * Las $limite líneas más recientes de cada telar (Date, Turno y created_at desc), agrupadas
     * por NoTelarId. Mismo orden y tope que la consulta por telar que había en getDatosTelares.
     *
     * @param  array<int, int>  $telares
     * @return array<int, \Illuminate\Support\Collection<int, TejEficienciaLine>>
     */
    private function lineasRecientesPorTelar(array $telares, int $limite): array
    {
        if ($telares === []) {
            return [];
        }

        $numeradas = TejEficienciaLine::query()
            ->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY NoTelarId ORDER BY Date DESC, Turno DESC, created_at DESC) AS rn')
            ->whereIn('NoTelarId', $telares);

        $porTelar = [];
        TejEficienciaLine::query()
            ->fromSub($numeradas, 'l')
            ->where('rn', '<=', $limite)
            ->orderBy('NoTelarId')
            ->orderBy('rn')
            ->get()
            ->each(function (TejEficienciaLine $linea) use (&$porTelar) {
                $porTelar[(int) $linea->getAttribute('NoTelarId')][] = $linea;
            });

        return array_map(fn (array $lineas) => collect($lineas), $porTelar);
    }

    /**
     * Generar nuevo folio y obtener información del usuario
     */
    public function generarFolio(Request $request)
    {
        try {
            // Obtener información del usuario autenticado
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            // Usar fecha y turno del request si se proporcionaron, de lo contrario usar valores del sistema
            $fecha = $request->query('fecha', now()->toDateString());
            $turno = (string) TurnoHelper::resolverTurnoOperativo($request->query('turno', TurnoHelper::getTurnoActual()));

            // 1. Verificar si ya existe un folio para esta misma fecha y turno (independientemente del status)
            // Esta es la restricción principal para evitar duplicados por turno
            $folioExistenteTurno = TejEficiencia::where('Date', $fecha)
                ->where('Turno', $turno)
                ->first();

            if ($folioExistenteTurno) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio para la fecha '.$fecha.' y turno '.$turno.': '.$folioExistenteTurno->Folio,
                    'folio_existente' => $folioExistenteTurno->Folio,
                    'status' => $folioExistenteTurno->Status,
                ], 400);
            }

            // 2. Verificar si existe algún OTRO folio que no esté finalizado (restricción global de "uno a la vez")
            $folioEnProceso = TejEficiencia::where('Status', '!=', 'Finalizado')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($folioEnProceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio en proceso: '.$folioEnProceso->Folio.'. Debe finalizarlo antes de crear uno nuevo.',
                    'folio_existente' => $folioEnProceso->Folio,
                ], 400);
            }

            return DB::transaction(function () use ($fecha, $turno, $user) {
                // Doble verificación dentro de la transacción para evitar condiciones de carrera
                $folioExistenteTurno = TejEficiencia::where('Date', $fecha)
                    ->where('Turno', $turno)
                    ->lockForUpdate()
                    ->first();

                if ($folioExistenteTurno) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un folio para esta fecha y turno',
                        'folio_existente' => $folioExistenteTurno->Folio,
                    ], 400);
                }

                // Generar el folio real (incrementa el consecutivo)
                $folio = FolioHelper::obtenerSiguienteFolio('CorteEficiencia', 4);

                if (empty($folio)) {
                    throw new \Exception('No se pudo generar un nuevo folio.');
                }

                // Crear inmediatamente el registro en TejEficiencia con status "En Proceso"
                // Esto reserva el folio y evita duplicados
                TejEficiencia::create([
                    'Folio' => $folio,
                    'Date' => $fecha,
                    'Turno' => $turno,
                    'Status' => 'En Proceso',
                    'numero_empleado' => $user->numero_empleado ?? 'N/A',
                    'nombreEmpl' => $user->nombre ?? 'Usuario',
                ]);

                // Obtener información del usuario actual desde la autenticación
                $usuario = [
                    'nombre' => $user->nombre ?? 'Usuario',
                    'numero_empleado' => $user->numero_empleado ?? 'N/A',
                ];

                return response()->json([
                    'success' => true,
                    'folio' => $folio,
                    'turno' => $turno,
                    'usuario' => $usuario,
                ]);
            });

        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al generar folio de cortes de eficiencia', 'Error al generar el folio');
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
                'turno' => 'required|in:1,2,3',
                'status' => 'required|string|max:20',
                'usuario' => 'required|string|max:100',
                'noEmpleado' => 'required|string|max:20',
                'datos_telares' => 'required|array',
                'horario1' => 'nullable|string',
                'horario2' => 'nullable|string',
                'horario3' => 'nullable|string',
            ]);

            // 1. Verificar si existe otro folio en proceso (excepto el actual)
            $folioEnProceso = TejEficiencia::where('Status', '!=', 'Finalizado')
                ->where('Folio', '!=', $validated['folio'])
                ->first();

            if ($folioEnProceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio en proceso: '.$folioEnProceso->Folio.'. Debe finalizarlo antes de crear uno nuevo.',
                    'folio_existente' => $folioEnProceso->Folio,
                ], 400);
            }

            // 2. Verificar si ya existe un folio para la misma fecha y turno (excepto el actual)
            // Esto evita que al intentar "guardar como nuevo" un registro que ya existe para ese turno se duplique
            $folioExistenteTurno = TejEficiencia::where('Date', $validated['fecha'])
                ->where('Turno', $validated['turno'])
                ->where('Folio', '!=', $validated['folio'])
                ->first();

            if ($folioExistenteTurno) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio para la fecha '.$validated['fecha'].' y turno '.$validated['turno'].': '.$folioExistenteTurno->Folio,
                    'folio_existente' => $folioExistenteTurno->Folio,
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
                $salonesByTelar = InvSecuenciaCorteEf::pluck('SalonTejidoId', 'NoTelarId')->toArray();
                $lineas = [];
                foreach ($validated['datos_telares'] as $telar) {
                    // Verificar que el telar tenga los datos necesarios
                    if (! isset($telar['NoTelar'])) {
                        continue;
                    }
                    $noTelar = (int) $telar['NoTelar'];
                    $salonId = $salonesByTelar[$noTelar] ?? null;

                    // Obtener RpmStd y EficienciaStd
                    $rpmStd = $telar['RpmStd'] ?? null;
                    $eficienciaStd = $telar['EficienciaStd'] ?? null;

                    // Truncar comentarios a los límites reales de la BD (ObsR1/2/3 = varchar(100))
                    // para que un comentario largo nunca tire el guardado con un error SQL.
                    $lineas[$noTelar] = StringTruncator::truncateArray([
                        'SalonTejidoId' => $salonId,
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
                    ]);
                }

                $this->guardarLineas($folioFinal, $validated['turno'], $validated['fecha'], $lineas);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Corte de eficiencia guardado exitosamente',
                    'folio' => $folioFinal,
                ]);

            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return $this->errorDeValidacion($e);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al guardar corte de eficiencia', 'Error al guardar el corte de eficiencia');
        }
    }

    /**
     * Crea o actualiza las líneas del folio (llave natural Folio + NoTelarId + Turno + Date).
     *
     * Antes: un updateOrCreate por telar (un SELECT y un INSERT/UPDATE por fila, en cada
     * autoguardado). Ahora: un SELECT de las líneas del folio, UPDATE solo de las que cambiaron
     * e INSERT en bloque de las nuevas. Si otra sesión insertó la misma línea entre el SELECT y
     * el INSERT (índice único), se repite fila por fila con updateOrCreate como antes.
     *
     * @param  array<int, array<string, mixed>>  $lineas  datos por NoTelarId
     */
    private function guardarLineas(string $folio, $turno, string $fecha, array $lineas): void
    {
        if ($lineas === []) {
            return;
        }

        $existentes = [];
        TejEficienciaLine::where('Folio', $folio)
            ->where('Turno', $turno)
            ->where('Date', $fecha)
            ->get()
            ->each(function (TejEficienciaLine $linea) use (&$existentes) {
                $existentes[(int) $linea->getAttribute('NoTelarId')] ??= $linea;
            });

        $nuevas = [];
        foreach ($lineas as $noTelar => $datos) {
            $linea = $existentes[$noTelar] ?? null;
            if ($linea) {
                // save() sin cambios no manda nada, igual que updateOrCreate.
                $linea->fill($datos)->save();

                continue;
            }
            $nueva = new TejEficienciaLine(['Folio' => $folio, 'NoTelarId' => $noTelar, 'Turno' => $turno, 'Date' => $fecha] + $datos);
            $ahora = $nueva->freshTimestampString();
            $nuevas[] = $nueva->getAttributes() + ['created_at' => $ahora, 'updated_at' => $ahora];
        }

        if ($nuevas === []) {
            return;
        }

        try {
            DB::connection((new TejEficienciaLine)->getConnectionName())->transaction(function () use ($nuevas) {
                foreach (array_chunk($nuevas, self::LINEAS_POR_INSERT) as $bloque) {
                    TejEficienciaLine::insert($bloque);
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            foreach ($nuevas as $fila) {
                TejEficienciaLine::updateOrCreate(
                    ['Folio' => $fila['Folio'], 'NoTelarId' => $fila['NoTelarId'], 'Turno' => $fila['Turno'], 'Date' => $fecha],
                    array_diff_key($lineas[(int) $fila['NoTelarId']], array_flip(['Folio', 'NoTelarId', 'Turno', 'Date']))
                );
            }
        }
    }

    /** 422 con el contrato del módulo (success/message/errors). */
    private function errorDeValidacion(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Revisa los datos enviados',
            'errors' => $e->errors(),
        ], 422);
    }

    /**
     * PUT /modulo-cortes-de-eficiencia/{id}: era un stub que respondía "actualizado" sin escribir
     * nada y no tiene llamadores (la pantalla guarda con store()). 410 en vez de un éxito falso
     * (20-03-MAPA-AUTHZ). La ruta sigue en module.permission:modificar,105,auditar.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Esta acción ya no existe; usa Guardar del corte.',
        ], 410);
    }

    /**
     * Actualizar campos del registro TejEficiencia (solo supervisores).
     */
    public function actualizarRegistro(Request $request, $folio)
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
            }

            $puesto = strtolower(trim($user->puesto ?? ''));
            if ($puesto !== 'supervisor') {
                return response()->json(['success' => false, 'message' => 'No tienes permisos para editar registros'], 403);
            }

            $corte = TejEficiencia::find($folio);
            if (! $corte) {
                return response()->json(['success' => false, 'message' => 'Folio no encontrado'], 404);
            }

            $datos = [];

            if ($request->has('Date')) {
                try {
                    $datos['Date'] = Carbon::parse($request->input('Date'))->toDateString();
                } catch (\Exception $e) {
                    return response()->json(['success' => false, 'message' => 'Fecha inválida'], 422);
                }
            }

            if ($request->has('Turno')) {
                $turno = (int) $request->input('Turno');
                if (! in_array($turno, [1, 2, 3], true)) {
                    return response()->json(['success' => false, 'message' => 'Turno inválido (debe ser 1, 2 o 3)'], 422);
                }
                $datos['Turno'] = $turno;
            }

            if ($request->has('Status')) {
                $statusPermitidos = ['En Proceso', 'Finalizado'];
                if (! in_array($request->input('Status'), $statusPermitidos, true)) {
                    return response()->json(['success' => false, 'message' => 'Status inválido'], 422);
                }
                $datos['Status'] = $request->input('Status');
            }

            if ($request->has('numero_empleado')) {
                $datos['numero_empleado'] = $request->input('numero_empleado');
            }

            if ($request->has('nombreEmpl')) {
                $datos['nombreEmpl'] = $request->input('nombreEmpl');
            }

            if (empty($datos)) {
                return response()->json(['success' => false, 'message' => 'No se enviaron campos para actualizar'], 422);
            }

            // Guardar valores originales de Date y Turno antes de actualizar
            $oldDate = $corte->Date;
            $oldTurno = $corte->Turno;

            $datos['updated_at'] = now();
            $corte->update($datos);

            // Si se cambió Date o Turno, sincronizar en TejEficienciaLine
            $datosLine = [];
            if (isset($datos['Date']) && $datos['Date'] != $oldDate) {
                $datosLine['Date'] = $datos['Date'];
            }
            if (isset($datos['Turno']) && $datos['Turno'] != $oldTurno) {
                $datosLine['Turno'] = $datos['Turno'];
            }

            if (! empty($datosLine)) {
                $datosLine['updated_at'] = now();
                TejEficienciaLine::where('Folio', $folio)
                    ->update($datosLine);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado correctamente',
                'corte' => $corte->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar registro de corte de eficiencia: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al actualizar el registro'], 500);
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

            if (! $corte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Corte no encontrado',
                ], 404);
            }

            // Verificar que no esté ya finalizado
            if ($corte->Status === 'Finalizado') {
                return response()->json([
                    'success' => false,
                    'message' => 'El corte ya está finalizado',
                ], 400);
            }

            // Actualizar el status a Finalizado
            $corte->Status = 'Finalizado';
            $corte->updated_at = now();
            $corte->save();

            // Notificar por Telegram automáticamente al finalizar (DESACTIVADO)
            /*
            try {
                Log::info('Iniciando notificación automática por Telegram para folio: ' . $corte->Folio, [
                    'fecha' => $corte->Date,
                    'turno' => $corte->Turno
                ]);

                $success = $this->enviarReporteTelegramInternal($corte->Date, $corte->Turno, Auth::user());

                if ($success) {
                    Log::info('Notificación automática enviada exitosamente para folio: ' . $corte->Folio);
                } else {
                    Log::warning('No se enviaron datos en la notificación automática (posiblemente sin líneas para la fecha/turno) para folio: ' . $corte->Folio);
                }
            } catch (\Exception $e) {
                Log::error('Error al enviar notificación automática de cortes: ' . $e->getMessage(), [
                    'folio' => $corte->Folio,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            */

            $pdfUrl = route('cortes.eficiencia.pdf', $corte->Folio);

            return response()->json([
                'success' => true,
                'message' => 'Corte de eficiencia finalizado exitosamente',
                'data' => [
                    'folio' => $corte->Folio,
                    'status' => $corte->Status,
                    'pdf_url' => $pdfUrl,
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al finalizar corte de eficiencia', 'Error al finalizar el corte de eficiencia');
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

            if (! $corte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Corte no encontrado',
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
                    'datos_telares' => $lineas,
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener corte de eficiencia', 'Error al obtener el corte de eficiencia');
        }
    }

    /**
     * Descargar PDF de un corte de eficiencia
     */
    public function pdf($id)
    {
        $corte = TejEficiencia::where('Folio', $id)->first();
        if (! $corte) {
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

        $html = view('modulos.cortes-eficiencia.pdf', [
            'corte' => $corte,
            'telares' => $telares,
            'lineasPorTelar' => $lineasPorTelar,
        ])->render();

        $options = new Options;
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

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="corte-eficiencia-'.$corte->Folio.'.pdf"');
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
            $telares = ReqProgramaTejido::whereIn('NoTelarId', $telaresOrden)
                ->where('EnProceso', 1)
                ->select('NoTelarId', 'VelocidadSTD', 'EficienciaSTD')
                ->get()
                ->map(function ($telar) {
                    return [
                        'NoTelar' => $telar->NoTelarId,
                        'VelocidadSTD' => $telar->VelocidadSTD ?? 0,
                        'EficienciaSTD' => $telar->EficienciaSTD ?? 0,
                    ];
                });

            // Si no se encontraron telares en proceso, intentar obtener los últimos datos disponibles
            if ($telares->isEmpty()) {
                Log::warning('No se encontraron telares con EnProceso=1, buscando últimos registros disponibles', [
                    'telares_solicitados' => $telaresOrden,
                ]);

                // El último registro de cada telar (MAX(Id)) en UNA consulta; antes, una por telar.
                $ultimos = ReqProgramaTejido::query()
                    ->whereIn('Id', ReqProgramaTejido::query()
                        ->selectRaw('MAX(Id)')
                        ->whereIn('NoTelarId', $telaresOrden)
                        ->groupBy('NoTelarId'))
                    ->select('NoTelarId', 'VelocidadSTD', 'EficienciaSTD')
                    ->get()
                    ->keyBy(fn ($r) => (string) $r->NoTelarId);

                $telares = collect($telaresOrden)->map(function ($telarId) use ($ultimos) {
                    $ultimoRegistro = $ultimos->get((string) $telarId);

                    if ($ultimoRegistro) {
                        return [
                            'NoTelar' => $ultimoRegistro->NoTelarId,
                            'VelocidadSTD' => $ultimoRegistro->VelocidadSTD ?? 0,
                            'EficienciaSTD' => $ultimoRegistro->EficienciaSTD ?? 0,
                        ];
                    }

                    Log::warning('No se encontraron datos para telar', ['NoTelarId' => $telarId]);

                    return [
                        'NoTelar' => $telarId,
                        'VelocidadSTD' => 0,
                        'EficienciaSTD' => 0,
                    ];
                })->filter();
            }

            return response()->json([
                'success' => true,
                'telares' => $telares,
            ]);

        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener datos de programa tejido', 'Error al obtener datos de programa tejido');
        }
    }

    /**
     * Listado de fallas de Cortes de Eficiencia (Clave y Descripción)
     */
    public function getFallasCe()
    {
        try {
            $fallas = TejeFallasCeModel::query()
                ->select(['Clave', 'Descripcion'])
                ->orderBy('Clave')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $fallas,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener fallas CE: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener las fallas',
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
                'turno' => 'required|integer|in:1,2,3',
                'horario' => 'required|integer|min:1|max:3',
                'hora' => 'required|string',
                'fecha' => 'required|date',
            ]);

            // Determinar el campo de horario correcto según el número
            $campoHorario = 'Horario'.$validated['horario'];

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
                'updated_at' => now(),
            ];

            if ($registro) {
                // Actualizar registro existente solo actualizando el campo de horario específico
                DB::table('TejEficiencia')
                    ->where('Folio', $validated['folio'])
                    ->where('Turno', $validated['turno'])
                    ->update([$campoHorario => $validated['hora'], 'updated_at' => now()]);

            } else {
                // Crear nuevo registro
                $datos['created_at'] = now();
                DB::table('TejEficiencia')->insert($datos);

            }

            return response()->json([
                'success' => true,
                'message' => 'Hora guardada exitosamente',
                'data' => $datos,
            ]);

        } catch (ValidationException $e) {
            return $this->errorDeValidacion($e);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al guardar hora en TejEficiencia', 'Error al guardar la hora');
        }
    }

    /**
     * Visualizar cortes de eficiencia de los 3 turnos por fecha
     */
    public function visualizar($folio)
    {
        try {
            $corteBase = TejEficiencia::where('Folio', $folio)->first();
            if (! $corteBase) {
                return redirect()->route('cortes.eficiencia.consultar')
                    ->with('error', 'Folio no encontrado');
            }

            $info = $this->obtenerDatosVisualizacionPorFecha($corteBase->Date, $corteBase->Turno);

            return view('modulos.cortes-eficiencia.visualizar-cortes-eficiencia', [
                'folio' => $folio,
                'fecha' => $info['fecha'],
                'datos' => $info['datos'],
                'foliosPorTurno' => $info['foliosPorTurno'],
                'horariosPorTurno' => $info['horariosPorTurno'],
                'coberturaT4PorTurno' => $info['coberturaT4PorTurno'] ?? [],
                'maxTurno' => $corteBase->Turno,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('cortes.eficiencia.consultar')
                ->with('error', 'Error al visualizar el corte (ref: '.$this->traceIdDeError($e).')');
        }
    }

    public function visualizarFolio($folio)
    {
        try {
            $corte = TejEficiencia::where('Folio', $folio)->first();
            if (! $corte) {
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
            Log::error('Error al visualizar folio de cortes: '.$e->getMessage());

            return redirect()->route('cortes.eficiencia.consultar')
                ->with('error', 'Error al visualizar folio');
        }
    }

    public function exportarVisualizacionExcel(Request $request)
    {
        try {
            $fecha = $request->input('fecha');
            if (! $fecha) {
                return response()->json(['error' => 'Fecha requerida'], 400);
            }

            $fechaNorm = $this->normalizarFecha($fecha);
            $info = $this->obtenerDatosVisualizacionPorFecha($fechaNorm);

            if ($info['datos']->isEmpty()) {
                return response()->json(['error' => 'Sin datos para la fecha seleccionada'], 404);
            }

            $filename = 'cortes_eficiencia_'.$fechaNorm.'.xlsx';

            return Excel::download(new CortesEficienciaExport($info, $fechaNorm), $filename);
        } catch (\Throwable $th) {
            return $this->errorConClaveError($th, 'Error al exportar Excel de cortes de eficiencia', 'Error al exportar');
        }
    }

    public function descargarVisualizacionPDF(Request $request)
    {
        try {
            $fecha = $request->input('fecha');
            if (! $fecha) {
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
                'horariosPorTurno' => $info['horariosPorTurno'],
                'coberturaT4PorTurno' => $info['coberturaT4PorTurno'] ?? [],
                'maxTurno' => $info['maxTurno'] ?? 3,
            ])->render();

            $options = new Options;
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            $options->set('isPhpEnabled', false);
            $options->set('chroot', public_path());
            $options->set('tempDir', sys_get_temp_dir());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();

            $pdfContent = $dompdf->output();
            $filename = 'cortes_eficiencia_'.$fechaNorm.'.pdf';

            if (empty($pdfContent)) {
                Log::error('PDF de cortes de eficiencia generado vacío', ['fecha' => $fechaNorm]);

                return response()->json(['error' => 'Error: PDF generado está vacío'], 500);
            }

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } catch (\Throwable $th) {
            return $this->errorConClaveError($th, 'Error al generar PDF de cortes de eficiencia', 'Error al generar PDF');
        }
    }

    /** apiErrorResponse + la clave `error` que estos dos endpoints mandaban desde antes. */
    private function errorConClaveError(\Throwable $e, string $log, string $mensaje): JsonResponse
    {
        $respuesta = $this->apiErrorResponse($e, $log, $mensaje);

        return $respuesta->setData($respuesta->getData(true) + ['error' => $mensaje]);
    }

    private function obtenerDatosVisualizacionPorFecha($fecha, $maxTurno = null)
    {
        $fechaNorm = $this->normalizarFecha($fecha);

        $query = TejEficienciaLine::whereDate('Date', $fechaNorm);
        if ($maxTurno !== null) {
            $query->where('Turno', '<=', (int) $maxTurno);
        }

        $lineasFecha = $query->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('Folio')
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
            $turno = (string) $linea->Turno;
            if (! isset($porTelarTurno[$telar])) {
                $porTelarTurno[$telar] = [];
            }
            if (! isset($porTelarTurno[$telar][$turno])) {
                $porTelarTurno[$telar][$turno] = $linea;
            }
            if (! isset($foliosPorTurno[$turno])) {
                $foliosPorTurno[$turno] = $linea->Folio;
            }
        }

        $cortesPorTurnoFolio = TejEficiencia::whereDate('Date', $fechaNorm)
            ->whereIn('Turno', [1, 2, 3])
            ->get()
            ->groupBy(function ($corte) {
                return (string) $corte->Turno.'|'.(string) $corte->Folio;
            });

        $horariosPorTurno = [];
        $coberturaT4PorTurno = [];
        foreach ([1, 2, 3] as $turno) {
            $folioTurno = $foliosPorTurno[(string) $turno] ?? null;
            $key = (string) $turno.'|'.(string) $folioTurno;
            $corteTurno = ($folioTurno !== null && isset($cortesPorTurnoFolio[$key]))
                ? $cortesPorTurnoFolio[$key]->first()
                : null;

            $horariosPorTurno[(string) $turno] = [
                1 => $this->formatearHora($corteTurno->Horario1 ?? null),
                2 => $this->formatearHora($corteTurno->Horario2 ?? null),
                3 => $this->formatearHora($corteTurno->Horario3 ?? null),
            ];

            // El turno 4 no se guarda: se marca que este turno lo cubrió el comodín.
            $coberturaT4PorTurno[(string) $turno] = [
                'cubierto' => TurnoHelper::esCoberturaT4($corteTurno->numero_empleado ?? null),
                'empleado' => $corteTurno->nombreEmpl ?? null,
            ];
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
            'horariosPorTurno' => $horariosPorTurno,
            'coberturaT4PorTurno' => $coberturaT4PorTurno,
        ];
    }

    /**
     * Destinatarios: SYSMensajes con CorteSEF=1 y Activo=1.
     *
     * @return array{enviados: int, total: int}
     */
    private function enviarReporteCortesPdfTelegram(string $pdfContent, string $filename, string $fecha, $usuario = null): array
    {
        $sinEnvio = ['enviados' => 0, 'total' => 0];

        try {
            Log::info('Iniciando enviarReporteCortesPdfTelegram');
            $botToken = config('services.telegram.bot_token');
            if (empty($botToken)) {
                Log::warning('No se pudo enviar PDF de cortes: TELEGRAM_BOT_TOKEN no configurado');

                return $sinEnvio;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo('CorteSEF');
            Log::info('Chat IDs obtenidos para CorteSEF', ['chatIds' => $chatIds]);

            if (empty($chatIds)) {
                Log::warning('No hay destinatarios con CorteSEF activo en SYSMensajes');

                return $sinEnvio;
            }

            if (empty($pdfContent)) {
                Log::warning('PDF de cortes vacío, no se envía a Telegram', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                ]);

                return ['enviados' => 0, 'total' => count($chatIds)];
            }

            $pdfSizeMB = strlen($pdfContent) / 1024 / 1024;
            if ($pdfSizeMB > 50) {
                Log::warning('PDF de cortes excede límite de Telegram', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                    'size_mb' => round($pdfSizeMB, 2),
                ]);

                return ['enviados' => 0, 'total' => count($chatIds)];
            }

            $nombreUsuario = $usuario->nombre ?? $usuario->name ?? null;
            $numeroEmpleado = $usuario->numero_empleado ?? null;

            $caption = "Reporte Cortes de Eficiencia\n";
            $caption .= "Fecha: {$fecha}\n";
            if (! empty($nombreUsuario)) {
                $caption .= "Generado por: {$nombreUsuario}";
                if (! empty($numeroEmpleado)) {
                    $caption .= " ({$numeroEmpleado})";
                }
            }

            $resultados = app(TelegramEnvio::class)->archivo('sendDocument', $chatIds, $pdfContent, $filename, $caption);
            TelegramEnvio::registrarFallos($resultados, 'cortes', ['fecha' => $fecha, 'filename' => $filename]);

            return ['enviados' => TelegramEnvio::enviados($resultados), 'total' => count($resultados)];
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar PDF de cortes a Telegram', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fecha' => $fecha,
                'filename' => $filename,
            ]);

            return ['enviados' => 0, 'total' => count($chatIds ?? [])];
        }
    }

    /**
     * Generar PDF y enviarlo por Telegram (sin descarga al navegador).
     */
    public function notificarTelegram(Request $request)
    {
        try {
            $fecha = null;
            $maxTurno = null;
            $folio = trim((string) $request->input('folio', ''));

            if ($folio !== '') {
                $corte = TejEficiencia::where('Folio', $folio)->first();
                if (! $corte) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Folio no encontrado',
                    ], 404);
                }

                $fecha = $corte->Date;
                $maxTurno = (int) $corte->Turno;
            } else {
                $fecha = $request->input('fecha');
            }

            if (! $fecha) {
                return response()->json(['success' => false, 'message' => 'Fecha o folio requerido'], 400);
            }

            $resultado = $this->enviarReporteTelegramInternal($fecha, $maxTurno, Auth::user());

            if ($resultado === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo enviar el reporte por Telegram',
                ], 500);
            }

            // PERF-13: el usuario ve a cuántos destinatarios llegó, no un "exitosamente" fijo.
            $enviado = $resultado['enviados'] > 0;

            return response()->json([
                'success' => $enviado,
                'message' => TelegramEnvio::resumen('Reporte', $resultado['enviados'], $resultado['total'])
                    .($enviado && $folio !== '' ? ' para el folio '.$folio : ''),
                'enviados' => $resultado['enviados'],
                'destinatarios' => $resultado['total'],
            ], $enviado ? 200 : 500);
        } catch (\Throwable $th) {
            return $this->apiErrorResponse($th, 'Error al notificar por Telegram cortes de eficiencia', 'Error al enviar por Telegram');
        }
    }

    /**
     * Recibir imagen del reporte y enviarla por Telegram.
     */
    public function notificarTelegramImagen(Request $request)
    {
        try {
            $request->validate([
                'imagen' => 'required|file|mimes:jpg,jpeg,png|max:10240',
                'fecha' => 'nullable|date',
            ]);

            $imagen = $request->file('imagen');
            if (! $imagen || ! $imagen->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imagen inválida para enviar por Telegram',
                ], 422);
            }

            $fecha = $request->input('fecha') ?: now()->toDateString();
            $fechaNorm = $this->normalizarFecha($fecha);
            $extension = strtolower($imagen->getClientOriginalExtension() ?: 'jpg');
            $filename = 'cortes_eficiencia_'.$fechaNorm.'.'.$extension;
            $imageContent = file_get_contents($imagen->getRealPath());

            $resultado = $this->enviarReporteCortesImagenTelegram($imageContent, $filename, $fechaNorm, Auth::user());
            $enviado = $resultado['enviados'] > 0;

            return response()->json([
                'success' => $enviado,
                'message' => TelegramEnvio::resumen('Imagen', $resultado['enviados'], $resultado['total'], femenino: true),
                'enviados' => $resultado['enviados'],
                'destinatarios' => $resultado['total'],
            ], $enviado ? 200 : 500);
        } catch (ValidationException $e) {
            return $this->errorDeValidacion($e);
        } catch (\Throwable $th) {
            return $this->apiErrorResponse($th, 'Error al notificar imagen por Telegram cortes de eficiencia', 'Error al enviar la imagen por Telegram');
        }
    }

    /**
     * Destinatarios: SYSMensajes con CorteSEF=1 y Activo=1.
     *
     * @return array{enviados: int, total: int}
     */
    private function enviarReporteCortesImagenTelegram(string $imageContent, string $filename, string $fecha, $usuario = null): array
    {
        $sinEnvio = ['enviados' => 0, 'total' => 0];

        try {
            $botToken = config('services.telegram.bot_token');
            if (empty($botToken)) {
                Log::warning('No se pudo enviar imagen de cortes: TELEGRAM_BOT_TOKEN no configurado');

                return $sinEnvio;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo('CorteSEF');
            if (empty($chatIds)) {
                Log::warning('No hay destinatarios con CorteSEF activo en SYSMensajes (imagen)');

                return $sinEnvio;
            }

            if (empty($imageContent)) {
                Log::warning('Imagen de cortes vacía, no se envía a Telegram', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                ]);

                return ['enviados' => 0, 'total' => count($chatIds)];
            }

            $imageSizeMB = strlen($imageContent) / 1024 / 1024;
            if ($imageSizeMB > 10) {
                Log::warning('Imagen de cortes excede el límite de 10 MB', [
                    'fecha' => $fecha,
                    'filename' => $filename,
                    'size_mb' => round($imageSizeMB, 2),
                ]);

                return ['enviados' => 0, 'total' => count($chatIds)];
            }

            $nombreUsuario = $usuario->nombre ?? $usuario->name ?? null;
            $numeroEmpleado = $usuario->numero_empleado ?? null;

            $caption = "Reporte Cortes de Eficiencia (Imagen)\n";
            $caption .= "Fecha: {$fecha}\n";
            if (! empty($nombreUsuario)) {
                $caption .= "Generado por: {$nombreUsuario}";
                if (! empty($numeroEmpleado)) {
                    $caption .= " ({$numeroEmpleado})";
                }
            }

            $resultados = app(TelegramEnvio::class)->archivo('sendDocument', $chatIds, $imageContent, $filename, $caption);
            TelegramEnvio::registrarFallos($resultados, 'imagen de cortes', ['fecha' => $fecha, 'filename' => $filename]);

            return ['enviados' => TelegramEnvio::enviados($resultados), 'total' => count($resultados)];
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar imagen de cortes a Telegram', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fecha' => $fecha,
                'filename' => $filename,
            ]);

            return ['enviados' => 0, 'total' => count($chatIds ?? [])];
        }
    }

    /**
     * Lógica interna para generar el PDF y enviar a Telegram.
     *
     * @return array{enviados: int, total: int}|null null si no hay datos o el PDF salió vacío.
     */
    private function enviarReporteTelegramInternal($fecha, $maxTurno = null, $usuario = null): ?array
    {
        Log::info('Ejecutando enviarReporteTelegramInternal', ['fecha' => $fecha, 'maxTurno' => $maxTurno]);

        $fechaNorm = $this->normalizarFecha($fecha);
        $info = $this->obtenerDatosVisualizacionPorFecha($fechaNorm, $maxTurno);

        if ($info['datos']->isEmpty()) {
            Log::warning('No se encontraron datos para la visualización en enviarReporteTelegramInternal', ['fecha' => $fechaNorm, 'maxTurno' => $maxTurno]);

            return null;
        }

        Log::info('Datos encontrados para reporte', ['total_datos' => count($info['datos'])]);

        // Generar el PDF
        $html = view('modulos.cortes-eficiencia.visualizar-cortes-eficiencia-pdf', [
            'fecha' => $info['fecha'],
            'datos' => $info['datos'],
            'foliosPorTurno' => $info['foliosPorTurno'],
            'horariosPorTurno' => $info['horariosPorTurno'],
            'coberturaT4PorTurno' => $info['coberturaT4PorTurno'] ?? [],
            'maxTurno' => $maxTurno,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $filename = 'cortes_eficiencia_'.$fechaNorm.'.pdf';

        if (empty($pdfContent)) {
            Log::error('PDF de cortes de eficiencia generado vacío', ['fecha' => $fechaNorm]);

            return null;
        }

        Log::info('PDF generado exitosamente, procediendo a enviar por Telegram', ['filename' => $filename, 'size' => strlen($pdfContent)]);

        // Enviar por Telegram
        return $this->enviarReporteCortesPdfTelegram($pdfContent, $filename, $fechaNorm, $usuario);
    }

    private function normalizarFecha($fecha)
    {
        if ($fecha instanceof Carbon) {
            return $fecha->toDateString();
        }

        return date('Y-m-d', strtotime(str_replace('/', '-', (string) $fecha)));
    }
}
