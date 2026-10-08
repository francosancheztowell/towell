<?php

namespace Tests\Feature\Tejido;

use App\Http\Controllers\Tejido\Reportes\SaldosController;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fija la salida de SaldosController::query() (OUTER APPLY, solo SQL Server):
 * columnas que lee la vista y que rmc/cc traen el último Id por TamanoClave / OrdenTejido.
 * Solo lee; no escribe nada.
 */
#[Group('sqlserver')]
class SaldosQuerySqlServerTest extends TestCase
{
    public function test_query_trae_ultimo_modelo_y_ultimo_codificado_por_fila(): void
    {
        if (config('database.default') !== 'sqlsrv' || config('database.connections.sqlsrv.driver') !== 'sqlsrv') {
            $this->markTestSkipped('Requiere conexión SQL Server real');
        }

        $query = new ReflectionMethod(SaldosController::class, 'query');
        $filas = $query->invoke(new SaldosController)->get();

        if ($filas->isEmpty()) {
            $this->markTestSkipped('Sin registros en ReqProgramaTejido para comparar');
        }

        $columnas = ['Id', 'NoProduccion', 'OrdenLider', 'NoMarbete', 'TotalRollos', 'Tolerancia', 'CodigoDibujo',
            'FlogsIdRmc', 'Clave', 'ObsModelo', 'TipoRizo', 'AlturaRizo', 'C1', 'ObsC1', 'C4', 'ObsC4',
            'MedidaCenefa', 'MedIniRizoCenefa'];
        foreach ($columnas as $col) {
            $this->assertArrayHasKey($col, $filas->first()->getAttributes(), "Falta la columna {$col}");
        }

        foreach ($filas->take(5) as $fila) {
            $cc = DB::table('CatCodificados')->where('OrdenTejido', $fila->NoProduccion)
                ->orderByDesc('Id')->first(['NoMarbete', 'TotalRollos']);
            $this->assertEquals($cc?->NoMarbete, $fila->NoMarbete);
            $this->assertEquals($cc?->TotalRollos, $fila->TotalRollos);

            $rmc = $fila->TamanoClave === null ? null : DB::table('ReqModelosCodificados')
                ->where('TamanoClave', $fila->TamanoClave)->orderByDesc('Id')->first(['Clave', 'Comb1', 'MedidaCenefa']);
            $this->assertEquals($rmc?->Clave, $fila->Clave);
            $this->assertEquals($rmc?->Comb1, $fila->C1);
            $this->assertEquals($rmc?->MedidaCenefa, $fila->MedidaCenefa);
        }
    }
}
