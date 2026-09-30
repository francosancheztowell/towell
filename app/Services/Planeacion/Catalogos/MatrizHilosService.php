<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Catalogos;

use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;

/**
 * Matriz de Hilos. Si un hilo en uso (FibraRizo de algún programa) cambia N1/N2/Calibre/Calibre2,
 * se recalcula MtsRizo de las líneas con rizo:
 *   MtsRizo = ((N1·Rizo·1000/0.59/2 + N2·Rizo·1000/0.59/2) / CuentaRizo) · 1.0162
 * (N1 y N2 caen a Calibre y Calibre2 si vienen vacíos).
 */
final class MatrizHilosService
{
    private const CAMPOS_CALCULO = ['N1', 'N2', 'Calibre', 'Calibre2'];

    private const LOTE = 2000;

    public function enUso(string $hilo): bool
    {
        return ReqProgramaTejido::where('FibraRizo', $hilo)->exists();
    }

    /** @param  array<string, mixed>  $nuevos */
    public function cambiaCalculo(ReqMatrizHilos $hilo, array $nuevos): bool
    {
        $numero = fn ($v) => $v === '' || $v === null ? null : (float) $v;
        foreach (self::CAMPOS_CALCULO as $campo) {
            if ($numero($hilo->{$campo}) !== $numero($nuevos[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Antes: una consulta de líneas y un UPDATE por línea de cada programa. Ahora: una consulta
     * de programas, una de líneas (por lotes de Id) y un UPDATE por valor distinto.
     */
    public function recalcularMtsRizo(ReqMatrizHilos $hilo): void
    {
        [$n1, $n2] = [$this->primero($hilo->N1, $hilo->Calibre), $this->primero($hilo->N2, $hilo->Calibre2)];
        if ($n1 === null || $n1 <= 0 || $n2 === null || $n2 <= 0) {
            return;
        }

        $cuentas = ReqProgramaTejido::where('FibraRizo', $hilo->Hilo)->pluck('CuentaRizo', 'Id')
            ->map(fn ($c) => (float) $c)
            ->filter(fn (float $c) => $c > 0);

        foreach ($this->lineasPorValor($cuentas->all(), $n1, $n2) as ['valor' => $valor, 'ids' => $ids]) {
            foreach (array_chunk($ids, self::LOTE) as $lote) {
                ReqProgramaTejidoLine::query()->whereKey($lote)->update(['MtsRizo' => $valor]);
            }
        }
    }

    /**
     * Líneas con rizo de los programas, agrupadas por su MtsRizo nuevo.
     *
     * @param  array<int|string, float>  $cuentas  CuentaRizo por Id de programa
     * @return array<string, array{valor: float, ids: list<int>}>
     */
    private function lineasPorValor(array $cuentas, float $n1, float $n2): array
    {
        $porValor = [];
        foreach (array_chunk(array_keys($cuentas), self::LOTE) as $programas) {
            $lineas = ReqProgramaTejidoLine::query()->whereIn('ProgramaId', $programas)
                ->whereNotNull('Rizo')->where('Rizo', '>', 0)->get(['Id', 'ProgramaId', 'Rizo']);
            foreach ($lineas as $linea) {
                $mts = self::mtsRizo($n1, $n2, (float) $linea->getAttribute('Rizo'), $cuentas[$linea->getAttribute('ProgramaId')]);
                if ($mts <= 0) {
                    continue;
                }
                // Llave con todos los dígitos: dos líneas comparten UPDATE solo si el valor es idéntico.
                $llave = sprintf('%.17g', $mts);
                $porValor[$llave] ??= ['valor' => $mts, 'ids' => []];
                $porValor[$llave]['ids'][] = (int) $linea->getKey();
            }
        }

        return $porValor;
    }

    public static function mtsRizo(float $n1, float $n2, float $rizo, float $cuentaRizo): float
    {
        $valorRizo1 = (($n1 * ($rizo * 1000)) / 0.59) / 2;
        $valorRizo2 = (($n2 * ($rizo * 1000)) / 0.59) / 2;

        return (($valorRizo1 + $valorRizo2) / $cuentaRizo) * 1.0162;
    }

    private function primero(mixed ...$valores): ?float
    {
        foreach ($valores as $v) {
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return (float) $v;
            }
        }

        return null;
    }
}
