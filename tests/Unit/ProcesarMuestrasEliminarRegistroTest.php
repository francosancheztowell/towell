<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use App\Models\Planeacion\Muestras;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * La muestra procesada se consume al guardar. Antes el borrado se resolvia por
 * heuristica (salon + telar -> el EnProceso=1, o si no el mas antiguo) sin recibir
 * el registro procesado: procesar B mientras A estaba en proceso borraba A.
 *
 * Estas aserciones fijan el contrato de la firma. La eliminacion real necesita
 * base de datos y se cubre en la prueba manual del recorrido.
 */
class ProcesarMuestrasEliminarRegistroTest extends TestCase
{
    private function metodo(): \ReflectionMethod
    {
        $metodo = (new ReflectionClass(ProcesarMuestrasDesarrolladorService::class))
            ->getMethod('eliminarRegistroMuestra');
        $metodo->setAccessible(true);

        return $metodo;
    }

    public function test_recibe_el_registro_procesado_y_no_salon_mas_telar(): void
    {
        $parametros = $this->metodo()->getParameters();

        $this->assertCount(1, $parametros, 'Debe recibir la fila procesada, no un par salon+telar.');

        $tipo = $parametros[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $tipo);
        $this->assertSame(Muestras::class, $tipo->getName());
    }

    public function test_borra_por_clave_primaria_y_bajo_bloqueo(): void
    {
        $archivo = (new ReflectionClass(ProcesarMuestrasDesarrolladorService::class))->getFileName();
        $metodo = $this->metodo();
        $cuerpo = implode('', array_slice(
            file($archivo),
            $metodo->getStartLine() - 1,
            $metodo->getEndLine() - $metodo->getStartLine() + 1
        ));

        $this->assertStringContainsString('whereKey(', $cuerpo, 'Debe apuntar a la fila por su clave.');
        $this->assertStringContainsString('lockForUpdate()', $cuerpo, 'Dos guardados simultaneos no deben borrar dos filas.');
        $this->assertStringNotContainsString("'EnProceso'", $cuerpo, 'Ya no debe elegir por EnProceso.');
        $this->assertStringNotContainsString("orderBy('FechaInicio'", $cuerpo, 'Ya no debe caer al mas antiguo.');
    }
}
