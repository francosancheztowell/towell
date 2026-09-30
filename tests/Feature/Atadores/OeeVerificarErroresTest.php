<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * SEC-07 (19-03) en "Exportar a OEE": una regla que el usuario corrige vuelve como 422 con su
 * mensaje; un fallo interno, como 500 genérico con trace_id y sin la ruta del servidor.
 */
class OeeVerificarErroresTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private ?string $archivo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->actingAs($this->createUsuario(), 'web');
    }

    protected function tearDown(): void
    {
        putenv('OEE_ATADORES_FILE_PATH');
        if ($this->archivo && is_file($this->archivo)) {
            unlink($this->archivo);
        }
        parent::tearDown();
    }

    private function usarArchivo(string $ruta): void
    {
        putenv('OEE_ATADORES_FILE_PATH='.$ruta);
        $_ENV['OEE_ATADORES_FILE_PATH'] = $ruta;
        $_SERVER['OEE_ATADORES_FILE_PATH'] = $ruta;
    }

    public function test_rango_que_cruza_anios_iso_es_422_con_el_mensaje_de_la_regla(): void
    {
        $this->archivo = tempnam(sys_get_temp_dir(), 'oee').'.xlsx';
        touch($this->archivo);
        $this->usarArchivo($this->archivo);

        $this->getJson(route('atadores.reportes.oee.verificar', ['fecha_ini' => '2026-12-21', 'fecha_fin' => '2027-01-10']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'El rango debe pertenecer al mismo año ISO para actualizar el archivo anual OEE.');
    }

    public function test_archivo_inexistente_es_500_generico_sin_la_ruta(): void
    {
        $this->usarArchivo('/ruta/secreta/del/servidor/OEE_ATADORES.xlsx');

        $respuesta = $this->getJson(route('atadores.reportes.oee.verificar', ['fecha_ini' => '2026-09-21', 'fecha_fin' => '2026-09-27']))
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        $this->assertNotEmpty($respuesta->json('trace_id'));
        $this->assertStringNotContainsString('/ruta/secreta', (string) $respuesta->getContent());
    }

    public function test_despachar_sin_archivo_no_muestra_la_ruta(): void
    {
        $this->usarArchivo('/ruta/secreta/OEE_ATADORES.xlsx');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $respuesta = $this->postJson(route('atadores.reportes.oee.despachar'), ['fecha_ini' => '2026-09-21', 'fecha_fin' => '2026-09-27'])
            ->assertStatus(422);

        $this->assertStringNotContainsString('/ruta/secreta', (string) $respuesta->getContent());
    }
}
