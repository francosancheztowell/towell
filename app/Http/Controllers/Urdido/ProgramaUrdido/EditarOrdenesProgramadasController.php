<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
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
    private const ACCION_METROS_SOLO_CAMPO = 'solo_campo';
    private const ACCION_METROS_ACTUALIZAR_TODA = 'actualizar_produccion_toda';
    private const ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO = 'actualizar_produccion_sin_hora_inicio';

    /**
     * Verificar si el usuario puede editar órdenes
     * Permite a cualquier supervisor editar, no solo los de urdido
     */
    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return false;
        }

        // NO convertir puesto a minúsculas para poder buscar todas las variantes
        $puesto = trim($usuario->puesto ?? '');

        // Verificar puesto - buscar "supervisor" sin importar mayúsculas/minúsculas usando stripos
        // Permitir a cualquier supervisor editar órdenes de urdido
        $puestoValido = !empty($puesto) && stripos($puesto, 'supervisor') !== false;

        return $puestoValido;
    }

    /**
     * Obtener el registro de EngProgramaEngomado que corresponde a la orden solo por Folio.
     * No se usa NoTelarId porque puede haber no_telar iguales en distintos registros.
     */
    private function obtenerEngomadoPorOrden(UrdProgramaUrdido $orden): ?EngProgramaEngomado
    {
        $folio = trim($orden->Folio ?? '');

        if ($folio === '') {
            return null;
        }

        return EngProgramaEngomado::where('Folio', $folio)->first();
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

    private function sincronizarMetrosProduccion(UrdProgramaUrdido $orden, ?float $metros, string $accionMetros): int
    {
        $query = UrdProduccionUrdido::where('Folio', $orden->Folio);

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

    private function sincronizarHilosProduccionPorFolio(UrdProgramaUrdido $orden, ?int $hilosAnterior, ?int $hilosNuevo): int
    {
        if ($hilosAnterior === null || $hilosNuevo === null || $hilosAnterior === $hilosNuevo) {
            return 0;
        }

        return UrdProduccionUrdido::where('Folio', $orden->Folio)
            ->where('Hilos', $hilosAnterior)
            ->update(['Hilos' => $hilosNuevo]);
    }

    private function crearRegistrosProduccionDesdeJulio(UrdProgramaUrdido $orden, int $cantidadJulios, int $hilos): array
    {
        if ($cantidadJulios <= 0 || $hilos <= 0) {
            return [];
        }

        $usuario = Auth::user();
        $claveUsuario = $usuario ? ($usuario->numero_empleado ?? null) : null;
        $nombreUsuario = $usuario ? ($usuario->nombre ?? null) : null;
        $turnoUsuario = $usuario ? ($usuario->turno ?? null) : null;
        $metrosOrden = $orden->Metros !== null ? round((float) $orden->Metros, 2) : null;

        $creados = [];
        for ($i = 0; $i < $cantidadJulios; $i++) {
            $data = [
                'Folio' => $orden->Folio,
                'TipoAtado' => $orden->TipoAtado ?? null,
                'NoJulio' => null,
                'Hilos' => $hilos,
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
            if ($metrosOrden !== null && $metrosOrden > 0) {
                $data['Metros1'] = $metrosOrden;
            }

            $registro = UrdProduccionUrdido::create($data);
            $creados[] = [
                'id' => (int) $registro->Id,
                'hilos' => (int) ($registro->Hilos ?? $hilos),
                'metros' => (float) (($registro->Metros1 ?? 0) + ($registro->Metros2 ?? 0) + ($registro->Metros3 ?? 0)),
            ];
        }

        return $creados;
    }

    private function eliminarRegistrosProduccionPorEliminacionJulio(UrdProgramaUrdido $orden, int $hilos, int $cantidadJulios): array
    {
        if ($hilos <= 0 || $cantidadJulios <= 0) {
            return [];
        }

        $ids = UrdProduccionUrdido::where('Folio', $orden->Folio)
            ->where('Hilos', $hilos)
            ->orderByRaw("CASE WHEN HoraInicial IS NULL OR LTRIM(RTRIM(HoraInicial)) = '' THEN 0 ELSE 1 END ASC")
            ->orderBy('Id', 'desc')
            ->limit($cantidadJulios)
            ->pluck('Id')
            ->map(static fn($id) => (int) $id)
            ->values()
            ->all();

        if (count($ids) === 0) {
            return [];
        }

        UrdProduccionUrdido::whereIn('Id', $ids)->delete();

        return $ids;
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
        $fromReimpresion = $request->query('from') === 'reimpresion';
        $routeBack = $fromReimpresion ? 'urdido.reimpresion.finalizadas' : 'urdido.programar.urdido';


        if (!$ordenId) {
            Log::warning('No se proporcionó orden_id');
            return redirect()->route($routeBack)
                ->with('error', 'No se proporcionó el ID de la orden');
        }

        // Buscar la orden
        $orden = UrdProgramaUrdido::find($ordenId);

        if (!$orden) {
            Log::warning('Orden no encontrada', ['orden_id' => $ordenId]);
            return redirect()->route($routeBack)
                ->with('error', 'Orden no encontrada');
        }

        // Verificar permisos - permitir ver la página pero mostrar advertencia si no puede editar
        // No redirigir, solo marcar si puede editar o no
        $puedeEditar = $this->usuarioPuedeEditar();

        if (!$puedeEditar) {
            $usuario = Auth::user();
            Log::warning('Usuario sin permisos para editar', [
                'usuario_id' => $usuario->id ?? null,
                'area' => $usuario->area ?? null,
                'puesto' => $usuario->puesto ?? null,
            ]);
            // No redirigir, permitir ver la página pero sin permisos de edición
        }


        $julios = $orden->julios()->orderBy('Id')->get();
        $axUrdido = (int) ($orden->AX ?? $orden->Ax ?? $orden->getAttribute('ax') ?? 0);
        if ($axUrdido === 0) {
            $axUrdido = (int) (DB::table('UrdProgramaUrdido')->where('Id', $orden->Id)->value('ax') ?? 0);
        }
        $bloqueaUrdido = $axUrdido === 1;

        // Obtener máquinas disponibles del área Urdido
        $maquinas = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->orderBy('MaquinaId')
            ->get();

        // Formatear metros con separador de miles
        $metros = $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '0';

        // Solo En Proceso o Programado permiten editar MaquinaId, BomId, Fibra, Calibre, Cuenta, RizoPie y Julios
        $permiteEditarPorStatus = in_array(trim($orden->Status ?? ''), ['En Proceso', 'Programado'], true);

        $registrosProduccion = collect();
        $mapaJuliosHilos = [];
        foreach ($julios as $j) {
            $key = trim((string) ($j->Julios ?? ''));
            if ($key !== '') {
                $mapaJuliosHilos[$key] = (int) ($j->Hilos ?? 0);
            }
        }
        if (in_array(trim($orden->Status ?? ''), ['Finalizado', 'En Proceso'], true)) {
            $registrosProduccion = UrdProduccionUrdido::where('Folio', $orden->Folio)->orderBy('Id')->get();
        }

        return view('modulos.urdido.editar-orden-programada', [
            'orden' => $orden,
            'metros' => $metros,
            'maquinas' => $maquinas,
            'julios' => $julios,
            'axUrdido' => $axUrdido,
            'bloqueaUrdido' => $bloqueaUrdido,
            'permiteEditarPorStatus' => $permiteEditarPorStatus,
            'registrosProduccion' => $registrosProduccion,
            'mapaJuliosHilos' => $mapaJuliosHilos,
            'puedeEditar' => $puedeEditar,
            'fromReimpresion' => $fromReimpresion,
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
            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'campo' => ['required', 'string', Rule::in([
                    'RizoPie',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'Fibra',
                    'InventSizeId',
                    'SalonTejidoId',
                    'MaquinaId',
                    'BomId',
                    'FechaProg',
                    'TipoAtado',
                    'LoteProveedor',
                    'FolioConsumo',
                    'NoTelarId',
                    'Observaciones',
                ])],
                'valor' => 'nullable|string|max:500',
                'accion_metros' => ['nullable', 'string', Rule::in([
                    self::ACCION_METROS_SOLO_CAMPO,
                    self::ACCION_METROS_ACTUALIZAR_TODA,
                    self::ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO,
                ])],
            ]);

            // Validar que el folio no se pueda editar
            if ($request->campo === 'Folio') {
                return response()->json([
                    'success' => false,
                    'error' => 'El folio no se puede editar. Es un campo de solo lectura.',
                ], 422);
            }

            $orden = UrdProgramaUrdido::findOrFail($request->orden_id);

            // Engomado solo por Folio (no NoTelarId). Una instancia por tabla.
            $engomado = $this->obtenerEngomadoPorOrden($orden);
            if (!$engomado) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontro un folio relacionado en Engomado para esta orden.',
                ], 422);
            }

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

            $axUrdido = (int) ($orden->AX ?? $orden->Ax ?? $orden->getAttribute('ax') ?? 0);
            if ($axUrdido === 0) {
                $axUrdido = (int) (DB::table('UrdProgramaUrdido')->where('Id', $orden->Id)->value('ax') ?? 0);
            }
            $bloqueaUrdido = $axUrdido === 1;

            // Campos que se bloquean con AX=1. NoTelarId, RizoPie, Metros, FolioConsumo, TipoAtado (Tipo) siempre se pueden editar
            $camposBloqueadosPorAx = [
                'Cuenta',
                'Calibre',
                'Fibra',
                'InventSizeId',
                'SalonTejidoId',
                'MaquinaId',
                'BomId',
                'FechaProg',
                'LoteProveedor',
                'Observaciones',
            ];

            if ($bloqueaUrdido && in_array($campo, $camposBloqueadosPorAx, true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Urdido ya está en AX. No se pueden editar campos de Urdido.',
                ], 403);
            }

            // Solo En Proceso o Programado permiten editar MaquinaId, BomId, Fibra, Calibre, Cuenta, RizoPie
            $camposPorStatus = ['RizoPie', 'Cuenta', 'Calibre', 'Fibra', 'MaquinaId', 'BomId'];
            if (in_array($campo, $camposPorStatus, true)) {
                if (!in_array($statusActual, ['En Proceso', 'Programado'], true)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Solo se pueden editar estos campos cuando el estado es En Proceso o Programado.',
                    ], 403);
                }
            }

            // Mapeo de campos entre UrdProgramaUrdido y EngProgramaEngomado (solo campos editables en esta pantalla)
            $camposSincronizados = [
                'RizoPie' => 'RizoPie',
                'Cuenta' => 'Cuenta',
                'Calibre' => 'Calibre',
                'Metros' => 'Metros',
                'Fibra' => 'Fibra',
                'InventSizeId' => 'InventSizeId',
                'SalonTejidoId' => 'SalonTejidoId',
                'FechaProg' => 'FechaProg',
                'TipoAtado' => 'TipoAtado',
                'LoteProveedor' => 'LoteProveedor',
                'NoTelarId' => 'NoTelarId',
                'Observaciones' => 'Observaciones',
            ];

            // Campos especiales que requieren conversión
            $camposNumericos = ['Calibre', 'Metros'];
            $camposFecha = ['FechaProg'];

            DB::beginTransaction();

            try {
                $registrosProduccionActualizados = 0;
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

                if ($campo === 'InventSizeId' && $valor !== null) {
                    $col = DB::connection('sqlsrv')->selectOne(
                        "SELECT CHARACTER_MAXIMUM_LENGTH AS len
                         FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_NAME = 'UrdProgramaUrdido' AND COLUMN_NAME = 'InventSizeId'"
                    );
                    $maxLen = (int) ($col->len ?? 0);
                    if ($maxLen > 0 && strlen($valor) > $maxLen) {
                        return response()->json([
                            'success' => false,
                            'error' => "Tamaño demasiado largo. Máximo {$maxLen} caracteres.",
                        ], 422);
                    }
                }

                // Actualizar campo en UrdProgramaUrdido
                $valorAntOrden = $orden->getAttribute($campo);
                $orden->$campo = $valor;
                $orden->save();
                AuditoriaUrdEng::registrar(AuditoriaUrdEng::TABLA_URDIDO, (int) $orden->Id, $orden->Folio, AuditoriaUrdEng::ACCION_UPDATE, AuditoriaUrdEng::formatoCampo($campo, $valorAntOrden, $valor));

                // Si el campo debe sincronizarse con EngProgramaEngomado
                if (isset($camposSincronizados[$campo])) {
                    $campoEngomado = $camposSincronizados[$campo];
                    $valorAntEng = $engomado->getAttribute($campoEngomado);
                    $engomado->$campoEngomado = $valor;
                    $engomado->save();
                    AuditoriaUrdEng::registrar(AuditoriaUrdEng::TABLA_ENGOMADO, (int) $engomado->Id, $engomado->Folio, AuditoriaUrdEng::ACCION_UPDATE, AuditoriaUrdEng::formatoCampo($campoEngomado, $valorAntEng, $valor));
                }

                // Campos especiales que también se sincronizan con nombres diferentes
                if ($campo === 'MaquinaId') {
                    $valorAntEng = $engomado->MaquinaUrd;
                    $engomado->MaquinaUrd = $valor;
                    $engomado->save();
                    AuditoriaUrdEng::registrar(AuditoriaUrdEng::TABLA_ENGOMADO, (int) $engomado->Id, $engomado->Folio, AuditoriaUrdEng::ACCION_UPDATE, AuditoriaUrdEng::formatoCampo('MaquinaUrd', $valorAntEng, $valor));
                }
                if ($campo === 'BomId') {
                    $valorAntEng = $engomado->BomUrd;
                    $engomado->BomUrd = $valor;
                    $engomado->save();
                    AuditoriaUrdEng::registrar(AuditoriaUrdEng::TABLA_ENGOMADO, (int) $engomado->Id, $engomado->Folio, AuditoriaUrdEng::ACCION_UPDATE, AuditoriaUrdEng::formatoCampo('BomUrd', $valorAntEng, $valor));
                }

                if ($campo === 'Metros' && $accionMetros !== self::ACCION_METROS_SOLO_CAMPO) {
                    $registrosProduccionActualizados = $this->sincronizarMetrosProduccion(
                        $orden,
                        $valor !== null ? (float) $valor : null,
                        $accionMetros
                    );
                }

                DB::commit();

                $message = 'Campo actualizado correctamente';
                if ($campo === 'Metros' && $accionMetros !== self::ACCION_METROS_SOLO_CAMPO) {
                    $message .= ". Se sincronizaron {$registrosProduccionActualizados} registro(s) de produccion.";
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'campo' => $campo,
                        'valor' => $valor,
                        'accion_metros' => $campo === 'Metros' ? $accionMetros : self::ACCION_METROS_SOLO_CAMPO,
                        'registros_produccion_actualizados' => $registrosProduccionActualizados,
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
     * Actualizar Hilos en UrdJuliosOrden (tabla No. Julio / Hilos) y
     * sincronizar Hilos en UrdProduccionUrdido por Folio + Hilos anterior.
     */
    public function actualizarHilosProduccion(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'no_julio' => 'required',
                'hilos' => 'required',
            ]);
            $orden = UrdProgramaUrdido::findOrFail($request->orden_id);
            $noJulio = $request->input('no_julio');
            $hilos = (int) $request->input('hilos');
            $hilosAnterior = null;
            $registrosProduccionActualizados = 0;
            $k = trim((string) $noJulio);
            $julio = UrdJuliosOrden::where('Folio', $orden->Folio)
                ->where(function ($q) use ($k) {
                    $q->where('Julios', $k)->orWhere('Julios', (int) $k);
                })->first();

            if (!$julio) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontro el registro de julio para actualizar hilos.',
                ], 404);
            }

            $hilosAnterior = $julio->Hilos !== null ? (int) $julio->Hilos : null;
            $julio->Hilos = $hilos;
            $julio->save();
            if (trim((string) ($orden->Status ?? '')) !== 'Programado') {
                $registrosProduccionActualizados = $this->sincronizarHilosProduccionPorFolio($orden, $hilosAnterior, $hilos);
            }
            return response()->json([
                'success' => true,
                'message' => 'Hilos actualizados',
                'data' => [
                    'hilos_anterior' => $hilosAnterior,
                    'hilos_nuevo' => $hilos,
                    'registros_produccion_actualizados' => $registrosProduccionActualizados,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar hilos', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
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
            $request->validate([
                'orden_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'id' => 'nullable|integer',
                'no_julio' => 'nullable',
                'hilos' => 'nullable',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->orden_id);

            $statusActual = trim($orden->Status ?? '');
            $registroId = $request->input('id');
            $noJulio = $request->input('no_julio');
            $hilos = $request->input('hilos');
            $esSoloActualizarHilos = $noJulio !== null && $noJulio !== '' && $hilos !== null && $hilos !== '';

            // En Proceso, Programado: permiten todo. Finalizado: solo actualizar Hilos (existente)
            if (!in_array($statusActual, ['En Proceso', 'Programado'], true)) {
                if ($statusActual !== 'Finalizado' || !$esSoloActualizarHilos) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Solo se pueden editar los julios cuando el estado es En Proceso o Programado.',
                    ], 403);
                }
            }

            $axUrdido = (int) ($orden->AX ?? $orden->Ax ?? $orden->getAttribute('ax') ?? 0);
            if ($axUrdido === 0) {
                $axUrdido = (int) (DB::table('UrdProgramaUrdido')->where('Id', $orden->Id)->value('ax') ?? 0);
            }
            $bloqueaUrdido = $axUrdido === 1;

            if ($bloqueaUrdido && !$esSoloActualizarHilos) {
                return response()->json([
                    'success' => false,
                    'error' => 'Urdido ya está en AX. No se pueden editar julios.',
                ], 403);
            }

            if ($esSoloActualizarHilos && $statusActual === 'Finalizado') {
                $registro = null;
                $hilosAnterior = null;
                $registrosProduccionActualizados = 0;
                if ($registroId) {
                    $registro = UrdJuliosOrden::where('Id', (int) $registroId)->where('Folio', $orden->Folio)->first();
                }
                if (!$registro && $noJulio !== null && $noJulio !== '') {
                    $k = trim((string) $noJulio);
                    $registro = UrdJuliosOrden::where('Folio', $orden->Folio)
                        ->where(function ($q) use ($k) {
                            $q->where('Julios', $k)->orWhere('Julios', (int) $k);
                        })->first();
                }

                if (!$registro) {
                    return response()->json([
                        'success' => false,
                        'error' => 'No se encontro el registro de julio para actualizar hilos.',
                    ], 404);
                }

                if ($registro) {
                    $hilosAnterior = $registro->Hilos !== null ? (int) $registro->Hilos : null;
                    $registro->Hilos = (int) $hilos;
                    $registro->save();
                    $registrosProduccionActualizados = $this->sincronizarHilosProduccionPorFolio(
                        $orden,
                        $hilosAnterior,
                        (int) $hilos
                    );
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Hilos actualizados',
                    'data' => [
                        'id' => $registro ? $registro->Id : $registroId,
                        'hilos_anterior' => $hilosAnterior,
                        'hilos_nuevo' => (int) $hilos,
                        'registros_produccion_actualizados' => $registrosProduccionActualizados,
                    ],
                ]);
            }

            $noJulioVacio = $noJulio === null || $noJulio === '';
            $hilosVacio = $hilos === null || $hilos === '';

            if ($noJulioVacio && $hilosVacio) {
                $registrosProduccionEliminadosIds = [];
                if ($registroId) {
                    DB::beginTransaction();
                    try {
                        $registroEliminar = UrdJuliosOrden::where('Id', $registroId)
                            ->where('Folio', $orden->Folio)
                            ->first();

                        if ($registroEliminar) {
                            $hilosEliminar = $registroEliminar->Hilos !== null ? (int) $registroEliminar->Hilos : 0;
                            $cantidadJuliosEliminar = $registroEliminar->Julios !== null ? (int) $registroEliminar->Julios : 0;

                            if ($statusActual !== 'Programado' && $hilosEliminar > 0 && $cantidadJuliosEliminar > 0) {
                                $registrosProduccionEliminadosIds = $this->eliminarRegistrosProduccionPorEliminacionJulio(
                                    $orden,
                                    $hilosEliminar,
                                    $cantidadJuliosEliminar
                                );
                            }

                            $registroEliminar->delete();
                        }

                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Registro de julio eliminado',
                    'data' => [
                        'deleted' => true,
                        'registros_produccion_eliminados' => count($registrosProduccionEliminadosIds),
                        'registros_produccion_eliminados_ids' => $registrosProduccionEliminadosIds,
                    ],
                ]);
            }

            if ($noJulioVacio || $hilosVacio) {
                return response()->json([
                    'success' => false,
                    'error' => 'No. Julio y Hilos son requeridos para guardar',
                ], 422);
            }

            if (!is_numeric($noJulio) || (int) $noJulio <= 0 || !is_numeric($hilos) || (int) $hilos <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No. Julio y Hilos deben ser numeros mayores a 0.',
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

                $esNuevoRegistroJulio = !$registro;
                if (!$registro) {
                    $registro = new UrdJuliosOrden();
                    $registro->Folio = $orden->Folio;
                }

                $hilosAnterior = $registro->exists && $registro->Hilos !== null ? (int) $registro->Hilos : null;
                $juliosAnterior = $registro->exists && $registro->Julios !== null ? (int) $registro->Julios : 0;
                $hilosNuevo = (int) $hilos;
                $registro->Julios = (int) $noJulio;
                $registro->Hilos = $hilosNuevo;
                $registro->save();

                $registrosProduccionCreados = [];
                $registrosProduccionEliminadosIds = [];
                $deltaJulios = 0;
                if ($statusActual === 'En Proceso') {
                    $deltaJulios = ((int) $noJulio) - max(0, $juliosAnterior);

                    if (!$esNuevoRegistroJulio && $deltaJulios < 0) {
                        $hilosParaEliminar = $hilosAnterior ?? $hilosNuevo;
                        if ($hilosParaEliminar !== null && $hilosParaEliminar > 0) {
                            $registrosProduccionEliminadosIds = $this->eliminarRegistrosProduccionPorEliminacionJulio(
                                $orden,
                                (int) $hilosParaEliminar,
                                abs($deltaJulios)
                            );
                        }
                    }
                }

                $registrosProduccionActualizados = 0;
                if ($statusActual !== 'Programado') {
                    $registrosProduccionActualizados = $this->sincronizarHilosProduccionPorFolio(
                        $orden,
                        $hilosAnterior,
                        $hilosNuevo
                    );
                }

                if ($statusActual === 'En Proceso' && $deltaJulios > 0) {
                    $cantidadCrear = $deltaJulios;
                    if ($cantidadCrear > 0) {
                        $registrosProduccionCreados = $this->crearRegistrosProduccionDesdeJulio(
                            $orden,
                            $cantidadCrear,
                            $hilosNuevo
                        );
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Julio actualizado correctamente',
                    'data' => [
                        'id' => $registro->Id,
                        'hilos_anterior' => $hilosAnterior,
                        'hilos_nuevo' => $hilosNuevo,
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
