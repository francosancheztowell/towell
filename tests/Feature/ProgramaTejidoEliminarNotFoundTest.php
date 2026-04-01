<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoEliminarNotFoundTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    public function test_eliminar_devuelve_404_cuando_el_id_no_existe(): void
    {
        $response = EliminarTejido::eliminar(9_999_999);

        $this->assertSame(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('registro_no_encontrado', $payload['codigo']);
        $this->assertStringContainsString('ya no existe', $payload['message']);
    }

    public function test_eliminar_en_proceso_devuelve_404_cuando_el_id_no_existe(): void
    {
        $response = EliminarTejido::eliminarEnProceso(9_999_999);

        $this->assertSame(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('registro_no_encontrado', $payload['codigo']);
    }
}
