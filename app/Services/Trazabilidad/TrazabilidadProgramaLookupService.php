<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Collection;

/**
 * Catálogo de programas compartido durante una petición.
 *
 * Livewire construye el resumen y la tabla de avance en el mismo render. Ambos
 * consumidores solicitan las mismas órdenes, por lo que este servicio conserva
 * los resultados en memoria y consulta únicamente las órdenes aún no cargadas.
 */
final class TrazabilidadProgramaLookupService
{
    /** @var Collection<string, object> */
    private Collection $programas;

    /** @var array<string, true> */
    private array $ordenesCargadas = [];

    public function __construct()
    {
        $this->programas = collect();
    }

    /**
     * Devuelve un programa canónico por cada clave solicitada.
     *
     * La normalización evita enviar al IN variantes como z125691 y Z125691.
     *
     * @param  Collection<int, mixed>  $ordenes
     * @return Collection<string, object>
     */
    public function forOrders(Collection $ordenes): Collection
    {
        $solicitadas = $ordenes
            ->map(static fn (mixed $orden): string => trim((string) $orden))
            ->filter()
            ->unique()
            ->values();

        $pendientes = $solicitadas
            ->unique(fn (string $orden): string => $this->normalize($orden))
            ->mapWithKeys(fn (string $orden): array => [$this->normalize($orden) => $orden])
            ->reject(fn (string $orden, string $normalizada): bool => isset($this->ordenesCargadas[$normalizada]));

        foreach ($pendientes->values()->chunk(1000) as $lote) {
            $filas = ReqProgramaTejido::query()
                ->whereIn('NoProduccion', $lote->all())
                ->orderByDesc('EnProceso')
                ->orderByDesc('Id')
                ->get([
                    'NoProduccion', 'NoTelarId', 'SalonTejidoId',
                    'TotalPedido', 'Produccion', 'SaldoPedido', 'TotalPzas',
                    'StdDia', 'ProdKgDia', 'EnProceso',
                    'FechaInicio', 'FechaFinal',
                    'OrdCompartida', 'OrdCompartidaLider',
                ]);

            foreach ($filas as $fila) {
                $normalizada = $this->normalize($fila->NoProduccion ?? '');
                if ($normalizada !== '' && ! $this->programas->has($normalizada)) {
                    $this->programas->put($normalizada, $fila);
                }
            }
        }

        foreach ($pendientes->keys() as $normalizada) {
            $this->ordenesCargadas[$normalizada] = true;
        }

        return $solicitadas
            ->mapWithKeys(function (string $orden): array {
                $programa = $this->programas->get($this->normalize($orden));

                return $programa ? [$orden => $programa] : [];
            });
    }

    private function normalize(mixed $orden): string
    {
        return mb_strtoupper(trim((string) $orden));
    }
}
