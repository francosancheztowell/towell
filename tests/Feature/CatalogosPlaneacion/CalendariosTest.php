<?php

namespace Tests\Feature\CatalogosPlaneacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqCalendarioTab;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\CatalogosPlaneacion\Concerns\CatalogosFixtures;
use Tests\TestCase;

/**
 * Caracterización (19-06b) de CalendarioController ANTES de partirlo: calendarios, líneas,
 * edición masiva por turnos, borrado por rango, Excel real y el recálculo de programas de tejido
 * en cascada por telar (recalcularProgramasPorCalendario, 212 líneas sin tests hasta hoy).
 */
class CalendariosTest extends TestCase
{
    use CatalogosFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogos();
        BalancearTejido::clearCalendarioLinesCache();
    }

    protected function tearDown(): void
    {
        BalancearTejido::clearCalendarioLinesCache();
        parent::tearDown();
    }

    /** Calendario continuo: 3 turnos de 8 h por día desde el 5 de enero de 2026 a las 06:30. */
    private function calendarioContinuo(string $id = 'CAL', int $dias = 6): void
    {
        ReqCalendarioTab::create(['CalendarioId' => $id, 'Nombre' => 'Tejido 3 turnos']);
        $filas = [];
        $inicio = Carbon::parse('2026-01-05 06:30:00');
        for ($t = 0; $t < $dias * 3; $t++) {
            $ini = $inicio->copy()->addHours(8 * $t);
            $filas[] = ['CalendarioId' => $id, 'FechaInicio' => $ini->format('Y-m-d H:i:s'),
                'FechaFin' => $ini->copy()->addHours(8)->format('Y-m-d H:i:s'), 'HorasTurno' => 8, 'Turno' => ($t % 3) + 1];
        }
        ReqCalendarioLine::insert($filas);
    }

    /** @param  list<list<mixed>>  $filas */
    private function excel(array $filas): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas);
        $ruta = tempnam(sys_get_temp_dir(), 'cal').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'calendarios.xlsx', null, null, true);
    }

    public function test_index_y_json(): void
    {
        $this->calendarioContinuo();
        $this->get('/planeacion/catalogos/calendarios')->assertOk()->assertSee('Tejido 3 turnos');
        $this->getJson('/planeacion/calendarios/json')->assertOk()
            ->assertJson(['success' => true, 'data' => [['CalendarioId' => 'CAL', 'Nombre' => 'Tejido 3 turnos']]]);
    }

    public function test_alta_con_turnos_crea_lineas_por_dia_y_valida_24_horas(): void
    {
        $turnos = ['1' => ['lunes' => ['horas' => 8, 'inicio' => '06:30', 'activo' => true], 'martes' => ['horas' => 8, 'inicio' => '06:30']],
            '2' => ['lunes' => ['horas' => 8, 'inicio' => '14:30', 'activo' => false]]];
        $this->postJson('/planeacion/calendarios', ['CalendarioId' => 'NUEVO', 'Nombre' => 'Nuevo', 'FechaInicial' => '2026-01-05', 'FechaFinal' => '2026-01-11', 'Turnos' => $turnos])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Calendario creado exitosamente', 'lineas_creadas' => 2]);
        $primera = ReqCalendarioLine::where('CalendarioId', 'NUEVO')->orderBy('FechaInicio')->first();
        $this->assertSame('2026-01-05 06:30:00', Carbon::parse($primera->FechaInicio)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-05 14:30:00', Carbon::parse($primera->FechaFin)->format('Y-m-d H:i:s'));

        $this->postJson('/planeacion/calendarios', ['CalendarioId' => 'NUEVO', 'Nombre' => 'Otra'])->assertStatus(422)->assertJson(['success' => false]);
        $demasiado = ['1' => ['lunes' => ['horas' => 20, 'inicio' => '00:00']], '2' => ['lunes' => ['horas' => 6, 'inicio' => '20:00']]];
        $this->postJson('/planeacion/calendarios', ['CalendarioId' => 'X', 'Nombre' => 'X', 'FechaInicial' => '2026-01-05', 'FechaFinal' => '2026-01-05', 'Turnos' => $demasiado])
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'La suma de horas para lunes no puede ser mayor a 24']);
        $this->assertNull(ReqCalendarioTab::find('X'), 'la transacción se revierte');
    }

    public function test_detalle_resume_turnos_por_dia_y_rango(): void
    {
        $this->calendarioContinuo(dias: 2);
        $this->getJson('/planeacion/calendarios/CAL/detalle')->assertOk()
            ->assertJsonPath('data.fechaInicial', '2026-01-05')
            ->assertJsonPath('data.fechaFinal', '2026-01-06')
            ->assertJsonPath('data.turnos.1.lunes', ['horas' => 8, 'inicio' => '06:30:00', 'fin' => '14:30:00', 'activo' => true])
            ->assertJsonPath('data.turnos.1.domingo.activo', false);
        $this->getJson('/planeacion/calendarios/NADA/detalle')->assertNotFound();
    }

    public function test_masivo_reemplaza_solo_el_rango(): void
    {
        $this->calendarioContinuo(dias: 6);
        $turnos = ['1' => ['miercoles' => ['horas' => 4, 'inicio' => '07:00']]];
        $this->putJson('/planeacion/calendarios/CAL/masivo', ['Nombre' => 'Renombrado', 'FechaInicial' => '2026-01-07', 'FechaFinal' => '2026-01-07', 'Turnos' => $turnos])
            ->assertOk()->assertJson(['success' => true, 'lineas_creadas' => 1]);
        $this->assertSame('Renombrado', ReqCalendarioTab::find('CAL')->Nombre);
        $delDia = ReqCalendarioLine::where('CalendarioId', 'CAL')->where('FechaInicio', 'like', '2026-01-07%')->get();
        $this->assertCount(1, $delDia);
        $this->assertEquals(4, $delDia->first()->HorasTurno);
        $this->assertSame(18 - 4 + 1, ReqCalendarioLine::count(), 'se borran las 4 líneas que tocan el 7 (incluida la que viene del 6)');
    }

    public function test_actualizar_nombre_y_borrar_calendario(): void
    {
        $this->calendarioContinuo(dias: 1);
        $this->putJson('/planeacion/calendarios/CAL', ['Nombre' => 'Otro'])->assertOk()->assertJson(['success' => true]);
        $this->putJson('/planeacion/calendarios/NADA', ['Nombre' => 'Otro'])->assertNotFound();

        $this->programa(['Id' => 1, 'CalendarioId' => 'CAL', 'NoTelarId' => '201']);
        $this->deleteJson('/planeacion/calendarios/CAL')->assertStatus(422)
            ->assertJson(['message' => 'No se puede eliminar el calendario porque esta siendo utilizado por 1 programa(s) de tejido.']);
        ReqProgramaTejido::query()->delete();
        $this->deleteJson('/planeacion/calendarios/CAL')->assertOk()->assertJson(['success' => true]);
        $this->assertSame(0, ReqCalendarioLine::count());
    }

    public function test_recalcular_encadena_por_telar_respetando_el_primero(): void
    {
        $this->calendarioContinuo();
        $this->programa(['Id' => 1, 'CalendarioId' => 'CAL', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'EnProceso' => 1,
            'FechaInicio' => '2026-01-05 08:00:00', 'FechaFinal' => '2026-01-05 09:00:00', 'HorasProd' => 10, 'SaldoPedido' => 480, 'PesoCrudo' => 500]);
        $this->programa(['Id' => 2, 'CalendarioId' => 'CAL', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201',
            'FechaInicio' => '2026-01-06 00:00:00', 'HorasProd' => 5, 'EntregaPT' => '2026-01-20 00:00:00']);
        $this->programa(['Id' => 3, 'CalendarioId' => 'CAL', 'SalonTejidoId' => 'SMITH', 'NoTelarId' => '305',
            'FechaInicio' => '2026-01-07 10:00:00', 'HorasProd' => 0]);
        $this->programa(['Id' => 4, 'CalendarioId' => 'OTRO', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201',
            'FechaInicio' => '2026-01-05 07:00:00', 'HorasProd' => 3]);

        $res = $this->postJson('/planeacion/calendarios/CAL/recalcular-programas')->assertOk()
            ->assertJson(['success' => true, 'data' => ['calendario_id' => 'CAL', 'calendario_nombre' => 'Tejido 3 turnos']])
            ->json('data.recalculo');
        $this->assertSame(2, $res['procesados']);
        $this->assertSame(2, $res['actualizados']);
        $this->assertSame(1, $res['errores'], 'el programa sin horas ni modelo no se puede calcular');

        $p1 = ReqProgramaTejido::find(1);
        $this->assertSame('2026-01-05 08:00:00', Carbon::parse($p1->FechaInicio)->format('Y-m-d H:i:s'), 'el primero del telar no se mueve');
        $this->assertSame('2026-01-05 18:00:00', Carbon::parse($p1->FechaFinal)->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(10 / 24, (float) $p1->DiasEficiencia, 0.0001);
        $this->assertEqualsWithDelta(round((480 / (10 / 24)) / 24, 4), (float) $p1->StdHrsEfect, 0.0001);
        $this->assertEqualsWithDelta(round(10 / 24, 4), (float) $p1->DiasJornada, 0.0001);
        $this->assertSame('2026-01-17 18:00:00', Carbon::parse($p1->EntregaCte)->format('Y-m-d H:i:s'), 'fin + 12 días');

        $p2 = ReqProgramaTejido::find(2);
        $this->assertSame('2026-01-05 18:00:00', Carbon::parse($p2->FechaInicio)->format('Y-m-d H:i:s'), 'arranca donde termina el anterior');
        $this->assertSame('2026-01-05 23:00:00', Carbon::parse($p2->FechaFinal)->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(round(Carbon::parse('2026-01-17 23:00:00')->diffInDays(Carbon::parse('2026-01-20'), false), 2), (float) $p2->PTvsCte, 0.001);

        $this->assertSame('2026-01-05 07:00:00', Carbon::parse(ReqProgramaTejido::find(4)->FechaInicio)->format('Y-m-d H:i:s'), 'otro calendario no se toca');
        $this->assertNotNull(ReqProgramaTejido::getEventDispatcher(), 'los observers vuelven a quedar activos');
    }

    public function test_recalcular_calendario_inexistente_404_sin_listar_otros(): void
    {
        $this->calendarioContinuo();
        $this->postJson('/planeacion/calendarios/NADA/recalcular-programas')->assertNotFound()->assertJson(['success' => false]);
    }

    public function test_excel_de_calendarios_y_de_lineas_reemplaza_todo(): void
    {
        $this->calendarioContinuo(dias: 1);
        $tab = $this->excel([['No Calendario', 'Nombre'], ['C1', 'Uno'], ['C2', 'Dos']]);
        $this->post('/planeacion/calendarios/excel', ['archivo_excel' => $tab, 'tipo' => 'calendarios'], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true, 'data' => ['registros_procesados' => 2]]);
        $this->assertSame(['C1', 'C2'], ReqCalendarioTab::orderBy('CalendarioId')->pluck('CalendarioId')->all());
        $this->assertSame(0, ReqCalendarioLine::count(), 'el Excel de calendarios borra también las líneas');

        $lineas = $this->excel([['No Calendario', 'Inicio (Fecha Hora)', 'Fin (Fecha Hora)', 'Horas', 'Turno'],
            ['C1', '05/01/2026 06:30', '05/01/2026 14:30', 8, 1], ['C1', '05/01/2026 14:30', '05/01/2026 22:30', 8, 4]]);
        $this->post('/planeacion/calendarios/excel', ['archivo_excel' => $lineas, 'tipo' => 'lineas'], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['success' => true, 'data' => ['total_errores' => 1]]);
        $this->assertSame(1, ReqCalendarioLine::count(), 'el turno 4 se rechaza');

        $this->post('/planeacion/calendarios/excel', ['archivo_excel' => UploadedFile::fake()->create('x.txt', 1, 'text/plain')], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJson(['success' => false]);
    }
}
