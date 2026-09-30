<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Catalogos;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;

/**
 * Catálogo de Aplicaciones. Al cambiar el Factor, las líneas diarias de los programas que usan
 * la aplicación se recalculan: Aplicacion = round(Factor × Kilos, 6) (misma precisión que el observer).
 */
final class AplicacionesService
{
    /** Filas por UPDATE … WHERE Id IN (…): SQL Server admite 2 100 parámetros. */
    private const LOTE = 2000;

    /** @param  array{AplicacionId: string, Nombre: string, Factor?: float|int|string|null}  $datos */
    public function actualizar(ReqAplicaciones $aplicacion, array $datos): void
    {
        $factorAnterior = $aplicacion->Factor;
        $clave = $aplicacion->AplicacionId;
        $factor = isset($datos['Factor']) && $datos['Factor'] !== '' ? (float) $datos['Factor'] : null;

        $aplicacion->update(['AplicacionId' => $datos['AplicacionId'], 'Nombre' => $datos['Nombre'], 'Factor' => $factor]);

        if (abs((float) $factorAnterior - (float) $factor) > 0.0001) {
            $this->recalcularLineas($clave, $factor);
        }
    }

    /**
     * Una consulta para leer las líneas y un UPDATE por valor distinto (antes: una consulta de
     * programas, otra de líneas y un UPDATE por línea).
     */
    public function recalcularLineas(string $aplicacionId, ?float $factor): void
    {
        $lineas = ReqProgramaTejidoLine::query()
            ->whereIn('ProgramaId', ReqProgramaTejido::query()->select('Id')->where('AplicacionId', $aplicacionId))
            ->whereNotNull('Kilos')
            ->where('Kilos', '>', 0)
            ->get(['Id', 'Kilos']);

        // La columna es VARCHAR: se guarda el mismo texto que ponía el cast 'string' del modelo.
        $porValor = $lineas->groupBy(
            fn (ReqProgramaTejidoLine $l) => $factor === null ? '' : (string) round($factor * (float) $l->getAttribute('Kilos'), 6),
        );
        foreach ($porValor as $valor => $grupo) {
            foreach ($grupo->pluck('Id')->chunk(self::LOTE) as $ids) {
                ReqProgramaTejidoLine::query()->whereKey($ids->all())->update(['Aplicacion' => $valor === '' ? null : (string) $valor]);
            }
        }
    }

    public function enUso(string $aplicacionId): bool
    {
        return ReqProgramaTejido::where('AplicacionId', $aplicacionId)->exists()
            || ReqProgramaTejidoLine::where('Aplicacion', $aplicacionId)->exists();
    }
}
