<?php

namespace App\Http\Controllers\Engomado\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdCatJulios;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModuloProduccionEngomadoController extends Controller
{
    /**
     * Extraer numero de tabla del campo MaquinaEng.
     *
     * @param string|null $maquinaEng
     * @return int|null
     */
    private function extractTablaNumber(?string $maquinaEng): ?int
    {
        if (empty($maquinaEng)) {
            return null;
        }

        $maquinaEng = trim($maquinaEng);

        if (preg_match('/west\s*point\s*(\d+)/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            if ($numero === 2) return 1;
            if ($numero === 3) return 2;
            return null;
        }

        if (preg_match('/tabla\s*(\d+)/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        if (preg_match('/izquierda/i', $maquinaEng)) {
            return 1;
        }

        if (preg_match('/derecha/i', $maquinaEng)) {
            return 2;
        }

        if (preg_match('/(\d+)\s*$/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        if (preg_match('/^(\d+)$/', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        return null;
    }

    /**
     * Mostrar la vista de producción de engomado con los datos de la orden seleccionada
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
            $orden = EngProgramaEngomado::find($ordenId);
            if (!$orden) {
                return response()->json([
                    'puedeCrear' => false,
                    'tieneRegistros' => false,
                    'error' => 'Orden no encontrada'
                ], 404);
            }

            $registrosProduccion = EngProduccionEngomado::where('Folio', $orden->Folio)->count();
            $usuarioActual = Auth::user();
            $usuarioArea = $usuarioActual ? ($usuarioActual->area ?? null) : null;
            $puedeCrearRegistros = ($usuarioArea === 'Engomado');

            return response()->json([
                'puedeCrear' => $puedeCrearRegistros,
                'tieneRegistros' => $registrosProduccion > 0,
                'usuarioArea' => $usuarioArea,
            ]);
        }

        // Si no hay orden_id, mostrar vista vacía
        if (!$ordenId) {
            return view('modulos.engomado.modulo-produccion-engomado', [
                'orden' => null,
                'julios' => collect([]),
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

        // Buscar la orden de engomado
        $orden = EngProgramaEngomado::find($ordenId);

        if (!$orden) {
            return redirect()->route('engomado.programar.engomado')
                ->with('error', 'Orden no encontrada');
        }

        // Verificar que la orden de urdido esté finalizada antes de permitir poner en proceso
        $urdido = UrdProgramaUrdido::where('Folio', $orden->Folio)->first();
        if (!$urdido || $urdido->Status !== 'Finalizado') {
            return redirect()->route('engomado.programar.engomado')
                ->with('error', "No se puede cargar la orden. La orden de urdido debe tener status 'Finalizado' antes de poder ponerla en proceso en engomado.");
        }

        // Actualizar el status a "En Proceso" cuando se carga la p?gina de producci?n
        if ($orden->Status !== 'En Proceso') {
            // Validar que no haya mas de 2 ordenes con status "En Proceso" en la misma tabla
            $tablaActual = $this->extractTablaNumber($orden->MaquinaEng);
            $limitePorTabla = 2;

            if ($tablaActual !== null) {
                $ordenesEnProceso = EngProgramaEngomado::where('Status', 'En Proceso')
                    ->whereNotNull('MaquinaEng')
                    ->where('Id', '!=', $orden->Id)
                    ->get()
                    ->filter(function ($item) use ($tablaActual) {
                        return $this->extractTablaNumber($item->MaquinaEng) === $tablaActual;
                    })
                    ->count();

                if ($ordenesEnProceso >= $limitePorTabla) {
                    $nombreTabla = $tablaActual === 1 ? 'West Point 2' : 'West Point 3';

                    return redirect()->route('engomado.programar.engomado')
                        ->with('error', "Ya existen {$limitePorTabla} ordenes con status \"En Proceso\" en {$nombreTabla}. No se puede cargar otra orden hasta finalizar alguna de las actuales.");
                }
            } else {
                return redirect()->route('engomado.programar.engomado')
                    ->with('error', 'No se pudo determinar la tabla de la maquina. No se puede cargar la orden.');
            }

            try {
                $statusAnterior = $orden->Status;
                $orden->Status = 'En Proceso';
                $orden->save();

                Log::info('Status actualizado a "En Proceso" (Engomado)', [
                    'folio' => $orden->Folio,
                    'orden_id' => $orden->Id,
                    'status_anterior' => $statusAnterior,
                    'status_nuevo' => 'En Proceso',
                ]);
            } catch (\Throwable $e) {
                Log::error('Error al actualizar status a "En Proceso" (Engomado)', [
                    'folio' => $orden->Folio,
                    'orden_id' => $orden->Id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Obtener julios asociados al folio (comparten el mismo Folio con urdido)
        $julios = UrdJuliosOrden::where('Folio', $orden->Folio)
            ->whereNotNull('Julios')
            ->orderBy('Julios')
            ->get();

        // Calcular el total de registros basado en No. De Telas
        $totalRegistros = (int) ($orden->NoTelas ?? 0);

        // Obtener registros existentes en EngProduccionEngomado para este Folio
        $registrosProduccion = EngProduccionEngomado::where('Folio', $orden->Folio)
            ->orderBy('Id')
            ->get();

        // Verificar área del usuario
        $usuarioActual = Auth::user();
        $usuarioArea = $usuarioActual ? ($usuarioActual->area ?? null) : null;
        $puedeCrearRegistros = ($usuarioArea === 'Engomado');
        $tieneRegistrosExistentes = $registrosProduccion->count() > 0;

        // Si el usuario no es del área Engomado y no hay registros existentes, no permitir crear
        if (!$puedeCrearRegistros && !$tieneRegistrosExistentes) {
            return redirect()->route('engomado.programar.engomado')
                ->with('error', 'No tienes permisos para crear registros en este módulo. Solo usuarios del área Engomado pueden crear registros.');
        }

        // Crear registros basándose en No. De Telas
        // SOLO crear si el usuario tiene el área correcta
        if ($totalRegistros > 0 && $puedeCrearRegistros) {
            try {
                // Contar cuántos registros ya existen para este folio
                $registrosExistentes = $registrosProduccion->count();

                // Calcular cuántos registros faltan
                $registrosFaltantes = max(0, $totalRegistros - $registrosExistentes);

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

                // Crear los registros faltantes
                $registrosACrear = [];
                for ($i = 0; $i < $registrosFaltantes; $i++) {
                    // Preparar datos del registro
                    $registroData = [
                        'Folio' => $orden->Folio,
                        'NoJulio' => null, // NoJulio debe ser null al crear los registros
                        'Fecha' => now()->format('Y-m-d'), // Establecer fecha actual al crear el registro
                        'Canoa1' => null,
                        'Canoa2' => null,
                        'Canoa3' => null,
                        'Tambor' => null,
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

                // Crear todos los registros en lote si hay alguno
                if (count($registrosACrear) > 0) {
                    foreach ($registrosACrear as $index => $registroData) {
                        // Log antes de crear
                        Log::info("Creando registro {$index} (Engomado)", [
                            'datos' => $registroData
                        ]);

                        $registroCreado = EngProduccionEngomado::create($registroData);

                        // Log después de crear para verificar que se guardó correctamente
                        Log::info("Registro creado con ID: {$registroCreado->Id} (Engomado)", [
                            'CveEmpl1' => $registroCreado->CveEmpl1,
                            'NomEmpl1' => $registroCreado->NomEmpl1,
                            'Metros1' => $registroCreado->Metros1,
                            'Turno1' => $registroCreado->Turno1,
                        ]);
                    }

                    Log::info('Registros creados en EngProduccionEngomado', [
                        'folio' => $orden->Folio,
                        'creados' => count($registrosACrear),
                        'total_requerido' => $totalRegistros,
                        'no_telas' => $orden->NoTelas,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Error al crear registros en EngProduccionEngomado', [
                    'folio' => $orden->Folio,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Recargar los registros después de crear los faltantes
            $registrosProduccion = EngProduccionEngomado::where('Folio', $orden->Folio)
                ->orderBy('Id')
                ->get();
        }

        // Formatear metros con separador de miles
        $metros = $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0';

        // Obtener destino (SalonTejidoId)
        $destino = $orden->SalonTejidoId ?? null;

        // Obtener hilo (Fibra)
        $hilo = $orden->Fibra ?? null;

        // Tipo atado
        $tipoAtado = $orden->TipoAtado ?? null;

        // Nombre del empleado que ordenó
        $nomEmpl = $orden->NomEmpl ?? null;

        // Observaciones
        $observaciones = $orden->Obs ?? '';

        // Campos adicionales para la sección superior
        $urdido = $orden->MaquinaUrd ?? null;
        $nucleo = $orden->Nucleo ?? null;
        $noTelas = $orden->NoTelas ?? null;
        $anchoBalonas = $orden->AnchoBalonas ?? null;
        $metrajeTelas = $orden->MetrajeTelas ? number_format($orden->MetrajeTelas, 0, '.', ',') : null;
        $cuendeadosMin = $orden->Cuentados ?? null;
        $loteProveedor = $orden->LoteProveedor ?? null;

        // Obtener usuario autenticado para pre-rellenar en el modal
        $usuarioNombre = $usuarioActual ? ($usuarioActual->nombre ?? '') : '';
        $usuarioClave = $usuarioActual ? ($usuarioActual->numero_empleado ?? '') : '';

        return view('modulos.engomado.modulo-produccion-engomado', [
            'orden' => $orden,
            'julios' => $julios,
            'metros' => $metros,
            'destino' => $destino,
            'hilo' => $hilo,
            'tipoAtado' => $tipoAtado,
            'nomEmpl' => $nomEmpl,
            'observaciones' => $observaciones,
            'totalRegistros' => $totalRegistros,
            'registrosProduccion' => $registrosProduccion,
            'usuarioNombre' => $usuarioNombre,
            'usuarioClave' => $usuarioClave,
            'urdido' => $urdido,
            'nucleo' => $nucleo,
            'noTelas' => $noTelas,
            'anchoBalonas' => $anchoBalonas,
            'metrajeTelas' => $metrajeTelas,
            'cuendeadosMin' => $cuendeadosMin,
            'loteProveedor' => $loteProveedor,
            'puedeCrearRegistros' => $puedeCrearRegistros,
            'tieneRegistrosExistentes' => $tieneRegistrosExistentes,
            'usuarioArea' => $usuarioArea,
        ]);
    }

    /**
     * Obtener catálogo de julios desde UrdCatJulios
     * Filtrado por departamento "Engomado"
     *
     * @return JsonResponse
     */
    public function getCatalogosJulios(): JsonResponse
    {
        try {
            // Obtener julios desde el catálogo UrdCatJulios filtrados por departamento "Engomado"
            $julios = UrdCatJulios::select('NoJulio', 'Tara', 'Departamento')
                ->whereNotNull('NoJulio')
                ->where('Departamento', 'Engomado')
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
            Log::error('Error al obtener catálogo de julios (Engomado)', [
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
     * Obtener usuarios del área de Engomado
     *
     * @return JsonResponse
     */
    public function getUsuariosEngomado(): JsonResponse
    {
        try {
            $usuarios = SYSUsuario::select([
                'idusuario',
                'numero_empleado',
                'nombre',
                'turno',
            ])
            ->where('area', 'Engomado')
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
            Log::error('Error al obtener usuarios de Engomado', [
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
     * Guardar oficial en un registro de producción
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

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $numeroOficial = (int) $request->numero_oficial;
            $folio = $registro->Folio;

            $propagarOficial = false;
            if ($numeroOficial === 1) {
                $existeOficialEnFolio = EngProduccionEngomado::where('Folio', $folio)
                    ->whereNotNull("NomEmpl{$numeroOficial}")
                    ->where("NomEmpl{$numeroOficial}", '!=', '')
                    ->exists();

                $propagarOficial = !$existeOficialEnFolio;
            }

            if ($numeroOficial < 1 || $numeroOficial > 3) {
                return response()->json([
                    'success' => false,
                    'error' => 'Número de oficial inválido. Solo se permiten 3 oficiales (1, 2 o 3).',
                ], 422);
            }

            $oficialExistente = !empty($registro->{"NomEmpl{$numeroOficial}"});

            if (!$oficialExistente) {
                $oficialesRegistrados = 0;
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($registro->{"NomEmpl{$i}"})) {
                        $oficialesRegistrados++;
                    }
                }

                if ($oficialesRegistrados >= 3) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Ya se han registrado 3 oficiales (máximo permitido). Solo puedes editar los existentes.',
                    ], 422);
                }
            }

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

                EngProduccionEngomado::where('Folio', $folio)
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
            Log::error('Error al guardar oficial (Engomado)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al guardar oficial: ' . $e->getMessage(),
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

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $numeroOficial = $request->numero_oficial;

            if (empty($registro->{"NomEmpl{$numeroOficial}"})) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay un oficial registrado en esta posición',
                ], 422);
            }

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
            Log::error('Error al actualizar turno de oficial (Engomado)', [
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

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

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
            Log::error('Error al actualizar fecha (Engomado)', [
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

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $registro->NoJulio = $request->no_julio ?? null;

            $taraValue = null;
            if ($request->has('tara') && $request->tara !== null && $request->tara !== '') {
                $taraValue = (float)$request->tara;
            }
            $registro->Tara = $taraValue;

            if ($taraValue !== null) {
                $kgBruto = $registro->KgBruto !== null ? (float)$registro->KgBruto : 0;
                $kgNetoCalculado = $kgBruto - $taraValue;
                $registro->KgNeto = $kgNetoCalculado;
            } else {
                $registro->KgNeto = $registro->KgBruto !== null ? (float)$registro->KgBruto : null;
            }

            $registro->save();
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
            Log::error('Error al actualizar NoJulio y Tara (Engomado)', [
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

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $kgBrutoValue = null;
            if ($request->has('kg_bruto') && $request->kg_bruto !== null && $request->kg_bruto !== '') {
                $kgBrutoValue = (float)$request->kg_bruto;
            }
            $registro->KgBruto = $kgBrutoValue;

            if ($registro->Tara !== null) {
                $kgBruto = $kgBrutoValue !== null ? $kgBrutoValue : 0;
                $tara = (float)$registro->Tara;
                $kgNetoCalculado = $kgBruto - $tara;
                $registro->KgNeto = $kgNetoCalculado;
            } else {
                $registro->KgNeto = $kgBrutoValue !== null ? $kgBrutoValue : null;
            }

            $registro->save();
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
            Log::error('Error al actualizar KgBruto (Engomado)', [
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
     * Actualizar campos de producción (Solidos, Canoa1-3, Tambor, Humedad, Roturas)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarCamposProduccion(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:Solidos,Canoa1,Canoa2,Canoa3,Tambor,Humedad,Roturas',
                'valor' => 'nullable|numeric',
            ]);

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $campo = $request->campo;
            $valor = $request->valor !== null ? (float)$request->valor : null;

            // Para Roturas, convertir a entero
            if ($campo === 'Roturas' && $valor !== null) {
                $valor = (int)$valor;
            }

            $registro->$campo = $valor;
            $registro->save();
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
            Log::error('Error al actualizar campos de producción (Engomado)', [
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

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro no encontrado',
                ], 404);
            }

            $campo = $request->campo;
            $valor = $request->valor !== null && $request->valor !== '' ? $request->valor : null;

            $registro->$campo = $valor;
            $registro->save();
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
            Log::error('Error al actualizar horas (Engomado)', [
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
     * Finalizar orden de engomado cambiando el status de "En Proceso" a "Finalizado"
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function finalizar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $orden = EngProgramaEngomado::find($request->orden_id);

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

            // Cambiar el status a "Finalizado"
            $orden->Status = 'Finalizado';
            $orden->save();

            Log::info('Orden de engomado finalizada', [
                'folio' => $orden->Folio,
                'orden_id' => $orden->Id,
                'status_anterior' => 'En Proceso',
                'status_nuevo' => 'Finalizado',
            ]);

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
            Log::error('Error al finalizar orden de engomado', [
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
