<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\CatCodificadosDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\NotificacionTelegramDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class ProcesarDesarrolladorPasadasTest extends TestCase
{
    public function test_acepta_total_inicial_y_hasta_treinta_por_ciento_adicional(): void
    {
        $this->validarPasadas([
            'PasadasTrama' => 408,
            'PasadasComb1' => 98,
            'PasadasComb2' => 96,
        ]);

        // 602 + 30% = 782.6; al ser pasadas enteras, 782 es el máximo permitido.
        $this->validarPasadas([
            'PasadasTrama' => 408,
            'PasadasComb1' => 98,
            'PasadasComb2' => 96,
            'PasadasComb3' => 180,
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_rechaza_total_mayor_al_treinta_por_ciento_adicional(): void
    {
        $this->expectException(ValidationException::class);

        $this->validarPasadas([
            'PasadasTrama' => 408,
            'PasadasComb1' => 98,
            'PasadasComb2' => 96,
            'PasadasComb3' => 181,
        ]);
    }

    public function test_rechaza_combinaciones_menores_al_treinta_por_ciento_inicial(): void
    {
        $this->expectException(ValidationException::class);

        $this->validarPasadas([
            'PasadasTrama' => 422,
            'PasadasComb1' => 90,
            'PasadasComb2' => 90,
        ]);
    }

    /**
     * @param  array<string, int>  $pasadas
     */
    private function validarPasadas(array $pasadas): void
    {
        $orden = new ReqProgramaTejido;
        $orden->PasadasTrama = 408;
        $orden->PasadasComb1 = 98;
        $orden->PasadasComb2 = 96;

        $catCodificadosService = new CatCodificadosDesarrolladorService;
        $service = new ProcesarDesarrolladorService(
            new MovimientoDesarrolladorService($catCodificadosService),
            $this->createMock(NotificacionTelegramDesarrolladorService::class),
            $catCodificadosService
        );

        $method = (new ReflectionClass($service))->getMethod('validarPasadasContraConfiguracionInicial');
        $method->setAccessible(true);
        $method->invoke($service, $pasadas, $orden);
    }
}
