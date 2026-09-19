<?php

declare(strict_types=1);

namespace Tests\Unit\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\ReservarProgramarActionService as Acciones;
use PHPUnit\Framework\TestCase;

/**
 * Que campos llegan al UPDATE y cuales no.
 *
 * La regla no es uniforme y es facil de romper: casi todos los campos se
 * ignoran si vienen vacios, pero cuenta y calibre se escriben igual, porque
 * vaciarlos desde la edicion inline tiene que borrar el valor en la base.
 */
class ReservarProgramarCamposTest extends TestCase
{
    public function test_los_campos_vacios_no_se_escriben(): void
    {
        $update = Acciones::camposDeInventario([
            'no_telar' => '401',
            'metros' => '',
            'no_julio' => null,
            'localidad' => '   ',
            'hilo' => '',
        ]);

        $this->assertSame([], $update, 'Ni metros, ni julio, ni localidad, ni hilo deben viajar vacios.');
    }

    public function test_las_claves_ausentes_no_se_escriben(): void
    {
        $this->assertSame([], Acciones::camposDeInventario(['no_telar' => '401']));
    }

    /**
     * Es la excepcion a la regla: la edicion inline de la tabla tiene que poder
     * dejar la celda vacia y que eso borre el dato.
     */
    public function test_cuenta_y_calibre_si_se_vacian(): void
    {
        $update = Acciones::camposDeInventario(['cuenta' => '', 'calibre' => '']);

        $this->assertArrayHasKey('cuenta', $update);
        $this->assertSame('', $update['cuenta']);
        $this->assertArrayHasKey('calibre', $update);
        $this->assertNull($update['calibre']);
    }

    public function test_el_numero_de_orden_tambien_escribe_el_lote_del_proveedor(): void
    {
        $update = Acciones::camposDeInventario(['no_orden' => 'URD00123']);

        $this->assertSame('URD00123', $update['no_orden']);
        $this->assertSame('URD00123', $update['LoteProveedor']);
    }

    /** Si vienen los dos, manda el lote explicito. */
    public function test_el_lote_explicito_gana_al_derivado_del_numero_de_orden(): void
    {
        $update = Acciones::camposDeInventario([
            'no_orden' => 'URD00123',
            'lote_proveedor' => '00061',
        ]);

        $this->assertSame('URD00123', $update['no_orden']);
        $this->assertSame('00061', $update['LoteProveedor']);
    }

    public function test_los_numeros_se_convierten(): void
    {
        $update = Acciones::camposDeInventario(['metros' => '1234.5', 'calibre' => '20.5']);

        $this->assertSame(1234.5, $update['metros']);
        $this->assertSame(20.5, $update['calibre']);
    }

    /** El cero es un valor, no un vacio. */
    public function test_el_cero_es_un_valor_valido(): void
    {
        $update = Acciones::camposDeInventario(['metros' => '0']);

        $this->assertArrayHasKey('metros', $update);
        $this->assertSame(0.0, $update['metros']);
    }

    public function test_los_programas_reciben_el_tipo_ya_normalizado(): void
    {
        $update = Acciones::camposDeProgramas(['tipo' => 'RIZO', 'hilo' => 'ALG'], 'Rizo');

        $this->assertSame('Rizo', $update['tipo'], 'No debe llegar el valor crudo del request.');
        $this->assertSame('ALG', $update['hilo']);
    }

    /**
     * Los programas no borran: a diferencia del inventario, aqui un calibre
     * vacio no debe escribir nada.
     */
    public function test_los_programas_no_escriben_campos_vacios(): void
    {
        $this->assertSame([], Acciones::camposDeProgramas(['cuenta' => '', 'calibre' => '', 'tipo' => ''], null));
    }

    /** El campo que solo entiende el inventario no debe colarse a los programas. */
    public function test_los_programas_ignoran_los_campos_de_inventario(): void
    {
        $update = Acciones::camposDeProgramas(['no_julio' => '00061-744', 'metros' => '1200'], null);

        $this->assertSame([], $update);
    }
}
