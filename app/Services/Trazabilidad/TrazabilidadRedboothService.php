<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Trazabilidad\TrazaProduccion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TrazabilidadRedboothService
{
    /**
     * @return array{flog:string,ordenes:array<int, array<string, mixed>>,primerVinculo:array<string, mixed>|null}
     */
    public function resolver(string $flog): array
    {
        $flog = trim($flog);
        $ordenesTraza = TrazaProduccion::query()
            ->where('Flogs', $flog)
            ->whereNotNull('Orden')
            ->where('Orden', '<>', '')
            ->distinct()
            ->pluck('Orden')
            ->map(static fn (mixed $orden): string => trim((string) $orden))
            ->filter()
            ->unique()
            ->values();

        $programasEncontrados = $this->programasPorOrdenes($ordenesTraza);
        $codificadosEncontrados = $this->codificadosPorOrdenes($ordenesTraza);

        // FlogsId queda como respaldo para Flogs que todavía no tienen movimientos
        // en TrazaProduccion. Si el cruce sí encuentra órdenes, se respeta exactamente
        // ese conjunto para coincidir con la tarjeta "Avance del pedido".
        if ($programasEncontrados->isEmpty() && $codificadosEncontrados->isEmpty()) {
            $programasEncontrados = ReqProgramaTejido::query()
                ->where('FlogsId', $flog)
                ->get(['Id', 'NoProduccion', 'FlogsId', 'IdRedbooth', 'NombreRedbooth']);
            $codificadosEncontrados = CatCodificados::query()
                ->where('FlogsId', $flog)
                ->get(['Id', 'OrdenTejido', 'FlogsId', 'IdRedbooth', 'NombreRedbooth']);
        }

        $programas = $programasEncontrados
            ->map(fn (ReqProgramaTejido $programa): array => $this->normalizarRegistro(
                'programa',
                (int) $programa->getKey(),
                $programa->NoProduccion,
                $programa->IdRedbooth,
                $programa->NombreRedbooth,
            ));

        $codificados = $codificadosEncontrados
            ->map(fn (CatCodificados $codificado): array => $this->normalizarRegistro(
                'catcodificados',
                (int) $codificado->getKey(),
                $codificado->OrdenTejido,
                $codificado->IdRedbooth,
                $codificado->NombreRedbooth,
            ));

        $registros = $programas->concat($codificados);
        // TrazaProduccion contiene órdenes de varias etapas. Solo se exponen las
        // que también existen realmente en Programa Tejido o CatCodificados.
        $ordenes = $registros->pluck('orden')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values();

        $ordenesResumidas = $ordenes
            ->map(fn (string $orden): array => $this->resumirOrden($orden, $registros))
            ->values()
            ->all();

        return [
            'flog' => $flog,
            'ordenes' => $ordenesResumidas,
            'primerVinculo' => $this->primerVinculo($ordenesResumidas),
        ];
    }

    /**
     * Asigna una tarea a todas las órdenes reconocidas del Flog únicamente cuando
     * ninguna de ellas tiene ya un vínculo. Así se evita sobrescribir una tarea
     * existente si dos usuarios operan el mismo Flog casi al mismo tiempo.
     *
     * @return array{asignado:bool,ordenesActualizadas:int,programasActualizados:int,catCodificadosActualizados:int,vinculoExistente:array<string, mixed>|null}
     */
    public function asignarTareaATodasSiNingunaTieneVinculo(
        string $flog,
        int $taskId,
        string $taskName,
    ): array {
        return DB::connection('sqlsrv')->transaction(function () use ($flog, $taskId, $taskName): array {
            $resolucion = $this->resolver($flog);
            if (is_array($resolucion['primerVinculo'])) {
                return [
                    'asignado' => false,
                    'ordenesActualizadas' => 0,
                    'programasActualizados' => 0,
                    'catCodificadosActualizados' => 0,
                    'vinculoExistente' => $resolucion['primerVinculo'],
                ];
            }

            $ordenes = collect($resolucion['ordenes'])
                ->pluck('orden')
                ->map(static fn (mixed $orden): string => trim((string) $orden))
                ->filter()
                ->unique()
                ->values();
            $programasActualizados = 0;
            $catCodificadosActualizados = 0;

            foreach ($ordenes->chunk(1000) as $lote) {
                $programasActualizados += ReqProgramaTejido::query()
                    ->whereIn('NoProduccion', $lote->all())
                    ->update([
                        'IdRedbooth' => $taskId,
                        'NombreRedbooth' => $taskName,
                    ]);
                $catCodificadosActualizados += CatCodificados::query()
                    ->whereIn('OrdenTejido', $lote->all())
                    ->update([
                        'IdRedbooth' => $taskId,
                        'NombreRedbooth' => $taskName,
                    ]);
            }

            return [
                'asignado' => true,
                'ordenesActualizadas' => $ordenes->count(),
                'programasActualizados' => $programasActualizados,
                'catCodificadosActualizados' => $catCodificadosActualizados,
                'vinculoExistente' => null,
            ];
        });
    }

    /** @return Collection<int, ReqProgramaTejido> */
    private function programasPorOrdenes(Collection $ordenesTraza): Collection
    {
        $columnas = ['Id', 'NoProduccion', 'FlogsId', 'IdRedbooth', 'NombreRedbooth'];
        $programas = collect();

        foreach ($ordenesTraza->chunk(1000) as $lote) {
            $programas = $programas->concat(
                ReqProgramaTejido::query()->whereIn('NoProduccion', $lote->all())->get($columnas)
            );
        }

        return $programas->unique(fn (ReqProgramaTejido $programa): int => (int) $programa->getKey())->values();
    }

    /** @return Collection<int, CatCodificados> */
    private function codificadosPorOrdenes(Collection $ordenesTraza): Collection
    {
        $columnas = ['Id', 'OrdenTejido', 'FlogsId', 'IdRedbooth', 'NombreRedbooth'];
        $codificados = collect();

        foreach ($ordenesTraza->chunk(1000) as $lote) {
            $codificados = $codificados->concat(
                CatCodificados::query()->whereIn('OrdenTejido', $lote->all())->get($columnas)
            );
        }

        return $codificados->unique(fn (CatCodificados $codificado): int => (int) $codificado->getKey())->values();
    }

    /** @return array{source:string,registroId:int,orden:string,idRedbooth:int|null,nombreRedbooth:string|null} */
    private function normalizarRegistro(
        string $source,
        int $registroId,
        mixed $orden,
        mixed $idRedbooth,
        mixed $nombreRedbooth,
    ): array {
        $id = (int) $idRedbooth;
        $nombre = trim((string) $nombreRedbooth);

        return [
            'source' => $source,
            'registroId' => $registroId,
            'orden' => trim((string) $orden),
            'idRedbooth' => $id > 0 ? $id : null,
            'nombreRedbooth' => $nombre !== '' ? $nombre : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $registros
     * @return array<string, mixed>
     */
    private function resumirOrden(string $orden, Collection $registros): array
    {
        $registrosOrden = $registros
            ->where('orden', $orden)
            ->sortBy([
                [fn (array $registro): int => $registro['source'] === 'programa' ? 0 : 1, 'asc'],
                ['registroId', 'desc'],
            ])
            ->values();
        $objetivo = $registrosOrden->first();
        $vinculos = $registrosOrden
            ->filter(fn (array $registro): bool => ! is_null($registro['idRedbooth']))
            ->unique(fn (array $registro): string => $registro['source'].'-'.$registro['registroId'].'-'.$registro['idRedbooth'])
            ->values()
            ->all();

        return [
            'orden' => $orden,
            'source' => $objetivo['source'] ?? null,
            'registroId' => $objetivo['registroId'] ?? null,
            'vinculos' => $vinculos,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ordenes
     * @return array<string, mixed>|null
     */
    private function primerVinculo(array $ordenes): ?array
    {
        foreach ($ordenes as $orden) {
            foreach ((array) ($orden['vinculos'] ?? []) as $vinculo) {
                if ((int) ($vinculo['idRedbooth'] ?? 0) <= 0) {
                    continue;
                }

                return array_merge($vinculo, ['orden' => (string) ($orden['orden'] ?? '')]);
            }
        }

        return null;
    }
}
