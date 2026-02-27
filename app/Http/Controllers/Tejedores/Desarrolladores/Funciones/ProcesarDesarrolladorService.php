<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejedores\TelTelaresOperador;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcesarDesarrolladorService
{
    protected MovimientoDesarrolladorService $movimientoService;
    protected NotificacionTelegramDesarrolladorService $telegramService;

    public function __construct(
        MovimientoDesarrolladorService $movimientoService,
        NotificacionTelegramDesarrolladorService $telegramService
    ) {
        $this->movimientoService = $movimientoService;
        $this->telegramService = $telegramService;
    }

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

                if (!$contextoDestino['esCambioTelar']) {
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

                    if (!$codigoDibujoAnterior) {
                        $modeloCat = new CatCodificados();
                        $colCat = Schema::getColumnListing($modeloCat->getTable());
                        $queryCat = CatCodificados::query();
                        if (in_array('OrdenTejido', $colCat, true)) {
                            $queryCat->where('OrdenTejido', $validated['NoProduccion']);
                        } else {
                            $queryCat->where('NoProduccion', $validated['NoProduccion']);
                        }
                        if (in_array('TelarId', $colCat, true)) {
                            $queryCat->where('TelarId', $contextoOrigen['telarOrigen']);
                        } elseif (in_array('NoTelarId', $colCat, true)) {
                            $queryCat->where('NoTelarId', $contextoOrigen['telarOrigen']);
                        }
                        $codigoDibujoAnterior = $queryCat->value('CodigoDibujo');
                    }
                    
                    $codigoDibujoAnterior = $this->normalizeCodigoDibujo($codigoDibujoAnterior, $contextoOrigen['telarOrigen']);
                }

                $programaFinal = $this->ejecutarMovimientoYPonerEnProceso(
                    $programaObjetivo,
                    $contextoDestino
                );

                return [
                    'programa' => $programaFinal ?: ReqProgramaTejido::query()->where('Id', $programaObjetivo->Id)->first(),
                    'contexto' => $contextoDestino,
                    'codigoDibujo' => $codigoDibujo,
                    'codigoDibujoAnterior' => $codigoDibujoAnterior,
                ];
            });

            if (!empty($resultado['programa'])) {
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

            return redirect()->route('desarrolladores')->with('success', 'Datos guardados correctamente');
        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validacion',
                    'errors' => $e->errors(),
                ], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrio un error: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Ocurrio un error: ' . $e->getMessage())->withInput();
        }
    }

    private function validarYNormalizarEntrada(Request $request): array
    {
        $request->merge([
            'CambioTelarActivo' => filter_var(
                $request->input('CambioTelarActivo', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        ]);

        $validated = $request->validate([
            'NoTelarId' => 'required|string',
            'NoProduccion' => 'required|string|max:80',
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
            'CodificacionModelo' => 'required|string|max:100',
            'pasadas' => 'nullable|array',
            'pasadas.*' => 'nullable|integer|min:1',
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

    private function resolverContextoOrigen(array $validated): array
    {
        $programa = ReqProgramaTejido::query()
            ->where('NoProduccion', $validated['NoProduccion'])
            ->where('NoTelarId', $validated['NoTelarId'])
            ->lockForUpdate()
            ->first();

        if (!$programa) {
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

    private function resolverContextoDestino(array $validated, ReqProgramaTejido $programaOrigen): array
    {
        $salonOrigen = trim((string) ($programaOrigen->SalonTejidoId ?? ''));
        $telarOrigen = trim((string) ($programaOrigen->NoTelarId ?? ''));
        $esCambioTelar = (bool) ($validated['CambioTelarActivo'] ?? false);

        $salonDestino = $salonOrigen;
        $telarDestino = $telarOrigen;

        if ($esCambioTelar) {
            $rawDestino = trim((string) ($validated['TelarDestino'] ?? ''));
            $partes = explode('|', $rawDestino, 2);

            if (count($partes) !== 2) {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'El formato de telar destino es invalido.',
                ]);
            }

            $salonDestino = trim((string) ($partes[0] ?? ''));
            $telarDestino = trim((string) ($partes[1] ?? ''));

            if ($salonDestino === '' || $telarDestino === '') {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'Debes seleccionar un telar destino valido.',
                ]);
            }

            if ($salonDestino === $salonOrigen && $telarDestino === $telarOrigen) {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'El telar destino debe ser diferente al telar origen.',
                ]);
            }

            $existeDestino = ReqProgramaTejido::query()
                ->where('SalonTejidoId', $salonDestino)
                ->where('NoTelarId', $telarDestino)
                ->exists();

            if (!$existeDestino) {
                $existeDestino = TelTelaresOperador::query()
                    ->where('NoTelarId', $telarDestino)
                    ->where(function ($query) use ($salonDestino) {
                        $query->whereNull('SalonTejidoId')
                            ->orWhere('SalonTejidoId', $salonDestino);
                    })
                    ->exists();
            }

            if (!$existeDestino) {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'El telar destino seleccionado no existe o no esta disponible.',
                ]);
            }
        }

        return [
            'esCambioTelar' => $esCambioTelar,
            'salonOrigen' => $salonOrigen,
            'telarOrigen' => $telarOrigen,
            'salonDestino' => $salonDestino,
            'telarDestino' => $telarDestino,
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
        $modeloCat = new CatCodificados();
        $columns = Schema::getColumnListing($modeloCat->getTable());

        $queryBase = CatCodificados::query();
        $hasOrderFilter = false;
        if (in_array('OrdenTejido', $columns, true)) {
            $queryBase->where('OrdenTejido', $validated['NoProduccion']);
            $hasOrderFilter = true;
        } elseif (in_array('NumOrden', $columns, true)) {
            $queryBase->where('NumOrden', $validated['NoProduccion']);
            $hasOrderFilter = true;
        }

        if (!$hasOrderFilter) {
            $queryBase->where('NoProduccion', $validated['NoProduccion']);
        }

        $telarColumn = null;
        if (in_array('TelarId', $columns, true)) {
            $telarColumn = 'TelarId';
        } elseif (in_array('NoTelarId', $columns, true)) {
            $telarColumn = 'NoTelarId';
        }

        $registro = null;
        if ($telarColumn) {
            $telaresPreferidos = array_values(array_unique(array_filter([
                $contextoDestino['telarOrigen'] ?? null,
                $contextoDestino['telarDestino'] ?? null,
            ])));

            foreach ($telaresPreferidos as $telar) {
                $registro = (clone $queryBase)->where($telarColumn, $telar)->first();
                if ($registro) {
                    break;
                }
            }
        }

        if (!$registro) {
            $registro = (clone $queryBase)->first();
        }

        if (!$registro) {
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
            'FechaCumplimiento' => now()->format('Y-m-d H:i:s'),
        ], $detallePayload, $pasadasPayload);

        if ($modeloDestino) {
            $payload['CuentaRizo'] = $modeloDestino->CuentaRizo ?? null;
            $payload['CuentaPie'] = $modeloDestino->CuentaPie ?? null;
        }

        foreach ($payload as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $registro->setAttribute($column, $value);
        }

        $registro->save();
        return $registro;
    }

    private function resolverModeloDestinoYCopiaSiAplica(
        ReqProgramaTejido $programaOrigen,
        array $contextoDestino
    ): ?ReqModelosCodificados {
        $tamanoClave = trim((string) ($programaOrigen->TamanoClave ?? ''));
        if ($tamanoClave === '') {
            return null;
        }

        $salonDestino = $contextoDestino['salonDestino'];
        $telarDestino = $contextoDestino['telarDestino'];

        $modeloDestino = ReqModelosCodificados::query()
            ->where('TamanoClave', $tamanoClave)
            ->where('SalonTejidoId', $salonDestino)
            ->first();

        if ($modeloDestino) {
            return $modeloDestino;
        }

        if (!$contextoDestino['esCambioTelar']) {
            return null;
        }

        $salonOrigen = $contextoDestino['salonOrigen'];
        $modeloOrigen = ReqModelosCodificados::query()
            ->where('TamanoClave', $tamanoClave)
            ->where('SalonTejidoId', $salonOrigen)
            ->first();

        if (!$modeloOrigen) {
            $modeloOrigen = ReqModelosCodificados::query()
                ->where('TamanoClave', $tamanoClave)
                ->first();
        }

        if (!$modeloOrigen) {
            return null;
        }

        $nuevoModelo = $modeloOrigen->replicate();
        $columnasModelo = Schema::getColumnListing($nuevoModelo->getTable());

        if (in_array('SalonTejidoId', $columnasModelo, true)) {
            $nuevoModelo->SalonTejidoId = $salonDestino;
        }
        if (in_array('NoTelarId', $columnasModelo, true)) {
            $nuevoModelo->NoTelarId = $telarDestino;
        }
        if (in_array('OrdenTejido', $columnasModelo, true)) {
            $nuevoModelo->OrdenTejido = $programaOrigen->NoProduccion;
        }
        if (in_array('CodigoDibujo', $columnasModelo, true)) {
            $nuevoModelo->CodigoDibujo = null;
        }
        if (in_array('CodificacionModelo', $columnasModelo, true)) {
            $nuevoModelo->CodificacionModelo = null;
        }

        $nuevoModelo->save();
        return $nuevoModelo;
    }

    private function actualizarProgramaAntesDeMovimiento(
        ReqProgramaTejido $programaOrigen,
        ?ReqModelosCodificados $modeloDestino,
        array $contextoDestino
    ): void {
        if (!$contextoDestino['esCambioTelar']) {
            return;
        }

        [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar(
            $programaOrigen,
            $modeloDestino,
            $contextoDestino['telarDestino'],
            $contextoDestino['salonDestino']
        );

        if (!is_null($nuevaEficiencia)) {
            $programaOrigen->EficienciaSTD = round((float) $nuevaEficiencia, 2);
        }
        if (!is_null($nuevaVelocidad)) {
            $programaOrigen->VelocidadSTD = (float) $nuevaVelocidad;
        }

        $programaOrigen->Maquina = TejidoHelpers::construirMaquinaConSalon(
            $programaOrigen->Maquina ?? null,
            $contextoDestino['salonDestino'],
            $contextoDestino['telarDestino']
        );

        if ($modeloDestino) {
            if (!is_null($modeloDestino->CuentaRizo)) {
                $programaOrigen->CuentaRizo = (string) $modeloDestino->CuentaRizo;
            }
            if (!is_null($modeloDestino->CuentaPie)) {
                $programaOrigen->CuentaPie = (string) $modeloDestino->CuentaPie;
            }
        }

        $programaOrigen->saveQuietly();
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

        if (!$registroModelo) {
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
        ], $detallePayload, $pasadasPayload);

        $columnasModelo = Schema::getColumnListing($registroModelo->getTable());
        foreach ($payloadModelo as $column => $value) {
            if (!in_array($column, $columnasModelo, true)) {
                continue;
            }
            $registroModelo->setAttribute($column, $value);
        }
        $registroModelo->save();
    }

    private function actualizarProgramasRelacionados(
        ReqProgramaTejido $programaInicial,
        $fuenteDatos,
        ?string $fechaInicioProgramada
    ): Collection {
        $programas = collect();

        if (!empty($programaInicial->OrdCompartida)) {
            $programas = ReqProgramaTejido::query()
                ->where('OrdCompartida', (int) $programaInicial->OrdCompartida)
                ->lockForUpdate()
                ->get();
        } else {
            $programas = ReqProgramaTejido::query()
                ->where('NoProduccion', $programaInicial->NoProduccion)
                ->where('SalonTejidoId', $programaInicial->SalonTejidoId)
                ->lockForUpdate()
                ->get();
        }

        if ($programas->isEmpty()) {
            return collect([$programaInicial]);
        }

        if (!$fuenteDatos) {
            return $programas;
        }

        $columnasPrograma = Schema::getColumnListing($programas->first()->getTable());
        $payloadPrograma = [
            'CalibreTrama' => $fuenteDatos->Tra ?? $fuenteDatos->CalibreTrama ?? null,
            'CalibreTrama2' => $fuenteDatos->CalibreTrama2 ?? null,
            'FibraTrama' => $fuenteDatos->FibraId ?? $fuenteDatos->FibraTrama ?? null,
            'PasadasTrama' => $fuenteDatos->PasadasTramaFondoC1 ?? $fuenteDatos->PasadasTrama ?? null,
            'PasadasComb1' => $fuenteDatos->PasadasComb1 ?? null,
            'PasadasComb2' => $fuenteDatos->PasadasComb2 ?? null,
            'PasadasComb3' => $fuenteDatos->PasadasComb3 ?? null,
            'PasadasComb4' => $fuenteDatos->PasadasComb4 ?? null,
            'PasadasComb5' => $fuenteDatos->PasadasComb5 ?? null,
            'CodColorTrama' => $fuenteDatos->CodColorTrama ?? null,
            'ColorTrama' => $fuenteDatos->ColorTrama ?? null,
            'CalibreComb1' => $fuenteDatos->CalibreComb1 ?? null,
            'CalibreComb12' => $fuenteDatos->CalibreComb12 ?? null,
            'FibraComb1' => $fuenteDatos->FibraComb1 ?? null,
            'CodColorComb1' => $fuenteDatos->CodColorC1 ?? $fuenteDatos->CodColorComb1 ?? null,
            'NombreCC1' => $fuenteDatos->NomColorC1 ?? $fuenteDatos->NombreCC1 ?? null,
            'CalibreComb2' => $fuenteDatos->CalibreComb2 ?? null,
            'CalibreComb22' => $fuenteDatos->CalibreComb22 ?? null,
            'FibraComb2' => $fuenteDatos->FibraComb2 ?? null,
            'CodColorComb2' => $fuenteDatos->CodColorC2 ?? $fuenteDatos->CodColorComb2 ?? null,
            'NombreCC2' => $fuenteDatos->NomColorC2 ?? $fuenteDatos->NombreCC2 ?? null,
            'CalibreComb3' => $fuenteDatos->CalibreComb3 ?? null,
            'CalibreComb32' => $fuenteDatos->CalibreComb32 ?? null,
            'FibraComb3' => $fuenteDatos->FibraComb3 ?? null,
            'CodColorComb3' => $fuenteDatos->CodColorC3 ?? $fuenteDatos->CodColorComb3 ?? null,
            'NombreCC3' => $fuenteDatos->NomColorC3 ?? $fuenteDatos->NombreCC3 ?? null,
            'CalibreComb4' => $fuenteDatos->CalibreComb4 ?? null,
            'CalibreComb42' => $fuenteDatos->CalibreComb42 ?? null,
            'FibraComb4' => $fuenteDatos->FibraComb4 ?? null,
            'CodColorComb4' => $fuenteDatos->CodColorC4 ?? $fuenteDatos->CodColorComb4 ?? null,
            'NombreCC4' => $fuenteDatos->NomColorC4 ?? $fuenteDatos->NombreCC4 ?? null,
            'CalibreComb5' => $fuenteDatos->CalibreComb5 ?? null,
            'CalibreComb52' => $fuenteDatos->CalibreComb52 ?? null,
            'FibraComb5' => $fuenteDatos->FibraComb5 ?? null,
            'CodColorComb5' => $fuenteDatos->CodColorC5 ?? $fuenteDatos->CodColorComb5 ?? null,
            'NombreCC5' => $fuenteDatos->NomColorC5 ?? $fuenteDatos->NombreCC5 ?? null,
        ];

        if ($fechaInicioProgramada) {
            $payloadPrograma['FechaInicio'] = $fechaInicioProgramada;
        }

        /** @var ReqProgramaTejido $programa */
        foreach ($programas as $programa) {
            foreach ($payloadPrograma as $column => $value) {
                if (!in_array($column, $columnasPrograma, true)) {
                    continue;
                }
                $programa->setAttribute($column, $value);
            }
            $programa->saveQuietly();
        }

        return ReqProgramaTejido::query()
            ->whereIn('Id', $programas->pluck('Id')->all())
            ->get();
    }

    private function ejecutarMovimientoYPonerEnProceso(
        ReqProgramaTejido $programaObjetivo,
        array $contextoDestino
    ): ?ReqProgramaTejido {
        if ($contextoDestino['esCambioTelar']) {
            return $this->movimientoService->moverRegistroConCambioTelarEnProceso(
                $programaObjetivo,
                $contextoDestino['salonDestino'],
                $contextoDestino['telarDestino']
            );
        }

        $this->movimientoService->moverRegistroEnProceso($programaObjetivo, true);
        return ReqProgramaTejido::query()->where('Id', $programaObjetivo->Id)->first();
    }

    private function enviarNotificacion(
        array $validated,
        ReqProgramaTejido $programa,
        string $codigoDibujo,
        array $contextoDestino,
        ?string $codigoDibujoAnterior = null
    ): void {
        $payload = $validated;
        $payload['NoTelarId'] = $contextoDestino['telarDestino'];

        if (!empty($contextoDestino['esCambioTelar'])) {
            $payload['CambioTelarActivo'] = true;
            $payload['NoTelarOrigen'] = $contextoDestino['telarOrigen'];
            $payload['SalonOrigen'] = $contextoDestino['salonOrigen'];
            $payload['NoTelarDestino'] = $contextoDestino['telarDestino'];
            $payload['SalonDestino'] = $contextoDestino['salonDestino'];
            if ($codigoDibujoAnterior) {
                $payload['CodigoDibujoAnterior'] = $codigoDibujoAnterior;
            }
        }

        $this->telegramService->enviarProcesoCompletado($payload, $programa, $codigoDibujo);
    }

    private function construirFechaInicioProgramada(?string $horaFinal): ?string
    {
        if (empty($horaFinal)) {
            return null;
        }

        try {
            $horaFinalCarbon = Carbon::createFromFormat('H:i', $horaFinal);
            return Carbon::today()
                ->setTimeFromTimeString($horaFinalCarbon->format('H:i'))
                ->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function normalizarLongitudLucha($longitudLuchaRaw): ?int
    {
        return $longitudLuchaRaw !== null && $longitudLuchaRaw !== ''
            ? (int) round((float) $longitudLuchaRaw)
            : null;
    }

    private function buildPasadasPayload(array $pasadasFromRequest, $ordenData): array
    {
        $pasadasPayload = [];
        if (count($pasadasFromRequest) > 0) {
            foreach ($pasadasFromRequest as $key => $value) {
                if ($value === null || $value === '') {
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

    private function normalizeCodigoDibujo(?string $value, ?string $telarId = null): string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->replaceMatches('/\.(?:JC5|JCS)$/i', '')
            ->toString();

        if ($normalized === '') {
            return '';
        }

        $suffix = $this->resolverSufijoCodigoPorTelar($telarId);
        return $suffix === '' ? $normalized : ($normalized . '.' . $suffix);
    }

    private function resolverSufijoCodigoPorTelar(?string $telarId): string
    {
        $n = (int) trim((string) ($telarId ?? ''));
        if ($n >= 300) {
            return '';
        }
        return 'JC5';
    }

    private function buildDetallePayloadFromOrden($ordenData): array
    {
        if (!$ordenData) {
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

    private function calcularMinutosCambio(?string $horaInicio, ?string $horaFinal): ?int
    {
        if (!$horaInicio || !$horaFinal) {
            return null;
        }
        try {
            $inicio = Carbon::createFromFormat('H:i', $horaInicio);
            $final = Carbon::createFromFormat('H:i', $horaFinal);
            if ($final->lt($inicio)) {
                $final->addDay();
            }
            return max(0, $inicio->diffInMinutes($final));
        } catch (Exception $e) {
            return null;
        }
    }
}
