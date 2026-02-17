<?php

namespace App\Http\Controllers\Engomado\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Engomado\CatUbicaciones;
use App\Traits\ProduccionTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ModuloProduccionEngomadoController extends Controller
{
    use ProduccionTrait;

    protected function getProduccionModelClass(): string
    {
        return EngProduccionEngomado::class;
    }

    protected function getProgramaModelClass(): string
    {
        return EngProgramaEngomado::class;
    }

    protected function getDepartamento(): string
    {
        return 'Engomado';
    }

    protected function shouldRoundKgBruto(): bool
    {
        return true;
    }

    // ─── index ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $hasFinalizarPermission = true;
        $ordenId = $request->query('orden_id');

        if ($request->query('check_only') === 'true' && $ordenId) {
            $orden = EngProgramaEngomado::find($ordenId);
            if (!$orden) {
                return response()->json(['puedeCrear' => false, 'tieneRegistros' => false, 'error' => 'Orden no encontrada'], 404);
            }

            $registrosCount = EngProduccionEngomado::where('Folio', $orden->Folio)->count();
            $user = Auth::user();

            return response()->json([
                'puedeCrear' => true,
                'tieneRegistros' => $registrosCount > 0,
                'usuarioArea' => $user ? ($user->area ?? null) : null,
            ]);
        }

        if (!$ordenId) {
            $foliosPrograma = EngProgramaEngomado::where('Status', '!=', 'Finalizado')
                ->orderBy('Folio', 'desc')
                ->get(['Folio', 'Cuenta', 'Calibre', 'RizoPie', 'BomFormula']);

            return view('modulos.engomado.modulo-produccion-engomado', [
                'orden' => null,
                'julios' => collect([]),
                'metros' => '0',
                'metrosProduccion' => null,
                'destino' => null,
                'hilo' => null,
                'hiloFibra' => '-',
                'tipoAtado' => null,
                'nomEmpl' => null,
                'hasFinalizarPermission' => $hasFinalizarPermission,
                'observaciones' => '',
                'totalRegistros' => 0,
                'registrosProduccion' => collect([]),
                'foliosPrograma' => $foliosPrograma,
            ]);
        }

        $orden = EngProgramaEngomado::find($ordenId);
        if (!$orden) {
            return redirect()->route('engomado.programar.engomado')->with('error', 'Orden no encontrada');
        }

        // Verificar que la orden de urdido esté finalizada
        $urdido = UrdProgramaUrdido::where('Folio', $orden->Folio)->first();
        if (!$urdido || $urdido->Status !== 'Finalizado') {
            return redirect()->route('engomado.programar.engomado')
                ->with('error', "No se puede cargar la orden. La orden de urdido debe tener status 'Finalizado' antes de poder ponerla en proceso en engomado.");
        }

        if ($orden->Status === 'Programado') {
            try {
                $orden->Status = 'En Proceso';
                $orden->save();
            } catch (\Throwable $e) {
                // Error al actualizar status
            }
        }

        $julios = UrdJuliosOrden::where('Folio', $orden->Folio)
            ->whereNotNull('Julios')
            ->orderBy('Julios')
            ->get();

        $totalRegistros = (int) ($orden->NoTelas ?? 0);

        // Obtener sólidos desde la última formulación del folio
        $solidosFormulacion = null;
        $formulacion = EngProduccionFormulacionModel::where('Folio', $orden->Folio)->first();
        if ($formulacion && $formulacion->Solidos !== null) {
            $solidosFormulacion = $formulacion->Solidos;
        }

        // Crear registros faltantes
        if ($totalRegistros > 0) {
            try {
                if ($orden->Folio) {
                    $registrosExistentes = EngProduccionEngomado::where('Folio', $orden->Folio)->count();
                    $registrosFaltantes = max(0, $totalRegistros - $registrosExistentes);

                    if ($registrosFaltantes > 0) {
                        $user = Auth::user();
                        $claveUsuario = $user ? ($user->numero_empleado ?? null) : null;
                        $nombreUsuario = $user ? ($user->nombre ?? null) : null;
                        $turnoUsuario = $user ? ($user->turno ?? null) : null;
                        if (!$turnoUsuario) {
                            $turnoUsuario = \App\Helpers\TurnoHelper::getTurnoActual();
                        }
                        $metrosOrden = $orden->MetrajeTelas ?? $orden->Metros ?? 0;

                        for ($i = 0; $i < $registrosFaltantes; $i++) {
                            $data = [
                                'Folio' => $orden->Folio,
                                'NoJulio' => null,
                                'Fecha' => now()->format('Y-m-d'),
                            ];
                            if ($solidosFormulacion !== null) $data['Solidos'] = $solidosFormulacion;
                            if (!empty($claveUsuario)) $data['CveEmpl1'] = $claveUsuario;
                            if (!empty($nombreUsuario)) $data['NomEmpl1'] = $nombreUsuario;
                            if ($metrosOrden > 0) $data['Metros1'] = round($metrosOrden, 2);
                            if (!empty($turnoUsuario)) $data['Turno1'] = (int) $turnoUsuario;

                            try {
                                EngProduccionEngomado::create($data);
                            } catch (\Throwable $e) {
                                Log::error('Error al crear registro de producción Engomado', [
                                    'folio' => $orden->Folio,
                                    'error' => $e->getMessage(),
                                ]);
                                continue;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Error general al crear registros en EngProduccionEngomado', [
                    'folio' => $orden->Folio,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->traitAutollenarOficial1EnRegistrosSinHoraInicial($orden);
        $registrosProduccion = EngProduccionEngomado::where('Folio', $orden->Folio)->orderBy('Id')->get();

        $user = Auth::user();
        $foliosPrograma = EngProgramaEngomado::where('Status', '!=', 'Finalizado')
            ->orderBy('Folio', 'desc')
            ->get(['Folio', 'Cuenta', 'Calibre', 'RizoPie', 'BomFormula']);

        return view('modulos.engomado.modulo-produccion-engomado', [
            'orden' => $orden,
            'julios' => $julios,
            'metros' => $orden->MetrajeTelas ? number_format($orden->MetrajeTelas, 0, '.', ',') : ($orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0'),
            'destino' => $orden->SalonTejidoId ?? null,
            'hilo' => $orden->Fibra ?? null,
            'hiloFibra' => !empty($orden->Fibra) ? $orden->Fibra : '-',
            'tipoAtado' => $orden->TipoAtado ?? null,
            'nomEmpl' => $orden->NomEmpl ?? null,
            'hasFinalizarPermission' => $hasFinalizarPermission,
            'observaciones' => $orden->Observaciones ?? '',
            'totalRegistros' => $totalRegistros,
            'registrosProduccion' => $registrosProduccion,
            'usuarioNombre' => $user ? ($user->nombre ?? '') : '',
            'usuarioClave' => $user ? ($user->numero_empleado ?? '') : '',
            'urdido' => $orden->MaquinaUrd ?? null,
            'nucleo' => $orden->Nucleo ?? null,
            'noTelas' => $orden->NoTelas ?? null,
            'anchoBalonas' => $orden->AnchoBalonas ?? null,
            'metrajeTelas' => $orden->MetrajeTelas ? number_format($orden->MetrajeTelas, 0, '.', ',') : null,
            'metrosProduccion' => $orden->MetrajeTelas ?? $orden->Metros ?? null,
            'cuendeadosMin' => $orden->Cuentados ?? null,
            'loteProveedor' => $orden->LoteProveedor ?? null,
            'mermaGoma' => $orden->MermaGoma ?? null,
            'merma' => $orden->Merma ?? null,
            'usuarioArea' => $user ? ($user->area ?? null) : null,
            'ubicaciones' => CatUbicaciones::orderBy('Codigo')->get(),
            'foliosPrograma' => $foliosPrograma,
        ]);
    }

    // ─── endpoints específicos de Engomado ───────────────────────────

    public function getUsuariosEngomado(): JsonResponse
    {
        try {
            $usuarios = SYSUsuario::select(['idusuario', 'numero_empleado', 'nombre', 'turno'])
                ->where('area', 'Engomado')
                ->whereNotNull('numero_empleado')
                ->orderBy('nombre')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->idusuario,
                    'numero_empleado' => $u->numero_empleado,
                    'nombre' => $u->nombre,
                    'turno' => $u->turno,
                ]);

            return response()->json(['success' => true, 'data' => $usuarios]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Error al obtener usuarios: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarCamposProduccion(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:Solidos,Canoa1,Canoa2,Canoa3,Humedad,Ubicacion,Roturas',
                'valor' => 'nullable',
            ]);

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (!$registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado con ID: ' . $request->registro_id], 404);
            }

            $campo = $request->campo;

            if ($campo === 'Ubicacion') {
                $valor = $request->valor !== null && $request->valor !== '' ? (string) $request->valor : null;
            } else {
                if ($request->valor !== null && $request->valor !== '') {
                    if (!is_numeric($request->valor)) {
                        return response()->json(['success' => false, 'error' => 'El valor debe ser numérico para el campo ' . $campo], 422);
                    }
                    $valor = (float) $request->valor;
                    if ($campo === 'Solidos') {
                        $valor = round($valor, 2);
                    }
                } else {
                    $valor = null;
                }
            }

            if ($campo === 'Roturas' && $valor !== null) {
                $valor = (int) $valor;
            }

            if (!in_array($campo, $registro->getFillable())) {
                return response()->json(['success' => false, 'error' => 'El campo ' . $campo . ' no está permitido para actualización'], 422);
            }

            $registro->$campo = $valor;

            if (!$registro->save()) {
                return response()->json(['success' => false, 'error' => 'No se pudo guardar el registro'], 500);
            }

            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($campo) . ' actualizado correctamente',
                'data' => ['campo' => $campo, 'valor' => $registro->$campo],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar campo: ' . $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    public function actualizarCampoOrden(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer',
                'campo' => 'required|string|in:merma_con_goma,merma_sin_goma',
                'valor' => 'nullable',
            ]);

            $orden = EngProgramaEngomado::find($request->orden_id);

            if (!$orden) {
                return response()->json(['success' => false, 'error' => 'Orden no encontrada'], 404);
            }

            $valor = null;
            if ($request->has('valor') && $request->valor !== null && $request->valor !== '') {
                if (!is_numeric($request->valor)) {
                    return response()->json(['success' => false, 'error' => 'El valor debe ser numérico'], 422);
                }
                $valor = (float) $request->valor;
            }

            $campoMap = ['merma_con_goma' => 'MermaGoma', 'merma_sin_goma' => 'Merma'];
            $campoBD = $campoMap[$request->campo] ?? null;

            if (!$campoBD) {
                return response()->json(['success' => false, 'error' => 'Campo no válido'], 422);
            }

            $orden->$campoBD = $valor;
            $orden->save();
            $orden->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('_', ' ', $request->campo)) . ' actualizado correctamente',
                'data' => ['campo' => $request->campo, 'valor' => $orden->$campoBD],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Invalid column name')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Las columnas MermaGoma y Merma no existen en la tabla. Por favor, ejecuta el script SQL para agregarlas.',
                ], 500);
            }
            return response()->json(['success' => false, 'error' => 'Error de base de datos al actualizar campo: ' . $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Error al actualizar campo: ' . $e->getMessage()], 500);
        }
    }

    public function verificarFormulaciones(Request $request): JsonResponse
    {
        try {
            $request->validate(['folio' => 'required|string|max:50']);

            $folio = $request->input('folio');
            $count = EngProduccionFormulacionModel::where('Folio', $folio)->count();

            return response()->json(['success' => true, 'tieneFormulaciones' => $count > 0, 'cantidad' => $count]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Error al verificar formulaciones: ' . $e->getMessage()], 500);
        }
    }

    public function finalizar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $orden = EngProgramaEngomado::find($request->orden_id);

            if (!$orden) {
                return response()->json(['success' => false, 'error' => 'Orden no encontrada'], 404);
            }

            if (!in_array($orden->Status, ['En Proceso', 'Parcial'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo se puede finalizar una orden en estado "En Proceso" o "Parcial". Estado actual: ' . $orden->Status,
                ], 422);
            }

            if ($this->traitHasNegativeKgNetoByFolio($orden->Folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden porque existen registros con Kg Neto negativo.',
                ], 422);
            }

            // Validar horas
            $errorHoras = $this->validarHorasRegistros($orden->Folio);
            if ($errorHoras) {
                return response()->json(['success' => false, 'error' => $errorHoras], 422);
            }

            $formulacionesExistentes = EngProduccionFormulacionModel::where('Folio', $orden->Folio)->count();
            if ($formulacionesExistentes === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden. Debe existir al menos una formulación (EngProduccionFormulacion) con el Folio ' . $orden->Folio . ' antes de finalizar.',
                ], 422);
            }

            EngProduccionEngomado::where('Folio', $orden->Folio)->update(['Finalizar' => 1]);

            $orden->Status = 'Finalizado';
            $orden->save();

            EngProduccionFormulacionModel::where('Folio', $orden->Folio)->update(['Status' => 'Finalizado']);

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
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al finalizar orden de engomado', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al finalizar la orden: ' . $e->getMessage()], 500);
        }
    }
}
