<?php

declare(strict_types=1);

namespace Tests\Feature\Costos;

use App\Models\Costos\CosCuotasReal;
use App\Services\Costos\CostosJulio;
use App\Services\Costos\CuotasUrdidoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CuotasUrdidoServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** @var list<object> movimientos de AX del mes (TWEXPORTATRANSACCIONES vive en el .24) */
    private array $movimientos = [];

    /** @var array<string, string> regla cuenta+centro → tipo */
    private array $reglas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();

        Schema::connection('sqlsrv')->create('CosCuotasReal', function (Blueprint $t) {
            $t->string('Depto', 50)->nullable();
            $t->integer('Año')->nullable();
            $t->integer('Mes')->nullable();
            foreach (CosCuotasReal::columnasValor() as $campo) {
                $t->decimal($campo, 18, 4)->nullable();
            }
        });
        Schema::connection('sqlsrv')->create('UrdProduccionUrdido', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('Folio');
            $t->date('Fecha')->nullable();
            $t->time('HoraInicial')->nullable();
            $t->time('HoraFinal')->nullable();
            foreach (array_keys(CostosJulio::COLUMNAS) as $col) {
                $t->decimal($col, 18, 4)->nullable();
            }
        });
        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('Folio');
            $t->string('MaquinaId')->nullable();
        });

        config()->set('database.default', 'sqlsrv');
        $this->createTablaDbo('ManFallasParos', [ // el modelo lleva el prefijo dbo.
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'Depto' => 'TEXT', 'MaquinaId' => 'TEXT', 'TipoFallaId' => 'TEXT',
            'Fecha' => 'TEXT', 'Hora' => 'TEXT', 'FechaFin' => 'TEXT', 'HoraFin' => 'TEXT',
        ]);

        $this->partialMock(CuotasUrdidoService::class, function ($m) {
            $m->shouldReceive('movimientos')->andReturnUsing(fn () => collect($this->movimientos));
            $m->shouldReceive('reglas')->andReturnUsing(fn () => $this->reglas);
        });
    }

    public function test_duracion_corrige_medianoche_y_am_pm(): void
    {
        $this->assertSame(37.0, CuotasUrdidoService::duracion('19:10:00', '19:47:00'));
        $this->assertSame(176.0, CuotasUrdidoService::duracion('23:43:00', '02:39:00'), 'Cruza la medianoche.');
        $this->assertSame(351.0, CuotasUrdidoService::duracion('17:40:00', '11:31:00'), '17:40→11:31 son 17h51: AM/PM, era 23:31.');
        $this->assertNull(CuotasUrdidoService::duracion(null, '10:00:00'));
    }

    public function test_minutos_del_mes_con_tope_por_folio(): void
    {
        $julio = fn ($folio, $fecha, $ini, $fin) => ['Folio' => $folio, 'Fecha' => $fecha, 'HoraInicial' => $ini, 'HoraFinal' => $fin];
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            $julio('00440', '2026-04-26', '06:41:00', '07:41:00'), // 60
            $julio('00440', '2026-04-26', '07:41:00', '08:31:00'), // 50
            $julio('00440', '2026-04-26', '08:31:00', '09:21:00'), // 50
            $julio('00440', '2026-04-26', '07:58:00', '14:03:00'), // 365 > 3 × mediana (55) → 55
            $julio('00440', '2026-04-27', null, '10:00:00'),       // sin hora → mediana 55
            $julio('00500', '2026-05-01', '10:00:00', '11:00:00'), // otro mes: no cuenta
        ]);

        $this->assertSame(60.0 + 50 + 50 + 55 + 55, app(CuotasUrdidoService::class)->minutos(2026, 4));
    }

    public function test_calcula_sabana_y_cuota_y_guarda_solo_lo_calculado(): void
    {
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            ['Folio' => '00001', 'Fecha' => '2026-04-10', 'HoraInicial' => '10:00:00', 'HoraFinal' => '11:40:00'], // 100 min
        ]);
        $mov = fn ($cuenta, $centro, $tipo, $total) => (object) compact('cuenta', 'centro', 'tipo', 'total');
        $this->movimientos = [
            // Abril: AX trae la clasificación de la cuenta, no el tipo → regla cuenta+centro.
            $mov('702-002-000-0', '003', 'Salarios', 300),
            $mov('702-004-000-0', '003', 'Tiempo Extra', 50),   // sin regla: nómina → MOD
            $mov('702-040-000-0', '003', 'Mantenimientos', 40),
            $mov('702-040-000-0', '005', 'Mantenimientos', 10),
            $mov('702-101-000-0', '005', 'FIJOS', 200),           // ya tipificado (desde junio)
            $mov('999-999-000-0', '003', 'Otros', 7),             // sin regla ni nómina
        ];
        $this->reglas = ['702-002-000-0|003' => 'MOD', '702-040-000-0|003' => 'VARIABLES', '702-040-000-0|005' => 'FIJOS'];
        // Una cuota previa con Prorrateo capturado a mano: no se toca.
        CosCuotasReal::create(['Depto' => 'Urdido', 'Año' => 2026, 'Mes' => 4, 'Minutos' => 3000, 'SabGtosFijos' => 50000, 'ProrrateoFijo' => 3.2278]);

        $r = app(CuotasUrdidoService::class)->actualizar(2026, 4);

        $this->assertSame(7.0, $r['SinClasificar']);
        $c = CosCuotasReal::existente('Urdido', 2026, 4);
        $this->assertSame('100.0000', $c->Minutos);
        $this->assertSame('350.0000', $c->SabMO);
        $this->assertSame('3.5000', $c->MO, '350 ÷ 100 min.');
        $this->assertSame('40.0000', $c->SabGtosVariable);
        $this->assertSame('0.4000', $c->GtosVariables);
        $this->assertSame('210.0000', $c->SabGtosFijos, '200 tipificado + 10 por regla del centro 005.');
        $this->assertSame('2.1000', $c->GtosFijos);
        $this->assertSame('3.2278', $c->ProrrateoFijo, 'Lo capturado a mano se conserva.');
        $this->assertSame(1, CosCuotasReal::count(), 'Reemplaza el mes, no duplica.');
    }

    public function test_min_paro_solo_cuenta_lo_que_paso_durante_un_julio(): void
    {
        $this->julios([
            ['00001', 'Mc Coy 1', '2026-04-10', '10:00:00', '12:00:00'],
            ['00002', 'Karl Mayer', '2026-04-20', '08:00:00', '09:00:00'],
            ['00003', 'Mc Coy 2', '2026-04-01', '00:00:00', '00:30:00'],
        ]);
        $this->paros([
            ['Mc Coy 1', 'Mecanico', '2026-04-10 10:00:00', '2026-04-10 11:00:00'],     // 60 dentro del julio
            ['MC Coy 1', 'Electrico', '2026-04-10 10:30:00', '2026-04-10 13:00:00'],    // encimado: +60 hasta las 12 (el resto, sin julio)
            ['Mc Coy 1', 'Mecanico', '2026-04-11 10:00:00', '2026-04-11 11:00:00'],     // máquina sin julio: no cuenta
            ['Mc Coy 2', 'Mecanico', '2026-03-31 23:00:00', '2026-04-01 01:00:00'],     // recortado al mes: 30 del julio
            ['KM1', 'Tiempo Muerto', '2026-04-20 00:00:00', '2026-04-21 00:00:00'],     // Karl Mayer = KM1: 60 del julio, solo en total
            ['Mc Coy 3', 'Mecanico', '2026-04-15 08:00:00', null],                      // sin cerrar: no cuenta
        ]);
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert(
            ['Depto' => 'Engomado', 'MaquinaId' => 'Mc Coy 1', 'TipoFallaId' => 'Mecanico', 'Fecha' => '2026-04-10', 'Hora' => '10:00:00', 'FechaFin' => '2026-04-10', 'HoraFin' => '12:00:00'],
        );

        $s = app(CuotasUrdidoService::class);
        $this->assertSame(150.0, $s->minutosParo(2026, 4), 'Sin tiempo muerto: 60 + 60 + 30.');
        $this->assertSame(210.0, $s->minutosParo(2026, 4, true), 'Total: + 60 de tiempo muerto durante el julio de Karl Mayer.');
    }

    public function test_cuota_sin_paros_por_default_y_con_paros_con_el_switch(): void
    {
        $this->julios([['00001', 'Mc Coy 1', '2026-04-10', '10:00:00', '12:00:00']]); // 120 min
        $this->paros([['Mc Coy 1', 'Mecanico', '2026-04-10 11:00:00', '2026-04-10 11:20:00']]); // 20 durante el julio
        $this->movimientos = [(object) ['cuenta' => '702-002-000-0', 'centro' => '003', 'tipo' => 'MOD', 'total' => 1000]];
        $s = app(CuotasUrdidoService::class);

        $sin = $s->calcular(2026, 4);
        $this->assertSame(120.0, $sin['Minutos']);
        $this->assertSame(20.0, $sin['MinParo']);
        $this->assertSame(10.0, $sin['MO'], 'Sin paros: 1000 ÷ (120 − 20).');

        $this->assertSame(8.3333, $s->calcular(2026, 4, false, true)['MO'], 'Con paros: 1000 ÷ 120.');
    }

    public function test_la_cuota_llega_a_cada_julio_y_suma_la_sabana(): void
    {
        $this->julios([
            ['00001', 'Mc Coy 1', '2026-04-10', '10:00:00', '12:00:00'], // 120 min, 20 de paro dentro
            ['00002', 'Mc Coy 2', '2026-04-11', '08:00:00', '09:00:00'], // 60 min, sin paro
        ]);
        $this->paros([['Mc Coy 1', 'Mecanico', '2026-04-10 11:00:00', '2026-04-10 11:20:00']]);
        $this->movimientos = [(object) ['cuenta' => '702-002-000-0', 'centro' => '003', 'tipo' => 'MOD', 'total' => 1000]];
        // Prorrateo fijo capturado a mano en la pantalla de Cuotas; maquila sin capturar.
        CosCuotasReal::create(['Depto' => 'Urdido', 'Año' => 2026, 'Mes' => 4, 'ProrrateoFijo' => 0.5]);
        $s = app(CuotasUrdidoService::class);

        $r = $s->actualizar(2026, 4); // sin paros: cuota MO = 1000 ÷ (180 − 20) = 6.25

        $this->assertSame(2, $r['Julios']);
        $j = fn () => DB::connection('sqlsrv')->table('UrdProduccionUrdido')->orderBy('Folio')->get();
        $this->assertSame([625.0, 375.0], $j()->pluck('MOD')->map(fn ($v) => (float) $v)->all(), '6.25 × (120 − 20) y 6.25 × 60: suman SabMO.');
        $this->assertSame([50.0, 30.0], $j()->pluck('Pf')->map(fn ($v) => (float) $v)->all(), 'Prorrateo capturado × la misma base.');
        $this->assertSame([0.0, 0.0], $j()->pluck('GtsV')->map(fn ($v) => (float) $v)->all());
        $this->assertSame([null, null], $j()->pluck('Maquila')->all(), 'Cuota no capturada: queda vacío.');

        $s->actualizar(2026, 4, false, true); // con paros: 1000 ÷ 180 sobre los minutos completos
        $this->assertEqualsWithDelta(1000.0, $j()->sum('MOD'), 0.01);
        $this->assertSame(666.6720, round((float) $j()->first()->MOD, 4), '5.5556 × 120.');

        $this->assertSame(0, $s->actualizar(2026, 4, false, true)['Julios'], 'Sin cambios no reescribe.');
    }

    public function test_julios_encimados_reparten_la_sabana_completa(): void
    {
        // Captura encimada: dos julios de Mc Coy 1 al mismo tiempo y un paro dentro de ambos.
        $this->julios([
            ['00001', 'Mc Coy 1', '2026-04-10', '10:00:00', '11:00:00'],
            ['00002', 'Mc Coy 1', '2026-04-10', '10:30:00', '11:30:00'],
        ]);
        $this->paros([['Mc Coy 1', 'Mecanico', '2026-04-10 10:40:00', '2026-04-10 10:50:00']]);
        $this->movimientos = [(object) ['cuenta' => '702-002-000-0', 'centro' => '003', 'tipo' => 'MOD', 'total' => 777]];

        $r = app(CuotasUrdidoService::class)->actualizar(2026, 4);

        $this->assertSame(20.0, $r['MinParo'], 'El paro cae en los dos julios: 10 + 10.');
        $this->assertEqualsWithDelta(777.0, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->sum('MOD'), 0.01);
    }

    public function test_el_rango_lee_la_sabana_una_vez_y_da_lo_mismo_que_mes_por_mes(): void
    {
        $this->julios([
            ['00001', 'Mc Coy 1', '2026-04-10', '10:00:00', '11:00:00'],
            ['00002', 'Mc Coy 1', '2026-05-10', '10:00:00', '12:00:00'],
        ]);
        $leidas = 0;
        $this->movimientos = [(object) ['cuenta' => '702-002-000-0', 'centro' => '003', 'tipo' => 'Salarios', 'total' => 600]];
        $this->reglas = ['702-002-000-0|003' => 'MOD'];
        $this->partialMock(CuotasUrdidoService::class, function ($m) use (&$leidas) {
            $m->shouldReceive('movimientos')->andReturnUsing(fn () => collect($this->movimientos));
            $m->shouldReceive('reglas')->andReturnUsing(function () use (&$leidas) {
                $leidas++;

                return $this->reglas;
            });
        });

        app(CuotasUrdidoService::class)->actualizarRango(2026, 4, 5);
        $rango = CosCuotasReal::orderBy('Mes')->pluck('MO')->all();

        $this->assertSame(1, $leidas, 'Las reglas de AX se leen una vez para todo el rango.');
        app(CuotasUrdidoService::class)->actualizar(2026, 4);
        app(CuotasUrdidoService::class)->actualizar(2026, 5);
        $this->assertSame(['10.0000', '5.0000'], $rango, '600 ÷ 60 y 600 ÷ 120.');
        $this->assertSame($rango, CosCuotasReal::orderBy('Mes')->pluck('MO')->all(), 'Mes por mes da lo mismo.');
    }

    public function test_reparto_de_muchos_julios_cruza_lotes_de_siete_columnas(): void
    {
        // 7 columnas × 2 + 1 = 15 parámetros por julio → 133 por lote; 300 julios = 3 UPDATE.
        $this->julios(array_map(fn ($i) => [sprintf('%05d', $i), 'Mc Coy 1', '2026-04-'.str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT), '10:00:00', '10:10:00'], range(1, 300)));
        $this->movimientos = [(object) ['cuenta' => '702-002-000-0', 'centro' => '003', 'tipo' => 'MOD', 'total' => 3000]];

        DB::connection('sqlsrv')->enableQueryLog();
        $r = app(CuotasUrdidoService::class)->actualizar(2026, 4);

        $this->assertSame(300, $r['Julios']);
        $updates = collect(DB::connection('sqlsrv')->getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'UPDATE [UrdProduccionUrdido]'));
        $this->assertCount(3, $updates);
        $this->assertEqualsWithDelta(3000.0, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->sum('MOD'), 0.01);
        $this->assertSame(0, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->whereNull('MOD')->count());
    }

    /** @param list<array{0: string, 1: string, 2: string, 3: string, 4: string}> $julios folio, máquina, fecha, inicio, fin */
    private function julios(array $julios): void
    {
        foreach ($julios as [$folio, $maquina, $fecha, $ini, $fin]) {
            DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(['Folio' => $folio, 'Fecha' => $fecha, 'HoraInicial' => $ini, 'HoraFinal' => $fin]);
            DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => $folio, 'MaquinaId' => $maquina]);
        }
    }

    /** @param list<array{0: string, 1: string, 2: string, 3: string|null}> $paros máquina, tipo, inicio, fin */
    private function paros(array $paros): void
    {
        foreach ($paros as [$maquina, $tipo, $ini, $fin]) {
            DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
                'Depto' => 'Urdido', 'MaquinaId' => $maquina, 'TipoFallaId' => $tipo,
                'Fecha' => substr($ini, 0, 10), 'Hora' => substr($ini, 11),
                'FechaFin' => $fin === null ? null : substr($fin, 0, 10), 'HoraFin' => $fin === null ? null : substr($fin, 11),
            ]);
        }
    }

    public function test_rango_avisa_meses_sin_produccion_y_lo_no_clasificado(): void
    {
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(
            ['Folio' => '00001', 'Fecha' => '2026-04-10', 'HoraInicial' => '10:00:00', 'HoraFinal' => '11:00:00'],
        );
        $this->movimientos = [(object) ['cuenta' => '999-999-000-0', 'centro' => '003', 'tipo' => 'Otros', 'total' => 12.5]];

        $r = app(CuotasUrdidoService::class)->actualizarRango(2026, 4, 5);

        $this->assertFalse($r['completo']);
        $this->assertSame('Urdido: 2 mes(es) calculado(s), 1 julio(s) actualizados. Sin producción: Mayo. $25.00 de AX sin clasificar.', $r['texto']);
        $this->assertSame(2, CosCuotasReal::count());
    }

    public function test_mes_sin_julios_deja_la_cuota_vacia(): void
    {
        $this->movimientos = [(object) ['cuenta' => '702-002-000-0', 'centro' => '003', 'tipo' => 'MOD', 'total' => 100]];

        $r = app(CuotasUrdidoService::class)->calcular(2026, 9);

        $this->assertSame(0.0, $r['Minutos']);
        $this->assertSame(100.0, $r['SabMO']);
        $this->assertNull($r['MO'], 'Sin minutos no hay cuota (no se divide entre cero).');
    }
}
