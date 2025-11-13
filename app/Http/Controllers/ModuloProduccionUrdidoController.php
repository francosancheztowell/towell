<?php

namespace App\Http\Controllers;

use App\Models\UrdProgramaUrdido;
use App\Models\UrdJuliosOrden;
use App\Models\EngProgramaEngomado;
use App\Models\UrdProduccionUrdido;
use App\Models\UrdCatJulios;
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
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        $ordenId = $request->query('orden_id');

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

        // Crear registros basándose en los julios de UrdJuliosOrden
        // Para cada julio, crear N registros (donde N = valor de Julios)
        // Cada registro debe tener NoJulio y Hilos rellenados automáticamente
        if ($julios->count() > 0) {
            try {
                $registrosACrear = [];

                foreach ($julios as $julio) {
                    $numeroJulio = (int) ($julio->Julios ?? 0);
                    $hilos = $julio->Hilos ?? null;
                    $noJulio = $julio->Julios; // Mantener el valor original (puede ser string)

                    if ($numeroJulio > 0) {
                        // Contar cuántos registros ya existen para este NoJulio específico
                        // Convertir ambos a string para comparación segura
                        $existentesParaEsteJulio = $registrosProduccion
                            ->filter(function($registro) use ($noJulio) {
                                return (string)($registro->NoJulio ?? '') === (string)$noJulio;
                            })
                            ->count();

                        // Crear solo los que faltan
                        $faltantesParaEsteJulio = max(0, $numeroJulio - $existentesParaEsteJulio);

                        // Crear los registros faltantes para este julio
                        for ($i = 0; $i < $faltantesParaEsteJulio; $i++) {
                            $registrosACrear[] = [
                                'Folio' => $orden->Folio,
                                'TipoAtado' => $orden->TipoAtado ?? null,
                                'NoJulio' => $noJulio, // Rellenar NoJulio desde UrdJuliosOrden
                                'Hilos' => $hilos, // Rellenar Hilos desde UrdJuliosOrden
                            ];
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
                            return ['NoJulio' => $r['NoJulio'], 'Hilos' => $r['Hilos']];
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

        // Obtener usuario autenticado para pre-rellenar en el modal
        $usuarioActual = Auth::user();
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
            'registrosProduccion' => $registrosProduccion,
            'usuarioNombre' => $usuarioNombre,
            'usuarioClave' => $usuarioClave,
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
            // Obtener julios desde el catálogo UrdCatJulios
            $julios = UrdCatJulios::select('NoJulio', 'Tara', 'Departamento')
                ->whereNotNull('NoJulio')
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

            $numeroOficial = $request->numero_oficial;

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
}

