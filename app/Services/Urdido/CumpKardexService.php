<?php

declare(strict_types=1);

namespace App\Services\Urdido;

use App\Models\Inventario\InvKardexMPConsol;
use App\Models\Urdido\Urdbom;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Support\ActualizacionPorId;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Costo de materia prima de urdido.
 *
 * cump: costo unitario de cada material de Urdbom, del kardex consolidado de MP. La máquina del folio
 * en UrdProgramaUrdido elige la columna (Mc Coy 1 → MC1CU, …, Karl Mayer → KMCU) y el material se busca
 * por ITEMID/CONFIGID/INVENTCOLORID = Calibre/Config/Color.
 * Periodo: el mes en que se urdió (Fecha del primer julio en UrdProduccionUrdido; sin producción, FechaProg),
 * porque el kardex es consumo y se consume al urdir, no al programar. Si falta ese mes, el último anterior.
 * Con $porFecha=false (o sin fecha), el último mes del kardex; con $anterior=false solo vale el mes exacto. Sin renglón, cump no se toca.
 * Si la máquina está en 0 o NULL (no consumió ese material ese mes según AX), se usa EXISTENCIACU
 * del mismo renglón: el costo promedio del inventario ese mes ($existencia, encendido por default).
 *
 * Importes (lo producido, no lo programado), con tasa del folio = Σ cump × Porcentaje/100 ($/kg de la mezcla):
 * - UrdProduccionUrdido.ImporteMP de cada julio = tasa × KgNeto de esa línea.
 * - Urdbom.importe = cump × Porcentaje/100 × Σ KgNeto del folio; así Σ importe = Σ ImporteMP por folio.
 */
class CumpKardexService
{
    private const COLUMNA = ['MCCOY1' => 'MC1CU', 'MCCOY2' => 'MC2CU', 'MCCOY3' => 'MC3CU', 'KARLMAYER' => 'KMCU'];

    /** Lo único que este servicio escribe. */
    private const ESCRIBIBLES = ['Urdbom' => ['cump', 'importe'], 'UrdProduccionUrdido' => ['ImporteMP']];

    /**
     * Recalcula y guarda solo lo que cambia.
     *
     * @param  array<int, string>|null  $folios  null = todos
     * @return array{materiales: int, produccion: int} filas actualizadas
     */
    public function actualizar(?array $folios = null, bool $porFecha = true, bool $anterior = true, bool $existencia = true): array
    {
        $c = $this->calcular($folios, $porFecha, $anterior, $existencia);

        DB::connection('sqlsrv')->transaction(function () use ($c) {
            ActualizacionPorId::ejecutar('Urdbom', $c['materiales'], self::ESCRIBIBLES['Urdbom']);
            ActualizacionPorId::ejecutar('UrdProduccionUrdido', array_map(fn ($v) => ['ImporteMP' => $v], $c['produccion']), self::ESCRIBIBLES['UrdProduccionUrdido']);
        });

        return ['materiales' => count($c['materiales']), 'produccion' => count($c['produccion'])];
    }

    /**
     * Solo lectura: los valores nuevos de lo que cambiaría.
     *
     * @param  array<int, string>|null  $folios
     * @return array{materiales: array<int, array{cump: float, importe: float}>, produccion: array<int, float>}
     */
    public function calcular(?array $folios = null, bool $porFecha = true, bool $anterior = true, bool $existencia = true): array
    {
        $materiales = Urdbom::query()
            ->when($folios !== null, fn ($q) => $q->whereIn('Folio', $folios))
            ->get(['Id', 'Folio', 'Calibre', 'Config', 'Color', 'Porcentaje', 'cump', 'importe']);
        $lineas = UrdProduccionUrdido::query()
            ->whereIn('Folio', $materiales->pluck('Folio')->unique()->values()->all())
            ->get(['Id', 'Folio', 'Fecha', 'KgNeto', 'ImporteMP']);
        $kgFolio = $lineas->groupBy('Folio')->map(fn ($l) => $l->sum(fn ($x) => (float) $x->KgNeto));
        // ponytail: un mes por folio (el del primer julio); los pocos folios que cruzan de mes toman el primero.
        $fechaProd = $lineas->whereNotNull('Fecha')->groupBy('Folio')->map(fn ($l) => $l->min('Fecha'));
        $cumps = $this->cumps($materiales, $porFecha ? $fechaProd->all() : null, $anterior, $existencia);

        $tasa = [];
        $cambios = ['materiales' => [], 'produccion' => []];
        foreach ($materiales as $m) {
            $parte = $cumps[$m->Id] * (float) $m->Porcentaje / 100;
            $tasa[$m->Folio] = ($tasa[$m->Folio] ?? 0) + $parte;
            $nuevo = ['cump' => $cumps[$m->Id], 'importe' => round($parte * ($kgFolio[$m->Folio] ?? 0), 4)];
            if ($nuevo !== ['cump' => round((float) $m->cump, 4), 'importe' => round((float) $m->importe, 4)]) {
                $cambios['materiales'][$m->Id] = $nuevo;
            }
        }
        foreach ($lineas as $l) {
            $importe = round(($tasa[$l->Folio] ?? 0) * (float) $l->KgNeto, 4);
            if ($importe !== round((float) $l->ImporteMP, 4)) {
                $cambios['produccion'][$l->Id] = $importe;
            }
        }

        return $cambios;
    }

