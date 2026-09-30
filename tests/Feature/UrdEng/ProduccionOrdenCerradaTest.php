<?php

namespace Tests\Feature\UrdEng;

use App\Models\Engomado\CatUbicaciones;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Sistema\Usuario;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Bugs graves de Producción Urdido/Engomado (auditoría sep-2026):
 * captura sobre órdenes cerradas, autollenado de Oficial 1 que pisaba captura,
 * GET de Engomado que mutaba para usuarios de solo lectura y HoraInicial = ''.
 */
class ProduccionOrdenCerradaTest extends TestCase
{
    use ModuloUrdEng;

    private const URD = '/urdido/modulo-produccion-urdido';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(UrdProgramaUrdido::class, ['Incorrecto']);
        $this->tablaDe(SYSUsuario::class, ['area']);
        foreach ([UrdJuliosOrden::class, UrdProduccionUrdido::class, EngProgramaEngomado::class,
            EngProduccionEngomado::class, EngProduccionFormulacionModel::class, CatUbicaciones::class] as $modelo) {
            $this->tablaDe($modelo);
        }

        $db = DB::connection('sqlsrv');
        $db->table('UrdProgramaUrdido')->insert(['Id' => 1, 'Folio' => 'F-1', 'Status' => 'Finalizado', 'MaquinaId' => 'Mc Coy 2', 'Metros' => 6000, 'Incorrecto' => 0]);
        $db->table('UrdProduccionUrdido')->insert(['Id' => 1, 'Folio' => 'F-1', 'Hilos' => 640, 'KgBruto' => 300, 'AX' => 0]);
    }

    private function capturista(): Usuario
    {
        return $this->usuarioCon([154 => ['acceso', 'modificar'], 'Producción Urdido' => ['acceso', 'modificar']], 'Urdido');
    }

    public function test_no_se_captura_sobre_una_orden_finalizada(): void
    {
        $this->actingAs($this->capturista())
            ->postJson(self::URD.'/actualizar-kg-bruto', ['registro_id' => 1, 'kg_bruto' => 999])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->postJson(self::URD.'/marcar-listo', ['registro_id' => 1, 'listo' => false])
            ->assertStatus(422);

        $this->assertEquals(300, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', 1)->value('KgBruto'));
    }

    public function test_la_pantalla_edicion_si_corrige_una_orden_finalizada(): void
    {
        $this->actingAs($this->capturista())
            ->postJson(self::URD.'/actualizar-kg-bruto', ['registro_id' => 1, 'kg_bruto' => 310, 'edicion' => 1])
            ->assertOk();

        $this->assertEquals(310, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', 1)->value('KgBruto'));
    }

    public function test_abrir_una_orden_finalizada_no_recrea_filas(): void
    {
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->insert(['Folio' => 'F-1', 'Julios' => 3, 'Hilos' => 640]);

        $this->withoutVite()->actingAs($this->capturista())->get(self::URD.'?orden_id=1')->assertOk();

        $this->assertSame(1, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', 'F-1')->count());
    }

    public function test_abrir_la_orden_no_pisa_oficial_de_filas_con_captura(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('UrdProgramaUrdido')->where('Id', 1)->update(['Status' => 'En Proceso']);
        $db->table('UrdJuliosOrden')->insert(['Folio' => 'F-1', 'Julios' => 3, 'Hilos' => 640]);
        $db->table('UrdProduccionUrdido')->where('Id', 1)->update(['CveEmpl1' => '7', 'NomEmpl1' => 'Operador', 'NoJulio' => '12']);
        $db->table('UrdProduccionUrdido')->insert([
            ['Id' => 2, 'Folio' => 'F-1', 'Hilos' => 640, 'CveEmpl1' => '7', 'NomEmpl1' => 'Operador', 'AX' => 1],
            ['Id' => 3, 'Folio' => 'F-1', 'Hilos' => 640, 'CveEmpl1' => '7', 'NomEmpl1' => 'Operador', 'AX' => 0],
        ]);

        $this->withoutVite()->actingAs($this->capturista())->get(self::URD.'?orden_id=1')->assertOk();

        $oficial = $db->table('UrdProduccionUrdido')->orderBy('Id')->pluck('CveEmpl1', 'Id');
        $this->assertSame('7', (string) $oficial[1], 'fila con julio/peso');
        $this->assertSame('7', (string) $oficial[2], 'fila en AX');
        $this->assertSame('100', (string) $oficial[3], 'fila vacía: la toma quien abre');
    }

    public function test_engomado_lector_no_muta_la_orden_al_abrirla(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('EngProgramaEngomado')->insert(['Id' => 1, 'Folio' => 'F-1', 'Status' => 'Programado', 'NoTelas' => 2]);

        $this->withoutVite()
            ->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso'], 43 => ['acceso']]))
            ->get('/engomado/modulo-produccion-engomado?orden_id=1')
            ->assertOk();

        $this->assertSame('Programado', $db->table('EngProgramaEngomado')->where('Id', 1)->value('Status'));
        $this->assertSame(0, $db->table('EngProduccionEngomado')->count());
    }

    /**
     * SQL Server 2008 convierte '' a time 00:00: "HoraInicial = ''" atrapaba filas que
     * arrancaron a medianoche (turno 3). sqlite no reproduce la conversión: se vigila el patrón.
     */
    public function test_nadie_compara_hora_inicial_con_cadena_vacia(): void
    {
        foreach (['app/Traits/ProduccionTrait.php', 'app/Http/Controllers/Urdido/Configuracion/ModuloProduccionUrdidoController.php', 'app/Livewire/UrdEng/EdicionOrden.php'] as $archivo) {
            $this->assertStringNotContainsString("orWhere('HoraInicial', '')", file_get_contents(base_path($archivo)), $archivo);
        }
    }
}
