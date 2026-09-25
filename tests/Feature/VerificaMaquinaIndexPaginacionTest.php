<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Mecanicos\VerificaMaquina\Index;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SiembraPermisos;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * ERP-F0-10: produccion es SQL Server 2008 R2, que no entiende OFFSET … FETCH. El
 * ->paginate() de Laravel lo emitia desde la pagina 2 de Estado de Maquina. Sqlite si
 * acepta OFFSET, asi que la guarda es el SQL registrado, no que la consulta truene.
 */
class VerificaMaquinaIndexPaginacionTest extends TestCase
{
    use SiembraPermisos;
    use UsesSqlsrvSqlite;

    /** @var array<int, string> */
    private array $sql = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->useSqlsrvSqlite();
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('MecVerificaMaquinaTable', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Fecha')->nullable();
            $table->integer('TurnoRecibe')->nullable();
            $table->string('CveOperador')->nullable();
            $table->string('NomOperador')->nullable();
            $table->string('Estatus')->nullable();
            $table->string('HoraInicio')->nullable();
            $table->string('HoraFin')->nullable();
        });

        $filas = [];
        for ($i = 1; $i <= 20; $i++) {
            $filas[] = [
                'Folio' => sprintf('VM%05d', $i),
                'Fecha' => '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'TurnoRecibe' => 1,
                'CveOperador' => '1001',
                'NomOperador' => 'Operador',
                'Estatus' => 'Activo',
                'HoraInicio' => '08:00:00',
                'HoraFin' => null,
            ];
        }
        DB::connection('sqlsrv')->table('MecVerificaMaquinaTable')->insert($filas);

        $this->actingAs($this->createUsuario(), 'web');
        // 1102 = Estado Maquina (routes/modules/mecanicos.php); el componente resuelve por nombre.
        $this->sembrarPermisos((int) Auth::id(), [1102 => 'Estado Maquina']);
    }

    public function test_pagina_2_trae_el_resto_sin_offset(): void
    {
        DB::listen(function ($query) {
            $this->sql[] = $query->sql;
        });

        $componente = Livewire::test(Index::class)->call('gotoPage', 2);

        $verificaciones = $componente->viewData('verificaciones');

        $this->assertSame(20, $verificaciones->total());
        $this->assertSame(2, $verificaciones->currentPage());
        $this->assertCount(5, $verificaciones->items());
        // Orden por Fecha desc: la pagina 2 son los 5 mas viejos.
        $this->assertSame('VM00005', $verificaciones->items()[0]->Folio);

        foreach ($this->sql as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('offset', $sql, 'SQL Server 2008 R2 no soporta OFFSET: '.$sql);
        }
    }
}
