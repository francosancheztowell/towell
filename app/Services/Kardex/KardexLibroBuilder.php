<?php

namespace App\Services\Kardex;

use Closure;

/**
 * Arma el libro del Kardex (réplica de la hoja REs) a partir de los renglones de las hojas Kdx
 * de un mes. Sin base de datos: recibe arreglos de atributos y devuelve la forma de
 * resources/js/modulos/kardex/libro.ts (ver KardexLibroBuilderTest).
 *
 * Columnas netas verificadas contra el libro de agosto 2026 (al centavo):
 *  - URDID: Traspasos = ENTTRASPASOS − SALTRASPASOS.
 *  - AJULTELA: Otros = SALOTROS − ENTRADASOTROS.
 *  - AEPTEJID: Otros = SALOTROS − (EXISTENCIA − INICIAL − ENTURDIDO − ENTENGOM) (los traspasos internos netean).
 * Existencia = inicial + entradas mostradas; el libro calcula Saldo = Existencia − salidas − ajuste.
 */
final class KardexLibroBuilder
{
    /** @var array<int, string> */
    private const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /** Entradas a crudo por tipo de telar: columna del libro → [kg, piezas] en InvKardexCrudo. */
    private const CRUDO_TELARES = [
        'Ent Jacquard Kg' => ['ENTJACQUARDKG', 'ENTJACQUARDPZA'],
        'Ent Itema Kg' => ['ENTITEMAKG', 'ENTITEMAPZA'],
        'Ent Smith Kg' => ['ENTSMITHKG', 'ENTSMITHPZA'],
        'Ent Karl Mayer Kg' => ['ENTKARLMAYERKG', 'ENTKARLMAYERPZA'],
    ];

    /**
     * @param  array{urdido: list<array<string, mixed>>, julurd: list<array<string, mixed>>, jultela: list<array<string, mixed>>, tejido: list<array<string, mixed>>, crudo: list<array<string, mixed>>}  $filas
     * @return array<string, mixed>
     */
    public function construir(int $anio, int $mes, array $filas): array
    {
        $secciones = [];
        $articulos = [];
        foreach ($this->definiciones() as $clave => $def) {
            $renglones = $filas[$clave];
            $secciones[] = $this->seccion($clave, $def, $renglones);
            $articulos[$def['almacen']] = $this->articulos($def, $renglones);
        }

        $sum = fn (string $clave, string ...$columnas): float => round(array_sum(array_map(
            fn (array $r): float => array_sum(array_map(fn (string $c): float => self::num($r, $c), $columnas)),
            $filas[$clave],
        )), 2);

        $crudoKg = [];
        $crudoPz = [];
        foreach (self::CRUDO_TELARES as $etiqueta => [$kg, $pz]) {
            $crudoKg[$etiqueta] = $sum('crudo', $kg);
            $crudoPz[$etiqueta] = $sum('crudo', $pz);
        }

        $ultimoDia = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        return [
            'periodo' => [
                'anio' => $anio,
                'mes' => $mes,
                'nombre' => self::MESES[$mes].' '.$anio,
                'corte' => sprintf('%02d/%02d/%04d', $ultimoDia, $mes, $anio),
            ],
            'kpis' => [
                'julUrd' => $sum('julurd', 'ENTMC1KG', 'ENTMC2KG', 'ENTMC3KG', 'ENTKM1KG'),
                'julTela' => $sum('jultela', 'ENTWP1KG', 'ENTWP2KG', 'ENTWP3KG'),
                'crudoKg' => round(array_sum($crudoKg), 2),
                'crudoPz' => round(array_sum($crudoPz), 2),
            ],
            'secciones' => $secciones,
            'articulos' => $articulos,
            'crudo' => ['referenciaKdx' => $crudoKg],
            'flujo' => [
                'nodos' => [
                    'A-EP-URDID' => ['inicial' => $sum('urdido', 'INICIALKG'), 'final' => $sum('urdido', 'SALDOKG')],
                    'A-JUL/URD' => ['inicial' => $sum('julurd', 'INICIALKG'), 'final' => $sum('julurd', 'SALDOKG')],
                    'A-JUL/TELA' => ['inicial' => $sum('jultela', 'INICIALKG'), 'final' => $sum('jultela', 'SALDOKG')],
                    'A-EP-TEJID' => ['inicial' => $sum('tejido', 'INICIALKG'), 'final' => $sum('tejido', 'SALDOKG')],
                ],
                'aristas' => [
                    'mp-urdid' => $sum('urdido', 'ENTRADAMPKG'),
                    'mpbb-urdid' => $sum('urdido', 'ENTMPBBKG'),
                    'urdid-julurd' => $sum('urdido', 'SALPRODMC1KG', 'SALPRODMC2KG', 'SALPRODMC3KG', 'SALPRODKMKG'),
                    'julurd-jultela' => $sum('julurd', 'SALWP2', 'SALWP3'),
                    'julurd-eptejid' => $sum('julurd', 'SALKM1KG'),
                    'jultela-eptejid' => $sum('jultela', 'JAQKG', 'ITEMAKG'),
                    'eptejid-crudo' => $sum('tejido', 'SALJACQKG', 'SALITEMAKG', 'SALSMITHKG', 'SALKMKG'),
                ],
                'crudoKg' => round(array_sum($crudoKg), 2),
            ],
        ];
    }

