<?php

namespace Tests\Feature\Planeacion\Concerns;

use App\Livewire\Planeacion\ProgramaTejido\DuplicarDividir;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Planeacion\ProgramaTejido\CatalogoModelos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use ReflectionProperty;

/**
 * Escenario del diálogo Livewire de Duplicar/Dividir/Vincular: superficies con los fixtures de
 * Programa, Modelos (CLV-1 en SMIT), aplicaciones y catálogo de telares.
 * Fixture: Id 1 = SMIT/201, TotalPedido 1000, SaldoPedido 800, Produccion 200, NoProduccion 30001.
 * Grupo 7 = Ids 3 (saldo 600) y 4 (saldo 400). Id 2 = sin NoProduccion.
 */
trait DialogoDuplicarDividir
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function prepararDialogo(): void
    {
        // Caché por request de los modelos: en los tests el proceso sigue vivo entre casos.
        (new ReflectionProperty(CatalogoModelos::class, 'datosArrayCache'))->setValue(null, []);
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        // Primero una tabla dbo.*: adjunta el esquema dbo que usa ReqAplicaciones.
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        foreach ([ReqModelosCodificados::class, ReqAplicaciones::class] as $modelo) {
            $this->createTablaDesdeModelo($modelo);
        }
        Schema::connection('sqlsrv')->create((new URDCatalogoMaquina)->getTable(), function (Blueprint $t): void {
            $t->string('MaquinaId')->primary();
            $t->string('Departamento')->nullable();
        });

        // DividirTejido hace DB::reconnect() tras el commit: se reconecta al mismo PDO en memoria.
        $pdo = DB::connection('sqlsrv')->getPdo();
        DB::extend('sqlsrv', fn (array $config) => new SQLiteConnection($pdo, ':memory:', '', $config));

        // Solo se crea con clave en Modelos del salón y flog vigente: CLV-1 existe en SMIT (no en
        // JACQUARD) y, sin AX en los tests, cualquier flog capturado cuenta como vigente.
        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            DB::table($tabla)->whereIn('Id', [1, 3, 4])->update(['TamanoClave' => 'CLV-1', 'FlogsId' => 'RS-FLOG-1']);
        }
        DB::table('ReqModelosCodificados')->insert(['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'CLV-1']);
        DB::table('URDCatalogoMaquinas')->insert([
            ['Departamento' => 'Itema', 'MaquinaId' => '201'],
            ['Departamento' => 'Smith', 'MaquinaId' => '210'],
            ['Departamento' => 'Jacquard', 'MaquinaId' => '205'],
            ['Departamento' => 'Urdido', 'MaquinaId' => 'MC1'],
        ]);
    }

    private function componente(array $permisos = ['crear'], string $superficie = 'programa')
    {
        $modulo = $superficie === 'muestras' ? 5 : 2;
        $this->actingAs($this->usuarioConPermisos([$modulo => $permisos]));

        return Livewire::test(DuplicarDividir::class, ['superficie' => $superficie]);
    }

    private function filasNuevas(string $tabla = 'ReqProgramaTejido'): array
    {
        return DB::table($tabla)->where('Id', '>', 5)->orderBy('Id')->get()->all();
    }
}
