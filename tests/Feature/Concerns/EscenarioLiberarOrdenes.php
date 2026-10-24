<?php

namespace Tests\Feature\Concerns;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Sistema\SSYSFoliosSecuencia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Escenario de LiberarOrdenesController::liberar() sobre sqlite: dobles de AX (L.Mat, tipos de
 * hilo, catálogo de flogs), ReqProgramaTejido con todas las columnas que liberar() lee/escribe,
 * CatCodificados y las tablas de apoyo. La clase que lo usa también usa UsesSqlsrvSqlite y llama
 * prepararLiberar() en setUp y limpiarLiberar() en tearDown.
 */
trait EscenarioLiberarOrdenes
{
    protected function prepararLiberar(): void
    {
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        // liberar() revalida el L.Mat contra AX (BOMTABLE + BOMVERSION). Sin este
        // doble en sqlite la prueba pegaba al TI_PRO real y siempre daba 422.
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        Schema::connection('sqlsrv_ti')->create('BOMTABLE', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('NAME')->nullable();
            $table->string('ITEMGROUPID')->nullable();
            $table->string('TWINVENTSIZEID')->nullable();
            $table->string('TWSALON')->nullable();
            $table->integer('Vigente')->default(1);
        });

        Schema::connection('sqlsrv_ti')->create('BOMVERSION', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('ITEMID');
        });

        // index() lee los tipos de hilo del catálogo de AX.
        Schema::connection('sqlsrv_ti')->create('INVENTTABLE', function (Blueprint $table) {
            $table->string('TwTipoHiloId')->nullable();
        });

        // Catálogo de flogs: item+talla (de cualquier artículo, pese al nombre de la tabla
        // en AX) cuyos renglones deciden si la orden lleva flog. Se siembra por prueba.
        Schema::connection('sqlsrv_ti')->create('TwArticulosFelpas', function (Blueprint $table) {
            $table->string('ITEMID');
            $table->string('INVENTSIZEID')->nullable();
            $table->string('ITEMNAME')->nullable();
        });

        // L.Mat válido para el registro base (IT100 / STD / JACQUARD).
        $this->sembrarBomCrudo('BOM-CRUDO-01', 'IT100', 'STD');
        $this->crearTablaPrograma();
        $this->crearTablasDeApoyo();
    }

    /** Esquema mínimo de ReqProgramaTejido con TODAS las columnas que liberar() asigna/lee. */
    private function crearTablaPrograma(): void
    {
        $schema = Schema::connection('sqlsrv');

        // Esquema mínimo con TODAS las columnas que liberar() asigna/lee
        // (incluye auditoría, porque AuditoriaHelper consulta Schema::getColumnListing).
        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('Prioridad')->nullable();
            $table->date('Programado')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->integer('LargoCrudo')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('NoExisteBase')->nullable();
            $table->float('Ancho')->nullable();
            $table->float('Repeticiones')->nullable();
            $table->float('PesoRollo')->nullable();
            $table->integer('SaldoMarbete')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->float('RollosProgramados')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('Densidad')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
            $table->string('HiloAX')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->string('Observaciones')->nullable();
            $table->string('CambioHilo')->nullable();
            $table->boolean('CreaProd')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->float('EficienciaSTD')->nullable();
            $table->string('CategoriaCalidad')->nullable();
            $table->string('CustName')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
            $table->string('TipoPedido')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->string('OrdPrincipal', 15)->nullable();
            $table->string('OrdCompartida', 15)->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->string('HoraCreacion')->nullable();
            $table->string('UsuarioCrea')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->string('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->integer('Posicion')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->string('Ultimo')->nullable();
        });
    }

    private function crearTablasDeApoyo(): void
    {
        $schema = Schema::connection('sqlsrv');

        // Tabla real del modelo ReqPesosRollosTejido (ojo: singular "Rollo") — fuente
        // del peso rollo maestro cuando el request no manda pesoRollo.
        $schema->create('ReqPesosRolloTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->float('PesoRollo')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->date('FechaModificacion')->nullable();
        });

        // Columnas que liberar() consulta (validación de unicidad, resolverCodigoDibujo) y
        // sincroniza vía actualizarCatCodificados (payload filtrado por getColumnListing).
        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
            $table->string('HiloAX')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->string('CambioRepaso')->nullable();
            $table->float('Densidad')->nullable();
            $table->string('Obs5')->nullable();
            $table->boolean('CreaProd')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->string('CategoriaCalidad')->nullable();
            $table->string('CustName')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
            // Espeja el DEFAULT ((1)) de dbo.CatCodificados: toda orden lleva flog salvo
            // que el usuario lo baje a 0 en un renglón del catálogo.
            $table->boolean('AsignarFlogs')->default(true);
            $table->float('PesoMuestra')->nullable();
            $table->string('OrdPrincipal', 15)->nullable();
            $table->string('OrdCompartida', 15)->nullable();
            $table->integer('OrdCompartidaLider')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->string('HoraCreacion')->nullable();
            $table->string('UsuarioCrea')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->string('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });

        // Consultada por actualizarReqModelosCodificados (y por el paso de Excel post-commit).
        $schema->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->dateTime('FechaTejido')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->string('OrdPrincipal', 15)->nullable();
        });

        // El observer de ReqProgramaTejido puede intentar regenerar líneas diarias.
        $schema->create('ReqProgramaTejidoLine', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('ProgramaId')->nullable();
            $table->date('Fecha')->nullable();
            $table->float('Cantidad')->nullable();
            $table->float('Kilos')->nullable();
            $table->float('Aplicacion')->nullable();
            $table->float('Trama')->nullable();
            $table->float('Combina1')->nullable();
            $table->float('Combina2')->nullable();
            $table->float('Combina3')->nullable();
            $table->float('Combina4')->nullable();
            $table->float('Combina5')->nullable();
            $table->float('Pie')->nullable();
            $table->float('Rizo')->nullable();
            $table->float('MtsRizo')->nullable();
            $table->float('MtsPie')->nullable();
        });
    }

    protected function limpiarLiberar(): void
    {
        Carbon::setTestNow();
        $schema = Schema::connection('sqlsrv');
        foreach (['ReqProgramaTejidoLine', 'ReqModelosCodificados', 'CatCodificados', 'ReqPesosRolloTejido', 'ReqProgramaTejido'] as $tabla) {
            $schema->dropIfExists($tabla);
        }
        foreach (['BOMTABLE', 'BOMVERSION', 'TwArticulosFelpas', 'INVENTTABLE'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
    }

    /**
     * Alta de un L.Mat CRUDO en el doble de AX.
     *
     * @param  int  $versiones  Filas en BOMVERSION. AX guarda varias por item, y ese
     *                          era el origen de la duplicación que rompía el autollenado.
     */
    private function sembrarBomCrudo(
        string $bomId,
        string $itemId,
        string $inventSizeId,
        string $salon = 'JACQUARD',
        int $versiones = 1
    ): void {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => $bomId,
            'NAME' => 'LISTA MATERIALES '.$bomId,
            'ITEMGROUPID' => 'CRUDO',
            'TWINVENTSIZEID' => $inventSizeId,
            'TWSALON' => $salon,
            'Vigente' => 1,
        ]);

        for ($i = 0; $i < $versiones; $i++) {
            DB::connection('sqlsrv_ti')->table('BOMVERSION')->insert([
                'BOMID' => $bomId,
                'ITEMID' => $itemId.'-1',
            ]);
        }
    }

    /**
     * Inserta un registro base listo para liberar (vía query builder: sin observers).
     */
    private function sembrarRegistro(array $overrides = []): int
    {
        return DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId(array_merge([
            'NoProduccion' => null,
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'TamanoClave' => 'MB-ARIA',
            'NombreProducto' => 'MB-ARIA SC',
            'PesoCrudo' => 455,
            'NoTiras' => 3,
            'LargoCrudo' => 142,
            'SaldoPedido' => 12891,
            'TotalPedido' => 12891,
            'CustName' => 'CLIENTE X',
            'FechaInicio' => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
            'EnProceso' => 0,
        ], $overrides));
    }

    /** $ruta decide la superficie (ProgramaTejidoSurface::actual() lee el request en curso). */
    private function liberar(array $registros, string $ruta = '/planeacion/programa-tejido/liberar-ordenes/procesar')
    {
        $request = Request::create($ruta, 'POST', [
            'registros' => $registros,
        ]);
        app()->instance('request', $request);

        return (new LiberarOrdenesController)->liberar($request);
    }

    /** Secuencias de folio como en ProdTowel: Planeacion sin prefijo (37070) y Muestras con M (3174). */
    private function sembrarFolios(): void
    {
        $this->createTablaDbo('SSYSFoliosSecuencias', ['Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'modulo' => 'TEXT', 'prefijo' => 'TEXT', 'consecutivo' => 'INTEGER']);
        $db = DB::connection('sqlsrv');
        // SSYSFoliosSecuencia pregunta sus columnas a INFORMATION_SCHEMA (igual que BpmTest).
        $db->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $db->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $columna) {
            $db->table('INFORMATION_SCHEMA.COLUMNS')->insert(['TABLE_SCHEMA' => 'dbo', 'TABLE_NAME' => 'SSYSFoliosSecuencias', 'COLUMN_NAME' => $columna]);
        }
        SSYSFoliosSecuencia::flushColumnMap();
        $db->table('dbo.SSYSFoliosSecuencias')->insert([
            ['modulo' => 'Planeacion', 'prefijo' => null, 'consecutivo' => 37070],
            ['modulo' => 'Muestras', 'prefijo' => 'M', 'consecutivo' => 3174],
        ]);
    }

    /** @return array<string, int> consecutivo por módulo */
    private function consecutivos(): array
    {
        return DB::connection('sqlsrv')->table('dbo.SSYSFoliosSecuencias')->pluck('consecutivo', 'modulo')->map(fn ($v) => (int) $v)->all();
    }

    private function itemLiberar(int $id, string $orden): array
    {
        return ['id' => $id, 'bomId' => 'BOM-CRUDO-01', 'bomName' => 'LISTA MATERIALES CRUDO 01', 'noProduccion' => $orden];
    }

    private function attachDboFlogTables(): void
    {
        $conexion = DB::connection('sqlsrv_ti');
        if (! in_array('dbo', array_column($conexion->select('PRAGMA database_list'), 'name'), true)) {
            $conexion->statement("ATTACH DATABASE ':memory:' AS dbo");
        }

        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsTable" (
            "IDFLOG" TEXT, "NAMEPROYECT" TEXT, "CUSTNAME" TEXT, "ESTADOFLOG" INTEGER
        )');
        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsItemLine" (
            "IDFLOG" TEXT, "ITEMID" TEXT, "INVENTSIZEID" TEXT, "ESTADOLINEA" INTEGER DEFAULT 0
        )');
        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsCustomer" (
            "IdFlog" TEXT, "CustName" TEXT, "CategoriaCalidad" TEXT
        )');
    }
}
