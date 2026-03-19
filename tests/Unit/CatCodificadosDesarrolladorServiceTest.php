<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\CatCodificadosDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\NotificacionTelegramDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Models\Planeacion\Catalogos\CatCodificados;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class CatCodificadosDesarrolladorServiceTest extends TestCase
{
    protected CatCodificadosDesarrolladorService $catCodificadosService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlsrv');
        Config::set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->string('CodificacionModelo')->nullable();
            $table->string('RespInicio')->nullable();
            $table->string('HrInicio')->nullable();
            $table->string('HrTermino')->nullable();
            $table->integer('MinutosCambio')->nullable();
            $table->float('TramaAnchoPeine')->nullable();
            $table->float('AnchoPeineTrama')->nullable();
            $table->integer('LogLuchaTotal')->nullable();
            $table->integer('LongitudLuchaTot')->nullable();
            $table->integer('Total')->nullable();
            $table->integer('TotalPasadasDibujo')->nullable();
            $table->string('NumeroJulioRizo')->nullable();
            $table->string('NumeroJulioPie')->nullable();
            $table->string('JulioRizo')->nullable();
            $table->string('JulioPie')->nullable();
            $table->integer('EficienciaInicio')->nullable();
            $table->integer('EficienciaFinal')->nullable();
            $table->integer('EfiInicial')->nullable();
            $table->integer('EfiFinal')->nullable();
            $table->float('DesperdicioTrama')->nullable();
            $table->dateTime('FechaCumplimiento')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
        });

        $this->catCodificadosService = new CatCodificadosDesarrolladorService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_resolve_canonical_devuelve_el_id_mas_alto_sin_eliminar_duplicados(): void
    {
        CatCodificados::query()->create(['OrdenTejido' => 'ORD-500', 'CodigoDibujo' => 'OLD-A']);
        $principal = CatCodificados::query()->create(['OrdenTejido' => 'ORD-500', 'CodigoDibujo' => 'OLD-B']);

        $registro = $this->catCodificadosService->resolveCanonical('ORD-500');

        $this->assertNotNull($registro);
        $this->assertSame($principal->Id, $registro->Id);
        $this->assertSame(2, CatCodificados::query()->where('OrdenTejido', 'ORD-500')->count());
    }

    public function test_actualizar_cat_codificados_crea_registro_si_no_existe(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-19 06:40:00'));

        $registro = $this->invokeActualizarCatCodificados([
            'NoProduccion' => 'ORD-700',
            'NumeroJulioRizo' => 'JR-1',
            'NumeroJulioPie' => 'JP-1',
            'TotalPasadasDibujo' => 120,
            'Desarrollador' => 'FER',
            'HoraInicio' => '06:00',
            'HoraFinal' => '06:40',
            'TramaAnchoPeine' => 55,
            'EficienciaInicio' => 75,
            'EficienciaFinal' => 80,
            'DesperdicioTrama' => 1.5,
        ], [
            'salonDestino' => 'JAC',
            'telarDestino' => '101',
        ], 'COD-700.JC5', 40, 250);

        $this->assertNotNull($registro);
        $this->assertSame(1, CatCodificados::query()->where('OrdenTejido', 'ORD-700')->count());

        $guardado = CatCodificados::query()->where('OrdenTejido', 'ORD-700')->firstOrFail();

        $this->assertSame(101, (int) $guardado->TelarId);
        $this->assertSame('JAC', $guardado->Departamento);
        $this->assertSame('COD-700.JC5', $guardado->CodigoDibujo);
        $this->assertSame('2026-03-19 06:00:00', optional($guardado->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-19 06:40:00', optional($guardado->FechaFinaliza)->format('Y-m-d H:i:s'));
    }

    public function test_actualizar_cat_codificados_actualiza_registro_mas_reciente_y_no_crea_otro(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-19 07:00:00'));

        $anterior = CatCodificados::query()->create([
            'OrdenTejido' => 'ORD-800',
            'TelarId' => '100',
            'CodigoDibujo' => 'OLD-1',
        ]);

        $principal = CatCodificados::query()->create([
            'OrdenTejido' => 'ORD-800',
            'TelarId' => '200',
            'CodigoDibujo' => 'OLD-2',
        ]);

        $registro = $this->invokeActualizarCatCodificados([
            'NoProduccion' => 'ORD-800',
            'NumeroJulioRizo' => 'JR-9',
            'NumeroJulioPie' => 'JP-9',
            'TotalPasadasDibujo' => 180,
            'Desarrollador' => 'FER',
            'HoraInicio' => '06:20',
            'HoraFinal' => '07:00',
            'TramaAnchoPeine' => 60,
            'EficienciaInicio' => 70,
            'EficienciaFinal' => 90,
            'DesperdicioTrama' => 2.5,
        ], [
            'salonDestino' => 'SMIT',
            'telarDestino' => '305',
        ], 'COD-800', 40, 300);

        $this->assertNotNull($registro);
        $this->assertSame($principal->Id, $registro->Id);
        $this->assertSame(2, CatCodificados::query()->where('OrdenTejido', 'ORD-800')->count());

        $guardado = CatCodificados::query()->whereKey($principal->Id)->firstOrFail();
        $sinTocar = CatCodificados::query()->whereKey($anterior->Id)->firstOrFail();

        $this->assertSame($principal->Id, $guardado->Id);
        $this->assertSame(305, (int) $guardado->TelarId);
        $this->assertSame('SMIT', $guardado->Departamento);
        $this->assertSame('COD-800', $guardado->CodigoDibujo);
        $this->assertSame('JR-9', $guardado->JulioRizo);
        $this->assertSame(180, $guardado->Total);
        $this->assertSame('2026-03-19 06:20:00', optional($guardado->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-19 07:00:00', optional($guardado->FechaFinaliza)->format('Y-m-d H:i:s'));
        $this->assertSame('OLD-1', $sinTocar->CodigoDibujo);
    }

    public function test_actualizar_cat_codificados_ajusta_fecha_finaliza_al_siguiente_dia_si_la_hora_final_es_menor(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-19 23:50:00'));

        $registro = $this->invokeActualizarCatCodificados([
            'NoProduccion' => 'ORD-900',
            'NumeroJulioRizo' => 'JR-3',
            'NumeroJulioPie' => 'JP-3',
            'TotalPasadasDibujo' => 90,
            'Desarrollador' => 'FER',
            'HoraInicio' => '23:30',
            'HoraFinal' => '01:10',
            'TramaAnchoPeine' => 58,
            'EficienciaInicio' => 72,
            'EficienciaFinal' => 77,
            'DesperdicioTrama' => 1.2,
        ], [
            'salonDestino' => 'JAC',
            'telarDestino' => '111',
        ], 'COD-900.JC5', 100, 180);

        $this->assertNotNull($registro);

        $guardado = CatCodificados::query()->where('OrdenTejido', 'ORD-900')->firstOrFail();

        $this->assertSame('2026-03-19 23:30:00', optional($guardado->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-20 01:10:00', optional($guardado->FechaFinaliza)->format('Y-m-d H:i:s'));
    }

    private function invokeActualizarCatCodificados(
        array $validated,
        array $contextoDestino,
        string $codigoDibujo,
        ?int $minutosCambio,
        ?int $longitudLuchaTot
    ): ?CatCodificados {
        $movimientoService = new MovimientoDesarrolladorService($this->catCodificadosService);
        $telegramService = $this->createMock(NotificacionTelegramDesarrolladorService::class);

        $service = new ProcesarDesarrolladorService(
            $movimientoService,
            $telegramService,
            $this->catCodificadosService
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('actualizarCatCodificados');
        $method->setAccessible(true);

        /** @var CatCodificados|null $registro */
        $registro = $method->invoke(
            $service,
            $validated,
            $contextoDestino,
            [],
            [],
            $codigoDibujo,
            $minutosCambio,
            $longitudLuchaTot,
            null,
            null
        );

        return $registro;
    }
}
