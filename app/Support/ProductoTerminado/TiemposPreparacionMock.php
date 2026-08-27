<?php

declare(strict_types=1);

namespace App\Support\ProductoTerminado;

/**
 * Datos de demostración para el panel de Tiempos de Preparación.
 *
 * TEMPORAL: este archivo existe únicamente mientras el módulo no consulta la
 * base de datos. Al conectar el origen real (repositorio/servicio sobre las
 * tablas de distribución y compras), eliminar esta clase y sus llamadas en
 * TiemposPreparacionController.
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
                'cliente' => 'Walmart México',
                'destino' => 'CEDIS Cuautitlán',
                'fecha' => '2026-08-24',
                'piezas' => 4820,
                'estatus' => 'En preparación',
                'inicio' => '2026-08-25 07:15',
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
                'cliente' => 'Liverpool',
                'destino' => 'CEDIS Tultitlán',
                'fecha' => '2026-08-25',
                'piezas' => 2650,
                'estatus' => 'En preparación',
                'inicio' => '2026-08-25 13:40',
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
                'cliente' => 'Costco Wholesale',
                'destino' => 'CEDIS Guadalajara',
                'fecha' => '2026-08-25',
                'piezas' => 7300,
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
                'cliente' => 'Soriana',
                'destino' => 'CEDIS Monterrey',
                'fecha' => '2026-08-26',
                'piezas' => 3120,
                'estatus' => 'Detenida',
                'inicio' => '2026-08-26 06:50',
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
                'cliente' => 'Amazon México',
                'destino' => 'CEDIS Tepotzotlán',
                'fecha' => '2026-08-26',
                'piezas' => 1980,
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
                'cliente' => 'Walmart México',
                'cierre' => '2026-08-23 18:20',
                'piezas' => 5400,
                'compras' => 4,
                'minutos' => 760,
            ],
            [
                'folio' => 'OD-2026-0180',
                'cliente' => 'Chedraui',
                'cierre' => '2026-08-23 15:05',
                'piezas' => 2240,
                'compras' => 2,
                'minutos' => 305,
            ],
            [
                'folio' => 'OD-2026-0181',
                'cliente' => 'Liverpool',
                'cierre' => '2026-08-24 11:45',
                'piezas' => 3860,
                'compras' => 3,
                'minutos' => 495,
            ],
            [
                'folio' => 'OD-2026-0182',
                'cliente' => 'Costco Wholesale',
                'cierre' => '2026-08-24 20:10',
                'piezas' => 6120,
                'compras' => 5,
                'minutos' => 1080,
            ],
            [
                'folio' => 'OD-2026-0183',
                'cliente' => 'Soriana',
                'cierre' => '2026-08-25 09:30',
                'piezas' => 1750,
                'compras' => 2,
                'minutos' => 240,
            ],
        ];
    }
}
