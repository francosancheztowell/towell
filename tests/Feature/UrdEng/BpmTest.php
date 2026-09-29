<?php

namespace Tests\Feature\UrdEng;

use App\Models\Engomado\EngBpmModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Índice BPM (Urdido/Engomado): una vista parametrizada, SEC-07 en store/update/destroy. */
class BpmTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaDe(UrdBpmModel::class);
        $this->tablaDe(EngBpmModel::class);
        $this->tablaDe(SYSUsuario::class);
        $this->tablaDe(URDCatalogoMaquina::class);

        $db = DB::connection('sqlsrv');
        $fila = ['Status' => 'Terminado', 'Fecha' => '2026-09-20 07:45:00', 'CveEmplRec' => '100', 'NombreEmplRec' => 'Ana Recibe',
            'TurnoRecibe' => '1', 'CveEmplEnt' => '200', 'NombreEmplEnt' => 'Beto Entrega', 'TurnoEntrega' => '3', 'CveEmplAutoriza' => '300'];
        $db->table('UrdBPM')->insert(['Id' => 1, 'Folio' => 'UB0001', 'NombreEmplAutoriza' => 'Caro Urd'] + $fila);
        $db->table('EngBPM')->insert(['Id' => 1, 'Folio' => 'EB0001', 'NomEmplAutoriza' => 'Caro Eng'] + $fila);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function variantes(): array
    {
        return [
            'urdido' => ['/urd-bpm', 'UrdBPM', 'Caro Urd', '2026-09-20'],
            'engomado' => ['/eng-bpm', 'EngBPM', 'Caro Eng', '2026-09-20T07:45'],
        ];
    }

    #[DataProvider('variantes')]
    public function test_indice_renderiza_con_config_y_fila_sin_js_inline(string $url, string $tabla, string $autoriza, string $fechaEdicion): void
    {
        $usuario = $this->usuarioCon(['BPM (Buenas Practicas Manufactura) Urd' => ['acceso', 'crear', 'modificar'], 'BPM (Buenas Practicas Manufactura) Eng' => ['acceso', 'crear', 'modificar']]);
        $usuario->setAttribute('nombre', "Ana O'Neil <b>&"); // el config va en data-bpm='…': un apóstrofo no debe romper el atributo
        $html = $this->actingAs($usuario)
            ->get($url)
            ->assertOk()
            ->assertSee('data-bpm=', false)
            ->assertSee($autoriza)
            ->getContent();

        // Columna autoriza de cada variante y fecha del modal Editar desde data-* (bug Engomado: quedaba vacía).
        $this->assertStringContainsString('data-fecha-edicion="'.$fechaEdicion.'"', $html);
        $this->assertStringContainsString($url === '/urd-bpm' ? 'type="date" id="edit_Fecha"' : 'type="datetime-local" id="edit_Fecha"', $html);
        $this->assertStringContainsString('data-bpm-accion="checklist"', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('Swal.fire', $html);

        $this->assertSame(1, preg_match("/data-bpm='([^']*)'/", $html, $m));
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertSame("Ana O'Neil <b>&", $config['usuario']);
        $this->assertStringEndsWith('/__FOLIO__', $config['rutas']['checklist']);
    }

    public function test_la_vista_comun_no_trae_js_inline(): void
    {
        foreach (['comun/bpm', 'BPM-Urdido/index'] as $v) {
            $fuente = file_get_contents(resource_path("views/modulos/urdido/{$v}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente, $v);
            $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc=)/i', $fuente, $v);
        }
        $fuente = file_get_contents(resource_path('views/modulos/engomado/BPM-Engomado/index.blade.php'));
        $this->assertStringContainsString("'variante' => 'engomado'", $fuente);
    }

    #[DataProvider('variantes')]
    public function test_store_con_error_no_expone_la_excepcion(string $url): void
    {
        // Sin tabla de folios: FolioHelper lanza (SQLSTATE ... no such table) dentro del try de store().
        $this->actingAs($this->usuarioCon(['BPM (Buenas Practicas Manufactura) Urd' => ['acceso', 'crear'], 'BPM (Buenas Practicas Manufactura) Eng' => ['acceso', 'crear']]))
            ->from($url)
            ->post($url, ['Fecha' => '2026-09-20', 'NombreEmplRec' => 'Ana', 'Status' => 'Creado', 'MaquinaId' => '401'])
            ->assertRedirect($url)
            ->assertSessionHas('error', fn (string $m) => str_starts_with($m, 'Error al crear el registro (ref: ')
                && ! str_contains($m, 'SQLSTATE') && ! str_contains($m, 'SSYSFoliosSecuencias'));
    }

    #[DataProvider('variantes')]
    public function test_update_y_destroy(string $url, string $tabla): void
    {
        // destroy se gatea por id de módulo (35 Urd, 41 Eng), no en modo auditar.
        $usuario = $this->usuarioCon([35 => ['acceso', 'modificar', 'eliminar'], 41 => ['acceso', 'modificar', 'eliminar']]);

        $this->actingAs($usuario)->from($url)
            ->put("{$url}/1", ['Folio' => 'X1', 'Fecha' => '2026-09-21T08:30', 'Status' => 'Creado', 'NombreEmplRec' => 'Dani'])
            ->assertRedirect($url)->assertSessionHas('success');
        $this->assertSame('Dani', DB::connection('sqlsrv')->table($tabla)->where('Id', 1)->value('NombreEmplRec'));

        // No existe: el mensaje lleva referencia, no el texto de ModelNotFoundException.
        $this->actingAs($usuario)->from($url)->delete("{$url}/999")
            ->assertRedirect($url)
            ->assertSessionHas('error', fn (string $m) => str_starts_with($m, 'Error al eliminar el registro (ref: ') && ! str_contains($m, 'No query results'));

        $this->actingAs($usuario)->from($url)->delete("{$url}/1")->assertRedirect($url)->assertSessionHas('success');
        $this->assertSame(0, DB::connection('sqlsrv')->table($tabla)->count());
    }
}
