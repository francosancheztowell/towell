<?php

namespace App\Repositories\Kardex;

use App\Models\Inventario\Kardex\InvKardexAEPTEJID;
use App\Models\Inventario\Kardex\InvKardexAJULTELA;
use App\Models\Inventario\Kardex\InvKardexAJULURD;
use App\Models\Inventario\Kardex\InvKardexCrudo;
use App\Models\Inventario\Kardex\InvKardexURDID;
use App\Models\Inventario\Kardex\KardexLibro;

/** Lectura de las hojas Kdx del libro (ProdTowel del .24). Cada hoja trae < 300 renglones por mes. */
class KardexLibroRepository
{
    /** @var array<string, class-string<KardexLibro>> */
    private const HOJAS = [
        'urdido' => InvKardexURDID::class,
        'julurd' => InvKardexAJULURD::class,
        'jultela' => InvKardexAJULTELA::class,
        'tejido' => InvKardexAEPTEJID::class,
        'crudo' => InvKardexCrudo::class,
    ];

    /**
     * Meses con libro cargado, del más reciente al más antiguo. URDID es la primera hoja del tramo.
     *
     * @return list<array{anio: int, mes: int}>
     */
    public function periodos(): array
    {
        return InvKardexURDID::query()
            ->select(['YEARDATE', 'MONTHDATE'])
            ->distinct()
            ->orderByDesc('YEARDATE')
            ->orderByDesc('MONTHDATE')
            ->get()
            ->map(fn (InvKardexURDID $r): array => ['anio' => $r->YEARDATE, 'mes' => $r->MONTHDATE])
            ->values()
            ->all();
    }

    /** @return array{urdido: list<array<string, mixed>>, julurd: list<array<string, mixed>>, jultela: list<array<string, mixed>>, tejido: list<array<string, mixed>>, crudo: list<array<string, mixed>>} */
    public function filas(int $anio, int $mes): array
    {
        $filas = [];
        foreach (self::HOJAS as $clave => $modelo) {
            $filas[$clave] = $modelo::query()->periodo($anio, $mes)->get()->map->getAttributes()->values()->all();
        }

        /** @var array{urdido: list<array<string, mixed>>, julurd: list<array<string, mixed>>, jultela: list<array<string, mixed>>, tejido: list<array<string, mixed>>, crudo: list<array<string, mixed>>} $filas */
        return $filas;
    }
}
