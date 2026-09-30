<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\InsercionEnBloques;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Límites de SQL Server por INSERT … VALUES: 2 100 parámetros y 1 000 filas (error 10738).
 * Con 2 columnas el tope de parámetros daría 1 049 filas: manda el de filas.
 */
class InsercionEnBloquesTest extends TestCase
{
    use ModuloUrdEng;

    public function test_con_pocas_columnas_no_pasa_de_mil_filas_por_insert(): void
    {
        $this->prepararSqlite();
        Schema::connection('sqlsrv')->create('prueba_bloques', function (Blueprint $t): void {
            $t->increments('id');
            $t->text('a')->nullable();
            $t->text('b')->nullable();
        });
        $modelo = new class extends Model
        {
            protected $connection = 'sqlsrv';

            protected $table = 'prueba_bloques';

            public $timestamps = false;

            protected $fillable = ['a', 'b'];
        };

        $filas = array_map(fn (int $i): array => ['a' => "a{$i}", 'b' => "b{$i}"], range(1, 2500));

        DB::connection('sqlsrv')->enableQueryLog();
        $insertadas = InsercionEnBloques::insertar($modelo::class, $filas);
        $inserts = collect(DB::connection('sqlsrv')->getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'insert'));

        $this->assertSame(2500, $insertadas);
        $this->assertSame([1000, 1000, 500], $inserts->map(fn ($q) => count($q['bindings']) / 2)->values()->all());
        $this->assertSame(2500, DB::connection('sqlsrv')->table('prueba_bloques')->count());
    }
}
