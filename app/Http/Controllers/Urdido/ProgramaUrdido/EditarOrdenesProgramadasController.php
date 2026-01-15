<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Urdido\UrdJuliosOrden;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditarOrdenesProgramadasController extends Controller
{
    /**
     * Verificar si el usuario puede editar órdenes
     */
    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return false;
        }

        $area = strtolower(trim($usuario->area ?? ''));
        // NO convertir puesto a minúsculas para poder buscar todas las variantes
        $puesto = trim($usuario->puesto ?? '');

        // Verificar área (case-insensitive)
        $areaValida = $area === 'urdido';

        // Verificar puesto - buscar "supervisor" sin importar mayúsculas/minúsculas usando stripos
        $puestoValido = !empty($puesto) && stripos($puesto, 'supervisor') !== false;

        return $areaValida && $puestoValido;
    }

    /**
     * Mostrar la vista de edición de orden programada
     *
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {
        $ordenId = $request->query('orden_id');

        if (!$ordenId) {
            return redirect()->route('urdido.programar.urdido')
                ->with('error', 'No se proporcionó el ID de la orden');
        }

        // Buscar la orden
        $orden = UrdProgramaUrdido::find($ordenId);

        if (!$orden) {
            return redirect()->route('urdido.programar.urdido')
                ->with('error', 'Orden no encontrada');
        }

        // Verificar que la orden NO esté en proceso
        if ($orden->Status === 'En Proceso') {
            return redirect()->route('urdido.programar.urdido')
                ->with('error', 'No se pueden editar órdenes con status "En Proceso". Solo se pueden editar órdenes con status "Programado".');
        }

        // Verificar permisos
        if (!$this->usuarioPuedeEditar()) {
            return redirect()->route('urdido.programar.urdido')
                ->with('error', 'No tienes permisos para editar órdenes. Solo supervisores del área Urdido pueden editar.');
        }

        // Obtener información de engomado si existe
        $engomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();
        $julios = $orden->julios()->orderBy('Id')->get();

        // Obtener máquinas disponibles del área Urdido
        $maquinas = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->orderBy('MaquinaId')
            ->get();

        // Obtener fibras/hilos disponibles del catálogo ReqMatrizHilos
        // Usar distinct para obtener valores únicos de Hilo (que es el identificador principal)
        $fibras = ReqMatrizHilos::select('Hilo', 'Fibra')
            ->whereNotNull('Hilo')
            ->where('Hilo', '!=', '')
            ->orderBy('Hilo')
            ->get()
            ->unique('Hilo')
            ->values();

        // Formatear metros con separador de miles
        $metros = $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0';

        return view('modulos.urdido.editar-orden-programada', [
            'orden' => $orden,
            'engomado' => $engomado,
            'metros' => $metros,
            'maquinas' => $maquinas,
            'fibras' => $fibras,
            'julios' => $julios,
            'puedeEditar' => $this->usuarioPuedeEditar(),
        ]);
    }

    /**
     * Actualizar campos de la orden programada
     * Sincroniza cambios con EngProgramaEngomado cuando corresponda
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizar(Request $request): JsonResponse
    {
        try {
            if (!$this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para editar órdenes',
                ], 403);
            }

            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'campo' => ['required', 'string', Rule::in([
                    'RizoPie',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'Kilos',
                    'Fibra',
                    'SalonTejidoId',
                    'MaquinaId',
                    'FechaProg',
                    'TipoAtado',
                    'LoteProveedor',
                    'Observaciones',
                ])],
                'valor' => 'nullable|string|max:500',
            ]);

            // Validar que el folio no se pueda editar
            if ($request->campo === 'Folio') {
                return response()->json([
                    'success' => false,
                    'error' => 'El folio no se puede editar. Es un campo de solo lectura.',
                ], 422);
            }

            $orden = UrdProgramaUrdido::findOrFail($request->orden_id);

            $engomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();
            if (!$engomado) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontro un folio relacionado en Engomado para esta orden.',
                ], 422);
            }

            // Verificar que la orden NO esté en proceso
            if ($orden->Status === 'En Proceso') {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pueden editar órdenes con status "En Proceso"',
                ], 422);
            }

            $campo = $request->campo;
            $valor = $request->valor;

            // Mapeo de campos entre UrdProgramaUrdido y EngProgramaEngomado
            $camposSincronizados = [
                'RizoPie' => 'RizoPie',
                'Cuenta' => 'Cuenta',
                'Calibre' => 'Calibre',
                'Metros' => 'Metros',
                'Kilos' => 'Kilos',
                'Fibra' => 'Fibra',
                'SalonTejidoId' => 'SalonTejidoId',
                'FechaProg' => 'FechaProg',
                'TipoAtado' => 'TipoAtado',
                'LoteProveedor' => 'LoteProveedor',
                'Observaciones' => 'Observaciones',
            ];

            // Campos especiales que requieren conversión
            $camposNumericos = ['Calibre', 'Metros', 'Kilos'];
            $camposFecha = ['FechaProg'];

            DB::beginTransaction();

            try {
                // Convertir valor según el tipo de campo
                if (in_array($campo, $camposNumericos)) {
                    $valor = $valor !== null && $valor !== '' ? (float)$valor : null;
                } elseif (in_array($campo, $camposFecha)) {
                    $valor = $valor !== null && $valor !== '' ? $valor : null;
                } elseif ($campo === 'Observaciones') {
                    $valor = $valor ?? '';
                } else {
                    $valor = $valor !== null && $valor !== '' ? trim($valor) : null;
                }

                // Actualizar campo en UrdProgramaUrdido
                $orden->$campo = $valor;
                $orden->save();

                // Si el campo debe sincronizarse con EngProgramaEngomado
                if (isset($camposSincronizados[$campo])) {
                    $campoEngomado = $camposSincronizados[$campo];

                    // Buscar registro de engomado relacionado
                    // Actualizar campo correspondiente en EngProgramaEngomado
                    $engomado->$campoEngomado = $valor;
                    $engomado->save();

                }

                // Campos especiales que también se sincronizan con nombres diferentes
                if ($campo === 'MaquinaId') {
                    $engomado->MaquinaUrd = $valor;
                    $engomado->save();
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Campo actualizado correctamente',
                    'data' => [
                        'campo' => $campo,
                        'valor' => $valor,
                    ],
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar orden programada', [
                'orden_id' => $request->orden_id ?? null,
                'campo' => $request->campo ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener datos de la orden para edición
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function obtenerOrden(Request $request): JsonResponse
    {
        try {
            $ordenId = $request->query('orden_id');

            if (!$ordenId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se proporcionó el ID de la orden',
                ], 400);
            }

            $orden = UrdProgramaUrdido::find($ordenId);

            if (!$orden) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar que la orden NO esté en proceso
            if ($orden->Status === 'En Proceso') {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pueden editar órdenes con status "En Proceso"',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'rizo_pie' => $orden->RizoPie,
                    'cuenta' => $orden->Cuenta,
                    'calibre' => $orden->Calibre,
                    'metros' => $orden->Metros,
                    'kilos' => $orden->Kilos,
                    'fibra' => $orden->Fibra,
                    'salon_tejido_id' => $orden->SalonTejidoId,
                    'maquina_id' => $orden->MaquinaId,
                    'fecha_prog' => $orden->FechaProg ? ($orden->FechaProg instanceof \DateTime ? $orden->FechaProg->format('Y-m-d') : $orden->FechaProg) : null,
                    'tipo_atado' => $orden->TipoAtado,
                    'lote_proveedor' => $orden->LoteProveedor,
                    'observaciones' => $orden->Observaciones,
                    'status' => $orden->Status,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener orden para edición', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar No. Julio y Hilos para una orden
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarJulios(Request $request): JsonResponse
    {
        try {
            if (!$this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para editar órdenes',
                ], 403);
            }

            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'id' => 'nullable|integer|exists:UrdJuliosOrden,Id',
                'no_julio' => 'nullable|integer|min:1',
                'hilos' => 'nullable|integer|min:1',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->orden_id);

            if ($orden->Status === 'En Proceso') {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pueden editar órdenes con status "En Proceso"',
                ], 422);
            }

            $noJulio = $request->input('no_julio');
            $hilos = $request->input('hilos');
            $registroId = $request->input('id');

            $noJulioVacio = $noJulio === null || $noJulio === '';
            $hilosVacio = $hilos === null || $hilos === '';

            if ($noJulioVacio && $hilosVacio) {
                if ($registroId) {
                    UrdJuliosOrden::where('Id', $registroId)
                        ->where('Folio', $orden->Folio)
                        ->delete();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Registro de julio eliminado',
                    'data' => [
                        'deleted' => true,
                    ],
                ]);
            }

            if ($noJulioVacio || $hilosVacio) {
                return response()->json([
                    'success' => false,
                    'error' => 'No. Julio y Hilos son requeridos para guardar',
                ], 422);
            }

            try {
                $registro = null;
                if ($registroId) {
                    $registro = UrdJuliosOrden::where('Id', $registroId)
                        ->where('Folio', $orden->Folio)
                        ->first();

                    if (!$registro) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Registro de julio no encontrado',
                        ], 404);
                    }
                }

                DB::beginTransaction();

                if (!$registro) {
                    $registro = new UrdJuliosOrden();
                    $registro->Folio = $orden->Folio;
                }

                $registro->Julios = (int) $noJulio;
                $registro->Hilos = (int) $hilos;
                $registro->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Julio actualizado correctamente',
                    'data' => [
                        'id' => $registro->Id,
                    ],
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar julios de la orden', [
                'orden_id' => $request->orden_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar julios: ' . $e->getMessage(),
            ], 500);
        }
    }
}
