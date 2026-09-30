<?php

declare(strict_types=1);

namespace Tests\Unit\Programas;

use App\Services\Programas\ProgramBoardReadService;
use App\Support\Programas\ProgramaModulo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgramBoardReadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlsrv');

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('Fibra')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->float('Metros')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaProg')->nullable();
            $table->integer('Prioridad')->nullable();
            $table->text('Observaciones')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->string('Calidad')->nullable();
            $table->string('CalidadComentario')->nullable();
            $table->string('AutorizaCalidad')->nullable();
            $table->dateTime('FechaCalidad')->nullable();
        });

        Schema::connection('sqlsrv')->create('EngProgramaEngomado', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('Fibra')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->float('Metros')->nullable();
            $table->string('MaquinaEng')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaProg')->nullable();
            $table->integer('Prioridad')->nullable();
            $table->text('Observaciones')->nullable();
            $table->string('BomFormula')->nullable();
        });

        // El servicio marca que folios ya tienen produccion capturada en AX; sin estas dos
        // tablas la consulta truena antes de llegar a las aserciones.
        foreach (['UrdProduccionUrdido', 'EngProduccionEngomado'] as $tabla) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $table): void {
                $table->increments('Id');
                $table->string('Folio')->nullable();
                $table->boolean('AX')->default(false);
            });
        }

        // ManFallasParos usa el prefijo dbo.: sqlite lo lee como esquema.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        DB::connection('sqlsrv')->statement('CREATE TABLE dbo.ManFallasParos (Id INTEGER PRIMARY KEY, Folio TEXT, Estatus TEXT, MaquinaId TEXT, Hora TEXT, Falla TEXT, TipoFallaId TEXT)');
    }

    public function test_urdido_is_sorted_in_sql_and_grouped_by_machine(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            [
                'Folio' => 'URD-002',
                'RizoPie' => 'Rizo',
                'InventSizeId' => '20/1',
                'Fibra' => 'Algodón',
                'Metros' => 1200,
                'MaquinaId' => 'Mc Coy 1',
                'Status' => 'Programado',
                'Prioridad' => 2,
                'CreatedAt' => '2026-07-29 08:00:00',
                'Calidad' => null,
            ],
            [
                'Folio' => 'URD-001',
                'RizoPie' => 'Pie',
                'InventSizeId' => '16/1',
                'Fibra' => 'Poliéster',
                'Metros' => 800,
                'MaquinaId' => 'Mc Coy 1',
                'Status' => 'En Proceso',
                'Prioridad' => 1,
                'CreatedAt' => '2026-07-29 07:00:00',
                'Calidad' => 'A',
            ],
            [
                'Folio' => 'URD-KM',
                'RizoPie' => null,
                'InventSizeId' => null,
                'Fibra' => null,
                'Metros' => 500,
                'MaquinaId' => 'Karl Mayer',
                'Status' => 'Parcial',
                'Prioridad' => 3,
                'CreatedAt' => '2026-07-29 09:00:00',
                'Calidad' => null,
            ],
            [
                'Folio' => 'IGNORAR',
                'RizoPie' => null,
                'InventSizeId' => null,
                'Fibra' => null,
                'Metros' => null,
                'MaquinaId' => 'Sin catálogo',
                'Status' => 'Programado',
                'Prioridad' => 4,
                'CreatedAt' => null,
                'Calidad' => null,
            ],
        ]);

        // Mantenimiento escribe "Mc Coy 1" y "KM1"; los carriles dicen "MC Coy 1" y "Karl Mayer".
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            ['Folio' => 'PF-0', 'Estatus' => 'Activo', 'MaquinaId' => 'MC COY 1', 'Hora' => '08:15:00', 'Falla' => null, 'TipoFallaId' => 'ELEC'],
            ['Folio' => 'PF-1', 'Estatus' => 'Activo', 'MaquinaId' => 'Mc Coy 1', 'Hora' => '10:32:00', 'Falla' => 'Mecánica', 'TipoFallaId' => 'MEC'],
            ['Folio' => 'PF-2', 'Estatus' => 'Activo', 'MaquinaId' => 'KM1', 'Hora' => '11:00:00', 'Falla' => '43', 'TipoFallaId' => null],
            ['Folio' => 'PF-3', 'Estatus' => 'Terminado', 'MaquinaId' => 'Mc Coy 2', 'Hora' => '09:00:00', 'Falla' => 'x', 'TipoFallaId' => 'x'],
        ]);

        $board = app(ProgramBoardReadService::class)->board(ProgramaModulo::Urdido);

        // Dos paros activos en MC Coy 1: manda el más reciente y se cuentan ambos.
        $this->assertSame([
            'folio' => 'PF-1', 'hora' => '10:32', 'falla' => 'MEC', 'total' => 2,
            'detalle' => ['PF-1 · 10:32 · MEC', 'PF-0 · 08:15 · ELEC'],
        ], $board['lanes'][0]['paro']);
        $this->assertNull($board['lanes'][1]['paro']);
        $this->assertSame('43', $board['lanes'][3]['paro']['falla']);
        $this->assertSame(['URD-001', 'URD-002'], array_column($board['lanes'][0]['orders'], 'folio'));
        $this->assertSame('URD-KM', $board['lanes'][3]['orders'][0]['folio']);
        $this->assertSame(3, $board['summary']['total']);
        $this->assertSame(2500.0, $board['summary']['metros']);
        $this->assertSame('A', $board['lanes'][0]['orders'][0]['quality']);
    }

    public function test_engomado_uses_one_batched_urdido_status_query(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Folio' => 'ENG-001',
            'MaquinaId' => 'Mc Coy 1',
            'Status' => 'Finalizado',
        ]);
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            [
                'Folio' => 'ENG-001',
                'MaquinaEng' => 'West Point 2',
                'Status' => 'Programado',
                'Prioridad' => 1,
                'FechaProg' => '2026-07-29',
            ],
            [
                'Folio' => 'ENG-002',
                'MaquinaEng' => 'West Point 3',
                'Status' => 'Programado',
                'Prioridad' => 2,
                'FechaProg' => '2026-07-29',
            ],
        ]);

        DB::connection('sqlsrv')->enableQueryLog();
        $board = app(ProgramBoardReadService::class)->board(
            ProgramaModulo::Engomado,
            'ENG',
            'Programado'
        );
        $queries = DB::connection('sqlsrv')->getQueryLog();

        // Lo que importa es que el estatus de urdido se resuelva de un solo golpe para los dos
        // folios, no uno por orden. Contar el total de consultas ataba el test a cuantas hace el
        // board en total, que ya incluye la del bloqueo por AX.
        $consultasUrdido = array_filter(
            $queries,
            fn (array $query): bool => str_contains($query['query'], 'UrdProgramaUrdido')
        );

        $this->assertCount(1, $consultasUrdido);
        $this->assertTrue($board['lanes'][0]['orders'][0]['urdido_finished']);
        $this->assertFalse($board['lanes'][1]['orders'][0]['urdido_finished']);
    }
}
