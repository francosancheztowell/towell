<?php

namespace Tests\Unit;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Tejedores\Desarrolladores\CatCodificadosDesarrolladorService;
use App\Services\Tejedores\Desarrolladores\MovimientoDesarrolladorService;
use App\Services\Tejedores\Desarrolladores\NotificacionTelegramDesarrolladorService;
use App\Services\Tejedores\Desarrolladores\ProcesarDesarrolladorService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guardado de la captura de desarrolladores de punta a punta, contra las tres tablas:
 * CatCodificados, ReqModelosCodificados y ReqProgramaTejido.
 *
 * La base es SQLite, pero cada columna que toca la captura lleva el tipo y la longitud
 * que tiene en SQL Server (INFORMATION_SCHEMA de ProdTowel, sep-2026) como CHECK: un
 * texto en una columna numerica, una cadena mas larga que su nvarchar o una fecha que
 * no es fecha revientan aqui igual que en produccion.
 *
 * Se revisa campo por campo, que no se toque lo que no se capturo, que las tres tablas
 * terminen diciendo lo mismo y que un fallo no deje ninguna a medias.
 */
class ProcesarDesarrolladorStoreTest extends TestCase
{
    private const AHORA = '2026-09-23 11:45:00';

    private $dispatcher;

    private $telegram;

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
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        Carbon::setTestNow(self::AHORA);

        // El observer del programa regenera lineas diarias contra tablas que aqui no
        // existen; lo que se prueba es lo que escribe la captura.
        $this->dispatcher = Model::getEventDispatcher();
        Model::unsetEventDispatcher();

        $this->crearTabla('ReqProgramaTejido', (new ReqProgramaTejido)->getFillable(), $this->tiposPrograma());
        $this->crearTabla('CatCodificados', CatCodificados::COLUMNS, $this->tiposCat());
        $this->crearTabla('ReqModelosCodificados', (new ReqModelosCodificados)->getFillable(), $this->tiposModelo());