    /**
     * Columnas de cada sección en el orden del libro. Tipo: ini | ent | sal | aj | saldo; la
     * Existencia se calcula (inicial + entradas) y va antes de la primera salida.
     *
     * @return array<string, array{titulo: string, almacen: string, agrupar: string, etiqueta: string, columnas: list<array{0: string, 1: string, 2: Closure(array<string, mixed>): float}>}>
     */
    private function definiciones(): array
    {
        return [
            'urdido' => $this->urdido(),
            'julurd' => $this->julurd(),
            'jultela' => $this->jultela(),
            'tejido' => $this->tejido(),
        ];
    }

    /** Columna tal cual. */
    private static function col(string $columna): Closure
    {
        return fn (array $r): float => self::num($r, $columna);
    }

    /** Diferencia de dos columnas (traspasos u otros netos). */
    private static function resta(string $a, string $b): Closure
    {
        return fn (array $r): float => self::num($r, $a) - self::num($r, $b);
    }

    /** @return array{0: string, 1: string, 2: Closure} */
    private static function inicial(): array
    {
        return ['Kilos Iniciales', 'ini', self::col('INICIALKG')];
    }

    /** @return array{0: string, 1: string, 2: Closure} */
    private static function ajuste(): array
    {
        return ['AjusteKg', 'aj', self::col('AJUSTESKG')];
    }

