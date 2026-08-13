<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Desglose de segundas por telar para el modal del KPI "2das".
 *
 * Solo se ejecuta al abrir el desglose: la consulta agregada no entra en el
 * render normal del tablero para no engordar el snapshot ni el JSON de máquinas
 * que se imprime en cada pulso.
 */
final readonly class CrudoDefectosService
{
    public function __construct(private CrudoReadRepository $repository) {}

    /**
     * @return array{
     *     columnas: list<string>,
     *     telares: list<array{telar: string, total: float, defectos: array<string, float>}>,
     *     porDefecto: list<array{defecto: string, total: float}>,
     *     total: float,
     *     recortados: int
     * }
     */
    public function porTelar(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $filas = $this->cachedRows($from, $to);

        $porDefecto = [];
        $porTelar = [];

        foreach ($filas as $fila) {
            $telar = trim((string) ($fila->TELAR ?? ''));
            $cantidad = is_numeric($fila->quantity ?? null) ? (float) $fila->quantity : 0.0;
            if ($telar === '' || $cantidad <= 0) {
                continue;
            }

            $defecto = trim((string) ($fila->description ?? '')) ?: (trim((string) ($fila->code ?? '')) ?: 'Sin descripción');

            $porDefecto[$defecto] = ($porDefecto[$defecto] ?? 0) + $cantidad;
            $porTelar[$telar][$defecto] = ($porTelar[$telar][$defecto] ?? 0) + $cantidad;
        }

        arsort($porDefecto);

        // ponytail: la tabla se recorta a los tipos con más piezas y el resto cae
        // en "Otros"; con 30 columnas no se lee nada. El aviso del recorte lo pinta
        // la vista, no se esconde.
        $tope = max(1, (int) config('crudo.defect_columns', 6));
        $principales = array_slice(array_keys($porDefecto), 0, $tope);
        $recortados = max(0, count($porDefecto) - count($principales));
        $columnas = $principales;
        if ($recortados > 0) {
            $columnas[] = 'Otros';
        }

        $telares = [];
        foreach ($porTelar as $telar => $defectos) {
            $fila = array_fill_keys($columnas, 0.0);
            $total = 0.0;

            foreach ($defectos as $defecto => $cantidad) {
                $columna = in_array($defecto, $principales, true) ? $defecto : 'Otros';
                $fila[$columna] = ($fila[$columna] ?? 0) + $cantidad;
                $total += $cantidad;
            }

            $telares[] = ['telar' => (string) $telar, 'total' => $total, 'defectos' => $fila];
        }

        // La tabla se lee buscando un telar concreto: va en orden 201, 202, 203…
        // La gráfica sí se ordena por total, que es lo que ahí se compara.
        $numero = static fn (array $fila): int => (int) preg_replace('/\D/', '', $fila['telar']);
        usort($telares, static fn (array $a, array $b): int => [$numero($a), $a['telar']] <=> [$numero($b), $b['telar']]);

        $ranking = array_map(
            static fn (string $defecto): array => ['defecto' => $defecto, 'total' => $porDefecto[$defecto]],
            array_keys($porDefecto),
        );

        return [
            'columnas' => $columnas,
            'telares' => $telares,
            'porDefecto' => $ranking,
            'total' => array_sum($porDefecto),
            'recortados' => $recortados,
            'maximo' => $telares === [] ? 0.0 : max(array_column($telares, 'total')),
        ];
    }

    /**
     * @return list<object>
     */
    private function cachedRows(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $seconds = max(0, (int) config('crudo.production_cache_seconds', 180));
        if ($seconds === 0) {
            return $this->repository->defectTotalsForRange($from, $to);
        }

        return Cache::remember(
            sprintf('crudo:defectos:%s:%s', $from->format('Y-m-d'), $to->format('Y-m-d')),
            now()->addSeconds($seconds),
            fn (): array => $this->repository->defectTotalsForRange($from, $to),
        );
    }
}