        foreach (['dbo.ReqEficienciaStd' => 'Eficiencia', 'dbo.ReqVelocidadStd' => 'Velocidad'] as $tabla => $valor) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($valor) {
                $t->increments('Id');
                $t->string('NoTelarId')->nullable();
                $t->string('FibraId')->nullable();
                $t->string('Densidad')->nullable();
                $t->float($valor)->nullable();
            });
        }
        Schema::connection('sqlsrv')->create('TelTelaresOperador', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('NoTelarId')->nullable();
            $t->string('SalonTejidoId')->nullable();
        });

        $this->telegram = $this->createMock(NotificacionTelegramDesarrolladorService::class);
    }

    protected function tearDown(): void
    {
        Model::setEventDispatcher($this->dispatcher);
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Esquema con los tipos de SQL Server ─────────────────────────────────

    /** @param  array<string, string|int>  $tipos  'int' | 'float' | 'fecha' | longitud nvarchar */
    private function crearTabla(string $tabla, array $columnas, array $tipos): void
    {
        $defs = ['"Id" INTEGER PRIMARY KEY AUTOINCREMENT'];

        foreach (array_diff(array_unique($columnas), ['Id']) as $c) {
            $tipo = $tipos[$c] ?? null;
            $defs[] = match (true) {
                $tipo === 'int' => "\"$c\" INTEGER CHECK (\"$c\" IS NULL OR typeof(\"$c\") = 'integer')",
                $tipo === 'float' => "\"$c\" REAL CHECK (\"$c\" IS NULL OR typeof(\"$c\") IN ('integer','real'))",
                $tipo === 'fecha' => "\"$c\" TEXT CHECK (\"$c\" IS NULL OR datetime(\"$c\") IS NOT NULL)",
                is_int($tipo) => "\"$c\" TEXT CHECK (\"$c\" IS NULL OR length(\"$c\") <= $tipo)",
                default => "\"$c\" TEXT",
            };
        }

        DB::connection('sqlsrv')->statement("CREATE TABLE \"$tabla\" (".implode(', ', $defs).')');
    }

    private function tiposPrograma(): array
    {
        $tipos = StringTruncator::getFieldLimits() + [
            'EnProceso' => 'int', 'Posicion' => 'int', 'OrdCompartida' => 'int', 'OrdCompartidaLider' => 'int',
            'PasadasTrama' => 'int', 'CalibreTrama' => 'float', 'CalibreTrama2' => 'float',
            'TotalPedido' => 'float', 'Produccion' => 'float', 'SaldoPedido' => 'float',
            'EficienciaSTD' => 'float', 'VelocidadSTD' => 'float',
            'FechaInicio' => 'fecha', 'FechaFinal' => 'fecha', 'FechaArranque' => 'fecha', 'FechaFinaliza' => 'fecha',
            'CreatedAt' => 'fecha', 'UpdatedAt' => 'fecha',
        ];
        for ($i = 1; $i <= 5; $i++) {
            $tipos["PasadasComb{$i}"] = 'int';
            $tipos["CalibreComb{$i}2"] = 'float';
        }
        foreach ([1, 2, 3, 4] as $n) {
            $tipos["CuentaBarra{$n}"] = 10;
            $tipos["CalibreBarra{$n}"] = 50;
            $tipos["PasadasBarra{$n}"] = 'int';
        }

        return $tipos;
    }

    private function tiposCat(): array
    {
        $tipos = [
            'TelarId' => 'int', 'Tra' => 'float', 'CalibreTrama2' => 'float', 'CalTramaFondoC1' => 'float',
            'CalTramaFondoC12' => 'float', 'PasadasTramaFondoC1' => 'int', 'Total' => 'float', 'MinutosCambio' => 'int',
            'EfiInicial' => 'int', 'EfiFinal' => 'int', 'Pedido' => 'float', 'Produccion' => 'float', 'Saldos' => 'float',
            'OrdCompartida' => 'int', 'OrdCompartidaLider' => 'int', 'HiloAX' => 30,
            'FechaArranque' => 'fecha', 'FechaFinaliza' => 'fecha', 'FechaCumplimiento' => 'fecha', 'FechaTejido' => 'fecha',
        ];
        for ($i = 1; $i <= 5; $i++) {
            $tipos["CalibreComb{$i}"] = 50;
            $tipos["CalibreComb{$i}2"] = 'float';
            $tipos["PasadasComb{$i}"] = 'int';
        }
        foreach ([1, 2, 3, 4] as $n) {
            $tipos["CuentaBarra{$n}"] = 10;
            $tipos["CalibreBarra{$n}"] = 50;
            $tipos["PasadasBarra{$n}"] = 'int';
        }

        return $tipos;
    }

    private function tiposModelo(): array
    {
        $tipos = [
            'CalibreTrama' => 40, 'CalibreTrama2' => 40, 'CalTramaFondoC1' => 20, 'CalTramaFondoC12' => 20,
            'FibraId' => 100, 'FibraTramaFondoC1' => 30, 'CodColorTrama' => 200, 'ColorTrama' => 60,
            'PasadasTramaFondoC1' => 20, 'Total' => 'float', 'FechaCumplimiento' => 'fecha',
        ];
        for ($i = 1; $i <= 5; $i++) {
            $tipos["CalibreComb{$i}"] = 20;
            $tipos["CalibreComb{$i}2"] = 20;
            $tipos["FibraComb{$i}"] = 30;
            $tipos["CodColorC{$i}"] = 200;
            $tipos["NomColorC{$i}"] = 60;
            $tipos["PasadasComb{$i}"] = 20;
        }
        foreach ([1, 2, 3, 4] as $n) {
            $tipos["CuentaBarra{$n}"] = 10;
            $tipos["CalibreBarra{$n}"] = 50;
            $tipos["PasadasBarra{$n}"] = 'int';
        }

        return $tipos;
    }

    // ── Planta sembrada ─────────────────────────────────────────────────────

    private function insertar(string $tabla, array $fila): int
    {
        return DB::connection('sqlsrv')->table($tabla)->insertGetId($fila);
    }

    private function programa(array $extra = []): int
    {
        return $this->insertar('ReqProgramaTejido', array_merge([
            'NoProduccion' => '36857', 'NoTelarId' => '309', 'SalonTejidoId' => 'SMIT', 'TamanoClave' => 'MOD1',
            'NombreProducto' => 'TOALLA MOD1', 'ItemId' => 'IT-1', 'FlogsId' => 'FL-1', 'HiloAX' => 'OPEN END',
            'Rasurado' => 'SI', 'CalibreRizo' => '12', 'CuentaRizo' => '3400',
            'EnProceso' => 0, 'Posicion' => 2, 'FechaInicio' => '2026-09-24 06:00:00', 'FechaFinal' => '2026-09-30 06:00:00',
            'TotalPedido' => 100, 'SaldoPedido' => 100, 'Produccion' => 0,
            'CalibreTrama' => 10.1, 'CalibreTrama2' => 10, 'FibraTrama' => 'ALGODON', 'CodColorTrama' => 'B01',
            'ColorTrama' => 'BLANCO', 'PasadasTrama' => 1776,
            'CalibreComb1' => '10.1', 'CalibreComb12' => 10, 'FibraComb1' => 'ALGODON', 'CodColorComb1' => 'B01', 'NombreCC1' => 'BLANCO', 'PasadasComb1' => 436,
            'CalibreComb2' => '20.1', 'CalibreComb22' => 20, 'FibraComb2' => 'POLI', 'CodColorComb2' => 'R05', 'NombreCC2' => 'ROJO', 'PasadasComb2' => 120,
            'CalibreComb3' => '30.1', 'CalibreComb32' => 30, 'FibraComb3' => 'LINO', 'CodColorComb3' => 'V07', 'NombreCC3' => 'VERDE', 'PasadasComb3' => 60,
        ], $extra));
    }

    private function modelo(array $extra = []): int
    {
        return $this->insertar('ReqModelosCodificados', array_merge([
            'TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '308', 'OrdenTejido' => '36001',
            'CodigoDibujo' => 'DIBUJOVIEJO01', 'Total' => 999, 'AlturaRizo' => '4.0',
            'AnchoPeineTrama' => '110', 'LogLuchaTotal' => '121',
            'Nombre' => 'TOALLA MOD1', 'Peine' => '60', 'CuentaRizo' => '3400', 'CuentaPie' => '3000', 'TipoRizo' => 'NORMAL',
            'Tolerancia' => '5%', 'Clave' => 'K1', 'Vendedor' => 'VEND', 'FlogsId' => 'FL-1',
            'CalibreTrama' => '10.1', 'CalibreTrama2' => '10', 'CalTramaFondoC1' => '10.1', 'CalTramaFondoC12' => '10',
            'FibraId' => 'ALGODON', 'FibraTramaFondoC1' => 'ALGODON', 'CodColorTrama' => 'B01', 'ColorTrama' => 'BLANCO',
            'PasadasTramaFondoC1' => '1776',
            'CalibreComb1' => '10.1', 'CalibreComb12' => '10', 'FibraComb1' => 'ALGODON', 'CodColorC1' => 'B01', 'NomColorC1' => 'BLANCO', 'PasadasComb1' => '436',
            'CalibreComb2' => '20.1', 'CalibreComb22' => '20', 'FibraComb2' => 'POLI', 'CodColorC2' => 'R05', 'NomColorC2' => 'ROJO', 'PasadasComb2' => '120',
            'CalibreComb3' => '30.1', 'CalibreComb32' => '30', 'FibraComb3' => 'LINO', 'CodColorC3' => 'V07', 'NomColorC3' => 'VERDE', 'PasadasComb3' => '60',
        ], $extra));
    }

    private function cat(array $extra = []): int
    {
        return $this->insertar('CatCodificados', array_merge([
            'OrdenTejido' => '36857', 'TelarId' => 309, 'Departamento' => 'SMIT', 'ClaveModelo' => 'MOD1',
            'Nombre' => 'TOALLA MOD1', 'HiloAX' => 'OPEN END', 'TramaAnchoPeine' => '110', 'LogLuchaTotal' => '121',
            'Tra' => 10.1, 'PasadasTramaFondoC1' => 1776,
        ], $extra));
    }

    /**
     * La planta de todos los casos: la orden a capturar en el 309, la que hoy esta en
     * proceso en ese telar, otra orden del mismo modelo en el 310, el modelo, dos
     * modelos vecinos que no deben moverse y los renglones de CatCodificados.
     *
     * @return array<string, int>
     */
    private function sembrar(): array
    {
        return [
            'programa' => $this->programa(),
            'enProceso' => $this->programa([
                'NoProduccion' => '36800', 'TamanoClave' => 'MOD0', 'EnProceso' => 1, 'Posicion' => 1,
                'FechaInicio' => '2026-09-20 07:00:00', 'FechaFinal' => '2026-09-24 06:00:00', 'SaldoPedido' => 0,
            ]),
            'otroTelar' => $this->programa(['NoProduccion' => '36999', 'NoTelarId' => '310', 'EnProceso' => 1, 'Posicion' => 1]),
            'modelo' => $this->modelo(),
            'modeloOtroSalon' => $this->modelo(['SalonTejidoId' => 'JACQUARD', 'CodigoDibujo' => 'OTROSALON001', 'PasadasTramaFondoC1' => '555']),
            'modeloOtraClave' => $this->modelo(['TamanoClave' => 'MOD2', 'CodigoDibujo' => 'OTROMODELO01', 'PasadasTramaFondoC1' => '777']),
            'cat' => $this->cat(),
            'catEnProceso' => $this->cat(['OrdenTejido' => '36800', 'ClaveModelo' => 'MOD0', 'FechaArranque' => '2026-09-20 07:00:00']),
            'catOtroTelar' => $this->cat(['OrdenTejido' => '36999', 'TelarId' => 310, 'CodigoDibujo' => 'NOTOCAR00001', 'PasadasTramaFondoC1' => 999]),
        ];
    }

    /** Lo que manda la pantalla Livewire al guardar (Captura::cargaUtil): trama y una sola combinacion. */
    private function captura(array $extra = []): array
    {
        return array_merge([
            'NoTelarId' => '309',
            'NoProduccion' => '36857',
            'accion' => 'finalizar',
            'CambioTelarActivo' => '0',
            'TelarDestino' => '',
            'NumeroJulioRizo' => 'JR-7',
            'NumeroJulioPie' => 'JP-3',
            'TotalPasadasDibujo' => 2243,
            'HoraInicio' => '10:30',
            'HoraFinal' => '11:40',
            'EficienciaInicio' => 70,
            'EficienciaFinal' => 85,
            'Desarrollador' => 'ALVARO',
            'DesperdicioTrama' => 11,
            'AlturaRizo' => '5.5',
            'CodificacionModelo' => 'mbaustco32n060e7',
            'pasadas' => ['PasadasTrama' => 1501, 'PasadasComb1' => 742],
            'detalle_cuenta' => ['', ''],
            'detalle_calibre' => ['12.1', '8.1'],
            'detalle_hilo' => ['12', '8'],
            'detalle_fibra' => ['OPEN', 'PEINADO'],
            'detalle_codcolor' => ['T09', 'C22'],
            'detalle_nombrecolor' => ['CRUDO', 'AZUL'],
        ], $extra);
    }

    private function guardar(array $datos): array
    {
        $cat = new CatCodificadosDesarrolladorService;
        $servicio = new ProcesarDesarrolladorService(new MovimientoDesarrolladorService($cat), $this->telegram, $cat);

        return $servicio->store(
            Request::create('/desarrolladores', 'POST', $datos, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])
        )->getData(true);
    }

    private function guardarBien(array $datos): void
    {
        $respuesta = $this->guardar($datos);
        $this->assertTrue($respuesta['success'] ?? false, 'El guardado fallo: '.json_encode($respuesta));
    }

    private function fila(string $tabla, int $id): array
    {
        $fila = DB::connection('sqlsrv')->table($tabla)->where('Id', $id)->first();
        $this->assertNotNull($fila, "No existe {$tabla}.Id={$id}");

        return (array) $fila;
    }

    /** Compara todos los campos y reporta todos los que no coinciden, no solo el primero. */
    private function assertCampos(array $esperado, array $fila, string $donde): void
    {
        $errores = [];
        foreach ($esperado as $campo => $valor) {
            $real = $fila[$campo] ?? null;
            $igual = is_float($valor) || is_int($valor)
                ? is_numeric($real) && abs((float) $real - $valor) < 0.0001
                : $real === $valor;

            if (! $igual) {
                $errores[] = sprintf('%s.%s: se esperaba %s y hay %s', $donde, $campo, var_export($valor, true), var_export($real, true));
            }
        }

        $this->assertSame([], $errores, implode("\n", $errores));
    }

    private function vacias(array $campos): array
    {
        return array_fill_keys($campos, null);
    }

    private function combos(array $plantillas, array $slots): array
    {
        $campos = [];
        foreach ($slots as $i) {
            foreach ($plantillas as $p) {
                $campos[] = str_replace('#', (string) $i, $p);
            }
        }

        return $campos;
    }

    // ── Finalizar: lo que queda en cada tabla ───────────────────────────────

    public function test_cat_codificados_recibe_cada_campo_capturado(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos([
            'OrdenTejido' => '36857', 'TelarId' => 309, 'Departamento' => 'SMIT',
            'CodigoDibujo' => 'MBAUSTCO32N060E7', 'RespInicio' => 'ALVARO', 'HrInicio' => '10:30', 'HrTermino' => '11:40',
            'MinutosCambio' => 70, 'Total' => 2243, 'JulioRizo' => 'JR-7', 'JulioPie' => 'JP-3',
            'EfiInicial' => 70, 'EfiFinal' => 85, 'DesperdicioTrama' => '11', 'AlturaRizo' => '5.5',
            'FechaCumplimiento' => '2026-09-23 11:45:00',
            // trama
            'Tra' => 12.1, 'CalTramaFondoC1' => 12.1, 'CalibreTrama2' => 12, 'CalTramaFondoC12' => 12,
            'FibraId' => 'OPEN', 'FibraTramaFondoC1' => 'OPEN', 'CodColorTrama' => 'T09', 'ColorTrama' => 'CRUDO',
            'PasadasTramaFondoC1' => 1501,
            // combinacion 1
            'CalibreComb1' => '8.1', 'CalibreComb12' => 8, 'FibraComb1' => 'PEINADO', 'CodColorC1' => 'C22',
            'NomColorC1' => 'AZUL', 'PasadasComb1' => 742,
            // del modelo y del programa
            'CuentaRizo' => '3400', 'CuentaPie' => '3000', 'TipoRizo' => 'NORMAL', 'Tolerancia' => '5%', 'Clave' => 'K1',
            'Vendedor' => 'VEND', 'FlogsId' => 'FL-1', 'Razurada' => 'SI',
            'Pedido' => 100, 'Saldos' => 100,
        ], $this->fila('CatCodificados', $ids['cat']), 'CatCodificados');
    }

    public function test_cat_codificados_limpia_las_combinaciones_que_se_quitaron(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos(
            $this->vacias($this->combos(['CalibreComb#', 'CalibreComb#2', 'FibraComb#', 'CodColorC#', 'NomColorC#', 'PasadasComb#'], [2, 3, 4, 5])),
            $this->fila('CatCodificados', $ids['cat']),
            'CatCodificados'
        );
    }

    public function test_cat_codificados_conserva_lo_que_no_se_capturo(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos([
            'Nombre' => 'TOALLA MOD1',
            'ClaveModelo' => 'MOD1',
            // Tipo de hilo de AX que pone Liberar: el divisor del hilo no va aqui.
            'HiloAX' => 'OPEN END',
            // La pantalla no los captura: no se deben borrar.
            'TramaAnchoPeine' => '110',
            'LogLuchaTotal' => '121',
        ], $this->fila('CatCodificados', $ids['cat']), 'CatCodificados');
    }

    public function test_cat_codificados_sella_arranque_y_la_orden_anterior_queda_finalizada(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        // La orden capturada arranca al guardar y sigue abierta; la que estaba en
        // proceso se finaliza sin perder su arranque real.
        $this->assertCampos(['FechaArranque' => self::AHORA, 'FechaFinaliza' => null], $this->fila('CatCodificados', $ids['cat']), 'Cat orden capturada');
        $this->assertCampos(
            ['FechaArranque' => '2026-09-20 07:00:00', 'FechaFinaliza' => self::AHORA],
            $this->fila('CatCodificados', $ids['catEnProceso']),
            'Cat orden anterior'
        );
    }

    public function test_modelo_recibe_cada_campo_capturado(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos([
            'TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '309', 'OrdenTejido' => '36857',
            'CodigoDibujo' => 'MBAUSTCO32N060E7', 'Total' => 2243, 'AlturaRizo' => '5.5',
            'FechaCumplimiento' => '2026-09-23 11:45:00',
            'CalibreTrama' => '12.1', 'CalTramaFondoC1' => '12.1', 'CalibreTrama2' => '12', 'CalTramaFondoC12' => '12',
            'FibraId' => 'OPEN', 'FibraTramaFondoC1' => 'OPEN', 'CodColorTrama' => 'T09', 'ColorTrama' => 'CRUDO',
            'PasadasTramaFondoC1' => '1501',
            'CalibreComb1' => '8.1', 'CalibreComb12' => '8', 'FibraComb1' => 'PEINADO', 'CodColorC1' => 'C22',
            'NomColorC1' => 'AZUL', 'PasadasComb1' => '742',
        ], $this->fila('ReqModelosCodificados', $ids['modelo']), 'ReqModelosCodificados');
    }

    public function test_modelo_limpia_las_combinaciones_que_se_quitaron(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos(
            $this->vacias($this->combos(['CalibreComb#', 'CalibreComb#2', 'FibraComb#', 'CodColorC#', 'NomColorC#', 'PasadasComb#'], [2, 3, 4, 5])),
            $this->fila('ReqModelosCodificados', $ids['modelo']),
            'ReqModelosCodificados'
        );
    }

    public function test_modelo_conserva_lo_que_no_se_capturo(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos([
            'Nombre' => 'TOALLA MOD1', 'Peine' => '60', 'CuentaRizo' => '3400', 'CuentaPie' => '3000',
            'TipoRizo' => 'NORMAL', 'Tolerancia' => '5%', 'Clave' => 'K1', 'Vendedor' => 'VEND', 'FlogsId' => 'FL-1',
            'AnchoPeineTrama' => '110', 'LogLuchaTotal' => '121',
        ], $this->fila('ReqModelosCodificados', $ids['modelo']), 'ReqModelosCodificados');
    }

    public function test_solo_se_toca_el_modelo_de_esa_clave_y_ese_salon(): void
    {
        $ids = $this->sembrar();
        $antes = [$this->fila('ReqModelosCodificados', $ids['modeloOtroSalon']), $this->fila('ReqModelosCodificados', $ids['modeloOtraClave'])];

        $this->guardarBien($this->captura());

        $this->assertSame($antes[0], $this->fila('ReqModelosCodificados', $ids['modeloOtroSalon']));
        $this->assertSame($antes[1], $this->fila('ReqModelosCodificados', $ids['modeloOtraClave']));
    }

    public function test_programa_recibe_cada_campo_capturado(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos([
            'EnProceso' => 1, 'Posicion' => 1, 'NoTelarId' => '309', 'SalonTejidoId' => 'SMIT',
            'CalibreTrama' => 12.1, 'CalibreTrama2' => 12, 'FibraTrama' => 'OPEN', 'CodColorTrama' => 'T09',
            'ColorTrama' => 'CRUDO', 'PasadasTrama' => 1501,
            'CalibreComb1' => '8.1', 'CalibreComb12' => 8, 'FibraComb1' => 'PEINADO', 'CodColorComb1' => 'C22',
            'NombreCC1' => 'AZUL', 'PasadasComb1' => 742,
            'FechaArranque' => self::AHORA,
        ], $this->fila('ReqProgramaTejido', $ids['programa']), 'ReqProgramaTejido');
    }

    public function test_programa_limpia_las_combinaciones_que_se_quitaron(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos(
            $this->vacias($this->combos(['CalibreComb#', 'CalibreComb#2', 'FibraComb#', 'CodColorComb#', 'NombreCC#', 'PasadasComb#'], [2, 3, 4, 5])),
            $this->fila('ReqProgramaTejido', $ids['programa']),
            'ReqProgramaTejido'
        );
    }

    public function test_programa_conserva_lo_que_no_se_capturo(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertCampos([
            'NoProduccion' => '36857', 'TamanoClave' => 'MOD1', 'NombreProducto' => 'TOALLA MOD1', 'ItemId' => 'IT-1',
            'FlogsId' => 'FL-1', 'HiloAX' => 'OPEN END', 'Rasurado' => 'SI', 'CalibreRizo' => '12', 'CuentaRizo' => '3400',
            'TotalPedido' => 100, 'SaldoPedido' => 100,
        ], $this->fila('ReqProgramaTejido', $ids['programa']), 'ReqProgramaTejido');
    }

    public function test_la_orden_que_estaba_en_proceso_se_finaliza(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());

        $this->assertNull(DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $ids['enProceso'])->first());
    }

    public function test_otro_telar_con_el_mismo_modelo_no_se_toca(): void
    {
        $ids = $this->sembrar();
        $antes = [$this->fila('ReqProgramaTejido', $ids['otroTelar']), $this->fila('CatCodificados', $ids['catOtroTelar'])];

        $this->guardarBien($this->captura());

        $this->assertSame($antes[0], $this->fila('ReqProgramaTejido', $ids['otroTelar']));
        $this->assertSame($antes[1], $this->fila('CatCodificados', $ids['catOtroTelar']));
    }

    // ── Las tres tablas dicen lo mismo, en cada escenario ───────────────────

    /**
     * Columna de CatCodificados => [columna en ReqModelosCodificados, columna en ReqProgramaTejido].
     * null = esa tabla no guarda el dato.
     */
    private function equivalencias(): array
    {
        $mapa = [
            'Tra' => ['CalibreTrama', 'CalibreTrama'],
            'CalTramaFondoC1' => ['CalTramaFondoC1', 'CalibreTrama'],
            'CalibreTrama2' => ['CalibreTrama2', 'CalibreTrama2'],
            'FibraId' => ['FibraId', 'FibraTrama'],
            'CodColorTrama' => ['CodColorTrama', 'CodColorTrama'],
            'ColorTrama' => ['ColorTrama', 'ColorTrama'],
            'PasadasTramaFondoC1' => ['PasadasTramaFondoC1', 'PasadasTrama'],
            'CodigoDibujo' => ['CodigoDibujo', null],
            'OrdenTejido' => ['OrdenTejido', 'NoProduccion'],
            'TelarId' => ['NoTelarId', 'NoTelarId'],
            'Total' => ['Total', null],
            'AlturaRizo' => ['AlturaRizo', null],
        ];
        for ($i = 1; $i <= 5; $i++) {
            $mapa["CalibreComb{$i}"] = ["CalibreComb{$i}", "CalibreComb{$i}"];
            $mapa["CalibreComb{$i}2"] = ["CalibreComb{$i}2", "CalibreComb{$i}2"];
            $mapa["FibraComb{$i}"] = ["FibraComb{$i}", "FibraComb{$i}"];
            $mapa["CodColorC{$i}"] = ["CodColorC{$i}", "CodColorComb{$i}"];
            $mapa["NomColorC{$i}"] = ["NomColorC{$i}", "NombreCC{$i}"];
            $mapa["PasadasComb{$i}"] = ["PasadasComb{$i}", "PasadasComb{$i}"];
        }

        return $mapa;
    }

    private function normalizar($valor)
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        return is_numeric($valor) ? round((float) $valor, 4) : trim((string) $valor);
    }

    private function assertTresTablasDeAcuerdo(int $catId, int $modeloId, int $programaId): void
    {
        $cat = $this->fila('CatCodificados', $catId);
        $modelo = $this->fila('ReqModelosCodificados', $modeloId);
        $programa = $this->fila('ReqProgramaTejido', $programaId);

        $errores = [];
        foreach ($this->equivalencias() as $colCat => [$colModelo, $colPrograma]) {
            $v = $this->normalizar($cat[$colCat] ?? null);
            if ($colModelo && $this->normalizar($modelo[$colModelo] ?? null) !== $v) {
                $errores[] = "Cat.{$colCat}=".var_export($v, true).' vs Modelo.'.$colModelo.'='.var_export($modelo[$colModelo] ?? null, true);
            }
            if ($colPrograma && $this->normalizar($programa[$colPrograma] ?? null) !== $v) {
                $errores[] = "Cat.{$colCat}=".var_export($v, true).' vs Programa.'.$colPrograma.'='.var_export($programa[$colPrograma] ?? null, true);
            }
        }

        $this->assertSame([], $errores, "Las tres tablas no coinciden:\n".implode("\n", $errores));
    }

    public static function escenarios(): array
    {
        return [
            'finalizar' => [[]],
            'reprogramar al siguiente' => [['accion' => 'reprogramar_siguiente']],
            'reprogramar al final' => [['accion' => 'reprogramar_final']],
            'tres combinaciones' => [[
                'pasadas' => ['PasadasTrama' => 900, 'PasadasComb1' => 10, 'PasadasComb2' => 20, 'PasadasComb3' => 30],
                'detalle_cuenta' => ['', '', '', ''],
                'detalle_calibre' => ['12.1', '8.1', '20.1', '30.1'],
                'detalle_hilo' => ['12', '8', '20', '30'],
                'detalle_fibra' => ['OPEN', 'PEINADO', 'POLI', 'LINO'],
                'detalle_codcolor' => ['T09', 'C22', 'R05', 'V07'],
                'detalle_nombrecolor' => ['CRUDO', 'AZUL', 'ROJO', 'VERDE'],
            ]],
            'sin detalle (ruta HTTP vieja)' => [[
                'pasadas' => [], 'detalle_cuenta' => [], 'detalle_calibre' => [], 'detalle_hilo' => [],
                'detalle_fibra' => [], 'detalle_codcolor' => [], 'detalle_nombrecolor' => [],
            ]],
        ];
    }

    #[DataProvider('escenarios')]
    public function test_las_tres_tablas_terminan_diciendo_lo_mismo(array $extra): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura($extra));

        $this->assertTresTablasDeAcuerdo($ids['cat'], $ids['modelo'], $ids['programa']);
    }

    public function test_sin_detalle_las_tres_tablas_toman_el_de_la_orden_sin_borrar_combinaciones(): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura());
        // Segunda captura, ya sin detalle: todo debe quedar como lo dejo la primera.
        $this->guardarBien($this->captura(['pasadas' => [], 'detalle_calibre' => [], 'detalle_hilo' => [], 'detalle_fibra' => [], 'detalle_codcolor' => [], 'detalle_nombrecolor' => [], 'detalle_cuenta' => []]));

        $this->assertCampos(['PasadasTrama' => 1501, 'PasadasComb1' => 742, 'FibraTrama' => 'OPEN'], $this->fila('ReqProgramaTejido', $ids['programa']), 'Programa');
        $this->assertCampos(['PasadasTramaFondoC1' => 1501, 'PasadasComb1' => 742, 'FibraId' => 'OPEN'], $this->fila('CatCodificados', $ids['cat']), 'Cat');
    }

    // ── Reprogramar ─────────────────────────────────────────────────────────

    #[DataProvider('reprogramaciones')]
    public function test_reprogramar_no_finaliza_la_orden_desplazada(string $accion): void
    {
        $ids = $this->sembrar();
        $this->guardarBien($this->captura(['accion' => $accion]));

        $desplazada = $this->fila('ReqProgramaTejido', $ids['enProceso']);
        $this->assertSame(0, (int) $desplazada['EnProceso']);
        $this->assertNull($desplazada['Reprogramar'], 'Reprogramar se consume al mover la orden.');
        $this->assertCampos(['FechaFinaliza' => null], $this->fila('CatCodificados', $ids['catEnProceso']), 'Cat desplazada');

        $this->assertCampos(['EnProceso' => 1, 'PasadasTrama' => 1501], $this->fila('ReqProgramaTejido', $ids['programa']), 'Programa');
        $this->assertCampos(['FechaFinaliza' => null, 'PasadasTramaFondoC1' => 1501], $this->fila('CatCodificados', $ids['cat']), 'Cat');
        $this->assertCampos(['CodigoDibujo' => 'MBAUSTCO32N060E7'], $this->fila('ReqModelosCodificados', $ids['modelo']), 'Modelo');
    }

    public static function reprogramaciones(): array
    {
        return ['siguiente' => ['reprogramar_siguiente'], 'final' => ['reprogramar_final']];
    }

    // ── Casos de borde que antes dejaban una tabla sin actualizar ───────────

    public function test_jacquard_lleva_sufijo_jc5_en_cat_y_en_modelo(): void
    {
        $programa = $this->programa(['NoTelarId' => '209', 'SalonTejidoId' => 'JACQUARD']);
        $modelo = $this->modelo(['SalonTejidoId' => 'JACQUARD']);
        $cat = $this->cat(['TelarId' => 209, 'Departamento' => 'JACQUARD']);

        $this->guardarBien($this->captura(['NoTelarId' => '209']));

        $this->assertCampos(['CodigoDibujo' => 'MBAUSTCO32N060E7.JC5'], $this->fila('CatCodificados', $cat), 'Cat');
        $this->assertCampos(['CodigoDibujo' => 'MBAUSTCO32N060E7.JC5'], $this->fila('ReqModelosCodificados', $modelo), 'Modelo');
        $this->assertTresTablasDeAcuerdo($cat, $modelo, $programa);
    }

    public function test_sin_renglon_en_cat_se_crea_con_los_datos_de_la_orden_y_la_captura(): void
    {
        $programa = $this->programa();
        $modelo = $this->modelo();

        $this->guardarBien($this->captura());

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '36857')->get();
        $this->assertCount(1, $cat, 'Debe crearse exactamente un renglon.');

        $this->assertCampos([
            'TelarId' => 309, 'Departamento' => 'SMIT', 'ClaveModelo' => 'MOD1', 'Nombre' => 'TOALLA MOD1',
            'ItemId' => 'IT-1', 'HiloAX' => 'OPEN END', 'Razurada' => 'SI', 'FechaTejido' => '2026-09-24 00:00:00',
            'CodigoDibujo' => 'MBAUSTCO32N060E7', 'JulioRizo' => 'JR-7', 'PasadasTramaFondoC1' => 1501,
        ], (array) $cat->first(), 'Cat nuevo');
        $this->assertTresTablasDeAcuerdo((int) $cat->first()->Id, $modelo, $programa);
    }

    public function test_clave_modelo_inutil_en_cat_no_impide_actualizar_el_modelo(): void
    {
        foreach ([null, '', '0', '(MODELO NUEVO)'] as $i => $clave) {
            DB::connection('sqlsrv')->table('ReqProgramaTejido')->delete();
            DB::connection('sqlsrv')->table('ReqModelosCodificados')->delete();
            DB::connection('sqlsrv')->table('CatCodificados')->delete();

            $programa = $this->programa();
            $modelo = $this->modelo();
            $cat = $this->cat(['ClaveModelo' => $clave]);

            $this->guardarBien($this->captura());

            $this->assertTresTablasDeAcuerdo($cat, $modelo, $programa);
        }
    }

    public function test_sin_modelo_se_actualizan_cat_y_programa(): void
    {
        $programa = $this->programa();
        $cat = $this->cat();

        $this->guardarBien($this->captura());

        $this->assertSame(0, DB::connection('sqlsrv')->table('ReqModelosCodificados')->count(), 'No se inventa un modelo.');
        $this->assertCampos(['CodigoDibujo' => 'MBAUSTCO32N060E7', 'PasadasTramaFondoC1' => 1501], $this->fila('CatCodificados', $cat), 'Cat');
        $this->assertCampos(['PasadasTrama' => 1501, 'CalibreTrama' => 12.1, 'FibraTrama' => 'OPEN'], $this->fila('ReqProgramaTejido', $programa), 'Programa');
    }

    public function test_numero_de_orden_repetido_en_otro_telar_no_contamina_la_captura(): void
    {
        // Mismo numero, otro telar y otro producto, con Id mas bajo: first() lo elegia.
        $this->programa(['NoTelarId' => '305', 'TamanoClave' => 'MOD9', 'PasadasTrama' => 5, 'FibraTrama' => 'AJENA', 'EnProceso' => 1, 'Posicion' => 1]);
        $programa = $this->programa();
        $modelo = $this->modelo();
        $cat = $this->cat();

        // Sin detalle: la base sale de la orden, y tiene que ser la del 309.
        $this->guardarBien($this->captura(['pasadas' => [], 'detalle_calibre' => [], 'detalle_hilo' => [], 'detalle_fibra' => [], 'detalle_codcolor' => [], 'detalle_nombrecolor' => [], 'detalle_cuenta' => []]));

        $this->assertCampos(['PasadasTramaFondoC1' => 1776, 'FibraId' => 'ALGODON'], $this->fila('CatCodificados', $cat), 'Cat');
        $this->assertTresTablasDeAcuerdo($cat, $modelo, $programa);
    }

    public function test_orden_compartida_actualiza_todos_sus_programas(): void
    {
        $ids = $this->sembrar();
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $ids['programa'])->update(['OrdCompartida' => 77, 'OrdCompartidaLider' => 1]);
        $hermana = $this->programa(['NoProduccion' => '36858', 'NoTelarId' => '312', 'OrdCompartida' => 77, 'EnProceso' => 0, 'Posicion' => 1]);

        $this->guardarBien($this->captura());

        foreach ([$ids['programa'], $hermana] as $id) {
            $this->assertCampos(
                ['PasadasTrama' => 1501, 'CalibreTrama' => 12.1, 'FibraTrama' => 'OPEN', 'PasadasComb1' => 742, 'PasadasComb2' => null],
                $this->fila('ReqProgramaTejido', $id),
                "Programa {$id}"
            );
        }
    }

    // ── Cambio de telar ─────────────────────────────────────────────────────

    public function test_cambio_de_telar_en_el_mismo_salon_actualiza_las_tres_tablas(): void
    {
        $ids = $this->sembrar();
        $this->programa(['NoProduccion' => '36850', 'NoTelarId' => '311', 'EnProceso' => 1, 'Posicion' => 1]);

        $this->telegram->expects($this->once())->method('enviarProcesoCompletado')->with(
            $this->callback(fn (array $p): bool => ($p['CodigoDibujoAnterior'] ?? null) === 'DIBUJOVIEJO01'
                && $p['NoTelarDestino'] === '311' && $p['NoTelarOrigen'] === '309'),
            $this->anything(),
            'MBAUSTCO32N060E7'
        );

        $this->guardarBien($this->captura(['CambioTelarActivo' => '1', 'TelarDestino' => 'SMIT|311']));

        $this->assertCampos(['NoTelarId' => '311', 'SalonTejidoId' => 'SMIT', 'EnProceso' => 1, 'PasadasTrama' => 1501], $this->fila('ReqProgramaTejido', $ids['programa']), 'Programa');
        $this->assertCampos(['TelarId' => 311, 'Departamento' => 'SMIT'], $this->fila('CatCodificados', $ids['cat']), 'Cat');
        $this->assertCampos(['NoTelarId' => '311', 'CodigoDibujo' => 'MBAUSTCO32N060E7'], $this->fila('ReqModelosCodificados', $ids['modelo']), 'Modelo');
        $this->assertTresTablasDeAcuerdo($ids['cat'], $ids['modelo'], $ids['programa']);

        // El telar de origen se queda con su orden en proceso, intacta.
        $this->assertSame(1, (int) $this->fila('ReqProgramaTejido', $ids['enProceso'])['EnProceso']);
    }

    public function test_cambio_de_telar_a_otro_salon_actualiza_el_modelo_del_destino_y_no_el_del_origen(): void
    {
        $ids = $this->sembrar();
        DB::connection('sqlsrv')->table('ReqModelosCodificados')->where('Id', $ids['modeloOtroSalon'])->delete();
        $this->programa(['NoProduccion' => '36850', 'NoTelarId' => '210', 'SalonTejidoId' => 'JACQUARD', 'EnProceso' => 1, 'Posicion' => 1]);
        $origenAntes = $this->fila('ReqModelosCodificados', $ids['modelo']);

        $this->guardarBien($this->captura(['CambioTelarActivo' => '1', 'TelarDestino' => 'JACQUARD|210']));

        $destino = DB::connection('sqlsrv')->table('ReqModelosCodificados')
            ->where('TamanoClave', 'MOD1')->where('SalonTejidoId', 'JACQUARD')->get();
        $this->assertCount(1, $destino, 'Debe existir un solo modelo en el salon destino.');

        $this->assertCampos(
            ['NoTelarId' => '210', 'OrdenTejido' => '36857', 'CodigoDibujo' => 'MBAUSTCO32N060E7.JC5', 'PasadasTramaFondoC1' => '1501'],
            (array) $destino->first(),
            'Modelo destino'
        );
        $this->assertSame($origenAntes, $this->fila('ReqModelosCodificados', $ids['modelo']), 'El modelo del salon origen no se toca.');
        $this->assertCampos(['NoTelarId' => '210', 'SalonTejidoId' => 'JACQUARD', 'EnProceso' => 1], $this->fila('ReqProgramaTejido', $ids['programa']), 'Programa');
        $this->assertCampos(['TelarId' => 210, 'Departamento' => 'JACQUARD', 'CodigoDibujo' => 'MBAUSTCO32N060E7.JC5'], $this->fila('CatCodificados', $ids['cat']), 'Cat');
        $this->assertTresTablasDeAcuerdo($ids['cat'], (int) $destino->first()->Id, $ids['programa']);
    }

    // ── Karl Mayer ──────────────────────────────────────────────────────────

    public function test_karl_mayer_propaga_sus_barras_a_las_tres_tablas_y_nada_mas(): void
    {
        $barras = [
            'CuentaBarra1' => '24', 'CalibreBarra1' => '150', 'FibraBarra1' => 'POLI', 'CodColorBarra1' => 'N01', 'ColorBarra1' => 'NEGRO', 'PasadasBarra1' => 10,
            'CuentaBarra2' => '20', 'CalibreBarra2' => '75', 'FibraBarra2' => 'NYLON', 'CodColorBarra2' => 'B01', 'ColorBarra2' => 'BLANCO', 'PasadasBarra2' => 6,
            'CuentaBarra3' => '18', 'CalibreBarra3' => '50', 'FibraBarra3' => 'LYCRA', 'CodColorBarra3' => 'R01', 'ColorBarra3' => 'ROJO', 'PasadasBarra3' => 2,
        ];
        $programa = $this->programa(['NoTelarId' => '401', 'SalonTejidoId' => 'KARL MAYER'] + $barras);
        $modelo = $this->modelo(['SalonTejidoId' => 'KARL MAYER'] + $barras);
        $cat = $this->cat(['TelarId' => 401, 'Departamento' => 'KARL MAYER'] + $barras);
        $programaAntes = $this->fila('ReqProgramaTejido', $programa);

        // Se capturan las barras 1 y 2; la 3 se deja de usar.
        $this->guardarBien($this->captura([
            'NoTelarId' => '401', 'NumeroJulioRizo' => '', 'NumeroJulioPie' => '', 'AlturaRizo' => null, 'DesperdicioTrama' => null,
            'pasadas' => ['PasadasBarra1' => 12, 'PasadasBarra2' => 8],
            'detalle_cuenta' => ['26', '20'], 'detalle_calibre' => ['300', '75'], 'detalle_hilo' => ['', ''],
            'detalle_fibra' => ['NYLON', 'NYLON'], 'detalle_codcolor' => ['A01', 'B01'], 'detalle_nombrecolor' => ['AZUL', 'BLANCO'],
        ]));

        $esperado = [
            'CuentaBarra1' => '26', 'CalibreBarra1' => '300', 'FibraBarra1' => 'NYLON', 'CodColorBarra1' => 'A01', 'ColorBarra1' => 'AZUL', 'PasadasBarra1' => 12,
            'CuentaBarra2' => '20', 'CalibreBarra2' => '75', 'FibraBarra2' => 'NYLON', 'CodColorBarra2' => 'B01', 'ColorBarra2' => 'BLANCO', 'PasadasBarra2' => 8,
        ] + $this->vacias($this->combos(['CuentaBarra#', 'CalibreBarra#', 'FibraBarra#', 'CodColorBarra#', 'ColorBarra#', 'PasadasBarra#'], [3, 4]));

        $this->assertCampos($esperado, $this->fila('CatCodificados', $cat), 'Cat');
        $this->assertCampos($esperado, $this->fila('ReqProgramaTejido', $programa), 'Programa');
        $this->assertCampos($esperado, $this->fila('ReqModelosCodificados', $modelo), 'Modelo');

        // Karl Mayer no tiene trama ni combinaciones: esas columnas del programa no se tocan.
        $programaDespues = $this->fila('ReqProgramaTejido', $programa);
        foreach (['CalibreTrama', 'FibraTrama', 'PasadasTrama', 'CalibreComb1', 'FibraComb1', 'PasadasComb1', 'CalibreComb2', 'PasadasComb3'] as $c) {
            $this->assertSame($programaAntes[$c], $programaDespues[$c], "Programa.{$c} no es de Karl Mayer y cambio.");
        }
    }

    // ── Todo o nada ─────────────────────────────────────────────────────────

    public function test_una_captura_invalida_no_toca_ninguna_tabla(): void
    {
        $ids = $this->sembrar();
        $antes = $this->volcado();

        $respuesta = $this->guardar($this->captura(['AlturaRizo' => '11']));

        $this->assertFalse($respuesta['success']);
        $this->assertSame($antes, $this->volcado());
    }

    public function test_si_falla_una_tabla_no_queda_ninguna_a_medias(): void
    {
        $ids = $this->sembrar();
        $antes = $this->volcado();

        // 16 letras: cabe en CatCodificados (varchar max) y en el modelo (100), pero no
        // en ReqProgramaTejido.FibraTrama, que es nvarchar(15). SQL Server rechaza el UPDATE.
        $respuesta = $this->guardar($this->captura(['detalle_fibra' => ['ALGODON PEINADO1', 'PEINADO']]));

        $this->assertFalse($respuesta['success']);
        $this->assertSame($antes, $this->volcado(), 'Cat y el modelo no deben quedar escritos si el programa fallo.');
    }

    /** Estado completo de las tres tablas, para comparar antes y despues. */
    private function volcado(): array
    {
        return collect(['CatCodificados', 'ReqModelosCodificados', 'ReqProgramaTejido'])
            ->mapWithKeys(fn (string $t) => [$t => DB::connection('sqlsrv')->table($t)->orderBy('Id')->get()->map(fn ($f) => (array) $f)->all()])
            ->all();
    }
}
