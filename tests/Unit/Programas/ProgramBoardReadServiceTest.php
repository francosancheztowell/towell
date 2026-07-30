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

        $board = app(ProgramBoardReadService::class)->board(ProgramaModulo::Urdido);

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

        $this->assertCount(2, $queries);
        $this->assertTrue($board['lanes'][0]['orders'][0]['urdido_finished']);
        $this->assertFalse($board['lanes'][1]['orders'][0]['urdido_finished']);
    }
}
