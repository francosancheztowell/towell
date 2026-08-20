<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\NotificacionTelegramDesarrolladorService;
use App\Models\Planeacion\ReqProgramaTejido;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * El mensaje se envia con parse_mode Markdown. Telegram interpreta _ * ` [ y
 * responde 400 si el texto los trae sueltos; como el envio esta dentro de un
 * try/catch que solo loguea, la notificacion se perderia en silencio.
 */
class NotificacionTelegramDesarrolladorMensajeTest extends TestCase
{
    private function construirMensaje(array $validated, string $codigoDibujo = 'ABC123'): string
    {
        $clase = new ReflectionClass(NotificacionTelegramDesarrolladorService::class);
        $servicio = $clase->newInstanceWithoutConstructor();
        $metodo = $clase->getMethod('construirMensajeProcesoCompletado');
        $metodo->setAccessible(true);

        return $metodo->invoke($servicio, $validated, new ReqProgramaTejido, $codigoDibujo);
    }

    /** @return array<string, mixed> */
    private function datosBase(array $extra = []): array
    {
        return array_merge([
            'NoTelarId' => '101',
            'NoProduccion' => '12345',
            'TotalPasadasDibujo' => 100,
        ], $extra);
    }

    public function test_escapa_el_guion_bajo_del_nombre_del_desarrollador(): void
    {
        $mensaje = $this->construirMensaje($this->datosBase(['Desarrollador' => 'JUAN_PEREZ']));

        $this->assertStringContainsString('JUAN\_PEREZ', $mensaje);
        $this->assertStringNotContainsString('JUAN_PEREZ', $mensaje);
    }

    public function test_escapa_asteriscos_sin_romper_las_etiquetas_en_negrita(): void
    {
        $mensaje = $this->construirMensaje($this->datosBase(['NumeroJulioRizo' => 'J*7']));

        $this->assertStringContainsString('J\*7', $mensaje);
        // Las etiquetas del propio mensaje siguen siendo Markdown valido.
        $this->assertStringContainsString('*Julio Rizo:*', $mensaje);
    }

    public function test_escapa_el_codigo_de_dibujo(): void
    {
        $mensaje = $this->construirMensaje($this->datosBase(), 'COD_9[x');

        $this->assertStringContainsString('COD\_9\[x', $mensaje);
    }

    public function test_un_texto_sin_caracteres_especiales_no_se_altera(): void
    {
        $mensaje = $this->construirMensaje($this->datosBase(['Desarrollador' => 'JUAN PEREZ']));

        $this->assertStringContainsString('JUAN PEREZ', $mensaje);
        $this->assertStringNotContainsString('\\', $mensaje);
    }
}
