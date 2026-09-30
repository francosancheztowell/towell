<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\BomMaterialesService;
use App\Services\ProgramaUrdEng\ResumenSemanasService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\ProgramaUrdEng\Concerns\ModuloProgramaUrdEng;
use Tests\TestCase;

/**
 * SEC-07 (19-05): un 500 de Programa Urd / Eng no manda el texto de la excepción (SQL, tablas,
 * rutas) al navegador; manda un mensaje fijo, 'error' con el mismo texto (pantallas viejas) y
 * el trace_id para buscarlo en el log. Sin tablas del módulo, cada endpoint revienta con una
 * QueryException real de sqlite.
 */
class ErroresProgramaUrdEngTest extends TestCase
{
    use ModuloProgramaUrdEng;

    private const SECRETO = 'SQLSTATE[42S02] tabla secreta dbo.Interna';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->actingAs($this->usuarioCon([self::MODULO_PROGRAMA_URD_ENG => ['acceso', 'crear', 'modificar', 'eliminar']]));
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}> */
    public static function endpoints(): array
    {
        $karlMayer = [
            'no_telar' => 'KM1', 'barras' => '1', 'fibra' => 'A20', 'tamano' => 'T1', 'metros' => 10, 'fecha_programada' => '2026-10-01',
            'tipo_atado' => 'Normal', 'bom_id' => 'B1', 'julios' => [1], 'hilos' => [1], 'materiales' => [['itemId' => 'X']],
        ];

        return [
            'crear ordenes' => ['post', 'programa.urd.eng.crear.ordenes', [
                'grupo' => ['salonTejidoId' => 'Jacquard', 'fibra' => 'A20'], 'materialesEngomado' => [['itemId' => 'X']],
                'construccionUrdido' => [['julios' => 1]], 'datosEngomado' => ['nucleo' => 'N'],
            ], 'Error al crear las órdenes. No se guardó nada.'],
            'crear orden karl mayer' => ['post', 'programa.urd.eng.crear.orden.karl.mayer', $karlMayer, 'Error al crear la orden Karl Mayer. No se guardó nada.'],
            'inventario telares' => ['get', 'programa.urd.eng.inventario.telares', [], 'Error al obtener inventario de telares'],
            // Antes la tapaba reservas/{noTelar} (bug de orden de rutas).
            'diagnostico reservas' => ['get', 'programa.urd.eng.reservas.diagnostico', [], 'Error al diagnosticar reservas'],
            'reservas por telar' => ['get', 'programa.urd.eng.reservas.porTelar', ['noTelar' => '299'], 'Error al obtener las reservas del telar'],
            // QueryException es RuntimeException: antes salía como 404 con el SQL en 'message'.
            'actualizar telar' => ['post', 'programa.urd.eng.actualizar.telar', ['no_telar' => '299', 'metros' => 10], 'Error al actualizar el telar'],
            'liberar telar' => ['post', 'programa.urd.eng.liberar.telar', ['no_telar' => '299', 'id' => 1], 'Error al liberar el telar'],
            'reservar' => ['post', 'programa.urd.eng.reservar.inventario', ['NoTelarId' => '299', 'ItemId' => 'X', 'tej_inventario_telares_id' => 1], 'Error al reservar la pieza'],
        ];
    }

    /** @param  array<string, mixed>  $datos */
    #[DataProvider('endpoints')]
    public function test_un_500_no_filtra_la_excepcion_y_trae_trace_id(string $metodo, string $ruta, array $datos, string $mensaje): void
    {
        $respuesta = $metodo === 'get' ? $this->getJson(route($ruta, $datos)) : $this->postJson(route($ruta), $datos);

        $respuesta->assertStatus(500)
            ->assertJson(['success' => false, 'message' => $mensaje, 'error' => $mensaje])
            ->assertJsonStructure(['trace_id']);
        $this->assertStringNotContainsStringIgnoringCase('no such table', $respuesta->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $respuesta->getContent());
    }

    public function test_materiales_completo_conserva_resumen_y_detalle_vacios(): void
    {
        $this->mock(BomMaterialesService::class)->shouldReceive('getMaterialesUrdidoCompleto')->andThrow(new RuntimeException(self::SECRETO));

        $respuesta = $this->getJson(route('programa.urd.eng.materiales.urdido.completo', ['bomId' => 'B1']))
            ->assertStatus(500)
            ->assertJson(['resumen' => [], 'detalle' => [], 'error' => 'Error al obtener los materiales de urdido'])
            ->assertJsonStructure(['trace_id']);
        $this->assertStringNotContainsString('secreta', $respuesta->getContent());
    }

    public function test_resumen_semanas_conserva_data_y_semanas(): void
    {
        $this->partialMock(ResumenSemanasService::class)->shouldReceive('generar')->andThrow(new RuntimeException(self::SECRETO));

        $respuesta = $this->postJson(route('programa.urd.eng.programacion.resumen.semanas'), ['telares' => [['no_telar' => '1']]])
            ->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'Error al obtener resumen de semanas', 'data' => ['rizo' => [], 'pie' => []]])
            ->assertJsonCount(5, 'semanas')
            ->assertJsonStructure(['trace_id']);
        $this->assertStringNotContainsString('secreta', $respuesta->getContent());
    }

    public function test_error_de_negocio_llega_con_su_mensaje(): void
    {
        // Sin destino: DomainException escrita por CrearOrdenesService, 422 con el texto (message y error).
        $this->postJson(route('programa.urd.eng.crear.ordenes'), [
            'grupo' => ['fibra' => 'A20'], 'materialesEngomado' => [['itemId' => 'X']], 'construccionUrdido' => [['julios' => 1]], 'datosEngomado' => ['nucleo' => 'N'],
        ])->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'Debe seleccionar un destino antes de crear la orden.',
            'error' => 'Debe seleccionar un destino antes de crear la orden.',
        ]);
    }

    public function test_entrada_invalida_es_422_y_no_500(): void
    {
        // La validación vivía dentro del try y el catch genérico la volvía un 500.
        $this->postJson(route('programa.urd.eng.programar.telar'), [])->assertStatus(422)->assertJsonValidationErrors('no_telar');
        $this->postJson(route('programa.urd.eng.reservar.inventario'), ['NoTelarId' => '299'])->assertStatus(422)->assertJsonValidationErrors('ItemId');
    }
}
