<?php

namespace Tests\Unit;

use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ProgramarUrdEngController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProgramarUrdEngControllerTest extends TestCase
{
    public function test_crear_ordenes_rechaza_destino_vacio(): void
    {
        $controller = new ProgramarUrdEngController();

        $request = Request::create('/programa-urd-eng/crear-ordenes', 'POST', [
            'grupo' => [
                'telaresStr' => '299,300',
                'tipo' => 'Rizo',
                'fibra' => 'ALG',
                'hilo' => 'ALG',
                'salonTejidoId' => '',
                'destino' => '',
            ],
            'materialesEngomado' => [
                ['itemId' => 'MAT-01'],
            ],
            'construccionUrdido' => [
                ['julios' => '8', 'hilos' => '1200', 'observaciones' => ''],
            ],
            'datosEngomado' => [
                'lMatEngomado' => 'BOM-01',
            ],
        ]);

        $response = $controller->crearOrdenes($request);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Debe seleccionar un destino antes de crear la orden.', $payload['error']);
    }
}
