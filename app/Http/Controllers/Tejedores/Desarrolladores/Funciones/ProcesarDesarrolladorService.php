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

    public function __construct(
        protected MovimientoDesarrolladorService $movimientoService,
        protected NotificacionTelegramDesarrolladorService $telegramService,
        protected CatCodificadosDesarrolladorService $catCodificadosService,
    ) {}

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
        // Por telarOrigen, no por destino: el renglon esta donde estaba antes del movimiento
        // y es el payload de abajo el que lo mueve al telar destino.
        $registro = $this->catCodificadosService->resolveCanonical(
            (string) $validated['NoProduccion'],
            (string) ($contextoDestino['telarOrigen'] ?? '')
        );
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
            // Columna de texto en SQL Server: se guarda tal cual se capturo.
            'AlturaRizo' => $this->normalizarAlturaRizo($validated['AlturaRizo'] ?? null),
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
            // Saldos lee rmc.AlturaRizo directo y Alineacion usa Cat con respaldo en rmc:
            // escribir solo CatCodificados dejaria las dos tablas en desacuerdo.
            'AlturaRizo' => $this->normalizarAlturaRizo($validated['AlturaRizo'] ?? null),
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
}
