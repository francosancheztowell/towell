<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-urd-eng-mant (Urdido, finalizar): el destino que decide si Vueltas y
 * Diámetro son obligatorios (Karl Mayer) ahora es `$orden->SalonTejidoId ?: EngProgramaEngomado
 * ->value('SalonTejidoId')`. Se fija que el respaldo desde Engomado siga funcionando con NULL y
 * con cadena vacía, y que el SalonTejidoId propio de la orden gane.
 */
final class QueryEngVentasUrdidoTest extends TestCase
{
    use ModuloUrdEng;

    private const BASE = '/urdido/modulo-produccion-urdido';

    private const ERROR_KM = 'No se puede finalizar: hay 1 registro(s) sin Vueltas o Diámetro. Estos campos son obligatorios para Karl Mayer.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(UrdProgramaUrdido::class, ['Incorrecto']);
        $this->tablaDe(UrdJuliosOrden::class);
        $this->tablaDe(UrdProduccionUrdido::class);
        $this->tablaDe(EngProgramaEngomado::class);
        $this->tablaDe(SYSUsuario::class, ['area']);
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        DB::connection('sqlsrv')->statement('CREATE TABLE dbo.ManFallasParos (Id INTEGER PRIMARY KEY, Folio TEXT, Estatus TEXT, Depto TEXT, MaquinaId TEXT)');
    }

    /** @return array<string, array{?string, ?string, bool}> */
    public static function destinos(): array
    {
        return [
            'orden sin salón (NULL), Engomado Karl Mayer' => [null, 'KARL MAYER', true],
            'orden con salón vacío, Engomado Karl Mayer' => ['', 'Karl Mayer', true],
            'orden Karl Mayer, sin fila en Engomado' => ['Karl Mayer', null, true],
            'orden Jacquard gana sobre Engomado Karl Mayer' => ['JACQUARD', 'KARL MAYER', false],
            'sin salón en ningún lado' => [null, null, false],
            'orden sin salón, Engomado Smith' => [null, 'SMITH', false],
        ];
    }

    #[DataProvider('destinos')]
    public function test_finalizar_exige_vueltas_y_diametro_solo_si_el_destino_es_karl_mayer(?string $salonOrden, ?string $salonEngomado, bool $esKm): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('UrdProgramaUrdido')->insert([
            'Id' => 1, 'Folio' => 'F-1', 'Status' => 'En Proceso', 'MaquinaId' => 'Mc Coy 2',
            'Metros' => 6000, 'Incorrecto' => 0, 'SalonTejidoId' => $salonOrden,
        ]);
        if ($salonEngomado !== null) {
            $db->table('EngProgramaEngomado')->insert(['Folio' => 'F-1', 'SalonTejidoId' => $salonEngomado, 'Status' => 'Programado']);
        }
        // Registro completo salvo Vueltas/Diámetro.
        $db->table('UrdProduccionUrdido')->insert([
            'Folio' => 'F-1', 'Hilos' => 640, 'NoJulio' => '7', 'KgBruto' => 300, 'KgNeto' => 250,
            'HoraInicial' => '07:00', 'HoraFinal' => '08:00', 'Vueltas' => null, 'Diametro' => null,
        ]);

        $r = $this->actingAs($this->usuarioCon([154 => ['acceso', 'modificar'], 'Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/finalizar', ['orden_id' => 1]);

        if ($esKm) {
            $r->assertStatus(422)->assertJsonPath('error', self::ERROR_KM);
            $this->assertSame('En Proceso', $db->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
        } else {
            $r->assertOk()->assertJsonPath('success', true);
            $this->assertSame('Finalizado', $db->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
        }
    }
}
