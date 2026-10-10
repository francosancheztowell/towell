<?php

declare(strict_types=1);

namespace Tests\Feature\UrdEng;

use App\Models\Engomado\EngBpmLineModel;
use App\Models\Engomado\EngBpmModel;
use App\Models\Urdido\UrdBpmLineModel;
use App\Models\Urdido\UrdBpmModel;
use App\Services\Bpm\BpmReporteFilasService;
use App\Support\Bpm\AreaBpm;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

class BpmReporteFilasTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        $this->prepararSqlite();
        foreach ([UrdBpmModel::class, UrdBpmLineModel::class, EngBpmModel::class, EngBpmLineModel::class] as $modelo) {
            $this->tablaDe($modelo);
        }
    }

    public function test_mapea_valor_normaliza_clave_y_marca_el_primer_renglon_del_folio(): void
    {
        $this->assertSame(['CORRECTO', 'INCORRECTO', 'S/N'], [
            BpmReporteFilasService::mapearValor(1),
            BpmReporteFilasService::mapearValor(2),
            BpmReporteFilasService::mapearValor(0),
        ]);
        $this->assertSame(8, BpmReporteFilasService::normalizarClave(' 08 '));
        $this->assertNull(BpmReporteFilasService::normalizarClave('abc'));
        $this->assertNull(BpmReporteFilasService::normalizarClave('   '));

        $filas = BpmReporteFilasService::marcarInicio(collect([
            (object) ['Folio' => 'BU1'],
            (object) ['Folio' => 'BU1'],
            (object) ['Folio' => ''],
            (object) ['Folio' => 'BU2'],
        ]));

        $this->assertSame(['•', null, null, '•'], $filas->pluck('InicioFolio')->all());
    }

    /** @return array<string, array{AreaBpm, string, string}> */
    public static function areas(): array
    {
        return [
            'urdido' => [AreaBpm::Urdido, 'UrdBPM', 'UrdBPMLine'],
            'engomado' => [AreaBpm::Engomado, 'EngBPM', 'EngBPMLine'],
        ];
    }

    #[DataProvider('areas')]
    public function test_arma_una_fila_por_linea_y_conserva_el_folio_sin_lineas(AreaBpm $area, string $cabecera, string $lineas): void
    {
        $autoriza = $area->columnaAutoriza();
        DB::connection('sqlsrv')->table($cabecera)->insert([
            $this->cabecera('A2', '2026-01-01 23:59:00', 'Terminado', $autoriza, 'Luz'),
            $this->cabecera('A1', '2026-01-01 08:00:00', 'Autorizado', $autoriza, 'Ana'),
            $this->cabecera('A3', '2026-01-02 00:00:00', 'Terminado', $autoriza, 'Fuera'),
            $this->cabecera('A4', '2026-01-01 10:00:00', 'Creado', $autoriza, 'Pendiente'),
        ]);
        DB::connection('sqlsrv')->table($lineas)->insert([
            ['Folio' => 'A1', 'Orden' => 2, 'Actividad' => 'Orden', 'Valor' => '2'],
            ['Folio' => 'A1', 'Orden' => 1, 'Actividad' => 'Limpieza', 'Valor' => '1'],
        ]);

        $filas = (new BpmReporteFilasService)->filas($area, '2026-01-01', '2026-01-01', true);

        $this->assertSame(['A1', 'A1', 'A2'], $filas->pluck('Folio')->all());
        $this->assertSame(['Limpieza', 'Orden', null], $filas->pluck('Actividad')->all());
        $this->assertSame(['CORRECTO', 'INCORRECTO', 'S/N'], $filas->pluck('ValorTexto')->all());
        $this->assertSame(['•', null, '•'], $filas->pluck('InicioFolio')->all());
        $this->assertSame(['Ana', 'Ana', 'Luz'], $filas->pluck('NombreEmplAutoriza')->all());
        $this->assertSame([8, 8, 8], $filas->pluck('CveEmplEnt')->all());
        $this->assertSame([null, null, null], $filas->pluck('CveEmplRec')->all());
        $this->assertSame([null, null, null], $filas->pluck('CveEmplAutoriza')->all());
    }

    #[DataProvider('areas')]
    public function test_sin_filtro_de_status_incluye_creados_y_la_vista_muestra_el_valor(AreaBpm $area, string $cabecera, string $lineas): void
    {
        $autoriza = $area->columnaAutoriza();
        DB::connection('sqlsrv')->table($cabecera)->insert(
            $this->cabecera('B1', '2026-02-02 12:00:00', 'Creado', $autoriza, 'Nico')
        );
        DB::connection('sqlsrv')->table($lineas)->insert([
            ['Folio' => 'B1', 'Orden' => 1, 'Actividad' => 'Limpieza', 'Valor' => '1'],
        ]);

        $filas = (new BpmReporteFilasService)->filas($area, '2026-02-02', '2026-02-02', false);
        $this->assertCount(1, $filas);
        $this->assertSame('Creado', $filas->first()->Status);

        $ruta = $area === AreaBpm::Urdido
            ? '/urdido/reportesurdido/bpm-urdido'
            : '/engomado/reportesengomado/bpm-engomado';

        $this->actingAs($this->usuarioCon([]))
            ->get($ruta.'?fecha_ini=2026-02-02&fecha_fin=2026-02-02&solo_finalizados=0')
            ->assertOk()
            ->assertSee('Limpieza')
            ->assertSee('CORRECTO')
            ->assertSee('Nico');
    }

    public function test_rango_vacio_no_consulta_filas(): void
    {
        $this->assertCount(0, (new BpmReporteFilasService)->filas(AreaBpm::Urdido, '2026-01-01', '2026-01-01', true));
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(string $folio, string $fecha, string $status, string $columnaAutoriza, string $nombre): array
    {
        return [
            'Folio' => $folio,
            'Fecha' => $fecha,
            'Status' => $status,
            'CveEmplEnt' => ' 08 ',
            'NombreEmplEnt' => 'Entrega',
            'TurnoEntrega' => '1',
            'CveEmplRec' => 'no-num',
            'NombreEmplRec' => 'Recibe',
            'TurnoRecibe' => '2',
            'CveEmplAutoriza' => '',
            $columnaAutoriza => $nombre,
        ];
    }
}
