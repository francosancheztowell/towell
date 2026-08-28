<?php

declare(strict_types=1);

namespace App\Support\ProductoTerminado;

use Illuminate\Support\Carbon;

/**
 * Datos de demostración para el panel de Tiempos de Preparación.
 *
 * TEMPORAL: este archivo existe únicamente mientras el módulo no consulta la
 * base de datos. Al conectar el origen real (repositorio/servicio sobre las
 * tablas de distribución y compras), eliminar esta clase y sus llamadas en
 * TiemposPreparacionController.
 *
 * Las marcas de tiempo se calculan relativas a "ahora" para que el contador de
 * preparación muestre valores realistas en cualquier momento de la demo.
 */
final class TiemposPreparacionMock
{
    /**
     * Órdenes de distribución abiertas, cada una con sus órdenes de compra.
     *
     * @return list<array<string, mixed>>
     */
    public static function ordenesDistribucion(): array
    {
        return [
            [
                'folio' => 'OD-2026-0184',
                'orden' => 'ORD-41207',
                'cliente' => 'Walmart México',
                'destino' => 'CEDIS Cuautitlán',
                'tipo' => 'Nacional',
                'piezas' => 4820,
                'kg' => 1928.40,
                'estatus' => 'En preparación',
                'inicio' => self::hace(195),
                'compras' => [
                    [
                        'folio' => 'OC-88412',
                        'articulo' => 'Toalla baño 70x140 blanco',
                        'modelo' => 'TB-70140-BL',
                        'cantidad' => 2400,
                        'surtido' => 2400,
                        'compromiso' => '2026-08-26',
                        'estatus' => 'Completa',
                    ],
                    [
                        'folio' => 'OC-88413',
                        'articulo' => 'Toalla manos 40x70 gris',
                        'modelo' => 'TM-4070-GR',
                        'cantidad' => 1600,
                        'surtido' => 940,
                        'compromiso' => '2026-08-27',
                        'estatus' => 'Parcial',
                    ],
                    [
                        'folio' => 'OC-88419',
                        'articulo' => 'Toallita facial 30x30 blanco',
                        'modelo' => 'TF-3030-BL',
                        'cantidad' => 820,
                        'surtido' => 0,
                        'compromiso' => '2026-08-28',
                        'estatus' => 'Pendiente',
                    ],
                ],
            ],
            [
                'folio' => 'OD-2026-0185',
                'orden' => 'ORD-41213',
                'cliente' => 'Liverpool',
                'destino' => 'CEDIS Tultitlán',
                'tipo' => 'Nacional',
                'piezas' => 2650,
                'kg' => 1007.00,
                'estatus' => 'En preparación',
                'inicio' => self::hace(72),
                'compras' => [
                    [
                        'folio' => 'OC-88437',
                        'articulo' => 'Juego 3 pzas jacquard beige',
                        'modelo' => 'JG3-JQ-BE',
                        'cantidad' => 1250,
                        'surtido' => 1250,
                        'compromiso' => '2026-08-27',
                        'estatus' => 'Completa',
                    ],
                    [
                        'folio' => 'OC-88441',
                        'articulo' => 'Toalla playa 90x170 estampada',
                        'modelo' => 'TP-90170-ES',
                        'cantidad' => 1400,
                        'surtido' => 610,
                        'compromiso' => '2026-08-29',
                        'estatus' => 'Parcial',
                    ],
                ],
            ],
            [
                'folio' => 'OD-2026-0186',
                'orden' => 'ORD-41220',
                'cliente' => 'Costco Wholesale',
                'destino' => 'CEDIS Guadalajara',
                'tipo' => 'Exportación',
                'piezas' => 7300,
                'kg' => 3212.00,
                'estatus' => 'Programada',
                'inicio' => null,
                'compras' => [
                    [
                        'folio' => 'OC-88450',
                        'articulo' => 'Paquete 6 toallas algodón',
                        'modelo' => 'PQ6-ALG-MX',
                        'cantidad' => 3600,
                        'surtido' => 0,
                        'compromiso' => '2026-08-31',
                        'estatus' => 'Pendiente',
                    ],
                    [
                        'folio' => 'OC-88451',
                        'articulo' => 'Toalla baño 70x140 azul',
                        'modelo' => 'TB-70140-AZ',
                        'cantidad' => 2200,
                        'surtido' => 0,
                        'compromiso' => '2026-09-01',
                        'estatus' => 'Pendiente',
                    ],
                    [
                        'folio' => 'OC-88452',
                        'articulo' => 'Tapete baño 50x80 gris',
                        'modelo' => 'TA-5080-GR',
                        'cantidad' => 1500,
                        'surtido' => 0,
                        'compromiso' => '2026-09-02',
                        'estatus' => 'Pendiente',
                    ],
                ],
            ],
            [
                'folio' => 'OD-2026-0187',
                'orden' => 'ORD-41224',
                'cliente' => 'Soriana',
                'destino' => 'CEDIS Monterrey',
                'tipo' => 'Traspaso',
                'piezas' => 3120,
                'kg' => 1310.40,
                'estatus' => 'Detenida',
                'inicio' => self::hace(640),
                'compras' => [
                    [
                        'folio' => 'OC-88463',
                        'articulo' => 'Toalla manos 40x70 blanco',
                        'modelo' => 'TM-4070-BL',
                        'cantidad' => 1820,
                        'surtido' => 1120,
                        'compromiso' => '2026-08-30',
                        'estatus' => 'Parcial',
                    ],
                    [
                        'folio' => 'OC-88464',
                        'articulo' => 'Bata rizo talla M',
                        'modelo' => 'BR-RZ-M',
                        'cantidad' => 1300,
                        'surtido' => 0,
                        'compromiso' => '2026-09-03',
                        'estatus' => 'Pendiente',
                    ],
                ],
            ],
            [
                'folio' => 'OD-2026-0188',
                'orden' => 'ORD-41231',
                'cliente' => 'Amazon México',
                'destino' => 'CEDIS Tepotzotlán',
                'tipo' => 'Exportación',
                'piezas' => 1980,
                'kg' => 851.40,
                'estatus' => 'Programada',
                'inicio' => null,
                'compras' => [
                    [
                        'folio' => 'OC-88470',
                        'articulo' => 'Set 2 toallas premium',
                        'modelo' => 'ST2-PRM-BL',
                        'cantidad' => 1980,
                        'surtido' => 0,
                        'compromiso' => '2026-09-04',
                        'estatus' => 'Pendiente',
                    ],
                ],
            ],
            [
                'folio' => 'OD-2026-0189',
                'orden' => 'ORD-41238',
                'cliente' => 'Chedraui',
                'destino' => 'CEDIS Puebla',
                'tipo' => 'Nacional',
                'piezas' => 3440,
                'kg' => 1341.60,
                'estatus' => 'En preparación',
                'inicio' => self::hace(455),
                'compras' => [
                    [
                        'folio' => 'OC-88482',
                        'articulo' => 'Toalla baño 70x140 verde',
                        'modelo' => 'TB-70140-VE',
                        'cantidad' => 2040,
                        'surtido' => 1580,
                        'compromiso' => '2026-08-29',
                        'estatus' => 'Parcial',
                    ],
                    [
                        'folio' => 'OC-88483',
                        'articulo' => 'Toallita facial 30x30 rosa',
                        'modelo' => 'TF-3030-RO',
                        'cantidad' => 1400,
                        'surtido' => 1400,
                        'compromiso' => '2026-08-28',
                        'estatus' => 'Completa',
                    ],
                ],
            ],
        ];
    }

