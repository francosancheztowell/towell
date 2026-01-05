<?php

namespace App\Http\Controllers\Urdido\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\UrdProgramaUrdido;
use App\Models\UrdJuliosOrden;
use App\Models\EngProgramaEngomado;
use App\Models\UrdProduccionUrdido;
use App\Models\UrdCatJulios;
use App\Models\SYSUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModuloProduccionUrdidoController extends Controller
{
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
            $puedeCrearRegistros = ($usuarioArea === 'Urdido');

            return response()->json([
                'puedeCrear' => $puedeCrearRegistros,
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
            // Validar que no haya otra orden con status "En Proceso"
            $ordenesEnProceso = UrdProgramaUrdido::where('Status', 'En Proceso')
                ->where('Id', '!=', $orden->Id)
                ->count();

            if ($ordenesEnProceso > 0) {
                return redirect()->route('urdido.programar.urdido')
                    ->with('error', 'Ya existe una orden con status "En Proceso". No se puede cargar otra orden hasta finalizar la actual.');
            }

            try {
                $statusAnterior = $orden->Status;
                $orden->Status = 'En Proceso';
                $orden->save();

                Log::info('Status actualizado a "En Proceso"', [
                    'folio' => $orden->Folio,
                    'orden_id' => $orden->Id,
                    'status_anterior' => $statusAnterior,
                    'status_nuevo' => 'En Proceso',
                ]);
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
        $julios = UrdJuliosOrden::where('Folio', $orden->Folio)
            ->whereNotNull('Julios')
            ->orderBy('Julios')
            ->get();

        // Calcular el total de registros (suma de todos los números de julio)
        // Si hay un julio con número 12, se generan 12 filas
        // Si hay un julio con número 15, se generan 15 filas
        // Si hay múltiples julios, se suman todos
        $totalRegistros = 0;
        if ($julios->count() > 0) {
            foreach ($julios as $julio) {
                $numeroJulio = (int) ($julio->Julios ?? 0);
                if ($numeroJulio > 0) {
                    $totalRegistros += $numeroJulio;
                }
            }
        }

        // Obtener registros existentes en UrdProduccionUrdido para este Folio
        $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)
            ->orderBy('Id')
            ->get();

        // Verificar área del usuario
        $usuarioActual = Auth::user();
        $usuarioArea = $usuarioActual ? ($usuarioActual->area ?? null) : null;
        $puedeCrearRegistros = ($usuarioArea === 'Urdido');
        $tieneRegistrosExistentes = $registrosProduccion->count() > 0;

        // Si el usuario no es del área Urdido y no hay registros existentes, no permitir crear
        if (!$puedeCrearRegistros && !$tieneRegistrosExistentes) {
            return redirect()->route('urdido.programar.urdido')
                ->with('error', 'No tienes permisos para crear registros en este módulo. Solo usuarios del área Urdido pueden crear registros.');
        }

        // Crear registros basándose en los julios de UrdJuliosOrden
        // Para cada julio, crear N registros (donde N = valor de Julios)
        // NoJulio será null al crear, pero Hilos se rellenan desde UrdJuliosOrden
        // SOLO crear si el usuario tiene el área correcta
        if ($julios->count() > 0 && $puedeCrearRegistros) {
            try {
                $registrosACrear = [];

                // Contar cuántos registros ya existen para este folio
                $registrosExistentes = $registrosProduccion->count();

                // Calcular cuántos registros faltan
                $registrosFaltantes = max(0, $totalRegistros - $registrosExistentes);

                // Crear los registros faltantes, distribuyendo los Hilos de los julios
                // Iterar por los julios y crear N registros para cada uno (donde N = valor de Julios)
                $indiceRegistro = 0;
                foreach ($julios as $julio) {
                    $numeroJulio = (int) ($julio->Julios ?? 0);
                    $hilos = $julio->Hilos ?? null;

                    if ($numeroJulio > 0) {
                        // Crear N registros para este julio (donde N = numeroJulio)
                        // Pero solo crear los que faltan
                        for ($i = 0; $i < $numeroJulio && $indiceRegistro < $registrosFaltantes; $i++) {
                            $registrosACrear[] = [
                                'Folio' => $orden->Folio,
                                'TipoAtado' => $orden->TipoAtado ?? null,
                                'NoJulio' => null, // NoJulio debe ser null al crear los registros
                                'Hilos' => $hilos, // Rellenar Hilos desde UrdJuliosOrden
                                'Fecha' => now()->format('Y-m-d'), // Establecer fecha actual al crear el registro
                            ];
                            $indiceRegistro++;
                        }
                    }
                }

                // Crear todos los registros en lote si hay alguno
                if (count($registrosACrear) > 0) {
                    foreach ($registrosACrear as $registroData) {
                        UrdProduccionUrdido::create($registroData);
                    }

                    Log::info('Registros creados en UrdProduccionUrdido', [
                        'folio' => $orden->Folio,
                        'creados' => count($registrosACrear),
                        'total_requerido' => $totalRegistros,
                        'detalle' => array_map(function($r) {
                            return ['NoJulio' => $r['NoJulio'] ?? 'null', 'Hilos' => $r['Hilos']];
                        }, $registrosACrear),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Error al crear registros en UrdProduccionUrdido', [
                    'folio' => $orden->Folio,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Recargar los registros después de crear los faltantes
            $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)
                ->orderBy('Id')
                ->get();
        }

        // Debug temporal - remover después
        Log::info('ModuloProduccionUrdido - Debug', [
            'folio' => $orden->Folio,
            'julios_count' => $julios->count(),
            'julios_data' => $julios->map(function($j) {
                return ['Julios' => $j->Julios, 'Hilos' => $j->Hilos];
            })->toArray(),
            'totalRegistros' => $totalRegistros
        ]);

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
        $usuarioNombre = $usuarioActual ? ($usuarioActual->nombre ?? '') : '';
        $usuarioClave = $usuarioActual ? ($usuarioActual->numero_empleado ?? '') : '';

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
            $guardado = $registro->save();

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
            $guardado = $registro->save();

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

            // Cambiar el status a "Finalizado"
            $orden->Status = 'Finalizado';
            $orden->save();

            Log::info('Orden de urdido finalizada', [
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