    /** @return array<string, mixed> */
    private function urdido(): array
    {
        return [
            'titulo' => 'ALMACÉN PTE URDIDO',
            'almacen' => 'A-EP-URDID',
            'agrupar' => 'WMSLOCATIONID',
            'etiqueta' => 'Localidad',
            'columnas' => [
                self::inicial(),
                ['MPKg', 'ent', self::col('ENTRADAMPKG')],
                ['MPBBKg', 'ent', self::col('ENTMPBBKG')],
                ['EntOtrosKg', 'ent', self::col('ENTRADASOTROSKG')],
                ['Traspasos', 'ent', self::resta('ENTTRASPASOSKG', 'SALTRASPASOSKG')],
                ['SalMC1Kg', 'sal', self::col('SALPRODMC1KG')],
                ['SalMC2Kg', 'sal', self::col('SALPRODMC2KG')],
                ['SalMC3Kg', 'sal', self::col('SALPRODMC3KG')],
                ['SalKM1Kg', 'sal', self::col('SALPRODKMKG')],
                ['LisoKg', 'sal', self::col('LISOKG')],
                ['JaqKg', 'sal', self::col('JAQKG')],
                ['ItemaKg', 'sal', self::col('ITEMAKG')],
                ['SalOtrosKg', 'sal', self::col('SALOTROSKG')],
                self::ajuste(),
                ['Saldo Kg.', 'saldo', self::col('SALDOKG')],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function julurd(): array
    {
        return [
            'titulo' => 'JULIOS URDIDOS',
            'almacen' => 'A-JUL/URD',
            'agrupar' => 'WMSLOCATIONID',
            'etiqueta' => 'Localidad',
            'columnas' => [
                self::inicial(),
                ['EntMC1Kg', 'ent', self::col('ENTMC1KG')],
                ['EntMC2Kg', 'ent', self::col('ENTMC2KG')],
                ['EntMC3Kg', 'ent', self::col('ENTMC3KG')],
                ['EntKM Kg', 'ent', self::col('ENTKM1KG')],
                ['SalWP2Kg', 'sal', self::col('SALWP2')],
                ['SalWP3Kg', 'sal', self::col('SALWP3')],
                ['Sal KMKg', 'sal', self::col('SALKM1KG')],
                ['Otros Kg.', 'sal', self::col('SALOTROSKG')],
                self::ajuste(),
                ['SaldoKg', 'saldo', self::col('SALDOKG')],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function jultela(): array
    {
        return [
            'titulo' => 'JULIOS TELA',
            'almacen' => 'A-JUL/TELA',
            'agrupar' => 'ITEMID',
            'etiqueta' => 'Articulo',
            'columnas' => [
                self::inicial(),
                ['EntWP1Kg', 'ent', self::col('ENTWP1KG')],
                ['EntWP2Kg', 'ent', self::col('ENTWP2KG')],
                ['EntWP3Kg', 'ent', self::col('ENTWP3KG')],
                ['JaqKg', 'sal', self::col('JAQKG')],
                ['ItemaKg', 'sal', self::col('ITEMAKG')],
                ['Otros Kg.', 'sal', self::resta('SALOTROSKG', 'ENTRADASOTROSKG')],
                self::ajuste(),
                ['Saldo Kg.', 'saldo', self::col('SALDOKG')],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function tejido(): array
    {
        return [
            'titulo' => 'TEJIDO',
            'almacen' => 'A-EP-TEJID',
            'agrupar' => 'WMSLOCATIONID',
            'etiqueta' => 'Localidad',
            'columnas' => [
                self::inicial(),
                ['EntUrdido Kg', 'ent', self::col('ENTURDIDOKG')],
                ['EntEngomKg', 'ent', self::col('ENTENGOMKG')],
                ['SalSulserKg', 'sal', self::col('SALSULSERKG')],
                ['SalJacqKg', 'sal', self::col('SALJACQKG')],
                ['SalItemaKg', 'sal', self::col('SALITEMAKG')],
                ['SalSmithKg', 'sal', self::col('SALSMITHKG')],
                ['Sal KMKg', 'sal', self::col('SALKMKG')],
                ['Otros Kg.', 'sal', fn (array $r): float => self::num($r, 'SALOTROSKG')
                    - (self::num($r, 'EXISTENCIAKG') - self::num($r, 'INICIALKG') - self::num($r, 'ENTURDIDOKG') - self::num($r, 'ENTENGOMKG'))],
                self::ajuste(),
                ['Saldo Kg.', 'saldo', self::col('SALDOKG')],
            ],
        ];
    }

    /**
     * Valores de un renglón (o de un grupo de renglones) en el orden de los encabezados.
     *
     * @param  array{columnas: list<array{0: string, 1: string, 2: Closure(array<string, mixed>): float}>}  $def
     * @param  list<array<string, mixed>>  $renglones
     * @return list<float>
     */
    private function valores(array $def, array $renglones): array
    {
        $valores = [];
        $existencia = 0.0;
        $existenciaPuesta = false;
        foreach ($def['columnas'] as [, $tipo, $fn]) {
            if (! $existenciaPuesta && in_array($tipo, ['sal', 'aj', 'saldo'], true)) {
                $valores[] = self::r2($existencia);
                $existenciaPuesta = true;
            }
            $v = array_sum(array_map($fn, $renglones));
            if (in_array($tipo, ['ini', 'ent'], true)) {
                $existencia += $v;
            }
            $valores[] = self::r2($v);
        }

        return $valores;
    }

    /** @return list<string> */
    private function encabezados(array $def): array
    {
        $titulos = [$def['etiqueta']];
        $existenciaPuesta = false;
        foreach ($def['columnas'] as [$titulo, $tipo]) {
            if (! $existenciaPuesta && in_array($tipo, ['sal', 'aj', 'saldo'], true)) {
                $titulos[] = 'Existencia Kg.';
                $existenciaPuesta = true;
            }
            $titulos[] = $titulo;
        }

        return $titulos;
    }

    /**
     * @param  list<array<string, mixed>>  $renglones
     * @return array<string, mixed>
     */
    private function seccion(string $clave, array $def, array $renglones): array
    {
        $grupos = [];
        foreach ($renglones as $r) {
            $grupos[trim((string) ($r[$def['agrupar']] ?? '')) ?: '(sin localidad)'][] = $r;
        }
        uksort($grupos, 'strnatcasecmp');

        return [
            'clave' => $clave,
            'titulo' => $def['titulo'],
            'unica' => false,
            'encabezados' => $this->encabezados($def),
            'renglones' => array_map(
                fn (string $etiqueta, array $rs): array => ['etiqueta' => $etiqueta, 'valores' => $this->valores($def, $rs)],
                array_keys($grupos),
                array_values($grupos),
            ),
            'total' => $renglones === [] ? null : ['etiqueta' => 'Total general', 'valores' => $this->valores($def, $renglones)],
        ];
    }

    /**
     * Desglose por artículo (lo que abre el modal de un renglón), del saldo mayor al menor.
     *
     * @param  list<array<string, mixed>>  $renglones
     * @return list<array<string, mixed>>
     */
    private function articulos(array $def, array $renglones): array
    {
        $items = [];
        foreach ($renglones as $r) {
            $suma = ['ini' => 0.0, 'ent' => 0.0, 'sal' => 0.0, 'aj' => 0.0, 'saldo' => 0.0];
            foreach ($def['columnas'] as [, $tipo, $fn]) {
                $suma[$tipo] += $fn($r);
            }
            if (abs($suma['ini']) + abs($suma['ent']) + abs($suma['sal']) + abs($suma['saldo']) <= 0.005) {
                continue;
            }
            $items[] = [
                'a' => trim((string) ($r['ITEMID'] ?? '')),
                'c' => trim((string) ($r['CONFIGID'] ?? '')),
                't' => trim((string) ($r['INVENTSIZEID'] ?? '')),
                'o' => trim((string) ($r['INVENTCOLORID'] ?? '')),
                'loc' => trim((string) ($r['WMSLOCATIONID'] ?? '')),
                'th' => '',
                ...array_map(fn (float $v): float => self::r2($v), $suma),
            ];
        }
        usort($items, fn (array $a, array $b): int => abs($b['saldo']) <=> abs($a['saldo']));

        return $items;
    }

    /** Redondeo a centavos sin dejar un -0 (la tabla lo pintaría como «-0»). */
    private static function r2(float $v): float
    {
        $r = round($v, 2);

        return $r == 0.0 ? 0.0 : $r;
    }

    /** @param  array<string, mixed>  $r */
    private static function num(array $r, string $columna): float
    {
        return (float) ($r[$columna] ?? 0);
    }
}
