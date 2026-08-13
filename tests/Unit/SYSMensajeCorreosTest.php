<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Sistema\SYSMensaje;
use PHPUnit\Framework\TestCase;

class SYSMensajeCorreosTest extends TestCase
{
    public function test_limpia_valida_y_deduplica_correos(): void
    {
        $this->assertSame(
            ['ana@towel.com', 'luis@towel.com'],
            SYSMensaje::soloCorreosValidos([
                '  ana@towel.com ', 'ana@towel.com', 'sin-arroba', null, '', 'luis@towel.com',
            ])
        );
    }

    public function test_ignora_columnas_que_no_son_de_reportes(): void
    {
        // Sin tocar la BD: la lista blanca corta antes de armar la consulta.
        $this->assertSame([], SYSMensaje::getCorreosPorModulo('Token'));
        $this->assertSame([], SYSMensaje::getCorreosPorModulo('Andon; DROP TABLE'));
    }
}
