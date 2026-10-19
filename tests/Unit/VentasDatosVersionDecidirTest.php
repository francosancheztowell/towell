<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ventas\VentasDatosVersion;
use PHPUnit\Framework\TestCase;

/**
 * Cuándo se publica una versión nueva de los datos de Ventas: solo cuando la marca de escritura de
 * las tablas cambió y lleva el periodo de calma sin volver a cambiar (para no cachear una carga a medias).
 */
class VentasDatosVersionDecidirTest extends TestCase
{
    private const CALMA = VentasDatosVersion::CALMA_SEGUNDOS;

    /** @var array<string, string|null> */
    private const MARCAS = ['TwHistoricosVentas' => null, 'TwHistoricosPedidos' => '2026-10-19 10:09:45.970'];

    /** @var array<string, string|null> */
    private const MARCAS_NUEVAS = ['TwHistoricosVentas' => null, 'TwHistoricosPedidos' => '2026-10-19 11:00:00.000'];

    public function test_la_primera_lectura_solo_guarda_las_marcas(): void
    {
        [$estado, $publicar] = VentasDatosVersion::decidir(null, self::MARCAS, 1000);

        $this->assertFalse($publicar);
        $this->assertSame(self::MARCAS, $estado['estables']);
    }

    public function test_sin_cambios_no_publica(): void
    {
        $estado = ['estables' => self::MARCAS, 'vistas' => self::MARCAS, 'desde' => null];

        [, $publicar] = VentasDatosVersion::decidir($estado, self::MARCAS, 5000);

        $this->assertFalse($publicar);
    }

    public function test_un_cambio_espera_la_calma_y_luego_publica(): void
    {
        $estado = ['estables' => self::MARCAS, 'vistas' => self::MARCAS, 'desde' => null];

        [$estado, $publicar] = VentasDatosVersion::decidir($estado, self::MARCAS_NUEVAS, 1000);
        $this->assertFalse($publicar, 'recién visto el cambio');
        $this->assertSame(1000, $estado['desde']);

        [$estado, $publicar] = VentasDatosVersion::decidir($estado, self::MARCAS_NUEVAS, 1000 + self::CALMA - 1);
        $this->assertFalse($publicar, 'todavía dentro de la calma');

        [$estado, $publicar] = VentasDatosVersion::decidir($estado, self::MARCAS_NUEVAS, 1000 + self::CALMA);
        $this->assertTrue($publicar);
        $this->assertSame(self::MARCAS_NUEVAS, $estado['estables']);
        $this->assertNull($estado['desde']);
    }

    public function test_si_la_carga_sigue_escribiendo_la_calma_vuelve_a_empezar(): void
    {
        $estado = ['estables' => self::MARCAS, 'vistas' => self::MARCAS_NUEVAS, 'desde' => 1000];
        $otraEscritura = ['TwHistoricosVentas' => null, 'TwHistoricosPedidos' => '2026-10-19 11:01:30.000'];

        [$estado, $publicar] = VentasDatosVersion::decidir($estado, $otraEscritura, 1000 + self::CALMA);

        $this->assertFalse($publicar);
        $this->assertSame($otraEscritura, $estado['vistas']);
        $this->assertSame(1000 + self::CALMA, $estado['desde']);
    }

    public function test_un_reinicio_de_sql_server_cuenta_como_cambio(): void
    {
        // Tras reiniciar el servicio las marcas vuelven a null: no se sabe si hubo cargas, se reconstruye una vez.
        $estado = ['estables' => self::MARCAS, 'vistas' => self::MARCAS, 'desde' => null];
        $reinicio = ['TwHistoricosVentas' => null, 'TwHistoricosPedidos' => null];

        [$estado] = VentasDatosVersion::decidir($estado, $reinicio, 1000);
        [, $publicar] = VentasDatosVersion::decidir($estado, $reinicio, 1000 + self::CALMA);

        $this->assertTrue($publicar);
    }
}
