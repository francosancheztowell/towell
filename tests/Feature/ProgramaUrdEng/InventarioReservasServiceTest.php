<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use App\Services\ProgramaUrdEng\InventarioReservasService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * Caracterización de InventarioReservasService (19-05 p2.4, TESTS PRIMERO): se escribieron
 * contra el código anterior a PERF-08 y siguen verdes después.
 */
class InventarioReservasServiceTest extends TestCase
{
    use InventarioUrdEngSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInventario();
    }

    protected function tearDown(): void
    {
        $this->tearDownInventario();
        parent::tearDown();
    }

    /* ---------- normalizeFilters / normalizeDimValue / dimKey ---------- */

    public function test_normalize_filters_solo_deja_pares_columna_valor_como_string(): void
    {
        $svc = new InventarioReservasService;

        $this->assertSame([], $svc->normalizeFilters('ItemId=1'));
        $this->assertSame([], $svc->normalizeFilters(null));
        $this->assertSame(
            [['columna' => 'ItemId', 'valor' => 'JU'], ['columna' => 'Metros', 'valor' => '12.5']],
            $svc->normalizeFilters([
                ['columna' => 'ItemId', 'valor' => 'JU'],
                ['columna' => 'ConfigId'],
                'basura',
                ['columna' => 'Metros', 'valor' => 12.5, 'extra' => 'x'],
            ])
        );
    }

    public function test_dim_key_normaliza_nulos_y_espacios_en_array_y_objeto(): void
    {
        $svc = new InventarioReservasService;

        $this->assertSame('', $svc->normalizeDimValue(null));
        $this->assertSame('', $svc->normalizeDimValue('NULL'));
        $this->assertSame('', $svc->normalizeDimValue('null'));
        $this->assertSame('A1', $svc->normalizeDimValue('  A1 '));
        $this->assertSame('7', $svc->normalizeDimValue(7));

        $esperada = 'JU-ENG-RI-01|H1|3156||A-JUL/TELA|00061||00061-744';
        $this->assertSame($esperada, $svc->dimKey([
            'ItemId' => ' JU-ENG-RI-01', 'ConfigId' => 'H1', 'InventSizeId' => '3156', 'InventColorId' => 'null',
            'InventLocationId' => 'A-JUL/TELA', 'InventBatchId' => '00061', 'InventSerialId' => '00061-744 ',
        ]));
        $this->assertSame($esperada, $svc->dimKey((object) [
            'ItemId' => 'JU-ENG-RI-01', 'ConfigId' => 'H1', 'InventSizeId' => '3156', 'InventColorId' => null,
            'InventLocationId' => 'A-JUL/TELA', 'InventBatchId' => '00061', 'WMSLocationId' => '', 'InventSerialId' => '00061-744',
        ]));
    }

    public function test_es_violacion_de_unico_lee_el_codigo_del_driver(): void
    {
        $qe = function (int $codigo): QueryException {
            $pdo = new PDOException('x');
            $pdo->errorInfo = ['23000', $codigo, 'x'];

            return new QueryException('sqlsrv', 'insert', [], $pdo);
        };

        $this->assertTrue(InventarioReservasService::esViolacionDeUnico($qe(2627)));
        $this->assertTrue(InventarioReservasService::esViolacionDeUnico($qe(2601)));
        $this->assertFalse(InventarioReservasService::esViolacionDeUnico($qe(547)));
    }

    /* ---------- getDisponibleData ---------- */

    public function test_disponible_cruza_ti_pro_con_reservas_activas_por_dim_key(): void
    {
        $this->reservaActiva($this->dims('00061-744', '00061') + ['NoTelarId' => '401', 'SalonTejidoId' => 'Jacquard']);
        // Cancelada: no cuenta.
        $this->reservaActiva($this->dims('00062-100', '00062') + ['NoTelarId' => '402', 'Status' => 'Cancelado']);

        $svc = $this->servicioConTi([
            $this->filaTi('00061-744', '00061'),
            $this->filaTi('00062-100', '00062'),
        ]);

        $r = $svc->getDisponibleData([]);

        $this->assertSame(2, $r['total']);
        $this->assertSame('401', $r['data'][0]->NoTelarId);
        $this->assertSame('Jacquard', $r['data'][0]->SalonTejidoId);
        $this->assertNotNull($r['data'][0]->ReservaId);
        $this->assertNull($r['data'][1]->NoTelarId);
        $this->assertNull($r['data'][1]->ReservaId);
        $this->assertNull($r['data'][1]->SalonTejidoId);
    }

    public function test_filtro_no_telar_disponible_o_texto_se_resuelve_local(): void
    {
        $this->reservaActiva($this->dims('00061-744', '00061') + ['NoTelarId' => '401']);
        $this->reservaActiva($this->dims('00063-1', '00063') + ['NoTelarId' => '1105']);
        $filas = fn () => [
            $this->filaTi('00061-744', '00061'),
            $this->filaTi('00062-100', '00062'),
            $this->filaTi('00063-1', '00063'),
        ];

        $svc = $this->servicioConTi($filas());
        $disp = $svc->getDisponibleData([['columna' => 'NoTelarId', 'valor' => 'Disponible']]);
        $this->assertSame(['00062-100'], array_column($disp['data'], 'InventSerialId'));
        $this->assertSame([], $svc->filtrosRecibidos, 'NoTelarId no viaja a TI-PRO.');

        $svc = $this->servicioConTi($filas());
        $texto = $svc->getDisponibleData([['columna' => 'NoTelarId', 'valor' => '10'], ['columna' => 'ItemId', 'valor' => 'JU']]);
        $this->assertSame(['00063-1'], array_column($texto['data'], 'InventSerialId'));
        $this->assertSame([['columna' => 'ItemId', 'valor' => 'JU']], $svc->filtrosRecibidos);

        foreach (['null', 'vacío', 'vacio'] as $palabra) {
            $svc = $this->servicioConTi($filas());
            $this->assertSame(1, $svc->getDisponibleData([['columna' => 'NoTelarId', 'valor' => $palabra]])['total']);
        }
    }

    public function test_julio_urdido_toma_el_tipo_del_programa_por_folio_y_filtra_barra(): void
    {
        // '01269' en el programa, '1269' en el lote: cruzan. Recaptura: gana el Id mayor.
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            ['Folio' => '01269', 'RizoPie' => '1'],
            ['Folio' => '01269', 'RizoPie' => '2'],
            ['Folio' => '04072', 'RizoPie' => '3'],
            ['Folio' => '00099', 'RizoPie' => '  '],
        ]);

        $filas = fn () => [
            $this->filaTi('K6', '1269', 'JULIO-URDIDO-A'),
            $this->filaTi('K12', '04072', 'JULIO-URDIDO-B'),
            $this->filaTi('K13', '99', 'JULIO-URDIDO-B'),
            $this->filaTi('00061-744', '00061'),
        ];

        $todos = $this->servicioConTi($filas())->getDisponibleData([]);
        $this->assertSame(['2', '3', null, 'Rizo'], array_column($todos['data'], 'Tipo'));

        $svc = $this->servicioConTi($filas());
        $barra = $svc->getDisponibleData([['columna' => 'Tipo', 'valor' => '2']]);
        $this->assertSame(['K6'], array_column($barra['data'], 'InventSerialId'));
        $this->assertSame([], $svc->filtrosRecibidos, 'Una barra no se filtra en TI-PRO.');

        $svc = $this->servicioConTi($filas());
        $svc->getDisponibleData([['columna' => 'Tipo', 'valor' => 'Rizo']]);
        $this->assertSame([['columna' => 'Tipo', 'valor' => 'Rizo']], $svc->filtrosRecibidos);
    }

    public function test_sin_julios_de_urdido_no_consulta_el_programa(): void
    {
        $svc = $this->servicioConTi([$this->filaTi('00061-744', '00061')]);

        // antes 1 (reservas) y sigue en 1: tiposPorFolioUrdido no consulta si no hay lotes KM.
        $this->assertSame(1, $this->contarQueries(fn () => $svc->getDisponibleData([])));
    }

    /* ---------- consultaDisponibleTi (SQL de TI-PRO sin ejecutar) ---------- */

    public function test_consulta_ti_traduce_filtros_a_sql_server(): void
    {
        $svc = new InventarioReservasService;
        $q = $svc->consultaDisponibleTi(DB::connection('sqlsrv_ti'), [
            ['columna' => 'Tipo', 'valor' => 'rizo'],
            ['columna' => 'ProdDate', 'valor' => '2026-09-01'],
            ['columna' => 'InventQty', 'valor' => '12'],
            ['columna' => 'Metros', 'valor' => 'abc'],
            ['columna' => 'ConfigId', 'valor' => 'H1'],
            ['columna' => 'NoExiste', 'valor' => 'x'],
            ['columna' => 'ItemId', 'valor' => '  '],
        ], 50);

        $sql = $q->toSql();
        $this->assertStringContainsString('select top 50', $sql);
        $this->assertStringContainsString('InventSum AS s WITH (NOLOCK)', $sql);
        $this->assertStringContainsString('InventSerial AS ser WITH (NOLOCK)', $sql);
        $this->assertStringContainsString('CAST(ser.ProdDate AS DATE) = ?', $sql);
        $this->assertStringContainsString('ISNULL(s.PhysicalInvent,0) = ?', $sql);
        $this->assertStringContainsString('CAST(ISNULL(ser.TwMts,0) AS NVARCHAR(50)) LIKE ?', $sql);
        $this->assertStringContainsString('LOWER(CAST(d.ConfigId AS NVARCHAR(100))) LIKE ?', $sql);
        $this->assertStringNotContainsString('NoExiste', $sql);
        foreach (['OFFSET', 'FETCH', 'TRY_CONVERT', 'IIF(', 'CONCAT(', 'STRING_AGG'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $sql);
        }

        $bindings = $q->getBindings();
        $this->assertContains('2026-09-01', $bindings);
        $this->assertContains(12.0, $bindings);
        $this->assertContains('%abc%', $bindings);
        $this->assertContains('%h1%', $bindings);
        $this->assertSame(2, count(array_keys($bindings, 'JU-ENG-RI%', true)) - 1, 'Tipo rizo agrega otro LIKE JU-ENG-RI%.');
    }

    public function test_consulta_ti_con_fecha_invalida_busca_por_texto(): void
    {
        $q = (new InventarioReservasService)->consultaDisponibleTi(DB::connection('sqlsrv_ti'), [
            ['columna' => 'ProdDate', 'valor' => 'no-es-fecha'],
            ['columna' => 'Tipo', 'valor' => 'pie'],
        ]);

        $this->assertStringContainsString('CAST(ser.ProdDate AS NVARCHAR(23)) LIKE ?', $q->toSql());
        $this->assertContains('%no-es-fecha%', $q->getBindings());
        $this->assertStringContainsString('select top 2000', $q->toSql());
    }

    /* ---------- getReservasPorTelar / diagnóstico ---------- */

    public function test_reservas_por_telar_solo_activas_de_ese_telar_mas_recientes_primero(): void
    {
        $a = $this->reservaActiva($this->dims('00061-744', '00061') + ['NoTelarId' => '401']);
        $b = $this->reservaActiva($this->dims('00062-1', '00062') + ['NoTelarId' => '401']);
        $this->reservaActiva($this->dims('00063-1', '00063') + ['NoTelarId' => '401', 'Status' => 'Cancelado']);
        $c = $this->reservaActiva($this->dims('00064-1', '00064') + ['NoTelarId' => '402']);

        $svc = new InventarioReservasService;
        $r = $svc->getReservasPorTelar('401');

        $this->assertSame([$b, $a], $r->pluck('Id')->all());
        $this->assertSame('JU-ENG-RI-01|H1|3156||A-JUL/TELA|00062||00062-1', $r->first()->dimKey);

        $this->assertCount(3, $svc->getDiagnosticoReservas(null, 10));
        $this->assertCount(1, $svc->getDiagnosticoReservas('402', 10));
        $this->assertSame([$c], $svc->getDiagnosticoReservas('', 1)->pluck('Id')->all());
    }

    /* ---------- ejecutarReserva ---------- */

    public function test_reserva_deriva_el_lote_y_marca_el_telar(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => false]);

        $r = (new InventarioReservasService)->ejecutarReserva($this->datos($id) + [
            'ConfigId' => ' H1 ', 'InventSizeId' => '3156', 'InventColorId' => 'null', 'NoProveedor' => ' P9 ',
            'InventBatchId' => 'IGNORADO',
        ]);

        $this->assertSame(['created' => true, 'message' => 'Pieza reservada correctamente.'], $r);
        $reserva = InvTelasReservadas::sole();
        $this->assertSame('00061', $reserva->InventBatchId, 'El lote es el prefijo del julio.');
        $this->assertNull($reserva->JulioPrincipal);

        $telar = TejInventarioTelares::find($id);
        $this->assertTrue($telar->Reservado);
        $this->assertSame('H1', $telar->ConfigId);
        $this->assertSame('3156', $telar->InventSizeId);
        $this->assertSame('', $telar->InventColorId);
        $this->assertSame('00061', $telar->LoteProveedor);
        $this->assertSame('P9', $telar->NoProveedor);
    }

    public function test_reserva_sin_telar_no_toca_telares(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => false]);
        $datos = $this->datos($id);
        unset($datos['TejInventarioTelaresId']);

        $r = (new InventarioReservasService)->ejecutarReserva($datos);

        $this->assertTrue($r['created']);
        $this->assertFalse((bool) TejInventarioTelares::find($id)->Reservado);
    }

    public function test_duplicado_por_indice_unico_no_revienta_y_marca_el_telar(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => false]);
        $this->fallarInsertReserva(2627);

        $r = (new InventarioReservasService)->ejecutarReserva($this->datos($id));

        $this->assertSame(['created' => false, 'message' => 'La pieza ya estaba reservada (se evitó el duplicado).'], $r);
        $this->assertTrue(TejInventarioTelares::find($id)->Reservado);
    }

    public function test_otro_error_de_bd_revierte_todo_incluido_el_aviso(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => false]);
        $aviso = $this->aviso(['telar' => '401', 'tipo' => 'Rizo', 'hora' => '07:00', 'Fecha' => '2026-09-01']);
        $this->fallarInsertReserva(547);

        try {
            (new InventarioReservasService)->ejecutarReserva($this->datos($id));
            $this->fail('Se esperaba QueryException.');
        } catch (QueryException $e) {
            $this->assertSame(547, $e->errorInfo[1]);
        }

        $this->assertFalse((bool) TejInventarioTelares::find($id)->Reservado);
        $this->assertNull(TejInventarioTelares::find($id)->horaParo);
        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('TejNotificaTejedor')->where('id', $aviso)->value('Reserva'));
    }

    public function test_telar_inactivo_revienta_y_no_deja_reserva(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'status' => 'Inactivo']);

        try {
            (new InventarioReservasService)->ejecutarReserva($this->datos($id));
            $this->fail('Se esperaba RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("No se encontró telar activo {$id}", $e->getMessage());
        }

        $this->assertSame(0, InvTelasReservadas::count());
    }

    public function test_regla_tejedor_consume_el_aviso_mas_reciente_y_pasa_la_hora_al_telar(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'no_julio' => '00061-744', 'no_orden' => '00061']);
        $viejo = $this->aviso(['telar' => '401', 'tipo' => 'Rizo', 'hora' => '06:00', 'Fecha' => '2026-08-01']);
        $nuevo = $this->aviso(['telar' => ' 401 ', 'tipo' => ' RIZO ', 'hora' => '07:30', 'Fecha' => '2026-09-01']);
        $this->aviso(['telar' => '401', 'tipo' => 'Rizo', 'hora' => '09:00', 'Fecha' => '2026-09-30', 'Reserva' => 1]);
        $this->aviso(['telar' => '401', 'tipo' => 'Pie', 'hora' => '10:00', 'Fecha' => '2026-09-30']);

        (new InventarioReservasService)->ejecutarReserva($this->datos($id));

        $avisos = DB::connection('sqlsrv')->table('TejNotificaTejedor')->orderBy('id')->get()->keyBy('id');
        $this->assertSame(1, (int) $avisos[$nuevo]->Reserva);
        $this->assertSame('00061-744', $avisos[$nuevo]->no_julio);
        $this->assertSame('00061', $avisos[$nuevo]->no_orden);
        $this->assertSame(0, (int) $avisos[$viejo]->Reserva, 'Solo uno por reserva.');

        $telar = TejInventarioTelares::find($id);
        $this->assertSame('07:30', $telar->horaParo);
        $this->assertTrue($telar->Reservado);
    }

    public function test_regla_tejedor_toma_el_tipo_del_telar_y_el_serial_si_el_telar_no_tiene_julio(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'pie']);
        $aviso = $this->aviso(['telar' => '401', 'tipo' => 'Pie', 'hora' => '', 'Fecha' => '2026-09-01']);
        $datos = $this->datos($id);
        unset($datos['Tipo']);

        (new InventarioReservasService)->ejecutarReserva($datos);

        $fila = DB::connection('sqlsrv')->table('TejNotificaTejedor')->where('id', $aviso)->first();
        $this->assertSame(1, (int) $fila->Reserva);
        $this->assertSame('00061-744', $fila->no_julio, 'Sin julio en el telar, va el serial de la reserva.');
        $this->assertNull($fila->no_orden);
        $this->assertNull(TejInventarioTelares::find($id)->horaParo, 'Hora vacía no pisa horaParo.');
    }

    public function test_regla_tejedor_sin_id_busca_el_telar_activo_mas_reciente_por_numero_y_tipo(): void
    {
        $this->telar(['no_telar' => '401', 'tipo' => 'Rizo']);
        $reciente = $this->telar(['no_telar' => '401', 'tipo' => 'rizo']);
        $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'status' => 'Inactivo']);
        $this->aviso(['telar' => '401', 'tipo' => 'Rizo', 'hora' => '08:00', 'Fecha' => '2026-09-01']);
        $datos = $this->datos(0);
        unset($datos['TejInventarioTelaresId']);

        (new InventarioReservasService)->ejecutarReserva($datos);

        $this->assertSame([$reciente], TejInventarioTelares::where('horaParo', '08:00')->pluck('id')->all());
    }

    public function test_regla_tejedor_sin_telar_objetivo_igual_cierra_el_aviso(): void
    {
        $aviso = $this->aviso(['telar' => '999', 'tipo' => 'Rizo', 'hora' => '08:00', 'Fecha' => '2026-09-01']);
        $datos = ['NoTelarId' => '999'] + $this->datos(0);
        unset($datos['TejInventarioTelaresId']);

        (new InventarioReservasService)->ejecutarReserva($datos);

        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('TejNotificaTejedor')->where('id', $aviso)->value('Reserva'));
    }

    public function test_barra_km_guarda_julio_y_orden_principal(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => '2', 'no_julio' => ' K6 ', 'no_orden' => '01269']);

        (new InventarioReservasService)->ejecutarReserva(
            ['InventSerialId' => 'K7', 'InventBatchId' => '01269', 'Tipo' => '2'] + $this->datos($id)
        );

        $r = InvTelasReservadas::sole();
        $this->assertSame('K6', $r->JulioPrincipal);
        $this->assertSame('01269', $r->OrdenPrincipal);
    }

    /* ---------- ejecutarCancelar ---------- */

    public function test_cancelar_por_id_libera_el_telar_si_ya_no_tiene_reservas(): void
    {
        $telar = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => true]);
        $otro = $this->telar(['no_telar' => '401', 'tipo' => 'Pie', 'Reservado' => true, 'status' => 'Inactivo']);
        $id = $this->reservaActiva($this->dims('00061-744', '00061') + ['NoTelarId' => '401']);

        $r = (new InventarioReservasService)->ejecutarCancelar(['Id' => $id]);

        $this->assertSame(['updated' => true], $r);
        $this->assertSame('Cancelado', InvTelasReservadas::find($id)->Status);
        $this->assertFalse(TejInventarioTelares::find($telar)->Reservado);
        $this->assertTrue(TejInventarioTelares::find($otro)->Reservado, 'Solo telares activos.');
    }

    public function test_cancelar_con_otra_reserva_activa_no_libera_el_telar(): void
    {
        $telar = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => true]);
        $this->reservaActiva($this->dims('00061-744', '00061') + ['NoTelarId' => '401']);
        $this->reservaActiva($this->dims('00062-1', '00062') + ['NoTelarId' => '401']);

        $r = (new InventarioReservasService)->ejecutarCancelar(
            ['NoTelarId' => '401'] + $this->dims('00061-744', '00061')
        );

        $this->assertTrue($r['updated']);
        $this->assertSame(1, InvTelasReservadas::where('Status', 'Reservado')->count());
        $this->assertTrue(TejInventarioTelares::find($telar)->Reservado);
    }

    public function test_cancelar_sin_coincidencias_no_toca_nada(): void
    {
        $telar = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => true]);
        $this->reservaActiva($this->dims('00061-744', '00061') + ['NoTelarId' => '401']);

        $r = (new InventarioReservasService)->ejecutarCancelar(
            ['NoTelarId' => '401', 'ItemId' => 'JU-ENG-RI-01', 'InventSerialId' => 'otro']
        );

        $this->assertSame(['updated' => false], $r);
        $this->assertTrue(TejInventarioTelares::find($telar)->Reservado);
    }

    /* ---------- helpers ---------- */

    /** @return array<string, string> */
    private function dims(string $serial, string $lote, string $item = 'JU-ENG-RI-01'): array
    {
        return [
            'ItemId' => $item, 'ConfigId' => 'H1', 'InventSizeId' => '3156', 'InventColorId' => '',
            'InventLocationId' => 'A-JUL/TELA', 'InventBatchId' => $lote, 'WMSLocationId' => '', 'InventSerialId' => $serial,
        ];
    }

    private function filaTi(string $serial, string $lote, string $item = 'JU-ENG-RI-01'): object
    {
        $tipo = str_starts_with($item, 'JU-ENG-RI') ? 'Rizo' : (str_starts_with($item, 'JU-ENG-PI') ? 'Pie' : null);

        return (object) ($this->dims($serial, $lote, $item) + [
            'Tipo' => $tipo, 'Metros' => 1200.0, 'InventQty' => 50.5, 'ProdDate' => '2026-09-01 00:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(int $telarId): array
    {
        return [
            'NoTelarId' => '401', 'SalonTejidoId' => 'Jacquard', 'ItemId' => 'JU-ENG-RI-01',
            'InventSerialId' => '00061-744', 'InventBatchId' => '00061', 'Tipo' => 'Rizo',
            'Status' => 'Reservado', 'TejInventarioTelaresId' => $telarId,
        ];
    }

    /**
     * El servicio real con TI-PRO sustituido: devuelve $filas y guarda los filtros que le llegan.
     *
     * @param  array<int, object>  $filas
     */
    private function servicioConTi(array $filas): InventarioReservasService
    {
        return new class($filas) extends InventarioReservasService
        {
            /** @var array<int, array<string, string>>|null */
            public ?array $filtrosRecibidos = null;

            /** @param array<int, object> $filas */
            public function __construct(private array $filas) {}

            protected function queryDisponibleFromTiPro(array $filtros = [], int $limit = 2000): array
            {
                $this->filtrosRecibidos = $filtros;

                return $this->filas;
            }
        };
    }
}
