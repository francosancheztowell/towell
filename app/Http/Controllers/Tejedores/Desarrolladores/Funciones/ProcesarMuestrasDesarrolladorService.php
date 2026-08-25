<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\Muestras;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use DomainException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProcesarMuestrasDesarrolladorService
{
    use ArmaDatosDesarrollador;

    /** @return class-string<ReqProgramaTejido> */
    protected function modeloPrograma(): string
    {
        return Muestras::class;
    }

    public function __construct(
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

                // Bloqueo liviano del telar destino
                Muestras::query()
                    ->where('SalonTejidoId', $contextoDestino['salonDestino'])
                    ->where('NoTelarId', $contextoDestino['telarDestino'])
                    ->lockForUpdate()
                    ->limit(1)
                    ->get();

                $ordenData = Muestras::query()
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
                    $modeloDestino
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

                // Post-procesamiento MUESTRAS: eliminar registro en lugar de poner EnProceso.
                // delete() no vacia los atributos en memoria, asi que el propio modelo
                // sirve para la notificacion, que solo lee dos fechas.
                $this->eliminarRegistroMuestra($programaObjetivo);

                return [
                    'programa' => $programaObjetivo,
                    'contexto' => $contextoDestino,
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

            return redirect()->route('tejedores.desarrolladores-muestras')->with('success', 'Datos guardados correctamente');
        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validacion',
                    'errors' => $e->errors(),
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

    /**
     * La muestra procesada se consume: se elimina por identidad.
     *
     * Antes se buscaba "el que tenga EnProceso=1 en ese salon+telar y si no, el de
     * FechaInicio mas antigua", sin recibir el registro que se acababa de procesar.
     * Si el operador procesaba la orden B mientras A estaba en proceso, se borraba A
     * y B sobrevivia intacta. Sin identidad no hay forma de acertar, asi que ahora se
     * borra exactamente la fila procesada o ninguna.
     */
    private function eliminarRegistroMuestra(Muestras $procesada): void
    {
        $registro = Muestras::query()
            ->whereKey($procesada->getKey())
            ->lockForUpdate()
            ->first();

        $registro?->delete();
    }

    private function resolverContextoOrigen(array $validated): array
    {
        $programa = Muestras::query()
            ->where('NoProduccion', $validated['NoProduccion'])
            ->where('NoTelarId', $validated['NoTelarId'])
            ->lockForUpdate()
            ->first();

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
        ?ReqModelosCodificados $modeloDestino
    ): ?CatCodificados {
        $registro = $this->catCodificadosService->resolveCanonical(
            (string) $validated['NoProduccion'],
            (string) ($contextoDestino['telarOrigen'] ?? '')
        );

        if (! $registro) {
            return null;
        }

        $payload = array_merge([
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
        ], $detallePayload, $pasadasPayload);

        if ($modeloDestino) {
            $payload['CuentaRizo'] = $modeloDestino->CuentaRizo ?? null;
            $payload['CuentaPie'] = $modeloDestino->CuentaPie ?? null;
        }

        $this->catCodificadosService->applyPayload($registro, $payload);
        $registro->save();

        return $registro;
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

        $codigoPrevioModelo = trim((string) ($registroModelo->CodigoDibujo ?? $registroModelo->CodificacionModelo ?? ''));
        if ($codigoPrevioModelo !== '') {
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

    private function buildPasadasPayload(array $pasadasFromRequest, $ordenData): array
    {
        // Las claves vienen del request y terminan en setAttribute(), que se salta $fillable:
        // sin esta lista blanca un POST puede escribir cualquier columna numerica de CatCodificados.
        $permitidas = ['PasadasTrama', 'PasadasTramaFondoC1', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5'];

        $pasadasPayload = [];
        if (count($pasadasFromRequest) > 0) {
            foreach ($pasadasFromRequest as $key => $value) {
                if ($value === null || $value === '' || ! in_array($key, $permitidas, true)) {
                    continue;
                }
                if ($key === 'PasadasTrama') {
                    $pasadasPayload['PasadasTramaFondoC1'] = (int) $value;
                } else {
                    $pasadasPayload[$key] = (int) $value;
                }
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
