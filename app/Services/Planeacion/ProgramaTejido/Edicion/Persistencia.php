<?php

namespace App\Services\Planeacion\ProgramaTejido\Edicion;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Observers\ReqProgramaTejidoObserver;
use App\Services\Planeacion\ProgramaTejido\SecuenciaFechasTelar;
use Illuminate\Support\Facades\Log;

/**
 * Guardado de la edición inline con sus derivados (CatCodificados, fórmulas de rollos,
 * cascada, líneas, aplicación). saveQuietly + llamadas explícitas al observer.
 *
 * @internal Paso de EdicionProgramaTejido (aplicarCambios → recalcularDerivados → persistir).
 */
final class Persistencia
{
    /**
     * Guarda la cabecera y sus derivados (CatCodificados, fórmulas de rollos, cascada,
     * líneas, aplicación). Corre dentro de la transacción de quien llama.
     * $estricto = false es el comportamiento legacy (un fallo de aplicación en líneas se
     * registra y se confirma igual); true (v2) lo relanza para revertir todo.
     *
     * @param  array<string, bool>  $flags
     */
    public static function guardar(ReqProgramaTejido $registro, array $flags, string $fechaFinalAntes, bool $estricto): void
    {
        $fechaFinalCambiada = ((string) ($registro->FechaFinal ?? '') !== $fechaFinalAntes);

        $registro->saveQuietly();
        self::sincronizarTrasGuardar($registro);

        // Cascada solo si cambió FechaFinal y NO es Ultimo. cascade() relanza: la transacción
        // externa revierte saveQuietly() (mejor fallar limpio que dejar la cascada a medias).
        if ($fechaFinalCambiada && ! $registro->esUltimo()) {
            SecuenciaFechasTelar::cascade($registro);
        }

        $necesitaLineas = self::necesitaLineas($flags, $fechaFinalCambiada);
        if ($necesitaLineas) {
            // PT-02 (hallazgo 4): relanzando. Si las líneas no se regeneran, la transacción
            // revierte la edición.
            (new ReqProgramaTejidoObserver)->regenerateLinesFor($registro, relanzar: true);
        }

        if ($flags['afectaAplicacion'] && ! $necesitaLineas) {
            self::actualizarAplicacion($registro, $estricto);
        }
    }

    /**
     * saveQuietly() no dispara el observer: se sincroniza CatCodificados a mano y, si cambió
     * un input de la cadena de fórmulas, se recalculan Repeticiones/PzasRollo/MtsRollo/
     * TotalRollos/TotalPzas/SaldoMarbete en ReqProgramaTejido y CatCodificados.
     */
    private static function sincronizarTrasGuardar(ReqProgramaTejido $registro): void
    {
        try {
            $observer = new ReqProgramaTejidoObserver;
            $observer->sincronizarCatCodificados($registro);

            if (! $registro->wasChanged(ReqProgramaTejidoObserver::CAMPOS_RECALC_FORMULA)) {
                return;
            }
            if (! $observer->recalcularFormulasProduccion($registro)) {
                throw new \RuntimeException('No se pudieron persistir las fórmulas de rollos en ReqProgramaTejido.');
            }

            // Trabajar desde aquí con el valor confirmado en SQL Server, no con el modelo previo al UPDATE directo.
            $registro->refresh();
        } catch (\Throwable $e) {
            Log::warning('UpdateTejido: sincronizarCatCodificados/recalc error', ['id' => $registro->Id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private static function necesitaLineas(array $flags, bool $fechaFinalCambiada): bool
    {
        return $flags['afectaCalendario'] || $flags['afectaDuracion'] || $fechaFinalCambiada || $flags['fechaFinalManual'];
    }

    /** Aplicación cambió y las líneas no se regeneraron: Aplicacion = Factor × Kilos en las existentes. */
    private static function actualizarAplicacion(ReqProgramaTejido $registro, bool $estricto): void
    {
        try {
            self::actualizarAplicacionEnLineas($registro);
        } catch (\Throwable $e) {
            // Legacy: se registra y se confirma igual (WR-08). v2 ($estricto) revierte todo.
            if ($estricto) {
                throw $e;
            }
            Log::warning('UpdateTejido: actualizarAplicacionEnLineas error', ['id' => $registro->Id, 'error' => $e->getMessage()]);
        }
    }

    private static function actualizarAplicacionEnLineas(ReqProgramaTejido $programa): void
    {
        if (! $programa->Id || $programa->Id <= 0) {
            return;
        }

        $factorAplicacion = null;
        if ($programa->AplicacionId) {
            $aplicacion = ReqAplicaciones::where('AplicacionId', $programa->AplicacionId)->first();
            if ($aplicacion) {
                $factorAplicacion = (float) $aplicacion->Factor;
            }
        }

        $lineas = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)
            ->whereNotNull('Kilos')
            ->where('Kilos', '>', 0)
            ->get();

        foreach ($lineas as $linea) {
            $kilos = (float) ($linea->Kilos ?? 0);
            $linea->setAttribute('Aplicacion', ($factorAplicacion !== null && $kilos > 0) ? round($factorAplicacion * $kilos, 6) : null);
            $linea->saveQuietly();
        }
    }
}
