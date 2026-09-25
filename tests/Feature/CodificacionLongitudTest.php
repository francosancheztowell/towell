<?php

namespace Tests\Feature;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Support\Planeacion\ReqModelosCodificadosLongitudes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiembraPermisos;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CodificacionLongitudTest extends TestCase
{
    use SiembraPermisos;
    use UsesSqlsrvSqlite;

    /** @var array<int, string> */
    private array $escrituras = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->useSqlsrvSqlite();
        $this->createAuthTable();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->actingAs($this->createUsuario(['area' => 'Planeacion']));
        $this->sembrarPermisos((int) auth()->id(), [16 => 'Codificación Modelos']);
    }

    public function test_el_mapa_nombra_cada_columna_con_longitud(): void
    {
        $longitudes = ReqModelosCodificadosLongitudes::LONGITUDES;

        $this->assertSame(array_keys($longitudes), array_keys(ReqModelosCodificadosLongitudes::ETIQUETAS));
        $this->assertSame(60, $longitudes['ColorTrama']);
        $this->assertSame(100, $longitudes['FibraId']);
        $this->assertSame(10, $longitudes['CodColorBarra1']);
        $this->assertSame(10, $longitudes['CuentaRizo']);
        $this->assertSame(20, $longitudes['CalibreComb1']);
        $this->assertSame(30, $longitudes['FibraComb1']);
        $this->assertSame(200, $longitudes['CodColorC1']);
        $this->assertSame(500, $longitudes['CodigoDibujo']);
    }

    public function test_update_rechaza_color_trama_mas_largo_que_la_columna_sin_tocar_la_fila(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'FEL7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'JACQUARD',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'ColorTrama' => 'CRUDO',
            'Nombre' => 'Toalla',
        ]);

        $this->escucharEscrituras();

        $this->putJson('/planeacion/catalogos/codificacion-modelos/'.$modelo->Id, $this->payload([
            'ColorTrama' => str_repeat('A', 61),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validación incorrecta')
            ->assertJsonPath('errors.ColorTrama.0', 'El campo Color trama no debe exceder 60 caracteres.');

        $this->assertSame([], $this->escrituras);
        $this->assertSame('CRUDO', ReqModelosCodificados::find($modelo->Id)->ColorTrama);
        $this->assertSame('Toalla', ReqModelosCodificados::find($modelo->Id)->Nombre);
    }

    public function test_store_rechaza_color_trama_largo_y_no_inserta(): void
    {
        $this->escucharEscrituras();

        $this->postJson('/planeacion/catalogos/codificacion-modelos', $this->payload([
            'ColorTrama' => str_repeat('B', 61),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['ColorTrama']);

        $this->assertSame([], $this->escrituras);
        $this->assertSame(0, ReqModelosCodificados::count());
    }

    public function test_un_color_trama_en_el_limite_se_guarda_en_alta_y_en_edicion(): void
    {
        $exacto = str_repeat('C', 60);

        $this->postJson('/planeacion/catalogos/codificacion-modelos', $this->payload([
            'ColorTrama' => $exacto,
        ]))->assertCreated();

        $row = ReqModelosCodificados::where('OrdenTejido', '36440')->first();
        $this->assertSame($exacto, $row->ColorTrama);

        $otro = str_repeat('D', 60);
        $this->putJson('/planeacion/catalogos/codificacion-modelos/'.$row->Id, $this->payload([
            'ColorTrama' => $otro,
        ]))->assertOk();

        $this->assertSame($otro, $row->fresh()->ColorTrama);
    }

    public function test_recorta_antes_de_validar_y_vacio_queda_null(): void
    {
        $this->postJson('/planeacion/catalogos/codificacion-modelos', $this->payload([
            'ColorTrama' => ' '.str_repeat('E', 60).' ',
            'FibraId' => '   ',
        ]))->assertCreated();

        $row = ReqModelosCodificados::where('OrdenTejido', '36440')->first();
        $this->assertSame(str_repeat('E', 60), $row->ColorTrama);
        $this->assertNull($row->FibraId);
    }

    public function test_karl_mayer_valida_el_codigo_de_color_ya_copiado_a_la_barra(): void
    {
        $this->escucharEscrituras();

        $this->postJson('/planeacion/catalogos/codificacion-modelos', $this->payload([
            'SalonTejidoId' => 'KARL MAYER',
            'CodColorC1' => str_repeat('X', 11),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.CodColorBarra1.0', 'El campo Cód. color barra 1 no debe exceder 10 caracteres.');

        $this->assertSame([], $this->escrituras);
        $this->assertSame(0, ReqModelosCodificados::count());

        $this->postJson('/planeacion/catalogos/codificacion-modelos', $this->payload([
            'OrdenTejido' => '36441',
            'SalonTejidoId' => 'KARL MAYER',
            'CodColorC1' => 'ABCDEFGHIJ',
        ]))->assertCreated();

        $row = ReqModelosCodificados::where('OrdenTejido', '36441')->first();
        $this->assertSame('ABCDEFGHIJ', $row->CodColorBarra1);
        $this->assertNull($row->CodColorC1);
    }

    public function test_update_de_karl_mayer_con_codigo_largo_no_modifica_la_fila(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'FEL7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'KARL MAYER',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'CuentaBarra1' => '2028',
            'CodColorBarra1' => 'AZ',
        ]);

        $this->escucharEscrituras();

        $this->putJson('/planeacion/catalogos/codificacion-modelos/'.$modelo->Id, $this->payload([
            'SalonTejidoId' => 'KARL MAYER',
            'CuentaBarra1' => '2028',
            'CodColorBarra1' => str_repeat('Z', 11),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['CodColorBarra1']);

        $this->assertSame([], $this->escrituras);
        $this->assertSame('AZ', ReqModelosCodificados::find($modelo->Id)->CodColorBarra1);
        $this->assertSame('2028', (string) ReqModelosCodificados::find($modelo->Id)->CuentaBarra1);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'TamanoClave' => 'FEL7576',
            'OrdenTejido' => '36440',
            'SalonTejidoId' => 'JACQUARD',
            'ItemId' => '7576',
            'InventSizeId' => 'FEL',
            'ColorTrama' => 'CRUDO',
        ], $extra);
    }

    private function escucharEscrituras(): void
    {
        $this->escrituras = [];
        DB::listen(function ($query): void {
            if (stripos($query->sql, 'ReqModelosCodificados') === false) {
                return;
            }
            if (preg_match('/\b(update|insert|delete)\b/i', $query->sql)) {
                $this->escrituras[] = $query->sql;
            }
        });
    }
}
