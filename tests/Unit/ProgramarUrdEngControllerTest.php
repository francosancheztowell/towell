<?php

namespace Tests\Unit;

use App\Services\ProgramaUrdEng\CrearOrdenesService;
use App\Services\ProgramaUrdEng\InventarioTelaresService;
use DomainException;
use Tests\TestCase;

/**
 * Las reglas de negocio del alta de ordenes se comprueban antes de abrir la
 * transaccion, asi que se pueden probar sin tocar la base.
 *
 * Antes esto instanciaba el controller a mano; ahora el controller solo valida
 * la forma del payload y delega en CrearOrdenesService.
 */
class ProgramarUrdEngControllerTest extends TestCase
{
    public function test_crear_ordenes_rechaza_destino_vacio(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Debe seleccionar un destino antes de crear la orden.');

        $this->servicio()->crear($this->payload(['salonTejidoId' => '', 'destino' => '']));
    }

    /**
     * Sin fibra la orden no se puede urdir: es la regla que obliga a volver a
     * Programacion de Requerimientos.
     */
    public function test_crear_ordenes_rechaza_fibra_vacia(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La fibra/hilo es obligatoria');

        $this->servicio()->crear($this->payload(['fibra' => '', 'hilo' => '']));
    }

    /** El destino en blanco se rechaza igual que el ausente. */
    public function test_los_espacios_no_cuentan_como_destino(): void
    {
        $this->expectException(DomainException::class);

        $this->servicio()->crear($this->payload(['salonTejidoId' => '   ']));
    }

    private function servicio(): CrearOrdenesService
    {
        return new CrearOrdenesService(new InventarioTelaresService);
    }

    /**
     * @param  array<string, mixed>  $grupo  sobrescribe el grupo valido por defecto
     * @return array<string, mixed>
     */
    private function payload(array $grupo = []): array
    {
        return [
            'grupo' => array_merge([
                'telaresStr' => '299,300',
                'tipo' => 'Rizo',
                'fibra' => 'ALG',
                'hilo' => 'ALG',
                'salonTejidoId' => 'JACQUARD',
            ], $grupo),
            'materialesEngomado' => [['itemId' => 'MAT-01']],
            'construccionUrdido' => [['julios' => '8', 'hilos' => '1200', 'observaciones' => '']],
            'datosEngomado' => ['lMatEngomado' => 'BOM-01'],
        ];
    }
}
