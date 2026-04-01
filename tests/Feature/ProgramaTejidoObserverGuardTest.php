<?php

namespace Tests\Feature;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoObserverGuardTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');
    }

    private function callShouldRegenerate(ReqProgramaTejido $programa): bool
    {
        $observer = new ReqProgramaTejidoObserver();
        $method = new \ReflectionMethod($observer, 'shouldRegenerateLines');
        $method->setAccessible(true);
        return $method->invoke($observer, $programa);
    }

    public function test_siempre_regenera_para_registro_recien_creado(): void
    {
        $programa = new ReqProgramaTejido();
        $programa->setRawAttributes(['Id' => 1, 'Observaciones' => 'test']);
        $programa->syncOriginal();
        $ref = new \ReflectionProperty($programa, 'wasRecentlyCreated');
        $ref->setAccessible(true);
        $ref->setValue($programa, true);

        $this->assertTrue($this->callShouldRegenerate($programa));
    }

    public function test_no_regenera_cuando_solo_observaciones_cambia(): void
    {
        $programa = new ReqProgramaTejido();
        $programa->setRawAttributes([
            'Id' => 1,
            'Observaciones' => 'original',
            'FechaInicio' => '2026-01-01',
            'FechaFinal' => '2026-01-10',
        ]);
        $programa->syncOriginal();
        $programa->exists = true;
        $programa->Observaciones = 'nuevo comentario';

        $this->assertFalse($this->callShouldRegenerate($programa));
    }

    public function test_regenera_cuando_fecha_inicio_cambia(): void
    {
        $programa = new ReqProgramaTejido();
        $programa->setRawAttributes(['Id' => 1, 'FechaInicio' => '2026-01-01', 'FechaFinal' => '2026-01-10']);
        $programa->syncOriginal();
        $programa->exists = true;
        $programa->FechaInicio = '2026-01-05';

        $this->assertTrue($this->callShouldRegenerate($programa));
    }

    public function test_regenera_cuando_total_pedido_cambia(): void
    {
        $programa = new ReqProgramaTejido();
        $programa->setRawAttributes(['Id' => 1, 'TotalPedido' => '100']);
        $programa->syncOriginal();
        $programa->exists = true;
        $programa->TotalPedido = '200';

        $this->assertTrue($this->callShouldRegenerate($programa));
    }

    public function test_regenera_cuando_velocidad_std_cambia(): void
    {
        $programa = new ReqProgramaTejido();
        $programa->setRawAttributes(['Id' => 1, 'VelocidadSTD' => '150']);
        $programa->syncOriginal();
        $programa->exists = true;
        $programa->VelocidadSTD = '180';

        $this->assertTrue($this->callShouldRegenerate($programa));
    }
}
