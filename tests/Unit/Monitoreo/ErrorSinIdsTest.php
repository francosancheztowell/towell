<?php

namespace Tests\Unit\Monitoreo;

use App\Services\Monitoreo\ErrorRecorder;
use PHPUnit\Framework\TestCase;

class ErrorSinIdsTest extends TestCase
{
    public function test_los_ids_de_la_ruta_se_agrupan_y_el_endpoint_queda_legible(): void
    {
        $this->assertSame('HTTP 0 POST /configuracion/usuarios/{id}/duplicar', ErrorRecorder::sinIds('HTTP 0 POST /configuracion/usuarios/13/duplicar'));
        $this->assertSame('HTTP 500 GET /ventas/datos/compara', ErrorRecorder::sinIds('HTTP 500 GET /ventas/datos/compara'));
        $this->assertSame('/x/{id}', ErrorRecorder::sinIds('/x/3f2a1b4c-1111-4222-8333-123456789abc'));
    }
}
