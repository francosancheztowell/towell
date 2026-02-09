<?php

namespace App\Http\Controllers\Urdido\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdCatJulios;
use App\Models\Sistema\SYSUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModuloProduccionUrdidoController extends Controller
{
    private function hasNegativeKgNetoByFolio(string $folio): bool
    {
        return UrdProduccionUrdido::where('Folio', $folio)
            ->whereNotNull('KgNeto')
            ->where('KgNeto', '<', 0)
            ->exists();
    }

    private function hasHoraInicialCaptured(UrdProduccionUrdido $registro): bool
    {
        return $registro->HoraInicial !== null && trim((string) $registro->HoraInicial) !== '';
    }

    private function autollenarOficial1EnRegistrosSinHoraInicial(UrdProgramaUrdido $orden): void
    {
        $usuarioActual = Auth::user();
        if (!$usuarioActual) {
            return;
        }

        $claveUsuario = $usuarioActual->numero_empleado ?? null;
        $nombreUsuario = $usuarioActual->nombre ?? null;
        if (empty($claveUsuario) || empty($nombreUsuario)) {
            return;
        }

        $turnoUsuario = $usuarioActual->turno ?? \App\Helpers\TurnoHelper::getTurnoActual();

        UrdProduccionUrdido::where('Folio', $orden->Folio)
            ->where(function ($query) {
                $query->whereNull('HoraInicial')->orWhere('HoraInicial', '');
            })
            ->update([
                'CveEmpl1' => $claveUsuario,
                'NomEmpl1' => $nombreUsuario,
                'Turno1' => $turnoUsuario !== null && $turnoUsuario !== '' ? (int) $turnoUsuario : null,
            ]);
    }

    /**
     * Extraer numero de MC Coy o identificar Karl Mayer.
     *
     * @param string|null $maquinaId
     * @return int|null
     */
    private function extractMcCoyNumber(?string $maquinaId): ?int
    {
        if (empty($maquinaId)) {
            return null;
        }

        // Karl Mayer se maneja como MC Coy 4 en esta vista
        if (stripos($maquinaId, 'karl mayer') !== false) {
            return 4;
        }

        // Buscar patron "Mc Coy X" (case insensitive, permite espacios variables)
        if (preg_match('/mc\s*coy\s*(\d+)/i', $maquinaId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Mostrar la vista de producción de urdido con los datos de la orden seleccionada
     *
     * @param Request $request
     * @return View|RedirectResponse|JsonResponse
     */
    public function index(Request $request)
    {
        $ordenId = $request->query('orden_id');
        $checkOnly = $request->query('check_only') === 'true';

        // Si solo se está verificando permisos, retornar JSON
        if ($checkOnly && $ordenId) {
            $orden = UrdProgramaUrdido::find($ordenId);
            if (!$orden) {
                return response()->json([
                    'puedeCrear' => false,
                    'tieneRegistros' => false,
                    'error' => 'Orden no encontrada'
                ], 404);
            }

            $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)->count();
            $usuarioActual = Auth::user();
            $usuarioArea = $usuarioActual ? ($usuarioActual->area ?? null) : null;

            return response()->json([
                'puedeCrear' => true,
                'tieneRegistros' => $registrosProduccion > 0,
                'usuarioArea' => $usuarioArea,
            ]);
        }

        // Si no hay orden_id, mostrar vista vacía
        if (!$ordenId) {
            return view('modulos.urdido.modulo-produccion-urdido', [
                'orden' => null,
                'julios' => collect([]),
                'engomado' => null,
                'metros' => '0',
                'destino' => null,
                'hilo' => null,
                'tipoAtado' => null,
                'nomEmpl' => null,
                'observaciones' => '',
                'totalRegistros' => 0,
                'registrosProduccion' => collect([]),
            ]);
        }

        // Buscar la orden
        $orden = UrdProgramaUrdido::find($ordenId);

        if (!$orden) {
            return redirect()->route('urdido.programar.urdido')
                ->with('error', 'Orden no encontrada');
        }

        // Actualizar el status a "En Proceso" cuando se carga la página de producción
        if ($orden->Status !== 'En Proceso') {
            // Validar que no haya mas de 2 ordenes con status "En Proceso" en la misma maquina
            $mcCoyActual = $this->extractMcCoyNumber($orden->MaquinaId);
            $limitePorMaquina = 2;

            if ($mcCoyActual !== null) {
                $ordenesEnProceso = UrdProgramaUrdido::where('Status', 'En Proceso')
                    ->whereNotNull('MaquinaId')
                    ->where('Id', '!=', $orden->Id)
                    ->get()
                    ->filter(function ($item) use ($mcCoyActual) {
                        return $this->extractMcCoyNumber($item->MaquinaId) === $mcCoyActual;
                    })
                    ->count();

                if ($ordenesEnProceso >= $limitePorMaquina) {
                    $nombreMaquina = $mcCoyActual === 4 ? 'Karl Mayer' : "MC Coy {$mcCoyActual}";

                    return redirect()->route('urdido.programar.urdido')
                        ->with('error', "Ya existen {$limitePorMaquina} ordenes con status \"En Proceso\" en {$nombreMaquina}. No se puede cargar otra orden hasta finalizar alguna de las actuales.");
                }
            }

            try {
                $orden->Status = 'En Proceso';
                $orden->save();


            } catch (\Throwable $e) {
                Log::error('Error al actualizar status a "En Proceso"', [
                    'folio' => $orden->Folio,
                    'orden_id' => $orden->Id,
                    'error' => $e->getMessage(),
                ]);
                // No lanzar excepción, continuar con el flujo normal
            }
        }

        // Obtener julios asociados al folio
        // Solo obtener los que tienen Julios no null (el campo Julios indica la cantidad a crear)
        $julios = UrdJuliosOrden::where('Folio', $orden->Folio)
            ->whereNotNull('Julios')
            ->orderBy('Julios')
            ->get();

        // Calcular el total de registros: sumar todos los valores de Julios
        // Si hay un registro con Julios=6, se generan 6 registros
        // Si hay 2 registros con Julios=2 cada uno, se generan 4 registros total (2+2)
        $totalRegistros = 0;
        foreach ($julios as $julio) {
            $numeroJulio = (int) ($julio->Julios ?? 0);
            if ($numeroJulio > 0) {
                $totalRegistros += $numeroJulio;
            }
        }



        // Obtener registros existentes en UrdProduccionUrdido para este Folio
        // Contar TODOS los registros existentes para este folio (no solo los sin NoJulio)
        // porque necesitamos saber el total real de registros de producción
        $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)
            ->orderBy('Id')
            ->get();

        // Crear registros basándose en UrdJuliosOrden:
        // Julios = cantidad de registros a generar, Hilos = hilos por registro
        // Ej: Julios=2,Hilos=481 y Julios=2,Hilos=324 → 4 registros (2 con 481, 2 con 324)
        // NoJulio se asigna después por el usuario desde UrdCatJulios
        if ($julios->count() > 0) {
            try {
                $registrosACrear = [];

                // Contar TODOS los registros existentes para este folio (con o sin NoJulio)
                // NoJulio se asigna después por el usuario desde UrdCatJulios - no afecta la cantidad total
                $totalRegistrosExistentes = $registrosProduccion->count();

                // Calcular cuántos registros faltan
                // Ej: UrdJuliosOrden tiene Julios=2,Hilos=481 y Julios=2,Hilos=324 → totalRegistros=4
                // Si ya existen 4 registros, no crear más (aunque tengan NoJulio asignado)
                $registrosFaltantes = max(0, $totalRegistros - $totalRegistrosExistentes);



                // Si ya existen todos los registros necesarios, no crear más
                if ($registrosFaltantes > 0) {
                    // Obtener datos del usuario actual para asignar automáticamente
                    $usuarioActual = Auth::user();
                    $nombreUsuario = $usuarioActual ? ($usuarioActual->nombre ?? null) : null;
                    $claveUsuario = $usuarioActual ? ($usuarioActual->numero_empleado ?? null) : null;
                    $turnoUsuario = $usuarioActual ? ($usuarioActual->turno ?? null) : null;

                    // Si no tiene turno asignado, usar TurnoHelper para obtener el turno actual
                    if (!$turnoUsuario) {
                        $turnoUsuario = \App\Helpers\TurnoHelper::getTurnoActual();
                    }

                    // Obtener metros de la orden (asignar el total completo a cada registro)
                    $metrosOrden = $orden->Metros ?? 0;

                    // Crear los registros faltantes, distribuyendo los Hilos de los julios
                    // Para cada julio, crear N registros (donde N = valor de Julios)
                    // Verificar cuántos registros con ese Hilos ya existen y crear solo los faltantes
                    // Contar TODOS los registros existentes agrupados por Hilos
                    // (incluye los que ya tienen NoJulio asignado, para no crear duplicados)
                    $registrosPorHilos = [];
                    foreach ($registrosProduccion as $registro) {
                        $hilosKey = (string)($registro->Hilos ?? 'null');
                        if (!isset($registrosPorHilos[$hilosKey])) {
                            $registrosPorHilos[$hilosKey] = 0;
                        }
                        $registrosPorHilos[$hilosKey]++;
                    }

                    foreach ($julios as $julio) {
                        $numeroJulio = (int) ($julio->Julios ?? 0);
                        $hilos = $julio->Hilos ?? null;

                        if ($numeroJulio > 0 && $hilos !== null) {
                            $hilosKey = (string)$hilos;
                            $registrosExistentesParaEsteHilos = $registrosPorHilos[$hilosKey] ?? 0;
                            $registrosFaltantesParaEsteHilos = max(0, $numeroJulio - $registrosExistentesParaEsteHilos);

                            // Crear solo los registros faltantes para este Hilos
                            for ($i = 0; $i < $registrosFaltantesParaEsteHilos; $i++) {
                                $registroData = [
                                    'Folio' => $orden->Folio,
                                    'TipoAtado' => $orden->TipoAtado ?? null,
                                    'NoJulio' => null, // NoJulio debe ser null al crear los registros
                                    'Hilos' => $hilos, // Rellenar Hilos desde UrdJuliosOrden
                                    'Fecha' => now()->format('Y-m-d'), // Establecer fecha actual al crear el registro
                                ];

                                // Solo agregar campos de oficial si tienen valores
                                if (!empty($claveUsuario)) {
                                    $registroData['CveEmpl1'] = $claveUsuario;
                                }
                                if (!empty($nombreUsuario)) {
                                    $registroData['NomEmpl1'] = $nombreUsuario;
                                }
                                if ($metrosOrden > 0) {
                                    $registroData['Metros1'] = round($metrosOrden, 2);
                                }
                                if (!empty($turnoUsuario)) {
                                    $registroData['Turno1'] = (int)$turnoUsuario;
                                }

                                $registrosACrear[] = $registroData;
                            }
                        }
                    }
                }

                // Crear todos los registros en lote si hay alguno
                if (count($registrosACrear) > 0) {
                    foreach ($registrosACrear as $registroData) {
                        UrdProduccionUrdido::create($registroData);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Error al crear registros en UrdProduccionUrdido', [
                    'folio' => $orden->Folio,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Recargar los registros después de crear los faltantes (todos los registros, no solo los sin NoJulio)
            $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)
                ->orderBy('Id')
                ->get();
        }

        $this->autollenarOficial1EnRegistrosSinHoraInicial($orden);
        $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)
            ->orderBy('Id')
            ->get();


        // Obtener información de engomado si existe
        $engomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();

        // Formatear metros con separador de miles
        $metros = $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0';

        // Obtener destino (SalonTejidoId) - puede venir de la orden o del engomado
        $destino = $orden->SalonTejidoId ?? ($engomado ? $engomado->SalonTejidoId : null);

        // Obtener hilo (Fibra) - puede venir de la orden o del engomado
        $hilo = $orden->Fibra ?? ($engomado ? $engomado->Fibra : null);

        // Tipo atado - puede venir de la orden o del engomado
        $tipoAtado = $orden->TipoAtado ?? ($engomado ? $engomado->TipoAtado : null);

        // Nombre del empleado que ordenó - viene de la orden
        $nomEmpl = $orden->NomEmpl ?? null;

        // Observaciones - pueden venir del engomado
        $observaciones = $engomado ? ($engomado->Obs ?? '') : '';

        // Lote Proveedor
        $loteProveedor = $orden->LoteProveedor ?? null;

        // Obtener usuario autenticado para pre-rellenar en el modal
        $usuarioActual = Auth::user();
        $usuarioNombre = $usuarioActual ? ($usuarioActual->nombre ?? '') : '';
        $usuarioClave = $usuarioActual ? ($usuarioActual->numero_empleado ?? '') : '';
        $usuarioArea = $usuarioActual ? ($usuarioActual->area ?? null) : null;

        // Variables para la vista (sin restricción de área)
        $puedeCrearRegistros = true;
        $tieneRegistrosExistentes = $registrosProduccion->count() > 0;

        return view('modulos.urdido.modulo-produccion-urdido', [
            'orden' => $orden,
            'julios' => $julios,
            'engomado' => $engomado,
            'metros' => $metros,
            'destino' => $destino,
            'hilo' => $hilo,
            'tipoAtado' => $tipoAtado,
            'nomEmpl' => $nomEmpl,
            'observaciones' => $observaciones,
            'totalRegistros' => $totalRegistros,
            'loteProveedor' => $loteProveedor,
            'registrosProduccion' => $registrosProduccion,
            'usuarioNombre' => $usuarioNombre,
            'usuarioClave' => $usuarioClave,
            'puedeCrearRegistros' => $puedeCrearRegistros,
            'tieneRegistrosExistentes' => $tieneRegistrosExistentes,
            'usuarioArea' => $usuarioArea,
        ]);
    }

    /**
     * Obtener catálogo de julios desde UrdCatJulios
     * Retorna todos los julios del catálogo con su Tara
     * El NoJulio es independiente y no depende de los julios de la orden
     *
     * @return JsonResponse
     */
    public function getCatalogosJulios(): JsonResponse
    {
        try {
            // Obtener julios desde el catálogo UrdCatJulios, filtrando solo por departamento "Urdido"
            $julios = UrdCatJulios::select('NoJulio', 'Tara', 'Departamento')
                ->whereNotNull('NoJulio')
                ->where('Departamento', 'Urdido')
                ->orderBy('NoJulio')
                ->get()
                ->map(function($item) {
                    return [
                        'julio' => $item->NoJulio,
                        'tara' => $item->Tara ?? 0,
                        'departamento' => $item->Departamento ?? null,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $julios,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener catálogo de julios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener catálogo de julios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener Hilos de un NoJulio específico desde UrdJuliosOrden
     * Busca en los julios de la orden actual
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getHilosByJulio(Request $request): JsonResponse
    {
        try {
            $noJulio = $request->query('no_julio');
            $folio = $request->query('folio');

            if (!$noJulio || !$folio) {
                return response()->json([
                    'success' => false,
                    'error' => 'NoJulio y Folio son requeridos',
                ], 400);
            }

            // Buscar en UrdJuliosOrden el registro que coincida con el NoJulio y Folio
            $julio = UrdJuliosOrden::where('Folio', $folio)
                ->where('Julios', $noJulio)
                ->first();

            $hilos = $julio ? ($julio->Hilos ?? null) : null;

            return response()->json([
                'success' => true,
                'hilos' => $hilos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener hilos por julio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener hilos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar o actualizar oficial en un registro de producción
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function guardarOficial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'numero_oficial' => 'required|integer|in:1,2,3',
                'cve_empl' => 'required|string|max:30',
                'nom_empl' => 'required|string|max:150',
                'metros' => 'nullable|numeric|min:0',
                'turno' => 'nullable|integer|in:1,2,3',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }
            if ($this->hasHoraInicialCaptured($registro)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se permite cambiar oficiales cuando ya existe Hora Inicial.',
                ], 422);
            }

            $numeroOficial = (int) $request->numero_oficial;
            $folio = $registro->Folio;

            $propagarOficial = false;
            if ($numeroOficial === 1) {
                $existeOficialEnFolio = UrdProduccionUrdido::where('Folio', $folio)
                    ->whereNotNull("NomEmpl{$numeroOficial}")
                    ->where("NomEmpl{$numeroOficial}", '!=', '')
                    ->exists();

                $propagarOficial = !$existeOficialEnFolio;
            }

            // Validar que el número de oficial esté en el rango permitido (1-3)
            if ($numeroOficial < 1 || $numeroOficial > 3) {
                return response()->json([
                    'success' => false,
                    'error' => 'Número de oficial inválido. Solo se permiten 3 oficiales (1, 2 o 3).',
                ], 422);
            }

            // Verificar si se está intentando crear un nuevo oficial (no editar uno existente)
            // Si el oficial en esa posición ya existe, se permite editar
            // Si no existe, verificar que no haya 3 oficiales ya registrados
            $oficialExistente = !empty($registro->{"NomEmpl{$numeroOficial}"});

            if (!$oficialExistente) {
                // Contar cuántos oficiales ya están registrados
                $oficialesRegistrados = 0;
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($registro->{"NomEmpl{$i}"})) {
                        $oficialesRegistrados++;
                    }
                }

                // Si ya hay 3 oficiales, no permitir agregar uno nuevo
                if ($oficialesRegistrados >= 3) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Ya se han registrado 3 oficiales (máximo permitido). Solo puedes editar los existentes.',
                    ], 422);
                }
            }

            // Actualizar los campos correspondientes según el número de oficial
            $registro->{"CveEmpl{$numeroOficial}"} = $request->cve_empl;
            $registro->{"NomEmpl{$numeroOficial}"} = $request->nom_empl;

            if ($request->has('metros')) {
                $registro->{"Metros{$numeroOficial}"} = $request->metros;
            }

            if ($request->has('turno')) {
                $registro->{"Turno{$numeroOficial}"} = $request->turno;
            }

            $registro->save();

            if ($propagarOficial) {
                $updateData = [
                    "CveEmpl{$numeroOficial}" => $request->cve_empl,
                    "NomEmpl{$numeroOficial}" => $request->nom_empl,
                ];

                if ($request->has('turno')) {
                    $updateData["Turno{$numeroOficial}"] = $request->turno;
                }

                if ($request->has('metros')) {
                    $updateData["Metros{$numeroOficial}"] = $request->metros;
                }

                UrdProduccionUrdido::where('Folio', $folio)
                    ->where('Id', '!=', $registro->Id)
                    ->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Oficial guardado correctamente',
                'data' => [
                    'cve_empl' => $registro->{"CveEmpl{$numeroOficial}"},
                    'nom_empl' => $registro->{"NomEmpl{$numeroOficial}"},
                    'metros' => $registro->{"Metros{$numeroOficial}"} ?? null,
                    'turno' => $registro->{"Turno{$numeroOficial}"} ?? null,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al guardar oficial', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al guardar oficial: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function eliminarOficial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'numero_oficial' => 'required|integer|in:1,2,3',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }
            if ($this->hasHoraInicialCaptured($registro)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se permite cambiar oficiales cuando ya existe Hora Inicial.',
                ], 422);
            }

            $numeroOficial = (int) $request->numero_oficial;

            $registro->{"CveEmpl{$numeroOficial}"} = null;
            $registro->{"NomEmpl{$numeroOficial}"} = null;
            $registro->{"Metros{$numeroOficial}"} = null;
            $registro->{"Turno{$numeroOficial}"} = null;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Oficial eliminado correctamente',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar oficial', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al eliminar oficial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar turno de un oficial en un registro de producción
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarTurnoOficial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'numero_oficial' => 'required|integer|in:1,2,3',
                'turno' => 'required|integer|in:1,2,3',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }
            if ($this->hasHoraInicialCaptured($registro)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se permite cambiar oficiales cuando ya existe Hora Inicial.',
                ], 422);
            }

            $numeroOficial = $request->numero_oficial;

            // Verificar que el oficial existe
            if (empty($registro->{"NomEmpl{$numeroOficial}"})) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay un oficial registrado en esta posición',
                ], 422);
            }

            // Actualizar el turno del oficial
            $registro->{"Turno{$numeroOficial}"} = $request->turno;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Turno actualizado correctamente',
                'data' => [
                    'numero_oficial' => $numeroOficial,
                    'nom_empl' => $registro->{"NomEmpl{$numeroOficial}"},
                    'turno' => $registro->{"Turno{$numeroOficial}"},
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar turno de oficial', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar turno: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar fecha de un registro de producción
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarFecha(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'fecha' => 'required|date',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            // Actualizar la fecha
            $registro->Fecha = $request->fecha;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Fecha actualizada correctamente',
                'data' => [
                    'fecha' => $registro->Fecha,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar fecha', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar fecha: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar NoJulio y Tara de un registro de producción
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarJulioTara(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'no_julio' => 'nullable|string|max:10',
                'tara' => 'nullable|numeric|min:0',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            // Actualizar NoJulio y Tara
            $registro->NoJulio = $request->no_julio ?? null;

            // Procesar Tara: convertir a float si existe, null si no
            $taraValue = null;
            if ($request->has('tara') && $request->tara !== null && $request->tara !== '') {
                $taraValue = (float)$request->tara;
            }
            $registro->Tara = $taraValue;

            // Recalcular KgNeto siempre que haya Tara (incluso si es 0)
            // Si hay KgBruto, calcular KgBruto - Tara
            // Si no hay KgBruto pero hay Tara, KgNeto será -Tara (0 - Tara)
            if ($taraValue !== null) {
                $kgBruto = $registro->KgBruto !== null ? (float)$registro->KgBruto : 0;
                $kgNetoCalculado = $kgBruto - $taraValue;
                $registro->KgNeto = $kgNetoCalculado;
            } else {
                // Si no hay Tara, KgNeto puede ser null o igual a KgBruto
                $registro->KgNeto = $registro->KgBruto !== null ? (float)$registro->KgBruto : null;
            }

            // Guardar los cambios
            $registro->save();

            // Refrescar el modelo para obtener los valores actualizados de la BD
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => 'No. Julio y Tara actualizados correctamente',
                'data' => [
                    'no_julio' => $registro->NoJulio,
                    'tara' => $registro->Tara,
                    'kg_neto' => $registro->KgNeto,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar NoJulio y Tara', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar No. Julio y Tara: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar KgBruto de un registro de producción
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarKgBruto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'kg_bruto' => 'nullable|numeric|min:0',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            // Procesar KgBruto: convertir a float si existe, null si no
            $kgBrutoValue = null;
            if ($request->has('kg_bruto') && $request->kg_bruto !== null && $request->kg_bruto !== '') {
                $kgBrutoValue = (float)$request->kg_bruto;
            }
            $registro->KgBruto = $kgBrutoValue;

            // Recalcular KgNeto siempre que haya Tara
            // Si hay KgBruto, calcular KgBruto - Tara
            // Si no hay KgBruto pero hay Tara, KgNeto será -Tara (0 - Tara)
            if ($registro->Tara !== null) {
                $kgBruto = $kgBrutoValue !== null ? $kgBrutoValue : 0;
                $tara = (float)$registro->Tara;
                $kgNetoCalculado = $kgBruto - $tara;
                $registro->KgNeto = $kgNetoCalculado;
            } else {
                // Si no hay Tara, KgNeto puede ser null o igual a KgBruto
                $registro->KgNeto = $kgBrutoValue !== null ? $kgBrutoValue : null;
            }

            // Guardar los cambios
            $registro->save();

            // Refrescar el modelo para obtener los valores actualizados de la BD
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Kg. Bruto actualizado correctamente',
                'data' => [
                    'kg_bruto' => $registro->KgBruto,
                    'kg_neto' => $registro->KgNeto,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar KgBruto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar Kg. Bruto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar campos de producción (Hilatura, Maquina, Operac, Transf)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarCamposProduccion(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:Hilatura,Maquina,Operac,Transf',
                'valor' => 'nullable|integer|min:0|max:100',
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $campo = $request->campo;
            $valor = $request->valor !== null ? (int)$request->valor : null;

            // Actualizar el campo correspondiente
            $registro->$campo = $valor;
            $registro->save();

            // Refrescar el modelo para obtener los valores actualizados de la BD
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($campo) . ' actualizado correctamente',
                'data' => [
                    'campo' => $campo,
                    'valor' => $registro->$campo,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar campos de producción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar campo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar horas (HoraInicial y HoraFinal) de un registro de producción
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarHoras(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:HoraInicial,HoraFinal',
                'valor' => ['nullable', 'string', 'regex:#^([0-1][0-9]|2[0-3]):[0-5][0-9]$#'],
            ]);

            $registro = UrdProduccionUrdido::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $campo = $request->campo;
            $valor = $request->valor !== null && $request->valor !== '' ? $request->valor : null;

            // Actualizar el campo correspondiente
            $registro->$campo = $valor;
            $registro->save();

            // Refrescar el modelo para obtener los valores actualizados de la BD
            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ($campo === 'HoraInicial' ? 'Hora Inicial' : 'Hora Final') . ' actualizada correctamente',
                'data' => [
                    'campo' => $campo,
                    'valor' => $registro->$campo,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar horas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar hora: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener usuarios del área de Urdido
     *
     * @return JsonResponse
     */
    public function getUsuariosUrdido(): JsonResponse
    {
        try {
            $usuarios = SYSUsuario::select([
                'idusuario',
                'numero_empleado',
                'nombre',
                'turno',
            ])
            ->where('area', 'Urdido')
            ->whereNotNull('numero_empleado')
            ->orderBy('nombre')
            ->get();

            $usuariosFormateados = $usuarios->map(function ($usuario) {
                return [
                    'id' => $usuario->idusuario,
                    'numero_empleado' => $usuario->numero_empleado,
                    'nombre' => $usuario->nombre,
                    'turno' => $usuario->turno,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $usuariosFormateados,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener usuarios de Urdido', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener usuarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalizar orden de urdido cambiando el status de "En Proceso" a "Finalizado"
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function finalizar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $orden = UrdProgramaUrdido::find($request->orden_id);

            if (!$orden) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar que el status actual sea "En Proceso"
            if ($orden->Status !== 'En Proceso') {
                return response()->json([
                    'success' => false,
                    'error' => 'La orden no está en estado "En Proceso". Estado actual: ' . $orden->Status,
                ], 422);
            }
            if ($this->hasNegativeKgNetoByFolio($orden->Folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden porque existen registros con Kg Neto negativo.',
                ], 422);
            }

            // Eliminar registros que no tengan HoraInicial o HoraFinal antes de finalizar
            $registrosEliminados = UrdProduccionUrdido::where('Folio', $orden->Folio)
                ->where(function ($query) {
                    $query->whereNull('HoraInicial')
                        ->orWhereNull('HoraFinal');
                })
                ->delete();

            // Cambiar el status a "Finalizado"
            $orden->Status = 'Finalizado';
            $orden->save();

            return response()->json([
                'success' => true,
                'message' => 'Orden finalizada correctamente',
                'data' => [
                    'orden_id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'status' => $orden->Status,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al finalizar orden de urdido', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al finalizar la orden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