    /** @return Collection<int, InvKardexMPConsol> */
    public function kardex(): Collection
    {
        return InvKardexMPConsol::entero()
            ->get(['ITEMID', 'CONFIGID', 'INVENTCOLORID', 'MC1CU', 'MC2CU', 'MC3CU', 'KMCU', 'EXISTENCIACU', 'YEARDATE', 'MONTHDATE']);
    }

    /**
     * cump de cada material: el del kardex o, si no hay renglón/máquina, el que ya tenía.
     *
     * @param  Collection<int, Urdbom>  $materiales
     * @param  array<string, mixed>|null  $fechaProd  Folio => fecha del primer julio; null = último mes del kardex
     * @return array<int, float> Id => cump
     */
    private function cumps(Collection $materiales, ?array $fechaProd, bool $anterior, bool $existencia): array
    {
        if ($materiales->isEmpty()) {
            return [];
        }
        $ordenes = UrdProgramaUrdido::query()
            ->whereIn('Folio', $materiales->pluck('Folio')->unique()->values()->all())
            ->get(['Folio', 'MaquinaId', 'FechaProg'])
            ->keyBy('Folio');
        $kardex = $this->kardex()->groupBy(fn ($k) => self::clave($k->ITEMID, $k->CONFIGID, $k->INVENTCOLORID));

        $cumps = [];
        foreach ($materiales as $m) {
            $orden = $ordenes->get($m->Folio);
            $columna = self::COLUMNA[strtoupper(str_replace(' ', '', (string) $orden?->MaquinaId))] ?? null;
            $fila = $columna === null ? null
                : self::delPeriodo($kardex->get(self::clave($m->Calibre, $m->Config, $m->Color)), $fechaProd === null ? null : ($fechaProd[$m->Folio] ?? $orden->FechaProg), $anterior);
            $cumps[$m->Id] = round($fila === null ? (float) $m->cump : self::costo($fila, $columna, $existencia), 4);
        }

        return $cumps;
    }

    /** Costo de la máquina; en 0/NULL, el promedio del inventario del mes (EXISTENCIACU) si $existencia. */
    private static function costo(object $fila, string $columna, bool $existencia): float
    {
        $cu = (float) $fila->{$columna};

        return $cu == 0 && $existencia ? (float) $fila->EXISTENCIACU : $cu;
    }

    /** Renglón del kardex del mes de $fecha (o el último anterior si $anterior); sin fecha, el último. */
    private static function delPeriodo(?Collection $filas, mixed $fecha, bool $anterior): ?object
    {
        $tope = $fecha ? (int) date('Ym', strtotime((string) $fecha)) : PHP_INT_MAX;

        return ($filas ?? collect())
            ->filter(fn ($k) => $anterior || $tope === PHP_INT_MAX
                ? $k->YEARDATE * 100 + $k->MONTHDATE <= $tope
                : $k->YEARDATE * 100 + $k->MONTHDATE === $tope)
            ->sortByDesc(fn ($k) => $k->YEARDATE * 100 + $k->MONTHDATE)
            ->first();
    }

    private static function clave(?string $item, ?string $config, ?string $color): string
    {
        return strtoupper(trim((string) $item).'|'.trim((string) $config).'|'.trim((string) $color));
    }
}
