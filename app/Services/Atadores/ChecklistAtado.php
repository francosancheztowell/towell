<?php

declare(strict_types=1);

namespace App\Services\Atadores;

use App\Models\Atadores\AtaActividadesModel;
use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Atadores\AtaMontadoActividadesModel;
use App\Models\Atadores\AtaMontadoMaquinasModel;
use Illuminate\Support\Collection;

/**
 * Filas base del checklist (máquinas y actividades del catálogo) de un atado Jacquard/SMIT.
 *
 * Antes cada ítem del catálogo costaba un exists() y un create(): 2 + 2·(máquinas + actividades)
 * consultas por folio. Aquí son dos lecturas por tabla y un insert por bloque, sin importar el
 * tamaño del catálogo (PERF-08, número en 19-03-SUMMARY.md). Idempotente: lo ya sembrado no se
 * vuelve a insertar.
 */
final class ChecklistAtado
{
    /** SQL Server acepta 2 100 parámetros por consulta. */
    private const MAX_PARAMETROS = 2100;

    public function sembrar(string $noJulio, string $noProduccion, mixed $turno): void
    {
        $this->sembrarMaquinas($noJulio, $noProduccion);
        $this->sembrarActividades($noJulio, $noProduccion, $turno);
    }

    public function sembrarMaquinas(string $noJulio, string $noProduccion): void
    {
        $existentes = $this->llaves(
            AtaMontadoMaquinasModel::query()->where('NoJulio', $noJulio)->where('NoProduccion', $noProduccion)->pluck('MaquinaId')
        );

        $filas = AtaMaquinasModel::query()->pluck('MaquinaId')
            ->reject(fn ($id) => isset($existentes[$this->llave($id)]))
            ->unique(fn ($id) => $this->llave($id))
            ->map(fn ($id) => [
                'NoJulio' => $noJulio,
                'NoProduccion' => $noProduccion,
                'MaquinaId' => $id,
                'Estado' => 0,
                'NomEmpleado' => null,
            ]);

        $this->insertar(AtaMontadoMaquinasModel::class, $filas);
    }

    /**
     * @param  Collection<int, AtaActividadesModel>|null  $catalogo  si el llamador ya lo leyó
     */
    public function sembrarActividades(string $noJulio, string $noProduccion, mixed $turno, ?Collection $catalogo = null): void
    {
        $existentes = $this->llaves(
            AtaMontadoActividadesModel::query()->where('NoJulio', $noJulio)->where('NoProduccion', $noProduccion)->pluck('ActividadId')
        );

        $catalogo ??= AtaActividadesModel::query()->get(['ActividadId', 'Porcentaje']);

        $filas = $catalogo
            ->reject(fn ($act) => isset($existentes[$this->llave($act->ActividadId)]))
            ->unique(fn ($act) => $this->llave($act->ActividadId))
            ->map(fn ($act) => [
                'NoJulio' => $noJulio,
                'NoProduccion' => $noProduccion,
                'ActividadId' => $act->ActividadId,
                'Porcentaje' => $act->Porcentaje,
                'Estado' => 0,
                'CveEmpl' => null,
                'NomEmpl' => null,
                'Turno' => $turno,
            ]);

        $this->insertar(AtaMontadoActividadesModel::class, $filas);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function insertar(string $modelo, Collection $filas): void
    {
        if ($filas->isEmpty()) {
            return;
        }

        $porBloque = intdiv(self::MAX_PARAMETROS, count($filas->first()));
        foreach ($filas->values()->chunk($porBloque) as $bloque) {
            $modelo::query()->insert($bloque->values()->all());
        }
    }

    /**
     * Como compara SQL Server con la collation de la planta (sin mayúsculas ni espacios finales),
     * para no sembrar dos veces "Atadora " y "atadora".
     *
     * @param  Collection<int, mixed>  $ids
     * @return array<string, true>
     */
    private function llaves(Collection $ids): array
    {
        return $ids->mapWithKeys(fn ($id) => [$this->llave($id) => true])->all();
    }

    private function llave(mixed $id): string
    {
        return mb_strtolower(rtrim((string) $id));
    }
}
