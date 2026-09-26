<?php

namespace App\Http\Controllers\Engomado\Produccion;

use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Engomado\CatUbicaciones;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Support\Http\Concerns\HandlesApiErrors;
use App\Traits\ProduccionTrait;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModuloProduccionEngomadoController extends Controller
{
    use HandlesApiErrors;
    use ProduccionTrait;

    /**
     * Campos de la orden que la pantalla edita (inputs de merma) => columna de EngProgramaEngomado.
     * Lista blanca de actualizarCampoOrden (hueco AuthZ de 20-03-MAPA-AUTHZ).
     */
    private const CAMPOS_ORDEN = ['merma_con_goma' => 'MermaGoma', 'merma_sin_goma' => 'Merma'];

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

    /**
     * Engomado no aplica tope de Kg. Neto (el límite de 700 kg es solo en producción Urdido).
     */
    protected function maxKgNetoAllowed(): ?float
    {
        return null;
    }

    protected function maxKgBrutoAllowed(): ?float
    {
        return 2000.0;
    }

    protected function getModuleNameForPermissions(): string
    {
        return 'Producción Engomado';
    }

    /**
     * Verifica si el usuario puede editar según permisos del módulo (no área).
     */
    private function usuarioPuedeEditar(): bool
    {
        return function_exists('userCan') && userCan('modificar', $this->getModuleNameForPermissions());
    }

    private function resolveFechaFinalizaFromProduccion(string $folio): string
    {
        $ultimaFechaProduccion = EngProduccionEngomado::query()
            ->where('Folio', $folio)
            ->orderByDesc('Id')
            ->value('Fecha');

        if ($ultimaFechaProduccion instanceof \DateTimeInterface) {
            return $ultimaFechaProduccion->format('Y-m-d');
        }

        if (is_string($ultimaFechaProduccion) && trim($ultimaFechaProduccion) !== '') {
            return substr($ultimaFechaProduccion, 0, 10);
        }

        return now()->toDateString();
    }

    /**
     * Al desmarcar Finalizar en Engomado, resetear Impresion a NULL
     * para que el registro vuelva a ser elegible para impresión parcial.
     */
    protected function onRegistroDesmarcado($registro): void
    {
        try {
            $registro->Impresion = null;
        } catch (\Throwable $e) {
            // Columna puede no existir aún
        }
    }

    // ─── index ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', $this->getModuleNameForPermissions()),
            403,
            'No tiene acceso a este módulo.'
        );

        $hasFinalizarPermission = true;
        $ordenId = $request->query('orden_id');

        if (! $ordenId) {
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
                'tieneRegistrosParciales' => false,
                'canEdit' => $this->usuarioPuedeEditar(),
                'maxKgBruto' => $this->maxKgBrutoAllowed(),
            ]);
        }

        $orden = EngProgramaEngomado::find($ordenId);
        if (! $orden) {
            return redirect()->route('engomado.programar.engomado')->with('error', 'Orden no encontrada');
        }

        // Verificar que la orden de urdido esté finalizada
        $urdido = UrdProgramaUrdido::where('Folio', $orden->Folio)->first();
        if (! $urdido || $urdido->Status !== 'Finalizado') {
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

        // Se extrae a su propio metodo para poder ejercitarlo sin pasar por
        // la pantalla (ver scripts/ensayo-engomado-20-ordenes.php).
        $this->sincronizarRenglonesConNoTelas($orden, $totalRegistros, $solidosFormulacion);

        $this->traitRefrescarFechaEnRegistrosVacios($orden);
        $this->traitAutollenarOficial1EnRegistrosSinHoraInicial($orden);
        $registrosProduccion = EngProduccionEngomado::where('Folio', $orden->Folio)->orderBy('Id')->get();

        // Habilitar botón solo cuando hay registros con Finalizar=1 e Impresion=NULL/0 (listos pero no impresos)
        try {
            $tieneRegistrosParciales = EngProduccionEngomado::where('Folio', $orden->Folio)
                ->where('Finalizar', 1)
                ->whereRaw('(Impresion IS NULL OR Impresion = 0)')
                ->exists();
        } catch (\Throwable $e) {
            $tieneRegistrosParciales = false;
        }

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
            'hiloFibra' => ! empty($orden->Fibra) ? $orden->Fibra : '-',
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
            'tieneRegistrosParciales' => $tieneRegistrosParciales,
            'canEdit' => $this->usuarioPuedeEditar(),
            'maxKgBruto' => $this->maxKgBrutoAllowed(),
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
            return $this->apiErrorResponse($e, 'Error al obtener usuarios de Engomado', 'Error al obtener usuarios');
        }
    }

    public function actualizarCamposProduccion(Request $request): JsonResponse
    {
        $this->ensureUserCanEdit();
        try {
            $request->validate([
                'registro_id' => 'required|integer',
                'campo' => 'required|string|in:Solidos,Canoa1,Canoa2,Canoa3,Humedad,Ubicacion,Roturas',
                'valor' => 'nullable',
            ]);

            $registro = EngProduccionEngomado::find($request->registro_id);

            if (! $registro) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado con ID: '.$request->registro_id], 404);
            }

            $campo = $request->campo;

            if ($campo === 'Ubicacion') {
                $valor = $request->valor !== null && $request->valor !== '' ? (string) $request->valor : null;
            } else {
                if ($request->valor !== null && $request->valor !== '') {
                    if (! is_numeric($request->valor)) {
                        return response()->json(['success' => false, 'error' => 'El valor debe ser numérico para el campo '.$campo], 422);
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

            if (! in_array($campo, $registro->getFillable())) {
                return response()->json(['success' => false, 'error' => 'El campo '.$campo.' no está permitido para actualización'], 422);
            }

            $registro->$campo = $valor;

            if (! $registro->save()) {
                return response()->json(['success' => false, 'error' => 'No se pudo guardar el registro'], 500);
            }

            $registro->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($campo).' actualizado correctamente',
                'data' => ['campo' => $campo, 'valor' => $registro->$campo],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar campo de producción Engomado', 'Error al actualizar campo');
        }
    }

    public function actualizarCampoOrden(Request $request): JsonResponse
    {
        // Validación de entrada fuera del try: 422 con los errores (la ruta sigue en modo auditar).
        $datos = $request->validate([
            'orden_id' => 'required|integer',
            'campo' => ['required', 'string', Rule::in(array_keys(self::CAMPOS_ORDEN))],
            'valor' => 'nullable|numeric|min:0',
        ], [
            'campo.in' => 'Campo no válido',
            'valor.numeric' => 'El valor debe ser numérico',
            'valor.min' => 'La merma no puede ser negativa.',
        ]);

        try {
            $orden = EngProgramaEngomado::find($datos['orden_id']);

            if (! $orden) {
                return response()->json(['success' => false, 'error' => 'Orden no encontrada'], 404);
            }

            $campo = (string) $datos['campo'];
            $campoBD = self::CAMPOS_ORDEN[$campo];
            $valor = isset($datos['valor']) && $datos['valor'] !== '' ? (float) $datos['valor'] : null;

            $orden->$campoBD = $valor;
            $orden->save();
            $orden->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('_', ' ', $campo)).' actualizado correctamente',
                'data' => ['campo' => $campo, 'valor' => $orden->$campoBD],
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Invalid column name')) {
                return $this->apiErrorResponse($e, 'Faltan columnas de merma en EngProgramaEngomado',
                    'Las columnas MermaGoma y Merma no existen en la tabla. Por favor, ejecuta el script SQL para agregarlas.');
            }

            return $this->apiErrorResponse($e, 'Error de base de datos al actualizar campo de la orden de Engomado', 'Error de base de datos al actualizar campo');
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar campo de la orden de Engomado', 'Error al actualizar campo');
        }
    }

    public function verificarFormulaciones(Request $request): JsonResponse
    {
        // Fuera del try: sin folio es 422, no 500.
        $request->validate(['folio' => 'required|string|max:50']);

        try {
            $folio = $request->input('folio');
            $count = EngProduccionFormulacionModel::where('Folio', $folio)->count();

            return response()->json(['success' => true, 'tieneFormulaciones' => $count > 0, 'cantidad' => $count]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al verificar formulaciones de Engomado', 'Error al verificar formulaciones');
        }
    }

    public function finalizar(Request $request): JsonResponse
    {
        $this->ensureUserCanEdit();
        try {
            $request->validate([
                'orden_id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $orden = EngProgramaEngomado::find($request->orden_id);

            if (! $orden) {
                return response()->json(['success' => false, 'error' => 'Orden no encontrada'], 404);
            }

            if (! in_array($orden->Status, ['En Proceso', 'Parcial'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo se puede finalizar una orden en estado "En Proceso" o "Parcial". Estado actual: '.$orden->Status,
                ], 422);
            }

            if ($this->traitHasNegativeKgNetoByFolio($orden->Folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden porque existen registros con Kg Neto negativo.',
                ], 422);
            }

            $registrosInvalidos = EngProduccionEngomado::where('Folio', $orden->Folio)
                ->whereNotNull('HoraInicial')
                ->whereNotNull('HoraFinal')
                ->where(function ($q) {
                    $q->whereNull('NoJulio')
                        ->orWhere('NoJulio', '')
                        ->orWhereNull('KgBruto')
                        ->orWhere('KgBruto', 0);
                })
                ->count();

            if ($registrosInvalidos > 0) {
                return response()->json([
                    'success' => false,
                    'error' => "No se puede finalizar: hay {$registrosInvalidos} registro(s) con No. Julio vacío o Kg Bruto en cero. Revisa los registros antes de finalizar.",
                ], 422);
            }

            $formulacionesExistentes = EngProduccionFormulacionModel::where('Folio', $orden->Folio)->count();
            if ($formulacionesExistentes === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede finalizar la orden. Debe existir al menos una formulación (EngProduccionFormulacion) con el Folio '.$orden->Folio.' antes de finalizar.',
                ], 422);
            }

            $fechaCierre = $this->resolveMonthlyClosureDateContext();

            DB::connection('sqlsrv')->transaction(function () use ($orden, $fechaCierre) {
                // Las filas se pre-crean como esqueleto desde NoTelas. Las que
                // quedan sin capturar son telas planeadas que no se corrieron:
                // se descartan al cerrar, si no la orden deja filas fantasma
                // marcadas como produccion terminada con 0 kg.
                EngProduccionEngomado::where('Folio', $orden->Folio)
                    ->whereNull('HoraInicial')
                    ->where(function ($q) {
                        $q->whereNull('NoJulio')->orWhere('NoJulio', '');
                    })
                    ->where(function ($q) {
                        $q->whereNull('KgBruto')->orWhere('KgBruto', 0);
                    })
                    ->where(function ($q) {
                        $q->whereNull('AX')->orWhere('AX', '!=', 1);
                    })
                    ->delete();

                // No tocar filas ya procesadas en AX.
                EngProduccionEngomado::where('Folio', $orden->Folio)
                    ->where(function ($q) {
                        $q->whereNull('AX')->orWhere('AX', '!=', 1);
                    })
                    ->update(['Finalizar' => 1]);

                if ($fechaCierre['applies']) {
                    $this->updateProduccionFechaByFolio($orden->Folio, $fechaCierre['fecha_efectiva']);
                }

                $orden->Status = 'Finalizado';
                $orden->FechaFinaliza = $fechaCierre['applies']
                    ? $fechaCierre['fecha_efectiva']
                    : $this->resolveFechaFinalizaFromProduccion($orden->Folio);
                $orden->save();

                EngProduccionFormulacionModel::where('Folio', $orden->Folio)->update(['Status' => 'Finalizado']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Orden finalizada correctamente',
                'data' => [
                    'orden_id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'status' => $orden->Status,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al finalizar orden de engomado', 'Error al finalizar la orden');
        }
    }

    /**
     * Alta y baja de renglones para cuadrar con NoTelas.
     */
    protected function sincronizarRenglonesConNoTelas(EngProgramaEngomado $orden, int $totalRegistros, $solidosFormulacion): void
    {
        // Sincronizar registros de producción con NoTelas (lógica idempotente)
        if ($orden->Folio && $totalRegistros > 0) {
            try {
                // Serializa por orden: sin esto, dos peticiones simultaneas leen
                // ambas el mismo conteo y ambas dan de alta el plan completo,
                // dejando la orden al doble exacto. Y esto corre en un GET, que
                // el Service Worker de la PWA puede reintentar.
                DB::connection('sqlsrv')->transaction(function () use ($orden, $totalRegistros, $solidosFormulacion) {
                    EngProgramaEngomado::where('Id', $orden->Id)->lockForUpdate()->first();

                    $registrosExistentes = EngProduccionEngomado::where('Folio', $orden->Folio)->count();
                    $diferencia = $totalRegistros - $registrosExistentes;

                    // Solo proceder si hay diferencia
                    if ($diferencia !== 0) {
                        $user = Auth::user();
                        $claveUsuario = $user ? ($user->numero_empleado ?? null) : null;
                        $nombreUsuario = $user ? ($user->nombre ?? null) : null;
                        $turnoUsuario = $user ? ($user->turno ?? null) : null;
                        if (! $turnoUsuario) {
                            $turnoUsuario = TurnoHelper::getTurnoActual();
                        }
                        $metrosOrden = $orden->MetrajeTelas ?? $orden->Metros ?? 0;

                        if ($diferencia > 0) {
                            // Crear registros faltantes: todas las filas son iguales, se insertan
                            // en bloque (antes un create() por fila). Los atributos pasan por el
                            // modelo para conservar los casts de escritura (Fecha como fecha).
                            $data = [
                                'Folio' => $orden->Folio,
                                'NoJulio' => null,
                                'Fecha' => now()->format('Y-m-d'),
                            ];
                            if ($solidosFormulacion !== null) {
                                $data['Solidos'] = $solidosFormulacion;
                            }
                            if (! empty($claveUsuario)) {
                                $data['CveEmpl1'] = $claveUsuario;
                            }
                            if (! empty($nombreUsuario)) {
                                $data['NomEmpl1'] = $nombreUsuario;
                            }
                            if ($metrosOrden > 0) {
                                $data['Metros1'] = round($metrosOrden, 2);
                            }
                            if (! empty($turnoUsuario)) {
                                $data['Turno1'] = (int) $turnoUsuario;
                            }

                            $fila = (new EngProduccionEngomado)->forceFill($data)->getAttributes();
                            // SQL Server admite 2100 parámetros por sentencia.
                            $porBloque = max(1, intdiv(2100, max(1, count($fila))));
                            foreach (array_chunk(array_fill(0, $diferencia, $fila), $porBloque) as $bloque) {
                                try {
                                    EngProduccionEngomado::insert($bloque);
                                } catch (\Throwable $e) {
                                    Log::error('Error al crear registros de producción Engomado', [
                                        'folio' => $orden->Folio,
                                        'filas' => count($bloque),
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        } elseif ($diferencia < 0) {
                            // Eliminar registros sobrantes
                            // Solo se eliminan filas VACIAS: sin captura iniciada, sin
                            // julio, sin peso y no enviadas a AX. Si no alcanzan, se
                            // deja el sobrante y se registra, en vez de borrar trabajo
                            // real para cuadrar el conteo.
                            $registrosAEliminar = abs($diferencia);
                            $idsAEliminar = EngProduccionEngomado::where('Folio', $orden->Folio)
                                ->whereNull('HoraInicial')
                                ->where(function ($q) {
                                    $q->whereNull('NoJulio')->orWhere('NoJulio', '');
                                })
                                ->where(function ($q) {
                                    $q->whereNull('KgBruto')->orWhere('KgBruto', 0);
                                })
                                ->where(function ($q) {
                                    $q->whereNull('AX')->orWhere('AX', '!=', 1);
                                })
                                ->orderBy('Id', 'desc')  // los mas nuevos primero
                                ->limit($registrosAEliminar)
                                ->pluck('Id')
                                ->toArray();

                            if (count($idsAEliminar) < $registrosAEliminar) {
                                Log::warning('Sobran registros de produccion con captura; no se eliminan', [
                                    'folio' => $orden->Folio,
                                    'sobrantes' => $registrosAEliminar,
                                    'eliminables' => count($idsAEliminar),
                                ]);
                            }

                            if (count($idsAEliminar) < $registrosAEliminar) {
                                // Aun no hay suficientes, tomar los mas antiguos restantes
                                $faltan = $registrosAEliminar - count($idsAEliminar);
                                // Solo filas VACIAS y fuera de AX: si no alcanzan, se
                                // deja el sobrante en vez de borrar trabajo real.
                                $idsRestantes = EngProduccionEngomado::where('Folio', $orden->Folio)
                                    ->whereNotIn('Id', $idsAEliminar)
                                    ->whereNull('HoraInicial')
                                    ->where(function ($q) {
                                        $q->whereNull('NoJulio')->orWhere('NoJulio', '');
                                    })
                                    ->where(function ($q) {
                                        $q->whereNull('KgBruto')->orWhere('KgBruto', 0);
                                    })
                                    ->where(function ($q) {
                                        $q->whereNull('AX')->orWhere('AX', '!=', 1);
                                    })
                                    ->orderBy('Id', 'desc')
                                    ->limit($faltan)
                                    ->pluck('Id')
                                    ->toArray();
                                $idsAEliminar = array_merge($idsAEliminar, $idsRestantes);
                            }

                            if (! empty($idsAEliminar)) {
                                EngProduccionEngomado::whereIn('Id', $idsAEliminar)->delete();
                                Log::info('Eliminados registros de producción sobrantes', [
                                    'folio' => $orden->Folio,
                                    'ids_eliminados' => $idsAEliminar,
                                    'cantidad' => count($idsAEliminar),
                                ]);
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Error general al sincronizar registros en EngProduccionEngomado', [
                    'folio' => $orden->Folio,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
