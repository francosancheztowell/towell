<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * PERF-08 (19-05): al abrir Programación de Requerimientos, los telares que llegan sin id se
 * buscaban en tej_inventario_telares con una consulta por telar. Ahora es una para todos.
 */
class ProgramacionRequerimientosQueriesTest extends TestCase
{
    use InventarioUrdEngSqlite;

    private const MODULO = 52; // Programa Urd / Eng

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInventario();
        $this->tablaDe(URDCatalogoMaquina::class);
        $this->withoutVite();
    }

    public function test_completa_ids_con_una_consulta_y_los_mismos_filtros(): void
    {
        $tabla = DB::connection('sqlsrv')->table('tej_inventario_telares');
        $tabla->insert([
            ['no_telar' => '201', 'status' => 'Activo', 'tipo' => 'Rizo', 'fecha' => '2026-09-28 00:00:00', 'turno' => '1'],
            ['no_telar' => '201', 'status' => 'Activo', 'tipo' => 'Pie', 'fecha' => '2026-09-28 00:00:00', 'turno' => '1'],
            ['no_telar' => '202', 'status' => 'Activo', 'tipo' => 'Rizo', 'fecha' => '2026-09-29 00:00:00', 'turno' => '2'],
            ['no_telar' => '202', 'status' => 'Activo', 'tipo' => 'Rizo', 'fecha' => '2026-09-30 00:00:00', 'turno' => '3'],
            ['no_telar' => '203', 'status' => 'Inactivo', 'tipo' => 'Rizo', 'fecha' => '2026-09-28 00:00:00', 'turno' => '1'],
            ['no_telar' => '204', 'status' => 'Activo', 'tipo' => 'Pie', 'fecha' => '2026-09-28 00:00:00', 'turno' => '1'],
            ['no_telar' => '205', 'status' => 'Activo', 'tipo' => 'Rizo', 'fecha' => '2026-09-28 00:00:00', 'turno' => '1'],
        ]);

        $telares = [
            ['no_telar' => '201', 'tipo' => 'PIE'],                                               // → id 2 (tipo)
            ['no_telar' => '202', 'tipo' => 'RIZO', 'fecha' => '2026-09-30', 'turno' => '3'],   // → id 4 (fecha + turno)
            ['no_telar' => '203', 'tipo' => 'RIZO'],                                              // inactivo → sin id
            ['no_telar' => '204', 'tipo' => 'RIZO'],                                              // otro tipo → sin id
            ['no_telar' => '205'],                                                                 // sin tipo → id 7
            ['id' => 99, 'no_telar' => '206', 'tipo' => 'RIZO'],                                  // ya trae id
        ];

        DB::connection('sqlsrv')->enableQueryLog();
        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']], 'Urdido'))
            ->get(route('programa.urd.eng.programacion.requerimientos', ['telares' => json_encode($telares)]))
            ->assertOk()
            ->getContent();
        $consultas = collect(DB::connection('sqlsrv')->getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'tej_inventario_telares'));

        // Antes: 5 (una por telar sin id). Después: 1.
        $this->assertCount(1, $consultas);
        // no_telar es varchar: '201' como llave de array se vuelve int; debe ligarse como texto
        // (con int SQL Server convertiría la columna y fallaría con un telar no numérico).
        foreach ($consultas->first()['bindings'] as $valor) {
            $this->assertIsString($valor);
        }

        $config = $this->jsonDeAtributo($html, 'data-pagina');
        $porTelar = collect($config['telares'])->keyBy('no_telar');
        $this->assertSame(2, $porTelar['201']['id']);
        $this->assertSame(4, $porTelar['202']['id']);
        $this->assertSame('2026-09-30', $porTelar['202']['fecha']);
        $this->assertSame('3', (string) $porTelar['202']['turno']);
        $this->assertArrayNotHasKey('id', $porTelar['203']);
        $this->assertArrayNotHasKey('id', $porTelar['204']);
        $this->assertSame(7, $porTelar['205']['id']);
        $this->assertSame(99, $porTelar['206']['id']);
    }

    private function jsonDeAtributo(string $html, string $atributo): array
    {
        $this->assertMatchesRegularExpression("/{$atributo}='([^']*)'/", $html);
        preg_match("/{$atributo}='([^']*)'/", $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
