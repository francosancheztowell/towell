<?php

namespace Tests\Feature\Planeacion;

use App\Livewire\Planeacion\ProgramaTejido\DuplicarDividir;
use App\Models\Planeacion\ReqModelosCodificados;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-05 · PT-PERF-02: dividir-saldo ya no consulta (con UPDLOCK) las posiciones del telar
 * destino una vez por destino. Fija el número de consultas por destino y que las
 * posiciones asignadas son las mismas que daba obtenerSiguientePosicionDisponible()
 * (primer hueco del telar, contando los que se van creando).
 */
class ProgramaTejidoDividirQueriesTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        // DividirTejido hace DB::reconnect() tras el commit (visibilidad en SQL Server): con
        // sqlite en memoria eso abriría una base vacía. Se reconecta al mismo PDO.
        $pdo = DB::connection('sqlsrv')->getPdo();
        DB::extend('sqlsrv', fn (array $config) => new SQLiteConnection($pdo, ':memory:', '', $config));

        // Telar 210 con hueco en la posición 2; telar 211 vacío.
        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 20, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '210', 'Posicion' => 1, 'EnProceso' => 1, 'Ultimo' => '0', 'FechaInicio' => '2026-09-01 06:00:00', 'FechaFinal' => '2026-09-02 06:00:00'],
            ['Id' => 21, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '210', 'Posicion' => 3, 'EnProceso' => 0, 'Ultimo' => '1', 'FechaInicio' => '2026-09-02 06:00:00', 'FechaFinal' => '2026-09-03 06:00:00'],
        ]);
    }

    /** @return array{0: int, 1: array<int, array{0: string, 1: int}>} consultas y [telar, Posicion] de los nuevos */
    private function dividir(array $telaresDestino): array
    {
        // El original (SaldoPedido 800) se queda con lo que no se reparte: Σ saldos == 800.
        $destinos = [['telar' => '201', 'saldo' => (string) (800 - 100 * count($telaresDestino))]];
        foreach ($telaresDestino as $telar) {
            $destinos[] = ['telar' => $telar, 'saldo' => '100'];
        }

        $maxId = (int) DB::table('ReqProgramaTejido')->max('Id');
        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });

        $this->dividirSaldo(['salon_tejido_id' => 'SMIT', 'no_telar_id' => '201', 'registro_id_original' => 1, 'destinos' => $destinos])
            ->assertOk()
            ->assertJsonPath('success', true);

        $nuevos = DB::table('ReqProgramaTejido')->where('Id', '>', $maxId)->orderBy('Id')->get(['NoTelarId', 'Posicion'])
            ->map(fn ($r) => [(string) $r->NoTelarId, (int) $r->Posicion])->all();

        return [$consultas, $nuevos];
    }

    public function test_posiciones_asignadas_llenan_huecos_como_antes(): void
    {
        [, $nuevos] = $this->dividir(['210', '211', '210', '210', '211']);

        // 210: hueco 2, luego 4, 5. 211: 1, 2.
        $this->assertSame([['210', 2], ['211', 1], ['210', 4], ['210', 5], ['211', 2]], $nuevos);
    }

    public function test_consultas_por_destino(): void
    {
        [$uno] = $this->dividir(['210']);
        $this->refreshApplication();
        $this->setUp();
        [$seis] = $this->dividir(['210', '211', '212', '213', '214', '215']);

        $porDestino = ($seis - $uno) / 5;
        fwrite(STDERR, sprintf("\n[PT-PERF-02] dividir: 1 destino=%d consultas, 6 destinos=%d, por destino=%.1f\n", $uno, $seis, $porDestino));

        // Número medido tras el cambio; si sube, alguien volvió a meter una consulta por destino.
        $this->assertLessThanOrEqual(self::MAX_CONSULTAS_POR_DESTINO, $porDestino);
    }

    private const MAX_CONSULTAS_POR_DESTINO = 8; // antes de PT-05: 9

    public function test_original_sin_no_produccion_no_se_divide_ni_toca_otras_filas(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['NoProduccion' => null, 'OrdCompartida' => null]);
        $antes = DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray();

        $this->dividirSaldo([
            'salon_tejido_id' => 'SMIT', 'no_telar_id' => '201', 'registro_id_original' => 1,
            'destinos' => [['telar' => '201', 'pedido' => '100'], ['telar' => '210', 'pedido' => '100']],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals($antes, DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray());
    }

    /** La lógica que corre el modal (ya no hay endpoint HTTP). */
    private function dividirSaldo(array $payload): TestResponse
    {
        $this->actingAs($this->usuarioConPermisos([2 => ['crear']]));

        return TestResponse::fromBaseResponse(DuplicarDividir::correr(true, $payload));
    }
}