    /**
     * Órdenes de distribución ya cerradas, con el tiempo de preparación medido.
     *
     * @return list<array<string, mixed>>
     */
    public static function ordenesCerradas(): array
    {
        return [
            [
                'folio' => 'OD-2026-0179',
                'orden' => 'ORD-41180',
                'cliente' => 'Walmart México',
                'tipo' => 'Nacional',
                'cierre' => self::hace(1_620),
                'piezas' => 5400,
                'kg' => 2160.00,
                'compras' => 4,
                'minutos' => 760,
            ],
            [
                'folio' => 'OD-2026-0180',
                'orden' => 'ORD-41186',
                'cliente' => 'Chedraui',
                'tipo' => 'Nacional',
                'cierre' => self::hace(1_395),
                'piezas' => 2240,
                'kg' => 873.60,
                'compras' => 2,
                'minutos' => 305,
            ],
            [
                'folio' => 'OD-2026-0181',
                'orden' => 'ORD-41192',
                'cliente' => 'Liverpool',
                'tipo' => 'Exportación',
                'cierre' => self::hace(1_050),
                'piezas' => 3860,
                'kg' => 1544.00,
                'compras' => 3,
                'minutos' => 495,
            ],
            [
                'folio' => 'OD-2026-0182',
                'orden' => 'ORD-41198',
                'cliente' => 'Costco Wholesale',
                'tipo' => 'Exportación',
                'cierre' => self::hace(730),
                'piezas' => 6120,
                'kg' => 2692.80,
                'compras' => 5,
                'minutos' => 1080,
            ],
            [
                'folio' => 'OD-2026-0183',
                'orden' => 'ORD-41203',
                'cliente' => 'Soriana',
                'tipo' => 'Traspaso',
                'cierre' => self::hace(410),
                'piezas' => 1750,
                'kg' => 735.00,
                'compras' => 2,
                'minutos' => 240,
            ],
        ];
    }

    /**
     * Marca de tiempo ubicada N minutos antes del momento actual.
     */
    private static function hace(int $minutos): string
    {
        return Carbon::now()->subMinutes($minutos)->format('Y-m-d\TH:i:s');
    }
}
