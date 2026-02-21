<?php

namespace App\Http\Controllers\Engomado\ProgramaEngomado;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Engomado\CatUbicaciones;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditarOrdenesEngomadoController extends Controller
{
    private const ACCION_METROS_SOLO_CAMPO = 'solo_campo';
    private const ACCION_METROS_ACTUALIZAR_TODA = 'actualizar_produccion_toda';
    private const ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO = 'actualizar_produccion_sin_hora_inicio';

    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return false;
        }

        $puesto = trim($usuario->puesto ?? '');
        $puestoValido = !empty($puesto) && stripos($puesto, 'supervisor') !== false;

        return $puestoValido;
    }

    private function accionesMetrosPermitidasPorStatus(string $statusActual): array
    {
        if ($statusActual === 'Finalizado') {
            return [
                self::ACCION_METROS_SOLO_CAMPO,
                self::ACCION_METROS_ACTUALIZAR_TODA,
            ];
        }

        if ($statusActual === 'En Proceso') {
            return [
                self::ACCION_METROS_SOLO_CAMPO,
                self::ACCION_METROS_ACTUALIZAR_TODA,
                self::ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO,
            ];
        }

        return [self::ACCION_METROS_SOLO_CAMPO];
    }

    private function sincronizarMetrosProduccion(EngProgramaEngomado $orden, ?float $metros, string $accionMetros): int
    {
        $query = EngProduccionEngomado::where('Folio', $orden->Folio);

        if ($accionMetros === self::ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO) {
            $query->where(function ($q) {
                $q->whereNull('HoraInicial')->orWhere('HoraInicial', '');
            });
        }

        $metrosNormalizados = $metros !== null ? round($metros, 2) : null;

        return $query->update([
            'Metros1' => $metrosNormalizados,
            'Metros2' => null,
            'Metros3' => null,
        ]);
    }

    private function crearRegistrosProduccionDesdeNoTelas(EngProgramaEngomado $orden, int $cantidad): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        $usuario = Auth::user();
        $claveUsuario = $usuario ? ($usuario->numero_empleado ?? null) : null;
        $nombreUsuario = $usuario ? ($usuario->nombre ?? null) : null;
        $turnoUsuario = $usuario ? ($usuario->turno ?? null) : null;
        if (!$turnoUsuario) {
            $turnoUsuario = \App\Helpers\TurnoHelper::getTurnoActual();
        }

        $metrosBase = $orden->Metros ?? null;
        $solidosBase = EngProduccionEngomado::where('Folio', $orden->Folio)
            ->whereNotNull('Solidos')
            ->orderByDesc('Id')
            ->value('Solidos');

        $creados = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $data = [
                'Folio' => $orden->Folio,
                'NoJulio' => null,
                'Fecha' => now()->format('Y-m-d'),
            ];
            if (!empty($claveUsuario)) {
                $data['CveEmpl1'] = $claveUsuario;
            }
            if (!empty($nombreUsuario)) {
                $data['NomEmpl1'] = $nombreUsuario;
            }
            if (!empty($turnoUsuario)) {
                $data['Turno1'] = (int) $turnoUsuario;
            }
            if ($metrosBase !== null && (float) $metrosBase > 0) {
                $data['Metros1'] = round((float) $metrosBase, 2);
            }
            if ($solidosBase !== null && $solidosBase !== '') {
                $data['Solidos'] = (float) $solidosBase;
            }

            $registro = EngProduccionEngomado::create($data);
            $creados[] = [
                'id' => (int) $registro->Id,
                'metros' => (float) (($registro->Metros1 ?? 0) + ($registro->Metros2 ?? 0) + ($registro->Metros3 ?? 0)),
                'solidos' => $registro->Solidos !== null ? (float) $registro->Solidos : null,
            ];
        }

        return $creados;
    }

    private function eliminarRegistrosProduccionPorNoTelas(EngProgramaEngomado $orden, int $cantidad): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        $ids = EngProduccionEngomado::where('Folio', $orden->Folio)
            ->orderByRaw("CASE WHEN HoraInicial IS NULL OR LTRIM(RTRIM(HoraInicial)) = '' THEN 0 ELSE 1 END ASC")
            ->orderBy('Id', 'desc')
            ->limit($cantidad)
            ->pluck('Id')
            ->map(static fn($id) => (int) $id)
            ->values()
            ->all();

        if (count($ids) === 0) {
            return [];
        }

        EngProduccionEngomado::whereIn('Id', $ids)->delete();
        return $ids;
    }

    /**
     * Mostrar la vista de edición de orden programada de Engomado
     */
    public function index(Request $request): View|RedirectResponse
    {
        $ordenId = $request->query('orden_id');
        $fromReimpresion = $request->query('from') === 'reimpresion';
        $routeBack = $fromReimpresion ? 'engomado.reimpresion.finalizadas' : 'engomado.programar.engomado';

        if (!$ordenId) {
            Log::warning('Editar Engomado: No se proporcionó orden_id');
            return redirect()->route($routeBack)
                ->with('error', 'No se proporcionó el ID de la orden');
        }

        $orden = EngProgramaEngomado::find($ordenId);

        if (!$orden) {
            Log::warning('Editar Engomado: Orden no encontrada', ['orden_id' => $ordenId]);
            return redirect()->route($routeBack)
                ->with('error', 'Orden no encontrada');
        }

        $puedeEditar = $this->usuarioPuedeEditar();

        if (!$puedeEditar) {
            $usuario = Auth::user();
            Log::warning('Usuario sin permisos para editar Engomado', [
                'usuario_id' => $usuario->id ?? null,
                'area' => $usuario->area ?? null,
                'puesto' => $usuario->puesto ?? null,
            ]);
        }

        $maquinas = URDCatalogoMaquina::where('Departamento', 'Engomado')
            ->orderBy('MaquinaId')
            ->get();

        $metros = $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0';

        $permiteEditarPorStatus = in_array(trim($orden->Status ?? ''), ['En Proceso', 'Programado', 'Parcial'], true);
        $permiteEditarNoTelas = in_array(trim($orden->Status ?? ''), ['En Proceso', 'Programado', 'Parcial'], true);

        $registrosProduccion = collect();
        $statusActual = trim($orden->Status ?? '');
        if (in_array($statusActual, ['Finalizado', 'En Proceso', 'Parcial'], true)) {
            $registrosProduccion = EngProduccionEngomado::where('Folio', $orden->Folio)->orderBy('Id')->get();
        }

        $ubicaciones = CatUbicaciones::orderBy('Codigo')->get();

        return view('modulos.engomado.editar-orden-engomado', [
            'orden' => $orden,
            'metros' => $metros,
            'maquinas' => $maquinas,
            'ubicaciones' => $ubicaciones,
            'permiteEditarPorStatus' => $permiteEditarPorStatus,
            'permiteEditarNoTelas' => $permiteEditarNoTelas,
            'registrosProduccion' => $registrosProduccion,
            'puedeEditar' => $puedeEditar,
            'fromReimpresion' => $fromReimpresion,
        ]);
    }

    /**
     * Actualizar campos de la orden programada de Engomado
     * Sincroniza cambios con EngProduccionEngomado cuando corresponda
     */
    public function actualizar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'campo' => ['required', 'string', Rule::in([
                    'RizoPie',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'Fibra',
                    'InventSizeId',
                    'SalonTejidoId',
                    'MaquinaEng',
                    'BomEng',
                    'BomFormula',
                    'TipoAtado',
                    'LoteProveedor',
                    'NoTelarId',
                    'NoTelas',
                    'Observaciones',
                ])],
                'valor' => 'nullable|string|max:500',
                'accion_metros' => ['nullable', 'string', Rule::in([
                    self::ACCION_METROS_SOLO_CAMPO,
                    self::ACCION_METROS_ACTUALIZAR_TODA,
                    self::ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO,
                ])],
            ]);

            if ($request->campo === 'Folio') {
                return response()->json([
                    'success' => false,
                    'error' => 'El folio no se puede editar. Es un campo de solo lectura.',
                ], 422);
            }

            $orden = EngProgramaEngomado::findOrFail($request->orden_id);

            $campo = $request->campo;
            $valor = $request->valor;
            $statusActual = trim($orden->Status ?? '');
            $accionMetros = (string) ($request->input('accion_metros') ?? self::ACCION_METROS_SOLO_CAMPO);

            if ($campo !== 'Metros') {
                $accionMetros = self::ACCION_METROS_SOLO_CAMPO;
            }

            if ($campo === 'Metros') {
                $accionesPermitidas = $this->accionesMetrosPermitidasPorStatus($statusActual);
                if (!in_array($accionMetros, $accionesPermitidas, true)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'La opcion seleccionada para actualizar metros no aplica para el estado actual de la orden.',
                    ], 422);
                }
            }

            $camposPorStatus = ['RizoPie', 'Cuenta', 'Calibre', 'Fibra', 'MaquinaEng', 'BomEng', 'BomFormula'];
            if (in_array($campo, $camposPorStatus, true)) {
                if (!in_array($statusActual, ['En Proceso', 'Programado', 'Parcial'], true)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Solo se pueden editar estos campos cuando el estado es En Proceso, Programado o Parcial.',
                    ], 403);
                }
            }

            if ($campo === 'NoTelas' && !in_array($statusActual, ['En Proceso', 'Programado', 'Parcial'], true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No. de Telas solo se puede editar cuando el estado es En Proceso, Programado o Parcial.',
                ], 403);
            }

            $camposNumericos = ['Calibre', 'Metros', 'NoTelas'];

            DB::beginTransaction();

            try {
                $registrosProduccionActualizados = 0;
                $registrosProduccionCreados = [];
                $registrosProduccionEliminadosIds = [];

                if (in_array($campo, $camposNumericos)) {
                    $valor = $valor !== null && $valor !== '' ? (float) $valor : null;
                    if ($campo === 'NoTelas' && $valor !== null) {
                        $valor = (int) $valor;
                    }
                } elseif ($campo === 'Observaciones') {
                    $valor = $valor ?? '';
                } else {
                    $valor = $valor !== null && $valor !== '' ? trim($valor) : null;
                }

                if ($campo === 'InventSizeId' && $valor !== null) {
                    $col = DB::connection('sqlsrv')->selectOne(
                        "SELECT CHARACTER_MAXIMUM_LENGTH AS len
                         FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_NAME = 'EngProgramaEngomado' AND COLUMN_NAME = 'InventSizeId'"
                    );
                    $maxLen = (int) ($col->len ?? 0);
                    if ($maxLen > 0 && strlen($valor) > $maxLen) {
                        return response()->json([
                            'success' => false,
                            'error' => "Tamaño demasiado largo. Máximo {$maxLen} caracteres.",
                        ], 422);
                    }
                }

                $valorAntOrden = $orden->getAttribute($campo);
                $orden->$campo = $valor;
                $orden->save();
                AuditoriaUrdEng::registrar(AuditoriaUrdEng::TABLA_ENGOMADO, (int) $orden->Id, $orden->Folio, AuditoriaUrdEng::ACCION_UPDATE, AuditoriaUrdEng::formatoCampo($campo, $valorAntOrden, $valor));

                if ($campo === 'Metros' && $accionMetros !== self::ACCION_METROS_SOLO_CAMPO) {
                    $registrosProduccionActualizados = $this->sincronizarMetrosProduccion(
                        $orden,
                        $valor !== null ? (float) $valor : null,
                        $accionMetros
                    );
                }

                if ($campo === 'NoTelas' && $statusActual !== 'Programado') {
                    $anterior = $valorAntOrden !== null ? (int) $valorAntOrden : 0;
                    $nuevo = $valor !== null ? (int) $valor : 0;
                    $delta = $nuevo - $anterior;

                    if ($delta > 0) {
                        $registrosProduccionCreados = $this->crearRegistrosProduccionDesdeNoTelas($orden, $delta);
                    } elseif ($delta < 0) {
                        $registrosProduccionEliminadosIds = $this->eliminarRegistrosProduccionPorNoTelas($orden, abs($delta));
                    }
                }

                DB::commit();

                $message = 'Campo actualizado correctamente';
                if ($campo === 'Metros' && $accionMetros !== self::ACCION_METROS_SOLO_CAMPO) {
                    $message .= ". Se sincronizaron {$registrosProduccionActualizados} registro(s) de produccion.";
                }
                if ($campo === 'NoTelas') {
                    $message .= ". Produccion creada: " . count($registrosProduccionCreados) . ", eliminada: " . count($registrosProduccionEliminadosIds) . ".";
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'campo' => $campo,
                        'valor' => $valor,
                        'accion_metros' => $campo === 'Metros' ? $accionMetros : self::ACCION_METROS_SOLO_CAMPO,
                        'registros_produccion_actualizados' => $registrosProduccionActualizados,
                        'registros_produccion_creados' => $registrosProduccionCreados,
                        'registros_produccion_eliminados' => count($registrosProduccionEliminadosIds),
                        'registros_produccion_eliminados_ids' => $registrosProduccionEliminadosIds,
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
            Log::error('Error al actualizar orden programada Engomado', [
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

            $orden = EngProgramaEngomado::find($ordenId);

            if (!$orden) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orden no encontrada',
                ], 404);
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
                    'maquina_eng' => $orden->MaquinaEng,
                    'bom_eng' => $orden->BomEng,
                    'bom_formula' => $orden->BomFormula,
                    'fecha_prog' => $orden->FechaProg ? ($orden->FechaProg instanceof \DateTime ? $orden->FechaProg->format('Y-m-d') : $orden->FechaProg) : null,
                    'tipo_atado' => $orden->TipoAtado,
                    'lote_proveedor' => $orden->LoteProveedor,
                    'observaciones' => $orden->Observaciones,
                    'status' => $orden->Status,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener orden Engomado para edición', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener la orden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
