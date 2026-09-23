<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * ERP-F0-06: getOrdenProduccion probaba la conexion a TI_PRO con SELECT @@VERSION en cada
 * llamada y devolvia la version del servidor en la clave 'debug'. El contrato que queda es
 * 200 {success, orden} / 404 {error} / 400 sin no_telar.
 */
class NotificarCortadoRolloOrdenProduccionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** @var array<int, string> */
    private array $sql = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createAuthTable();
        $this->createProgramaTejidoTable();

        // TI_PRO a sqlite en memoria: si el probe siguiera, no sale a la red en el test.
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('sqlsrv_ti');

        $schema = Schema::connection(config('database.default'));
        if (! $schema->hasColumn('ReqProgramaTejido', 'NoProduccion')) {
            $schema->table('ReqProgramaTejido', function (Blueprint $table) {
                $table->string('NoProduccion')->nullable();
            });
        }

        DB::listen(function ($query) {
            $this->sql[] = $query->sql;
        });

        $this->actingAs($this->createUsuario(), 'web');
    }

    public function test_devuelve_la_orden_activa_sin_debug_ni_probe_de_version(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 1,
            'NoTelarId' => '201',
            'SalonTejidoId' => 'JACQUARD',
            'NoProduccion' => 'OP-123',
            'EnProceso' => 1,
        ]);

        $response = $this->getJson(route('notificar.cortado.rollo.orden.produccion', ['no_telar' => '201']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('orden.NoProduccion', 'OP-123')
            ->assertJsonMissingPath('debug');

        $this->assertSinProbeDeVersion();
    }

    public function test_sin_orden_activa_responde_404_sin_debug(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 1,
            'NoTelarId' => '201',
            'NoProduccion' => 'OP-123',
            'EnProceso' => 0,
        ]);

        $response = $this->getJson(route('notificar.cortado.rollo.orden.produccion', ['no_telar' => '201']));

        $response->assertNotFound()
            ->assertJsonStructure(['error'])
            ->assertJsonMissingPath('debug');

        $this->assertSinProbeDeVersion();
    }

    public function test_sin_no_telar_responde_400(): void
    {
        $this->getJson(route('notificar.cortado.rollo.orden.produccion'))
            ->assertStatus(400)
            ->assertJsonStructure(['error']);
    }

    private function assertSinProbeDeVersion(): void
    {
        foreach ($this->sql as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('@@VERSION', $sql);
        }

        // DB::listen no ve consultas que fallan: la prueba fuerte es que ni se abrio TI_PRO.
        $this->assertArrayNotHasKey('sqlsrv_ti', DB::getConnections());
    }
}
