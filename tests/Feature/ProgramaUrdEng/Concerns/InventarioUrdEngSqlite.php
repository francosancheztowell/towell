<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng\Concerns;

use App\Models\Inventario\InvTelasReservadas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;

/**
 * Inventario y reservas de Programa Urd-Eng (19-05 p2.4) sobre sqlite en memoria:
 * tej_inventario_telares, InvTelasReservadas, TejNotificaTejedor y UrdProgramaUrdido.
 *
 * TI-PRO (sqlsrv_ti) no se ejecuta en sqlite ('WITH (NOLOCK)', 'SET TRANSACTION'): los tests
 * sustituyen InventarioReservasService::queryDisponibleFromTiPro() con filas fijas.
 */
trait InventarioUrdEngSqlite
{
    use ModuloUrdEng;

    protected function prepararInventario(): void
    {
        $this->prepararSqlite();

        $pdo = DB::connection('sqlsrv')->getPdo();
        // InventarioTelaresService::baseQuery() usa DATE_FORMAT fuera de SQL Server.
        $pdo->sqliteCreateFunction('DATE_FORMAT', fn ($v) => $v === null ? null : substr((string) $v, 0, 10), 2);

        $schema = Schema::connection('sqlsrv');

        $schema->create('tej_inventario_telares', function (Blueprint $t): void {
            $t->increments('id');
            foreach (['no_telar', 'tipo', 'status', 'hilo', 'cuenta', 'localidad', 'ConfigId', 'InventSizeId',
                'InventColorId', 'LoteProveedor', 'NoProveedor', 'tipo_atado', 'salon', 'turno', 'horaParo',
                'no_julio', 'no_julio2', 'no_julio3', 'no_julio4', 'no_orden', 'no_orden2', 'no_orden3', 'no_orden4'] as $c) {
                $t->string($c)->nullable();
            }
            $t->float('metros')->nullable();
            $t->float('calibre')->nullable();
            $t->boolean('Reservado')->nullable();
            $t->boolean('Programado')->nullable();
            $t->date('fecha')->nullable();
            $t->timestamps();
        });

        $schema->create('InvTelasReservadas', function (Blueprint $t): void {
            $t->increments('Id');
            foreach (['NoTelarId', 'SalonTejidoId', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
                'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId', 'Tipo', 'Status',
                'JulioPrincipal', 'OrdenPrincipal', 'NumeroEmpleado', 'NombreEmpl'] as $c) {
                $t->string($c)->nullable();
            }
            $t->float('Metros')->nullable();
            $t->float('InventQty')->nullable();
            $t->dateTime('ProdDate')->nullable();
            $t->date('Fecha')->nullable();
            $t->integer('Turno')->nullable();
            $t->integer('TejInventarioTelaresId')->nullable();
            $t->timestamps();
        });

        $schema->create('TejNotificaTejedor', function (Blueprint $t): void {
            $t->increments('id');
            foreach (['telar', 'tipo', 'no_julio', 'no_orden', 'hora', 'NomEmpleado', 'NoEmpleado'] as $c) {
                $t->string($c)->nullable();
            }
            $t->date('Fecha')->nullable();
            $t->boolean('Reserva')->nullable();
        });

        $schema->create('UrdProgramaUrdido', function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('Folio')->nullable();
            $t->string('RizoPie')->nullable();
        });
    }

    /** @param array<string, mixed> $campos */
    protected function telar(array $campos): int
    {
        return (int) DB::connection('sqlsrv')->table('tej_inventario_telares')->insertGetId($campos + ['status' => 'Activo']);
    }

    /** @param array<string, mixed> $campos */
    protected function reservaActiva(array $campos): int
    {
        return (int) DB::connection('sqlsrv')->table('InvTelasReservadas')->insertGetId($campos + ['Status' => 'Reservado']);
    }

    /** @param array<string, mixed> $campos */
    protected function aviso(array $campos): int
    {
        return (int) DB::connection('sqlsrv')->table('TejNotificaTejedor')->insertGetId($campos + ['Reserva' => 0]);
    }

    /**
     * Hace que el siguiente INSERT de InvTelasReservadas falle como SQL Server con
     * el código de driver dado (2627/2601 = índice único).
     */
    protected function fallarInsertReserva(int $codigoDriver): void
    {
        InvTelasReservadas::creating(function () use ($codigoDriver): void {
            $pdo = new PDOException('SQLSTATE[23000]: simulado');
            $pdo->errorInfo = ['23000', $codigoDriver, 'simulado'];

            throw new QueryException('sqlsrv', 'insert into [InvTelasReservadas]', [], $pdo);
        });
    }

    protected function tearDownInventario(): void
    {
        InvTelasReservadas::flushEventListeners();
        Model::clearBootedModels();
    }
}
