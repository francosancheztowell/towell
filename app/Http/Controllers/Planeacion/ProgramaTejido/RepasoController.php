<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DuplicarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RepasoController extends Controller
{
    public function createrepaso(Request $request)
    {
        $request->validate([
            'telar' => 'required|string',
            'ancho' => 'required|numeric|min:0',
            'hilo' => 'required|string|max:50',
            'calibre' => 'nullable|numeric|min:0',
        ]);

        $telarRaw = trim((string) $request->input('telar'));
        $partes = explode('|', $telarRaw, 2);
        $noTelar = $partes[1] ?? $partes[0] ?? '';

        if (empty($noTelar) || ! is_numeric(trim($noTelar))) {
            throw ValidationException::withMessages(['telar' => 'Telar inválido']);
        }

        $noTelarId = (string) trim($noTelar);
        $salon = $this->derivarSalon($noTelarId);
        $ancho = (float) $request->input('ancho');
        $hilo = trim((string) $request->input('hilo'));
        $calibre = $request->filled('calibre') ? (float) $request->input('calibre') : null;

        $anterior = $this->obtenerRegistroAnterior($salon, $noTelarId);

        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        DB::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            if ($anterior) {
                $anterior->Ultimo = '0';
                $anterior->saveQuietly();
            }

            $nuevo = $this->crearRegistroRepaso($salon, $noTelarId, $ancho, $hilo, $calibre, $anterior);
            $nuevo->save();

            $this->aplicarFormulas($nuevo);

            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::commit();

            // Durante save()/saveQuietly() el dispatcher estaba desactivado: el observer no generó líneas diarias.
            // Carga explícita: en sqlsrv a veces fresh()/find por Id falla si la clave no quedó en memoria o hay triggers;
            // el INSERT ya está committed, así que resolvemos por Id y, si hace falta, por telar+posición+REPASO1.
            $registro = $this->resolverRegistroRepasoTrasCommit($nuevo, $salon, $noTelarId);

            if ($registro) {
                try {
                    app(ReqProgramaTejidoObserver::class)->saved($registro);
                } catch (\Throwable $e) {
                    Log::warning('RepasoController::createrepaso observer', [
                        'id' => $registro->Id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $idObs = $registro->getKey();
                $registro = ($idObs !== null && $idObs !== '')
                    ? ReqProgramaTejido::query()->useWritePdo()->find($idObs)
                    : null;
                if (! $registro) {
                    $registro = $this->resolverRegistroRepasoTrasCommit($nuevo, $salon, $noTelarId);
                }
            }

            if (! $registro) {
                Log::warning('RepasoController::createrepaso sin registro tras commit', [
                    'salon' => $salon,
                    'noTelarId' => $noTelarId,
                    'nuevo_key' => $nuevo->getKey(),
                    'nuevo_posicion' => $nuevo->Posicion ?? null,
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo recuperar el repaso creado.',
                ], 500);
            }

            $registroArray = $registro->toArray();

            return response()->json([
                'ok' => true,
                'message' => 'Repaso creado correctamente',
                'id' => $registro->Id,
                'registros_ids' => [$registro->Id],
                'registro' => $registroArray,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::setEventDispatcher($dispatcher ?? null);
            Log::error('RepasoController::createrepaso', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'ok' => false,
                'message' => 'Error al crear repaso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vuelve a leer el registro recién insertado tras commit (evita null de fresh() si Id no quedó en el modelo).
     */
    private function resolverRegistroRepasoTrasCommit(ReqProgramaTejido $nuevo, string $salon, string $noTelarId): ?ReqProgramaTejido
    {
        $id = $nuevo->getKey();
        if ($id !== null && $id !== '' && (int) $id > 0) {
            $found = ReqProgramaTejido::query()->useWritePdo()->find($id);
            if ($found) {
                return $found;
            }
        }

        $posicion = $nuevo->Posicion ?? null;

        return ReqProgramaTejido::query()
            ->useWritePdo()
            ->where('SalonTejidoId', $salon)
            ->where('NoTelarId', $noTelarId)
            ->where('NombreProducto', 'REPASO1')
            ->when($posicion !== null && $posicion !== '', fn ($q) => $q->where('Posicion', $posicion))
            ->orderByDesc('Id')
            ->first();
    }

    private function derivarSalon(string $noTelarId): string
    {
        $num = (int) preg_replace('/\D/', '', $noTelarId);

        return $num >= 299 ? 'SMIT' : 'JACQUARD';
    }

    private function obtenerRegistroAnterior(string $salon, string $noTelarId): ?ReqProgramaTejido
    {
        return ReqProgramaTejido::query()
            ->where('SalonTejidoId', $salon)
            ->where('NoTelarId', $noTelarId)
            ->orderByDesc('Posicion')
            ->orderByDesc('FechaFinal')
            ->orderByDesc('Id')
            ->first();
    }

    private function crearRegistroRepaso(
        string $salon,
        string $noTelarId,
        float $ancho,
        string $hilo,
        ?float $calibre,
        ?ReqProgramaTejido $anterior
    ): ReqProgramaTejido {
        $maquina = TejidoHelpers::construirMaquinaConSalon(null, $salon, $noTelarId);
        $posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($salon, $noTelarId);
        $calendario = $anterior?->CalendarioId;
        $cuentaRizo = $anterior?->CuentaRizo;
        $calibrePie = $anterior?->CalibrePie ?? $anterior?->CalibrePie2 ?? null;
        $calibrePie2 = $anterior?->CalibrePie2 ?? $anterior?->CalibrePie ?? null;
        $cambioHilo = $anterior && trim((string) ($anterior->FibraRizo ?? '')) !== $hilo ? '1' : '0';

        [$eficiencia, $velocidad] = $this->obtenerEficienciaVelocidad($salon, $noTelarId, $hilo);

        if ($anterior) {
            $fechaInicio = Carbon::parse($anterior->FechaFinal ?? $anterior->FechaInicio);
        } else {
            $fechaInicio = Carbon::now();
        }

        $snap = ! empty($calendario)
            ? TejidoHelpers::snapInicioAlCalendario($calendario, $fechaInicio)
            : null;
        if ($snap) {
            $fechaInicio = $snap;
        }

        $fechaFinal = $fechaInicio->copy()->addHours(12);

        $registro = new ReqProgramaTejido([
            'CreatedAt' => Carbon::now(),
            'UpdatedAt' => Carbon::now(),
            'EnProceso' => false,
            'CuentaRizo' => $cuentaRizo,
            'CalibreRizo' => $calibre,
            'SalonTejidoId' => $salon,
            'NoTelarId' => $noTelarId,
            'Ultimo' => '1',
            'CambioHilo' => $cambioHilo,
            'Maquina' => $maquina,
            'Ancho' => $ancho,
            'EficienciaSTD' => $eficiencia ?? 0.78,
            'VelocidadSTD' => $velocidad ?? 280,
            'FibraRizo' => $hilo,
            'CalibrePie' => $calibrePie,
            'CalibrePie2' => $calibrePie2,
            'CalendarioId' => $calendario,
            'TamanoClave' => null,
            'NoExisteBase' => 'REPASO1',
            'ItemId' => null,
            'InventSizeId' => null,
            'Rasurado' => null,
            'NombreProducto' => 'REPASO1',
            'TotalPedido' => 1,
            'Produccion' => null,
            'SaldoPedido' => 1,
            'SaldoMarbete' => null,
            'ProgramarProd' => null,
            'NoProduccion' => null,
            'Programado' => null,
            'Posicion' => $posicion,
            'Prioridad' => null,
            'FechaInicio' => $fechaInicio->format('Y-m-d H:i:s'),
            'FechaFinal' => $fechaFinal->format('Y-m-d H:i:s'),
        ]);

        return $registro;
    }

    private function obtenerEficienciaVelocidad(string $salon, string $noTelar, string $fibraId): array
    {
        $densidad = 'Normal';
        $eficiencia = TejidoHelpers::buscarStdEficiencia($salon, $noTelar, $fibraId, $densidad);
        $velocidad = TejidoHelpers::buscarStdVelocidad($salon, $noTelar, $fibraId, $densidad);

        return [
            $eficiencia?->Eficiencia,
            $velocidad?->Velocidad,
        ];
    }

    private function aplicarFormulas(ReqProgramaTejido $registro): void
    {
        $formulas = DuplicarTejido::calcularFormulasEficiencia($registro);
        foreach ($formulas as $campo => $valor) {
            if ($valor !== null && in_array($campo, $registro->getFillable())) {
                $registro->{$campo} = $valor;
            }
        }
        $registro->saveQuietly();
    }
}
