<?php

namespace Tests\Feature\ProgramaUrdEng\Concerns;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\AuditoriaUrdEng;
use App\Models\Urdido\UrdConsumoHilo;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;

/**
 * Base de los tests de Programa Urd / Eng (19-05): la de Urdido/Engomado (sqlite, tablas por
 * modelo, permisos) más las tablas del alta de órdenes y los folios de SSYSFoliosSecuencias.
 */
trait ModuloProgramaUrdEng
{
    use ModuloUrdEng;

    /** idrol de 'Programa Urd / Eng' (routes/modules/programa-urd-eng.php). */
    protected const MODULO_PROGRAMA_URD_ENG = 52;

    /** Tablas que escribe el alta de órdenes (CrearOrdenesService y Karl Mayer). */
    protected function prepararTablasDeOrdenes(): void
    {
        foreach ([UrdProgramaUrdido::class, EngProgramaEngomado::class, AuditoriaUrdEng::class, UrdConsumoHilo::class, UrdJuliosOrden::class] as $modelo) {
            $this->tablaDe($modelo);
        }

        // tablaDe() duplicaría created_at/updated_at (están en $casts y el modelo usa timestamps).
        Schema::connection('sqlsrv')->create('tej_inventario_telares', function (Blueprint $t): void {
            $t->increments('id');
            foreach (['no_telar', 'status', 'tipo', 'cuenta', 'hilo', 'no_julio', 'no_orden', 'salon', 'tipo_atado', 'localidad'] as $c) {
                $t->string($c)->nullable();
            }
            $t->float('calibre')->nullable();
            $t->float('metros')->nullable();
            $t->date('fecha')->nullable();
            $t->integer('turno')->nullable();
            $t->boolean('Reservado')->nullable();
            $t->boolean('Programado')->nullable();
            $t->timestamps();
        });

        $this->prepararFolios();
    }

    /** SSYSFoliosSecuencias en el esquema dbo e INFORMATION_SCHEMA para nextFolio(). */
    protected function prepararFolios(): void
    {
        $db = DB::connection('sqlsrv');
        $db->statement("ATTACH DATABASE ':memory:' AS dbo");
        $db->statement('CREATE TABLE dbo."SSYSFoliosSecuencias" ("Id" INTEGER PRIMARY KEY AUTOINCREMENT, "modulo" TEXT, "prefijo" TEXT, "consecutivo" INTEGER)');
        $db->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $db->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $columna) {
            $db->table('INFORMATION_SCHEMA.COLUMNS')->insert(['TABLE_SCHEMA' => 'dbo', 'TABLE_NAME' => 'SSYSFoliosSecuencias', 'COLUMN_NAME' => $columna]);
        }
        $db->table('dbo.SSYSFoliosSecuencias')->insert([
            ['modulo' => 'CambioHilo', 'prefijo' => 'CH', 'consecutivo' => 10],
            ['modulo' => 'URD/ENG', 'prefijo' => 'UE', 'consecutivo' => 20],
        ]);
    }

    /**
     * Filas de una tabla sin la PK, en orden de inserción (para comparar antes/después).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function filasDe(string $tabla, string $pk = 'Id'): array
    {
        return DB::connection('sqlsrv')->table($tabla)->orderBy($pk)->get()
            ->map(fn ($fila) => array_diff_key((array) $fila, [$pk => true]))
            ->all();
    }
}
