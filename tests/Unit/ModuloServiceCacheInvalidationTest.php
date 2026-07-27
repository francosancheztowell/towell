<?php

namespace Tests\Unit;

use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Services\ModuloService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ModuloServiceCacheInvalidationTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('cache.default', 'array');
        Cache::clear();

        Schema::connection('sqlsrv')->create('SYSRoles', function (Blueprint $table): void {
            $table->increments('idrol');
            $table->string('orden')->unique();
            $table->string('modulo');
            $table->integer('acceso')->default(0);
            $table->integer('crear')->default(0);
            $table->integer('modificar')->default(0);
            $table->integer('eliminar')->default(0);
            $table->integer('reigstrar')->default(0);
            $table->string('imagen')->nullable();
            $table->string('Dependencia')->nullable();
            $table->integer('Nivel');
            $table->string('Ruta')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlsrv')->create('SYSUsuariosRoles', function (Blueprint $table): void {
            $table->integer('idusuario');
            $table->integer('idrol');
            $table->integer('acceso')->default(0);
            $table->integer('crear')->default(0);
            $table->integer('modificar')->default(0);
            $table->integer('eliminar')->default(0);
            $table->integer('registrar')->default(0);
            $table->timestamp('assigned_at')->nullable();
        });
    }

    public function test_it_invalidates_cached_submodules_for_a_dynamically_created_module(): void
    {
        $usuarioId = 99;
        $moduloPrincipal = SYSRoles::create([
            'orden' => '1100',
            'modulo' => 'Mecanicos',
            'Nivel' => 1,
            'acceso' => 1,
        ]);

        $this->asignarAcceso($usuarioId, $moduloPrincipal);

        $service = app(ModuloService::class);

        $this->assertCount(
            0,
            $service->getSubmodulosPorModuloPrincipal('1100', $usuarioId, $moduloPrincipal)
        );

        $submodulo = SYSRoles::create([
            'orden' => '1101',
            'modulo' => 'Ordenes de Trabajo',
            'Nivel' => 2,
            'Dependencia' => '1100',
            'acceso' => 1,
        ]);
        $this->asignarAcceso($usuarioId, $submodulo);

        $this->assertCount(
            0,
            $service->getSubmodulosPorModuloPrincipal('1100', $usuarioId, $moduloPrincipal)
        );

        $service->limpiarCacheUsuario($usuarioId);

        $submodulos = $service->getSubmodulosPorModuloPrincipal('1100', $usuarioId, $moduloPrincipal);

        $this->assertCount(1, $submodulos);
        $this->assertSame('Ordenes de Trabajo', $submodulos->first()['nombre']);
    }

    private function asignarAcceso(int $usuarioId, SYSRoles $modulo): void
    {
        SYSUsuariosRoles::create([
            'idusuario' => $usuarioId,
            'idrol' => $modulo->idrol,
            'acceso' => 1,
        ]);
    }
}
