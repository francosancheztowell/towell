<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaDevolucionesModel;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AtaDevolucionesController extends Controller
{
    /**
     * Ubicación fija del catálogo WMSLocation (TI-PRO) usada para el
     * combo de Ubicación en el panel de Devolución.
     */
    private const UBICACION_INVENT_LOCATION_ID = 'A-JUL/TELA';

    private const UBICACION_DATA_AREA_ID = 'PRO';

    /** Ubicación de las devoluciones de una barra Karl Mayer. No sale del catálogo WMS. */
    public const UBICACION_KM = 'KM1';

    /**
     * Catálogo de ubicaciones (WMSLocationId) para el combo de Ubicación del
     * panel de Devolución. Se consulta en vivo contra TI-PRO (conexión
     * sqlsrv_ti) filtrando InventLocationId y dataAreaId fijos.
     */
    public function ubicaciones(): JsonResponse
    {
        try {
            $ubicaciones = DB::connection('sqlsrv_ti')
                ->table('WMSLocation')
                ->where('InventLocationId', self::UBICACION_INVENT_LOCATION_ID)
                ->where('dataAreaId', self::UBICACION_DATA_AREA_ID)
                ->distinct()
                ->orderBy('wMSLocationId')
                ->pluck('wMSLocationId')
                ->filter()
                ->values();

            return response()->json(['ok' => true, 'ubicaciones' => $ubicaciones]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar WMSLocation (TI-PRO) para ubicaciones de devolución', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo consultar el catálogo de ubicaciones en TI-PRO.',
            ], 500);
        }
    }

    /**
     * Estatus de AtaMontadoTelas que se consideran "atado finalizado" para
     * efectos de buscar julios previos del mismo Telar y Tipo. Un atado pasa
     * por Terminado -> Calificado -> Autorizado; los históricos casi siempre
     * ya están en Calificado o Autorizado, por lo que hay que incluir los tres.
     */
    private const JULIO_ESTATUS_FINALIZADOS = ['Terminado', 'Calificado', 'Autorizado'];

    /**
     * Julios atados (AtaMontadoTelas) de un Telar, filtrados por el mismo Tipo
     * del atado actual y en estatus finalizado, ordenados del más reciente al
     * más antiguo. Se sugiere el más reciente, excluyendo explícitamente el
     * atado que se está trabajando actualmente (exclude_id) para no auto-
     * sugerirse a sí mismo cuando ya alcanzó un estatus finalizado.
     */
    public function julios(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telar' => ['required', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        try {
            $fechaMinima = Carbon::now('America/Mexico_City')->subDays(30)->startOfDay();
            $query = AtaMontadoTelasModel::where('NoTelarId', $data['telar'])
                ->whereIn('Estatus', self::JULIO_ESTATUS_FINALIZADOS)
                ->where('Fecha', '>=', $fechaMinima->toDateString());

            if (! empty($data['tipo'])) {
                $query->where('Tipo', $data['tipo']);
            }

            if (! empty($data['exclude_id'])) {
                $query->where('Id', '!=', $data['exclude_id']);
            }

            $registros = $query->orderByDesc('Fecha')
                ->orderByDesc('Turno')
                ->orderByDesc('Id')
                ->get();

            // Cuenta y Calibre viven en TejHistorialInventarioTelares, donde se
            // insertan (Status = 'Completado') cuando el atado pasa a Autorizado.
            // Se consultan todos los julios en bloque para poder completar el
            // formulario al elegir cualquiera de las sugerencias.
            $historiales = collect();
            $juliosRegistrados = $registros->pluck('NoJulio')->filter()->unique()->values();
            if ($juliosRegistrados->isNotEmpty()) {
                $historiales = DB::connection('sqlsrv')
                    ->table('TejHistorialInventarioTelares')
                    ->where('NoTelarId', $data['telar'])
                    ->whereIn('NoJulio', $juliosRegistrados)
                    ->where('Status', 'Completado')
                    ->orderByDesc('FechaAtado')
                    ->get(['NoJulio', 'NoProduccion', 'Cuenta', 'Calibre', 'FechaAtado']);
            }

            $historialPorJulioYLote = $historiales
                ->groupBy(fn ($historial) => $this->claveJulioYLote($historial->NoJulio ?? null, $historial->NoProduccion ?? null))
                ->map(fn ($historialesDelJulio) => $historialesDelJulio->first());

            $datosPorJulio = $registros
                ->filter(fn ($registro) => filled($registro->NoJulio))
                ->map(function ($registro) use ($historialPorJulioYLote) {
                    $historial = $historialPorJulioYLote->get(
                        $this->claveJulioYLote($registro->NoJulio, $registro->NoProduccion)
                    );

                    return [
                        'julio' => trim((string) $registro->NoJulio),
                        'cuenta' => $historial->Cuenta ?? null,
                        'calibre' => $historial->Calibre ?? null,
                        'hilo' => $registro->ConfigId ?? null,
                        'lote' => $this->formatearLoteDevolucion($registro->NoProduccion),
                        'tipo' => $registro->Tipo ?? null,
                    ];
                })
                // La consulta ya viene del más reciente al más antiguo.
                ->unique('julio')
                ->values();

            $registroSugerido = $datosPorJulio->first();
            $julios = $datosPorJulio->pluck('julio')->values();

            return response()->json([
                'ok' => true,
                'julios' => $julios,
                'registros' => $datosPorJulio,
                'sugerido' => $registroSugerido['julio'] ?? null,
                'cuenta' => $registroSugerido['cuenta'] ?? null,
                'calibre' => $registroSugerido['calibre'] ?? null,
                'hilo' => $registroSugerido['hilo'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar julios atados por telar para devolución', [
                'telar' => $data['telar'],
                'tipo' => $data['tipo'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo consultar los julios atados de ese telar.',
            ], 500);
        }
    }

    /**
     * Devuelve los límites que aún pueden capturarse para la devolución de un
     * julio. La reserva es la fuente de entrada; las devoluciones anteriores
     * del mismo origen se descuentan para impedir excedentes acumulados.
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ref_id' => ['nullable', 'integer'],
            'telar' => ['required', 'string', 'max:10'],
            'no_julio' => ['required', 'string', 'max:20'],
            'tipo' => ['required', 'string', 'max:20'],
        ]);

        $reserva = $this->buscarReservaOrigen($data);
        if (! $reserva) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la entrada de inventario para el Telar, Julio y Tipo seleccionados.',
            ], 422);
        }

        $devolucionActualId = null;
        if (! empty($data['ref_id'])) {
            $devolucionActualId = AtaDevolucionesModel::where('RefId', $data['ref_id'])
                ->orderByDesc('Id')
                ->value('Id');
        }

        return response()->json([
            'ok' => true,
            'disponibilidad' => $this->calcularDisponibilidad($reserva, $devolucionActualId),
        ]);
    }

    /**
     * Registra o actualiza una devolución asociada a un proceso de atado (AtaMontadoTelas).
     *
     * El registro se vincula al montado mediante RefId. NoProduccion guarda el
     * lote de devolución con prefijo "DEV" y LoteOriginal guarda el mismo lote
     * sin ese prefijo.
     */
    public function store(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'ref_id' => ['required', 'integer'],
            'telar' => ['nullable', 'string', 'max:10'],
            'no_julio' => ['nullable', 'string', 'max:20'],
            'no_produccion' => ['nullable', 'string', 'max:20'],
            'kilos' => ['nullable', 'numeric', 'min:0'],
            'metros' => ['nullable', 'numeric', 'min:0'],
            'ubicacion' => ['nullable', 'string', 'max:10'],
            'fecha_devol' => ['nullable', 'date'],
            'cuenta' => ['nullable', 'string', 'max:10'],
            'calibre' => ['nullable', 'string', 'max:10'],
            'hilo' => ['nullable', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:5'],
            'obs' => ['nullable', 'string', 'max:255'],
            'config_id' => ['nullable', 'string', 'max:10'],
            'invent_size_id' => ['nullable', 'string', 'max:10'],
            'invent_color_id' => ['nullable', 'string', 'max:10'],
        ]);

        $montado = AtaMontadoTelasModel::find($data['ref_id']);
        if (! $montado) {
            return response()->json(['ok' => false, 'message' => 'No se encontró el atado asociado a la devolución'], 404);
        }

        if ($this->esKarlMayer($montado->Tipo, $montado->NoTelarId)) {
            return $this->guardarDevolucionesKm($request, $montado);
        }

        $kilos = $data['kilos'] ?? 0;
        $metros = $data['metros'] ?? 0;

        // El lote debe corresponder al Julio elegido en el panel (atado previo),
        // no al atado que se está calificando (ref_id).
        $noJulio = isset($data['no_julio']) && trim((string) $data['no_julio']) !== ''
            ? trim((string) $data['no_julio'])
            : null;
        $telarDevolucion = $data['telar'] ?? $montado->NoTelarId;
        $tipoDevolucion = $data['tipo'] ?? $montado->Tipo;
        $ordenDesdeJulio = $this->resolverOrdenDeJulioSeleccionado(
            $noJulio,
            $telarDevolucion,
            $tipoDevolucion
        );
        $ordenBase = trim((string) ($ordenDesdeJulio ?? $data['no_produccion'] ?? ''));
        $loteOriginal = $this->extraerLoteOriginal($ordenBase);
        $loteDev = $this->formatearLoteDevolucion($ordenBase);
        if ($loteDev !== null && strlen($loteDev) > 20) {
            return response()->json([
                'ok' => false,
                'message' => 'El lote de devolución excede los 20 caracteres permitidos.',
            ], 422);
        }

        $cveOperador = Auth::user()->numero_empleado ?? null;

        try {
            [$devolucion, $disponibilidad] = DB::connection('sqlsrv')->transaction(function () use ($data, $montado, $noJulio, $loteDev, $loteOriginal, $kilos, $metros, $cveOperador) {
                $devolucion = AtaDevolucionesModel::where('RefId', $montado->Id)
                    ->orderByDesc('Id')
                    ->lockForUpdate()
                    ->first();

                if ($this->estaBloqueadaPorAx($devolucion)) {
                    throw ValidationException::withMessages([
                        'devolucion' => ['Esta devolución ya fue procesada en AX y no se puede modificar.'],
                    ]);
                }

                /*
                 * VALIDACIÓN TEMPORALMENTE DESHABILITADA A PETICIÓN DEL USUARIO.
                 *
                 * $reserva = $this->buscarReservaOrigen($data, true);
                 * if (! $reserva) {
                 *     throw ValidationException::withMessages([
                 *         'no_julio' => ['No se encontró la entrada de inventario para el Telar, Julio y Tipo seleccionados.'],
                 *     ]);
                 * }
                 *
                 * $disponibilidad = $this->calcularDisponibilidad($reserva, $devolucion?->Id);
                 * if ((float) $kilos > $disponibilidad['kilos_disponibles'] + 0.0001) {
                 *     throw ValidationException::withMessages([
                 *         'kilos' => [sprintf('Los kilos a devolver exceden el disponible (%.4f kg).', $disponibilidad['kilos_disponibles'])],
                 *     ]);
                 * }
                 *
                 * if ((float) $metros > $disponibilidad['metros_disponibles'] + 0.0001) {
                 *     throw ValidationException::withMessages([
                 *         'metros' => [sprintf('Los metros a devolver exceden el disponible (%.4f m).', $disponibilidad['metros_disponibles'])],
                 *     ]);
                 * }
                 */
                $disponibilidad = null;

                $payload = [
                    'RefId' => $montado->Id,
                    // Se conserva el vínculo ya existente mientras la validación está pausada.
                    'InvTelasReservadaId' => $devolucion?->InvTelasReservadaId,
                    'NoTelarId' => $data['telar'] ?? $montado->NoTelarId,
                    'NoJulio' => $noJulio,
                    'NoProduccion' => $loteDev,
                    'LoteOriginal' => $loteOriginal,
                    'Kilos' => $kilos,
                    'Metros' => $metros,
                    'Ubicacion' => $data['ubicacion'] ?? null,
                    'FechaDevol' => $data['fecha_devol'] ?? Carbon::now('America/Mexico_City')->toDateString(),
                    'Cuenta' => $data['cuenta'] ?? null,
                    'Calibre' => $data['calibre'] ?? null,
                    'Hilo' => $data['hilo'] ?? null,
                    'Tipo' => $data['tipo'] ?? $montado->Tipo,
                    'Obs' => $data['obs'] ?? null,
                    'ConfigId' => $data['config_id'] ?? $montado->ConfigId,
                    'InventSizeId' => $data['invent_size_id'] ?? $montado->InventSizeId,
                    'InventColorId' => $data['invent_color_id'] ?? $montado->InventColorId,
                    // El Estatus queda ligado al del atado padre (AtaMontadoTelas) desde su creación.
                    'Estatus' => $montado->Estatus ?: 'Activo',
                    // Clave del operador autenticado que registra/actualiza la devolución.
                    'CveOperador' => $cveOperador,
                ];

                if ($devolucion) {
                    $devolucion->fill($payload);
                    $devolucion->save();
                } else {
                    $devolucion = AtaDevolucionesModel::create([
                        ...$payload,
                        'AX' => 0,
                    ]);
                }

                return [$devolucion, $disponibilidad];
            });
        } catch (ValidationException $e) {
            $errores = $e->errors();
            $bloqueadoPorAx = array_key_exists('devolucion', $errores);

            return response()->json([
                'ok' => false,
                'message' => collect($errores)->flatten()->first() ?? 'No se pudo validar la devolución.',
                'bloqueado_ax' => $bloqueadoPorAx,
            ], $bloqueadoPorAx ? 423 : 422);
        } catch (\Throwable $e) {
            Log::error('Error al registrar devolución de atadores', [
                'ref_id' => $montado->Id,
                'no_julio' => $montado->NoJulio,
                'no_orden' => $montado->NoProduccion,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo registrar la devolución: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Devolución actualizada correctamente',
            'id' => $devolucion->Id,
            'disponibilidad' => $disponibilidad,
        ]);
    }

    /**
     * Elimina la devolución de un atado cuando todavía no ha sido enviada a AX.
     */
    public function destroy(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'ref_id' => ['required', 'integer'],
        ]);

        try {
            DB::connection('sqlsrv')->transaction(function () use ($data) {
                $devoluciones = AtaDevolucionesModel::where('RefId', $data['ref_id'])
                    ->lockForUpdate()
                    ->get();

                // Sin filas ya es "sin devolución" (KM sin julios capturados): no es error.
                if ($devoluciones->contains(fn (AtaDevolucionesModel $fila) => $this->estaBloqueadaPorAx($fila))) {
                    throw ValidationException::withMessages([
                        'devolucion' => ['Esta devolución ya fue procesada en AX y no se puede eliminar.'],
                    ]);
                }

                $devoluciones->each->delete();
            });
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'No se pudo eliminar la devolución.',
                'bloqueado_ax' => true,
            ], 423);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar devolución de atadores', [
                'ref_id' => $data['ref_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo eliminar la devolución.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Devolución eliminada correctamente.',
        ]);
    }

    /**
     * Localiza la entrada original. El NoJulio de AtaMontadoTelas puede ser
     * distinto del InventSerialId del inventario (por ejemplo, un julio de
     * proceso contra el serial físico). Por ello se intenta primero el serial
     * exacto y, si no coincide, la reserva activa del mismo Telar y Tipo.
     */
    private function buscarReservaOrigen(array $data, bool $bloquear = false): ?InvTelasReservadas
    {
        $telar = trim((string) ($data['telar'] ?? ''));
        $julio = trim((string) ($data['no_julio'] ?? ''));
        $tipo = mb_strtolower(trim((string) ($data['tipo'] ?? '')), 'UTF-8');

        $query = InvTelasReservadas::query()
            ->where('NoTelarId', $telar)
            ->whereRaw('LOWER(LTRIM(RTRIM(Tipo))) = ?', [$tipo]);

        if ($bloquear) {
            $query->lockForUpdate();
        }

        // En instalaciones donde NoJulio e InventSerialId sí son iguales,
        // mantener esa coincidencia como la opción más precisa.
        $porSerial = (clone $query)
            ->where('InventSerialId', $julio)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->first()
            ?? (clone $query)
                ->where('InventSerialId', $julio)
                ->orderByDesc('Id')
                ->first();

        if ($porSerial) {
            return $porSerial;
        }

        // En el flujo de atadores, el Julio mostrado puede ser el de proceso;
        // la entrada física se identifica por la reserva activa del Telar/Tipo.
        return (clone $query)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->first()
            ?? $query->orderByDesc('Id')->first();
    }

    /**
     * Calcula lo que queda disponible. Incluye devoluciones antiguas sin FK
     * para que los registros existentes con InvTelasReservadaId = NULL también
     * cuenten contra el límite.
     */
    private function calcularDisponibilidad(InvTelasReservadas $reserva, ?int $excluirDevolucionId = null): array
    {
        $tipo = mb_strtolower(trim((string) $reserva->Tipo), 'UTF-8');

        $devoluciones = AtaDevolucionesModel::query()
            ->where(function ($query) use ($reserva, $tipo) {
                $query->where('InvTelasReservadaId', $reserva->Id)
                    ->orWhere(function ($legacy) use ($reserva, $tipo) {
                        $legacy->whereNull('InvTelasReservadaId')
                            ->where('NoTelarId', $reserva->NoTelarId)
                            ->where('NoJulio', $reserva->InventSerialId)
                            ->whereRaw('LOWER(LTRIM(RTRIM(Tipo))) = ?', [$tipo]);
                    });
            });

        if ($excluirDevolucionId) {
            $devoluciones->where('Id', '!=', $excluirDevolucionId);
        }

        $kilosDevueltos = (float) (clone $devoluciones)->sum('Kilos');
        $metrosDevueltos = (float) $devoluciones->sum('Metros');
        $kilosIngresados = (float) $reserva->InventQty;
        $metrosIngresados = (float) $reserva->Metros;

        return [
            'inv_telas_reservada_id' => (int) $reserva->Id,
            'invent_serial_id' => trim((string) $reserva->InventSerialId),
            'kilos_ingresados' => $kilosIngresados,
            'metros_ingresados' => $metrosIngresados,
            'kilos_ya_devueltos' => $kilosDevueltos,
            'metros_ya_devueltos' => $metrosDevueltos,
            'kilos_disponibles' => max(0, $kilosIngresados - $kilosDevueltos),
            'metros_disponibles' => max(0, $metrosIngresados - $metrosDevueltos),
        ];
    }

    private function extraerLoteOriginal(?string $lote): ?string
    {
        $loteOriginal = trim((string) (preg_replace('/^DEV/i', '', trim((string) $lote)) ?? ''));

        return $loteOriginal !== '' ? $loteOriginal : null;
    }

    /**
     * NoProduccion del atado finalizado que corresponde al Julio (y Telar/Tipo)
     * elegido en el panel de devolución. Es la fuente correcta del Lote DEV*.
     */
    private function resolverOrdenDeJulioSeleccionado(mixed $noJulio, mixed $telar, mixed $tipo): ?string
    {
        $julio = trim((string) $noJulio);
        $telarId = trim((string) $telar);
        if ($julio === '' || $telarId === '') {
            return null;
        }

        $query = AtaMontadoTelasModel::where('NoJulio', $julio)
            ->where('NoTelarId', $telarId)
            ->whereIn('Estatus', self::JULIO_ESTATUS_FINALIZADOS);

        $tipoNorm = trim((string) $tipo);
        if ($tipoNorm !== '') {
            $query->where('Tipo', $tipoNorm);
        }

        $orden = $query->orderByDesc('Fecha')
            ->orderByDesc('Turno')
            ->orderByDesc('Id')
            ->value('NoProduccion');

        $orden = trim((string) $orden);

        return $orden !== '' ? $orden : null;
    }

    private function formatearLoteDevolucion(?string $lote): ?string
    {
        $loteOriginal = $this->extraerLoteOriginal($lote);

        return $loteOriginal !== null ? 'DEV'.$loteOriginal : null;
    }

    private function claveJulioYLote(?string $julio, ?string $lote): string
    {
        return mb_strtoupper(trim((string) $julio), 'UTF-8').'|'.mb_strtoupper(trim((string) $lote), 'UTF-8');
    }

    private function estaBloqueadaPorAx(?AtaDevolucionesModel $devolucion): bool
    {
        return (int) ($devolucion?->AX ?? 0) === 1;
    }

    /**
     * Los cuatro julios de la barra, con cuenta, calibre e hilo del inventario
     * y los metros o kilos ya guardados en cada devolución.
     *
     * @param  Collection<int, AtaDevolucionesModel>  $guardadas
     * @return array<int, array<string, mixed>>
     */
    public function filasParaCalificar(?AtaMontadoTelasModel $anterior, Collection $guardadas): array
    {
        $porJulio = $guardadas->keyBy(fn (AtaDevolucionesModel $fila) => trim((string) $fila->NoJulio));

        return collect($this->juliosDeBarra($anterior))
            ->map(function (array $fila) use ($porJulio) {
                $guardada = $porJulio->get($fila['julio']);

                return [
                    'julio' => $fila['julio'],
                    'orden' => $fila['orden'],
                    'folio_dev' => $guardada->NoProduccion ?? $this->formatearLoteDevolucion($fila['orden']),
                    'ubicacion' => self::UBICACION_KM,
                    'cuenta' => $this->textoDevolucion($guardada->Cuenta ?? $fila['cuenta'], 10),
                    'calibre' => $this->textoDevolucion($guardada->Calibre ?? $fila['calibre'], 10),
                    'hilo' => $this->textoDevolucion($guardada->Hilo ?? $fila['hilo'], 20),
                    'hilo_completo' => trim((string) ($guardada->Hilo ?? $fila['hilo'])),
                    'metros' => $guardada->Metros ?? '',
                    'kilos' => $guardada->Kilos ?? '',
                    'obs' => $guardada->Obs ?? '',
                ];
            })
            ->all();
    }

    /**
     * Atados anteriores de la barra: mismo telar y tipo, ya finalizados, de los últimos
     * 30 días, del más reciente al más antiguo (misma regla que el select de Jacquard/Smit).
     *
     * @return Collection<int, AtaMontadoTelasModel>
     */
    public function atadosAnterioresKm(AtaMontadoTelasModel $montado): Collection
    {
        $fechaMinima = Carbon::now('America/Mexico_City')->subDays(30)->startOfDay();

        return AtaMontadoTelasModel::query()
            ->where('NoTelarId', $montado->NoTelarId)
            ->where('Tipo', $montado->Tipo)
            ->whereIn('Estatus', self::JULIO_ESTATUS_FINALIZADOS)
            ->where('Fecha', '>=', $fechaMinima->toDateString())
            ->where('Id', '!=', $montado->Id)
            ->orderByDesc('Fecha')
            ->orderByDesc('Turno')
            ->orderByDesc('Id')
            ->get();
    }

    /**
     * Atado anterior del que salen los julios a devolver: el elegido en el select, el que ya
     * tiene devoluciones guardadas (por su orden) o, si no, el más reciente.
     *
     * @param  Collection<int, AtaDevolucionesModel>  $guardadas
     * @param  Collection<int, AtaMontadoTelasModel>|null  $candidatos
     */
    public function atadoAnteriorKm(
        AtaMontadoTelasModel $montado,
        ?int $anteriorId,
        Collection $guardadas,
        ?Collection $candidatos = null,
    ): ?AtaMontadoTelasModel {
        $candidatos ??= $this->atadosAnterioresKm($montado);

        if ($anteriorId) {
            return $candidatos->first(fn (AtaMontadoTelasModel $atado) => (int) $atado->Id === $anteriorId);
        }

        $loteGuardado = trim((string) ($guardadas->first()?->LoteOriginal ?? ''));
        $conDevolucion = $loteGuardado === ''
            ? null
            : $candidatos->first(fn (AtaMontadoTelasModel $atado) => trim((string) $atado->NoProduccion) === $loteGuardado);

        return $conDevolucion ?? $candidatos->first();
    }

    /**
     * Julios a devolver: siempre los del atado anterior (los que se quitan del telar),
     * igual que Jacquard y Smit. Primero del inventario; si ya se autorizó, del historial.
     *
     * @return array<int, array{julio: string, orden: string, cuenta: string, calibre: string, hilo: string}>
     */
    private function juliosDeBarra(?AtaMontadoTelasModel $anterior): array
    {
        if ($anterior === null) {
            return [];
        }

        $enInventario = $this->juliosDelInventarioAnterior($anterior);
        if ($enInventario !== []) {
            return $enInventario;
        }

        $julio = trim((string) $anterior->NoJulio);
        if ($julio === '') {
            return [];
        }

        // Al autorizar se borra el inventario, pero las reservas de la barra siguen ligadas
        // a esa fila (TejInventarioTelaresId): de ahí salen los 4 julios, también en atados
        // autorizados antes de que el historial guardara uno por julio.
        $deReservas = $this->juliosDeReservas($anterior);
        if ($deReservas !== []) {
            return $deReservas;
        }

        $delHistorial = $this->juliosDelHistorial($anterior);
        if ($delHistorial !== []) {
            return $delHistorial;
        }

        [$cuenta, $calibre, $hilo] = $this->cuentaDelHistorial($anterior);

        return [[
            'julio' => $julio,
            'orden' => trim((string) $anterior->NoProduccion),
            'cuenta' => $cuenta,
            'calibre' => $calibre,
            'hilo' => $hilo !== '' ? $hilo : trim((string) ($anterior->ConfigId ?? '')),
        ]];
    }

    /**
     * Julios de la barra del atado anterior según sus reservas (InvTelasReservadas):
     * la reserva del julio del atado apunta a la fila de inventario, y las demás reservas
     * de esa misma fila son los otros julios de la barra.
     *
     * @return array<int, array{julio: string, orden: string, cuenta: string, calibre: string, hilo: string}>
     */
    private function juliosDeReservas(AtaMontadoTelasModel $anterior): array
    {
        try {
            $inventarioId = InvTelasReservadas::query()
                ->where('NoTelarId', (string) $anterior->NoTelarId)
                ->where('InventSerialId', trim((string) $anterior->NoJulio))
                ->where('InventBatchId', trim((string) $anterior->NoProduccion))
                ->whereNotNull('TejInventarioTelaresId')
                ->orderByDesc('Id')
                ->value('TejInventarioTelaresId');
            if (! $inventarioId) {
                return [];
            }

            $reservas = InvTelasReservadas::query()
                ->where('TejInventarioTelaresId', $inventarioId)
                ->where('NoTelarId', (string) $anterior->NoTelarId)
                ->orderBy('Id')
                ->get(['InventSerialId', 'InventBatchId']);
        } catch (\Throwable) {
            return [];
        }

        [$cuenta, $calibre, $hilo] = $this->cuentaDelHistorial($anterior);

        return $reservas
            ->unique(fn ($reserva) => trim((string) $reserva->InventSerialId))
            ->map(fn ($reserva) => [
                'julio' => trim((string) $reserva->InventSerialId),
                'orden' => trim((string) $reserva->InventBatchId),
                'cuenta' => $cuenta,
                'calibre' => $calibre,
                'hilo' => $hilo !== '' ? $hilo : trim((string) ($anterior->ConfigId ?? '')),
            ])
            ->filter(fn (array $fila) => $fila['julio'] !== '')
            ->values()
            ->all();
    }

    /**
     * Julios de un atado KM ya autorizado, desde TejHistorialInventarioTelares.
     *
     * @return array<int, array{julio: string, orden: string, cuenta: string, calibre: string, hilo: string}>
     */
    private function juliosDelHistorial(AtaMontadoTelasModel $anterior): array
    {
        try {
            $historial = DB::connection('sqlsrv')->table('TejHistorialInventarioTelares');
            $ancla = (clone $historial)
                ->where('NoTelarId', $anterior->NoTelarId)
                ->where('NoJulio', $anterior->NoJulio)
                ->where('Status', 'Completado')
                ->orderByDesc('FechaAtado')
                ->first(['FechaAtado']);
            if ($ancla === null) {
                return [];
            }

            $filas = (clone $historial)
                ->where('NoTelarId', $anterior->NoTelarId)
                ->where('Tipo', $anterior->Tipo)
                ->where('Status', 'Completado')
                // FechaAtado es DATE: la orden separa dos atados de la barra en el mismo día.
                ->where('FechaAtado', $ancla->FechaAtado)
                ->where('NoProduccion', $anterior->NoProduccion)
                ->get(['NoJulio', 'NoProduccion', 'Cuenta', 'Calibre', 'Fibra']);
        } catch (\Throwable) {
            return [];
        }

        return $filas
            ->unique(fn ($fila) => trim((string) $fila->NoJulio))
            ->map(fn ($fila) => [
                'julio' => trim((string) $fila->NoJulio),
                'orden' => trim((string) $fila->NoProduccion),
                'cuenta' => trim((string) ($fila->Cuenta ?? '')),
                'calibre' => trim((string) ($fila->Calibre ?? '')),
                'hilo' => trim((string) ($fila->Fibra ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{julio: string, orden: string, cuenta: string, calibre: string, hilo: string}>
     */
    private function juliosDelInventarioAnterior(AtaMontadoTelasModel $anterior): array
    {
        $julio = trim((string) $anterior->NoJulio);
        if ($julio === '') {
            return [];
        }

        $inventario = TejInventarioTelares::query()
            ->where('no_telar', (string) $anterior->NoTelarId)
            ->where('no_julio', $julio)
            ->orderByDesc('id')
            ->first();

        if ($inventario === null) {
            return [];
        }

        return $this->filasDeColumnasJulio($inventario, trim((string) $anterior->NoProduccion));
    }

    /**
     * @return array<int, array{julio: string, orden: string, cuenta: string, calibre: string, hilo: string}>
     */
    private function filasDeColumnasJulio(object $inventario, string $ordenPorDefecto): array
    {
        $filas = [];
        foreach ([
            ['no_julio', 'no_orden'],
            ['no_julio2', 'no_orden2'],
            ['no_julio3', 'no_orden3'],
            ['no_julio4', 'no_orden4'],
        ] as [$columnaJulio, $columnaOrden]) {
            $numero = trim((string) ($inventario->{$columnaJulio} ?? ''));
            if ($numero === '') {
                continue;
            }
            $filas[] = [
                'julio' => $numero,
                'orden' => trim((string) ($inventario->{$columnaOrden} ?? $ordenPorDefecto)),
                'cuenta' => trim((string) ($inventario->cuenta ?? '')),
                'calibre' => trim((string) ($inventario->calibre ?? '')),
                'hilo' => trim((string) ($inventario->hilo ?? '')),
            ];
        }

        return $filas;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function cuentaDelHistorial(AtaMontadoTelasModel $anterior): array
    {
        try {
            $historial = DB::connection('sqlsrv')
                ->table('TejHistorialInventarioTelares')
                ->where('NoTelarId', $anterior->NoTelarId)
                ->where('NoJulio', $anterior->NoJulio)
                ->where('Status', 'Completado')
                ->orderByDesc('FechaAtado')
                ->first();
        } catch (\Throwable) {
            return ['', '', ''];
        }

        if ($historial === null) {
            return ['', '', ''];
        }

        return [
            trim((string) ($historial->Cuenta ?? '')),
            trim((string) ($historial->Calibre ?? '')),
            trim((string) ($historial->Fibra ?? '')),
        ];
    }

    private function guardarDevolucionesKm(Request $request, AtaMontadoTelasModel $montado): JsonResponse
    {
        $data = $request->validate([
            'fecha_devol' => ['nullable', 'date'],
            'anterior_id' => ['nullable', 'integer'],
            'filas' => ['required', 'array', 'min:1', 'max:4'],
            'filas.*.no_julio' => ['required', 'string', 'max:20'],
            'filas.*.no_produccion' => ['nullable', 'string', 'max:20'],
            'filas.*.kilos' => ['nullable', 'numeric', 'min:0'],
            'filas.*.metros' => ['nullable', 'numeric', 'min:0'],
            'filas.*.cuenta' => ['nullable', 'string', 'max:10'],
            'filas.*.calibre' => ['nullable', 'string', 'max:10'],
            'filas.*.hilo' => ['nullable', 'string', 'max:20'],
            'filas.*.obs' => ['nullable', 'string', 'max:255'],
        ]);

        $anterior = $this->atadoAnteriorKm(
            $montado,
            isset($data['anterior_id']) ? (int) $data['anterior_id'] : null,
            AtaDevolucionesModel::where('RefId', $montado->Id)->get(),
        );
        $permitidos = collect($this->juliosDeBarra($anterior))
            ->pluck('julio')
            ->map(fn ($julio) => (string) $julio)
            ->all();

        foreach ($data['filas'] as $fila) {
            if (! in_array(trim((string) $fila['no_julio']), $permitidos, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El julio '.$fila['no_julio'].' no pertenece a esta barra.',
                ], 422);
            }
        }

        $cveOperador = Auth::user()->numero_empleado ?? null;
        $fecha = $data['fecha_devol'] ?? Carbon::now('America/Mexico_City')->toDateString();

        try {
            $guardadas = DB::connection('sqlsrv')->transaction(function () use ($data, $montado, $cveOperador, $fecha) {
                $existentes = AtaDevolucionesModel::where('RefId', $montado->Id)
                    ->lockForUpdate()
                    ->get();

                if ($existentes->contains(fn (AtaDevolucionesModel $fila) => $this->estaBloqueadaPorAx($fila))) {
                    throw ValidationException::withMessages([
                        'devolucion' => ['Esta devolución ya fue procesada en AX y no se puede modificar.'],
                    ]);
                }

                // Si se cambió de atado anterior, las devoluciones del otro ya no aplican.
                $juliosEnviados = collect($data['filas'])->map(fn ($fila) => trim((string) $fila['no_julio']))->all();
                $existentes
                    ->reject(fn (AtaDevolucionesModel $fila) => in_array(trim((string) $fila->NoJulio), $juliosEnviados, true))
                    ->each->delete();

                $porJulio = $existentes->keyBy(fn (AtaDevolucionesModel $fila) => trim((string) $fila->NoJulio));
                $resultado = collect();

                foreach ($data['filas'] as $fila) {
                    $julio = trim((string) $fila['no_julio']);
                    $ordenBase = trim((string) ($fila['no_produccion'] ?? ''));
                    $loteOriginal = $this->extraerLoteOriginal($ordenBase);
                    $loteDev = $this->formatearLoteDevolucion($ordenBase);
                    $actual = $porJulio->get($julio);

                    // Julio sin metros ni kilos no se devolvió: no se guarda ni sale a AX.
                    if ((float) ($fila['metros'] ?? 0) <= 0 && (float) ($fila['kilos'] ?? 0) <= 0) {
                        $actual?->delete();

                        continue;
                    }

                    $payload = [
                        'RefId' => $montado->Id,
                        'InvTelasReservadaId' => $actual?->InvTelasReservadaId,
                        'NoTelarId' => $montado->NoTelarId,
                        'NoJulio' => $julio,
                        'NoProduccion' => $loteDev,
                        'LoteOriginal' => $loteOriginal,
                        'Kilos' => $fila['kilos'] ?? 0,
                        'Metros' => $fila['metros'] ?? 0,
                        'Ubicacion' => self::UBICACION_KM,
                        'FechaDevol' => $fecha,
                        'Cuenta' => $fila['cuenta'] ?? null,
                        'Calibre' => $fila['calibre'] ?? null,
                        'Hilo' => $fila['hilo'] ?? null,
                        'Tipo' => $montado->Tipo,
                        'Obs' => $fila['obs'] ?? null,
                        'ConfigId' => $montado->ConfigId,
                        'InventSizeId' => $montado->InventSizeId,
                        'InventColorId' => $montado->InventColorId,
                        'Estatus' => $montado->Estatus ?: 'Activo',
                        'CveOperador' => $cveOperador,
                    ];

                    if ($actual) {
                        $actual->fill($payload);
                        $actual->save();
                        $resultado->push($actual);
                    } else {
                        $resultado->push(AtaDevolucionesModel::create([...$payload, 'AX' => 0]));
                    }
                }

                return $resultado;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'No se pudo validar la devolución.',
                'bloqueado_ax' => true,
            ], 423);
        } catch (\Throwable $e) {
            Log::error('Error al registrar devoluciones Karl Mayer', [
                'ref_id' => $montado->Id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo registrar la devolución: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Devolución actualizada correctamente',
            'ids' => $guardadas->pluck('Id')->values(),
        ]);
    }

    private function esKarlMayer(mixed $tipo, mixed $noTelar): bool
    {
        if (preg_match('/^[1-4]$/', trim((string) $tipo))) {
            return true;
        }

        return in_array(trim((string) $noTelar), ['401', '402'], true);
    }

    private function textoDevolucion(mixed $valor, int $max): string
    {
        return mb_substr(trim((string) $valor), 0, $max);
    }
}
