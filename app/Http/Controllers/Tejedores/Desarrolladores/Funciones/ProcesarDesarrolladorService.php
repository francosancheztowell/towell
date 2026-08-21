<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use DomainException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProcesarDesarrolladorService
{
    use ArmaDatosDesarrollador;

    /** @return class-string<ReqProgramaTejido> */
    protected function modeloPrograma(): string
    {
        return ReqProgramaTejido::class;
    }

    protected MovimientoDesarrolladorService $movimientoService;

    protected NotificacionTelegramDesarrolladorService $telegramService;

    protected CatCodificadosDesarrolladorService $catCodificadosService;

    public function __construct(
        MovimientoDesarrolladorService $movimientoService,
        NotificacionTelegramDesarrolladorService $telegramService,
        ?CatCodificadosDesarrolladorService $catCodificadosService = null
    ) {
        $this->movimientoService = $movimientoService;
        $this->telegramService = $telegramService;
        $this->catCodificadosService = $catCodificadosService ?? app(CatCodificadosDesarrolladorService::class);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validarYNormalizarEntrada($request);

            $minutosCambio = $this->calcularMinutosCambio($validated['HoraInicio'] ?? null, $validated['HoraFinal'] ?? null);
            $fechaInicioProgramada = $this->construirFechaInicioProgramada($validated['HoraFinal'] ?? null);
            $longitudLuchaTot = $this->normalizarLongitudLucha($validated['LongitudLuchaTot'] ?? null);

            AuditoriaHelper::contexto(
                ($validated['accion'] ?? 'finalizar') === 'finalizar'
                    ? 'FINALIZA_DESARROLLADORES'
                    : 'MOVER_DESARROLLADORES'
            );

            $resultado = DB::transaction(function () use (
                $validated,
                $minutosCambio,
                $fechaInicioProgramada,
                $longitudLuchaTot
            ) {
                $contextoOrigen = $this->resolverContextoOrigen($validated);
                $contextoDestino = $this->resolverContextoDestino($validated, $contextoOrigen['programa']);
                $codigoDibujo = $this->normalizeCodigoDibujo(
                    $validated['CodificacionModelo'] ?? '',
                    $contextoDestino['telarDestino'] ?? null
                );

                // Bloqueo liviano del telar destino para minimizar condiciones de carrera.
                ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $contextoDestino['salonDestino'])
                    ->where('NoTelarId', $contextoDestino['telarDestino'])
                    ->lockForUpdate()
                    ->limit(1)
                    ->get();

                $ordenData = ReqProgramaTejido::query()
                    ->where('NoProduccion', $validated['NoProduccion'])
                    ->first();

                $detallePayload = $this->buildDetallePayloadFromOrden($ordenData);
                $detallePayload = $this->aplicarDetalleDesdeRequest($detallePayload, $validated);
                $pasadasPayload = $this->buildPasadasPayload($validated['pasadas'] ?? [], $ordenData);

                $modeloDestino = $this->resolverModeloDestinoYCopiaSiAplica(
                    $contextoOrigen['programa'],
                    $contextoDestino
                );

                $this->actualizarProgramaAntesDeMovimiento(
                    $contextoOrigen['programa'],
                    $modeloDestino,
                    $contextoDestino
                );

                $registroCodificado = $this->actualizarCatCodificados(
                    $validated,
                    $contextoDestino,
                    $detallePayload,
                    $pasadasPayload,
                    $codigoDibujo,
                    $minutosCambio,
                    $longitudLuchaTot,
                    $modeloDestino,
                    $ordenData
                );

                $claveModelo = $registroCodificado
                    ? $registroCodificado->getAttribute('ClaveModelo')
                    : data_get($ordenData, 'TamanoClave');

                if (! $contextoDestino['esCambioTelar']) {
                    $this->actualizarModeloDestinoSiCorresponde(
                        $claveModelo,
                        $contextoDestino['salonDestino'],
                        $contextoDestino['telarDestino'],
                        $validated,
                        $detallePayload,
                        $pasadasPayload,
                        $codigoDibujo,
                        $longitudLuchaTot
                    );
                }

                $fuenteDatos = $modeloDestino ?? $registroCodificado ?? $ordenData;
                $programas = $this->actualizarProgramasRelacionados(
                    $contextoOrigen['programa'],
                    $fuenteDatos,
                    $fechaInicioProgramada
                );

                $programaObjetivo = $programas->firstWhere('Id', $contextoOrigen['programa']->Id)
                    ?: $contextoOrigen['programa'];

                $codigoDibujoAnterior = null;
                if ($contextoDestino['esCambioTelar']) {
                    $codigoDibujoAnterior = ReqModelosCodificados::query()
                        ->where('SalonTejidoId', $contextoOrigen['salonOrigen'])
                        ->where('TamanoClave', $programaObjetivo->TamanoClave)
                        ->whereNotNull('CodigoDibujo')
                        ->orderByDesc('Id')
                        ->value('CodigoDibujo');

                    if (! $codigoDibujoAnterior) {
                        $codigoDibujoAnterior = $this->catCodificadosService->resolveCodigoDibujo(
                            (string) $validated['NoProduccion'],
                            (string) $contextoOrigen['telarOrigen']
                        );
                    }

                    $codigoDibujoAnterior = $this->normalizeCodigoDibujo($codigoDibujoAnterior, $contextoOrigen['telarOrigen']);
                }

                $programaFinal = $this->ejecutarMovimientoYPonerEnProceso(
                    $programaObjetivo,
                    $contextoDestino,
                    $validated['accion'] ?? 'finalizar'
                );

                return [
                    'programa' => $programaFinal ?: ReqProgramaTejido::query()->where('Id', $programaObjetivo->Id)->first(),
                    'contexto' => $contextoDestino,
                    'contextoOrigenInicial' => [
                        'salonOrigen' => $contextoOrigen['salonOrigen'],
                        'telarOrigen' => $contextoOrigen['telarOrigen'],
                    ],
                    'codigoDibujo' => $codigoDibujo,
                    'codigoDibujoAnterior' => $codigoDibujoAnterior,
                ];
            });

            if (! empty($resultado['programa'])) {
                $this->enviarNotificacion(
                    $validated,
                    $resultado['programa'],
                    (string) ($resultado['codigoDibujo'] ?? ''),
                    $resultado['contexto'],
                    $resultado['codigoDibujoAnterior'] ?? null
                );
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Datos guardados correctamente',
                ]);
            }

            return redirect()->route('tejedores.desarrolladores')->with('success', 'Datos guardados correctamente');
        } catch (ValidationException $e) {
            if ($request->ajax()) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->filter()->first();

                return response()->json([
                    'success' => false,
                    'message' => $firstError ?: 'Error de validacion',
                    'errors' => $errors,
                ], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (DomainException $e) {
            // Errores de negocio: el texto lo escribimos nosotros y le sirve al operador.
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage())->withInput();
        } catch (Exception $e) {
            // Fallo inesperado: el detalle va al log, no al navegador.
            Log::error('Error al procesar el desarrollador', [
                'telar' => $request->input('NoTelarId'),
                'produccion' => $request->input('NoProduccion'),
                'error' => $e->getMessage(),
            ]);

            $generico = 'Ocurrio un error al guardar. Avisa a sistemas si se repite.';

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $generico,
                ], 500);
            }

            return back()->with('error', $generico)->withInput();
        }
    }

    private function validarYNormalizarEntrada(Request $request): array
    {
        $request->merge([
            'CambioTelarActivo' => filter_var(
                $request->input('CambioTelarActivo', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'EficienciaInicio' => $this->normalizarEntero($request->input('EficienciaInicio')),
            'EficienciaFinal' => $this->normalizarEntero($request->input('EficienciaFinal')),
        ]);

        $validated = $request->validate([
            'NoTelarId' => 'required|string',
            'NoProduccion' => 'required|string|max:80',
            'registroId' => 'nullable|integer',
            'accion' => 'nullable|string|in:finalizar,reprogramar_siguiente,reprogramar_final',
            'NumeroJulioRizo' => 'required|string|max:50',
            'NumeroJulioPie' => 'nullable|string|max:50',
            'TotalPasadasDibujo' => 'required|integer|min:1',
            'HoraInicio' => 'nullable|date_format:H:i',
            'EficienciaInicio' => 'nullable|integer|min:0|max:100',
            'HoraFinal' => 'nullable|date_format:H:i',
            'EficienciaFinal' => 'nullable|integer|min:0|max:100',
            'Desarrollador' => 'nullable|string|max:100',
            'TramaAnchoPeine' => 'nullable|numeric|min:0',
            'DesperdicioTrama' => 'nullable|numeric|min:0',
            'LongitudLuchaTot' => 'nullable|numeric|min:0',
            'CodificacionModelo' => ['required', 'string', 'max:100', $this->reglaLongitudCodigoDibujo()],
            'pasadas' => 'nullable|array',
            'pasadas.*' => 'nullable|integer|min:1',
            'detalle_calibre' => 'nullable|array',
            'detalle_calibre.*' => 'nullable|string|max:50',
            'detalle_hilo' => 'nullable|array',
            'detalle_hilo.*' => 'nullable|numeric|min:0',
            'detalle_fibra' => 'nullable|array',
            'detalle_fibra.*' => 'nullable|string|max:100',
            'detalle_codcolor' => 'nullable|array',
            'detalle_codcolor.*' => 'nullable|string|max:50',
            'detalle_nombrecolor' => 'nullable|array',
            'detalle_nombrecolor.*' => 'nullable|string|max:100',
            'CambioTelarActivo' => 'nullable|boolean',
            'TelarDestino' => 'nullable|string|max:120',
        ]);

        $validated['CambioTelarActivo'] = (bool) ($validated['CambioTelarActivo'] ?? false);

        if ($validated['CambioTelarActivo'] && empty(trim((string) ($validated['TelarDestino'] ?? '')))) {
            throw ValidationException::withMessages([
                'TelarDestino' => 'Debes seleccionar un telar destino para realizar el cambio.',
            ]);
        }

        return $validated;
    }

    private function reglaLongitudCodigoDibujo(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            $codigo = preg_replace('/\.(?:JC5|JCS)\s*$/i', '', trim((string) $value));
            $longitud = mb_strlen($codigo);

            if ($longitud < 10 || $longitud > 20) {
                $fail('El código de dibujo debe tener entre 10 y 20 caracteres.');
            }
        };
    }

    private function normalizarEntero($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        if (! is_numeric($value)) {
            return $value;
        }

        return (int) round((float) $value);
    }

    private function resolverContextoOrigen(array $validated): array
    {
        $programa = ReqProgramaTejido::query()
            ->where('NoProduccion', $validated['NoProduccion'])
            ->where('NoTelarId', $validated['NoTelarId'])
            ->lockForUpdate()
            ->first();

        // Fila sin orden: buscar por registroId y asignar la orden nueva
        if (! $programa && ! empty($validated['registroId'])) {
            $programa = ReqProgramaTejido::query()
                ->where('Id', $validated['registroId'])
                ->where('NoTelarId', $validated['NoTelarId'])
                ->where(function ($q) {
                    $q->whereNull('NoProduccion')->orWhere('NoProduccion', '');
                })
                ->lockForUpdate()
                ->first();

            if ($programa) {
                $programa->NoProduccion = $validated['NoProduccion'];
                $programa->save();
            }
        }

        if (! $programa) {
            throw ValidationException::withMessages([
                'NoProduccion' => 'No se encontro la orden seleccionada para el telar indicado.',
            ]);
        }

        return [
            'programa' => $programa,
            'salonOrigen' => trim((string) ($programa->SalonTejidoId ?? '')),
            'telarOrigen' => trim((string) ($programa->NoTelarId ?? '')),
        ];
    }

    private function actualizarCatCodificados(
        array $validated,
        array $contextoDestino,
        array $detallePayload,
        array $pasadasPayload,
        string $codigoDibujo,
        ?int $minutosCambio,
        ?int $longitudLuchaTot,
        ?ReqModelosCodificados $modeloDestino,
        ?ReqProgramaTejido $ordenData = null
    ): ?CatCodificados {
        $registro = $this->catCodificadosService->resolveCanonical((string) $validated['NoProduccion']);
        $fechasArranqueFinaliza = $this->buildFechasArranqueFinalizaPayload(
            $validated['HoraInicio'] ?? null,
            $validated['HoraFinal'] ?? null
        );
        if (($validated['accion'] ?? 'finalizar') !== 'finalizar') {
            $fechasArranqueFinaliza['FechaFinaliza'] = null;
        }

        $esNuevo = false;
        if (! $registro) {
            $registro = new CatCodificados;
            $esNuevo = true;
        }

        // Cuando es un registro nuevo, usar los datos de ReqProgramaTejido como base
        $programaPayload = [];
        if ($esNuevo && $ordenData) {
            $programaPayload = [
                'Nombre' => $ordenData->NombreProducto,
                'ClaveModelo' => $ordenData->TamanoClave,
                'ItemId' => $ordenData->ItemId,
                'InventSizeId' => $ordenData->InventSizeId,
                'FlogsId' => $ordenData->FlogsId,
                'NombreProyecto' => $ordenData->NombreProyecto,
                'CustName' => $ordenData->CustName,
                'Peine' => $ordenData->Peine,
                'Ancho' => $ordenData->Ancho,
                'Luchaje' => $ordenData->Luchaje,
                'P_crudo' => $ordenData->PesoCrudo,
                'DobladilloId' => $ordenData->DobladilloId,
                'MedidaPlano' => $ordenData->MedidaPlano,
                'CalibreRizo' => $ordenData->CalibreRizo,
                'CalibreRizo2' => $ordenData->CalibreRizo2,
                'CuentaRizo' => $ordenData->CuentaRizo,
                'FibraRizo' => $ordenData->FibraRizo,
                'CalibrePie' => $ordenData->CalibrePie,
                'CalibrePie2' => $ordenData->CalibrePie2,
                'CuentaPie' => $ordenData->CuentaPie,
                'FibraPie' => $ordenData->FibraPie,
                'VelocidadSTD' => $ordenData->VelocidadSTD,
                'EficienciaSTD' => $ordenData->EficienciaSTD,
                'NoTiras' => $ordenData->NoTiras,
                'Repeticiones' => $ordenData->Repeticiones,
                'Prioridad' => $ordenData->Prioridad,
                'MtsRollo' => $ordenData->MtsRollo,
                'PzasRollo' => $ordenData->PzasRollo,
                'TotalRollos' => $ordenData->TotalRollos,
                'TotalPzas' => $ordenData->TotalPzas,
                'CombinaTram' => $ordenData->CombinaTram,
                'BomId' => $ordenData->BomId,
                'BomName' => $ordenData->BomName,
                'CreaProd' => $ordenData->CreaProd,
                'Densidad' => $ordenData->Densidad,
                'HiloAX' => $ordenData->HiloAX,
                'ActualizaLmat' => $ordenData->ActualizaLmat,
                'PesoMuestra' => $ordenData->PesoMuestra,
                'OrdCompartida' => $ordenData->OrdCompartida,
                'OrdCompartidaLider' => $ordenData->OrdCompartidaLider,
                'CategoriaCalidad' => $ordenData->CategoriaCalidad,
                'FechaTejido' => $ordenData->FechaInicio?->format('Y-m-d'),
                'OrdPrincipal' => $ordenData->OrdPrincipal,
                'FechaArranque' => null,
                'FechaFinaliza' => null,
                'Cantidad' => $ordenData->TotalPedido,
            ];
        }

        $payload = array_merge($programaPayload, [
            'TelarId' => $contextoDestino['telarDestino'],
            'NoTelarId' => $contextoDestino['telarDestino'],
            'Departamento' => $contextoDestino['salonDestino'],
            'OrdenTejido' => $validated['NoProduccion'],
            'CodigoDibujo' => $codigoDibujo,
            'CodificacionModelo' => $codigoDibujo,
            'RespInicio' => $validated['Desarrollador'] ?? null,
            'HrInicio' => $validated['HoraInicio'] ?? null,
            'HrTermino' => $validated['HoraFinal'] ?? null,
            'MinutosCambio' => $minutosCambio,
            'TramaAnchoPeine' => $validated['TramaAnchoPeine'] ?? null,
            'AnchoPeineTrama' => $validated['TramaAnchoPeine'] ?? null,
            'LogLuchaTotal' => $longitudLuchaTot,
            'LongitudLuchaTot' => $longitudLuchaTot,
            'Total' => $validated['TotalPasadasDibujo'],
            'TotalPasadasDibujo' => $validated['TotalPasadasDibujo'],
            'NumeroJulioRizo' => $validated['NumeroJulioRizo'],
            'NumeroJulioPie' => $validated['NumeroJulioPie'] ?? null,
            'JulioRizo' => $validated['NumeroJulioRizo'],
            'JulioPie' => $validated['NumeroJulioPie'] ?? null,
            'EficienciaInicio' => $validated['EficienciaInicio'] ?? null,
            'EficienciaFinal' => $validated['EficienciaFinal'] ?? null,
            'EfiInicial' => $validated['EficienciaInicio'] ?? null,
            'EfiFinal' => $validated['EficienciaFinal'] ?? null,
            'DesperdicioTrama' => $validated['DesperdicioTrama'] ?? null,
            'FechaCumplimiento' => now()->format('Y-m-d H:i:s'),
        ], $fechasArranqueFinaliza, $detallePayload, $pasadasPayload);

        if ($modeloDestino) {
            $payload['CuentaRizo'] = $modeloDestino->CuentaRizo ?? null;
            $payload['CuentaPie'] = $modeloDestino->CuentaPie ?? null;
            $payload['TipoRizo'] = $modeloDestino->TipoRizo ?? null;
            $payload['Tolerancia'] = $modeloDestino->Tolerancia ?? null;
            $payload['Clave'] = $modeloDestino->Clave ?? null;
            $payload['Vendedor'] = $modeloDestino->Vendedor ?? null;
            $payload['FlogsId'] = $modeloDestino->FlogsId ?? ($ordenData->FlogsId ?? null);
        }

        // Rasurado viene de ReqProgramaTejido
        if ($ordenData && $ordenData->Rasurado !== null) {
            $payload['Razurada'] = $ordenData->Rasurado;
        }

        $this->catCodificadosService->applyPayload($registro, $payload);
        $registro->save();

        return $registro;
    }

    private function buildFechasArranqueFinalizaPayload(?string $horaInicio, ?string $horaFinal): array
    {
        $fechaBase = Carbon::today();
        $fechaArranque = $this->anclarAlDiaMasCercano($this->combinarFechaYHora($horaInicio, $fechaBase));
        $fechaFinalizaBase = $fechaArranque ? $fechaArranque->copy() : $fechaBase;

        if ($fechaArranque && $horaFinal) {
            try {
                $horaFinalCarbon = Carbon::createFromFormat('H:i', $horaFinal);
                $fechaFinalizaBase = $fechaArranque->copy();

                if ($horaFinalCarbon->format('H:i') < $fechaArranque->format('H:i')) {
                    $fechaFinalizaBase->addDay();
                }
            } catch (Exception $e) {
                $fechaFinalizaBase = $fechaBase;
            }
        }

        $fechaFinaliza = $this->combinarFechaYHora($horaFinal, $fechaFinalizaBase);

        return [
            'FechaArranque' => $fechaArranque?->format('Y-m-d H:i:s'),
            'FechaFinaliza' => $fechaFinaliza?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Ancla una hora capturada al dia al que realmente pertenece.
     *
     * Con Carbon::today() la hora se pegaba siempre al dia del servidor: el turno 3
     * captura 23:50 y si envia pasada la medianoche el registro quedaba sellado al dia
     * siguiente. Se elige la ocurrencia de esa hora mas cercana a ahora, que para una
     * jornada de 8 horas es siempre la correcta.
     */
    private function combinarFechaYHora(?string $hora, Carbon $fechaBase): ?Carbon
    {
        if (empty($hora)) {
            return null;
        }

        try {
            $horaCarbon = Carbon::createFromFormat('H:i', $hora);

            return $fechaBase->copy()->setTime(
                (int) $horaCarbon->format('H'),
                (int) $horaCarbon->format('i'),
                0
            );
        } catch (Exception $e) {
            return null;
        }
    }

    private function actualizarModeloDestinoSiCorresponde(
        ?string $claveModelo,
        ?string $salonDestino,
        string $telarDestino,
        array $validated,
        array $detallePayload,
        array $pasadasPayload,
        string $codigoDibujo,
        ?int $longitudLuchaTot
    ): void {
        $claveModelo = trim((string) ($claveModelo ?? ''));
        $salonDestino = trim((string) ($salonDestino ?? ''));
        if ($claveModelo === '' || $salonDestino === '' || $codigoDibujo === '') {
            return;
        }

        $registroModelo = ReqModelosCodificados::query()
            ->where('TamanoClave', $claveModelo)
            ->where('SalonTejidoId', $salonDestino)
            ->first();

        if (! $registroModelo) {
            return;
        }

        $payloadModelo = array_merge([
            'TamanoClave' => $claveModelo,
            'SalonTejidoId' => $salonDestino,
            'NoTelarId' => $telarDestino,
            'OrdenTejido' => $validated['NoProduccion'],
            'CodigoDibujo' => $codigoDibujo,
            'AnchoPeineTrama' => $validated['TramaAnchoPeine'] ?? null,
            'LogLuchaTotal' => $longitudLuchaTot,
            'Total' => $validated['TotalPasadasDibujo'],
            'FechaCumplimiento' => now()->format('Y-m-d H:i:s'),
        ], $detallePayload, $pasadasPayload);

        $columnasModelo = Schema::getColumnListing($registroModelo->getTable());
        foreach ($payloadModelo as $column => $value) {
            if (! in_array($column, $columnasModelo, true)) {
                continue;
            }
            $registroModelo->setAttribute($column, $value);
        }
        $registroModelo->save();
    }

    private function ejecutarMovimientoYPonerEnProceso(
        ReqProgramaTejido $programaObjetivo,
        array $contextoDestino,
        string $accion = 'finalizar'
    ): ?ReqProgramaTejido {
        $reprogramarValor = match ($accion) {
            'reprogramar_siguiente' => '1',
            'reprogramar_final' => '2',
            default => null,
        };

        if ($contextoDestino['esCambioTelar']) {
            return $this->movimientoService->moverRegistroConCambioTelarEnProceso(
                $programaObjetivo,
                $contextoDestino['salonDestino'],
                $contextoDestino['telarDestino'],
                $reprogramarValor
            );
        }

        if ($reprogramarValor !== null) {
            // La actual en proceso se debe MOVER (no eliminar), por eso se le setea Reprogramar.
            // El seleccionado ($programaObjetivo) es el que quedará en proceso.
            $actualEnProceso = ReqProgramaTejido::query()
                ->where('SalonTejidoId', $programaObjetivo->SalonTejidoId)
                ->where('NoTelarId', $programaObjetivo->NoTelarId)
                ->where('EnProceso', 1)
                ->where('Id', '!=', $programaObjetivo->Id)
                ->first();

            if ($actualEnProceso) {
                $actualEnProceso->Reprogramar = $reprogramarValor;
                $actualEnProceso->saveQuietly();
            }
        }

        $this->movimientoService->moverRegistroEnProceso($programaObjetivo, true);

        return ReqProgramaTejido::query()->where('Id', $programaObjetivo->Id)->first();
    }

    private function buildPasadasPayload(array $pasadasFromRequest, $ordenData): array
    {
        $pasadasPayload = [];
        if (count($pasadasFromRequest) > 0) {
            $pasadasTrama = $pasadasFromRequest['PasadasTrama']
                ?? $pasadasFromRequest['PasadasTramaFondoC1']
                ?? null;
            if ($pasadasTrama !== null && $pasadasTrama !== '') {
                $pasadasPayload['PasadasTramaFondoC1'] = (int) $pasadasTrama;
            }

            for ($i = 1; $i <= 5; $i++) {
                $campo = "PasadasComb{$i}";
                $valor = $pasadasFromRequest[$campo] ?? null;
                $pasadasPayload[$campo] = ($valor === null || $valor === '') ? null : (int) $valor;
            }
        } elseif ($ordenData) {
            $tramaValue = data_get($ordenData, 'PasadasTrama');
            if ($tramaValue !== null && $tramaValue !== '') {
                $pasadasPayload['PasadasTramaFondoC1'] = (int) $tramaValue;
            }
            for ($i = 1; $i <= 5; $i++) {
                $field = "PasadasComb{$i}";
                $combValue = data_get($ordenData, $field);
                if ($combValue === null || $combValue === '') {
                    continue;
                }
                $pasadasPayload[$field] = (int) $combValue;
            }
        }

        return $pasadasPayload;
    }

    private function buildDetallePayloadFromOrden($ordenData): array
    {
        if (! $ordenData) {
            return [];
        }
        $colorTrama = data_get($ordenData, 'ColorTrama') ?: data_get($ordenData, 'FibraTrama');

        $payload = [
            'Tra' => data_get($ordenData, 'CalibreTrama'),
            'CalibreTrama2' => data_get($ordenData, 'CalibreTrama2'),
            'CodColorTrama' => data_get($ordenData, 'CodColorTrama'),
            'ColorTrama' => $colorTrama,
            'FibraId' => data_get($ordenData, 'FibraTrama'),
            'CalTramaFondoC1' => data_get($ordenData, 'CalibreTrama'),
            'CalTramaFondoC12' => data_get($ordenData, 'CalibreTrama2'),
            'FibraTramaFondoC1' => data_get($ordenData, 'FibraTrama'),
        ];

        for ($i = 1; $i <= 5; $i++) {
            $nombreKey = $ordenData->{"NombreCC{$i}"} !== null ? "NombreCC{$i}" : "NomColorC{$i}";
            $nombreColor = data_get($ordenData, $nombreKey) ?: data_get($ordenData, "FibraComb{$i}");

            $payload["CalibreComb{$i}"] = data_get($ordenData, "CalibreComb{$i}");
            $payload["CalibreComb{$i}2"] = data_get($ordenData, "CalibreComb{$i}2");
            $payload["FibraComb{$i}"] = data_get($ordenData, "FibraComb{$i}");
            $payload["CodColorC{$i}"] = data_get($ordenData, "CodColorComb{$i}");
            $payload["NomColorC{$i}"] = $nombreColor;
        }

        return $payload;
    }

    /**
     * Sobrescribe $detallePayload con lo que el usuario editó en la tabla "Detalles de la Orden"
     * (detalle_calibre[]/detalle_hilo[]/detalle_fibra[]/detalle_codcolor[]/detalle_nombrecolor[]).
     * Cada fila se relaciona con su slot (Trama / Comb1..5) por la posición de su llave en
     * pasadas[], que ya viene nombrada igual que la columna (PasadasTramaFondoC1, PasadasCombN).
     * Si no hay forma de emparejar filas 1 a 1 con llaves de pasadas, no se sobrescribe nada
     * (se conservan los valores de la orden) para no mezclar datos de slots equivocados.
     */
    private function aplicarDetalleDesdeRequest(array $detallePayload, array $validated): array
    {
        $calibres = $validated['detalle_calibre'] ?? [];
        $hilos = $validated['detalle_hilo'] ?? [];
        $fibras = $validated['detalle_fibra'] ?? [];
        $codColores = $validated['detalle_codcolor'] ?? [];
        $nombreColores = $validated['detalle_nombrecolor'] ?? [];

        if (empty($calibres) && empty($hilos) && empty($fibras) && empty($codColores) && empty($nombreColores)) {
            return $detallePayload;
        }

        $pasadasKeys = array_keys($validated['pasadas'] ?? []);
        $total = count($calibres);
        if ($total === 0 || $total !== count($pasadasKeys)) {
            Log::warning('Detalle de orden ignorado: no se pudo emparejar con pasadas[]', [
                'total_detalle' => $total,
                'total_pasadas_keys' => count($pasadasKeys),
            ]);

            return $detallePayload;
        }

        $slotsCombinacionEnviados = array_flip(array_filter(
            $pasadasKeys,
            static fn ($key): bool => preg_match('/^PasadasComb[1-5]$/', (string) $key) === 1
        ));
        for ($i = 1; $i <= 5; $i++) {
            if (isset($slotsCombinacionEnviados["PasadasComb{$i}"])) {
                continue;
            }

            $detallePayload["CalibreComb{$i}"] = null;
            $detallePayload["CalibreComb{$i}2"] = null;
            $detallePayload["FibraComb{$i}"] = null;
            $detallePayload["CodColorC{$i}"] = null;
            $detallePayload["NomColorC{$i}"] = null;
        }

        $valor = static function (array $arr, int $i) {
            $v = $arr[$i] ?? null;
            $v = is_string($v) ? trim($v) : $v;

            return ($v === null || $v === '') ? null : $v;
        };

        for ($i = 0; $i < $total; $i++) {
            $key = $pasadasKeys[$i];
            $calibre = $valor($calibres, $i);
            $hilo = $valor($hilos, $i);
            $fibra = $valor($fibras, $i);
            $codColor = $valor($codColores, $i);
            $nombreColor = $valor($nombreColores, $i);

            if ($key === 'PasadasTramaFondoC1' || $key === 'PasadasTrama') {
                if ($calibre !== null) {
                    $detallePayload['Tra'] = $calibre;
                    $detallePayload['CalTramaFondoC1'] = $calibre;
                }
                if ($fibra !== null) {
                    $detallePayload['FibraId'] = $fibra;
                    $detallePayload['FibraTramaFondoC1'] = $fibra;
                }
                if ($codColor !== null) {
                    $detallePayload['CodColorTrama'] = $codColor;
                }
                if ($nombreColor !== null) {
                    $detallePayload['ColorTrama'] = $nombreColor;
                }
                if ($hilo !== null) {
                    $detallePayload['HiloAX'] = $hilo;
                    $detallePayload['CalibreTrama2'] = $hilo;
                    $detallePayload['CalTramaFondoC12'] = $hilo;
                }

                continue;
            }

            if (preg_match('/^PasadasComb([1-5])$/', $key, $m)) {
                $n = $m[1];
                if ($calibre !== null) {
                    $detallePayload["CalibreComb{$n}"] = $calibre;
                }
                if ($fibra !== null) {
                    $detallePayload["FibraComb{$n}"] = $fibra;
                }
                if ($codColor !== null) {
                    $detallePayload["CodColorC{$n}"] = $codColor;
                }
                if ($nombreColor !== null) {
                    $detallePayload["NomColorC{$n}"] = $nombreColor;
                }
                if ($hilo !== null) {
                    $detallePayload["CalibreComb{$n}2"] = $hilo;
                }
            }
        }

        return $detallePayload;
    }
}
