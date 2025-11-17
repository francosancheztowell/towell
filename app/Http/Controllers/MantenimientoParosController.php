<?php

namespace App\Http\Controllers;

use App\Helpers\FolioHelper;
use App\Models\CatParosFallas;
use App\Models\CatTipoFalla;
use App\Models\ManFallasParos;
use App\Models\ManOperadoresMantenimiento;
use App\Models\TelTelaresOperador;
use App\Models\URDCatalogoMaquina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MantenimientoParosController extends Controller
{
    /**
     * Departamentos disponibles para el módulo de mantenimiento.
     * Fuente: URDCatalogoMaquina.Departamento
     */
    public function departamentos(): JsonResponse
    {
        $departamentos = URDCatalogoMaquina::select('Departamento')
            ->distinct()
            ->orderBy('Departamento')
            ->pluck('Departamento');

        return response()->json([
            'success' => true,
            'data' => $departamentos,
        ]);
    }

    /**
     * Máquinas por departamento.
     *
     * - Para Urdido / Engomado: catálogo URDCatalogoMaquina (todas las máquinas del depto).
     * - Para Jacquard / Smith / Itema / Karl Mayer: máquinas asignadas al usuario
     *   autenticado en TelTelaresOperador, filtradas por salón.
     */
    public function maquinas(string $departamento): JsonResponse
    {
        try {
            $depUpper = strtoupper(trim($departamento));

            // Para Urdido / Engomado usamos directamente el catálogo URDCatalogoMaquina
            if (in_array($depUpper, ['URDIDO', 'ENGOMADO'], true)) {
                $maquinas = URDCatalogoMaquina::where('Departamento', $departamento)
                    ->orderBy('MaquinaId')
                    ->get(['MaquinaId', 'Nombre', 'Departamento']);

                return response()->json([
                    'success' => true,
                    'data' => $maquinas,
                ]);
            }

            // Para Jacquard / Smith / Itema / KarlMayer usamos TelTelaresOperador por usuario
            $usuario = Auth::user();
            $numeroEmpleado = $usuario->numero_empleado ?? null;

            if (!$numeroEmpleado) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado o sin número de empleado',
                    'data' => [],
                ], 401);
            }

            // Mapear departamento a SalonTejidoId (en TelTelaresOperador está como 'Jacquard' y 'Smith')
            $salones = match ($depUpper) {
                // Itema comparte mismos telares que Smith
                'ITEMA'     => ['Smith'],
                'JACQUARD'  => ['Jacquard'],
                'SMITH'     => ['Smith'],
                'KARLMAYER', 'KARL MAYER' => ['KARL MAYER', 'KarlMayer'],
                default     => [$departamento],
            };

            $maquinas = TelTelaresOperador::query()
                ->where('numero_empleado', $numeroEmpleado)
                ->whereIn('SalonTejidoId', $salones)
                ->select('NoTelarId as MaquinaId')
                ->distinct()
                ->orderBy('NoTelarId')
                ->get()
                ->map(function ($item) use ($departamento) {
                    return [
                        'MaquinaId'    => $item->MaquinaId,
                        'Nombre'       => $item->MaquinaId,
                        'Departamento' => $departamento,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $maquinas,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Catálogo de tipos de falla (CatTipoFalla).
     */
    public function tiposFalla(): JsonResponse
    {
        $tiposFalla = CatTipoFalla::orderBy('TipoFallaId')
            ->pluck('TipoFallaId');

        return response()->json([
            'success' => true,
            'data' => $tiposFalla,
        ]);
    }

    /**
     * Fallas por departamento desde CatParosFallas.
     */
    public function fallas(string $departamento): JsonResponse
    {
        try {
            $items = CatParosFallas::query()
                ->where('Departamento', $departamento)
                ->orderBy('Falla')
                ->get(['Falla', 'Descripcion', 'Abreviado', 'Seccion']);

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Orden de trabajo sugerida por departamento y máquina.
     *
     * - Jacquard / Smith / Itema / Karl Mayer → ReqProgramaTejido (EnProceso = 1)
     * - Urdido  → UrdProgramaUrdido (Status = 'En Proceso', por MaquinaId)
     * - Engomado → EngProgramaEngomado (Status = 'En Proceso', por MaquinaEng)
     */
    public function ordenTrabajo(string $departamento, string $maquina): JsonResponse
    {
        try {
            $depUpper = strtoupper(trim($departamento));

            // Caso URDIDO: usar programa de urdido (UrdProgramaUrdido) por MaquinaId y Status
            if ($depUpper === 'URDIDO') {
                $rows = DB::table('UrdProgramaUrdido')
                    ->where('MaquinaId', $maquina)
                    ->where('Status', 'En Proceso')
                    ->orderByDesc('FechaProg')
                    ->limit(5)
                    ->get([
                        'Folio as Orden_Prod',
                        'FechaProg as Fecha',
                        'MaquinaId',
                    ]);

                return response()->json([
                    'success' => true,
                    'data' => $rows,
                ]);
            }

            // Caso ENGOMADO: usar programa de engomado
            if ($depUpper === 'ENGOMADO') {
                $rows = DB::table('EngProgramaEngomado')
                    ->where('MaquinaEng', $maquina)
                    ->where('Status', 'En Proceso')
                    ->orderByDesc('FechaProg')
                    ->limit(5)
                    ->get([
                        'Folio as Orden_Prod',
                        'FechaProg as Fecha',
                        'MaquinaEng',
                        'SalonTejidoId',
                    ]);

                return response()->json([
                    'success' => true,
                    'data' => $rows,
                ]);
            }

            // Resto de departamentos: usar ReqProgramaTejido (planeación)
            // Mapear departamento (SysDepartamentos) a SalonTejidoId real en ReqProgramaTejido
            $salones = match ($depUpper) {
                // Itema y Smith usan salón SMIT en ReqProgramaTejido
                'ITEMA'      => ['SMIT'],
                'SMITH'      => ['SMIT'],
                // Jacquard coincide directo
                'JACQUARD'   => ['JACQUARD'],
                // Karl Mayer
                'KARLMAYER', 'KARL MAYER' => ['KARL MAYER', 'KARLMAYER'],
                default      => [$depUpper],
            };

            $rows = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->where('NoTelarId', $maquina)
                ->where('EnProceso', 1)
                ->orderByDesc('FechaInicio')
                ->limit(5)
                ->get(['NoProduccion as Orden_Prod', 'NombreProducto', 'FechaInicio', 'SalonTejidoId', 'NoTelarId']);

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar un nuevo paro/falla en ManFallasParos.
     */
    public function store(Request $request)
    {
        try {
            $usuario = Auth::user();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                ], 401);
            }

            // Validar campos requeridos
            $request->validate([
                'fecha' => 'required|date',
                'hora' => 'required',
                'depto' => 'required|string|max:30',
                'maquina' => 'required|string|max:50',
                'tipo_falla' => 'required|string|max:20',
                'falla' => 'required|string|max:20',
                'descrip' => 'nullable|string|max:100',
                'orden_trabajo' => 'nullable|string|max:50',
                'obs' => 'nullable|string',
            ]);

            // Generar folio usando FolioHelper con módulo "ParosFallas"
            $folio = FolioHelper::obtenerSiguienteFolio('ParosFallas', 5);

            if (empty($folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al generar folio',
                ], 500);
            }

            // Preparar datos para guardar
            $data = [
                'Folio' => $folio,
                'Estatus' => 'Activo',
                'Fecha' => $request->fecha,
                'Hora' => $request->hora,
                'Depto' => $request->depto,
                'MaquinaId' => $request->maquina,
                'TipoFallaId' => $request->tipo_falla,
                'Falla' => $request->falla,
                'Descripcion' => $request->descrip ?? null,
                'OrdenTrabajo' => $request->orden_trabajo ?? null,
                'Obs' => $request->obs ?? null,
                'CveEmpl' => $usuario->numero_empleado ?? null,
                'NomEmpl' => $usuario->nombre ?? null,
                'Turno' => (int)($usuario->turno ?? 1),
                'Enviado' => $request->boolean('notificar_supervisor', false),
                // Campos que se pueden llenar después (Terminar)
                'HoraFin' => null,
                'CveAtendio' => null,
                'NomAtendio' => null,
                'TurnoAtendio' => null,
            ];

            // Guardar en la base de datos
            $paro = ManFallasParos::create($data);

            Log::info('Paro/falla guardado correctamente', [
                'folio' => $folio,
                'usuario' => $usuario->numero_empleado,
                'paro_id' => $paro->Id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paro reportado correctamente',
                'data' => [
                    'folio' => $folio,
                    'id' => $paro->Id,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al guardar paro/falla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al guardar el paro: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener lista de paros/fallas activos para el reporte.
     */
    public function index(): JsonResponse
    {
        try {
            $paros = ManFallasParos::where('Estatus', 'Activo')
                ->orderByDesc('Fecha')
                ->orderByDesc('Hora')
                ->get([
                    'Id',
                    'Folio',
                    'Estatus',
                    'Fecha',
                    'Hora',
                    'Depto',
                    'MaquinaId',
                    'TipoFallaId',
                    'Falla',
                    'HoraFin',
                    'NomAtendio',
                ]);

            return response()->json([
                'success' => true,
                'data' => $paros,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener paros/fallas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Obtener un paro/falla específico por ID.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $paro = ManFallasParos::find($id);

            if (!$paro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Paro no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $paro,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener paro/falla', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalizar un paro/falla (actualizar con datos de cierre).
     */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        try {
            $paro = ManFallasParos::find($id);

            if (!$paro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Paro no encontrado',
                ], 404);
            }

            // Validar campos
            $request->validate([
                'atendio' => 'nullable|string|max:100',
                'turno' => 'nullable|integer|in:1,2,3',
                'calidad' => 'nullable|integer|min:1|max:10',
                'obs_cierre' => 'nullable|string|max:255',
            ]);

            $usuario = Auth::user();

            // Preparar datos de actualización
            $updateData = [
                'Estatus' => 'Terminado',
                'HoraFin' => now()->format('H:i:s'),
                'FechaFin' => now()->format('Y-m-d'),
            ];

            if ($request->filled('atendio')) {
                $updateData['NomAtendio'] = $request->atendio;
                $updateData['CveAtendio'] = $usuario->numero_empleado ?? null;
            }

            if ($request->filled('turno')) {
                $updateData['TurnoAtendio'] = (int)$request->turno;
            }

            if ($request->filled('calidad')) {
                $updateData['Calidad'] = (int)$request->calidad;
            }

            if ($request->filled('obs_cierre')) {
                $updateData['ObsCierre'] = $request->obs_cierre;
            }

            // Actualizar paro
            $paro->update($updateData);

            Log::info('Paro finalizado correctamente', [
                'paro_id' => $id,
                'folio' => $paro->Folio,
                'usuario' => $usuario->numero_empleado ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paro finalizado correctamente',
                'data' => [
                    'id' => $paro->Id,
                    'folio' => $paro->Folio,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al finalizar paro/falla', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al finalizar el paro: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener lista de operadores de mantenimiento para el select de "Atendio".
     */
    public function operadores(): JsonResponse
    {
        try {
            $operadores = ManOperadoresMantenimiento::select('Id', 'CveEmpl', 'NomEmpl', 'Turno', 'Depto')
                ->orderBy('NomEmpl')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $operadores,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener operadores de mantenimiento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}


