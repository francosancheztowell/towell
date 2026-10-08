<?php

namespace Tests\Unit\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fija la regla Flog -> TipoPedido (2 primeras letras en mayúsculas; vacío limpia ambos).
 */
class AsignarFlogTipoPedidoTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string, 2: ?string}> */
    public static function casos(): array
    {
        return [
            'flog normal' => ['CE-12345', 'CE-12345', 'CE'],
            'minusculas' => ['rs-001', 'rs-001', 'RS'],
            'dos letras' => ['ab', 'ab', 'AB'],
            'una letra' => ['X', 'X', null],
            'vacio' => ['', null, null],
            'null' => [null, null, null],
            'cero string' => ['0', null, null],
        ];
    }

    #[DataProvider('casos')]
    public function test_asigna_flog_y_deriva_tipo_pedido(?string $flog, ?string $flogEsperado, ?string $tipoEsperado): void
    {
        $r = new ReqProgramaTejido;
        $r->TipoPedido = 'ZZ';

        $r->asignarFlog($flog);

        $this->assertSame($flogEsperado, $r->FlogsId);
        $this->assertSame($tipoEsperado, $r->TipoPedido);
    }
}
