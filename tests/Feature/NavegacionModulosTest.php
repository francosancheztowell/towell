<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ModuloService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Navegación de módulos: nivel 1 -> 2 -> 3 y el botón "atrás".
 *
 * El botón atrás ya no consulta /api/modulo-padre: la ruta del padre se
 * resuelve en el servidor con ModuloService::rutaPadreDe(). Este test cubre
 * esa resolución, que es la única lógica no trivial que quedó.
 */
final class NavegacionModulosTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->useSqlsrvSqlite();

        // SYSRoles vive en la conexión 'sqlsrv'.
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('SYSRoles')) {
            $schema->create('SYSRoles', function (Blueprint $table) {
                $table->integer('idrol')->primary();
                $table->string('orden');
                $table->string('modulo');
                $table->string('imagen')->nullable();
                $table->string('Ruta')->nullable();
                $table->integer('Nivel');
                $table->string('Dependencia')->nullable();
            });
        }

        // Recorte real del árbol de SYSRoles: un nivel 1, sus nivel 2 y un nivel 3.
        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            ['idrol' => 1, 'orden' => '100', 'modulo' => 'Planeación', 'Ruta' => '/planeacion', 'Nivel' => 1, 'Dependencia' => null],
            ['idrol' => 2, 'orden' => '104', 'modulo' => 'Catálogos', 'Ruta' => '/planeacion/catalogos', 'Nivel' => 2, 'Dependencia' => '100'],
            ['idrol' => 3, 'orden' => '104-4', 'modulo' => 'Calendarios', 'Ruta' => '/planeacion/catalogos/calendarios', 'Nivel' => 3, 'Dependencia' => '104'],
            // Sin Ruta: el servicio le genera una desde el padre.
            ['idrol' => 4, 'orden' => '108', 'modulo' => 'Utilería', 'Ruta' => null, 'Nivel' => 2, 'Dependencia' => '100'],
            // Prefijo parecido a otro módulo: no debe confundirse con /planeacion.
            ['idrol' => 5, 'orden' => '1100', 'modulo' => 'Mecanicos', 'Ruta' => '/mecanicos', 'Nivel' => 1, 'Dependencia' => null],
            ['idrol' => 6, 'orden' => '1101', 'modulo' => 'Ordenes de Trabajo', 'Ruta' => '/mecanicos/ordenes-trabajo', 'Nivel' => 2, 'Dependencia' => '1100'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('SYSRoles');

        parent::tearDown();
    }

    private function servicio(): ModuloService
    {
        return app(ModuloService::class);
    }

    public function test_nivel_1_vuelve_al_inicio(): void
    {
        $this->assertSame(ModuloService::RUTA_INICIO, $this->servicio()->rutaPadreDe('/planeacion'));
    }

    public function test_nivel_2_sube_a_su_modulo_principal(): void
    {
        $this->assertSame('/planeacion', $this->servicio()->rutaPadreDe('/planeacion/catalogos'));
    }

    public function test_nivel_3_sube_a_su_nivel_2(): void
    {
        $this->assertSame(
            '/planeacion/catalogos',
            $this->servicio()->rutaPadreDe('/planeacion/catalogos/calendarios')
        );
    }

    public function test_pagina_de_detalle_sin_fila_propia_sube_al_modulo_que_la_contiene(): void
    {
        // /mecanicos/ordenes-trabajo/VM00011/captura nunca tendrá fila en SYSRoles.
        $this->assertSame(
            '/mecanicos/ordenes-trabajo',
            $this->servicio()->rutaPadreDe('/mecanicos/ordenes-trabajo/VM00011/captura')
        );
    }

    public function test_modulo_sin_ruta_en_bd_resuelve_por_la_ruta_generada(): void
    {
        // "Utilería" no tiene Ruta: el servicio genera /planeacion/utilera.
        $this->assertSame('/planeacion', $this->servicio()->rutaPadreDe('/planeacion/utilera'));
    }

    public function test_ruta_desconocida_cae_al_inicio(): void
    {
        $this->assertSame(ModuloService::RUTA_INICIO, $this->servicio()->rutaPadreDe('/no-existe'));
    }

    public function test_prefijo_parcial_no_cuenta_como_padre(): void
    {
        // "/planeacionfalsa" comparte prefijo textual con "/planeacion" pero no es su hijo.
        $this->assertSame(ModuloService::RUTA_INICIO, $this->servicio()->rutaPadreDe('/planeacionfalsa'));
    }

    public function test_acepta_la_ruta_sin_slash_inicial(): void
    {
        // request()->path() no trae slash inicial.
        $this->assertSame('/planeacion', $this->servicio()->rutaPadreDe('planeacion/catalogos'));
    }

    public function test_los_endpoints_del_boton_atras_ya_no_existen(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('api.modulo.padre'));
        $this->assertNull(Route::getRoutes()->getByName('api.submodulos'));
    }

    public function test_configurar_de_tejedores_apunta_al_modulo_configurar(): void
    {
        $ruta = Route::getRoutes()->getByName('tejedores.configurar');

        $this->assertNotNull($ruta);
        // 604 = "Configurar". Antes caía a 605, que es "Atado de Julio".
        $this->assertSame('604', $ruta->defaults['moduloPadre'] ?? null);
        $this->assertSame('showSubModulosNivel3', $ruta->getActionMethod());
    }

    public function test_la_ruta_muerta_de_configuracion_de_atadores_ya_no_existe(): void
    {
        // Apuntaba al orden 502, que no existe en SYSRoles: siempre página vacía.
        $this->assertNull(Route::getRoutes()->getByName('atadores.configuracion'));
    }
}
